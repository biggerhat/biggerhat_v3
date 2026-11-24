<?php

namespace Database\Factories;

use App\Enums\BattleReportStatusEnum;
use App\Models\Game;
use App\Models\User;
use App\Support\BattleReports\ApprovedSnapshot;
use App\Support\BattleReports\TrustedAuthors;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BattleReport>
 */
class BattleReportFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $this->faker->paragraph()]]]]],
            'show_game' => true,
            'status' => BattleReportStatusEnum::Private,
            'published_at' => null,
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => ['status' => BattleReportStatusEnum::Approved, 'published_at' => now()])
            ->afterCreating(fn ($report) => $report->update(['approved_snapshot' => ApprovedSnapshot::capture($report)]));
    }

    /** Public, and approved by a person (not automatically): what earns a writer trust. */
    public function approvedByModerator(): static
    {
        return $this->public()->state(fn () => ['moderated_by' => User::factory(), 'moderated_at' => now()])
            ->afterCreating(fn ($report) => TrustedAuthors::record($report, 'approved', $report->moderator));
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => BattleReportStatusEnum::Pending]);
    }

    public function rejected(string $note = 'Not suitable.'): static
    {
        return $this->state(fn () => ['status' => BattleReportStatusEnum::Rejected, 'moderation_note' => $note, 'moderated_at' => now()])
            ->afterCreating(fn ($report) => TrustedAuthors::record($report, 'rejected', User::factory()->create(), $note));
    }

    public function forGame(Game $game): static
    {
        return $this->state(fn () => ['game_id' => $game->id]);
    }
}
