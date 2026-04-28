<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AllegianceCard>
 */
class AllegianceCardFactory extends Factory
{
    protected $model = AllegianceCard::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(random_int(2, 3), true);

        return [
            'allegiance_id' => Allegiance::factory(),
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name' => Str::title($name),
            'type' => AllegianceTypeEnum::Earth,
            'body' => $this->faker->paragraph(),
            'image_path' => null,
            'sort_order' => 0,
        ];
    }

    public function forAllegiance(Allegiance $allegiance): static
    {
        return $this->state(fn () => [
            'allegiance_id' => $allegiance->id,
            'type' => $allegiance->type,
        ]);
    }

    /**
     * Hybrid card — prints both Earth and Malifaux on its face. Mirrors the
     * Allegiance hybrid pattern so consumer code can call `types()` and get
     * back two values.
     */
    public function hybrid(): static
    {
        return $this->state(fn () => [
            'type' => AllegianceTypeEnum::Earth,
            'secondary_type' => AllegianceTypeEnum::Malifaux,
        ]);
    }

    public function withAbilities(int $count = 2): static
    {
        return $this->afterCreating(function (AllegianceCard $card) use ($count) {
            $abilities = Ability::factory()->general()->count($count)->create();
            $card->abilities()->attach($abilities->pluck('id')->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all());
        });
    }
}
