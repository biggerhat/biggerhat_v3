<?php

use App\Enums\BattleReportStatusEnum;
use App\Enums\GameStatusEnum;
use App\Models\BattleReport;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use App\Notifications\BattleReport\BattleReportAppealed;
use App\Notifications\BattleReport\BattleReportApprovalRequested;
use App\Notifications\BattleReport\BattleReportCommented;
use App\Notifications\BattleReport\BattleReportFlagged;
use App\Notifications\BattleReport\BattleReportModerated;
use App\Tournaments\RichText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function doc(string $text = 'It came down to the last turn.'): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
}

function moderator(): User
{
    Permission::firstOrCreate(['name' => 'approve_battle_reports']);
    $user = User::factory()->create();
    $user->givePermissionTo('approve_battle_reports');

    return $user;
}

function reportSuperAdmin(): User
{
    Role::firstOrCreate(['name' => 'super_admin']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

/** A finished tracker game with the user in slot 1. */
function finishedGameFor(User $user): Game
{
    $game = Game::factory()->completed()->create(['creator_id' => $user->id]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => User::factory(), 'slot' => 2]);

    return $game;
}

function loggedGameFor(User $user): Game
{
    test()->actingAs($user)->post(route('games.logged.store'), [
        'format' => 'standard', 'played_on' => now()->subDay()->toDateString(), 'encounter_size' => 50, 'result' => 'win',
        'me' => ['faction' => 'guild', 'master_name' => 'Lady Justice', 'total_points' => 7],
        'opponent' => ['faction' => 'arcanists', 'master_name' => 'Rasputina', 'total_points' => 4, 'opponent_name' => 'Sam'],
    ]);

    return Game::query()->logged()->latest('id')->firstOrFail();
}

it('lets a signed-in player write a report and keeps it private by default', function () {
    $me = User::factory()->create();

    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'Round one', 'content' => doc()])->assertRedirect();

    $report = BattleReport::firstOrFail();
    expect($report->user_id)->toBe($me->id)->and($report->status)->toBe(BattleReportStatusEnum::Private)->and($report->published_at)->toBeNull()
        ->and($report->game_id)->toBeNull()->and($report->uuid)->not->toBeEmpty();
});

it('ties a report to a tracker game the writer finished, and to a logged one', function () {
    $me = User::factory()->create();
    $tracked = finishedGameFor($me);
    $logged = loggedGameFor($me);

    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'A', 'content' => doc(), 'game_uuid' => $tracked->uuid])->assertRedirect();
    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'B', 'content' => doc(), 'game_uuid' => $logged->uuid])->assertRedirect();

    expect(BattleReport::pluck('game_id')->all())->toEqualCanonicalizing([$tracked->id, $logged->id]);
});

it('refuses a game the writer was not in, one that is not finished, and a second report for the same game', function () {
    $me = User::factory()->create();
    $theirs = finishedGameFor(User::factory()->create());
    $unfinished = finishedGameFor($me);
    $unfinished->update(['status' => GameStatusEnum::InProgress]);
    $mine = finishedGameFor($me);
    BattleReport::factory()->forGame($mine)->create(['user_id' => $me->id]);

    foreach ([$theirs, $unfinished, $mine] as $game) {
        $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'X', 'content' => doc(), 'game_uuid' => $game->uuid])
            ->assertSessionHasErrors('game_uuid');
    }
    expect(BattleReport::count())->toBe(1);
});

it('needs a title and a document of a sensible size', function () {
    $me = User::factory()->create();

    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => '', 'content' => doc()])->assertSessionHasErrors('title');
    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'Long', 'content' => doc(str_repeat('a', 210_000))])->assertSessionHasErrors('content');
    expect(BattleReport::count())->toBe(0);
});

it('rebuilds the document so nothing unsafe is stored', function () {
    $me = User::factory()->create();
    $bad = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'see', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]],
        ['type' => 'image', 'attrs' => ['src' => 'https://evil.example/x.png']],
        ['type' => 'entityEmbed', 'attrs' => ['entityType' => 'character', 'entityId' => 1]],
        ['type' => 'image', 'attrs' => ['src' => '/storage/battle-reports/1/ok.png']],
    ]];

    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'T', 'content' => $bad]);

    $json = json_encode(BattleReport::firstOrFail()->content, JSON_UNESCAPED_SLASHES);
    expect($json)->not->toContain('javascript:')->not->toContain('evil.example')->not->toContain('entityEmbed')->toContain('/storage/battle-reports/1/ok.png');
});

it('shows a public report to a guest and a private one only to its writer', function () {
    $author = User::factory()->create();
    $public = BattleReport::factory()->public()->create(['user_id' => $author->id, 'title' => 'Open to all']);
    $private = BattleReport::factory()->create(['user_id' => $author->id]);

    $this->get(route('battle-reports.show', $public))->assertOk()->assertInertia(fn ($p) => $p->component('BattleReports/Show')->where('can_edit', false)->where('report.title', 'Open to all'));
    $this->get(route('battle-reports.show', $private))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('battle-reports.show', $private))->assertNotFound();
    $this->actingAs($author)->get(route('battle-reports.show', $private))->assertOk()->assertInertia(fn ($p) => $p->where('can_edit', true));
});

it('puts the game on a report, with a link only when the reader can open the game', function () {
    $me = User::factory()->create();
    $tracked = finishedGameFor($me);
    $logged = loggedGameFor($me);
    $a = BattleReport::factory()->public()->forGame($tracked)->create(['user_id' => $me->id]);
    $b = BattleReport::factory()->public()->forGame($logged)->create(['user_id' => $me->id]);

    $this->get(route('battle-reports.show', $a))->assertInertia(fn ($p) => $p->where('game.origin', 'tracked')->where('game.url', route('games.summary', $tracked->uuid))->has('game.players', 2));
    // The logged game is private, so the report shows its scoreboard but does not point to a page nobody else can open.
    $this->get(route('battle-reports.show', $b))->assertInertia(fn ($p) => $p->where('game.origin', 'logged')->where('game.url', null)->where('game.players.0.points', 7)->where('game.winner_slot', 1));
});

it('lists public reports for everyone and the writer\'s own, drafts included', function () {
    $me = User::factory()->create();
    BattleReport::factory()->public()->create(['title' => 'Public one']);
    BattleReport::factory()->create(['title' => 'Someone else draft']);
    BattleReport::factory()->create(['user_id' => $me->id, 'title' => 'My draft']);

    $this->get(route('battle-reports.index'))->assertOk()->assertInertia(fn ($p) => $p->has('reports.data', 1)->where('mine', []));
    $this->actingAs($me)->get(route('battle-reports.index'))->assertInertia(fn ($p) => $p->has('reports.data', 1)->has('mine', 1)->where('mine.0.title', 'My draft'));
});

it('only lets the writer edit or delete a report', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $report = BattleReport::factory()->create(['user_id' => $author->id]);

    $this->actingAs($other)->get(route('battle-reports.edit', $report))->assertForbidden();
    $this->actingAs($other)->put(route('battle-reports.update', $report), ['title' => 'Hijack', 'content' => doc()])->assertForbidden();
    $this->actingAs($other)->delete(route('battle-reports.destroy', $report))->assertForbidden();

    $this->actingAs($author)->get(route('battle-reports.edit', $report))->assertOk()->assertInertia(fn ($p) => $p->component('BattleReports/Form')->where('report.uuid', $report->uuid));
    $this->actingAs($author)->delete(route('battle-reports.destroy', $report))->assertRedirect(route('battle-reports.index'));
    expect(BattleReport::count())->toBe(0);
});

it('opens the form for a game, or the existing report when that game already has one', function () {
    $me = User::factory()->create();
    $game = finishedGameFor($me);

    $this->actingAs($me)->get(route('battle-reports.create', ['game' => $game->uuid]))->assertOk()
        ->assertInertia(fn ($p) => $p->where('preselected', $game->uuid)->has('games', 1));

    $report = BattleReport::factory()->forGame($game)->create(['user_id' => $me->id]);
    $this->actingAs($me)->get(route('battle-reports.create', ['game' => $game->uuid]))->assertRedirect(route('battle-reports.edit', $report));
});

it('keeps the report when its game is deleted', function () {
    $me = User::factory()->create();
    $game = loggedGameFor($me);
    $report = BattleReport::factory()->forGame($game)->create(['user_id' => $me->id]);

    $this->actingAs($me)->delete(route('games.logged.destroy', $game->uuid));

    expect($report->fresh())->not->toBeNull();
});

it('stores an uploaded image where reports can use it, and refuses other files', function () {
    Storage::fake('public');
    $me = User::factory()->create();

    $res = $this->actingAs($me)->post(route('battle-reports.image'), ['image' => UploadedFile::fake()->image('table.png')])->assertOk();
    expect(RichText::isOwnImage($res->json('url')))->toBeTrue();
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $res->json('url')));

    $this->actingAs($me)->post(route('battle-reports.image'), ['image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('image');
});

it('keeps guests out of writing, editing and uploading', function () {
    $report = BattleReport::factory()->create();

    $this->get(route('battle-reports.create'))->assertRedirect(route('login'));
    $this->get(route('battle-reports.edit', $report))->assertRedirect(route('login'));
    $this->post(route('battle-reports.store'), ['title' => 'x'])->assertRedirect(route('login'));
    $this->post(route('battle-reports.image'), ['image' => UploadedFile::fake()->image('a.png')])->assertRedirect(route('login'));
});

it('offers the way to a report on the game pages of a player, and nowhere else', function () {
    $me = User::factory()->create();
    $logged = loggedGameFor($me);
    $tracked = finishedGameFor($me);

    $this->actingAs($me)->get(route('games.logged.show', $logged->uuid))->assertInertia(fn ($p) => $p->where('battle_report.uuid', null)->where('battle_report.game_uuid', $logged->uuid));
    $this->actingAs($me)->get(route('games.summary', $tracked->uuid))->assertInertia(fn ($p) => $p->where('battle_report.uuid', null));

    $report = BattleReport::factory()->forGame($tracked)->create(['user_id' => $me->id]);
    $this->actingAs($me)->get(route('games.summary', $tracked->uuid))->assertInertia(fn ($p) => $p->where('battle_report.uuid', $report->uuid));
    $this->actingAs(User::factory()->create())->get(route('games.summary', $tracked->uuid))->assertInertia(fn ($p) => $p->where('battle_report', null));
});

it('names a logged game for personal reference', function () {
    $me = User::factory()->create();
    $this->actingAs($me)->post(route('games.logged.store'), [
        'format' => 'standard', 'played_on' => now()->toDateString(), 'encounter_size' => 50, 'result' => 'win', 'name' => 'League round 3',
        'me' => ['faction' => 'guild', 'master_name' => 'Lady Justice', 'total_points' => 7],
        'opponent' => ['faction' => 'arcanists', 'master_name' => 'Rasputina', 'total_points' => 4, 'opponent_name' => 'Sam'],
    ])->assertRedirect();

    expect(Game::query()->logged()->firstOrFail()->name)->toBe('League round 3');
    $this->actingAs($me)->get(route('games.index'))->assertInertia(fn ($p) => $p->where('recent_games.0.name', 'League round 3'));
});

it('asks a moderator instead of publishing, and tells the moderators', function () {
    Notification::fake();
    $mod = moderator();
    $admin = reportSuperAdmin();
    $other = User::factory()->create();
    $me = User::factory()->create();

    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'T', 'content' => doc(), 'is_public' => true])->assertRedirect();

    $report = BattleReport::firstOrFail();
    expect($report->status)->toBe(BattleReportStatusEnum::Pending)->and($report->published_at)->toBeNull()->and($report->isPublic())->toBeFalse();
    Notification::assertSentTo($mod, BattleReportApprovalRequested::class);
    Notification::assertSentTo($admin, BattleReportApprovalRequested::class);
    Notification::assertNotSentTo($other, BattleReportApprovalRequested::class);
    $this->get(route('battle-reports.index'))->assertInertia(fn ($p) => $p->has('reports.data', 0));
    $this->actingAs($other)->get(route('battle-reports.show', $report))->assertNotFound();
});

it('still saves when the permission has not been created yet', function () {
    $me = User::factory()->create();

    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'T', 'content' => doc(), 'is_public' => true])->assertRedirect();

    expect(BattleReport::firstOrFail()->status)->toBe(BattleReportStatusEnum::Pending);
});

it('lets a moderator read a waiting report but not a private one', function () {
    $mod = moderator();
    $pending = BattleReport::factory()->pending()->create();
    $private = BattleReport::factory()->create();

    $this->actingAs($mod)->get(route('battle-reports.show', $pending))->assertOk()->assertInertia(fn ($p) => $p->where('can_moderate', true)->where('can_edit', false));
    $this->actingAs($mod)->get(route('battle-reports.show', $private))->assertNotFound();
});

it('approves: the report becomes public, dated, and its writer is told', function () {
    Notification::fake();
    $mod = moderator();
    $report = BattleReport::factory()->pending()->create(['title' => 'Approve me']);

    $this->actingAs($mod)->post(route('admin.battle_reports.approve', $report))->assertRedirect();

    $report->refresh();
    expect($report->status)->toBe(BattleReportStatusEnum::Approved)->and($report->published_at)->not->toBeNull()->and($report->moderated_by)->toBe($mod->id);
    Notification::assertSentTo($report->author, BattleReportModerated::class, fn ($n) => $n->outcome === 'approved');
    $this->get(route('battle-reports.show', $report))->assertOk();
    $this->get(route('battle-reports.index'))->assertInertia(fn ($p) => $p->has('reports.data', 1));
});

it('turns a report down with a reason the writer sees, and can take a public one down', function () {
    Notification::fake();
    $mod = moderator();
    $pending = BattleReport::factory()->pending()->create();
    $public = BattleReport::factory()->public()->create();

    $this->actingAs($mod)->post(route('admin.battle_reports.reject', $pending), ['note' => 'Please remove the names.'])->assertRedirect();
    $this->actingAs($mod)->post(route('admin.battle_reports.reject', $public))->assertRedirect();

    expect($pending->fresh()->status)->toBe(BattleReportStatusEnum::Rejected)->and($pending->fresh()->moderation_note)->toBe('Please remove the names.')
        ->and($public->fresh()->status)->toBe(BattleReportStatusEnum::Rejected)->and($public->fresh()->published_at)->toBeNull();
    Notification::assertSentTo($pending->author, BattleReportModerated::class, fn ($n) => $n->outcome === 'rejected');
    $this->actingAs($pending->author)->get(route('battle-reports.edit', $pending))->assertInertia(fn ($p) => $p->where('report.status', 'rejected')->where('report.moderation_note', 'Please remove the names.'));
    $this->get(route('battle-reports.show', $public))->assertNotFound();
});

it('keeps approval to people with the permission, and the writer out of approving their own', function () {
    $report = BattleReport::factory()->pending()->create();
    $plain = User::factory()->create();

    $this->post(route('admin.battle_reports.approve', BattleReport::factory()->pending()->create()))->assertRedirect(route('login'));
    $this->actingAs($plain)->get(route('admin.battle_reports.index'))->assertForbidden();
    $this->actingAs($plain)->post(route('admin.battle_reports.approve', $report))->assertForbidden();
    $this->actingAs($report->author)->post(route('admin.battle_reports.approve', $report))->assertForbidden();

    // A moderator may not approve their own report; a super admin may.
    $mod = moderator();
    $own = BattleReport::factory()->pending()->create(['user_id' => $mod->id]);
    $this->actingAs($mod)->post(route('admin.battle_reports.approve', $own))->assertForbidden();
    $admin = reportSuperAdmin();
    $adminOwn = BattleReport::factory()->pending()->create(['user_id' => $admin->id]);
    $this->actingAs($admin)->post(route('admin.battle_reports.approve', $adminOwn))->assertRedirect();
    expect($adminOwn->fresh()->status)->toBe(BattleReportStatusEnum::Approved);
});

it('lets a super admin into the queue and lists each status', function () {
    BattleReport::factory()->pending()->create();
    BattleReport::factory()->public()->create();
    BattleReport::factory()->create();

    $this->actingAs(reportSuperAdmin())->get(route('admin.battle_reports.index'))->assertOk()
        ->assertInertia(fn ($p) => $p->has('reports', 1)->where('counts.pending', 1)->where('counts.approved', 1)->where('counts.rejected', 0));
    $this->actingAs(moderator())->get(route('admin.battle_reports.index', ['status' => 'approved']))->assertInertia(fn ($p) => $p->has('reports', 1)->where('status', 'approved'));
});

it('does not approve a report twice or one that is not waiting', function () {
    Notification::fake();
    $mod = moderator();
    $private = BattleReport::factory()->create();

    $this->actingAs($mod)->post(route('admin.battle_reports.approve', $private));

    expect($private->fresh()->status)->toBe(BattleReportStatusEnum::Private);
    Notification::assertNothingSent();
});

it('hides an approved report again when the writer changes it in a way that matters', function () {
    Notification::fake();
    $mod = moderator();
    $me = User::factory()->create();
    $report = BattleReport::factory()->public()->create(['user_id' => $me->id, 'title' => 'Original', 'content' => doc('one')]);
    $save = fn (string $title, array $content) => $this->actingAs($me)->put(route('battle-reports.update', $report), ['title' => $title, 'content' => $content, 'is_public' => true, 'show_game' => true]);

    // Saved unchanged, or with a typo fixed: still public.
    $save('Original', doc('one'));
    $save('Original', doc('one!'));
    expect($report->fresh()->status)->toBe(BattleReportStatusEnum::Approved);
    Notification::assertNothingSent();

    // A new title: back to the moderators and hidden.
    $save('A different title', doc('one'));
    expect($report->fresh()->status)->toBe(BattleReportStatusEnum::Pending)->and($report->fresh()->published_at)->toBeNull()->and($report->fresh()->approved_snapshot)->toBeNull();
    Notification::assertSentTo($mod, BattleReportApprovalRequested::class);
    $this->actingAs(User::factory()->create())->get(route('battle-reports.show', $report))->assertNotFound();
});

it('measures edits against what was approved, so small edits cannot add up to a rewrite', function () {
    Notification::fake();
    moderator();
    $me = User::factory()->create();
    $report = BattleReport::factory()->public()->create(['user_id' => $me->id, 'title' => 'T', 'content' => doc('the quick brown fox jumps over the lazy dog')]);
    $save = fn (string $text) => $this->actingAs($me)->put(route('battle-reports.update', $report), ['title' => 'T', 'content' => doc($text), 'is_public' => true]);

    $save('the quick brown fox jumps over the lazy dog today and tomorrow');
    expect($report->fresh()->status)->toBe(BattleReportStatusEnum::Approved);
    // Another few words: past the allowance measured from the approved text, although each save alone is small.
    $save('the quick brown fox jumps over the lazy dog today and tomorrow plus a very long extra story follows');
    expect($report->fresh()->status)->toBe(BattleReportStatusEnum::Pending);
});

it('lets the scoreboard be hidden while public, and reads again when a hidden one is shown', function () {
    Notification::fake();
    moderator();
    $me = User::factory()->create();
    $game = finishedGameFor($me);
    $report = BattleReport::factory()->public()->forGame($game)->create(['user_id' => $me->id, 'title' => 'T', 'content' => doc('x')]);
    $save = fn (bool $show) => $this->actingAs($me)->put(route('battle-reports.update', $report), ['title' => 'T', 'content' => doc('x'), 'is_public' => true, 'show_game' => $show, 'game_uuid' => $game->uuid]);

    $save(false);
    expect($report->fresh()->status)->toBe(BattleReportStatusEnum::Approved)->and($report->fresh()->show_game)->toBeFalse();
    // Hidden at approval, so showing it now is something a moderator has not read.
    $report->update(['approved_snapshot' => App\Support\BattleReports\ApprovedSnapshot::capture($report->fresh())]);
    $save(true);
    expect($report->fresh()->status)->toBe(BattleReportStatusEnum::Pending);
});

it('reads again when an image or a link is added', function () {
    Notification::fake();
    moderator();
    $me = User::factory()->create();
    $report = BattleReport::factory()->public()->create(['user_id' => $me->id, 'title' => 'T', 'content' => doc('words here')]);
    $withLink = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'words here', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com/x']]]]]]]];

    $this->actingAs($me)->put(route('battle-reports.update', $report), ['title' => 'T', 'content' => $withLink, 'is_public' => true]);
    expect($report->fresh()->status)->toBe(BattleReportStatusEnum::Pending);
});

it('lets a trusted writer publish at once, and ends the trust at a turn-down', function () {
    Notification::fake();
    $mod = moderator();
    $me = User::factory()->create();
    BattleReport::factory()->approvedByModerator()->count(3)->create(['user_id' => $me->id]);

    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'Fresh', 'content' => doc(), 'is_public' => true])->assertRedirect();
    $fresh = BattleReport::where('title', 'Fresh')->firstOrFail();
    expect($fresh->status)->toBe(BattleReportStatusEnum::Approved)->and($fresh->published_at)->not->toBeNull()->and($fresh->moderated_by)->toBeNull();
    Notification::assertNothingSent();

    // Auto-approved reports do not count towards trust: a newcomer with only those is still asked.
    $newcomer = User::factory()->create();
    BattleReport::factory()->public()->count(5)->create(['user_id' => $newcomer->id]);
    $this->actingAs($newcomer)->post(route('battle-reports.store'), ['title' => 'N', 'content' => doc(), 'is_public' => true]);
    expect(BattleReport::where('title', 'N')->first()->status)->toBe(BattleReportStatusEnum::Pending);
    Notification::assertSentTo($mod, BattleReportApprovalRequested::class);

    // One turned down: back to the queue.
    BattleReport::factory()->rejected()->create(['user_id' => $me->id]);
    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'Later', 'content' => doc(), 'is_public' => true]);
    expect(BattleReport::where('title', 'Later')->first()->status)->toBe(BattleReportStatusEnum::Pending);
});

it('lets the writer ask once for a second look at a turned-down report', function () {
    Notification::fake();
    $mod = moderator();
    $me = User::factory()->create();
    $report = BattleReport::factory()->rejected('Too rude.')->create(['user_id' => $me->id]);

    $this->actingAs($me)->post(route('battle-reports.appeal', $report), ['note' => ''])->assertSessionHasErrors('note');
    $this->actingAs(User::factory()->create())->post(route('battle-reports.appeal', $report), ['note' => 'Please'])->assertForbidden();

    $this->actingAs($me)->post(route('battle-reports.appeal', $report), ['note' => 'I toned it down in the second part.'])->assertRedirect();
    $report->refresh();
    expect($report->status)->toBe(BattleReportStatusEnum::Pending)->and($report->appeal_note)->toBe('I toned it down in the second part.')->and($report->moderation_note)->toBe('Too rude.');
    Notification::assertSentTo($mod, BattleReportAppealed::class);

    // The moderator sees the request beside the earlier reason, and turning it down again is final until the writer changes it.
    $this->actingAs($mod)->get(route('admin.battle_reports.index'))->assertInertia(fn ($p) => $p->where('reports.0.appeal_note', 'I toned it down in the second part.')->where('reports.0.note', 'Too rude.'));
    $this->actingAs($mod)->post(route('admin.battle_reports.reject', $report), ['note' => 'Still no.']);
    $this->actingAs($me)->post(route('battle-reports.appeal', $report), ['note' => 'Again please'])->assertForbidden();

    $this->actingAs($me)->put(route('battle-reports.update', $report), ['title' => 'New', 'content' => doc('rewritten'), 'is_public' => true]);
    expect($report->fresh()->status)->toBe(BattleReportStatusEnum::Pending)->and($report->fresh()->appeal_note)->toBeNull();
});

it('lets a reader report a public report once, and tells the moderators', function () {
    Notification::fake();
    $mod = moderator();
    $report = BattleReport::factory()->public()->create();
    $reader = User::factory()->create();

    $this->post(route('battle-reports.flag', $report), ['reason' => 'Spam'])->assertRedirect(route('login'));
    $this->actingAs($report->author)->post(route('battle-reports.flag', $report), ['reason' => 'My own'])->assertForbidden();
    $this->actingAs($reader)->post(route('battle-reports.flag', $report), ['reason' => ''])->assertSessionHasErrors('reason');

    $this->actingAs($reader)->post(route('battle-reports.flag', $report), ['reason' => 'It is an advert.'])->assertRedirect();
    $this->actingAs($reader)->post(route('battle-reports.flag', $report), ['reason' => 'Again']);
    expect($report->flags()->count())->toBe(1);
    Notification::assertSentToTimes($mod, BattleReportFlagged::class, 1);
    $this->actingAs($reader)->get(route('battle-reports.show', $report))->assertInertia(fn ($p) => $p->where('flagged', true)->where('can_flag', false));

    // A report that is not public cannot be reported.
    $this->actingAs($reader)->post(route('battle-reports.flag', BattleReport::factory()->pending()->create()), ['reason' => 'x y z'])->assertForbidden();
});

it('lists reported reports for moderators, and lets them leave one public or take it down', function () {
    Notification::fake();
    $mod = moderator();
    $report = BattleReport::factory()->public()->create();
    $other = BattleReport::factory()->public()->create();
    $this->actingAs(User::factory()->create())->post(route('battle-reports.flag', $report), ['reason' => 'An advert']);
    $this->actingAs(User::factory()->create())->post(route('battle-reports.flag', $other), ['reason' => 'Spam']);

    $this->actingAs($mod)->get(route('admin.battle_reports.index', ['status' => 'reported']))
        ->assertInertia(fn ($p) => $p->has('reports', 2)->where('counts.reported', 2)->where('reports.0.flags.0.reason', fn ($r) => in_array($r, ['An advert', 'Spam'])));

    $this->actingAs($mod)->post(route('admin.battle_reports.dismiss_flags', $report))->assertRedirect();
    expect($report->fresh()->isPublic())->toBeTrue()->and($report->flags()->whereNull('resolved_at')->count())->toBe(0);

    $this->actingAs($mod)->post(route('admin.battle_reports.reject', $other), ['note' => 'Advert']);
    expect($other->fresh()->status)->toBe(BattleReportStatusEnum::Rejected)->and($other->flags()->whereNull('resolved_at')->count())->toBe(0);

    $this->actingAs(User::factory()->create())->post(route('admin.battle_reports.dismiss_flags', $report))->assertForbidden();
});

it('posts a line to the moderators webhook when a report is asked about, without mentions', function () {
    Http::fake();
    config(['services.battle_reports.alert_webhook' => 'https://discord.test/hook']);
    moderator();
    $me = User::factory()->create();

    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => '@everyone look', 'content' => doc(), 'is_public' => true]);

    Http::assertSent(fn ($r) => $r->url() === 'https://discord.test/hook' && str_contains($r['content'], '@everyone look') && $r['allowed_mentions'] === ['parse' => []]);
});

it('deletes uploaded images no report uses, and keeps used and recent ones', function () {
    Storage::fake('public');
    $disk = Storage::disk('public');
    $disk->put('battle-reports/1/used.png', 'x');
    $disk->put('battle-reports/1/unused.png', 'x');
    $disk->put('battle-reports/1/recent.png', 'x');
    $old = now()->subDays(3)->getTimestamp();
    touch($disk->path('battle-reports/1/used.png'), $old);
    touch($disk->path('battle-reports/1/unused.png'), $old);
    $image = ['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => '/storage/battle-reports/1/used.png']]]];
    BattleReport::factory()->create(['content' => $image]);

    $this->artisan('battle-reports:prune-images', ['--dry-run' => true])->assertSuccessful();
    expect($disk->exists('battle-reports/1/unused.png'))->toBeTrue();

    $this->artisan('battle-reports:prune-images')->assertSuccessful();
    expect($disk->exists('battle-reports/1/unused.png'))->toBeFalse()->and($disk->exists('battle-reports/1/used.png'))->toBeTrue()->and($disk->exists('battle-reports/1/recent.png'))->toBeTrue();
});

it('lets the writer take a report back to private at any time, and does not re-ask a turned-down report unchanged', function () {
    Notification::fake();
    $moderator = moderator();
    $me = User::factory()->create();
    $rejected = BattleReport::factory()->rejected()->create(['user_id' => $me->id, 'title' => 'T', 'content' => doc('x')]);

    $this->actingAs($me)->put(route('battle-reports.update', $rejected), ['title' => 'T', 'content' => doc('x'), 'is_public' => true]);
    expect($rejected->fresh()->status)->toBe(BattleReportStatusEnum::Rejected);
    Notification::assertNothingSent();

    $this->actingAs($me)->put(route('battle-reports.update', $rejected), ['title' => 'T', 'content' => doc('changed'), 'is_public' => true]);
    expect($rejected->fresh()->status)->toBe(BattleReportStatusEnum::Pending)->and($rejected->fresh()->moderation_note)->toBeNull();

    $this->actingAs($me)->put(route('battle-reports.update', $rejected), ['title' => 'T', 'content' => doc('changed'), 'is_public' => false]);
    expect($rejected->fresh()->status)->toBe(BattleReportStatusEnum::Private);
});

it('hides the scoreboard when the writer turns it off', function () {
    $me = User::factory()->create();
    $game = finishedGameFor($me);
    $report = BattleReport::factory()->public()->forGame($game)->create(['user_id' => $me->id, 'show_game' => false]);

    $this->get(route('battle-reports.show', $report))->assertInertia(fn ($p) => $p->where('game', null));
    $this->get(route('battle-reports.index'))->assertInertia(fn ($p) => $p->where('reports.data.0.game', null));

    $report->update(['show_game' => true]);
    $this->get(route('battle-reports.show', $report))->assertInertia(fn ($p) => $p->has('game.players', 2));
});

it('keeps a turn-down on record after the report is deleted, and forgives one reversed on appeal', function () {
    Notification::fake();
    $mod = moderator();
    $me = User::factory()->create();
    BattleReport::factory()->approvedByModerator()->count(3)->create(['user_id' => $me->id]);
    expect(App\Support\BattleReports\TrustedAuthors::trusts($me))->toBeTrue();

    $bad = BattleReport::factory()->public()->create(['user_id' => $me->id]);
    $this->actingAs($mod)->post(route('admin.battle_reports.reject', $bad), ['note' => 'No']);
    expect(App\Support\BattleReports\TrustedAuthors::trusts($me))->toBeFalse();

    // Deleting the report does not wipe the record.
    $this->actingAs($me)->delete(route('battle-reports.destroy', $bad));
    expect(App\Support\BattleReports\TrustedAuthors::trusts($me))->toBeFalse();

    // A turn-down reversed on appeal no longer counts.
    $again = BattleReport::factory()->public()->create(['user_id' => $me->id]);
    $this->actingAs($mod)->post(route('admin.battle_reports.reject', $again), ['note' => 'Hm']);
    $this->actingAs($me)->post(route('battle-reports.appeal', $again), ['note' => 'It is fine really']);
    $this->actingAs($mod)->post(route('admin.battle_reports.approve', $again));
    expect($again->fresh()->status)->toBe(BattleReportStatusEnum::Approved)
        ->and(App\Models\BattleReportDecision::where('battle_report_id', $again->id)->where('decision', 'rejected')->first()->overturned_at)->not->toBeNull();
    // The deleted report's turn-down still stands, so trust stays gone.
    expect(App\Support\BattleReports\TrustedAuthors::trusts($me))->toBeFalse();
});

it('shows the moderator what was decided on a report before', function () {
    $mod = moderator();
    $report = BattleReport::factory()->rejected('Too rude.')->create();

    $this->actingAs($mod)->get(route('admin.battle_reports.index', ['status' => 'rejected']))
        ->assertInertia(fn ($p) => $p->where('reports.0.history.0.decision', 'rejected')->where('reports.0.history.0.note', 'Too rude.'));
});

it('lists a writer\'s public reports on their stats page, and only the public ones', function () {
    $me = User::factory()->create();
    BattleReport::factory()->public()->create(['user_id' => $me->id, 'title' => 'Open one']);
    BattleReport::factory()->pending()->create(['user_id' => $me->id, 'title' => 'Waiting one']);
    BattleReport::factory()->create(['user_id' => $me->id, 'title' => 'Private one']);

    $this->get(route('stats.show', $me->slug))->assertInertia(fn ($p) => $p->has('battle_reports', 1)->where('battle_reports.0.title', 'Open one'));
});

it('puts public reports in the sitemap, and the first picture in the link preview', function () {
    $image = ['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => '/storage/battle-reports/1/a.png']]]];
    $public = BattleReport::factory()->public()->create(['content' => $image]);
    $private = BattleReport::factory()->create();

    $xml = $this->get('/sitemap.xml')->streamedContent();
    expect($xml)->toContain(route('battle-reports.show', $public))->and($xml)->not->toContain(route('battle-reports.show', $private));
    expect($public->firstImage())->toBe('/storage/battle-reports/1/a.png');
    $this->get(route('battle-reports.show', $public))->assertSee('og:image', false)->assertSee('/storage/battle-reports/1/a.png', false);
});

it('attaches one of the writer\'s own crew lists and shows its master and models, not its name', function () {
    $me = User::factory()->create();
    $master = App\Models\Character::factory()->create(['station' => 'master', 'name' => 'Lady Justice', 'title' => null, 'cost' => null]);
    $a = App\Models\Character::factory()->create(['name' => 'Guild Guard', 'title' => null, 'cost' => 4]);
    $crew = App\Models\CrewBuild::factory()->create(['user_id' => $me->id, 'name' => 'Secret nickname', 'master_id' => $master->id, 'crew_data' => [$a->id, $a->id]]);
    $others = App\Models\CrewBuild::factory()->create();

    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'T', 'content' => doc(), 'crew_id' => $others->id])->assertSessionHasErrors('crew_id');
    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'T', 'content' => doc(), 'crew_id' => $crew->id, 'is_public' => false])->assertRedirect();

    $report = BattleReport::firstOrFail();
    expect($report->crew_build_id)->toBe($crew->id);
    $this->actingAs($me)->get(route('battle-reports.show', $report))->assertInertia(fn ($p) => $p
        ->where('crew.master', 'Lady Justice')->where('crew.models.0.name', 'Guild Guard')->where('crew.models.0.count', 2)->where('crew.cost', 8)->where('crew.url', null)
        ->missing('crew.name'));
    $this->actingAs($me)->get(route('battle-reports.create'))->assertInertia(fn ($p) => $p->has('crews', 1)->where('crews.0.id', $crew->id));

    // Deleting the crew list leaves the report, without a crew.
    $crew->delete();
    expect($report->fresh()->crew)->toBeNull();
    $this->actingAs($me)->put(route('battle-reports.update', $report), ['title' => 'T', 'content' => doc(), 'crew_id' => $crew->id])->assertSessionHasErrors('crew_id');
});

it('writes a report about a tournament round that was not tracked, and nobody else\'s', function () {
    $me = User::factory()->create();
    $tournament = App\Models\Tournament::factory()->create(['status' => App\Enums\TournamentStatusEnum::Completed, 'name' => 'Spring Open']);
    $round = App\Models\TournamentRound::factory()->create(['tournament_id' => $tournament->id, 'round_number' => 2]);
    $mine = App\Models\TournamentPlayer::factory()->create(['tournament_id' => $tournament->id, 'user_id' => $me->id]);
    $theirs = App\Models\TournamentPlayer::factory()->create(['tournament_id' => $tournament->id]);
    $game = App\Models\TournamentGame::factory()->withScore(8, 5)->create(['tournament_round_id' => $round->id, 'player_one_id' => $mine->id, 'player_two_id' => $theirs->id]);
    $strangers = App\Models\TournamentGame::factory()->withScore(8, 5)->create(['tournament_round_id' => $round->id]);

    $this->actingAs($me)->get(route('battle-reports.create'))->assertInertia(fn ($p) => $p->has('games', 1)->where('games.0.uuid', 'round:'.$game->id)->where('games.0.origin', 'tournament'));
    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'T', 'content' => doc(), 'round_id' => $strangers->id])->assertSessionHasErrors('round_id');
    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'T', 'content' => doc(), 'round_id' => $game->id, 'game_uuid' => (string) Illuminate\Support\Str::uuid()])->assertSessionHasErrors('game_uuid');
    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'T', 'content' => doc(), 'round_id' => $game->id])->assertRedirect();
    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'Again', 'content' => doc(), 'round_id' => $game->id])->assertSessionHasErrors('round_id');

    $report = BattleReport::firstOrFail();
    $this->actingAs($me)->get(route('battle-reports.show', $report))->assertInertia(fn ($p) => $p
        ->where('game.origin', 'tournament')->where('game.name', 'Spring Open · Round 2')->where('game.winner_slot', 1)->where('game.players.0.points', 8));
    $this->actingAs($me)->get(route('battle-reports.edit', $report))->assertInertia(fn ($p) => $p->where('report.game_uuid', 'round:'.$game->id));

    // A tournament round that was tracked as a game is written about as the game.
    $tracked = App\Models\TournamentGame::factory()->withScore(3, 2)->create(['tournament_round_id' => $round->id, 'player_one_id' => $mine->id, 'player_two_id' => $theirs->id, 'game_id' => Game::factory()->completed()->create()->id]);
    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'X', 'content' => doc(), 'round_id' => $tracked->id])->assertSessionHasErrors('round_id');
});

it('lets a signed-in reader comment on a public report, tells the writer, and lets the writer or a moderator remove it', function () {
    Notification::fake();
    $mod = moderator();
    $report = BattleReport::factory()->public()->create();
    $reader = User::factory()->create();
    $other = User::factory()->create();

    $this->post(route('battle-reports.comment', $report), ['body' => 'Nice one'])->assertRedirect(route('login'));
    $this->actingAs($reader)->post(route('battle-reports.comment', $report), ['body' => ''])->assertSessionHasErrors('body');
    $this->actingAs($reader)->post(route('battle-reports.comment', $report), ['body' => '<b>Great</b> read'])->assertRedirect();

    $comment = $report->comments()->firstOrFail();
    expect($comment->body)->toBe('<b>Great</b> read');
    Notification::assertSentTo($report->author, BattleReportCommented::class);
    $this->actingAs($other)->get(route('battle-reports.show', $report))->assertInertia(fn ($p) => $p->has('comments', 1)->where('comments.0.can_delete', false)->where('can_comment', true));
    $this->actingAs($reader)->get(route('battle-reports.show', $report))->assertInertia(fn ($p) => $p->where('comments.0.can_delete', true));

    $this->actingAs($other)->delete(route('battle-reports.comment.destroy', [$report, $comment->id]))->assertForbidden();
    $this->actingAs($mod)->delete(route('battle-reports.comment.destroy', [$report, $comment->id]))->assertRedirect();
    expect($report->comments()->count())->toBe(0);

    $again = $report->comments()->create(['user_id' => $reader->id, 'body' => 'x y']);
    $this->actingAs($report->author)->delete(route('battle-reports.comment.destroy', [$report, $again->id]))->assertRedirect();
    expect($report->comments()->count())->toBe(0);
});

it('keeps comments to public reports whose writer allows them', function () {
    $reader = User::factory()->create();
    $off = BattleReport::factory()->public()->create(['allow_comments' => false]);
    $pending = BattleReport::factory()->pending()->create();

    $this->actingAs($reader)->post(route('battle-reports.comment', $off), ['body' => 'Hello'])->assertForbidden();
    $this->actingAs($reader)->post(route('battle-reports.comment', $pending), ['body' => 'Hello'])->assertForbidden();
    expect(App\Models\BattleReportComment::count())->toBe(0);
});

it('toggles a nice-report reaction once per reader, on public reports only', function () {
    $report = BattleReport::factory()->public()->create();
    $reader = User::factory()->create();

    $this->actingAs($reader)->post(route('battle-reports.react', $report))->assertRedirect();
    expect($report->reactions()->count())->toBe(1);
    $this->actingAs($reader)->get(route('battle-reports.show', $report))->assertInertia(fn ($p) => $p->where('reactions.count', 1)->where('reactions.mine', true));
    $this->actingAs($reader)->post(route('battle-reports.react', $report));
    expect($report->reactions()->count())->toBe(0);

    $this->actingAs($reader)->post(route('battle-reports.react', BattleReport::factory()->pending()->create()))->assertNotFound();
});

it('saves the comments switch with the report', function () {
    $me = User::factory()->create();
    $this->actingAs($me)->post(route('battle-reports.store'), ['title' => 'T', 'content' => doc(), 'allow_comments' => false]);
    expect(BattleReport::firstOrFail()->allow_comments)->toBeFalse();
});
