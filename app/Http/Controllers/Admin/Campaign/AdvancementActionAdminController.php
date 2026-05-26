<?php

namespace App\Http\Controllers\Admin\Campaign;

use App\Models\Campaign\AdvancementAction;

class AdvancementActionAdminController extends BaseAdvancementAdminController
{
    protected function modelClass(): string
    {
        return AdvancementAction::class;
    }

    protected function routePrefix(): string
    {
        return 'admin.campaign.advancement-action';
    }

    protected function displayLabel(): string
    {
        return 'Action';
    }
}
