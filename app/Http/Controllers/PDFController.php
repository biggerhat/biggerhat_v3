<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Enums\PDFImageTypeEnum;
use App\Http\Resources\CharacterPDFResource;
use App\Http\Resources\UpgradePDFResource;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Upgrade;
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
            ->when($request->get('keyword'), function ($query) use ($request) {
                $query->whereHas('keywords', function ($query) use ($request) {
                    $query->where('slug', $request->get('keyword'));
                });
            })
            ->whereHas('standardMiniatures')
            ->orderBy('station_sort_order', 'ASC')
            ->orderBy('name', 'ASC');

        $upgrades = Upgrade::with('master')->orderBy('name', 'ASC');

        return inertia('PDF/Index', [
            'factions' => fn () => FactionEnum::buildDetails(),
            'keywords' => fn () => Keyword::orderBy('name', 'ASC')->get(),
            'characters' => fn () => CharacterPDFResource::collection($characters->get())->toArray($request),
            'upgrades' => fn () => UpgradePDFResource::collection($upgrades->get())->toArray($request),
        ]);
    }

    public function download(Request $request)
    {
        $miniatureArray = base64_decode($request->get('miniatures'));
        $miniatureArray = explode(',', $miniatureArray);

        $upgradeArray = base64_decode($request->get('upgrades'));
        $upgradeArray = explode(',', $upgradeArray);

        $miniatures = Miniature::whereIn('id', $miniatureArray)->get();
        $upgrades = Upgrade::whereIn('id', $upgradeArray)->get();
        $data = [
            'images' => [],
        ];

        foreach ($miniatureArray as $miniatureId) {
            $miniature = $miniatures->where('id', $miniatureId)->first();
            $imageData = base64_encode(Storage::disk('public')->get($miniature->combination_image));
            $data['images'][] = [
                'url' => $imageData,
                'type' => PDFImageTypeEnum::Double,
                'name' => $miniature->display_name,
            ];
        }

        foreach ($upgradeArray as $upgradeId) {
            $upgrade = $upgrades->where('id', $upgradeId)->first();
            $imageData = $upgrade->back_image ? base64_encode(Storage::disk('public')->get($upgrade->combination_image)) : base64_encode(Storage::disk('public')->get($upgrade->front_image));
            $data['images'][] = [
                'url' => $imageData,
                'type' => $upgrade->back_image ? PDFImageTypeEnum::Double : PDFImageTypeEnum::Single,
                'name' => $upgrade->name,
            ];
        }

        $pdf = Pdf::loadView('PDF.CharacterImageBlank', $data);

        $fileName = \Str::uuid();

        return $pdf->stream("{$fileName}.pdf");
    }
}
