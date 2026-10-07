<?php

namespace App\Listeners;

use App\Support\AdminAudit;
use Lab404\Impersonate\Events\LeaveImpersonation;
use Lab404\Impersonate\Events\TakeImpersonation;

class LogImpersonation
{
    public function onTake(TakeImpersonation $event): void
    {
        $this->record('Started impersonating', $event->impersonator, $event->impersonated);
    }

    public function onLeave(LeaveImpersonation $event): void
    {
        $this->record('Stopped impersonating', $event->impersonator, $event->impersonated);
    }

    private function record(string $verb, mixed $impersonator, mixed $impersonated): void
    {
        $activity = activity(AdminAudit::LOG_NAME)
            ->event('impersonation')
            ->causedBy($impersonator)
            ->performedOn($impersonated)
            ->log($verb.' '.($impersonated->name ?? 'a user'));

        unset($activity);
    }
}
