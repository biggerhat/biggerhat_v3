<?php

namespace Database\Factories\TOS;

use App\Models\TOS\Deployment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Deployment>
 */
class DeploymentFactory extends Factory
{
    protected $model = Deployment::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name' => Str::title($name),
            'suit' => null,
            'description' => $this->faker->sentence(),
            'image_path' => null,
            'sort_order' => 0,
        ];
    }

    public function forSuit(string $suit): static
    {
        return $this->state(fn () => ['suit' => $suit]);
    }
}
