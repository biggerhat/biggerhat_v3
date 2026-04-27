<?php

namespace Database\Factories\TOS;

use App\Models\TOS\Allegiance;
use App\Models\TOS\Crew;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Crew>
 */
class CrewFactory extends Factory
{
    protected $model = Crew::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'allegiance_id' => Allegiance::factory(),
            'name' => $this->faker->unique()->words(2, true).' Company',
            'notes' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function forAllegiance(Allegiance $allegiance): static
    {
        return $this->state(['allegiance_id' => $allegiance->id]);
    }
}
