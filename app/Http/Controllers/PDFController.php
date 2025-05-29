<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Http\Resources\CharacterPDFResource;
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
        $characters = Character::with(['standardMiniatures', 'keywords'])
            ->when($request->get('faction'), function ($query) use ($request) {
                $query->where('faction', $request->faction);
            })
            ->when($request->get('keywords'), function ($query) use ($request) {
                $query->where('slug', $request->get('keyword'));
            })
            ->whereHas('standardMiniatures')
            ->orderBy('station_sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->get();

        return inertia('PDF/Index', [
            'factions' => FactionEnum::buildDetails(),
            'keywords' => Keyword::orderBy('name', 'ASC')->get(),
            'characters' => fn () => CharacterPDFResource::collection($characters)->toArray($request),
        ]);
    }

    public function download(Request $request)
    {
        $miniatureArray = base64_decode($request->get('miniatures'));
        $miniatureArray = explode(',', $miniatureArray);

        $miniatures = Miniature::whereIn('id', $miniatureArray)->get();
        $data = [
            'images' => [],
        ];

        foreach ($miniatureArray as $miniatureId) {
            $miniature = $miniatures->where('id', $miniatureId)->first();
            $imageData = base64_encode(Storage::disk('public')->get($miniature->combination_image));
            $data['images'][] = [
                'url' => $imageData,
                'name' => $miniature->display_name,
            ];
        }

        $pdf = Pdf::loadView('PDF.CharacterImageBlank', $data);

        $fileName = \Str::uuid();

        return $pdf->stream("{$fileName}.pdf");
    }
}
