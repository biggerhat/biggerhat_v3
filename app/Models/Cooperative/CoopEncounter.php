<?php

namespace App\Models\Cooperative;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Cooperative Mode encounter. Number 0 is the introduction.
 *
 * `phases` is an ordered list; phase 1 is the opening setup and every later phase is shown behind a spoiler.
 * Each phase: title, narrative, max_hand_size, role_order[{role, class_slug}], setup[], special_rules[], victory[],
 * defeat[], progression[]. `campaign`: victory, defeat, aftermath[], next_encounter.
 *
 * @property int $id
 * @property int $number
 * @property string $name
 * @property string $slug
 * @property string|null $narrative
 * @property int|null $encounter_size
 * @property int|null $max_hand_size
 * @property string|null $board_size
 * @property string|null $challenge_mode
 * @property list<array<string, mixed>>|null $phases
 * @property array<string, mixed>|null $campaign
 * @property bool $is_published
 */
class CoopEncounter extends Model
{
    /** @use HasFactory<\Database\Factories\Cooperative\CoopEncounterFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'phases' => 'array',
            'campaign' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /** @param \Illuminate\Database\Eloquent\Builder<self> $query */
    public function scopePublished($query): void
    {
        $query->where('is_published', true);
    }
}
