<?php

use App\Enums\Campaign\LeaderArchetypeEnum;
use App\Enums\Campaign\LeaderTagEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use App\Models\Action;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignPlayer;
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

/**
 * A keyword that has at least one model in the given faction — required by the
 * Leader Build rule "at least one chosen keyword must have a model belonging to
 * the declared faction" (pg 15).
 */
function keywordWithModelInFaction(FactionEnum $faction): Keyword
{
    $kw = Keyword::factory()->create();
    Character::factory()->create(['faction' => $faction])->keywords()->attach($kw);

    return $kw;
}

it('saves a leader with a selected ability without error', function () {
    $user = leaderUser();
    $crew = crewFor($user);
    $faction = FactionEnum::Guild;
    $kw1 = keywordWithModelInFaction($faction);
    $kw2 = Keyword::factory()->create();

    $ability = \App\Models\Ability::factory()->create();
    $source = Character::factory()->create(['faction' => $faction, 'cost' => 1, 'station' => CharacterStationEnum::Minion]);
    $source->keywords()->attach($kw1);
    $source->abilities()->attach($ability);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.update', [$crew->campaign_id, $crew->share_code]), [
            'name' => 'Ability Leader',
            'archetype' => LeaderArchetypeEnum::Generalist->value,
            'tag' => LeaderTagEnum::Bruiser->value,
            'faction' => $faction->value,
            'keyword_1_id' => $kw1->id, 'keyword_2_id' => $kw2->id,
            'size' => 2, 'base' => 30,
            'actions' => [],
            'abilities' => [[
                'name' => $ability->name,
                'source_id' => $ability->id,
                'source_character_id' => $source->id,
            ]],
        ])
        ->assertRedirect();

    expect(CustomCharacter::where('campaign_crew_id', $crew->id)->where('current', true)->first()->abilities)
        ->toHaveCount(1);
});

it('renders the Leader Builder for the crew owner with all 5 archetypes from the enum', function () {
    $user = leaderUser();
    $crew = crewFor($user);

    $this->actingAs($user)
        ->get(route('campaigns.crews.leader.edit', [$crew->campaign_id, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Campaigns/LeaderBuilder')
            ->where('crew.share_code', $crew->share_code)
            ->has('archetypes', count(LeaderArchetypeEnum::cases()))
            ->where('archetypes.0.slug', LeaderArchetypeEnum::LuckyUpstart->value)
        );
});

it('offers the official characteristic catalog as picker options', function () {
    $user = leaderUser();
    $crew = crewFor($user);
    \App\Models\Characteristic::factory()->create(['name' => 'Living']);

    $this->actingAs($user)
        ->get(route('campaigns.crews.leader.edit', [$crew->campaign_id, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('characteristic_options', fn ($opts) => collect($opts)->contains('Living')));
});

it('blocks non-owner from the Leader Builder', function () {
    $owner = leaderUser();
    $other = leaderUser();
    $crew = crewFor($owner);

    $this->actingAs($other)
        ->get(route('campaigns.crews.leader.edit', [$crew->campaign_id, $crew->share_code]))
        ->assertForbidden();
});

it('saves a new leader as a CustomCharacter with campaign columns set from the enum', function () {
    $user = leaderUser();
    $crew = crewFor($user);
    $k1 = keywordWithModelInFaction(FactionEnum::Resurrectionists);
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
    $generalist = LeaderArchetypeEnum::Generalist;
    expect($leader)->not->toBeNull();
    expect($leader->is_campaign_leader)->toBeTrue();
    expect($leader->archetype)->toBe($generalist->value);
    expect($leader->tag)->toBe(LeaderTagEnum::Bruiser->value);
    expect($leader->campaign_df)->toBe($generalist->df());
    expect($leader->campaign_health)->toBe($generalist->health());
    expect($leader->current)->toBeTrue();
    expect($leader->keywords)->toHaveCount(2);
});

it('demotes the previous current leader when saving a new one', function () {
    $user = leaderUser();
    $crew = crewFor($user);
    $k1 = keywordWithModelInFaction(FactionEnum::Resurrectionists);
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

it('rejects an attack action whose source model exceeds the archetype cost cap', function () {
    // pg 17: the cap is on the SOURCE ally's cost (≤), not the action's own cost.
    $user = leaderUser();
    $crew = crewFor($user);
    $faction = FactionEnum::Guild;
    $k1 = keywordWithModelInFaction($faction);
    $k2 = Keyword::factory()->create();
    $cap = LeaderArchetypeEnum::Generalist->attackActionCostCap(); // 7

    $action = Action::factory()->create(['name' => 'Big Punch', 'type' => 'attack']);
    $source = Character::factory()->create(['faction' => $faction, 'cost' => $cap + 3, 'station' => CharacterStationEnum::Minion]);
    $source->keywords()->attach($k1);
    $source->actions()->attach($action);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.update', [$crew->campaign_id, $crew->share_code]), [
            'name' => 'Hopeful',
            'archetype' => LeaderArchetypeEnum::Generalist->value,
            'tag' => LeaderTagEnum::Bruiser->value,
            'faction' => $faction->value,
            'keyword_1_id' => $k1->id, 'keyword_2_id' => $k2->id,
            'size' => 2, 'base' => 30,
            'actions' => [[
                'name' => $action->name, 'type' => 'attack', 'category' => 'attack',
                'source_id' => $action->id, 'source_character_id' => $source->id,
                'is_signature' => false, 'triggers' => [],
            ]],
        ])
        ->assertSessionHasErrors('actions.0.source_character_id');

    expect(CustomCharacter::where('campaign_crew_id', $crew->id)->exists())->toBeFalse();
});

it('rejects too many abilities for the archetype', function () {
    // HeavyHitter caps abilities at 1; submitting 2 must fail.
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
                ['name' => 'Hardy'],
            ],
        ])
        ->assertSessionHasErrors();
});

it('rejects an ability whose source model exceeds the ability cost cap', function () {
    // Regression: the cap previously read a non-existent `abilities.cost`
    // column. It now reads the source model's cost (pg 17).
    $user = leaderUser();
    $crew = crewFor($user);
    $k1 = keywordWithModelInFaction(FactionEnum::Resurrectionists);
    $k2 = Keyword::factory()->create();

    $ability = \App\Models\Ability::factory()->create();
    // Generalist ability cap is 7; the source ally costs 10 → reject.
    $ally = Character::factory()->create(['faction' => FactionEnum::Resurrectionists, 'cost' => 10, 'station' => CharacterStationEnum::Minion]);
    $ally->keywords()->attach($k1);
    $ally->abilities()->attach($ability);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.update', [$crew->campaign_id, $crew->share_code]), [
            'name' => 'Over Cap',
            'archetype' => LeaderArchetypeEnum::Generalist->value,
            'tag' => LeaderTagEnum::Bruiser->value,
            'faction' => FactionEnum::Resurrectionists->value,
            'keyword_1_id' => $k1->id, 'keyword_2_id' => $k2->id,
            'size' => 2, 'base' => 30,
            'actions' => [],
            'abilities' => [[
                'name' => $ability->name,
                'source_id' => $ability->id,
                'source_character_id' => $ally->id,
            ]],
        ])
        ->assertSessionHasErrors('abilities.0.source_character_id');

    expect(CustomCharacter::where('campaign_crew_id', $crew->id)->exists())->toBeFalse();
});

it('search/actions returns matches filtered by crew keyword and source-ally cost cap', function () {
    $user = leaderUser();
    $crew = crewFor($user);

    $keyword = Keyword::factory()->create(['name' => 'Family']);
    $crew->update(['keyword_1_id' => $keyword->id]);

    // In-keyword ally within the cap → its action appears.
    $char = Character::factory()->create(['station' => CharacterStationEnum::Minion, 'cost' => 3]);
    $char->keywords()->attach($keyword);
    $action = Action::factory()->create(['name' => 'Family Slap']);
    $action->characters()->attach($char);

    // Out-of-keyword ally → shouldn't appear.
    $other = Character::factory()->create(['station' => CharacterStationEnum::Minion, 'cost' => 3]);
    $otherAction = Action::factory()->create(['name' => 'Family Bite']);
    $otherAction->characters()->attach($other);

    // Over-cap ally (cost 10 > 5) → its action is excluded.
    $expensiveAlly = Character::factory()->create(['station' => CharacterStationEnum::Minion, 'cost' => 10]);
    $expensiveAlly->keywords()->attach($keyword);
    $expensive = Action::factory()->create(['name' => 'Family Cannon']);
    $expensive->characters()->attach($expensiveAlly);

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

it('save accepts an action sourced from a valid in-keyword ally', function () {
    $user = leaderUser();
    $crew = crewFor($user);
    $k1 = keywordWithModelInFaction(FactionEnum::Resurrectionists);
    $k2 = Keyword::factory()->create();

    $action = Action::factory()->create(['stone_cost' => 5]);
    $ally = Character::factory()->create(['faction' => FactionEnum::Resurrectionists, 'cost' => 6, 'station' => CharacterStationEnum::Minion]);
    $ally->keywords()->attach($k1);
    $ally->actions()->attach($action);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.update', [$crew->campaign_id, $crew->share_code]), [
            'name' => 'Valid Source Leader',
            'archetype' => LeaderArchetypeEnum::Generalist->value,
            'tag' => LeaderTagEnum::Bruiser->value,
            'faction' => FactionEnum::Resurrectionists->value,
            'keyword_1_id' => $k1->id, 'keyword_2_id' => $k2->id,
            'size' => 2, 'base' => 30,
            'actions' => [[
                'name' => $action->name, 'type' => 'attack', 'category' => 'attack',
                'stone_cost' => 5, 'is_signature' => false, 'triggers' => [],
                'source_id' => $action->id, 'source_character_id' => $ally->id,
            ]],
            'abilities' => [],
        ])
        ->assertRedirect();

    expect(CustomCharacter::where('campaign_crew_id', $crew->id)->where('current', true)->exists())->toBeTrue();
});

it('save rejects an action sourced from a master (pg 17)', function () {
    $user = leaderUser();
    $crew = crewFor($user);
    $k1 = keywordWithModelInFaction(FactionEnum::Resurrectionists);
    $k2 = Keyword::factory()->create();

    $action = Action::factory()->create(['stone_cost' => 5]);
    $master = Character::factory()->create(['faction' => FactionEnum::Resurrectionists, 'cost' => null, 'station' => CharacterStationEnum::Master]);
    $master->keywords()->attach($k1);
    $master->actions()->attach($action);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.update', [$crew->campaign_id, $crew->share_code]), [
            'name' => 'Master Source Leader',
            'archetype' => LeaderArchetypeEnum::Generalist->value,
            'tag' => LeaderTagEnum::Bruiser->value,
            'faction' => FactionEnum::Resurrectionists->value,
            'keyword_1_id' => $k1->id, 'keyword_2_id' => $k2->id,
            'size' => 2, 'base' => 30,
            'actions' => [[
                'name' => $action->name, 'type' => 'attack', 'category' => 'attack',
                'stone_cost' => 5, 'is_signature' => false, 'triggers' => [],
                'source_id' => $action->id, 'source_character_id' => $master->id,
            ]],
            'abilities' => [],
        ])
        ->assertSessionHasErrors('actions.0.source_character_id');

    expect(CustomCharacter::where('campaign_crew_id', $crew->id)->exists())->toBeFalse();
});

it('leader action search includes actions from NULL-station (Unique) allies', function () {
    $user = leaderUser();
    $crew = crewFor($user);
    $kw = Keyword::factory()->create();
    $crew->update(['keyword_1_id' => $kw->id, 'faction' => FactionEnum::Guild->value]);

    // Enforcers/Henchmen/Uniques carry a NULL station (characteristics, not stations).
    $action = Action::factory()->create(['name' => 'Heavy Swing']);
    $enforcer = Character::factory()->create(['cost' => 8, 'station' => null, 'faction' => FactionEnum::Guild]);
    $enforcer->keywords()->attach($kw);
    $enforcer->actions()->attach($action);

    $res = $this->actingAs($user)->getJson(
        route('campaigns.crews.leader.search.actions', [$crew->campaign_id, $crew->share_code]).'?q=Heavy&max_cost=10'
    )->assertOk();

    expect(collect($res->json())->pluck('name'))->toContain('Heavy Swing');
});

it('leader action search filters by the picked category — no Tacticals under Attack', function () {
    $user = leaderUser();
    $crew = crewFor($user);
    $kw = Keyword::factory()->create();
    $crew->update(['keyword_1_id' => $kw->id, 'faction' => FactionEnum::Guild->value]);

    $atk = Action::factory()->create(['name' => 'Cleaver Strike', 'type' => 'attack']);
    $tac = Action::factory()->create(['name' => 'Cleaver Feint', 'type' => 'tactical']);
    $ally = Character::factory()->create(['cost' => 6, 'station' => null, 'faction' => FactionEnum::Guild]);
    $ally->keywords()->attach($kw);
    $ally->actions()->attach([$atk->id, $tac->id]);

    $names = collect($this->actingAs($user)->getJson(
        route('campaigns.crews.leader.search.actions', [$crew->campaign_id, $crew->share_code]).'?q=Cleaver&max_cost=10&type=attack',
    )->assertOk()->json())->pluck('name');

    expect($names)->toContain('Cleaver Strike');
    expect($names)->not->toContain('Cleaver Feint');
});

it('leader action search accepts in-form keywords before the crew is saved', function () {
    $user = leaderUser();
    $crew = crewFor($user); // no keyword persisted on the crew
    $kw = Keyword::factory()->create();

    $action = Action::factory()->create(['name' => 'Borrowed Blade', 'type' => 'attack']);
    $ally = Character::factory()->create(['cost' => 6, 'station' => null, 'faction' => FactionEnum::Guild]);
    $ally->keywords()->attach($kw);
    $ally->actions()->attach($action);

    $names = collect($this->actingAs($user)->getJson(
        route('campaigns.crews.leader.search.actions', [$crew->campaign_id, $crew->share_code])."?q=Borrowed&max_cost=10&keyword_1_id={$kw->id}",
    )->assertOk()->json())->pluck('name');

    expect($names)->toContain('Borrowed Blade');
});
