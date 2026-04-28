<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreAllegianceCardRequest;
use App\Http\Requests\TOS\Admin\UpdateAllegianceCardRequest;
use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Models\TOS\Trigger;
use App\Traits\TOS\HandlesTosImageUpload;
use Illuminate\Http\Request;

class AllegianceCardAdminController extends Controller
{
    use HandlesTosImageUpload;

    public function index(Request $request)
    {
        return inertia('Admin/TOS/AllegianceCards/Index', [
            'cards' => AllegianceCard::with('allegiance:id,name,type,secondary_type')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'type', 'secondary_type', 'allegiance_id', 'image_path', 'sort_order']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/AllegianceCards/AllegianceCardForm', [
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'name', 'type']),
            'allegiance_types' => fn () => AllegianceTypeEnum::toSelectOptions(),
            'abilities' => fn () => Ability::orderBy('name')->get(['id', 'name']),
            'actions' => fn () => Action::orderBy('name')->get(['id', 'name']),
            'triggers' => fn () => Trigger::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, AllegianceCard $card)
    {
        $card->load([
            'abilities:id', 'actions:id', 'triggers:id',
            'primaryAbilities:id', 'primaryActions:id', 'primaryTriggers:id',
        ]);

        return inertia('Admin/TOS/AllegianceCards/AllegianceCardForm', [
            'card' => $card,
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'name', 'type']),
            'allegiance_types' => fn () => AllegianceTypeEnum::toSelectOptions(),
            'abilities' => fn () => Ability::orderBy('name')->get(['id', 'name']),
            'actions' => fn () => Action::orderBy('name')->get(['id', 'name']),
            'triggers' => fn () => Trigger::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreAllegianceCardRequest $request)
    {
        $data = $request->validated();
        $links = $this->extractLinkArrays($data);
        $data['image_path'] = $this->storeTosImage($request->file('image_path'), 'tos/allegiance_cards');

        $card = AllegianceCard::create($data);
        $this->syncAllLinks($card, $links);

        return redirect()->route('admin.tos.allegiance_cards.index')->withMessage("{$card->name} created.");
    }

    public function update(UpdateAllegianceCardRequest $request, AllegianceCard $card)
    {
        $data = $request->validated();
        $links = $this->extractLinkArrays($data);

        if ($request->hasFile('image_path')) {
            $this->deleteTosImage($card->image_path);
            $data['image_path'] = $this->storeTosImage($request->file('image_path'), 'tos/allegiance_cards');
        } else {
            unset($data['image_path']);
        }

        $card->update($data);
        $this->syncAllLinks($card, $links);

        return redirect()->route('admin.tos.allegiance_cards.index')->withMessage("{$card->name} updated.");
    }

    /**
     * Pull every `*_ids` link array out of the validated payload and return
     * them as a single map keyed by relation name. Mutates `$data` so it
     * doesn't carry the link arrays into the model fill().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<int, int>>
     */
    private function extractLinkArrays(array &$data): array
    {
        $keys = [
            'abilities' => 'ability_ids',
            'actions' => 'action_ids',
            'triggers' => 'trigger_ids',
            'primaryAbilities' => 'primary_ability_ids',
            'primaryActions' => 'primary_action_ids',
            'primaryTriggers' => 'primary_trigger_ids',
        ];

        $out = [];
        foreach ($keys as $relation => $field) {
            $out[$relation] = $data[$field] ?? [];
            unset($data[$field]);
        }

        return $out;
    }

    /**
     * @param  array<string, array<int, int>>  $links
     */
    private function syncAllLinks(AllegianceCard $card, array $links): void
    {
        foreach ($links as $relation => $ids) {
            $card->{$relation}()->sync($this->withSortOrder($ids));
        }
    }

    public function delete(Request $request, AllegianceCard $card)
    {
        $name = $card->name;
        $card->delete();

        return redirect()->route('admin.tos.allegiance_cards.index')->withMessage("{$name} deleted.");
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{sort_order: int}>
     */
    private function withSortOrder(array $ids): array
    {
        $out = [];
        foreach (array_values($ids) as $i => $id) {
            $out[$id] = ['sort_order' => $i];
        }

        return $out;
    }
}
