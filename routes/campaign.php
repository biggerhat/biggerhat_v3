<?php

use App\Http\Controllers\Campaign\ArsenalSheetController;
use App\Http\Controllers\Campaign\CampaignAftermathController;
use App\Http\Controllers\Campaign\CampaignController;
use App\Http\Controllers\Campaign\CampaignGameController;
use App\Http\Controllers\Campaign\CampaignInvitationController;
use App\Http\Controllers\Campaign\CampaignTeaserController;
use App\Http\Controllers\Campaign\CrewLifecycleController;
use App\Http\Controllers\Campaign\LeaderAdvancementController;
use App\Http\Controllers\Campaign\LeaderBuilderController;
use App\Http\Controllers\Campaign\LeaderSearchController;
use App\Http\Controllers\Campaign\StartingArsenalController;
use App\Http\Controllers\Campaign\WeeklyCycleController;
use App\Http\Controllers\Campaign\WeeklyHireController;
use Illuminate\Support\Facades\Route;

/**
 * M4E Campaign Mode (Index of the Untold) public-facing routes. Gated by
 * the `campaign.access` middleware so visitors without the feature flag
 * (and without the use_campaign_mode permission / super_admin role) see a
 * 404, hiding the feature's existence while pre-release.
 *
 * Admin catalog routes live in routes/admin.php under /admin/campaign/*
 * and use the same gate plus per-action permission checks.
 */
// Teaser / coming-soon page — OUTSIDE the campaign.access gate so anyone
// can read the marketing copy and request access. Redirects to /campaigns
// for users who already have access (super_admin, use_campaign_mode, or the
// global feature flag enabled).
Route::get('/campaigns/preview', [CampaignTeaserController::class, 'show'])
    ->name('campaigns.preview');

Route::middleware(['campaign.access'])->group(function () {
    // Invitation accept screen is BEFORE the auth gate so unauthenticated
    // users can land here and bounce to login. The controller redirects
    // them through guest login then back.
    Route::get('/campaigns/invitations/{invitation}', [CampaignInvitationController::class, 'show'])
        ->name('campaigns.invitations.show');

    // Public Arsenal Sheet share link — anyone with the share_code can view.
    // No auth required, but campaign.access still gates so it stays hidden
    // while pre-release.
    Route::get('/a/{share_code}', [ArsenalSheetController::class, 'share'])
        ->name('campaigns.crews.arsenal.share');

    // Public, reusable campaign invite link — same "outside auth, bounce
    // unauthenticated visitors to login" treatment as the invitation accept
    // screen above. Bound by uuid, not the campaign's normal integer id.
    Route::get('/campaigns/join/{campaign:uuid}', [CampaignController::class, 'joinPublic'])
        ->name('campaigns.join');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
        Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');

        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
        Route::get('/campaigns/{campaign}/settings', [CampaignController::class, 'settings'])->name('campaigns.settings');
        Route::post('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
        Route::post('/campaigns/{campaign}/start', [CampaignController::class, 'start'])->name('campaigns.start');
        Route::post('/campaigns/{campaign}/end', [CampaignController::class, 'end'])->name('campaigns.end');
        Route::post('/campaigns/{campaign}/delete', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
        Route::post('/campaigns/{campaign}/join-link/regenerate', [CampaignController::class, 'regenerateJoinLink'])
            ->name('campaigns.join-link.regenerate');
        // A multiplayer campaign only auto-stubs a crew for solo campaigns —
        // the organizer (or any other existing member, e.g. after a data
        // inconsistency) has no other path to play their own campaign.
        Route::post('/campaigns/{campaign}/join-as-player', [CampaignController::class, 'joinAsPlayer'])
            ->name('campaigns.join-as-player');

        // Invitations — only the organizer can create/revoke; accept is the
        // invitee's action and binds via token.
        Route::post('/campaigns/{campaign}/invitations', [CampaignInvitationController::class, 'store'])
            ->name('campaigns.invitations.store');
        Route::post('/campaigns/invitations/{invitation}/accept', [CampaignInvitationController::class, 'accept'])
            ->name('campaigns.invitations.accept');
        Route::post('/campaigns/{campaign}/invitations/{invitation}/revoke', [CampaignInvitationController::class, 'revoke'])
            ->name('campaigns.invitations.revoke');

        // Leader Builder — per-crew wizard. Route-model binding uses share_code
        // for crew (its getRouteKeyName returns 'share_code').
        Route::get('/campaigns/{campaign}/crews/{crew}/leader', [LeaderBuilderController::class, 'edit'])
            ->name('campaigns.crews.leader.edit');
        Route::post('/campaigns/{campaign}/crews/{crew}/leader', [LeaderBuilderController::class, 'update'])
            ->name('campaigns.crews.leader.update');

        // Filtered action/ability search for the Leader Builder pickers.
        Route::get('/campaigns/{campaign}/crews/{crew}/leader/search/actions', [LeaderSearchController::class, 'actions'])
            ->name('campaigns.crews.leader.search.actions');
        Route::get('/campaigns/{campaign}/crews/{crew}/leader/search/abilities', [LeaderSearchController::class, 'abilities'])
            ->name('campaigns.crews.leader.search.abilities');

        // Leadership Experience — log / remove an advancement straight from the
        // Arsenal Sheet's XP track (also taken during the Aftermath).
        Route::post('/campaigns/{campaign}/crews/{crew}/leader/advancements', [LeaderAdvancementController::class, 'store'])
            ->name('campaigns.crews.leader.advancements.store');
        Route::delete('/campaigns/{campaign}/crews/{crew}/leader/advancements/{advancement}', [LeaderAdvancementController::class, 'destroy'])
            ->name('campaigns.crews.leader.advancements.destroy');

        // Starting Arsenal — 25-ss spend wizard + crew card effect picker.
        Route::get('/campaigns/{campaign}/crews/{crew}/starting-arsenal', [StartingArsenalController::class, 'edit'])
            ->name('campaigns.crews.starting-arsenal.edit');
        Route::post('/campaigns/{campaign}/crews/{crew}/starting-arsenal', [StartingArsenalController::class, 'update'])
            ->name('campaigns.crews.starting-arsenal.update');

        // Arsenal Sheet (authenticated path — public share is above).
        Route::get('/campaigns/{campaign}/crews/{crew}/arsenal', [ArsenalSheetController::class, 'show'])
            ->name('campaigns.crews.arsenal.show');
        // Ad-hoc unit/equipment adds — mid-game events outside Starting Arsenal/Weekly Hire/Aftermath.
        Route::post('/campaigns/{campaign}/crews/{crew}/arsenal/models', [ArsenalSheetController::class, 'addManualArsenalModel'])
            ->name('campaigns.crews.arsenal.models.store');
        // Rename (nickname) an already-hired Arsenal Model.
        Route::put('/campaigns/{campaign}/crews/{crew}/arsenal/models/{model}', [ArsenalSheetController::class, 'updateArsenalModel'])
            ->name('campaigns.crews.arsenal.models.update');
        Route::post('/campaigns/{campaign}/crews/{crew}/arsenal/equipment', [ArsenalSheetController::class, 'addManualEquipment'])
            ->name('campaigns.crews.arsenal.equipment.store');

        // Weekly cycle. Organizer advances the week (+ rolls Weekly Event if enabled).
        Route::post('/campaigns/{campaign}/weeks/advance', [WeeklyCycleController::class, 'advance'])
            ->name('campaigns.weeks.advance');

        // Per-player weekly New Hires page (pg 18). Mandatory ≥1 hire.
        Route::get('/campaigns/{campaign}/crews/{crew}/weekly-hire', [WeeklyHireController::class, 'edit'])
            ->name('campaigns.crews.weekly-hire.edit');
        Route::post('/campaigns/{campaign}/crews/{crew}/weekly-hire', [WeeklyHireController::class, 'update'])
            ->name('campaigns.crews.weekly-hire.update');

        // Start a campaign game. Creates a Game (format=Campaign) wrapped by a
        // campaign_games row and hands off to the existing /games/{uuid} tracker.
        Route::get('/campaigns/{campaign}/games/new', [CampaignGameController::class, 'create'])
            ->name('campaigns.games.create');
        Route::post('/campaigns/{campaign}/games', [CampaignGameController::class, 'store'])
            ->name('campaigns.games.store');

        // Solo campaign: log a game result manually (no live tracker, no
        // opponent crew). Solo campaigns route here instead of /games/new.
        Route::get('/campaigns/{campaign}/games/log', [CampaignGameController::class, 'createSolo'])
            ->name('campaigns.games.log');
        Route::post('/campaigns/{campaign}/games/log', [CampaignGameController::class, 'storeSolo'])
            ->name('campaigns.games.log.store');

        // Solo campaign: start a genuine live Game Tracker session (unlike
        // the retroactive log above) — no confirmation page needed since the
        // crew is already unique per campaign+user, so this is POST-only,
        // triggered directly from a button click.
        Route::post('/campaigns/{campaign}/games/play', [CampaignGameController::class, 'playLive'])
            ->name('campaigns.games.play');

        // Crew lifecycle (meta-level mutations outside game / aftermath flow).
        // Phase 10 — annihilate leader (miraculous recovery on first call),
        // start anew (rebuild crew), scrap model (Cut 'Em Up For Parts).
        Route::post('/campaigns/{campaign}/crews/{crew}/leader/annihilate', [CrewLifecycleController::class, 'annihilateLeader'])
            ->name('campaigns.crews.leader.annihilate');
        Route::post('/campaigns/{campaign}/crews/{crew}/starting-anew', [CrewLifecycleController::class, 'startingAnew'])
            ->name('campaigns.crews.starting-anew');
        Route::post('/campaigns/{campaign}/crews/{crew}/arsenal/{arsenalModel}/scrap', [CrewLifecycleController::class, 'scrapModel'])
            ->name('campaigns.crews.arsenal.scrap');

        // Aftermath wizard — six-phase state machine that mutates the Arsenal
        // Sheet (Phases 1, 2, 6 are wired this iteration; 3/4/5 are advance-only
        // stubs until catalog data lands).
        Route::post('/campaigns/games/{campaignGame}/aftermath/start', [CampaignAftermathController::class, 'start'])
            ->name('campaigns.aftermaths.start');
        Route::get('/campaigns/aftermaths/{aftermath}', [CampaignAftermathController::class, 'show'])
            ->name('campaigns.aftermaths.show');
        Route::post('/campaigns/aftermaths/{aftermath}/draw-hand', [CampaignAftermathController::class, 'drawHand'])
            ->name('campaigns.aftermaths.draw-hand');
        Route::post('/campaigns/aftermaths/{aftermath}/payday', [CampaignAftermathController::class, 'payday'])
            ->name('campaigns.aftermaths.payday');
        Route::post('/campaigns/aftermaths/{aftermath}/barter', [CampaignAftermathController::class, 'barter'])
            ->name('campaigns.aftermaths.barter');
        Route::post('/campaigns/aftermaths/{aftermath}/advance', [CampaignAftermathController::class, 'advance'])
            ->name('campaigns.aftermaths.advance');
        Route::post('/campaigns/aftermaths/{aftermath}/back', [CampaignAftermathController::class, 'goBack'])
            ->name('campaigns.aftermaths.back');
        Route::post('/campaigns/aftermaths/{aftermath}/advance-leader', [CampaignAftermathController::class, 'advanceLeader'])
            ->name('campaigns.aftermaths.advance-leader');
        Route::post('/campaigns/aftermaths/{aftermath}/doctor', [CampaignAftermathController::class, 'doctor'])
            ->name('campaigns.aftermaths.doctor');
        Route::post('/campaigns/aftermaths/{aftermath}/determine-injuries', [CampaignAftermathController::class, 'determineInjuries'])
            ->name('campaigns.aftermaths.determine-injuries');
        Route::post('/campaigns/aftermaths/{aftermath}/finalize', [CampaignAftermathController::class, 'finalize'])
            ->name('campaigns.aftermaths.finalize');
    });
});
