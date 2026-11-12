# Tournament Tracker — second audit (2026-11-12)

A front-to-back review after the first audit's work shipped (see [tournament-audit.md](tournament-audit.md) for that one and for what each branch changed).
Method: query/payload profiling on seeded and large synthetic events, a route and authorization sweep, a visual review of the harness screenshots
(phone and desktop, light and dark), a walk through each user journey in the code, and a read of notifications, metadata and exports.
IDs: **S** security/trust, **P** performance, **J** journey/feature, **V** visual/UI, **Q** code and tooling. Priority P1 = fix first, P3 = nice to have.
Effort: S ≈ a day or less, M ≈ a few days, L ≈ a week or more.

## What was measured, and what held up

| Check | Result |
|---|---|
| Authorization: every mutating `tournaments.*` route called as an unrelated signed-in user | **No leaks** (53 routes covered). Now a permanent test: `tests/Feature/Tournament/AuthorizationSweepTest.php`, which picks up new routes automatically. |
| Public routes | Only reads (list, view, display, results.pdf, event.ics, series pages). |
| Queries per page (seeded and 64/200-player events) | 12–35, no N+1 (`dup` ≤ 3). |
| Compute time | Standings 14 ms (64 players) / 46 ms (200); pairing 22 ms / 66 ms; peak memory ≈ 56 MB. Fine. |
| Contrast and structure (axe, 69 scans, light/dark, phone/desktop) | Clean after the first audit's two fixes. |
| Indexes | Adequate (MySQL adds FK indexes; SQLite shows fewer but is only the test DB). |

## Findings

### Security and trust
| ID | Pri | Finding | Evidence | Suggested fix | Effort |
|---|---|---|---|---|---|
| S1 | P2 | **CSV formula injection.** The export writes names unescaped. A display name such as `=HYPERLINK(...)` (account names are user-controlled) runs as a formula when an organizer opens the file in Excel/Sheets. | `exportCsv` passes values straight to `fputcsv`. | Prefix cells starting with `= + - @ \t \r` with `'`; apply to the import template too. | S |
| S2 | P2 | **Notification and invite spam.** Anyone signed in can create events (cap: 5 unfinished) and then add any account as player, organizer, scorer or award winner; each sends a notification with a link. 46 of 53 mutating routes have no throttle, and users cannot mute or decline. | Route list; `TournamentOrganizerController`, `TournamentPlayerController`, `TournamentAwardController`. | Throttle by route family; make organizer/scorer invites accept/decline; let users mute tournament notifications from a given organizer. | M |
| S3 | P3 | Public `results.pdf` renders DomPDF on every request, unthrottled. | `TournamentResultsController@pdf`. | Cache the finished event's PDF by `updated_at`, throttle. | S |

### Performance
| ID | Pri | Finding | Evidence | Suggested fix | Effort |
|---|---|---|---|---|---|
| P1 | **P1** | **Page payloads grow with games, and every live update refetches all of it.** Public view: 160 KB (64 players, 5 rounds), 620 KB (200 players, 7 rounds); organizer page 190 KB / 750 KB; `rounds` alone is 480 KB. Each game is serialised with full copies of both players. Every websocket event triggers a reload of `tournament`, `rounds` and `standings` for every viewer. | `scripts/ui-audit/profile.php` on synthetic events; `useTournamentChannel` handlers in `View`/`Display`. | Slim the game payload (scalar fields + player ids, players sent once); load finished rounds lazily; put the changed game in the broadcast and patch it client-side instead of reloading. | M |
| P2 | P2 | **Display mode does not scale to big events.** All pairings are one grid; 100 games overflow a TV, and cards stay small for few games. | `Display.vue` renders every game, no paging or sizing. | Auto-page or auto-scroll the grid; scale type and cards to the number of games. | S–M |

### Journeys and features
| ID | Pri | Finding | Evidence | Suggested fix | Effort |
|---|---|---|---|---|---|
| J1 | **P1** | **Sign-up is a two-step chore for the organizer.** An RSVP never becomes a player; the organizer converts each one by hand. | `TournamentRsvpController@store` only creates an RSVP. | "Instant sign-up" option: registering adds the player (with their declared faction), honouring the limit and waitlist. | M |
| J2 | **P1** | **Notifications only reach people who have the site open.** All 7 tournament notifications use `database` + `broadcast`; there is no email or web push and no user preferences. A player wandering the venue never learns "Round 3 — table 4". | `via()` of every class in `app/Notifications/Tournament`. | Web push through the existing service worker, optional email, a preferences screen; start with "round paired" and "your result needs confirming". | L |
| J3 | P2 | **No start time or time zone.** `event_date` is a date; the calendar file is all-day; nothing can say "doors 9:00, round 1 at 10:00" or remind people. | `TournamentCalendarController`, `tournaments.event_date`. | Add start time + time zone; check-in-opens time; reminder notifications the day before and an hour before. | M |
| J4 | P2 | **No audit trail for scores.** Scorekeepers, organizers and confirming players can change a result; nothing records who, when, or the old value, so disputes cannot be settled. | `TournamentScoreRecorder`, `LogsCreationActivity` only logs creation. | A per-game event log shown in the score dialog, with an undo of the last change. | M |
| J5 | P2 | **No printable event-day kit.** Only "Print pairings" exists. | `View.vue` print area. | Per-round match slips / table tents with names, table, terrain notes and QR to report; blank result slips. | M |
| J6 | P2 | **Call a judge / clock warnings.** Players cannot summon an organizer to a table; no "10 minutes left" (carried over from the first audit's F16). | No such route or job. | A "call judge" button on the match card that notifies organizers and scorekeepers with the table; scheduled clock notices. | M |
| J7 | P3 | **Scoring needs a connection.** Venue wifi is unreliable; the PWA service worker exists but the scoring desk cannot queue a result. | Score entry is a live `PUT`. | Queue writes offline in the desk and sync, with a visible "pending" state. | M–L |
| J8 | P3 | **Every event is public.** No unlisted or invite-only events (private league nights). | No visibility column. | `listed` / `unlisted` flag; unlisted skips the list and search. | S |
| J9 | P3 | **Pairing is Swiss or manual.** No round-robin pools, accelerated pairing or "avoid same club". | `TournamentPairingService`. | Round-robin option for small fields; club/meta avoidance generalised. | L |
| J10 | P3 | **No organizer insights.** Faction and master representation, attendance trend and scenario results exist as raw data but have no view. | `scheme_stats` is the only aggregate shown. | An event report page and a league-level version, exportable. | M |
| J11 | P3 | **Spectators cannot follow.** Friends and club members cannot subscribe to an event's updates without being players. | No follow model. | "Follow this event" feeding the same notification channels. | M |

### Sharing and discovery
| ID | Pri | Finding | Evidence | Suggested fix | Effort |
|---|---|---|---|---|---|
| D1 | P2 | **Links look generic when shared.** Tournament and league pages set only a `<title>`; a link pasted into Discord shows the site's default card. No JSON-LD `Event`. | Only `<Head :title>` in `View`/`Series/Show`; `SeoHead` and `JsonLd` exist but are unused here. | Use `SeoHead` with a description, and for finished events the results image as `og:image`; add `Event` JSON-LD. | S |
| D2 | P3 | The public page has no Share/QR button (organizers have one; display mode has a QR). | `View.vue`. | Share and QR for everyone. | S |

### Visual and UI
| ID | Pri | Finding | Where | Suggested change | Effort |
|---|---|---|---|---|---|
| V1 | P3 | Pairing rows stretch edge to edge on wide screens, leaving a large gap between the two players. | Public event page. | Cap the width and centre the matchup; make the table number and name prominent. | S |
| V2 | P3 | Finished standings list registered players who never played (0-0-0) as ranked rows, including the highlighted "You" row. | Final standings. | Fold them under "Did not play". | S |
| V3 | P3 | League page: the points rule is one dense run of text; the first place is not emphasised; event columns are truncated. | `Series/Show`. | Points as chips, a top-three treatment, event dates in the column headers. | S |
| V4 | P3 | Score dialog: no steppers for phones, and the bottom "0 – 0" is unlabelled. Keyboard "next game" still open (first audit's U4). | Score dialog. | +/− steppers, a labelled total, Tab-to-next-game. | S–M |
| V5 | P3 | Match card gives little about the opponent (name only) and a faint "Withdraw" link. | `MyMatchCard`. | Opponent faction/leader when known and the table's terrain note up front; a clearer withdraw action. | S |

### Code and tooling
| ID | Pri | Finding | Suggested fix | Effort |
|---|---|---|---|---|
| Q1 | P2 | Large files: `ManageRoundsTab` 1184 lines, `ManagePlayersTab` 1057, `View` 1053, `TournamentPagePayloads` 671. | Split along the seams already visible (round card, pairing row actions, standings, payload sections). | M |
| Q2 | P3 | `vue-tsc` reports ~1320 findings app-wide, about 90 in tournament files (65 in `Create.vue`); `preserveScroll` is flagged by the installed Inertia types. | Fix the tournament files first, then flip the CI step to blocking for them. | M |
| Q3 | P3 | Parallel tests need `memory_limit=512M`; at the default 128M a worker crashed in `CustomUpgradeCardImageTest`. `CardArtAdminControllerTest` flakes under parallel runs. | Raise the limit in the test runner config; find the flake's shared state. | S |
| Q4 | ops | `storage/logs/laravel.log` was ~4 GB, mostly harness-generated errors (the harness no longer writes them). | Truncate it locally. | — |

## Suggested order
1. **P1** payload slimming and delta updates — it affects every viewer of every live event and gets worse with size.
2. **J1** instant sign-up — the biggest organizer time saver.
3. **J2** push and email notifications — the biggest player-facing gap; plan this one carefully (service worker, preferences, opt-in).
4. **S1, S2** CSV escaping, and throttles plus invite acceptance — small and they close the only real trust issues found.
5. **D1** share cards and JSON-LD — a day of work with outsized reach.
6. **J4** score audit trail, then **J3** start times and reminders.
7. **P2** display mode for big fields, then the visual items V1–V5 as one polish pass.
8. J5, J6, J7, J8–J11 and the code items (Q1–Q3) as capacity allows.

## Not found
No broken authorization, no data-loss paths in the flows exercised, no N+1 queries, no contrast failures, and no slow computation at 200 players.
