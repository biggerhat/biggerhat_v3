<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreStratagemRequest;
use App\Http\Requests\TOS\Admin\UpdateStratagemRequest;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Stratagem;
use App\Traits\TOS\HandlesTosImageUpload;
use Illuminate\Http\Request;

class StratagemAdminController extends Controller
{
    use HandlesTosImageUpload;

    public function index(Request $request)
    {
        return inertia('Admin/TOS/Stratagems/Index', [
            'stratagems' => Stratagem::with('allegiance:id,name,type,secondary_type')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'allegiance_id', 'allegiance_type', 'tactical_cost', 'image_path', 'sort_order']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/Stratagems/StratagemForm', [
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'name']),
            'allegiance_types' => fn () => AllegianceTypeEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Stratagem $stratagem)
    {
        return inertia('Admin/TOS/Stratagems/StratagemForm', [
            'stratagem' => $stratagem,
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'name']),
            'allegiance_types' => fn () => AllegianceTypeEnum::toSelectOptions(),
        ]);
    }

    public function store(StoreStratagemRequest $request)
    {
        $data = $request->validated();
        $data['image_path'] = $this->storeTosImage($request->file('image_path'), 'tos/stratagems');

        $stratagem = Stratagem::create($data);

        return redirect()->route('admin.tos.stratagems.index')->withMessage("{$stratagem->name} created.");
    }

    public function update(UpdateStratagemRequest $request, Stratagem $stratagem)
    {
        $data = $request->validated();

        if ($request->hasFile('image_path')) {
            $this->deleteTosImage($stratagem->image_path);
            $data['image_path'] = $this->storeTosImage($request->file('image_path'), 'tos/stratagems');
        } else {
            unset($data['image_path']);
        }

        $stratagem->update($data);

        return redirect()->route('admin.tos.stratagems.index')->withMessage("{$stratagem->name} updated.");
    }

    public function delete(Request $request, Stratagem $stratagem)
    {
        $name = $stratagem->name;
        $stratagem->delete();

        return redirect()->route('admin.tos.stratagems.index')->withMessage("{$name} deleted.");
    }
}
