<?php

use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignArsenalModelInjury;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Upgrade;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Bus::fake();
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
});

function recapFixture(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $campaign = Campaign::factory()->active()->create(['organizer_user_id' => $user->id, 'current_week' => 3]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $mine = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'name' => 'Mine', 'total_wins' => 2]);
    $theirs = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'name' => 'Theirs', 'total_wins' => 1]);

    return [$user, $campaign, $mine, $theirs];
}

it('shows standings ordered by wins and games grouped by week, hiding games still in setup', function () {
    [$user, $campaign, $mine, $theirs] = recapFixture();
    CampaignGame::factory()->create(['campaign_id' => $campaign->id, 'week_number' => 1, 'crew_a_id' => $mine->id, 'crew_b_id' => $theirs->id, 'status' => 'aftermath', 'winner_crew_id' => $mine->id, 'vp_a' => 7, 'vp_b' => 3]);
    CampaignGame::factory()->create(['campaign_id' => $campaign->id, 'week_number' => 2, 'crew_a_id' => $theirs->id, 'crew_b_id' => $mine->id, 'status' => 'aftermath', 'winner_crew_id' => $theirs->id]);
    CampaignGame::factory()->create(['campaign_id' => $campaign->id, 'week_number' => 3, 'crew_a_id' => $mine->id, 'crew_b_id' => $theirs->id, 'status' => 'setup']);

    $this->actingAs($user)->get(route('campaigns.recap', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Campaigns/Recap')
            ->where('standings.0.name', 'Mine')
            ->where('standings.0.games', 2)
            ->where('standings.1.name', 'Theirs')
            ->has('weeks', 2)
            ->where('weeks.0.week', 1)
            ->where('weeks.0.games.0.winner', 'Mine')
            ->where('weeks.0.games.0.vp_a', 7));
});

it('refuses non-members and guests', function () {
    [, $campaign] = recapFixture();
    $outsider = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($outsider)->get(route('campaigns.recap', $campaign))->assertForbidden();
    auth()->logout();
    $this->get(route('campaigns.recap', $campaign))->assertRedirect();
});

it('includes each crew\'s story entry and the injuries taken in a game', function () {
    [$user, $campaign, $mine, $theirs] = recapFixture();
    $game = CampaignGame::factory()->create(['campaign_id' => $campaign->id, 'week_number' => 1, 'crew_a_id' => $mine->id, 'crew_b_id' => $theirs->id, 'status' => 'aftermath', 'winner_crew_id' => $mine->id]);
    $aftermath = CampaignAftermath::factory()->create(['campaign_game_id' => $game->id, 'campaign_crew_id' => $mine->id, 'story_entry' => 'We held the bridge.']);
    CampaignAftermath::factory()->create(['campaign_game_id' => $game->id, 'campaign_crew_id' => $theirs->id, 'story_entry' => null]);
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $mine->id]);
    CampaignArsenalModelInjury::create([
        'campaign_arsenal_model_id' => $model->id,
        'injury_upgrade_id' => Upgrade::factory()->campaignInjury()->create(['name' => 'Broken Arm'])->id,
        'acquired_aftermath_id' => $aftermath->id,
    ]);

    $this->actingAs($user)->get(route('campaigns.recap', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('weeks.0.games.0.stories', 1)
            ->where('weeks.0.games.0.stories.0.text', 'We held the bridge.')
            ->where('weeks.0.games.0.stories.0.crew', 'Mine')
            ->has('weeks.0.games.0.injuries', 1)
            ->where('weeks.0.games.0.injuries.0.injury', 'Broken Arm')
            ->where('weeks.0.games.0.injuries.0.crew', 'Mine'));
});
