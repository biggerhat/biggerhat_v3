<?php

namespace App\Tournaments;

use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentRsvp;
use Illuminate\Support\Facades\DB;

/**
 * "Instant sign-up" events turn an RSVP into a player straight away while a seat is free, so the organizer doesn't convert each one
 * by hand. Waitlisted RSVPs are seated oldest first as seats open up. Individual events only (a team needs its members chosen),
 * and never while a round is being played (a new player would have no game in it).
 */
class InstantSignup
{
    public static function applies(Tournament $tournament): bool
    {
        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;

        return (bool) $tournament->instant_signup
            && ! $tournament->isTeamEvent()
            && $status !== TournamentStatusEnum::Completed
            && ! $tournament->rounds()->where('status', TournamentRoundStatusEnum::InProgress)->exists();
    }

    /** Seat one RSVP as a player when there is room. Returns the player, or null when it stays an RSVP (full, already seated, not applicable). */
    public static function seat(Tournament $tournament, TournamentRsvp $rsvp): ?TournamentPlayer
    {
        if (! self::applies($tournament)) {
            return null;
        }

        return DB::transaction(function () use ($tournament, $rsvp): ?TournamentPlayer {
            // Serialise sign-ups so two people can't both take the last seat.
            Tournament::query()->whereKey($tournament->id)->lockForUpdate()->first();

            if ($tournament->isFull() || $tournament->players()->where('user_id', $rsvp->user_id)->exists()) {
                return null;
            }

            $user = $rsvp->user;

            return TournamentPlayer::create([
                'tournament_id' => $tournament->id,
                'display_name' => $user->name,
                'user_id' => $user->id,
                'meta_id' => $user->meta_id,
                'faction' => $rsvp->faction?->value,
                'allegiance_id' => $rsvp->allegiance_id,
                'is_ringer' => false,
            ]);
        });
    }

    /** Seat waitlisted RSVPs into whatever seats are free, oldest first. Returns how many were seated. */
    public static function seatWaiting(Tournament $tournament): int
    {
        if (! self::applies($tournament)) {
            return 0;
        }

        $seated = 0;
        $playerUserIds = $tournament->players()->whereNotNull('user_id')->pluck('user_id')->all();
        /** @var \Illuminate\Database\Eloquent\Collection<int, TournamentRsvp> $rsvps */
        $rsvps = $tournament->rsvps()->with('user')->orderBy('created_at')->orderBy('id')->get();
        foreach ($rsvps as $rsvp) {
            if (in_array($rsvp->user_id, $playerUserIds, true)) {
                continue;
            }
            if (self::seat($tournament, $rsvp) === null) {
                break;
            }
            $seated++;
        }

        return $seated;
    }
}
