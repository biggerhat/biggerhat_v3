<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Enums\CharacterStationEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreCrewCardRequest;
use App\Http\Requests\Admin\Campaign\UpdateCrewCardRequest;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Character;
use App\Models\CustomCharacter;
use Illuminate\Http\Request;

class CrewCardAdminController extends Controller
{
    /** Wire-format master type ('official'/'custom', matching the Card Creator's own source_type vocabulary) <-> the morph column's actual FQCN. */
    private const MASTER_TYPE_MAP = ['official' => Character::class, 'custom' => CustomCharacter::class];

    public function index(Request $request)
    {
        return inertia('Admin/Campaign/CrewCard/Index', [
            'items' => CampaignCrewCard::orderBy('name')
                ->with('master:id,faction,display_name')
                ->get(['id', 'name', 'master_id', 'master_type', 'requires_token_choice', 'requires_marker_choice', 'requires_upgrade_type_choice'])
                ->map(fn (CampaignCrewCard $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'master' => $c->master ? ['id' => $c->master->id, 'display_name' => $c->master->display_name] : null,
                    'master_is_custom' => $c->master_type === CustomCharacter::class,
                    'requires_token_choice' => $c->requires_token_choice,
                    'requires_marker_choice' => $c->requires_marker_choice,
                    'requires_upgrade_type_choice' => $c->requires_upgrade_type_choice,
                ]),
        ]);
    }

    private function formProps(): array
    {
        return [
            'all_actions' => fn () => Action::orderBy('name')->get(['id', 'name', 'type']),
            'all_abilities' => fn () => Ability::orderBy('name')->get(['id', 'name']),
            'masters' => fn () => Character::where('station', CharacterStationEnum::Master->value)
                ->orderBy('display_name')
                ->get(['id', 'display_name']),
            // Custom-built Campaign Leaders are also eligible — a Crew Card
            // can be printed on a homebrew master, not just an official one.
            'custom_masters' => fn () => CustomCharacter::where('is_campaign_leader', true)
                ->where('current', true)
                ->orderBy('display_name')
                ->get(['id', 'display_name']),
        ];
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/CrewCard/Form', $this->formProps());
    }

    public function edit(Request $request, CampaignCrewCard $crewCard)
    {
        $crewCard->load(['actions:id,name', 'abilities:id,name']);
        // Map actions to include pivot data so the form can pre-populate signature flags.
        $crewCard->setRelation('actions', $crewCard->actions->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'is_signature' => (bool) $a->pivot->is_signature_action, // @phpstan-ignore property.notFound (pivot from BelongsToMany)
        ])->values());

        return inertia('Admin/Campaign/CrewCard/Form', array_merge(
            [
                'item' => array_merge($crewCard->toArray(), [
                    'master_type' => array_search($crewCard->master_type, self::MASTER_TYPE_MAP, true) ?: null,
                ]),
            ],
            $this->formProps(),
        ));
    }

    public function store(StoreCrewCardRequest $request)
    {
        $validated = $this->resolveMasterType($request->validated());
        $actionsInput = $validated['actions'] ?? [];
        $abilityIds = $validated['ability_ids'] ?? [];
        unset($validated['actions'], $validated['ability_ids']);

        $row = CampaignCrewCard::create($validated);
        $row->actions()->sync($this->actionSyncMap($actionsInput));
        $row->abilities()->sync($abilityIds);

        return redirect()->route('admin.campaign.crew-cards.index')->withMessage("{$row->name} created.");
    }

    public function update(UpdateCrewCardRequest $request, CampaignCrewCard $crewCard)
    {
        $validated = $this->resolveMasterType($request->validated());
        $actionsInput = $validated['actions'] ?? [];
        $abilityIds = $validated['ability_ids'] ?? [];
        unset($validated['actions'], $validated['ability_ids']);

        $crewCard->update($validated);
        $crewCard->actions()->sync($this->actionSyncMap($actionsInput));
        $crewCard->abilities()->sync($abilityIds);

        return redirect()->route('admin.campaign.crew-cards.index')->withMessage("{$crewCard->name} updated.");
    }

    /**
     * Swaps the validated payload's wire-format `master_type` ('official'/
     * 'custom') for the real morph FQCN the `master_id` column pairs with —
     * null master_id means no master at all, so master_type follows it.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function resolveMasterType(array $validated): array
    {
        $validated['master_type'] = $validated['master_id'] ?? null
            ? self::MASTER_TYPE_MAP[$validated['master_type'] ?? 'official'] ?? Character::class
            : null;

        return $validated;
    }

    /**
     * Build a sync map for the actions pivot with the is_signature_action flag.
     *
     * @param  array<int, array{id: int, is_signature: bool}>  $actionsInput
     * @return array<int, array{is_signature_action: bool}>
     */
    private function actionSyncMap(array $actionsInput): array
    {
        $map = [];
        foreach ($actionsInput as $entry) {
            $map[(int) $entry['id']] = ['is_signature_action' => (bool) ($entry['is_signature'] ?? false)];
        }

        return $map;
    }

    public function delete(Request $request, CampaignCrewCard $crewCard)
    {
        $name = $crewCard->name;
        $crewCard->delete();

        return redirect()->route('admin.campaign.crew-cards.index')->withMessage("{$name} deleted.");
    }
}
