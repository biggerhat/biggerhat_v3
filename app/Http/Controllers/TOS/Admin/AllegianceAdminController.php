<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreAllegianceRequest;
use App\Http\Requests\TOS\Admin\UpdateAllegianceRequest;
use App\Models\TOS\Allegiance;
use App\Traits\TOS\HandlesTosImageUpload;
use Illuminate\Http\Request;

class AllegianceAdminController extends Controller
{
    use HandlesTosImageUpload;

    public function index(Request $request)
    {
        return inertia('Admin/TOS/Allegiances/Index', [
            'allegiances' => Allegiance::orderBy('is_syndicate')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/Allegiances/AllegianceForm', [
            'allegiance_types' => fn () => AllegianceTypeEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Allegiance $allegiance)
    {
        return inertia('Admin/TOS/Allegiances/AllegianceForm', [
            'allegiance' => $allegiance,
            'allegiance_types' => fn () => AllegianceTypeEnum::toSelectOptions(),
        ]);
    }

    public function store(StoreAllegianceRequest $request)
    {
        $data = $request->validated();
        $data['logo_path'] = $this->storeTosImage($request->file('logo_path'), 'tos/allegiances');

        $allegiance = Allegiance::create($data);

        return redirect()->route('admin.tos.allegiances.index')->withMessage("{$allegiance->name} created successfully.");
    }

    public function update(UpdateAllegianceRequest $request, Allegiance $allegiance)
    {
        $data = $request->validated();

        if ($request->hasFile('logo_path')) {
            $this->deleteTosImage($allegiance->logo_path);
            $data['logo_path'] = $this->storeTosImage($request->file('logo_path'), 'tos/allegiances');
        } else {
            unset($data['logo_path']);
        }

        $allegiance->update($data);

        return redirect()->route('admin.tos.allegiances.index')->withMessage("{$allegiance->name} has been updated.");
    }

    public function delete(Request $request, Allegiance $allegiance)
    {
        $name = $allegiance->name;
        $allegiance->delete();

        return redirect()->route('admin.tos.allegiances.index')->withMessage("{$name} has been deleted.");
    }
}
