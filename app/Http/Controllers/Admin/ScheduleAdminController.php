<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

class ScheduleAdminController extends Controller
{
    public function index(Request $request, Schedule $schedule): Response|ResponseFactory
    {
        $tasks = collect($schedule->events())->map(function (Event $event) {
            $description = trim($event->description ?? '');
            $command = $event->command ?? '';

            return [
                'description' => $description !== '' ? $description : $command,
                'command' => $this->prettyCommand($command),
                'expression' => $event->expression,
                'timezone' => $event->timezone instanceof \DateTimeZone ? $event->timezone->getName() : (string) $event->timezone,
                'next_run' => $event->nextRunDate()->toIso8601String(),
            ];
        })->values();

        return inertia('Admin/Schedule/Index', [
            'tasks' => $tasks,
        ]);
    }

    /**
     * Strip the `'/usr/bin/php' 'artisan' ...` boilerplate Laravel prepends so
     * the table shows just the artisan call.
     */
    private function prettyCommand(string $command): string
    {
        if (preg_match("/'artisan'\s+(.+)$/", $command, $m)) {
            return 'artisan '.trim(str_replace("'", '', $m[1]));
        }

        return $command;
    }
}
