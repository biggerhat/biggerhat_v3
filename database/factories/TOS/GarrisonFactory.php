<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\GarrisonFormatEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Garrison;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Garrison>
 */
class GarrisonFactory extends Factory
{
    protected $model = Garrison::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'allegiance_id' => Allegiance::factory(),
            'name' => $this->faker->unique()->words(2, true).' Garrison',
            'format' => GarrisonFormatEnum::OneCommander,
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

    public function format(GarrisonFormatEnum $format): static
    {
        return $this->state(['format' => $format]);
    }
}
