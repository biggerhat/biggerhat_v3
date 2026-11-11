<?php

namespace Database\Factories\Cooperative;

use App\Models\Cooperative\CoopEncounter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CoopEncounter>
 */
class CoopEncounterFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'number' => $this->faker->unique()->numberBetween(1, 200),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'narrative' => 'The dusty square is quiet.',
            'encounter_size' => 36,
            'max_hand_size' => 5,
            'board_size' => '36" × 36"',
            'challenge_mode' => null,
            'phases' => [
                [
                    'title' => 'Opening',
                    'narrative' => null,
                    'max_hand_size' => 5,
                    'role_order' => [['role' => 'brawler', 'class_slug' => null], ['role' => 'firepower', 'class_slug' => null]],
                    'setup' => ['Place a piece of climbable terrain in the center of each table quarter.'],
                    'special_rules' => ['Player-owned models may Interact with Strategy markers.'],
                    'victory' => ['If all Gunslingers have been killed, the players are victorious.'],
                    'defeat' => ['If all player-owned models have been killed, the players are defeated.'],
                    'progression' => [],
                ],
                [
                    'title' => 'Next phase',
                    'narrative' => 'Well, that seems to be it for easy jobs today.',
                    'max_hand_size' => 6,
                    'role_order' => [['role' => 'firepower', 'class_slug' => null]],
                    'setup' => ['Set the turn number to 0.'],
                    'special_rules' => [],
                    'victory' => [],
                    'defeat' => ['At the end of turn 3, the players are defeated.'],
                    'progression' => [],
                ],
            ],
            'campaign' => ['victory' => '', 'defeat' => '', 'aftermath' => [], 'next_encounter' => ''],
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
