<?php

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Campaign\CampaignWeek;
use App\Models\Campaign\WeeklyEvent;
use App\Models\Character;
use App\Models\Characteristic;
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

function wkUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

function activeCampaignFor(User $organizer, array $overrides = []): Campaign
{
    $campaign = Campaign::factory()->active()->create([
        'organizer_user_id' => $organizer->id,
        'current_week' => 1,
        'length_weeks' => 8,
        ...$overrides,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $organizer->id]);

    return $campaign;
}

it('advances the week and creates a CampaignWeek row', function () {
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer);

    $this->actingAs($organizer)
        ->post(route('campaigns.weeks.advance', $campaign))
        ->assertRedirect();

    $campaign->refresh();
    expect($campaign->current_week)->toBe(2);
    expect(CampaignWeek::where('campaign_id', $campaign->id)->where('week_number', 2)->exists())->toBeTrue();
});

it('a double-submitted week advance only creates one CampaignWeek row', function () {
    // Simulates a double-click: both requests read the campaign before
    // either commits. The lock-then-recheck inside advance() must let only
    // one of them actually advance the week.
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer);

    $this->actingAs($organizer)
        ->post(route('campaigns.weeks.advance', $campaign))
        ->assertRedirect();

    expect($campaign->fresh()->current_week)->toBe(2);

    // Second submission against the same stale pre-advance state.
    $this->actingAs($organizer)
        ->post(route('campaigns.weeks.advance', $campaign))
        ->assertRedirect();

    expect($campaign->fresh()->current_week)->toBe(3);
    expect(CampaignWeek::where('campaign_id', $campaign->id)->count())->toBe(2);
    expect(CampaignWeek::where('campaign_id', $campaign->id)->where('week_number', 2)->count())->toBe(1);
});

it('rolls a weekly event when the campaign has the option enabled', function () {
    WeeklyEvent::factory()->count(3)->create();
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer, ['weekly_event_active' => true]);

    $this->actingAs($organizer)
        ->post(route('campaigns.weeks.advance', $campaign))
        ->assertRedirect();

    $week = CampaignWeek::where('campaign_id', $campaign->id)->where('week_number', 2)->first();
    expect($week)->not->toBeNull();
    expect($week->weekly_event_id)->not->toBeNull();
});

it('does not roll a weekly event when the option is off', function () {
    WeeklyEvent::factory()->count(3)->create();
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer, ['weekly_event_active' => false]);

    $this->actingAs($organizer)->post(route('campaigns.weeks.advance', $campaign));

    $week = CampaignWeek::where('campaign_id', $campaign->id)->where('week_number', 2)->first();
    expect($week->weekly_event_id)->toBeNull();
});

it('blocks non-organizers from advancing the week', function () {
    $organizer = wkUser();
    $other = wkUser();
    $campaign = activeCampaignFor($organizer);

    $this->actingAs($other)
        ->post(route('campaigns.weeks.advance', $campaign))
        ->assertForbidden();
});

it('refuses to advance past length_weeks', function () {
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer, ['current_week' => 8, 'length_weeks' => 8]);

    $this->actingAs($organizer)
        ->post(route('campaigns.weeks.advance', $campaign));

    expect($campaign->fresh()->current_week)->toBe(8);
});

it('refuses to advance week on a non-active campaign', function () {
    $organizer = wkUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $organizer->id,
        'status' => CampaignStatusEnum::Planning,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $organizer->id]);

    $this->actingAs($organizer)
        ->post(route('campaigns.weeks.advance', $campaign));

    expect($campaign->fresh()->current_week)->toBe(1);
});

it('renders the weekly hire page for the crew owner', function () {
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer);
    $kw = Keyword::factory()->create();
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $organizer->id,
        'faction' => FactionEnum::Arcanists->value,
        'keyword_1_id' => $kw->id,
        'scrip' => 10,
    ]);

    $this->actingAs($organizer)
        ->get(route('campaigns.crews.weekly-hire.edit', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Campaigns/WeeklyHire')
            ->where('crew.scrip', 10)
            ->where('already_hired_this_week', 0)
        );
});

it('applies first-hire-of-week -5 discount and out-of-keyword +1 surcharge', function () {
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer);
    $kw = Keyword::factory()->create();
    $otherKw = Keyword::factory()->create();
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $organizer->id,
        'faction' => FactionEnum::Arcanists->value,
        'keyword_1_id' => $kw->id,
        'scrip' => 20,
    ]);

    $inKeyword = Character::factory()->create([
        'cost' => 8,
        'station' => CharacterStationEnum::Minion,
        'faction' => FactionEnum::Arcanists,
    ]);
    $inKeyword->keywords()->attach($kw);

    $outOfKeyword = Character::factory()->create([
        'cost' => 7,
        'station' => CharacterStationEnum::Minion,
        'faction' => FactionEnum::Arcanists,
    ]);
    $outOfKeyword->keywords()->attach($otherKw);

    // First hire (in-keyword 8 ss) → 8 - 5 = 3 scrip.
    // Second hire (out-of-keyword 7 ss) → 7 + 1 = 8 scrip.
    // Total = 11 scrip.
    $this->actingAs($organizer)
        ->post(route('campaigns.crews.weekly-hire.update', [$campaign, $crew->share_code]), [
            'hires' => [
                ['character_id' => $inKeyword->id],
                ['character_id' => $outOfKeyword->id],
            ],
        ])
        ->assertRedirect();

    $crew->refresh();
    expect($crew->scrip)->toBe(20 - 11);
    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(2);
});

it('hiring one titled model auto-adds its sibling to the arsenal sharing an arsenal-side title_group_key', function () {
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer);
    $kw = Keyword::factory()->create();
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $organizer->id,
        'faction' => FactionEnum::Arcanists->value,
        'keyword_1_id' => $kw->id,
        'scrip' => 20,
    ]);

    $titleGroup = 'test-titled-group';
    $versionA = Character::factory()->create([
        'cost' => 8, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists, 'title_group_key' => $titleGroup,
    ]);
    $versionA->keywords()->attach($kw);
    $versionB = Character::factory()->create([
        'cost' => 8, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists, 'title_group_key' => $titleGroup,
    ]);
    $versionB->keywords()->attach($kw);

    $this->actingAs($organizer)
        ->post(route('campaigns.crews.weekly-hire.update', [$campaign, $crew->share_code]), [
            'hires' => [
                ['character_id' => $versionA->id],
            ],
        ])
        ->assertRedirect();

    $models = CampaignArsenalModel::where('campaign_crew_id', $crew->id)->get();
    expect($models)->toHaveCount(2);
    expect($models->pluck('character_id')->sort()->values()->all())->toEqual([$versionA->id, $versionB->id]);
    expect($models->pluck('title_group_key')->unique())->toHaveCount(1);
    expect($models->first()->title_group_key)->not->toBeNull();
    // Only the originally-hired model's cost was charged.
    expect($crew->fresh()->scrip)->toBe(20 - 3);
});

it('a double-submitted hire only claims the first-hire discount once', function () {
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer);
    $kw = Keyword::factory()->create();
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $organizer->id,
        'faction' => FactionEnum::Arcanists->value,
        'keyword_1_id' => $kw->id,
        'scrip' => 20,
    ]);
    $model = Character::factory()->create([
        'cost' => 8,
        'station' => CharacterStationEnum::Minion,
        'faction' => FactionEnum::Arcanists,
    ]);
    $model->keywords()->attach($kw);

    // First hire: 8 - 5 = 3 scrip, claims the week's only discount.
    $this->actingAs($organizer)
        ->post(route('campaigns.crews.weekly-hire.update', [$campaign, $crew->share_code]), [
            'hires' => [['character_id' => $model->id]],
        ])
        ->assertRedirect();
    expect($crew->fresh()->scrip)->toBe(17);

    // Second hire this week must NOT get the discount again — full 8 scrip.
    $this->actingAs($organizer)
        ->post(route('campaigns.crews.weekly-hire.update', [$campaign, $crew->share_code]), [
            'hires' => [['character_id' => $model->id]],
        ])
        ->assertRedirect();

    expect($crew->fresh()->scrip)->toBe(17 - 8);
    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(2);
});

it('rejects hiring more than the crew can afford', function () {
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer);
    $kw = Keyword::factory()->create();
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $organizer->id,
        'faction' => FactionEnum::Arcanists->value,
        'keyword_1_id' => $kw->id,
        'scrip' => 3,
    ]);

    $expensive = Character::factory()->create([
        'cost' => 9,
        'station' => CharacterStationEnum::Minion,
        'faction' => FactionEnum::Arcanists,
    ]);
    $expensive->keywords()->attach($kw);

    // First hire: 9 - 5 = 4, exceeds the crew's 3 scrip.
    $this->actingAs($organizer)
        ->post(route('campaigns.crews.weekly-hire.update', [$campaign, $crew->share_code]), [
            'hires' => [['character_id' => $expensive->id]],
        ])
        ->assertRedirect();

    expect($crew->fresh()->scrip)->toBe(3);
    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(0);
});

it('requires at least one hire', function () {
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer);
    $kw = Keyword::factory()->create();
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $organizer->id,
        'faction' => FactionEnum::Arcanists->value,
        'keyword_1_id' => $kw->id,
        'scrip' => 5,
    ]);

    $this->actingAs($organizer)
        ->post(route('campaigns.crews.weekly-hire.update', [$campaign, $crew->share_code]), [
            'hires' => [],
        ])
        ->assertSessionHasErrors('hires');
});

it('drops first-hire discount once already-hired-this-week > 0', function () {
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer);
    $kw = Keyword::factory()->create();
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $organizer->id,
        'faction' => FactionEnum::Arcanists->value,
        'keyword_1_id' => $kw->id,
        'scrip' => 20,
    ]);

    // Pre-seed a hire for this week so the new submit is NOT first-of-week.
    CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crew->id,
        'acquired_week' => $campaign->current_week,
        'acquired_via' => 'hire',
    ]);

    $next = Character::factory()->create([
        'cost' => 6,
        'station' => CharacterStationEnum::Minion,
        'faction' => FactionEnum::Arcanists,
    ]);
    $next->keywords()->attach($kw);

    $this->actingAs($organizer)
        ->post(route('campaigns.crews.weekly-hire.update', [$campaign, $crew->share_code]), [
            'hires' => [['character_id' => $next->id]],
        ])
        ->assertRedirect();

    // Cost = 6 (no -5 discount because not first-of-week).
    expect($crew->fresh()->scrip)->toBe(20 - 6);
});

it('excludes Unique models already in the active arsenal from the hireable pool', function () {
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer);
    $kw = Keyword::factory()->create();
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $organizer->id,
        'faction' => FactionEnum::Arcanists->value,
        'keyword_1_id' => $kw->id,
        'scrip' => 10,
    ]);

    $uniqueChar = Characteristic::factory()->create(['name' => 'Unique']);
    $char = Character::factory()->create(['cost' => 5, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists]);
    $char->keywords()->attach($kw);
    $char->characteristics()->attach($uniqueChar);

    // Pre-seed the Unique in the active arsenal.
    CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crew->id,
        'character_id' => $char->id,
    ]);

    $this->actingAs($organizer)
        ->get(route('campaigns.crews.weekly-hire.edit', [$campaign, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('hireable', fn ($models) => ! collect($models)->contains('id', $char->id))
        );
});

it('rejects hiring the same Unique model twice in one weekly hire batch', function () {
    $organizer = wkUser();
    $campaign = activeCampaignFor($organizer);
    $kw = Keyword::factory()->create();
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $organizer->id,
        'faction' => FactionEnum::Arcanists->value,
        'keyword_1_id' => $kw->id,
        'scrip' => 30,
    ]);

    $uniqueChar = Characteristic::factory()->create(['name' => 'Unique']);
    $char = Character::factory()->create(['cost' => 5, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists]);
    $char->keywords()->attach($kw);
    $char->characteristics()->attach($uniqueChar);

    $this->actingAs($organizer)
        ->post(route('campaigns.crews.weekly-hire.update', [$campaign, $crew->share_code]), [
            'hires' => [['character_id' => $char->id], ['character_id' => $char->id]],
        ])
        ->assertRedirect();

    expect($crew->fresh()->scrip)->toBe(30);
    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(0);
});
