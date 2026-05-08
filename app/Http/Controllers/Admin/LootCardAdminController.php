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
 * Admin maintenance for the Bonanza Brawl Loot Deck. The 54 cards are seeded
 * with structural data only — admins use this UI to fill in titles, effects,
 * granted Actions / Abilities / Triggers per side, plus the card image.
 * No create/delete endpoints since the deck size is fixed at 54.
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

    public function edit(Request $request, LootCard $lootCard): \Inertia\Response|\Inertia\ResponseFactory
    {
        $lootCard->load([
            'sideAActions:id,name,slug',
            'sideBActions:id,name,slug',
            'sideAAbilities:id,name,slug',
            'sideBAbilities:id,name,slug',
            'sideATriggers:id,name,slug',
            'sideBTriggers:id,name,slug',
        ]);

        return inertia('Admin/LootCards/LootCardForm', [
            'card' => $lootCard,
            // Lazy props — populate the multi-select option lists only when
            // the form actually mounts.
            'all_actions' => fn () => Action::query()->orderBy('name')->get(['id', 'name', 'slug', 'is_signature']),
            'all_abilities' => fn () => Ability::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'all_triggers' => fn () => Trigger::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, LootCard $lootCard)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title_a' => ['nullable', 'string', 'max:255'],
            'title_b' => ['nullable', 'string', 'max:255'],
            'effect_a' => ['nullable', 'string', 'max:5000'],
            'effect_b' => ['nullable', 'string', 'max:5000'],
            // Side actions — array of { action_id, is_signature_action }.
            'side_a_actions' => ['nullable', 'array'],
            'side_a_actions.*.action_id' => ['required', 'integer', 'exists:actions,id'],
            'side_a_actions.*.is_signature_action' => ['sometimes', 'boolean'],
            'side_b_actions' => ['nullable', 'array'],
            'side_b_actions.*.action_id' => ['required', 'integer', 'exists:actions,id'],
            'side_b_actions.*.is_signature_action' => ['sometimes', 'boolean'],
            // Plain id arrays for abilities + triggers per side.
            'side_a_abilities' => ['nullable', 'array'],
            'side_a_abilities.*' => ['integer', 'exists:abilities,id'],
            'side_b_abilities' => ['nullable', 'array'],
            'side_b_abilities.*' => ['integer', 'exists:abilities,id'],
            'side_a_triggers' => ['nullable', 'array'],
            'side_a_triggers.*' => ['integer', 'exists:triggers,id'],
            'side_b_triggers' => ['nullable', 'array'],
            'side_b_triggers.*' => ['integer', 'exists:triggers,id'],
            'image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            // Lets the form clear a previously-uploaded image without
            // requiring the admin to upload a replacement first.
            'remove_image' => ['sometimes', 'boolean'],
        ]);

        // Image handling mirrors StrategyAdminController. Slug-stable folder
        // so re-edits don't orphan files in random directories.
        if ($request->hasFile('image')) {
            if ($lootCard->image) {
                Storage::disk('public')->delete($lootCard->image);
            }
            $extension = $request->file('image')->extension();
            $filename = sprintf('%s_%s.%s', $lootCard->slug, Str::uuid(), $extension);
            $path = "loot_cards/{$lootCard->slug}/{$filename}";
            Storage::disk('public')->put($path, file_get_contents($request->file('image')));
            $validated['image'] = $path;
        } elseif (! empty($validated['remove_image']) && $lootCard->image) {
            Storage::disk('public')->delete($lootCard->image);
            $validated['image'] = null;
        } else {
            unset($validated['image']);
        }
        unset($validated['remove_image']);

        // Sync side relations first so the column update stays a clean diff.
        $lootCard->syncSideActions('a', $validated['side_a_actions'] ?? []);
        $lootCard->syncSideActions('b', $validated['side_b_actions'] ?? []);
        $lootCard->syncSideAbilities('a', $validated['side_a_abilities'] ?? []);
        $lootCard->syncSideAbilities('b', $validated['side_b_abilities'] ?? []);
        $lootCard->syncSideTriggers('a', $validated['side_a_triggers'] ?? []);
        $lootCard->syncSideTriggers('b', $validated['side_b_triggers'] ?? []);

        $cardFields = collect($validated)->only(['name', 'title_a', 'title_b', 'effect_a', 'effect_b', 'image'])->all();
        $lootCard->update($cardFields);

        return redirect()->route('admin.loot_cards.index')->withMessage("{$lootCard->name} updated.");
    }
}
