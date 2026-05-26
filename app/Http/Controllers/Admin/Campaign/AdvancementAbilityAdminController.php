<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Models\Campaign\AdvancementAbility;

class AdvancementAbilityAdminController extends BaseAdvancementAdminController
{
    protected function modelClass(): string
    {
        return AdvancementAbility::class;
    }

    protected function routePrefix(): string
    {
        return 'admin.campaign.advancement-ability';
    }

    protected function displayLabel(): string
    {
        return 'Ability';
    }
}
