<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreAttackTacticalAdvancementRequest;
use App\Http\Requests\Admin\Campaign\UpdateAttackTacticalAdvancementRequest;
use App\Models\Campaign\AdvancementAttackMod;
use App\Models\Campaign\AdvancementTacticalMod;
use App\Models\Trigger;
use Illuminate\Http\Request;

/**
 * Shared CRUD for the Attack Mod / Tactical Mod advancement tables. Same
 * schema, same form — only the physical table differs per subclass.
 */
abstract class BaseAttackTacticalAdvancementAdminController extends Controller
{
    /** @return class-string<AdvancementAttackMod>|class-string<AdvancementTacticalMod> */
    abstract protected function modelClass(): string;

    /** Route-name prefix, e.g. `admin.campaign.advancement-attack-mod`. */
    abstract protected function routePrefix(): string;

    /** Human label for the page header. */
    abstract protected function displayLabel(): string;

    private const INDEX_VIEW = 'Admin/Campaign/Advancement/AttackTacticalIndex';

    private const FORM_VIEW = 'Admin/Campaign/Advancement/AttackTacticalForm';

    public function index(Request $request)
    {
        $model = $this->modelClass();

        return inertia(self::INDEX_VIEW, [
            'items' => $model::orderByRaw('flip_value IS NULL, flip_value ASC')
                ->orderBy('name')
                ->get(['id', 'name', 'flip_value', 'is_black_joker', 'is_red_joker', 'is_always_available', 'modifier_type', 'suit', 'trigger_id']),
            'route_prefix' => $this->routePrefix(),
            'display_label' => $this->displayLabel(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia(self::FORM_VIEW, $this->formData());
    }

    public function edit(Request $request, $advancement)
    {
        $row = $this->modelClass()::with('trigger:id,name')->findOrFail($advancement);

        return inertia(self::FORM_VIEW, [
            'item' => $row,
            ...$this->formData(),
        ]);
    }

    public function store(StoreAttackTacticalAdvancementRequest $request)
    {
        $row = $this->modelClass()::create($request->validated());

        return redirect()->route("{$this->routePrefix()}.index")->withMessage("{$row->name} created.");
    }

    public function update(UpdateAttackTacticalAdvancementRequest $request, $advancement)
    {
        $row = $this->modelClass()::findOrFail($advancement);
        $row->update($request->validated());

        return redirect()->route("{$this->routePrefix()}.index")->withMessage("{$row->name} updated.");
    }

    public function delete(Request $request, $advancement)
    {
        $row = $this->modelClass()::findOrFail($advancement);
        $name = $row->name;
        $row->delete();

        return redirect()->route("{$this->routePrefix()}.index")->withMessage("{$name} deleted.");
    }

    private function formData(): array
    {
        return [
            'route_prefix' => $this->routePrefix(),
            'display_label' => $this->displayLabel(),
            'suit_options' => fn () => SuitEnum::toSelectOptions(),
            'triggers' => fn () => Trigger::toSelectOptions('name'),
        ];
    }
}
