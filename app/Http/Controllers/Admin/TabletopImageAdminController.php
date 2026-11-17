<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TabletopImageRequest;
use App\Models\TabletopImage;
use App\Support\AdminAudit;
use App\Support\Tabletop\TabletopImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Admin-managed art for the tabletop. The app ships a default table surface set and a default texture for each kind of terrain;
 * what is added here is offered next to them (table surfaces) or replaces the default for its terrain kind.
 */
class TabletopImageAdminController extends Controller
{
    public function index(): Response|ResponseFactory
    {
        return inertia('Admin/TabletopImages/Index', [
            'images' => TabletopImage::query()
                ->orderBy('kind')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (TabletopImage $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'kind' => $i->kind,
                    'terrain_key' => $i->terrain_key,
                    'terrain_label' => $i->terrain_key ? (TabletopImages::TERRAIN_KINDS[$i->terrain_key] ?? $i->terrain_key) : null,
                    'url' => Storage::disk('public')->url($i->image_path),
                    'is_active' => $i->is_active,
                    'sort_order' => $i->sort_order,
                ]),
            'defaults' => [
                'tables' => collect(TabletopImages::DEFAULT_TABLES)->map(fn ($label, $key) => ['key' => $key, 'name' => $label, 'url' => "/images/tabletop/tables/{$key}.svg"])->values(),
                'terrain' => collect(TabletopImages::TERRAIN_KINDS)->map(fn ($label, $key) => ['key' => $key, 'name' => $label, 'url' => "/images/tabletop/terrain/{$key}.svg"])->values(),
            ],
        ]);
    }

    public function create(): Response|ResponseFactory
    {
        return inertia('Admin/TabletopImages/Form', ['image' => null, 'terrain_kinds' => $this->terrainKinds()]);
    }

    public function edit(TabletopImage $tabletopImage): Response|ResponseFactory
    {
        return inertia('Admin/TabletopImages/Form', [
            'image' => [
                ...$tabletopImage->only(['id', 'name', 'kind', 'terrain_key', 'credit', 'is_active', 'sort_order']),
                'url' => Storage::disk('public')->url($tabletopImage->image_path),
            ],
            'terrain_kinds' => $this->terrainKinds(),
        ]);
    }

    public function store(TabletopImageRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');
        $data['terrain_key'] = $data['kind'] === TabletopImage::KIND_TERRAIN ? $data['terrain_key'] : null;
        $data['image_path'] = $this->storeImage($request->file('image'), $data['name']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $image = TabletopImage::create($data);
        AdminAudit::log("Added tabletop image {$image->name}", $image);

        return redirect()->route('admin.tabletop_images.index')->withMessage("{$image->name} added.");
    }

    public function update(TabletopImageRequest $request, TabletopImage $tabletopImage): RedirectResponse
    {
        $data = $request->safe()->except('image');
        $data['terrain_key'] = $data['kind'] === TabletopImage::KIND_TERRAIN ? $data['terrain_key'] : null;
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if ($request->hasFile('image')) {
            $old = $tabletopImage->image_path;
            $data['image_path'] = $this->storeImage($request->file('image'), $data['name']);
            Storage::disk('public')->delete($old);
        }

        $tabletopImage->update($data);
        AdminAudit::log("Updated tabletop image {$tabletopImage->name}", $tabletopImage);

        return redirect()->route('admin.tabletop_images.index')->withMessage("{$tabletopImage->name} updated.");
    }

    public function delete(TabletopImage $tabletopImage): RedirectResponse
    {
        $name = $tabletopImage->name;
        Storage::disk('public')->delete($tabletopImage->image_path);
        $tabletopImage->delete();
        AdminAudit::log("Deleted tabletop image {$name}");

        return redirect()->route('admin.tabletop_images.index')->withMessage("{$name} deleted.");
    }

    private function storeImage(?UploadedFile $file, string $name): string
    {
        $filename = sprintf('%s_%s.%s', Str::slug($name) ?: 'image', Str::uuid(), $file?->extension() ?? 'webp');

        return Storage::disk('public')->putFileAs('tabletop', $file, $filename);
    }

    /** @return list<array{value: string, label: string}> */
    private function terrainKinds(): array
    {
        return collect(TabletopImages::TERRAIN_KINDS)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->all();
    }
}
