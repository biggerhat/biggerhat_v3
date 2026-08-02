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
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignCrewCardAdvancement;
use App\Models\Character;
use App\Models\CustomCharacter;
use App\Models\Marker;
use App\Models\Token;
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

it('tags each item with its source (starter vs borrowed) so the front/back faces can split on it', function () {
    [, $crew] = combinedCardFixture();

    $starterAction = Action::factory()->create(['name' => 'Starter Swing']);
    $starter = CampaignCrewCard::factory()->create();
    $starter->actions()->attach($starterAction->id, ['is_signature_action' => false]);
    $crew->update(['crew_card_effect_id' => $starter->id]);

    $borrowedAbility = Ability::factory()->create(['name' => 'Borrowed Boon']);
    $borrow = CampaignCrewCard::factory()->create();
    $borrow->abilities()->attach($borrowedAbility->id);
    CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crew->id,
        'crew_card_effect_id' => $borrow->id,
        'crew_card_effect_type' => CampaignCrewCard::class,
    ]);

    $this->get(route('tools.card_creator.capture_crew_card_combined', $crew->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items', function ($items) {
                $items = collect($items);
                $starterItem = $items->firstWhere('data.name', 'Starter Swing');
                $borrowedItem = $items->firstWhere('data.name', 'Borrowed Boon');

                return $starterItem && $starterItem['source'] === 'starter'
                    && $borrowedItem && $borrowedItem['source'] === 'borrowed';
            })
        );
});

it('capture page surfaces the starter\'s own token/marker/upgrade-type pick as a choice item on the front (T3-33)', function () {
    [, $crew] = combinedCardFixture();

    $starter = CampaignCrewCard::factory()->create();
    $crew->update([
        'crew_card_effect_id' => $starter->id,
        'crew_card_choice' => ['type' => 'token', 'id' => 5, 'name' => 'Corpse Counter'],
    ]);

    $this->get(route('tools.card_creator.capture_crew_card_combined', $crew->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items', function ($items) {
                $choice = collect($items)->firstWhere('type', 'choice');

                return $choice
                    && $choice['source'] === 'starter'
                    && $choice['data']['type'] === 'token'
                    && $choice['data']['name'] === 'Corpse Counter';
            })
        );
});

it('capture page surfaces a borrowed effect\'s own choice pick as a choice item on the back (T3-33)', function () {
    [, $crew] = combinedCardFixture();

    $borrow = CampaignCrewCard::factory()->create();
    CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crew->id,
        'crew_card_effect_id' => $borrow->id,
        'crew_card_effect_type' => CampaignCrewCard::class,
        'crew_card_choice' => ['type' => 'marker', 'id' => 9, 'name' => 'Scheme Marker'],
    ]);

    $this->get(route('tools.card_creator.capture_crew_card_combined', $crew->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items', function ($items) {
                $choice = collect($items)->firstWhere('type', 'choice');

                return $choice
                    && $choice['source'] === 'borrowed'
                    && $choice['data']['type'] === 'marker'
                    && $choice['data']['name'] === 'Scheme Marker';
            })
        );
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

it('capture page surfaces tokens/markers gathered from the crew\'s active arsenal, deduped', function () {
    [, $crew] = combinedCardFixture();

    $token = Token::factory()->create(['name' => 'Corpse Counter']);
    $marker = Marker::factory()->create(['name' => 'Scheme Marker']);
    $characterA = Character::factory()->create();
    $characterA->tokens()->attach($token->id);
    $characterA->markers()->attach($marker->id);
    $characterB = Character::factory()->create();
    // Shares the same token as $characterA — must be deduped, not doubled.
    $characterB->tokens()->attach($token->id);

    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id, 'character_id' => $characterA->id]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id, 'character_id' => $characterB->id]);

    $this->get(route('tools.card_creator.capture_crew_card_combined', $crew->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tokensMarkers', 2)
            ->where('tokensMarkers', function ($items) {
                $items = collect($items);

                return $items->firstWhere('name', 'Corpse Counter')['type'] === 'token'
                    && $items->firstWhere('name', 'Scheme Marker')['type'] === 'marker';
            })
        );
});

it('capture page surfaces tokens/markers tagged on a borrowed Crew Card Upgrade directly (via app:link-tokens-and-markers\' existing Upgrade link)', function () {
    [, $crew] = combinedCardFixture();

    $token = Token::factory()->create(['name' => 'Borrowed Upgrade Token']);
    $marker = Marker::factory()->create(['name' => 'Borrowed Upgrade Marker']);

    $upgrade = Upgrade::factory()->create(['domain' => UpgradeDomainTypeEnum::Crew->value]);
    $upgrade->tokens()->attach($token->id);
    $upgrade->markers()->attach($marker->id);
    CampaignCrewCardAdvancement::create([
        'campaign_crew_id' => $crew->id,
        'crew_card_effect_id' => $upgrade->id,
        'crew_card_effect_type' => Upgrade::class,
    ]);

    $this->get(route('tools.card_creator.capture_crew_card_combined', $crew->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tokensMarkers', 2)
            ->where('tokensMarkers', function ($items) {
                $items = collect($items);

                return $items->firstWhere('name', 'Borrowed Upgrade Token') && $items->firstWhere('name', 'Borrowed Upgrade Marker');
            })
        );
});

it('capture page does not surface tokens/markers for a generic (non-Upgrade) Crew Card starter — no such link exists yet', function () {
    [, $crew] = combinedCardFixture();

    $starter = CampaignCrewCard::factory()->create();
    $crew->update(['crew_card_effect_id' => $starter->id]);

    $this->get(route('tools.card_creator.capture_crew_card_combined', $crew->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tokensMarkers', 0));
});

it('capture page excludes tokens/markers from an annihilated arsenal model', function () {
    [, $crew] = combinedCardFixture();

    $token = Token::factory()->create(['name' => 'Corpse Counter']);
    $character = Character::factory()->create();
    $character->tokens()->attach($token->id);

    CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crew->id,
        'character_id' => $character->id,
        'annihilated_at' => now(),
    ]);

    $this->get(route('tools.card_creator.capture_crew_card_combined', $crew->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tokensMarkers', 0));
});

it('capture page contributes nothing from a hired homebrew Custom Character (no catalog token/marker link)', function () {
    [$user, $crew] = combinedCardFixture();

    $customCharacter = CustomCharacter::create([
        'user_id' => $user->id,
        'name' => 'Homebrew Hire',
        'faction' => FactionEnum::Guild->value,
        'health' => 8, 'defense' => 5, 'willpower' => 5, 'speed' => 5, 'base' => 30,
        'station' => 'minion',
    ]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id, 'character_id' => null, 'custom_character_id' => $customCharacter->id]);

    $this->get(route('tools.card_creator.capture_crew_card_combined', $crew->share_code))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tokensMarkers', 0));
});

it('dispatches a combined card regeneration when an arsenal model is hired', function () {
    Bus::fake();
    [, $crew] = combinedCardFixture();

    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);

    Bus::assertDispatched(GenerateCombinedCrewCardImage::class, fn ($job) => $job->campaignCrewId === $crew->id);
});

it('dispatches a combined card regeneration when an arsenal model is annihilated', function () {
    [, $crew] = combinedCardFixture();
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);

    Bus::fake();
    $model->update(['annihilated_at' => now()]);

    Bus::assertDispatched(GenerateCombinedCrewCardImage::class, fn ($job) => $job->campaignCrewId === $crew->id);
});

it('does not dispatch a combined card regeneration for an unrelated arsenal model field change', function () {
    [, $crew] = combinedCardFixture();
    $model = CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);

    Bus::fake();
    $model->update(['label' => 'Nickname']);

    Bus::assertNotDispatched(GenerateCombinedCrewCardImage::class);
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
