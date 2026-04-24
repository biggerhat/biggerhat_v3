<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Enums\TOS\SpecialUnitRuleEnum;
use App\Enums\TOS\UnitSideEnum;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSide;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(random_int(2, 3), true);

        return [
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name' => Str::title($name),
            'title' => null,
            'scrip' => $this->faker->numberBetween(2, 12),
            'tactics' => null,
            'description' => $this->faker->paragraph(),
            'lore_text' => null,
            'restriction' => null,
            'combined_arms_child_id' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * Flag the unit as Neutral for the given Allegiance type — hireable by any
     * Allegiance of that type without an explicit pivot attachment.
     */
    public function neutralFor(AllegianceTypeEnum $type): static
    {
        return $this->state(['restriction' => $type->value]);
    }

    /**
     * Always create both Standard and Glory sides — every Unit Card is
     * two-sided (rulebook p. 9), so a Unit row without both sides is invalid.
     * Use this state any time a test or seeder needs a "complete" Unit.
     */
    public function withSides(): static
    {
        return $this->afterCreating(function (Unit $unit) {
            UnitSide::factory()->for($unit, 'unit')->state(['side' => UnitSideEnum::Standard])->create();
            UnitSide::factory()->for($unit, 'unit')->state([
                'side' => UnitSideEnum::Glory,
                // Glory side typically buffs at least one AV; nudge defense up
                // so factory-built units exhibit the asymmetry a real card has.
                'defense' => $this->faker->numberBetween(5, 7),
            ])->create();
        });
    }

    public function commander(): static
    {
        return $this->withSpecialRule(SpecialUnitRuleEnum::Commander);
    }

    public function titan(): static
    {
        return $this->withSpecialRule(SpecialUnitRuleEnum::Titan);
    }

    public function champion(): static
    {
        return $this->withSpecialRule(SpecialUnitRuleEnum::Champion);
    }

    public function fireteam(int $baseMm = 30, int $modelsPerTeam = 3, int $modelSizeMm = 30): static
    {
        return $this->withSpecialRule(SpecialUnitRuleEnum::Fireteam, [
            'base_mm' => $baseMm,
            'models_per_team' => $modelsPerTeam,
            'model_size_mm' => $modelSizeMm,
        ]);
    }

    public function squad(int $fireteamCount = 3): static
    {
        return $this->withSpecialRule(SpecialUnitRuleEnum::Squad, [
            'fireteam_count' => $fireteamCount,
        ]);
    }

    public function unique(): static
    {
        return $this->withSpecialRule(SpecialUnitRuleEnum::Unique);
    }

    /**
     * @param  array<string, mixed>|null  $parameters
     */
    private function withSpecialRule(SpecialUnitRuleEnum $rule, ?array $parameters = null): static
    {
        return $this->afterCreating(function (Unit $unit) use ($rule, $parameters) {
            $rec = SpecialUnitRule::firstOrCreate(
                ['slug' => $rule->value],
                ['name' => $rule->label()],
            );
            $unit->specialUnitRules()->syncWithoutDetaching([
                $rec->id => ['parameters' => $parameters],
            ]);
        });
    }
}
