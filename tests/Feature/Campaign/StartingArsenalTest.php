<?php

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignPlayer;
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

function arsenalUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

function freshCrewWithKeyword(User $user, Keyword $kw): CampaignCrew
{
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    return CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'faction' => FactionEnum::Arcanists->value,
        'keyword_1_id' => $kw->id,
        'keyword_2_id' => null,
    ]);
}

it('renders the Starting Arsenal page for the crew owner', function () {
    CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    $this->actingAs($user)
        ->get(route('campaigns.crews.starting-arsenal.edit', [$crew->campaign_id, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Campaigns/StartingArsenal')
            ->where('starting_budget_ss', 25)
            ->where('max_leftover_scrip', 3)
            ->where('locked', false)
        );
});

it('blocks non-owner from the wizard', function () {
    CampaignCrewCard::factory()->create();
    $owner = arsenalUser();
    $other = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($owner, $kw);

    $this->actingAs($other)
        ->get(route('campaigns.crews.starting-arsenal.edit', [$crew->campaign_id, $crew->share_code]))
        ->assertForbidden();
});

it('saves arsenal models + crew card effect + computed scrip', function () {
    $effect = CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    // Two in-keyword hireable models totalling 22 ss → 3 scrip cap.
    $char1 = Character::factory()->create([
        'cost' => 8,
        'station' => CharacterStationEnum::Minion,
        'faction' => FactionEnum::Arcanists,
    ]);
    $char1->keywords()->attach($kw);
    $char2 = Character::factory()->create([
        'cost' => 14,
        'station' => CharacterStationEnum::Minion,
        'faction' => FactionEnum::Arcanists,
    ]);
    $char2->keywords()->attach($kw);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [
                ['character_id' => $char1->id],
                ['character_id' => $char2->id],
            ],
            'crew_card_effect_id' => $effect->id,
        ])
        ->assertRedirect();

    $crew->refresh();
    expect($crew->crew_card_effect_id)->toBe($effect->id);
    // 25 - 22 = 3, capped at 3.
    expect($crew->scrip)->toBe(3);
    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(2);
});

it('caps leftover scrip at 3 even with tiny arsenal', function () {
    $effect = CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    $char = Character::factory()->create(['cost' => 5, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists]);
    $char->keywords()->attach($kw);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [['character_id' => $char->id]],
            'crew_card_effect_id' => $effect->id,
        ])
        ->assertRedirect();

    expect($crew->fresh()->scrip)->toBe(3); // 25 - 5 = 20, capped at 3
});

it('rejects over-budget hires', function () {
    $effect = CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    $char = Character::factory()->create(['cost' => 30, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists]);
    $char->keywords()->attach($kw);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [['character_id' => $char->id]],
            'crew_card_effect_id' => $effect->id,
        ])
        ->assertRedirect();

    // Not saved.
    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(0);
    expect($crew->fresh()->crew_card_effect_id)->toBeNull();
});

it('refuses to save out-of-keyword non-versatile models', function () {
    $effect = CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    // Character has a DIFFERENT keyword and is not Versatile.
    $other = Keyword::factory()->create();
    $char = Character::factory()->create(['cost' => 5, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists]);
    $char->keywords()->attach($other);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [['character_id' => $char->id]],
            'crew_card_effect_id' => $effect->id,
        ])
        ->assertRedirect();

    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(0);
});

it('allows Versatile-in-faction even when not in-keyword', function () {
    $effect = CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    $versatile = Characteristic::factory()->create(['name' => 'Versatile']);
    $other = Keyword::factory()->create();
    $char = Character::factory()->create(['cost' => 7, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists]);
    $char->keywords()->attach($other);
    $char->characteristics()->attach($versatile);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [['character_id' => $char->id]],
            'crew_card_effect_id' => $effect->id,
        ])
        ->assertRedirect();

    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(1);
});

it('locks once the campaign is active', function () {
    $effect = CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);
    $crew->campaign->update(['status' => CampaignStatusEnum::Active]);

    $char = Character::factory()->create(['cost' => 5, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists]);
    $char->keywords()->attach($kw);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [['character_id' => $char->id]],
            'crew_card_effect_id' => $effect->id,
        ])
        ->assertRedirect();

    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(0);
});

it('includes NULL-station (Henchman/Enforcer/Unique) models in the hireable pool', function () {
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw); // faction Arcanists

    // Henchman/Enforcer/Unique are characteristics, not stations — these models
    // carry a NULL station. A whereNotIn('station', [master]) wrongly drops them.
    $unique = Character::factory()->create(['cost' => 8, 'station' => null, 'faction' => FactionEnum::Arcanists]);
    $unique->keywords()->attach($kw);

    $this->actingAs($user)
        ->get(route('campaigns.crews.starting-arsenal.edit', [$crew->campaign_id, $crew->share_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hireable', fn ($models) => collect($models)->contains('id', $unique->id)));
});
