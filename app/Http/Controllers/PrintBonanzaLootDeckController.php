<?php

namespace App\Http\Controllers;

use App\Models\LootCard;
use Illuminate\Support\Facades\Storage;

/**
 * Printer-friendly PDF of the Bonanza Brawl Loot Deck. Composes the stored card
 * images into a cut-grid at tarot size (2.75 × 4.75 in), 4 cards per Letter
 * page. Uses Imagick when available (fast, 300-DPI native); falls back to
 * DomPDF (universally installed) otherwise.
 */
class PrintBonanzaLootDeckController extends Controller
{
    public function __invoke(): \Illuminate\Http\Response
    {
        $cards = LootCard::query()
            ->whereNotNull('image')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $images = $cards->map(fn (LootCard $card) => [
            'data' => Storage::disk('public')->get($card->image),
            'name' => $card->name,
        ])->filter(fn ($img) => $img['data'] !== null)->values()->all();

        if (extension_loaded('imagick')) {
            try {
                return $this->renderWithImagick($images);
            } catch (\Throwable) {
                // Imagick loaded but PDF delegate missing or image read failed
                // (common on older distros) — fall through to DomPDF.
            }
        }

        return $this->renderWithDomPdf($images);
    }

    /**
     * @param  array<int, array{data: string, name: string}>  $images
     */
    private function renderWithImagick(array $images): \Illuminate\Http\Response
    {
        @set_time_limit(120);

        $dpi = 300;
        $pageW = (int) (8.5 * $dpi);
        $pageH = (int) (11 * $dpi);
        $cardW = (int) (2.75 * $dpi);
        $cardH = (int) (4.75 * $dpi);
        $cols = 2;
        $rows = 2;
        $perPage = $cols * $rows;
        $gapX = (int) (($pageW - $cols * $cardW) / ($cols + 1));
        $gapY = (int) (($pageH - $rows * $cardH) / ($rows + 1));

        $pdf = new \Imagick;
        $pdf->setResolution($dpi, $dpi);

        foreach (array_chunk($images, $perPage) as $pageImages) {
            $page = new \Imagick;
            $page->newImage($pageW, $pageH, new \ImagickPixel('white'));
            $page->setImageResolution($dpi, $dpi);
            $page->setImageFormat('pdf');

            $i = 0;
            foreach ($pageImages as $image) {
                $x = $gapX + ($i % $cols) * ($cardW + $gapX);
                $y = $gapY + intdiv($i, $cols) * ($cardH + $gapY);
                $i++;

                try {
                    $img = new \Imagick;
                    $img->readImageBlob($image['data']);
                    $img->resizeImage($cardW, $cardH, \Imagick::FILTER_LANCZOS, 1);
                    $page->compositeImage($img, \Imagick::COMPOSITE_OVER, $x, $y);
                    $img->clear();
                } catch (\Throwable) {
                }

                $draw = new \ImagickDraw;
                $draw->setStrokeColor(new \ImagickPixel('#cccccc'));
                $draw->setStrokeWidth(1);
                $draw->setFillOpacity(0);
                $draw->rectangle($x, $y, $x + $cardW, $y + $cardH);
                $page->drawImage($draw);
            }

            $pdf->addImage($page);
            $page->clear();
        }

        if ($pdf->getNumberImages() === 0) {
            $blank = new \Imagick;
            $blank->newImage($pageW, $pageH, new \ImagickPixel('white'));
            $blank->setImageResolution($dpi, $dpi);
            $blank->setImageFormat('pdf');
            $pdf->addImage($blank);
            $blank->clear();
        }

        $pdf->setImageFormat('pdf');
        $pdf->setImageCompression(\Imagick::COMPRESSION_JPEG);
        $pdf->setImageCompressionQuality(92);
        $blob = $pdf->getImagesBlob();
        $pdf->clear();

        return response($blob, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bonanza-loot-deck.pdf"',
        ]);
    }

    /**
     * DomPDF fallback — tiles base64-encoded card images into a simple HTML grid.
     * Slower than Imagick on large decks but universally available.
     *
     * @param  array<int, array{data: string, name: string}>  $images
     */
    private function renderWithDomPdf(array $images): \Illuminate\Http\Response
    {
        @set_time_limit(120);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Bonanza Loot Deck</title><style>'
            .'@page { margin: 0.3in; }'
            .'* { box-sizing: border-box; }'
            .'body { margin: 0; }'
            .'.page { page-break-after: always; }'
            .'.page:last-child { page-break-after: auto; }'
            .'.cell { display: inline-block; vertical-align: top; padding: 0.03in; border: 1px dashed #ccc; margin: 0 0.02in 0.04in 0.02in; }'
            .'.cell img { display: block; width: 2.75in; height: 4.75in; }'
            .'</style></head><body>';

        foreach (array_chunk($images, 4) as $page) {
            $html .= '<div class="page">';
            foreach ($page as $image) {
                $b64 = base64_encode($image['data']);
                $html .= '<span class="cell"><img src="data:image/png;base64,'.$b64.'" alt="'.e($image['name']).'" /></span>';
            }
            $html .= '</div>';
        }
        $html .= '</body></html>';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('letter', 'portrait');

        return $pdf->stream('bonanza-loot-deck.pdf');
    }
}
