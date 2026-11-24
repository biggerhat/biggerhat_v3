<?php

namespace App\Http\Controllers;

use App\Enums\BattleReportStatusEnum;
use App\Enums\GameStatusEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Concerns\BuildsPageMeta;
use App\Http\Requests\BattleReports\AppealBattleReportRequest;
use App\Http\Requests\BattleReports\BattleReportImageRequest;
use App\Http\Requests\BattleReports\FlagBattleReportRequest;
use App\Http\Requests\BattleReports\StoreBattleReportCommentRequest;
use App\Http\Requests\BattleReports\StoreBattleReportRequest;
use App\Http\Requests\BattleReports\UpdateBattleReportRequest;
use App\Models\BattleReport;
use App\Models\BattleReportComment;
use App\Models\CrewBuild;
use App\Models\Game;
use App\Models\User;
use App\Notifications\BattleReport\BattleReportAppealed;
use App\Notifications\BattleReport\BattleReportApprovalRequested;
use App\Notifications\BattleReport\BattleReportCommented;
use App\Notifications\BattleReport\BattleReportFlagged;
use App\Support\BattleReports\ApprovedSnapshot;
use App\Support\BattleReports\ModeratorAlerts;
use App\Support\BattleReports\TrustedAuthors;
use App\Support\Game\BattleReportCrewCard;
use App\Support\Game\BattleReportGameCard;
use App\Support\Game\LinkableRounds;
use App\Tournaments\RichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Battle reports: a player's written account of a game, in the same rich-text editor as articles. A report may be tied to a
 * game the writer played (tracked or logged) or stand alone. Private until the writer makes it public.
 *
 * The document is rebuilt through the RichText allow-list before it is stored, because every writer is an ordinary user.
 */
class BattleReportController extends Controller
{
    use BuildsPageMeta;

    private const PER_PAGE = 12;

    /** Public reports for everyone; the signed-in writer also gets their own, drafts included. */
    public function index(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        $public = BattleReport::public()
            ->with(['author:id,name,slug', 'game.players.user:id,name', 'game.strategy:id,name', ...array_map(fn ($w) => 'tournamentGame.'.$w, LinkableRounds::WITH)])
            ->latest('published_at')
            ->paginate(self::PER_PAGE)
            ->through(fn (BattleReport $r) => $this->row($r));

        $mine = $user === null ? [] : BattleReport::where('user_id', $user->id)
            ->with(['game.players.user:id,name', 'game.strategy:id,name', ...array_map(fn ($w) => 'tournamentGame.'.$w, LinkableRounds::WITH)])
            ->latest('updated_at')
            ->get()
            ->map(fn (BattleReport $r) => $this->row($r) + $this->statusOf($r))
            ->all();

        return inertia('BattleReports/Index', ['reports' => $public, 'mine' => $mine])
            ->withViewData(['page_meta' => $this->pageMeta('Battle reports', 'Written accounts of Malifaux games from the BiggerHat community.')]);
    }

    public function show(Request $request, BattleReport $report): Response|ResponseFactory
    {
        // A private report is not there at all for anyone but its writer.
        abort_unless(Gate::forUser($request->user())->allows('view', $report), 404);
        $report->load(['author:id,name,slug', ...array_map(fn ($w) => 'game.'.$w, BattleReportGameCard::WITH), ...array_map(fn ($w) => 'tournamentGame.'.$w, LinkableRounds::WITH)]);
        $canEdit = $request->user()?->id === $report->user_id;

        $response = inertia('BattleReports/Show', [
            'report' => [
                'uuid' => $report->uuid,
                'title' => $report->title,
                'content' => $report->content,
                'published_at' => $report->published_at?->toDateString(),
            ] + $this->statusOf($report),
            'author' => ['name' => $report->author->name, 'slug' => $report->author->slug],
            'game' => $this->cardOf($report),
            'crew' => $report->crew ? BattleReportCrewCard::for($report->crew) : null,
            'can_edit' => $canEdit,
            'comments' => $report->isPublic() ? $this->commentsFor($report, $request->user()) : [],
            'allow_comments' => $report->allow_comments,
            'can_comment' => $request->user() !== null && Gate::forUser($request->user())->allows('comment', $report),
            'reactions' => ['count' => $report->isPublic() ? $report->reactions()->count() : 0, 'mine' => $request->user() !== null && $report->reactions()->where('user_id', $request->user()->id)->exists()],
            'can_flag' => $request->user() !== null && Gate::forUser($request->user())->allows('flag', $report) && ! $report->flags()->where('user_id', $request->user()->id)->exists(),
            'flagged' => $request->user() !== null && $report->flags()->where('user_id', $request->user()->id)->exists(),
            'can_moderate' => $request->user() !== null && Gate::forUser($request->user())->allows('moderate', $report) && $report->status === BattleReportStatusEnum::Pending,
        ]);

        return $report->isPublic()
            ? $response->withViewData(['page_meta' => $this->pageMeta($report->title, $report->excerpt() ?: 'A battle report by '.$report->author->name.'.', $report->firstImage(), 'article')])
            : $response;
    }

    public function create(Request $request): Response|ResponseFactory|RedirectResponse
    {
        $game = null;
        if ($uuid = $request->query('game')) {
            /** @var Game|null $game */
            $game = $this->myFinishedGames($request)->where('uuid', $uuid)->first();
            if ($game && ($existing = BattleReport::where('user_id', $request->user()->id)->where('game_id', $game->id)->first())) {
                return redirect()->route('battle-reports.edit', $existing);
            }
        }

        return inertia('BattleReports/Form', [
            'report' => null,
            'crews' => $this->crewOptions($request),
            'games' => $this->gameOptions($request),
            'preselected' => $game?->uuid,
            'suggested_title' => $game ? $this->suggestedTitle($game) : '',
        ]);
    }

    public function store(StoreBattleReportRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $report = BattleReport::create([
            'user_id' => $request->user()->id,
            'game_id' => $request->game()?->id,
            'tournament_game_id' => $request->round()?->id,
            'crew_build_id' => $data['crew_id'] ?? null,
            'title' => $data['title'],
            'content' => RichText::sanitize($data['content'] ?? null),
            'show_game' => (bool) ($data['show_game'] ?? true),
            'allow_comments' => (bool) ($data['allow_comments'] ?? true),
            'status' => BattleReportStatusEnum::Private,
        ]);
        $this->settleVisibility($report, (bool) ($data['is_public'] ?? false), changed: true);

        return redirect()->route('battle-reports.show', $report)->withMessage($this->savedMessage($report), null, MessageTypeEnum::success);
    }

    public function edit(Request $request, BattleReport $report): Response|ResponseFactory
    {
        Gate::forUser($request->user())->authorize('update', $report);

        return inertia('BattleReports/Form', [
            'report' => [
                'uuid' => $report->uuid,
                'title' => $report->title,
                'content' => $report->content,
                'game_uuid' => $report->game->uuid ?? ($report->tournament_game_id ? 'round:'.$report->tournament_game_id : null),
                'crew_id' => $report->crew_build_id,
            ] + $this->statusOf($report),
            'games' => $this->gameOptions($request, $report->game, $report->tournament_game_id),
            'crews' => $this->crewOptions($request),
            'preselected' => null,
            'suggested_title' => '',
        ]);
    }

    public function update(UpdateBattleReportRequest $request, BattleReport $report): RedirectResponse
    {
        $data = $request->validated();
        $content = RichText::sanitize($data['content'] ?? null);
        $showGame = (bool) ($data['show_game'] ?? true);
        $gameId = $request->game()?->id;
        $roundId = $request->round()?->id;
        $link = $gameId ? 'g'.$gameId : ($roundId ? 't'.$roundId : null);

        // What counts as a change depends on where the report stands: an approved one is only read again for a change that matters
        // (see ApprovedSnapshot); a turned-down one is asked about again for any change.
        $anyChange = $report->title !== $data['title'] || json_encode($report->content) !== json_encode($content) || $report->show_game !== $showGame || $report->linkKey() !== $link;
        $changed = $report->status === BattleReportStatusEnum::Approved
            ? ApprovedSnapshot::needsReview($report->approved_snapshot, $data['title'], $link, $showGame, $content)
            : $anyChange;
        $report->update(['crew_build_id' => $data['crew_id'] ?? null, 'game_id' => $gameId, 'tournament_game_id' => $roundId, 'title' => $data['title'], 'content' => $content, 'show_game' => $showGame, 'allow_comments' => (bool) ($data['allow_comments'] ?? true)]);
        $this->settleVisibility($report, (bool) ($data['is_public'] ?? false), $changed);

        return redirect()->route('battle-reports.show', $report)->withMessage($this->savedMessage($report), null, MessageTypeEnum::success);
    }

    public function destroy(Request $request, BattleReport $report): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('delete', $report);
        $report->delete();

        return redirect()->route('battle-reports.index')->withMessage('Report deleted.', null, MessageTypeEnum::success);
    }

    /** The writer asks the moderators to look again at a report that was turned down, without changing it. Once per turn-down. */
    public function appeal(AppealBattleReportRequest $request, BattleReport $report): RedirectResponse
    {
        $report->update(['status' => BattleReportStatusEnum::Pending, 'appeal_note' => $request->validated()['note']]);
        ModeratorAlerts::send($report, new BattleReportAppealed($report), "A writer asked for a second look at the battle report \"{$report->title}\".");

        return redirect()->route('battle-reports.show', $report)->withMessage('Sent. A moderator will look again.', null, MessageTypeEnum::success);
    }

    /** A reader reports a public report. One per reader; moderators are told. */
    public function flag(FlagBattleReportRequest $request, BattleReport $report): RedirectResponse
    {
        $flag = $report->flags()->firstOrCreate(['user_id' => $request->user()->id], ['reason' => $request->validated()['reason']]);
        if ($flag->wasRecentlyCreated) {
            ModeratorAlerts::send($report, new BattleReportFlagged($report), "A reader reported the public battle report \"{$report->title}\".");
        }

        return back()->withMessage('Thank you. A moderator will look at it.', null, MessageTypeEnum::success);
    }

    /** A reader comments on a public report; the writer is told. */
    public function comment(StoreBattleReportCommentRequest $request, BattleReport $report): RedirectResponse
    {
        $comment = $report->comments()->create(['user_id' => $request->user()->id, 'body' => trim($request->validated()['body'])]);
        if ($report->user_id !== $request->user()->id) {
            $report->author->notify(new BattleReportCommented($report, $comment));
        }

        return back()->withMessage('Comment posted.', null, MessageTypeEnum::success);
    }

    public function deleteComment(Request $request, BattleReport $report, int $comment): RedirectResponse
    {
        $row = $report->comments()->findOrFail($comment);
        Gate::forUser($request->user())->authorize('deleteComment', [$report, $row]);
        $row->delete();

        return back()->withMessage('Comment removed.', null, MessageTypeEnum::success);
    }

    /** "Nice report": toggled on and off by a signed-in reader, on a public report. */
    public function react(Request $request, BattleReport $report): RedirectResponse
    {
        abort_unless($report->isPublic(), 404);
        $existing = $report->reactions()->where('user_id', $request->user()->id);
        if ($existing->exists()) {
            $existing->delete();
        } else {
            $report->reactions()->create(['user_id' => $request->user()->id]);
        }

        return back();
    }

    public function uploadImage(BattleReportImageRequest $request): JsonResponse
    {
        $path = $request->file('image')->store("battle-reports/{$request->user()->id}", 'public');

        return response()->json(['url' => '/storage/'.$path]);
    }

    /**
     * What the writer's "Ask to make this report public" switch does to the report's standing. Asking puts it in front of a
     * moderator; it is public only once approved (at once, for a writer the moderators trust). An approved report the writer
     * changes in a way that matters goes back to the moderators and is hidden until they approve it again; a small edit leaves it
     * public. A turned-down report is asked about again only when it changes.
     */
    private function settleVisibility(BattleReport $report, bool $wantsPublic, bool $changed): void
    {
        $from = $report->status;
        $to = match (true) {
            ! $wantsPublic => BattleReportStatusEnum::Private,
            $from === BattleReportStatusEnum::Private => BattleReportStatusEnum::Pending,
            $changed && $from !== BattleReportStatusEnum::Pending => BattleReportStatusEnum::Pending,
            default => $from,
        };
        if ($to === $from) {
            return;
        }

        $report->update([
            'status' => $to,
            'published_at' => $to === BattleReportStatusEnum::Approved ? $report->published_at : null,
            'moderated_by' => null,
            'moderated_at' => null,
            'moderation_note' => null,
            'appeal_note' => null,
            'approved_snapshot' => null,
        ]);

        if ($to !== BattleReportStatusEnum::Pending) {
            return;
        }
        if (TrustedAuthors::trusts($report->author)) {
            $report->update([
                'status' => BattleReportStatusEnum::Approved,
                'published_at' => now(),
                'moderated_at' => now(),
                'moderation_note' => null,
                'approved_snapshot' => ApprovedSnapshot::capture($report),
            ]);
            TrustedAuthors::record($report, 'approved', null, 'Trusted writer');

            return;
        }
        ModeratorAlerts::send($report, new BattleReportApprovalRequested($report), "{$report->author->name} asked to publish the battle report \"{$report->title}\".");
    }

    private function savedMessage(BattleReport $report): string
    {
        return match ($report->status) {
            BattleReportStatusEnum::Pending => 'Report saved. A moderator will read it before it is public.',
            BattleReportStatusEnum::Approved => $report->moderated_by === null && $report->moderated_at !== null ? 'Report saved. It is public.' : 'Report saved.',
            default => 'Report saved.',
        };
    }

    /** @return list<array<string, mixed>> */
    private function commentsFor(BattleReport $report, ?User $viewer): array
    {
        $canDelete = fn (BattleReportComment $c) => $viewer !== null && Gate::forUser($viewer)->allows('deleteComment', [$report, $c]);

        return $report->comments()->with('author:id,name,slug')->orderBy('id')->limit(200)->get()
            ->map(fn (BattleReportComment $c) => [
                'id' => $c->id,
                'body' => $c->body,
                'date' => $c->created_at?->toDateString(),
                'author' => ['name' => $c->author->name, 'slug' => $c->author->slug],
                'can_delete' => $canDelete($c),
            ])->all();
    }

    /** @return array{status: string, status_label: string, moderation_note: string|null, is_public: bool, show_game: bool, allow_comments: bool, can_appeal: bool, appeal_note: string|null} */
    private function statusOf(BattleReport $report): array
    {
        return [
            'status' => $report->status->value,
            'status_label' => $report->status->label(),
            'moderation_note' => $report->status === BattleReportStatusEnum::Rejected ? $report->moderation_note : null,
            'is_public' => $report->wantsPublic(),
            'show_game' => $report->show_game,
            'allow_comments' => $report->allow_comments,
            'can_appeal' => $report->status === BattleReportStatusEnum::Rejected && $report->appeal_note === null,
            'appeal_note' => $report->appeal_note,
        ];
    }

    /** @return array<string, mixed> */
    private function row(BattleReport $report): array
    {
        return [
            'uuid' => $report->uuid,
            'title' => $report->title,
            'excerpt' => $report->excerpt(180),
            'author' => $report->author ? ['name' => $report->author->name, 'slug' => $report->author->slug] : null,
            'date' => ($report->published_at ?? $report->updated_at)?->toDateString(),
            'game' => $this->cardOf($report),
        ];
    }

    /** Finished games the writer played in, tracked or logged. */
    private function myFinishedGames(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return Game::forUser($request->user()->id)->where('status', GameStatusEnum::Completed->value);
    }

    /**
     * What the "which game" list offers: the writer's most recent finished games, plus the one this report is already about.
     *
     * @return list<array<string, mixed>>
     */
    private function gameOptions(Request $request, ?Game $current = null, ?int $currentRound = null): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Game> $games */
        $games = $this->myFinishedGames($request)
            ->with(BattleReportGameCard::WITH)
            ->latest('completed_at')
            ->take(60)
            ->get();
        if ($current && ! $games->contains('id', $current->id)) {
            $current->load(BattleReportGameCard::WITH);
            $games->push($current);
        }
        $taken = BattleReport::where('user_id', $request->user()->id)->whereNotNull('game_id')->pluck('game_id')->all();

        $rounds = LinkableRounds::for($request->user());
        if ($currentRound && ! $rounds->contains('id', $currentRound) && ($own = LinkableRounds::find($request->user(), $currentRound))) {
            $rounds->push($own);
        }
        $takenRounds = BattleReport::where('user_id', $request->user()->id)->whereNotNull('tournament_game_id')->pluck('tournament_game_id')->all();
        $roundOptions = $rounds->map(function ($tg) use ($takenRounds, $currentRound) {
            $card = BattleReportGameCard::forRound($tg);

            return [
                'uuid' => 'round:'.$tg->id,
                'label' => $card['name'].': '.collect($card['players'])->pluck('name')->implode(' vs '),
                'played_on' => $card['played_on'],
                'origin' => 'tournament',
                'taken' => in_array($tg->id, $takenRounds, true) && $tg->id !== $currentRound,
            ];
        })->all();

        return $games->map(function (Game $g) use ($taken, $current) {
            $card = BattleReportGameCard::for($g);

            return [
                'uuid' => $g->uuid,
                'label' => $this->label($card),
                'played_on' => $card['played_on'],
                'origin' => $card['origin'],
                // Already has a report (not this one's own game): offered but marked, since a game gets one report per writer.
                'taken' => in_array($g->id, $taken, true) && $g->id !== $current?->id,
            ];
        })->concat($roundOptions)->values()->all();
    }

    /**
     * The writer's own crew lists the report can show (most recently changed first).
     *
     * @return list<array{id: int, label: string}>
     */
    private function crewOptions(Request $request): array
    {
        return CrewBuild::where('user_id', $request->user()->id)->where('is_archived', false)->with('master:id,display_name')
            ->latest('updated_at')->limit(60)->get()
            ->map(fn (CrewBuild $c) => ['id' => $c->id, 'label' => trim(($c->master->display_name ?? 'No master').' · '.$c->faction->label().' · '.$c->encounter_size.'ss')])
            ->all();
    }

    /** The scoreboard above the report: its game or tournament round, unless the writer hid it. */
    private function cardOf(BattleReport $report): ?array
    {
        if (! $report->show_game) {
            return null;
        }
        if ($report->game) {
            return BattleReportGameCard::for($report->game);
        }

        return $report->tournamentGame ? BattleReportGameCard::forRound($report->tournamentGame) : null;
    }

    private function suggestedTitle(Game $game): string
    {
        $game->load(BattleReportGameCard::WITH);

        return $this->label(BattleReportGameCard::for($game));
    }

    /** @param  array<string, mixed>  $card */
    private function label(array $card): string
    {
        return $card['name'] ?: collect($card['players'])->pluck('name')->implode(' vs ');
    }
}
