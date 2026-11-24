<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BattleReportStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\BattleReports\DismissBattleReportFlagsRequest;
use App\Http\Requests\BattleReports\RejectBattleReportRequest;
use App\Models\BattleReport;
use App\Models\BattleReportDecision;
use App\Notifications\BattleReport\BattleReportModerated;
use App\Support\AdminAudit;
use App\Support\BattleReports\ApprovedSnapshot;
use App\Support\BattleReports\TrustedAuthors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * The queue for battle reports writers ask to make public. A moderator reads the report (the writer's own page, which a
 * moderator may open while it waits) and approves or turns it down; either way the writer is told. A public report can be
 * taken down here too, which is the same as turning it down. Private reports are never listed.
 */
class BattleReportAdminController extends Controller
{
    private const STATUSES = ['pending', 'reported', 'approved', 'rejected'];

    public function index(Request $request): Response|ResponseFactory
    {
        $status = in_array($request->query('status'), self::STATUSES, true) ? (string) $request->query('status') : 'pending';

        $query = $status === 'reported'
            ? BattleReport::whereHas('flags', fn ($q) => $q->whereNull('resolved_at'))
            : BattleReport::where('status', $status);
        $reports = $query
            ->with(['author:id,name,slug', 'moderator:id,name', 'flags' => fn ($q) => $q->whereNull('resolved_at')->with('reporter:id,name')])
            ->orderBy($status === 'pending' ? 'updated_at' : 'moderated_at', $status === 'pending' ? 'asc' : 'desc')
            ->limit(100)
            ->get();
        // What moderators decided on these reports before (newest first), so nobody is judged blind.
        $history = BattleReportDecision::whereIn('battle_report_id', $reports->pluck('id'))->with('moderator:id,name')->latest('id')->get()->groupBy('battle_report_id');
        $reports = $reports->map(fn (BattleReport $r) => [
            'uuid' => $r->uuid,
            'title' => $r->title,
            'excerpt' => $r->excerpt(240),
            'author' => ['name' => $r->author->name, 'slug' => $r->author->slug],
            'updated_at' => $r->updated_at?->toDateString(),
            'moderated_at' => $r->moderated_at?->toDateString(),
            'moderated_by' => $r->moderator?->name,
            'auto_approved' => $r->status === BattleReportStatusEnum::Approved && $r->moderated_by === null,
            'note' => $r->moderation_note,
            'appeal_note' => $r->status === BattleReportStatusEnum::Pending ? $r->appeal_note : null,
            'history' => ($history[$r->id] ?? collect())->take(4)->map(fn (BattleReportDecision $d) => [
                'decision' => $d->decision,
                'by' => $d->moderator?->name,
                'note' => $d->note,
                'date' => $d->created_at?->toDateString(),
                'overturned' => $d->overturned_at !== null,
            ])->values()->all(),
            'flags' => $r->flags->map(fn ($f) => ['reporter' => $f->reporter->name, 'reason' => $f->reason])->values()->all(),
            'url' => route('battle-reports.show', $r),
        ]);

        return inertia('Admin/BattleReports/Index', [
            'reports' => $reports,
            'status' => $status,
            'counts' => collect(self::STATUSES)->mapWithKeys(fn (string $s) => [
                $s => $s === 'reported' ? BattleReport::whereHas('flags', fn ($q) => $q->whereNull('resolved_at'))->count() : BattleReport::where('status', $s)->count(),
            ]),
        ]);
    }

    public function approve(Request $request, BattleReport $report): RedirectResponse
    {
        Gate::authorize('moderate', $report);
        // Two moderators can open the same report; re-read it under the write lock (admin writes run in a transaction).
        $report = BattleReport::lockForUpdate()->findOrFail($report->id);
        if ($report->status !== BattleReportStatusEnum::Pending) {
            return back()->withMessage('That report is not waiting for approval.');
        }

        // The appeal note is still on the report here, so the record can tell this approval answers an appeal.
        TrustedAuthors::record($report, 'approved', $request->user());
        $report->update([
            'status' => BattleReportStatusEnum::Approved,
            'published_at' => now(),
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
            'moderation_note' => null,
            'appeal_note' => null,
            'approved_snapshot' => ApprovedSnapshot::capture($report),
        ]);
        $report->flags()->whereNull('resolved_at')->update(['resolved_at' => now()]);
        AdminAudit::log('Approved battle report #'.$report->id, $report);
        $report->author->notify(new BattleReportModerated($report, 'approved'));

        return back()->withMessage('Report approved. It is public now.');
    }

    public function reject(RejectBattleReportRequest $request, BattleReport $report): RedirectResponse
    {
        $report = BattleReport::lockForUpdate()->findOrFail($report->id);
        if (! in_array($report->status, [BattleReportStatusEnum::Pending, BattleReportStatusEnum::Approved], true)) {
            return back()->withMessage('That report is not waiting for approval or public.');
        }

        TrustedAuthors::record($report, 'rejected', $request->user(), $request->validated()['note'] ?? null);
        $report->update([
            'status' => BattleReportStatusEnum::Rejected,
            'published_at' => null,
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
            'moderation_note' => $request->validated()['note'] ?? null,
            'approved_snapshot' => null,
        ]);
        $report->flags()->whereNull('resolved_at')->update(['resolved_at' => now()]);
        AdminAudit::log('Turned down battle report #'.$report->id, $report);
        $report->author->notify(new BattleReportModerated($report, 'rejected'));

        return back()->withMessage('Report not approved. The writer was told.');
    }

    /** A moderator read the reports against a public report and leaves it public. */
    public function dismissFlags(DismissBattleReportFlagsRequest $request, BattleReport $report): RedirectResponse
    {
        $resolved = $report->flags()->whereNull('resolved_at')->update(['resolved_at' => now()]);
        if ($resolved > 0) {
            AdminAudit::log('Dismissed reports against battle report #'.$report->id, $report);
        }

        return back()->withMessage('Dismissed. The report stays public.');
    }
}
