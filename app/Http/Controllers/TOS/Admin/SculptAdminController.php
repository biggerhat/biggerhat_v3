<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreSculptRequest;
use App\Http\Requests\TOS\Admin\UpdateSculptRequest;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use App\Traits\TOS\HandlesTosImageUpload;
use Illuminate\Http\Request;

class SculptAdminController extends Controller
{
    use HandlesTosImageUpload;

    public function index(Request $request)
    {
        return inertia('Admin/TOS/Sculpts/Index', [
            'sculpts' => UnitSculpt::with('unit:id,name')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/Sculpts/SculptForm', [
            'units' => fn () => Unit::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, UnitSculpt $sculpt)
    {
        return inertia('Admin/TOS/Sculpts/SculptForm', [
            'sculpt' => $sculpt->loadMissing('unit:id,name'),
            'units' => fn () => Unit::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreSculptRequest $request)
    {
        $data = $request->validated();
        $folder = 'tos/sculpts';

        $frontPath = $this->storeTosImage($request->file('front_image'), $folder);
        $backPath = $this->storeTosImage($request->file('back_image'), $folder);

        $sculpt = UnitSculpt::create([
            'unit_id' => $data['unit_id'],
            'name' => $data['name'],
            'front_image' => $frontPath,
            'back_image' => $backPath,
            'release_date' => $data['release_date'] ?? null,
            'box_reference' => $data['box_reference'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $this->regenerateComboImage($sculpt);

        return redirect()->route('admin.tos.sculpts.index')->withMessage("{$sculpt->name} created.");
    }

    public function update(UpdateSculptRequest $request, UnitSculpt $sculpt)
    {
        $data = $request->validated();
        $folder = 'tos/sculpts';

        $payload = [
            'unit_id' => $data['unit_id'],
            'name' => $data['name'],
            'release_date' => $data['release_date'] ?? null,
            'box_reference' => $data['box_reference'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ];

        $imagesChanged = false;

        if ($request->hasFile('front_image')) {
            $this->deleteTosImage($sculpt->front_image);
            $payload['front_image'] = $this->storeTosImage($request->file('front_image'), $folder);
            $imagesChanged = true;
        }

        if ($request->hasFile('back_image')) {
            $this->deleteTosImage($sculpt->back_image);
            $payload['back_image'] = $this->storeTosImage($request->file('back_image'), $folder);
            $imagesChanged = true;
        }

        $sculpt->update($payload);

        if ($imagesChanged) {
            $this->regenerateComboImage($sculpt);
        }

        return redirect()->route('admin.tos.sculpts.index')->withMessage("{$sculpt->name} updated.");
    }

    public function delete(Request $request, UnitSculpt $sculpt)
    {
        $name = $sculpt->name;

        $this->deleteTosImage($sculpt->front_image);
        $this->deleteTosImage($sculpt->back_image);
        $this->deleteTosImage($sculpt->combination_image);

        $sculpt->delete();

        return redirect()->route('admin.tos.sculpts.index')->withMessage("{$name} deleted.");
    }

    /**
     * Rebuild the combination image after a front/back change.
     * - front + back → merged JPEG via GD (Malifaux parity)
     * - front only  → combo is the front image
     * - neither     → combo cleared
     */
    private function regenerateComboImage(UnitSculpt $sculpt): void
    {
        $this->deleteTosImage($sculpt->combination_image);

        if ($sculpt->front_image && $sculpt->back_image) {
            $combo = $this->generateTosComboImage($sculpt->front_image, $sculpt->back_image, 'tos/sculpts');
            $sculpt->update(['combination_image' => $combo]);

            return;
        }

        if ($sculpt->front_image) {
            $sculpt->update(['combination_image' => $sculpt->front_image]);

            return;
        }

        $sculpt->update(['combination_image' => null]);
    }
}
