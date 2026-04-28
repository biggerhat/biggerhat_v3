<?php

namespace App\Http\Controllers\TOS\Database;

use App\Enums\PDFImageTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\TOS\UnitSculpt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfController extends Controller
{
    /**
     * Streams a single sculpt's card art as a print-ready PDF. Mirrors the
     * Malifaux PDFController flow: base64-encode the combo (or front/back
     * separately) image, render through the existing CharacterImageBlank
     * blade, stream as PDF.
     *
     * Query params:
     *   ?separate_images=1 — emit front + back as two separate cards rather
     *                       than the merged combination_image.
     */
    public function download(Request $request, UnitSculpt $sculpt)
    {
        $separate = $request->boolean('separate_images');
        $name = $sculpt->unit->name ?? 'TOS Unit';
        $images = [];

        if ($separate && $sculpt->front_image && $sculpt->back_image) {
            foreach (['front_image', 'back_image'] as $col) {
                if ($img = $this->imagePayload($sculpt->{$col}, PDFImageTypeEnum::Single, $name)) {
                    $images[] = $img;
                }
            }
        } elseif ($img = $this->imagePayload($sculpt->combination_image, PDFImageTypeEnum::Double, $name)) {
            $images[] = $img;
        } elseif ($img = $this->imagePayload($sculpt->front_image, PDFImageTypeEnum::Single, $name)) {
            $images[] = $img;
        }

        if (empty($images)) {
            abort(404, 'No card art available for this sculpt.');
        }

        $pdf = Pdf::loadView('PDF.CharacterImageBlank', ['images' => $images]);
        $slug = Str::slug(($sculpt->unit->name ?? 'tos-unit').'-'.$sculpt->slug);

        return $pdf->stream("{$slug}.pdf");
    }

    /**
     * Read a public-disk image and return the blade-template payload, or
     * null if the path is missing/empty. Centralizes the
     * `Storage::disk('public')->exists() → base64_encode(get())` dance that
     * was duplicated across the three image branches.
     *
     * @return array{url: string, type: PDFImageTypeEnum, name: string}|null
     */
    private function imagePayload(?string $path, PDFImageTypeEnum $type, string $name): ?array
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return [
            'url' => base64_encode((string) Storage::disk('public')->get($path)),
            'type' => $type,
            'name' => $name,
        ];
    }
}
