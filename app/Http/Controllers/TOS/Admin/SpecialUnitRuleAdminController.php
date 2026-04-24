<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreSpecialUnitRuleRequest;
use App\Http\Requests\TOS\Admin\UpdateSpecialUnitRuleRequest;
use App\Models\TOS\SpecialUnitRule;
use Illuminate\Http\Request;

class SpecialUnitRuleAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/TOS/SpecialRules/Index', [
            'rules' => SpecialUnitRule::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/SpecialRules/SpecialRuleForm');
    }

    public function edit(Request $request, SpecialUnitRule $rule)
    {
        return inertia('Admin/TOS/SpecialRules/SpecialRuleForm', [
            'rule' => $rule,
        ]);
    }

    public function store(StoreSpecialUnitRuleRequest $request)
    {
        $rule = SpecialUnitRule::create($request->validated());

        return redirect()->route('admin.tos.special_rules.index')->withMessage("{$rule->name} created.");
    }

    public function update(UpdateSpecialUnitRuleRequest $request, SpecialUnitRule $rule)
    {
        $rule->update($request->validated());

        return redirect()->route('admin.tos.special_rules.index')->withMessage("{$rule->name} updated.");
    }

    public function delete(Request $request, SpecialUnitRule $rule)
    {
        $name = $rule->name;
        $rule->delete();

        return redirect()->route('admin.tos.special_rules.index')->withMessage("{$name} deleted.");
    }
}
