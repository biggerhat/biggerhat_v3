<?php

namespace App\Http\Controllers;

use App\Enums\CardTypeEnum;
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
        $characters = Character::with(['standardMiniatures', 'keywords', 'crewUpgrades', 'totem', 'isTotemFor'])
            ->whereHas('standardMiniatures')
            ->orderBy('station_sort_order', 'ASC')
            ->orderBy('name', 'ASC');

        $upgrades = Upgrade::with('masters')->forCharacters()->orderBy('name', 'ASC');

        return inertia('PDF/Index', [
            'factions' => fn () => FactionEnum::buildDetails(),
            'keywords' => fn () => Keyword::orderBy('name', 'ASC')->get(),
            'characters' => fn () => CharacterPDFResource::collection($characters->get())->toArray($request),
            'upgrades' => fn () => UpgradePDFResource::collection($upgrades->get())->toArray($request),
        ]);
    }

    public function download(Request $request)
    {
        $request->validate([
            'cards' => 'required|string',
            'options' => 'required|string',
        ]);

        $cards = base64_decode($request->get('cards'), true);
        $optionsRaw = base64_decode($request->get('options'), true);

        if ($cards === false || $optionsRaw === false) {
            abort(422, 'Invalid base64 encoding.');
        }

        $options = json_decode($optionsRaw, true);
        $cardArray = collect(json_decode($cards, true));

        if (! is_array($options) || ! $cardArray->count()) {
            abort(422, 'Invalid card or options data.');
        }

        $separateImages = $options['separate_images'] ?? false;

        $miniatureIds = $cardArray->where('card_type', CardTypeEnum::Miniature->value)->pluck('id');
        $upgradeIds = $cardArray->where('card_type', CardTypeEnum::Upgrade->value)->pluck('id');

        $miniatures = Miniature::whereIn('id', $miniatureIds)->get()->keyBy('id');
        $upgrades = Upgrade::whereIn('id', $upgradeIds)->get()->keyBy('id');
        $data = [
            'images' => [],
        ];

        foreach ($cardArray as $card) {
            if ($card['card_type'] === CardTypeEnum::Miniature->value) {
                $miniature = $miniatures[$card['id']] ?? null;

                if (! $miniature) {
                    continue;
                }

                if ($separateImages) {
                    $frontImage = base64_encode(Storage::disk('public')->get($miniature->front_image));
                    $data['images'][] = [
                        'url' => $frontImage,
                        'type' => PDFImageTypeEnum::Single,
                        'name' => $miniature->display_name,
                    ];

                    $backImage = base64_encode(Storage::disk('public')->get($miniature->back_image));
                    $data['images'][] = [
                        'url' => $backImage,
                        'type' => PDFImageTypeEnum::Single,
                        'name' => $miniature->display_name,
                    ];
                } else {
                    $imageData = base64_encode(Storage::disk('public')->get($miniature->combination_image));
                    $data['images'][] = [
                        'url' => $imageData,
                        'type' => PDFImageTypeEnum::Double,
                        'name' => $miniature->display_name,
                    ];
                }
            }
            if ($card['card_type'] === CardTypeEnum::Upgrade->value) {
                $upgrade = $upgrades[$card['id']] ?? null;

                if (! $upgrade) {
                    continue;
                }

                if ($separateImages && $upgrade->back_image) {
                    $frontImage = base64_encode(Storage::disk('public')->get($upgrade->front_image));
                    $data['images'][] = [
                        'url' => $frontImage,
                        'type' => PDFImageTypeEnum::Single,
                        'name' => $upgrade->name,
                    ];

                    $backImage = base64_encode(Storage::disk('public')->get($upgrade->back_image));
                    $data['images'][] = [
                        'url' => $backImage,
                        'type' => PDFImageTypeEnum::Single,
                        'name' => $upgrade->name,
                    ];
                } else {
                    $imageData = $upgrade->back_image ? base64_encode(Storage::disk('public')->get($upgrade->combination_image)) : base64_encode(Storage::disk('public')->get($upgrade->front_image));
                    $data['images'][] = [
                        'url' => $imageData,
                        'type' => $upgrade->back_image ? PDFImageTypeEnum::Double : PDFImageTypeEnum::Single,
                        'name' => $upgrade->name,
                    ];
                }
            }
        }

        $pdf = Pdf::loadView('PDF.CharacterImageBlank', $data);

        $fileName = \Str::uuid();

        return $pdf->stream("{$fileName}.pdf");
    }
}
