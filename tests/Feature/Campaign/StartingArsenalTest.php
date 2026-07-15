<?php

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\PermissionEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use App\Models\Token;
use App\Models\Upgrade;
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

it('hiring one titled model in the Starting Arsenal auto-adds its sibling', function () {
    $effect = CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    $titleGroup = 'test-titled-group';
    $versionA = Character::factory()->create([
        'cost' => 8, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists, 'title_group_key' => $titleGroup,
    ]);
    $versionA->keywords()->attach($kw);
    $versionB = Character::factory()->create([
        'cost' => 8, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists, 'title_group_key' => $titleGroup,
    ]);
    $versionB->keywords()->attach($kw);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [
                ['character_id' => $versionA->id],
            ],
            'crew_card_effect_id' => $effect->id,
        ])
        ->assertRedirect();

    $models = CampaignArsenalModel::where('campaign_crew_id', $crew->id)->get();
    expect($models)->toHaveCount(2);
    expect($models->pluck('character_id')->sort()->values()->all())->toEqual([$versionA->id, $versionB->id]);
    expect($models->pluck('title_group_key')->unique())->toHaveCount(1);
});

it('saves the crew card to the Card Creator when named, and updates on re-save', function () {
    $effect = CampaignCrewCard::factory()->create(['name' => 'Loot Their Stash', 'description' => 'Steal a thing.']);
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [],
            'crew_card_effect_id' => $effect->id,
            'crew_card_name' => 'My Crew Card',
        ])
        ->assertRedirect();

    $upgrade = \App\Models\CustomUpgrade::where('user_id', $user->id)->where('name', 'My Crew Card')->first();
    expect($upgrade)->not->toBeNull();
    expect($upgrade->domain->value)->toBe('crew');
    expect($upgrade->is_campaign_crew_card)->toBeTrue();

    // Re-saving with the same name updates the existing card rather than duplicating.
    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [],
            'crew_card_effect_id' => $effect->id,
            'crew_card_name' => 'My Crew Card',
        ])
        ->assertRedirect();

    expect(\App\Models\CustomUpgrade::where('user_id', $user->id)->where('name', 'My Crew Card')->count())->toBe(1);
});

it('blocks deleting the saved crew card from the generic Card Creator editor', function () {
    $effect = CampaignCrewCard::factory()->create(['name' => 'Loot Their Stash', 'description' => 'Steal a thing.']);
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [],
            'crew_card_effect_id' => $effect->id,
            'crew_card_name' => 'My Crew Card',
        ])
        ->assertRedirect();

    $upgrade = \App\Models\CustomUpgrade::where('user_id', $user->id)->where('name', 'My Crew Card')->firstOrFail();

    $this->actingAs($user)
        ->deleteJson(route('tools.card_creator.upgrades.destroy', $upgrade->id))
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    expect(\App\Models\CustomUpgrade::find($upgrade->id))->not->toBeNull();
});

it('does not save a Card Creator card when no name is given', function () {
    $effect = CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [],
            'crew_card_effect_id' => $effect->id,
        ])
        ->assertRedirect();

    expect(\App\Models\CustomUpgrade::where('user_id', $user->id)->count())->toBe(0);
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

it('refuses to save out-of-faction models', function () {
    $effect = CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw); // faction: Arcanists

    // Character is in a DIFFERENT faction with a different keyword.
    $other = Keyword::factory()->create();
    $char = Character::factory()->create(['cost' => 5, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Guild]);
    $char->keywords()->attach($other);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [['character_id' => $char->id]],
            'crew_card_effect_id' => $effect->id,
        ])
        ->assertRedirect();

    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(0);
});

it('allows out-of-keyword in-faction models', function () {
    $effect = CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw); // faction: Arcanists

    // Character is in the same faction but has a different keyword and is not Versatile.
    $other = Keyword::factory()->create();
    $char = Character::factory()->create(['cost' => 5, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists]);
    $char->keywords()->attach($other);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [['character_id' => $char->id]],
            'crew_card_effect_id' => $effect->id,
        ])
        ->assertRedirect();

    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(1);
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

it('stores a constrained crew-card token choice and rejects one outside the keyword pool', function () {
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    // A crew card (crew-domain upgrade) sharing the crew's keyword, with a token on it.
    $token = Token::factory()->create(['name' => 'Fast']);
    $crewCardUpgrade = Upgrade::factory()->create([
        'domain' => UpgradeDomainTypeEnum::Crew->value,
        'game_mode_type' => GameModeTypeEnum::Standard->value,
    ]);
    $crewCardUpgrade->keywords()->attach($kw);
    $crewCardUpgrade->tokens()->attach($token);

    $card = CampaignCrewCard::factory()->create(['requires_token_choice' => true]);

    // Valid pick is stored.
    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [],
            'crew_card_effect_id' => $card->id,
            'crew_card_choice' => ['type' => 'token', 'id' => $token->id],
        ])
        ->assertRedirect();
    expect($crew->fresh()->crew_card_choice)->toMatchArray(['type' => 'token', 'id' => $token->id, 'name' => 'Fast']);

    // A token not on any keyword crew card is rejected.
    $stray = Token::factory()->create();
    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [],
            'crew_card_effect_id' => $card->id,
            'crew_card_choice' => ['type' => 'token', 'id' => $stray->id],
        ]);
    // Unchanged from the first (valid) save — the rejected save never ran.
    expect($crew->fresh()->crew_card_choice['id'])->toBe($token->id);
});

it('stores a crew-card upgrade-type choice as the enum value, not a crew card', function () {
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    // A crew card sharing the keyword, carrying the Aspect upgrade type — the
    // option offered should be the TYPE (aspect), not the crew card itself.
    $crewCardUpgrade = Upgrade::factory()->create([
        'domain' => UpgradeDomainTypeEnum::Crew->value,
        'game_mode_type' => GameModeTypeEnum::Standard->value,
        'type' => \App\Enums\UpgradeTypeEnum::Aspect->value,
    ]);
    $crewCardUpgrade->keywords()->attach($kw);

    $card = CampaignCrewCard::factory()->create(['requires_upgrade_type_choice' => true]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [],
            'crew_card_effect_id' => $card->id,
            'crew_card_choice' => ['type' => 'upgrade', 'id' => \App\Enums\UpgradeTypeEnum::Aspect->value],
        ])
        ->assertRedirect();

    expect($crew->fresh()->crew_card_choice)->toMatchArray(['type' => 'upgrade', 'id' => 'aspect']);
});

it('counts out-of-keyword non-versatile models at +1 ss toward the 25 ss budget', function () {
    $effect = CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw); // faction: Arcanists, budget 25 ss

    // In-keyword model: 6 ss.
    $inKw = Character::factory()->create(['cost' => 6, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists]);
    $inKw->keywords()->attach($kw);

    // OOK in-faction model: 20 ss base. 6 + (20 + 1) = 27 — over budget.
    $otherKw = Keyword::factory()->create();
    $ookChar = Character::factory()->create(['cost' => 20, 'station' => CharacterStationEnum::Minion, 'faction' => FactionEnum::Arcanists]);
    $ookChar->keywords()->attach($otherKw);

    // Buying both (6 + 21 = 27) must be rejected.
    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [['character_id' => $inKw->id], ['character_id' => $ookChar->id]],
            'crew_card_effect_id' => $effect->id,
        ])
        ->assertRedirect();

    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(0);
});

it('rejects hiring the same Unique model twice in the starting arsenal', function () {
    $effect = CampaignCrewCard::factory()->create();
    $user = arsenalUser();
    $kw = Keyword::factory()->create();
    $crew = freshCrewWithKeyword($user, $kw);

    $uniqueChar = Characteristic::factory()->create(['name' => 'Unique']);
    $char = Character::factory()->create(['cost' => 5, 'station' => null, 'faction' => FactionEnum::Arcanists]);
    $char->keywords()->attach($kw);
    $char->characteristics()->attach($uniqueChar);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [['character_id' => $char->id], ['character_id' => $char->id]],
            'crew_card_effect_id' => $effect->id,
        ])
        ->assertRedirect();

    expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(0);
});
