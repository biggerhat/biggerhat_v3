<?php

namespace App\Http\Controllers;

use App\Models\LootCard;
use Illuminate\Support\Facades\Storage;

/**
 * Printer-friendly PDF of the Bonanza Brawl Loot Deck. Composes the card images
 * (captured light-mode, mirrored Side B) into a 300-DPI cut-grid — 4 cards per
 * Letter page — using Imagick. Imagick builds the PDF in C, which stays well
 * under the request timeout where a 54-image DomPDF render did not.
 */
class PrintBonanzaLootDeckController extends Controller
{
    public function __invoke(): \Illuminate\Http\Response
    {
        @set_time_limit(120);

        $dpi = 300;
        $pageW = (int) (8.5 * $dpi);   // Letter @ 300 DPI
        $pageH = (int) (11 * $dpi);
        $cardW = (int) (2.75 * $dpi);  // tarot
        $cardH = (int) (4.75 * $dpi);
        $cols = 2;
        $rows = 2;
        $perPage = $cols * $rows;
        $gapX = (int) (($pageW - $cols * $cardW) / ($cols + 1));
        $gapY = (int) (($pageH - $rows * $cardH) / ($rows + 1));

        $cards = LootCard::query()
            ->whereNotNull('image')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $pdf = new \Imagick;
        $pdf->setResolution($dpi, $dpi);

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
                    $img = new \Imagick;
                    $img->readImageBlob(Storage::disk('public')->get($card->image));
                    $img->resizeImage($cardW, $cardH, \Imagick::FILTER_LANCZOS, 1);
                    // Transparent areas fall through to the white page.
                    $page->compositeImage($img, \Imagick::COMPOSITE_OVER, $x, $y);
                    $img->clear();
                } catch (\Throwable) {
                    // Skip an unreadable image rather than fail the whole deck.
                }

                // Faint cut guide around the card.
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

        // Always emit at least one (blank) page so an empty deck still streams a PDF.
        if ($pdf->getNumberImages() === 0) {
            $blank = new \Imagick;
            $blank->newImage($pageW, $pageH, new \ImagickPixel('white'));
            $blank->setImageResolution($dpi, $dpi);
            $blank->setImageFormat('pdf');
            $pdf->addImage($blank);
            $blank->clear();
        }

        $pdf->setImageFormat('pdf');
        // JPEG-compress the page rasters so a 14-page deck stays a few MB; q92 is
        // visually lossless at print size.
        $pdf->setImageCompression(\Imagick::COMPRESSION_JPEG);
        $pdf->setImageCompressionQuality(92);

        $blob = $pdf->getImagesBlob();
        $pdf->clear();

        return response($blob, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bonanza-loot-deck.pdf"',
        ]);
    }
}
