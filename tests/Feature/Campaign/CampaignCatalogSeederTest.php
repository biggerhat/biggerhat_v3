<?php

use App\Models\Campaign\AdvancementAbility;
use App\Models\Campaign\AdvancementAction;
use App\Models\Campaign\AdvancementAttackMod;
use App\Models\Campaign\AdvancementTacticalMod;
use App\Models\Campaign\BackAlleyDoctorResult;
use App\Models\Campaign\CrewCardEffect;
use App\Models\Campaign\Equipment;
use App\Models\Campaign\Injury;
use App\Models\Campaign\LeaderArchetype;
use App\Models\Campaign\LuckyMiss;
use App\Models\Campaign\SummoningAdvancement;
use App\Models\Campaign\Totem;
use App\Models\Campaign\WeeklyEvent;
use Database\Seeders\CampaignCatalogSeeder;

it('seeds every campaign catalog table with usable rows', function () {
    $this->seed(CampaignCatalogSeeder::class);

    expect(LeaderArchetype::count())->toBe(5)
        ->and(CrewCardEffect::count())->toBeGreaterThanOrEqual(5)
        ->and(Equipment::count())->toBeGreaterThanOrEqual(10)
        ->and(Injury::count())->toBeGreaterThanOrEqual(6)
        ->and(LuckyMiss::count())->toBeGreaterThanOrEqual(3)
        ->and(BackAlleyDoctorResult::count())->toBe(7)
        ->and(AdvancementAttackMod::count())->toBeGreaterThanOrEqual(6)
        ->and(AdvancementTacticalMod::count())->toBeGreaterThanOrEqual(5)
        ->and(AdvancementAction::count())->toBeGreaterThanOrEqual(5)
        ->and(AdvancementAbility::count())->toBeGreaterThanOrEqual(5)
        ->and(Totem::count())->toBeGreaterThanOrEqual(5)
        ->and(SummoningAdvancement::count())->toBe(4)
        ->and(WeeklyEvent::count())->toBeGreaterThanOrEqual(5);
});

it('covers the full 1-13 flip range on back-alley doctor results', function () {
    $this->seed(CampaignCatalogSeeder::class);

    // Range-based lookup — any uncovered band returns null at flip time.
    foreach (range(1, 13) as $fv) {
        $hit = BackAlleyDoctorResult::query()
            ->where('flip_value_min', '<=', $fv)
            ->where('flip_value_max', '>=', $fv)
            ->exists();
        expect($hit)->toBeTrue("Doctor table is missing coverage for flip value {$fv}");
    }

    expect(BackAlleyDoctorResult::where('is_black_joker', true)->exists())->toBeTrue()
        ->and(BackAlleyDoctorResult::where('is_red_joker', true)->exists())->toBeTrue();
});

it('is idempotent — re-running does not duplicate rows', function () {
    $this->seed(CampaignCatalogSeeder::class);
    $firstCount = LeaderArchetype::count() + BackAlleyDoctorResult::count() + Equipment::count();

    $this->seed(CampaignCatalogSeeder::class);
    $secondCount = LeaderArchetype::count() + BackAlleyDoctorResult::count() + Equipment::count();

    expect($secondCount)->toBe($firstCount);
});
