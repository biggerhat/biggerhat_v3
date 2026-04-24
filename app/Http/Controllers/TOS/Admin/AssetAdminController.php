<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Enums\TOS\AssetLimitParameterTypeEnum;
use App\Enums\TOS\AssetLimitTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreAssetRequest;
use App\Http\Requests\TOS\Admin\UpdateAssetRequest;
use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\AssetLimit;
use App\Models\TOS\Unit;
use App\Traits\TOS\HandlesTosImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetAdminController extends Controller
{
    use HandlesTosImageUpload;

    public function index(Request $request)
    {
        return inertia('Admin/TOS/Assets/Index', [
            'assets' => Asset::with('allegiances:id,name', 'limits')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'scrip_cost', 'disable_count', 'scrap_count', 'image_path', 'sort_order']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/Assets/AssetForm', $this->formData());
    }

    public function edit(Request $request, Asset $asset)
    {
        $asset->load(['allegiances:id', 'abilities:id', 'actions:id', 'limits']);

        return inertia('Admin/TOS/Assets/AssetForm', [
            ...$this->formData(),
            'asset' => $asset,
        ]);
    }

    public function store(StoreAssetRequest $request)
    {
        $data = $request->validated();
        $imagePath = $this->storeTosImage($request->file('image_path'), 'tos/assets');

        $asset = DB::transaction(function () use ($data, $imagePath) {
            $asset = Asset::create([
                'name' => $data['name'],
                'scrip_cost' => $data['scrip_cost'],
                'disable_count' => $data['disable_count'] ?? null,
                'scrap_count' => $data['scrap_count'] ?? null,
                'body' => $data['body'] ?? null,
                'image_path' => $imagePath,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncPivots($asset, $data);
            $this->syncLimits($asset, $data['limits'] ?? []);

            return $asset;
        });

        return redirect()->route('admin.tos.assets.index')->withMessage("{$asset->name} created.");
    }

    public function update(UpdateAssetRequest $request, Asset $asset)
    {
        $data = $request->validated();

        $newImagePath = null;
        if ($request->hasFile('image_path')) {
            $this->deleteTosImage($asset->image_path);
            $newImagePath = $this->storeTosImage($request->file('image_path'), 'tos/assets');
        }

        DB::transaction(function () use ($asset, $data, $request, $newImagePath) {
            $payload = [
                'name' => $data['name'],
                'scrip_cost' => $data['scrip_cost'],
                'disable_count' => $data['disable_count'] ?? null,
                'scrap_count' => $data['scrap_count'] ?? null,
                'body' => $data['body'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
            ];
            if ($request->hasFile('image_path')) {
                $payload['image_path'] = $newImagePath;
            }

            $asset->update($payload);

            $this->syncPivots($asset, $data);
            $this->syncLimits($asset, $data['limits'] ?? []);
        });

        return redirect()->route('admin.tos.assets.index')->withMessage("{$asset->name} updated.");
    }

    public function delete(Request $request, Asset $asset)
    {
        $name = $asset->name;
        $asset->delete();

        return redirect()->route('admin.tos.assets.index')->withMessage("{$name} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'name']),
            'units' => fn () => Unit::orderBy('name')->get(['id', 'name']),
            'abilities' => fn () => Ability::orderBy('name')->get(['id', 'name']),
            'actions' => fn () => Action::with('typeLinks:id,action_id,type')->orderBy('name')->get(['id', 'name']),
            'limit_types' => AssetLimitTypeEnum::toSelectOptions(),
            'parameter_types' => AssetLimitParameterTypeEnum::toSelectOptions(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPivots(Asset $asset, array $data): void
    {
        $asset->allegiances()->sync($data['allegiance_ids'] ?? []);
        $asset->abilities()->sync($this->withSortOrder($data['ability_ids'] ?? []));
        $asset->actions()->sync($this->withSortOrder($data['action_ids'] ?? []));
    }

    /**
     * @param  array<int, array<string, mixed>>  $limits
     */
    private function syncLimits(Asset $asset, array $limits): void
    {
        $asset->limits()->delete();
        foreach ($limits as $l) {
            AssetLimit::create([
                'asset_id' => $asset->id,
                'limit_type' => $l['limit_type'],
                'parameter_type' => $l['parameter_type'] ?? null,
                'parameter_value' => $l['parameter_value'] ?? null,
                'parameter_unit_id' => $l['parameter_unit_id'] ?? null,
                'parameter_allegiance_id' => $l['parameter_allegiance_id'] ?? null,
                'notes' => $l['notes'] ?? null,
            ]);
        }
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
