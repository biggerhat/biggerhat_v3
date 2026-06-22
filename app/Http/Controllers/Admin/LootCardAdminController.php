<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\LootCard;
use App\Models\Trigger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin maintenance for the Bonanza Brawl Loot Deck. 54 cards are seeded with
 * structural data; admins fill in titles, effects, granted Actions / Abilities
 * / Triggers per side, plus the card image. Create/delete are also exposed
 * so admins can add homebrew or expansion cards beyond the rulebook 54.
 */
class LootCardAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        $cards = LootCard::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->withCount(['actions', 'abilities', 'triggers'])
            ->get();

        return inertia('Admin/LootCards/Index', [
            'cards' => $cards,
        ]);
    }

    /**
     * Batch "Regenerate print images" page. Ships only the card list (lightweight);
     * the Vue page fetches each card's full render data one at a time via
     * cardData() to avoid a massive upfront payload + SSR timeout.
     */
    public function regenerate(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        $cards = LootCard::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug', 'name', 'suit', 'value_label']);

        // Disable SSR for this request — the page renders BonanzaSplitCard
        // offscreen for capture, which the SSR Node process can't handle
        // (times out on prod).
        config(['inertia.ssr.enabled' => false]);

        return inertia('Admin/LootCards/Regenerate', ['cards' => $cards]);
    }

    /**
     * JSON endpoint returning a single card's full render data (side relations
     * included) — consumed by the Regenerate page one card at a time.
     */
    public function cardData(LootCard $lootCard): \Illuminate\Http\JsonResponse
    {
        $lootCard->load([
            'sideAActions:id,name,slug,type,is_signature,stone_cost,range,range_type,stat,stat_suits,stat_modifier,resisted_by,target_number,target_suits,damage,description',
            'sideBActions:id,name,slug,type,is_signature,stone_cost,range,range_type,stat,stat_suits,stat_modifier,resisted_by,target_number,target_suits,damage,description',
            'sideAActions.triggers:id,name,slug,suits,stone_cost,description',
            'sideBActions.triggers:id,name,slug,suits,stone_cost,description',
            'sideAAbilities:id,name,slug,suits,defensive_ability_type,costs_stone,description',
            'sideBAbilities:id,name,slug,suits,defensive_ability_type,costs_stone,description',
            'sideATriggers:id,name,slug,suits,stone_cost,description',
            'sideBTriggers:id,name,slug,suits,stone_cost,description',
        ]);

        return response()->json($lootCard);
    }

    /**
     * Store a single regenerated card image (from the batch). Mirrors the image
     * handling in update() but accepts just the file.
     */
    public function storeImage(Request $request, LootCard $lootCard): \Illuminate\Http\JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'max:30000', 'mimes:png,jpeg,jpg,webp']]);

        if ($lootCard->image) {
            Storage::disk('public')->delete($lootCard->image);
        }
        $extension = $request->file('image')->extension();
        $filename = sprintf('%s_%s.%s', $lootCard->slug, Str::uuid(), $extension);
        $path = "loot_cards/{$lootCard->slug}/{$filename}";
        Storage::disk('public')->put($path, file_get_contents($request->file('image')));
        $lootCard->update(['image' => $path]);

        return response()->json(['image' => $path]);
    }

    public function create(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/LootCards/LootCardForm', [
            'card' => null,
            ...$this->multiselectProps(),
        ]);
    }

    public function edit(Request $request, LootCard $lootCard): \Inertia\Response|\Inertia\ResponseFactory
    {
        $lootCard->load([
            'sideAActions:id,name,slug,type,is_signature,stone_cost,range,range_type,stat,stat_suits,stat_modifier,resisted_by,target_number,target_suits,damage,description',
            'sideBActions:id,name,slug,type,is_signature,stone_cost,range,range_type,stat,stat_suits,stat_modifier,resisted_by,target_number,target_suits,damage,description',
            'sideAActions.triggers:id,name,slug,suits,stone_cost,description',
            'sideBActions.triggers:id,name,slug,suits,stone_cost,description',
            'sideAAbilities:id,name,slug,suits,defensive_ability_type,costs_stone,description',
            'sideBAbilities:id,name,slug,suits,defensive_ability_type,costs_stone,description',
            'sideATriggers:id,name,slug,suits,stone_cost,description',
            'sideBTriggers:id,name,slug,suits,stone_cost,description',
        ]);

        return inertia('Admin/LootCards/LootCardForm', [
            'card' => $lootCard,
            ...$this->multiselectProps(),
        ]);
    }

    /**
     * @return array<string, \Closure>
     */
    private function multiselectProps(): array
    {
        return [
            'all_actions' => fn () => Action::query()
                ->orderBy('name')
                ->with(['triggers:id,name,slug,suits,stone_cost,description'])
                ->get([
                    'id', 'name', 'slug', 'type', 'is_signature', 'stone_cost', 'range',
                    'range_type', 'stat', 'stat_suits', 'stat_modifier', 'resisted_by', 'target_number',
                    'target_suits', 'damage', 'description',
                ]),
            'all_abilities' => fn () => Ability::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'suits', 'defensive_ability_type', 'costs_stone', 'description']),
            'all_triggers' => fn () => Trigger::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'suits', 'stone_cost', 'description']),
        ];
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request, isCreate: true);

        // Random suffix avoids collisions across homebrew cards.
        $slug = Str::slug($validated['name']).'-'.Str::lower(Str::random(4));

        $card = LootCard::create([
            'slug' => $slug,
            'name' => $validated['name'],
            'suit' => $validated['suit'],
            'value' => $validated['value'] ?? null,
            'value_label' => $this->resolveValueLabel($validated),
            'title_a' => $validated['title_a'] ?? null,
            'title_b' => $validated['title_b'] ?? null,
            'effect_a' => $validated['effect_a'] ?? null,
            'effect_b' => $validated['effect_b'] ?? null,
            'sort_order' => (int) (LootCard::max('sort_order') ?? 0) + 1,
        ]);

        $this->handleImage($request, $card, $validated);
        $this->syncSideRelations($card, $validated);

        return redirect()->route('admin.loot_cards.index')->withMessage("{$card->name} created.");
    }

    public function destroy(Request $request, LootCard $lootCard)
    {
        $name = $lootCard->name;
        if ($lootCard->image) {
            Storage::disk('public')->delete($lootCard->image);
        }
        $lootCard->delete();

        return redirect()->route('admin.loot_cards.index')->withMessage("{$name} deleted.");
    }

    public function update(Request $request, LootCard $lootCard)
    {
        $validated = $this->validatePayload($request, isCreate: false);
        $this->handleImage($request, $lootCard, $validated);
        $this->syncSideRelations($lootCard, $validated);

        $cardFields = collect($validated)->only(['name', 'title_a', 'title_b', 'effect_a', 'effect_b', 'image'])->all();
        $lootCard->update($cardFields);

        return redirect()->route('admin.loot_cards.index')->withMessage("{$lootCard->name} updated.");
    }

    /**
     * Jokers need an admin-supplied label (Red vs Black). Suited cards
     * print their numeric value 1-13.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveValueLabel(array $validated): string
    {
        $suit = $validated['suit'] ?? null;
        if ($suit === 'joker') {
            $label = trim((string) ($validated['value_label'] ?? ''));

            return $label !== '' ? $label : 'Joker';
        }

        $value = $validated['value'] ?? null;

        return $value === null ? '' : (string) $value;
    }

    private function validatePayload(Request $request, bool $isCreate): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'title_a' => ['nullable', 'string', 'max:255'],
            'title_b' => ['nullable', 'string', 'max:255'],
            'effect_a' => ['nullable', 'string', 'max:5000'],
            'effect_b' => ['nullable', 'string', 'max:5000'],
            'side_a_actions' => ['nullable', 'array'],
            'side_a_actions.*.action_id' => ['required', 'integer', 'exists:actions,id'],
            'side_a_actions.*.is_signature_action' => ['sometimes', 'boolean'],
            'side_b_actions' => ['nullable', 'array'],
            'side_b_actions.*.action_id' => ['required', 'integer', 'exists:actions,id'],
            'side_b_actions.*.is_signature_action' => ['sometimes', 'boolean'],
            'side_a_abilities' => ['nullable', 'array'],
            'side_a_abilities.*' => ['integer', 'exists:abilities,id'],
            'side_b_abilities' => ['nullable', 'array'],
            'side_b_abilities.*' => ['integer', 'exists:abilities,id'],
            'side_a_triggers' => ['nullable', 'array'],
            'side_a_triggers.*' => ['integer', 'exists:triggers,id'],
            'side_b_triggers' => ['nullable', 'array'],
            'side_b_triggers.*' => ['integer', 'exists:triggers,id'],
            'image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'remove_image' => ['sometimes', 'boolean'],
        ];

        if ($isCreate) {
            $rules['suit'] = ['required', 'string', 'in:crow,mask,ram,tome,joker'];
            $rules['value'] = ['nullable', 'integer', 'min:1', 'max:13'];
            $rules['value_label'] = ['nullable', 'string', 'max:32'];
        }

        return $request->validate($rules);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function handleImage(Request $request, LootCard $card, array &$validated): void
    {
        if ($request->hasFile('image')) {
            if ($card->image) {
                Storage::disk('public')->delete($card->image);
            }
            $extension = $request->file('image')->extension();
            $filename = sprintf('%s_%s.%s', $card->slug, Str::uuid(), $extension);
            $path = "loot_cards/{$card->slug}/{$filename}";
            Storage::disk('public')->put($path, file_get_contents($request->file('image')));
            $validated['image'] = $path;
            $card->update(['image' => $path]);
        } elseif (! empty($validated['remove_image']) && $card->image) {
            Storage::disk('public')->delete($card->image);
            $validated['image'] = null;
            $card->update(['image' => null]);
        } else {
            unset($validated['image']);
        }
        unset($validated['remove_image']);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncSideRelations(LootCard $card, array $validated): void
    {
        $card->syncSideActions('a', $validated['side_a_actions'] ?? []);
        $card->syncSideActions('b', $validated['side_b_actions'] ?? []);
        $card->syncSideAbilities('a', $validated['side_a_abilities'] ?? []);
        $card->syncSideAbilities('b', $validated['side_b_abilities'] ?? []);
        $card->syncSideTriggers('a', $validated['side_a_triggers'] ?? []);
        $card->syncSideTriggers('b', $validated['side_b_triggers'] ?? []);
    }
}
