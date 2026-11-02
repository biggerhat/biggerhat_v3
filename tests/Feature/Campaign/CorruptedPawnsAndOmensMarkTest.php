<?php

use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignEquipment;
use App\Models\Campaign\CampaignGame;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Character;
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

function pawnUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

function pawnFixture(array $rules = ['corrupted_pawns' => true]): array
{
    $user = pawnUser();
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id, 'optional_rules' => $rules]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $cheap = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id, 'character_id' => Character::factory()->create(['cost' => 5])->id]);
    $pricey = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id, 'character_id' => Character::factory()->create(['cost' => 8])->id]);
    $ttw = Upgrade::factory()->campaignEquipmentTtw()->create(['name' => 'Edict', 'campaign_br' => 5]);

    return [$user, $campaign, $crew, $cheap, $pricey, $ttw];
}

it('Corrupted Pawns attaches a Those Who Thirst item to a cost-6-or-less model and replaces it on re-pick', function () {
    [$user, $campaign, $crew, $cheap, , $ttw] = pawnFixture();
    $other = Upgrade::factory()->campaignEquipmentTtw()->create(['name' => 'Blight', 'campaign_br' => 6]);
    $route = route('campaigns.crews.corrupted-pawn.store', [$campaign, $crew]);

    $this->actingAs($user)->post($route, ['equipment_upgrade_id' => $ttw->id, 'arsenal_model_id' => $cheap->id])->assertRedirect();
    $this->actingAs($user)->post($route, ['equipment_upgrade_id' => $other->id, 'arsenal_model_id' => $cheap->id])->assertRedirect();

    $rows = CampaignEquipment::where('campaign_crew_id', $crew->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->equipment_upgrade_id)->toBe($other->id)
        ->and($rows->first()->bound_arsenal_model_id)->toBe($cheap->id)
        ->and($rows->first()->source)->toBe('starting_corrupted_pawns');
});

it('Corrupted Pawns refuses a model over cost 6, a non-TTW item, the disabled rule, and a started campaign', function () {
    [$user, $campaign, $crew, $cheap, $pricey, $ttw] = pawnFixture();
    $normal = Upgrade::factory()->campaignEquipment()->create();
    $route = route('campaigns.crews.corrupted-pawn.store', [$campaign, $crew]);

    $this->actingAs($user)->post($route, ['equipment_upgrade_id' => $ttw->id, 'arsenal_model_id' => $pricey->id])->assertRedirect();
    $this->actingAs($user)->post($route, ['equipment_upgrade_id' => $normal->id, 'arsenal_model_id' => $cheap->id])->assertSessionHasErrors('equipment_upgrade_id');
    expect(CampaignEquipment::count())->toBe(0);

    // Started campaign + a crew that already finished its starting arsenal (crew card picked) = locked.
    $campaign->update(['status' => 'active']);
    $crew->update(['crew_card_effect_id' => CampaignCrewCard::factory()->create()->id]);
    $this->actingAs($user)->post($route, ['equipment_upgrade_id' => $ttw->id, 'arsenal_model_id' => $cheap->id])->assertRedirect();
    expect(CampaignEquipment::count())->toBe(0);

    [$user2, $campaign2, $crew2, $cheap2, , $ttw2] = pawnFixture([]);
    $this->actingAs($user2)->post(route('campaigns.crews.corrupted-pawn.store', [$campaign2, $crew2]), ['equipment_upgrade_id' => $ttw2->id, 'arsenal_model_id' => $cheap2->id])->assertNotFound();
});

it('the Corrupted Pawns item does not count against the one-TTW limit and cannot be removed by hand', function () {
    [$user, $campaign, $crew, $cheap, , $ttw] = pawnFixture();
    $second = Upgrade::factory()->campaignEquipmentTtw()->create(['campaign_br' => 7, 'campaign_cc' => 0]);
    $pawn = CampaignEquipment::factory()->create([
        'campaign_crew_id' => $crew->id,
        'equipment_upgrade_id' => $ttw->id,
        'source' => 'starting_corrupted_pawns',
        'bound_arsenal_model_id' => $cheap->id,
    ]);
    $campaign->update(['status' => 'active', 'current_week' => 1]);
    $crew->update(['scrip' => 5]);
    $opponent = CampaignCrew::factory()->create(['campaign_id' => $campaign->id]);
    $game = CampaignGame::factory()->create(['campaign_id' => $campaign->id, 'crew_a_id' => $crew->id, 'crew_b_id' => $opponent->id]);
    $aftermath = CampaignAftermath::factory()->create(['campaign_game_id' => $game->id, 'campaign_crew_id' => $crew->id, 'current_phase' => 3, 'hand_drawn' => []]);

    $this->actingAs($user)->post(route('campaigns.aftermaths.barter', $aftermath), ['ttw_pickup' => $second->id])->assertRedirect();
    expect(CampaignEquipment::where('equipment_upgrade_id', $second->id)->exists())->toBeTrue();

    $this->actingAs($user)->delete(route('campaigns.crews.arsenal.equipment.destroy', [$campaign, $crew, $pawn]))->assertRedirect();
    expect($pawn->fresh()->annihilated_at)->toBeNull();
});

it('Phase 6 passes Omen\'s Mark to the opposing crew when its carrier is killed (pg 30)', function () {
    $user = pawnUser();
    $campaign = Campaign::factory()->active()->create(['organizer_user_id' => $user->id, 'current_week' => 1]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $opponent = CampaignCrew::factory()->create(['campaign_id' => $campaign->id]);
    $game = CampaignGame::factory()->create(['campaign_id' => $campaign->id, 'crew_a_id' => $crew->id, 'crew_b_id' => $opponent->id]);
    $omen = Upgrade::factory()->campaignEquipmentTtw()->create(['name' => 'Omen\'s Mark', 'campaign_is_omens_mark' => true, 'campaign_cc' => 0]);
    $mine = CampaignEquipment::factory()->create(['campaign_crew_id' => $crew->id, 'equipment_upgrade_id' => $omen->id, 'source' => 'joker']);
    $aftermath = CampaignAftermath::factory()->create(['campaign_game_id' => $game->id, 'campaign_crew_id' => $crew->id, 'current_phase' => 6, 'hand_drawn' => []]);

    $props = $this->actingAs($user)->get(route('campaigns.aftermaths.show', $aftermath))->viewData('page')['props'];
    expect($props['omens_mark'])->toBe(['owned' => 1, 'carrier_killed' => false]);

    $this->actingAs($user)->post(route('campaigns.aftermaths.determine-injuries', $aftermath), ['flips' => [], 'omens_mark_killed' => true])->assertRedirect();

    expect($mine->fresh()->annihilated_at)->not->toBeNull()
        ->and(CampaignEquipment::where('campaign_crew_id', $opponent->id)->where('equipment_upgrade_id', $omen->id)->active()->count())->toBe(1);
});
