<?php

namespace App\Http\Controllers;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\DefensiveAbilityTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Models\CustomCharacter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

class CustomCharacterController extends Controller
{
    public function index(): Response
    {
        $characters = CustomCharacter::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        return inertia('Tools/CardCreator/Index', [
            'characters' => $characters,
        ]);
    }

    public function create(): Response
    {
        return inertia('Tools/CardCreator/Editor', [
            'character' => null,
            'enums' => $this->enumOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateCharacter($request);
        $validated['user_id'] = Auth::id();

        $character = CustomCharacter::create($validated);

        $this->handleCharacterImage($request, $character);

        return response()->json([
            'success' => true,
            'redirect' => route('tools.card_creator.edit', $character->id),
        ]);
    }

    public function edit(CustomCharacter $customCharacter): Response
    {
        if ($customCharacter->user_id !== Auth::id()) {
            abort(403);
        }

        return inertia('Tools/CardCreator/Editor', [
            'character' => $customCharacter,
            'enums' => $this->enumOptions(),
        ]);
    }

    public function update(Request $request, CustomCharacter $customCharacter): JsonResponse
    {
        if ($customCharacter->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $this->validateCharacter($request);

        $customCharacter->update($validated);

        $this->handleCharacterImage($request, $customCharacter);

        return response()->json([
            'success' => true,
            'character_image' => $customCharacter->fresh()->character_image,
        ]);
    }

    public function destroy(CustomCharacter $customCharacter): JsonResponse
    {
        if ($customCharacter->user_id !== Auth::id()) {
            abort(403);
        }

        $customCharacter->delete();

        return response()->json(['success' => true]);
    }

    public function share(string $shareCode): Response
    {
        $character = CustomCharacter::where('share_code', $shareCode)
            ->with('user:id,name')
            ->firstOrFail();

        return inertia('Tools/CardCreator/View', [
            'character' => $character,
            'creator_name' => $character->user->name,
        ]);
    }

    public function exportImage(Request $request, CustomCharacter $customCharacter): JsonResponse
    {
        if ($customCharacter->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'front_image' => ['required', 'string'],
            'back_image' => ['required', 'string'],
            'combo_image' => ['required', 'string'],
        ]);

        $basePath = 'custom-characters/'.Auth::id().'/cards';
        $updates = [];

        foreach (['front_image', 'back_image', 'combo_image'] as $field) {
            $data = $request->input($field);
            if (! str_starts_with($data, 'data:image/png;base64,')) {
                continue;
            }

            $imageData = base64_decode(str_replace('data:image/png;base64,', '', $data));
            $filename = "{$basePath}/{$customCharacter->id}-{$field}.png";

            // Delete old image if exists
            if ($customCharacter->$field) {
                Storage::disk('public')->delete($customCharacter->$field);
            }

            Storage::disk('public')->put($filename, $imageData);
            $updates[$field] = $filename;
        }

        if (! empty($updates)) {
            $customCharacter->update($updates);
        }

        $fresh = $customCharacter->fresh();

        return response()->json([
            'success' => true,
            'front_image' => $fresh->front_image,
            'back_image' => $fresh->back_image,
            'combo_image' => $fresh->combo_image,
        ]);
    }

    private function handleCharacterImage(Request $request, CustomCharacter $character): void
    {
        if ($request->boolean('remove_character_image')) {
            if ($character->character_image) {
                Storage::disk('public')->delete($character->character_image);
            }
            $character->update(['character_image' => null]);

            return;
        }

        $data = $request->input('character_image');
        if (! $data || ! preg_match('/^data:image\/(\w+);base64,/', $data, $matches)) {
            return;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $data));
        $path = "custom-characters/{$character->user_id}/art/{$character->id}.{$extension}";

        if ($character->character_image) {
            Storage::disk('public')->delete($character->character_image);
        }

        Storage::disk('public')->put($path, $imageData);
        $character->update(['character_image' => $path]);
    }

    private function validateCharacter(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'faction' => ['required', 'string'],
            'second_faction' => ['nullable', 'string'],
            'station' => ['nullable', 'string'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:99'],
            'health' => ['required', 'integer', 'min:1', 'max:99'],
            'size' => ['nullable', 'integer', 'min:0', 'max:10'],
            'base' => ['required', 'string'],
            'defense' => ['required', 'integer', 'min:0', 'max:20'],
            'defense_suit' => ['nullable', 'string'],
            'willpower' => ['required', 'integer', 'min:0', 'max:20'],
            'willpower_suit' => ['nullable', 'string'],
            'speed' => ['required', 'integer', 'min:0', 'max:20'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'summon_target_number' => ['nullable', 'integer', 'min:1', 'max:20'],
            'generates_stone' => ['boolean'],
            'is_unhirable' => ['boolean'],
            'actions' => ['nullable', 'array'],
            'actions.*.name' => ['required', 'string', 'max:255'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.is_signature' => ['boolean'],
            'actions.*.stone_cost' => ['integer', 'min:0'],
            'actions.*.range' => ['nullable', 'integer'],
            'actions.*.range_type' => ['nullable', 'string'],
            'actions.*.stat' => ['nullable', 'integer'],
            'actions.*.stat_suits' => ['nullable', 'string'],
            'actions.*.stat_modifier' => ['nullable', 'string'],
            'actions.*.resisted_by' => ['nullable', 'string'],
            'actions.*.target_number' => ['nullable', 'integer'],
            'actions.*.target_suits' => ['nullable', 'string'],
            'actions.*.damage' => ['nullable', 'string'],
            'actions.*.description' => ['nullable', 'string'],
            'actions.*.source_id' => ['nullable', 'integer'],
            'actions.*.triggers' => ['nullable', 'array'],
            'actions.*.triggers.*.name' => ['required', 'string', 'max:255'],
            'actions.*.triggers.*.suits' => ['nullable', 'string'],
            'actions.*.triggers.*.stone_cost' => ['integer', 'min:0'],
            'actions.*.triggers.*.description' => ['nullable', 'string'],
            'actions.*.triggers.*.source_id' => ['nullable', 'integer'],
            'abilities' => ['nullable', 'array'],
            'abilities.*.name' => ['required', 'string', 'max:255'],
            'abilities.*.suits' => ['nullable', 'string'],
            'abilities.*.defensive_ability_type' => ['nullable', 'string'],
            'abilities.*.costs_stone' => ['boolean'],
            'abilities.*.description' => ['nullable', 'string'],
            'abilities.*.source_id' => ['nullable', 'integer'],
            'keywords' => ['nullable', 'array'],
            'keywords.*.id' => ['nullable', 'integer'],
            'keywords.*.name' => ['required', 'string', 'max:255'],
            'characteristics' => ['nullable', 'array'],
            'characteristics.*' => ['string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function enumOptions(): array
    {
        return [
            'factions' => FactionEnum::toSelectOptions(),
            'stations' => CharacterStationEnum::toSelectOptions(),
            'bases' => BaseSizeEnum::toSelectOptions(),
            'suits' => SuitEnum::toSelectOptions(),
            'action_types' => ActionTypeEnum::toSelectOptions(),
            'range_types' => ActionRangeTypeEnum::toSelectOptions(),
            'defensive_ability_types' => DefensiveAbilityTypeEnum::toSelectOptions(),
        ];
    }
}
