# App-wide audit (2026-11-13)

Scope: security, dependencies, reliability and operations, code health, UX gaps and feature ideas, across the whole app.
Each finding says how it was checked. **Verified** means I reproduced it or read the code path; **Inferred** means it follows from
what the repo shows but needs a look at production to confirm.

## Fixed in this pass

| #   | Finding                                                                                                                                                                                                                                                                                                                            | Evidence                                                                                                                                                                           |
| --- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| S1  | **Stored XSS through JSON-LD.** `JsonLd.vue` wrote `JSON.stringify(data)` raw into a `<script>`. Breadcrumb data comes from page props, and a tournament name (any signed-in user can create one, 255 chars) is one of them. A name containing a closing script tag ends the block in the server-rendered HTML and injects markup. | Verified: Vue's server renderer turned `{"name":"</script><img src=x onerror=…>"}` into a live `<img>` tag. Fixed by escaping `< > &` and U+2028/9 as `\uXXXX` (still valid JSON). |

## Security

| #   | Sev    | Finding                                                                                                                                                                                                                                                                                                                                                                            | Status                                                                                                       |
| --- | ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| S2  | Medium | **No email verification.** `MustVerifyEmail` is commented out on `User`. Anyone can register with someone else's address, and password-reset and notification mail then goes to the real owner. Tournament invites, organizer adds and Discord-style identity all trust the address.                                                                                               | Verified. Decision needed: enforce verification, or only gate tournament creation and friend requests on it. |
| S3  | Medium | **No security headers from the app.** No CSP, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, or `X-Content-Type-Options` anywhere in `app/`, `bootstrap/` or `config/`. `docs/performance.md` mentions nginx `add_header`s, so they may exist at the proxy.                                                                                                           | Inferred: check the live response headers. A CSP would also have contained S1.                               |
| S4  | Low    | No two-factor sign-in or passkeys. Admin accounts (roles, deletes, super_admin tooling) are protected by a password alone.                                                                                                                                                                                                                                                         | Verified. Worth doing for the `admin.any` roles at least.                                                    |
| S5  | Low    | Registration has no throttle of its own (login is limited in `LoginRequest`). Only password-reset and verify routes have `throttle:6,1`.                                                                                                                                                                                                                                           | Verified in `routes/auth.php`.                                                                               |
| S6  | Low    | `composer audit`: `laravel/framework` (low, Concurrency process output), `firebase/php-jwt` (low), `grpc/grpc` (CA store). `npm audit --omit=dev`: 9 findings, all through `puppeteer`/`puppeteer-core` (`chromium-bidi`, `zod`) and `vite`; `npm audit fix` is available. Puppeteer is a runtime dependency (Browsershot PDFs), so update it deliberately and run the PDF checks. | Verified.                                                                                                    |
| S7  | Info   | Write routes not behind auth are all package or intentional: Telescope and Log Viewer (gated to `super_admin`), `feedback` (throttled), `system/switch`, `broadcasting/auth`, and Laravel's local `storage/{path}` route. `$request->all()/only()` appears only in the two password-reset controllers. Raw SQL is all constant strings or bound parameters.                        | Verified. No action.                                                                                         |
| S8  | Info   | `Auth::login($user)` in `RegisteredUserController` does not remember the new user. Standard behaviour, but a new user who closes the browser is signed out after the 120-minute session.                                                                                                                                                                                           | Verified. Trivial change if wanted.                                                                          |

## Reliability and operations

| #   | Sev    | Finding                                                                                                                                                                                                                        | Status                                                                |
| --- | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------- |
| O1  | High   | **No error tracking.** No Sentry, Flare, Nightwatch or similar in `composer.json`. Production exceptions are only in log files and Telescope (dev). The SSR path 500s that tests cannot catch (see CLAUDE.md) would go unseen. | Verified. Biggest operational gap.                                    |
| O2  | High   | **No database backup job** in `routes/console.php` or `composer.json`. May be done at the host level.                                                                                                                          | Inferred: confirm backups exist and have been restored at least once. |
| O3  | Medium | Housekeeping missing from the scheduler: `queue:prune-failed`, `horizon:snapshot` (Horizon's metrics graphs are empty without it), and pruning of old read `notifications` rows.                                               | Verified. Add three lines to `routes/console.php`.                    |
| O4  | Low    | The tournament time warning (F16) and bot heartbeat checks depend on `schedule:run` every minute. There is no "scheduler is alive" check.                                                                                      | Add a heartbeat alongside the bot one.                                |
| O5  | Low    | `public/test.pdf` (a 2025 PDF-feature leftover) and `public/hot.off` are tracked in git and served publicly.                                                                                                                   | Verified. Delete both; `hot.off` is only a local convention.          |
| O6  | Info   | Mail defaults to `log` in `.env.example`; production needs `resend` plus a key (documented). Confirm production is not still on `log`, since S2 and every notification email depend on it.                                     | Inferred.                                                             |

## Code health

- **Very large files.** `Games/Show.vue` 5.7k lines, `Tools/CrewBuilder/Index.vue` 4.2k, `Search/View.vue` 3.5k, `Campaigns/ArsenalSheet.vue` 3.1k, `Campaigns/Aftermath.vue` 2.0k; `GameController` 2.0k and `CampaignAftermathController` 1.7k on the PHP side. The play-screen crew row extraction showed the pattern works. Next best candidates: the Crew Builder roster list, and the Game Tracker's dialogs.
- **Tests:** 246 test files, about 2,500 tests. Thin areas: Cooperative (2 files; the play tracker does not exist yet), Game (22 files for the biggest controllers). Tournament has 48 files plus 30+ browser flows in CI.
- **Strictness is on:** `Model::shouldBeStrict()` catches lazy loading and mass assignment in dev and tests. No TODO/FIXME debt in code.
- **Dependencies:** `inertia-laravel` and `@inertiajs/vue3` have a new major (3.x); the Tiptap packages are a minor behind. None urgent.
- **No `vue-tsc`.** Templates are only checked by the Vue compiler and ESLint, so prop and type mismatches in templates reach the browser. Installing it and fixing the backlog is the single biggest frontend quality win.

## UX gaps worth fixing

1. **Account data and deletion.** There is a delete-account action, but no "download my data" (crews, collection, games, tournaments). A JSON export is the cheap version and covers privacy requests.
2. **Game Tracker has a log but no undo** for a mis-tapped health, token or activation change. Phones make mis-taps common, and the play screen is now denser.
3. **Offline and flaky-network behaviour.** Only the shell is cached. Game Tracker and tournament scoring need a network round trip per tap, with no queued retry. At a venue with bad signal, taps just fail. A small queue for health, activation and score writes would help, with a visible "offline, will sync" state.
4. **Notifications are in-app and email only.** No web push, so a "your round is paired" or "10 minutes left" notice is missed if the tab is closed. Push fits the existing `TournamentRoundPaired` and `TournamentRoundTimeWarning` notifications.
5. **No localisation** (no `lang/` files). All strings are English, so adding a language later means touching every page.

## Feature ideas, roughly by value for effort

| Idea                                                         | Why                                                                                                               | Builds on                                     |
| ------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| Web push for tournament pairings and clock warnings          | Closed-tab players miss both today                                                                                | Existing notification classes, service worker |
| Crew legality check in tournaments                           | Largest remaining gap in the tournament audit (F10)                                                               | Crew Builder rules, `crew_lists`              |
| Undo and log for the Game Tracker                            | Mis-taps on phones                                                                                                | `GameLog`                                     |
| Queued offline writes for tracker and scoring                | Venues with poor signal                                                                                           | `useGameChannel` poll fallback                |
| "What can I play?" from Collection                           | On the approved roadmap; Collection and crew data already exist                                                   | `CollectionController`, Crew Builder          |
| Match replay / shareable game summary                        | On the roadmap; `GameLog` and `GameTurn` hold the data                                                            | `crewShareImage`, `tournamentResultsImage`    |
| Tournament: round-robin and double elimination, team leagues | Listed as not done in the tournament audit                                                                        | `TournamentStateMachine`, bracket code        |
| Co-operative play tracker                                    | Reference data exists; the tracker does not                                                                       | `docs/features/cooperative-mode.md`           |
| Passkeys / 2FA for admins                                    | S4                                                                                                                | Fortify-style flow                            |
| Public API keys with per-key rate limits                     | `api/v1` is open read-only; a keyed tier would let the Discord bot and third-party tools be monitored and limited | `api_v1.php`                                  |

## Suggested order

1. Done: S1.
2. This week, small: O3 scheduler lines, O5 file removal, S5 registration throttle, S6 `npm audit fix` with a PDF check, S3 response-header check.
3. Decide: S2 email verification (enforce vs gate), O1 error tracker choice, O2 backup confirmation.
4. Then features: web push, crew legality, game undo.
