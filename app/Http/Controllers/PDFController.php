<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PDFController extends Controller
{
    public function index(Request $request)
    {
        $query = Character::with(['standardMiniatures', 'keywords']);

        if ($request->get('faction')) {
            $query->where('faction', $request->faction);
        }

        if ($request->get('keyword')) {
            $query->whereHas('keywords', function ($subQuery) use ($request) {
                $subQuery->where('slug', $request->get('keyword'));
            });
        }

        $characters = $query->whereHas('standardMiniatures')->orderBy('name', 'ASC')->get();

        return inertia('PDF/Index', [
            'factions' => FactionEnum::buildDetails(),
            'keywords' => Keyword::orderBy('name', 'ASC')->get(),
            'characters' => fn () => $characters,
        ]);
    }

    public function download(Request $request)
    {
        $validated = $request->validate([
            'miniatures' => ['required', 'array'],
            'miniatures.*' => ['required', 'integer']
        ]);

        $miniatures = Miniature::whereIn('id', $validated['miniatures'])->get();
        $data = [
            'images' => [],
        ];

        foreach ($validated['miniatures'] as $miniatureId) {
            $miniature = $miniatures->where('id', $miniatureId)->first();
            $imageData = base64_encode(Storage::disk('public')->get($miniature->combination_image));
            $data['images'][] = [
                'url' => $imageData,
                'name' => $miniature->display_name
            ];
        }

        $pdf = Pdf::loadView('PDF.CharacterImageBlank', $data);

        $fileName = \Str::uuid(16);
        return $pdf->stream("{$fileName}.pdf");
    }
}
