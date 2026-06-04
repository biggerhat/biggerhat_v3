<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameModeTypeEnum;
use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Models\Trigger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TriggerAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Triggers/Index', [
            'triggers' => Trigger::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Triggers/TriggerForm', [
            'suits' => SuitEnum::toSelectOptions(),
            'game_mode_types' => fn () => GameModeTypeEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Trigger $trigger)
    {
        return inertia('Admin/Triggers/TriggerForm', [
            'trigger' => $trigger->loadMissing(['actions']),
            'suits' => SuitEnum::toSelectOptions(),
            'game_mode_types' => fn () => GameModeTypeEnum::toSelectOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $trigger = $this->validateAndSave($request);

        return redirect()->route('admin.triggers.index')->withMessage("{$trigger->name} created successfully.");
    }

    public function update(Request $request, Trigger $trigger)
    {
        $trigger = $this->validateAndSave($request, $trigger);

        return redirect()->route('admin.triggers.index')->withMessage("{$trigger->name} has been updated.");
    }

    public function delete(Request $request, Trigger $trigger)
    {
        $name = $trigger->name;
        $trigger->delete();

        return redirect()->route('admin.triggers.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(Request $request, ?Trigger $trigger = null): Trigger
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'suits' => ['nullable', 'string', 'max:255'],
            'stone_cost' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            // Optional — defaults to 'standard' on the column when not sent.
            'game_mode_type' => ['sometimes', 'string', Rule::enum(GameModeTypeEnum::class)],
            // Campaign-only — zeroed below unless mode is campaign.
            'campaign_advancement_kind' => ['nullable', 'string', 'in:attack,tactical'],
            'campaign_flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'campaign_is_always_available' => ['sometimes', 'boolean'],
            'campaign_joker_freechoice' => ['sometimes', 'boolean'],
            'campaign_grants_signature' => ['sometimes', 'boolean'],
            'campaign_modifier_type' => ['nullable', 'string', 'in:trigger,skl,signature,joker'],
            'campaign_skl_from' => ['nullable', 'integer', 'min:1'],
            'campaign_skl_to' => ['nullable', 'integer', 'min:1'],
        ]);

        $mode = $validated['game_mode_type'] ?? 'standard';
        if ($mode !== GameModeTypeEnum::Campaign->value) {
            foreach ([
                'campaign_advancement_kind', 'campaign_flip_value', 'campaign_modifier_type',
                'campaign_skl_from', 'campaign_skl_to',
            ] as $col) {
                $validated[$col] = null;
            }
            foreach ([
                'campaign_is_always_available', 'campaign_joker_freechoice', 'campaign_grants_signature',
            ] as $col) {
                $validated[$col] = false;
            }
        }

        if (! $trigger) {
            $trigger = Trigger::create($validated);
            $trigger->update([
                'slug' => $trigger->id.'-'.$trigger->slug,
            ]);
        } else {
            $trigger->update($validated);
        }

        return $trigger;
    }
}
