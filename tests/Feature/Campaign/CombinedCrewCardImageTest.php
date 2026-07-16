<?php

use App\Enums\CrewUpgradeRestrictionDescriptorTypeEnum;
use App\Enums\CrewUpgradeRestrictionEnum;
use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Jobs\Campaign\GenerateCombinedCrewCardImage;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignCrewCardAdvancement;
use App\Models\CustomCharacter;
use App\Models\Upgrade;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

/** @return array{User, CampaignCrew} */
function combinedCardFixture(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $campaign = Campaign::factory()->create();
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    return [$user, $crew];
}

it('capture page combines the starter effect with a generic Tier-4 borrow and a keyword-matched borrow with a restriction qualifier', function () {
    [, $crew] = combinedCardFixture();

    $starterAction = Action::factory()->create(['name' => 'Starter Swing']);
    $starter = CampaignCrewCard::factory()->create();
    $starter->actions()->attach($starterAction->id, ['is_signature_action' => false]);
    $crew->update(['crew_card_effect_id' => $starter->id]);

    // A generic (campaign_crew_card-sourced) Tier-4 borrow — no restriction concept.
    $genericAbility = Ability::factory()->create(['name' => 'Generic Boon']);
    $genericBorrow = CampaignCrewCard::factory()->create();
    $genericBorrow->abilities()->attach($genericAbility->id);
    CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crew->id,
        'crew_card_effect_id' => $genericBorrow->id,
        'crew_card_effect_type' => CampaignCrewCard::class,
    ]);

    // A keyword-matched (crew_upgrade-sourced) Tier-4 borrow with a restricted ability.
    $restrictedAbility = Ability::factory()->create(['name' => 'Keyword Gift']);
    $upgrade = Upgrade::factory()->create(['domain' => UpgradeDomainTypeEnum::Crew->value]);
    $upgrade->abilities()->attach($restrictedAbility->id, ['restriction' => CrewUpgradeRestrictionEnum::FriendlyKeyword->value]);
    CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crew->id,
        'crew_card_effect_id' => $upgrade->id,
        'crew_card_effect_type' => Upgrade::class,
    ]);

    $this->get(route('tools.card_creator.capture_crew_card_combined', $crew->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CardCreator/CaptureCombinedCrewCard')
            ->where('crewName', $crew->name)
            ->where('items', function ($items) use ($restrictedAbility) {
                $items = collect($items);
                $starterItem = $items->firstWhere('data.name', 'Starter Swing');
                $genericItem = $items->firstWhere('data.name', 'Generic Boon');
                $restrictedItem = $items->firstWhere('data.name', $restrictedAbility->name);
                $expectedQualifier = CrewUpgradeRestrictionEnum::FriendlyKeyword->descriptor(CrewUpgradeRestrictionDescriptorTypeEnum::Ability);

                return $starterItem && $starterItem['qualifier'] === null
                    && $genericItem && $genericItem['qualifier'] === null
                    && $restrictedItem && $restrictedItem['qualifier'] === $expectedQualifier;
            })
        );
});

it('capture page includes a standalone trigger from a keyword-matched Crew Card Upgrade', function () {
    [, $crew] = combinedCardFixture();

    $trigger = \App\Models\Trigger::factory()->create(['name' => 'Free Trigger']);
    $upgrade = Upgrade::factory()->create(['domain' => UpgradeDomainTypeEnum::Crew->value]);
    $upgrade->triggers()->attach($trigger->id, ['restriction' => null]);
    CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crew->id,
        'crew_card_effect_id' => $upgrade->id,
        'crew_card_effect_type' => Upgrade::class,
    ]);

    $this->get(route('tools.card_creator.capture_crew_card_combined', $crew->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('items', function ($items) {
            $match = collect($items)->firstWhere('data.name', 'Free Trigger');

            return $match && $match['type'] === 'trigger';
        }));
});

it('capture page includes the starter effect\'s own description as a text item', function () {
    [, $crew] = combinedCardFixture();

    $starter = CampaignCrewCard::factory()->create(['description' => 'This crew card grants a permanent boon.']);
    $crew->update(['crew_card_effect_id' => $starter->id]);

    $this->get(route('tools.card_creator.capture_crew_card_combined', $crew->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('items', function ($items) {
            $match = collect($items)->firstWhere('data.body', 'This crew card grants a permanent boon.');

            return $match && $match['type'] === 'text' && $match['qualifier'] === null;
        }));
});

it('Starting Arsenal dispatches a combined card regeneration', function () {
    Bus::fake();
    [$user, $crew] = combinedCardFixture();
    $crew->update(['keyword_1_id' => \App\Models\Keyword::factory()->create()->id]);
    CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Starter Leader',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6,
        'base' => 30,
    ]);
    $starter = CampaignCrewCard::factory()->create();

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$crew->campaign_id, $crew->share_code]), [
            'hires' => [],
            'crew_card_effect_id' => $starter->id,
        ]);

    Bus::assertDispatched(GenerateCombinedCrewCardImage::class, fn ($job) => $job->campaignCrewId === $crew->id);
});

it('app:regenerate-crew-card-images re-queues generation for every crew with a starter effect, skipping crews without one', function () {
    Bus::fake();
    [, $crewWithStarter] = combinedCardFixture();
    $crewWithStarter->update(['crew_card_effect_id' => CampaignCrewCard::factory()->create()->id]);
    [, $crewWithoutStarter] = combinedCardFixture();

    $this->artisan('app:regenerate-crew-card-images')->assertExitCode(0);

    Bus::assertDispatched(GenerateCombinedCrewCardImage::class, fn ($job) => $job->campaignCrewId === $crewWithStarter->id);
    Bus::assertNotDispatched(GenerateCombinedCrewCardImage::class, fn ($job) => $job->campaignCrewId === $crewWithoutStarter->id);
});
