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
        $images = [];

        if ($separate && $sculpt->front_image && $sculpt->back_image) {
            foreach (['front_image', 'back_image'] as $col) {
                if (! Storage::disk('public')->exists($sculpt->{$col})) {
                    continue;
                }
                $images[] = [
                    'url' => base64_encode((string) Storage::disk('public')->get($sculpt->{$col})),
                    'type' => PDFImageTypeEnum::Single,
                    'name' => $sculpt->unit->name ?? 'TOS Unit',
                ];
            }
        } elseif ($sculpt->combination_image && Storage::disk('public')->exists($sculpt->combination_image)) {
            $images[] = [
                'url' => base64_encode((string) Storage::disk('public')->get($sculpt->combination_image)),
                'type' => PDFImageTypeEnum::Double,
                'name' => $sculpt->unit->name ?? 'TOS Unit',
            ];
        } elseif ($sculpt->front_image && Storage::disk('public')->exists($sculpt->front_image)) {
            $images[] = [
                'url' => base64_encode((string) Storage::disk('public')->get($sculpt->front_image)),
                'type' => PDFImageTypeEnum::Single,
                'name' => $sculpt->unit->name ?? 'TOS Unit',
            ];
        }

        if (empty($images)) {
            abort(404, 'No card art available for this sculpt.');
        }

        $pdf = Pdf::loadView('PDF.CharacterImageBlank', ['images' => $images]);
        $name = Str::slug(($sculpt->unit->name ?? 'tos-unit').'-'.$sculpt->slug);

        return $pdf->stream("{$name}.pdf");
    }
}
