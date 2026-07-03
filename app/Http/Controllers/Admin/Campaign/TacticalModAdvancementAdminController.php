<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Models\Campaign\AdvancementTacticalMod;

class TacticalModAdvancementAdminController extends BaseAttackTacticalAdvancementAdminController
{
    protected function modelClass(): string
    {
        return AdvancementTacticalMod::class;
    }

    protected function routePrefix(): string
    {
        return 'admin.campaign.advancement-tactical-mod';
    }

    protected function displayLabel(): string
    {
        return 'Tactical Modification';
    }
}
