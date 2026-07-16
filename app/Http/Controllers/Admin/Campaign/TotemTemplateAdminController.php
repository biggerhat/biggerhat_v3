<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Enums\FactionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreTotemTemplateRequest;
use App\Http\Requests\Admin\Campaign\UpdateTotemTemplateRequest;
use App\Models\Ability;
use App\Models\Action;
use App\Models\CustomCharacter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TotemTemplateAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/TotemTemplate/Index', [
            'items' => CustomCharacter::query()
                ->where('is_campaign_totem_template', true)
                ->orderBy('campaign_totem_flip_value')
                ->orderBy('name')
                ->get(['id', 'name', 'faction', 'campaign_totem_flip_value', 'campaign_is_black_joker_totem', 'campaign_is_red_joker_totem', 'campaign_totem_special_replace']),
        ]);
    }

    private function formProps(): array
    {
        return [
            'factions' => FactionEnum::toSelectOptions(),
            'all_actions' => fn () => Action::orderBy('name')->get(['id', 'name'])->map(fn (Action $a) => [
                'id' => $a->id,
                'name' => "{$a->name} (#{$a->id})",
            ]),
            'all_abilities' => fn () => Ability::orderBy('name')->get(['id', 'name']),
        ];
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/TotemTemplate/Form', $this->formProps());
    }

    public function edit(Request $request, CustomCharacter $totemTemplate)
    {
        abort_unless($totemTemplate->is_campaign_totem_template, 404);

        $totemTemplate->load(['campaignTotemActions:id,name', 'campaignTotemAbilities:id,name']);

        return inertia('Admin/Campaign/TotemTemplate/Form', array_merge(
            ['item' => $totemTemplate],
            $this->formProps(),
        ));
    }

    public function store(StoreTotemTemplateRequest $request)
    {
        $validated = $request->validated();
        $name = $validated['name'];
        [$actionIds, $signatureIds, $abilityIds] = $this->extractLinks($validated);

        $totem = CustomCharacter::create(array_merge($validated, [
            'user_id' => $request->user()->id,
            'display_name' => $name,
            'slug' => Str::slug($name),
            // Default base to 30mm when left blank; it stays editable when the
            // totem is added to a crew (pg 52).
            'base' => $validated['base'] ?? '30',
            'is_campaign_totem_template' => true,
            'is_public' => false,
            'count' => 1,
        ]));

        $this->syncLinks($totem, $actionIds, $signatureIds, $abilityIds);

        return redirect()->route('admin.campaign.totem-templates.index')->withMessage("{$name} created.");
    }

    public function update(UpdateTotemTemplateRequest $request, CustomCharacter $totemTemplate)
    {
        abort_unless($totemTemplate->is_campaign_totem_template, 404);

        $validated = $request->validated();
        $name = $validated['name'];
        [$actionIds, $signatureIds, $abilityIds] = $this->extractLinks($validated);

        $totemTemplate->update(array_merge($validated, [
            'display_name' => $name,
            'slug' => Str::slug($name),
            'base' => $validated['base'] ?? '30',
        ]));

        $this->syncLinks($totemTemplate, $actionIds, $signatureIds, $abilityIds);

        return redirect()->route('admin.campaign.totem-templates.index')->withMessage("{$totemTemplate->name} updated.");
    }

    /**
     * Pull the link arrays out of the validated payload (so they don't reach
     * the mass-create) and return them as [actionIds, signatureIds, abilityIds].
     *
     * @param  array<string, mixed>  $validated  Mutated by reference: link keys removed.
     * @return array{0: int[], 1: int[], 2: int[]}
     */
    private function extractLinks(array &$validated): array
    {
        $actionIds = $validated['action_ids'] ?? [];
        $signatureIds = $validated['signature_action_ids'] ?? [];
        $abilityIds = $validated['ability_ids'] ?? [];
        unset($validated['action_ids'], $validated['signature_action_ids'], $validated['ability_ids']);

        return [$actionIds, $signatureIds, $abilityIds];
    }

    /**
     * @param  int[]  $actionIds
     * @param  int[]  $signatureIds
     * @param  int[]  $abilityIds
     */
    private function syncLinks(CustomCharacter $totem, array $actionIds, array $signatureIds, array $abilityIds): void
    {
        $signature = array_flip($signatureIds);
        $actionPivot = [];
        foreach ($actionIds as $id) {
            $actionPivot[$id] = ['is_signature_action' => isset($signature[$id])];
        }

        $totem->campaignTotemActions()->sync($actionPivot);
        $totem->campaignTotemAbilities()->sync($abilityIds);
    }

    public function delete(Request $request, CustomCharacter $totemTemplate)
    {
        abort_unless($totemTemplate->is_campaign_totem_template, 404);

        $name = $totemTemplate->name;
        $totemTemplate->delete();

        return redirect()->route('admin.campaign.totem-templates.index')->withMessage("{$name} deleted.");
    }
}
