<?php

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\LeaderArchetypeEnum;
use App\Enums\LeaderTagEnum;
use App\Enums\PermissionEnum;
use App\Models\Action;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Campaign\LeaderArchetype;
use App\Models\Character;
use App\Models\CustomCharacter;
use App\Models\Keyword;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

function leaderUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

function crewFor(User $user): CampaignCrew
{
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    return CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
}

it('renders the Leader Builder for the crew owner', function () {
    LeaderArchetype::factory()->heavyHitter()->create();
    LeaderArchetype::factory()->create(['slug' => LeaderArchetypeEnum::Generalist->value, 'name' => 'Generalist']);

    $user = leaderUser();
    $crew = crewFor($user);

    $this->actingAs($user)
        ->get(route('campaigns.crews.leader.edit', [$crew->campaign_id, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Campaigns/LeaderBuilder')
            ->where('crew.share_code', $crew->share_code)
            ->has('archetypes', 2)
        );
});

it('blocks non-owner from the Leader Builder', function () {
    LeaderArchetype::factory()->create(['slug' => LeaderArchetypeEnum::Generalist->value]);

    $owner = leaderUser();
    $other = leaderUser();
    $crew = crewFor($owner);

    $this->actingAs($other)
        ->get(route('campaigns.crews.leader.edit', [$crew->campaign_id, $crew->share_code]))
        ->assertForbidden();
});

it('saves a new leader as a CustomCharacter with campaign columns set', function () {
    LeaderArchetype::factory()->create([
        'slug' => LeaderArchetypeEnum::Generalist->value, 'name' => 'Generalist',
        'df' => 5, 'wp' => 5, 'sp' => 6, 'health' => 14,
        'attack_actions_count' => 1, 'attack_action_cost_cap' => 7,
        'tactical_actions_count' => 1, 'tactical_action_cost_cap' => 7,
        'abilities_count' => 1, 'ability_cost_cap' => 7,
    ]);
    $user = leaderUser();
    $crew = crewFor($user);
    $k1 = Keyword::factory()->create();
    $k2 = Keyword::factory()->create();

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.update', [$crew->campaign_id, $crew->share_code]), [
            'name' => 'Mortimer Vance',
            'archetype' => LeaderArchetypeEnum::Generalist->value,
            'tag' => LeaderTagEnum::Bruiser->value,
            'faction' => FactionEnum::Resurrectionists->value,
            'keyword_1_id' => $k1->id,
            'keyword_2_id' => $k2->id,
            'size' => 2,
            'base' => 30,
            'characteristics' => ['Living'],
            'actions' => [],
            'abilities' => [],
        ])
        ->assertRedirect();

    $leader = CustomCharacter::firstWhere('campaign_crew_id', $crew->id);
    expect($leader)->not->toBeNull();
    expect($leader->is_campaign_leader)->toBeTrue();
    expect($leader->archetype)->toBe(LeaderArchetypeEnum::Generalist->value);
    expect($leader->tag)->toBe(LeaderTagEnum::Bruiser->value);
    expect($leader->campaign_df)->toBe(5);
    expect($leader->campaign_health)->toBe(14);
    expect($leader->current)->toBeTrue();
    expect($leader->keywords)->toHaveCount(2);
});

it('demotes the previous current leader when saving a new one', function () {
    LeaderArchetype::factory()->create(['slug' => LeaderArchetypeEnum::Generalist->value]);
    $user = leaderUser();
    $crew = crewFor($user);
    $k1 = Keyword::factory()->create();
    $k2 = Keyword::factory()->create();

    // First save
    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.update', [$crew->campaign_id, $crew->share_code]), [
            'name' => 'Leader One',
            'archetype' => LeaderArchetypeEnum::Generalist->value,
            'tag' => LeaderTagEnum::Bruiser->value,
            'faction' => FactionEnum::Resurrectionists->value,
            'keyword_1_id' => $k1->id, 'keyword_2_id' => $k2->id,
            'size' => 2, 'base' => 30,
        ]);

    // Second save — replaces
    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.update', [$crew->campaign_id, $crew->share_code]), [
            'name' => 'Leader Two',
            'archetype' => LeaderArchetypeEnum::Generalist->value,
            'tag' => LeaderTagEnum::Strategist->value,
            'faction' => FactionEnum::Resurrectionists->value,
            'keyword_1_id' => $k1->id, 'keyword_2_id' => $k2->id,
            'size' => 2, 'base' => 30,
        ]);

    $rows = CustomCharacter::where('campaign_crew_id', $crew->id)->orderBy('id')->get();
    expect($rows)->toHaveCount(2);
    expect($rows[0]->current)->toBeFalse();
    expect($rows[0]->replaced_at)->not->toBeNull();
    expect($rows[1]->current)->toBeTrue();
    expect($rows[1]->name)->toBe('Leader Two');
});

it('rejects an attack action over the archetype cost cap', function () {
    LeaderArchetype::factory()->create([
        'slug' => LeaderArchetypeEnum::Schemer->value, 'name' => 'Schemer',
        'df' => 6, 'wp' => 5, 'sp' => 7, 'health' => 13,
        'attack_actions_count' => 1, 'attack_action_cost_cap' => 4,
        'tactical_actions_count' => 2, 'tactical_action_cost_cap' => 8,
        'abilities_count' => 1, 'ability_cost_cap' => 8,
    ]);
    $user = leaderUser();
    $crew = crewFor($user);
    $k1 = Keyword::factory()->create();
    $k2 = Keyword::factory()->create();

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.update', [$crew->campaign_id, $crew->share_code]), [
            'name' => 'Hopeful',
            'archetype' => LeaderArchetypeEnum::Schemer->value,
            'tag' => LeaderTagEnum::Strategist->value,
            'faction' => FactionEnum::Arcanists->value,
            'keyword_1_id' => $k1->id, 'keyword_2_id' => $k2->id,
            'size' => 2, 'base' => 30,
            'actions' => [[
                'name' => 'Big Punch', 'type' => 'attack', 'category' => 'attack',
                'stone_cost' => 9, 'is_signature' => false, 'triggers' => [],
            ]],
        ])
        ->assertSessionHasErrors();

    expect(CustomCharacter::where('campaign_crew_id', $crew->id)->exists())->toBeFalse();
});

it('rejects too many abilities for the archetype', function () {
    LeaderArchetype::factory()->create([
        'slug' => LeaderArchetypeEnum::HeavyHitter->value, 'name' => 'Heavy Hitter',
        'attack_actions_count' => 1, 'attack_action_cost_cap' => 10,
        'tactical_actions_count' => 1, 'tactical_action_cost_cap' => 5,
        'abilities_count' => 0, 'ability_cost_cap' => 0,
    ]);
    $user = leaderUser();
    $crew = crewFor($user);
    $k1 = Keyword::factory()->create();
    $k2 = Keyword::factory()->create();

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.update', [$crew->campaign_id, $crew->share_code]), [
            'name' => 'Bruiser',
            'archetype' => LeaderArchetypeEnum::HeavyHitter->value,
            'tag' => LeaderTagEnum::Bruiser->value,
            'faction' => FactionEnum::Guild->value,
            'keyword_1_id' => $k1->id, 'keyword_2_id' => $k2->id,
            'size' => 2, 'base' => 40,
            'abilities' => [
                ['name' => 'Tough'],
            ],
        ])
        ->assertSessionHasErrors();
});

it('search/actions returns matches filtered by crew keyword and cost cap', function () {
    $user = leaderUser();
    $crew = crewFor($user);

    $keyword = Keyword::factory()->create(['name' => 'Family']);
    $crew->update(['keyword_1_id' => $keyword->id]);

    // Character (non-master) with the keyword owning the action.
    $char = Character::factory()->create(['station' => CharacterStationEnum::Minion]);
    $char->keywords()->attach($keyword);
    $action = Action::factory()->create(['name' => 'Family Slap', 'stone_cost' => 3]);
    $action->characters()->attach($char);

    // Out-of-keyword action — shouldn't appear.
    $other = Character::factory()->create(['station' => CharacterStationEnum::Minion]);
    $otherAction = Action::factory()->create(['name' => 'Family Bite', 'stone_cost' => 3]);
    $otherAction->characters()->attach($other);

    // Cap-busting action — has the keyword but cost > cap, should not match.
    $expensive = Action::factory()->create(['name' => 'Family Cannon', 'stone_cost' => 10]);
    $expensive->characters()->attach($char);

    $this->actingAs($user)
        ->getJson(route('campaigns.crews.leader.search.actions', [$crew->campaign_id, $crew->share_code]).'?q=Family&max_cost=5')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Family Slap');
});

it('search/actions excludes masters', function () {
    $user = leaderUser();
    $crew = crewFor($user);
    $keyword = Keyword::factory()->create();
    $crew->update(['keyword_1_id' => $keyword->id]);

    $master = Character::factory()->create(['station' => CharacterStationEnum::Master]);
    $master->keywords()->attach($keyword);
    $action = Action::factory()->create(['name' => 'Master Slash', 'stone_cost' => 2]);
    $action->characters()->attach($master);

    $this->actingAs($user)
        ->getJson(route('campaigns.crews.leader.search.actions', [$crew->campaign_id, $crew->share_code]).'?q=Master&max_cost=10')
        ->assertOk()
        ->assertJsonCount(0);
});
