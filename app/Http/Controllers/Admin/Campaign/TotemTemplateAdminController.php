<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Enums\FactionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreTotemTemplateRequest;
use App\Http\Requests\Admin\Campaign\UpdateTotemTemplateRequest;
use App\Models\CustomCharacter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TotemTemplateAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Campaign/TotemTemplate/Index', [
            'items' => CustomCharacter::query()
                ->where('is_campaign_totem_template', true)
                ->orderBy('campaign_totem_flip_value')
                ->orderBy('name')
                ->get(['id', 'name', 'faction', 'campaign_totem_flip_value', 'campaign_is_black_joker_totem', 'campaign_is_red_joker_totem', 'campaign_totem_special_replace']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Campaign/TotemTemplate/Form', [
            'factions' => FactionEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, CustomCharacter $totemTemplate)
    {
        abort_unless($totemTemplate->is_campaign_totem_template, 404);

        return inertia('Admin/Campaign/TotemTemplate/Form', [
            'item' => $totemTemplate,
            'factions' => FactionEnum::toSelectOptions(),
        ]);
    }

    public function store(StoreTotemTemplateRequest $request)
    {
        $validated = $request->validated();
        $name = $validated['name'];

        CustomCharacter::create(array_merge($validated, [
            'user_id' => $request->user()->id,
            'display_name' => $name,
            'slug' => Str::slug($name),
            'is_campaign_totem_template' => true,
            'is_public' => false,
            'count' => 1,
        ]));

        return redirect()->route('admin.campaign.totem-templates.index')->withMessage("{$name} created.");
    }

    public function update(UpdateTotemTemplateRequest $request, CustomCharacter $totemTemplate)
    {
        abort_unless($totemTemplate->is_campaign_totem_template, 404);

        $validated = $request->validated();
        $name = $validated['name'];

        $totemTemplate->update(array_merge($validated, [
            'display_name' => $name,
            'slug' => Str::slug($name),
        ]));

        return redirect()->route('admin.campaign.totem-templates.index')->withMessage("{$totemTemplate->name} updated.");
    }

    public function delete(Request $request, CustomCharacter $totemTemplate)
    {
        abort_unless($totemTemplate->is_campaign_totem_template, 404);

        $name = $totemTemplate->name;
        $totemTemplate->delete();

        return redirect()->route('admin.campaign.totem-templates.index')->withMessage("{$name} deleted.");
    }
}
