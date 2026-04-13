<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Tournament;
use App\Models\User;

class TournamentPolicy
{
    /**
     * Super-admins (anyone holding manage_tournaments) can do everything.
     */
    public function before(?User $user, string $ability): ?bool
    {
        if ($user !== null && $user->can(PermissionEnum::ManageTournaments->value)) {
            return true;
        }

        return null;
    }

    /** Anyone can view the listing. */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /** Anyone can view a tournament's public page. */
    public function view(?User $user, Tournament $tournament): bool
    {
        return true;
    }

    /** Tournament organizers (the role) can create new tournaments. */
    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateTournaments->value);
    }

    /**
     * Manage covers all per-tournament organizer actions: editing settings,
     * adding/removing players, configuring rounds, scoring games, etc.
     */
    public function manage(User $user, Tournament $tournament): bool
    {
        return $tournament->isOrganizer($user->id);
    }

    /** Generic update gate — settings edits. */
    public function update(User $user, Tournament $tournament): bool
    {
        return $this->manage($user, $tournament);
    }

    /** Only the creator can delete a tournament (and only while in Draft). */
    public function delete(User $user, Tournament $tournament): bool
    {
        return $tournament->creator_id === $user->id;
    }

    /** Only the creator can grant the "organizer" role on this tournament. */
    public function addOrganizer(User $user, Tournament $tournament): bool
    {
        return $tournament->creator_id === $user->id;
    }

    /** Only the creator can revoke organizer access. */
    public function removeOrganizer(User $user, Tournament $tournament): bool
    {
        return $tournament->creator_id === $user->id;
    }
}
