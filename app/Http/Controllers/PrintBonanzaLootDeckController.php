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
            ->get(['id', 'slug', 'name', 'image', 'sort_order']);

        if (extension_loaded('imagick')) {
            try {
                return $this->renderWithImagick($cards);
            } catch (\Throwable) {
            }
        }

        return $this->renderWithDomPdf($cards);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, LootCard>  $cards
     */
    private function renderWithImagick($cards): \Illuminate\Http\Response
    {
        @ini_set('memory_limit', '512M');
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

        // Write pages to a temp file instead of accumulating in memory.
        $tmpFile = tempnam(sys_get_temp_dir(), 'bonanza_').'.pdf';

        $first = true;
        foreach ($cards->chunk($perPage) as $pageCards) {
            $page = new \Imagick;
            $page->newImage($pageW, $pageH, new \ImagickPixel('white'));
            $page->setImageResolution($dpi, $dpi);
            $page->setImageFormat('pdf');

            $i = 0;
            foreach ($pageCards as $card) {
                $x = $gapX + ($i % $cols) * ($cardW + $gapX);
                $y = $gapY + intdiv($i, $cols) * ($cardH + $gapY);
                $i++;

                try {
                    $blob = Storage::disk('public')->get($card->image);
                    if (! $blob) {
                        continue;
                    }
                    $img = new \Imagick;
                    $img->readImageBlob($blob);
                    unset($blob);
                    $img->resizeImage($cardW, $cardH, \Imagick::FILTER_LANCZOS, 1);
                    $page->compositeImage($img, \Imagick::COMPOSITE_OVER, $x, $y);
                    $img->clear();
                    $img->destroy();
                } catch (\Throwable) {
                }

                $draw = new \ImagickDraw;
                $draw->setStrokeColor(new \ImagickPixel('#cccccc'));
                $draw->setStrokeWidth(1);
                $draw->setFillOpacity(0);
                $draw->rectangle($x, $y, $x + $cardW, $y + $cardH);
                $page->drawImage($draw);
            }

            // Flatten to a single raster layer (frees composite overhead).
            $page->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $page->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $page->setImageCompressionQuality(85);

            if ($first) {
                $page->writeImage($tmpFile);
                $first = false;
            } else {
                // Append pages to the existing PDF file.
                $existing = new \Imagick;
                $existing->readImage($tmpFile);
                $existing->addImage($page);
                $existing->writeImages($tmpFile, true);
                $existing->clear();
                $existing->destroy();
            }

            $page->clear();
            $page->destroy();
        }

        if (! file_exists($tmpFile) || filesize($tmpFile) === 0) {
            @unlink($tmpFile);
            throw new \RuntimeException('Failed to generate PDF');
        }

        $content = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bonanza-loot-deck.pdf"',
        ]);
    }

    /**
     * DomPDF fallback — tiles base64-encoded card images into a simple HTML grid.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, LootCard>  $cards
     */
    private function renderWithDomPdf($cards): \Illuminate\Http\Response
    {
        @ini_set('memory_limit', '512M');
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

        foreach ($cards->chunk(4) as $page) {
            $html .= '<div class="page">';
            foreach ($page as $card) {
                $data = Storage::disk('public')->get($card->image);
                if (! $data) {
                    continue;
                }
                $b64 = base64_encode($data);
                unset($data);
                $html .= '<span class="cell"><img src="data:image/png;base64,'.$b64.'" alt="'.e($card->name).'" /></span>';
                unset($b64);
            }
            $html .= '</div>';
        }
        $html .= '</body></html>';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('letter', 'portrait');

        return $pdf->stream('bonanza-loot-deck.pdf');
    }
}
