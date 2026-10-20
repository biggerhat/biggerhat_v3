<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Response;
use Inertia\ResponseFactory;
use Throwable;

class FailedJobsAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        // Select only what the page needs — `payload` can be hundreds of KB per
        // row — and cap the stack trace; the summary line carries the headline.
        $rows = DB::table('failed_jobs')
            ->select(['id', 'uuid', 'connection', 'queue', 'failed_at', 'exception', 'payload'])
            ->orderByDesc('failed_at')
            ->paginate(50);

        $rows->getCollection()->transform(function ($row) {
            $payload = json_decode($row->payload, true) ?: [];

            return [
                'id' => $row->id,
                'uuid' => $row->uuid,
                'connection' => $row->connection,
                'queue' => $row->queue,
                'failed_at' => $row->failed_at,
                'job_name' => $payload['displayName'] ?? ($payload['job'] ?? 'Unknown'),
                'exception_summary' => $this->summarizeException($row->exception),
                'exception' => Str::limit($row->exception, 4000, "\n… (truncated)"),
            ];
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
            AdminAudit::log('Retried a failed job', null, ['uuid' => $uuid]);

            return back()->withMessage('Job queued for retry.');
        } catch (Throwable $e) {
            return back()->withMessage('Retry failed: '.$e->getMessage());
        }
    }

    public function retryAll(Request $request): RedirectResponse
    {
        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
            AdminAudit::log('Retried all failed jobs');

            return back()->withMessage('All failed jobs queued for retry.');
        } catch (Throwable $e) {
            return back()->withMessage('Retry-all failed: '.$e->getMessage());
        }
    }

    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        AdminAudit::log('Deleted a failed job', null, ['uuid' => $uuid]);

        return back()->withMessage('Failed job deleted.');
    }

    public function flush(Request $request): RedirectResponse
    {
        $count = DB::table('failed_jobs')->count();
        DB::table('failed_jobs')->delete();
        AdminAudit::log('Flushed all failed jobs', null, ['count' => $count]);

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
