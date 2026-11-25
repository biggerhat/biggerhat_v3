# CI and deploys

Everything GitHub checks, how a merge reaches the site, and the Discord posts. The workflows are in `.github/workflows/`.

## What runs on a pull request (`ci.yml`)

One workflow, three jobs, and an aggregate:

| Job | What it checks | Blocks a merge |
|---|---|---|
| `quality` | `pint --test`, `prettier --check`, `eslint .`, PHPStan, the vue-tsc typecheck (against `scripts/typecheck-baseline.json`), `npm run test:js` (geometry, Fate, deployment, search text, help registry). PHP 8.2 | yes |
| `tests` | the PHP suite (`php artisan test -p`) on **PHP 8.2 (production) and 8.4**, after `npm run build:ssr` (client and server bundles) | yes |
| `mysql` | `migrate:fresh` and the whole suite on a real MySQL 8 | **no, for now** (`continue-on-error`) |
| `audit` | `composer audit` and `npm audit --omit=dev`; the log lists known vulnerabilities | **no** (informational) |
| `ci-pass` | red if `quality` or `tests` failed | **the one check to require** |

The style steps *check*. (`composer lint`, `npm run format` and `npm run lint` fix files, which is right locally and meant a style problem could never fail CI.)
Run the same commands before pushing: `vendor/bin/pint --test`, `npm run format:check`, `npm run lint:check`, `composer stan`, `npm run typecheck`, `npm run test:js`.
`composer prepush` does the PHP side and the JS tests.

**Why 8.2 and MySQL:** production runs PHP 8.2 (`composer.json` pins `config.platform.php`) and MySQL, while the suite runs on 8.4 and in-memory SQLite
locally. The 8.2 job catches a newer-PHP-only function or syntax; the MySQL job catches what SQLite allows and MySQL does not (identifier length,
foreign-key order, case-sensitive collations). Once `mysql` has been green for a while, remove its `continue-on-error` line and add it to `ci-pass`'s `needs`.

## Branch protection (done in GitHub, not in the repo)

Settings > Branches > `main` > require a pull request and status checks: **`ci-pass`** (and "branches up to date"). Because the host deploys on merge, this is
also what stops a red build deploying. If the old checks `linter / quality` and `tests / ci` are still listed as required, remove them: those workflows were
merged into `ci.yml` and the names no longer exist.

## After a merge (`deploy-notify.yml`)

When CI passes on `main`:

1. Polls `GET /version` on the live site (`App\Support\Release`: the commit read from the server's `.git`, nothing else; session-less, never cached) until it shows
   this commit, or a later one (up to 20 minutes). No deploy-script change is needed.
2. Checks `/up` and that `/privacy` is server-rendered (an `<h1>` in the HTML; `docs/ssr.md`).
3. Finds the merged pull request and posts its **`## Release notes`** section to Discord as an embed (title, link, the notes, PR number and commit).
4. If the deploy is not seen, or a check fails, posts a warning and the run goes red.

**Release notes** are written in the PR description (the template has the section; the `pr-prep` skill writes it). For players: a few plain bullets. `none` means
do not announce it (refactors, CI, tests). Left empty, only the title and link are posted. Deploy steps, test results and file names go in the other sections.

Set up once, in Settings > Secrets and variables > Actions:

| Name | Kind | Purpose |
|---|---|---|
| `DISCORD_DEPLOY_WEBHOOK` | secret | the channel that gets the release notes (Discord: channel > Integrations > Webhooks). Unset: the checks run, nothing is posted |
| `DISCORD_ALERT_WEBHOOK` | secret, optional | where a failed or unconfirmed deploy is reported; defaults to the one above |
| `PROD_URL` | variable, optional | the live site; defaults to `https://biggerhat.net` |

Run it by hand for any commit from the Actions tab (`deploy notify` > Run workflow > `sha`).

## Posting a news item to Discord by hand

Admin > Site News: a published post has **Post to Discord** (needs `manage_news`). It sends the title, excerpt (or the start of the post) and link to the channel in
`DISCORD_NEWS_WEBHOOK` (an env var on the server; unset hides the button), with an optional line of the admin's own above. The post remembers when it was sent
(`blog_posts.discord_posted_at`) and the list shows "Posted"; sending again asks for confirmation in the dialog. `App\Services\Discord\NewsBroadcast`.

## Nightly browser flows (`tournament-flows.yml`)

The real-browser pass over the site (`scripts/ui-audit/tournaments`, `docs/ui-audit.md`) runs every night on `main` and opens (or comments on) an issue
"Nightly browser flows are failing" when it fails; screenshots are kept on the run. Also runnable by hand before a release.

## Dependencies

`.github/dependabot.yml`: weekly Composer and npm updates (minor and patch grouped into one PR each) and monthly GitHub Actions updates. Security fixes arrive separately.

## Pull request template

`.github/pull_request_template.md`: Summary, Release notes, Verification, a checklist (help updated, migration run on staging, `composer prepush`), Before deploying.
