<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreCrewCardRequest;
use App\Http\Requests\Admin\Campaign\UpdateCrewCardRequest;
use App\Jobs\Campaign\GenerateCrewCardImage;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\CampaignCrewCard;
use Illuminate\Http\Request;

class CrewCardAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/CrewCard/Index', [
            'items' => CampaignCrewCard::orderBy('name')
                ->get(['id', 'name', 'requires_token_choice', 'requires_marker_choice', 'requires_upgrade_type_choice'])
                ->map(fn (CampaignCrewCard $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'requires_token_choice' => $c->requires_token_choice,
                    'requires_marker_choice' => $c->requires_marker_choice,
                    'requires_upgrade_type_choice' => $c->requires_upgrade_type_choice,
                ]),
        ]);
    }

    private function formProps(): array
    {
        return [
            'all_actions' => fn () => Action::orderBy('name')->get(['id', 'name', 'type'])->map(fn (Action $a) => [
                'id' => $a->id,
                'name' => "{$a->name} (#{$a->id})",
                'type' => $a->type,
            ]),
            'all_abilities' => fn () => Ability::orderBy('name')->get(['id', 'name']),
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
            'name' => "{$a->name} (#{$a->id})",
            'is_signature' => (bool) $a->pivot->is_signature_action, // @phpstan-ignore property.notFound (pivot from BelongsToMany)
        ])->values());

        return inertia('Admin/Campaign/CrewCard/Form', array_merge(
            ['item' => $crewCard->toArray()],
            $this->formProps(),
        ));
    }

    public function store(StoreCrewCardRequest $request)
    {
        $validated = $request->validated();
        $actionsInput = $validated['actions'] ?? [];
        $abilitiesInput = $validated['abilities'] ?? [];
        unset($validated['actions'], $validated['abilities']);

        $row = CampaignCrewCard::create($validated);
        $row->actions()->sync($this->actionSyncMap($actionsInput));
        $row->abilities()->sync($this->abilitySyncMap($abilitiesInput));
        GenerateCrewCardImage::dispatch($row->id)->afterCommit();

        return redirect()->route('admin.campaign.crew-cards.index')->withMessage("{$row->name} created.");
    }

    public function update(UpdateCrewCardRequest $request, CampaignCrewCard $crewCard)
    {
        $validated = $request->validated();
        $actionsInput = $validated['actions'] ?? [];
        $abilitiesInput = $validated['abilities'] ?? [];
        unset($validated['actions'], $validated['abilities']);

        $crewCard->update($validated);
        $crewCard->actions()->sync($this->actionSyncMap($actionsInput));
        $crewCard->abilities()->sync($this->abilitySyncMap($abilitiesInput));
        GenerateCrewCardImage::dispatch($crewCard->id)->afterCommit();

        return redirect()->route('admin.campaign.crew-cards.index')->withMessage("{$crewCard->name} updated.");
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

    /**
     * Build a sync map (plain id list) for the abilities pivot.
     *
     * @param  array<int, array{id: int}>  $abilitiesInput
     * @return array<int, int>
     */
    private function abilitySyncMap(array $abilitiesInput): array
    {
        return array_map(fn ($entry) => (int) $entry['id'], $abilitiesInput);
    }

    public function delete(Request $request, CampaignCrewCard $crewCard)
    {
        $name = $crewCard->name;
        $crewCard->delete();

        return redirect()->route('admin.campaign.crew-cards.index')->withMessage("{$name} deleted.");
    }
}
