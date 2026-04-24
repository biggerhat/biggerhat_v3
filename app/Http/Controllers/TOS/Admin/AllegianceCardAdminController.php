<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreAllegianceCardRequest;
use App\Http\Requests\TOS\Admin\UpdateAllegianceCardRequest;
use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Traits\TOS\HandlesTosImageUpload;
use Illuminate\Http\Request;

class AllegianceCardAdminController extends Controller
{
    use HandlesTosImageUpload;

    public function index(Request $request)
    {
        return inertia('Admin/TOS/AllegianceCards/Index', [
            'cards' => AllegianceCard::with('allegiance:id,name')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'type', 'allegiance_id', 'image_path', 'sort_order']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/AllegianceCards/AllegianceCardForm', [
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'name', 'type']),
            'allegiance_types' => fn () => AllegianceTypeEnum::toSelectOptions(),
            'abilities' => fn () => Ability::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, AllegianceCard $card)
    {
        $card->load('abilities:id');

        return inertia('Admin/TOS/AllegianceCards/AllegianceCardForm', [
            'card' => $card,
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'name', 'type']),
            'allegiance_types' => fn () => AllegianceTypeEnum::toSelectOptions(),
            'abilities' => fn () => Ability::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreAllegianceCardRequest $request)
    {
        $data = $request->validated();
        $abilityIds = $data['ability_ids'] ?? [];
        unset($data['ability_ids']);
        $data['image_path'] = $this->storeTosImage($request->file('image_path'), 'tos/allegiance_cards');

        $card = AllegianceCard::create($data);
        $card->abilities()->sync($this->withSortOrder($abilityIds));

        return redirect()->route('admin.tos.allegiance_cards.index')->withMessage("{$card->name} created.");
    }

    public function update(UpdateAllegianceCardRequest $request, AllegianceCard $card)
    {
        $data = $request->validated();
        $abilityIds = $data['ability_ids'] ?? [];
        unset($data['ability_ids']);

        if ($request->hasFile('image_path')) {
            $this->deleteTosImage($card->image_path);
            $data['image_path'] = $this->storeTosImage($request->file('image_path'), 'tos/allegiance_cards');
        } else {
            unset($data['image_path']);
        }

        $card->update($data);
        $card->abilities()->sync($this->withSortOrder($abilityIds));

        return redirect()->route('admin.tos.allegiance_cards.index')->withMessage("{$card->name} updated.");
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
