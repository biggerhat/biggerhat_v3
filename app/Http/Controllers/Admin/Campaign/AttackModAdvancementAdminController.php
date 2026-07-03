<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Models\Campaign\AdvancementAttackMod;

class AttackModAdvancementAdminController extends BaseAttackTacticalAdvancementAdminController
{
    protected function modelClass(): string
    {
        return AdvancementAttackMod::class;
    }

    protected function routePrefix(): string
    {
        return 'admin.campaign.advancement-attack-mod';
    }

    protected function displayLabel(): string
    {
        return 'Attack Modification';
    }
}
