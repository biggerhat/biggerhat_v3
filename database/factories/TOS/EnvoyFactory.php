<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Enums\TOS\EnvoyRestrictionEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Envoy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Envoy>
 */
class EnvoyFactory extends Factory
{
    protected $model = Envoy::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(random_int(2, 3), true);

        return [
            'allegiance_id' => Allegiance::factory()->syndicate(),
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name' => Str::title($name),
            'keyword' => null,
            'restriction' => EnvoyRestrictionEnum::Malifaux,
            'body' => $this->faker->paragraph(),
            'image_path' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * Anchor the Envoy to a specific Syndicate. The Envoy's restriction is
     * derived from the Syndicate's type so Court of Two (Malifaux) yields an
     * Envoy with `restriction = malifaux` by default.
     */
    public function forAllegiance(Allegiance $allegiance): static
    {
        return $this->state(fn () => [
            'allegiance_id' => $allegiance->id,
            'restriction' => $allegiance->type === AllegianceTypeEnum::Earth
                ? EnvoyRestrictionEnum::Earth
                : EnvoyRestrictionEnum::Malifaux,
        ]);
    }

    public function earth(): static
    {
        return $this->state(fn () => ['restriction' => EnvoyRestrictionEnum::Earth]);
    }

    public function malifaux(): static
    {
        return $this->state(fn () => ['restriction' => EnvoyRestrictionEnum::Malifaux]);
    }

    public function withAbilities(int $count = 2): static
    {
        return $this->afterCreating(function (Envoy $envoy) use ($count) {
            $abilities = Ability::factory()->general()->count($count)->create();
            $envoy->abilities()->attach($abilities->pluck('id')->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all());
        });
    }
}
