<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use Inertia\ResponseFactory;
use Throwable;

class FailedJobsAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        $rows = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->paginate(50);

        // Decode payload + lift commonly-needed fields onto each row so the
        // Vue page doesn't need to reach into raw JSON.
        $rows->getCollection()->transform(function ($row) {
            $payload = json_decode($row->payload, true) ?: [];
            $row->job_name = $payload['displayName'] ?? ($payload['job'] ?? 'Unknown');
            $row->exception_summary = $this->summarizeException($row->exception);

            return $row;
        });

        return inertia('Admin/FailedJobs/Index', [
            'jobs' => $rows,
        ]);
    }

    public function retry(Request $request, string $uuid): RedirectResponse
    {
        $exists = DB::table('failed_jobs')->where('uuid', $uuid)->exists();
        if (! $exists) {
            return back()->withMessage('Failed job not found.');
        }

        try {
            Artisan::call('queue:retry', ['id' => [$uuid]]);

            return back()->withMessage('Job queued for retry.');
        } catch (Throwable $e) {
            return back()->withMessage('Retry failed: '.$e->getMessage());
        }
    }

    public function retryAll(Request $request): RedirectResponse
    {
        try {
            Artisan::call('queue:retry', ['id' => ['all']]);

            return back()->withMessage('All failed jobs queued for retry.');
        } catch (Throwable $e) {
            return back()->withMessage('Retry-all failed: '.$e->getMessage());
        }
    }

    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();

        return back()->withMessage('Failed job deleted.');
    }

    public function flush(Request $request): RedirectResponse
    {
        DB::table('failed_jobs')->delete();

        return back()->withMessage('All failed jobs cleared.');
    }

    /**
     * Pull the exception class name + first line of the message out of the raw
     * stack trace. Keeps the index page tidy — full trace is on the detail row.
     */
    private function summarizeException(?string $exception): array
    {
        if (! $exception) {
            return ['class' => null, 'message' => null];
        }

        // The first line typically looks like: "Some\Exception\Class: Message text".
        $firstLine = strtok($exception, "\n");
        if (preg_match('/^(\S+):\s*(.*)$/', $firstLine ?: '', $m)) {
            return ['class' => $m[1], 'message' => $m[2]];
        }

        return ['class' => null, 'message' => $firstLine];
    }
}
