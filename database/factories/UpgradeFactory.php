<?php

namespace Database\Factories;

use App\Enums\GameModeTypeEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Enums\UpgradeTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Upgrade>
 */
class UpgradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_mode_type' => GameModeTypeEnum::Standard,
            'name' => $this->faker->unique()->word(),
            'domain' => $this->faker->randomElement(UpgradeDomainTypeEnum::cases()),
            'type' => $this->faker->optional()->randomElement(UpgradeTypeEnum::cases()),
            'front_image' => 'seed/upgrade-front.png',
            'back_image' => 'seed/upgrade-back.png',
        ];
    }

    /**
     * Campaign Mode equipment — lives on the upgrades table with
     * `campaign_upgrade_kind = 'equipment'`. Replaces the deprecated
     * `EquipmentFactory` from the dedicated equipment_catalog table.
     */
    public function campaignEquipment(): static
    {
        return $this->state(fn () => [
            'game_mode_type' => GameModeTypeEnum::Campaign,
            'campaign_upgrade_kind' => 'equipment',
            'campaign_br' => $this->faker->numberBetween(1, 13),
            'campaign_cc' => $this->faker->numberBetween(1, 3),
            'campaign_is_always_available' => false,
            'campaign_ttw_only' => false,
            'campaign_pool_suit_a' => 'ram',
            'campaign_pool_suit_b' => 'crow',
        ]);
    }

    /** Always-Available campaign equipment — bypasses BR check at barter. */
    public function campaignEquipmentAlwaysAvailable(): static
    {
        return $this->campaignEquipment()->state(fn () => [
            'campaign_br' => null,
            'campaign_is_always_available' => true,
        ]);
    }

    /** Those Who Thirst campaign equipment — red-joker-gated at barter. */
    public function campaignEquipmentTtw(): static
    {
        return $this->campaignEquipment()->state(fn () => [
            'campaign_ttw_only' => true,
        ]);
    }

    /**
     * Campaign Mode injury — lives on the upgrades table with
     * `campaign_upgrade_kind = 'injury'`. Replaces the deprecated InjuryFactory.
     */
    public function campaignInjury(): static
    {
        return $this->state(fn () => [
            'game_mode_type' => GameModeTypeEnum::Campaign,
            'campaign_upgrade_kind' => 'injury',
            'campaign_flip_value' => $this->faker->numberBetween(1, 13),
            'campaign_suit_pool' => 'pc',
            'campaign_is_traitor' => false,
            'campaign_annihilates_model' => false,
        ]);
    }

    /** Killed Off injury — campaign_annihilates_model = true. */
    public function campaignInjuryKilledOff(): static
    {
        return $this->campaignInjury()->state(fn () => [
            'campaign_annihilates_model' => true,
            'name' => 'Killed Off',
        ]);
    }
}
