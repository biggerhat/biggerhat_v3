<?php

use App\Enums\Campaign\BackAlleyDoctorOutcomeEnum;
use App\Models\Campaign\BackAlleyDoctorResult;
use App\Models\Campaign\LuckyMiss;
use App\Models\Upgrade;
use Database\Seeders\CampaignCatalogSeeder;
use Database\Seeders\CampaignRulebookSeeder;

it('seeds the book equipment and injury tables and is idempotent', function () {
    (new CampaignRulebookSeeder)->run();
    $equipment = Upgrade::where('campaign_upgrade_kind', 'equipment')->count();
    $injuries = Upgrade::where('campaign_upgrade_kind', 'injury')->count();

    (new CampaignRulebookSeeder)->run();

    expect(Upgrade::where('campaign_upgrade_kind', 'equipment')->count())->toBe($equipment)
        ->and(Upgrade::where('campaign_upgrade_kind', 'injury')->count())->toBe($injuries)
        // pg 34-35: 13 + 13 numbered results, Traitor (black joker), Close Call (red joker)
        ->and($injuries)->toBe(28)
        ->and(Upgrade::where('name', 'Pistol')->value('campaign_is_always_available'))->toBeTrue()
        ->and(Upgrade::where('name', 'Mutagen Injector')->first()->only(['campaign_br', 'campaign_cc']))->toBe(['campaign_br' => 10, 'campaign_cc' => 2])
        ->and(Upgrade::where('campaign_ttw_only', true)->count())->toBe(9)
        ->and(Upgrade::where('name', 'Killed Off')->where('campaign_annihilates_model', true)->count())->toBe(2);
});

it('seeds the Back-Alley Doctor and Lucky Miss tables as printed (pg 33, 36)', function () {
    (new CampaignCatalogSeeder)->run();

    expect(BackAlleyDoctorResult::where('is_black_joker', true)->value('outcome_kind'))->toBe(BackAlleyDoctorOutcomeEnum::AddedInjury)
        ->and(BackAlleyDoctorResult::where('flip_value_min', 1)->value('outcome_kind'))->toBe(BackAlleyDoctorOutcomeEnum::NoEffect)
        ->and(LuckyMiss::count())->toBe(14)
        ->and(LuckyMiss::where('is_doppelganger', true)->count())->toBe(1);
});
