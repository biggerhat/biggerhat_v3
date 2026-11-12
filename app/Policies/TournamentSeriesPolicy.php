<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\TournamentSeries;
use App\Models\User;

class TournamentSeriesPolicy
{
    /** Anyone holding manage_tournaments can moderate every series. */
    public function before(?User $user, string $ability): ?bool
    {
        if ($user !== null && $user->can(PermissionEnum::ManageTournaments->value)) {
            return true;
        }

        return null;
    }

    /** Series pages are public, like tournament pages. */
    public function view(?User $user, TournamentSeries $series): bool
    {
        return true;
    }

    /** Any signed-in user can start a league. */
    public function create(User $user): bool
    {
        return true;
    }

    /** Settings, and which events belong to it, are the creator's. */
    public function manage(User $user, TournamentSeries $series): bool
    {
        return $series->creator_id === $user->id;
    }
}
