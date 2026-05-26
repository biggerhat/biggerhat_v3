<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreAdvancementRequest;
use App\Http\Requests\Admin\Campaign\UpdateAdvancementRequest;
use App\Models\Campaign\Advancement;
use Illuminate\Http\Request;

/**
 * Shared CRUD for the four flip-based Leader advancement tables. The 4 catalogs
 * (attack-mod, tactical-mod, action, ability) have identical schemas — only
 * the physical table differs — so one controller class is parameterized by
 * subclass with the model + route prefix, and Vue uses a single shared form.
 */
abstract class BaseAdvancementAdminController extends Controller
{
    /** @return class-string<Advancement> */
    abstract protected function modelClass(): string;

    /** Route-name prefix, e.g. `admin.campaign.advancement-attack-mod`. */
    abstract protected function routePrefix(): string;

    /** Human label for the page header. */
    abstract protected function displayLabel(): string;

    /** Inertia component path — shared across all 4. */
    private const INDEX_VIEW = 'Admin/Campaign/Advancement/Index';

    private const FORM_VIEW = 'Admin/Campaign/Advancement/Form';

    public function index(Request $request)
    {
        $model = $this->modelClass();

        return inertia(self::INDEX_VIEW, [
            'items' => $model::orderByRaw('flip_value IS NULL, flip_value ASC')
                ->orderBy('name')
                ->get(['id', 'name', 'flip_value', 'is_always_available', 'is_black_joker', 'is_red_joker', 'modifier_type', 'suit', 'grants_signature', 'joker_freechoice']),
            'route_prefix' => $this->routePrefix(),
            'display_label' => $this->displayLabel(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia(self::FORM_VIEW, [
            'route_prefix' => $this->routePrefix(),
            'display_label' => $this->displayLabel(),
            'suit_options' => fn () => SuitEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, $advancement)
    {
        $row = $this->modelClass()::findOrFail($advancement);

        return inertia(self::FORM_VIEW, [
            'item' => $row,
            'route_prefix' => $this->routePrefix(),
            'display_label' => $this->displayLabel(),
            'suit_options' => fn () => SuitEnum::toSelectOptions(),
        ]);
    }

    public function store(StoreAdvancementRequest $request)
    {
        $row = $this->modelClass()::create($request->validated());

        return redirect()->route($this->routePrefix().'.index')
            ->withMessage("{$row->name} created.");
    }

    public function update(UpdateAdvancementRequest $request, $advancement)
    {
        $row = $this->modelClass()::findOrFail($advancement);
        $row->update($request->validated());

        return redirect()->route($this->routePrefix().'.index')
            ->withMessage("{$row->name} updated.");
    }

    public function delete(Request $request, $advancement)
    {
        $row = $this->modelClass()::findOrFail($advancement);
        $name = $row->name;
        $row->delete();

        return redirect()->route($this->routePrefix().'.index')
            ->withMessage("{$name} deleted.");
    }
}
