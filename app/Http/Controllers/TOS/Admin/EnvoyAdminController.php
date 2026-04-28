<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Enums\TOS\EnvoyRestrictionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreEnvoyRequest;
use App\Http\Requests\TOS\Admin\UpdateEnvoyRequest;
use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Envoy;
use App\Traits\TOS\HandlesTosImageUpload;
use Illuminate\Http\Request;

class EnvoyAdminController extends Controller
{
    use HandlesTosImageUpload;

    public function index(Request $request)
    {
        return inertia('Admin/TOS/Envoys/Index', [
            'envoys' => Envoy::with('allegiance:id,name,is_syndicate,type,secondary_type')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'keyword', 'restriction', 'allegiance_id', 'sort_order']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/Envoys/EnvoyForm', [
            // Envoys are typically tied to Syndicates, but bespoke Envoys can
            // hang off any Allegiance — expose the full list and let the editor
            // pick.
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'name', 'is_syndicate']),
            'restrictions' => fn () => EnvoyRestrictionEnum::toSelectOptions(),
            'abilities' => fn () => Ability::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, Envoy $envoy)
    {
        $envoy->load('abilities:id');

        return inertia('Admin/TOS/Envoys/EnvoyForm', [
            'envoy' => $envoy,
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'name', 'is_syndicate']),
            'restrictions' => fn () => EnvoyRestrictionEnum::toSelectOptions(),
            'abilities' => fn () => Ability::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreEnvoyRequest $request)
    {
        $data = $request->validated();
        $abilityIds = $data['ability_ids'] ?? [];
        unset($data['ability_ids']);
        $data['image_path'] = $this->storeTosImage($request->file('image_path'), 'tos/envoys');

        $envoy = Envoy::create($data);
        $envoy->abilities()->sync($this->withSortOrder($abilityIds));

        return redirect()->route('admin.tos.envoys.index')->withMessage("{$envoy->name} created.");
    }

    public function update(UpdateEnvoyRequest $request, Envoy $envoy)
    {
        $data = $request->validated();
        $abilityIds = $data['ability_ids'] ?? [];
        unset($data['ability_ids']);

        if ($request->hasFile('image_path')) {
            $this->deleteTosImage($envoy->image_path);
            $data['image_path'] = $this->storeTosImage($request->file('image_path'), 'tos/envoys');
        } else {
            unset($data['image_path']);
        }

        $envoy->update($data);
        $envoy->abilities()->sync($this->withSortOrder($abilityIds));

        return redirect()->route('admin.tos.envoys.index')->withMessage("{$envoy->name} updated.");
    }

    public function delete(Request $request, Envoy $envoy)
    {
        $name = $envoy->name;
        $envoy->delete();

        return redirect()->route('admin.tos.envoys.index')->withMessage("{$name} deleted.");
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
