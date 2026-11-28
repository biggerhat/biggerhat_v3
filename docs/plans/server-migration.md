# Server migration plan (PHP 8.2 host → PHP 8.5 host)

Status: **planned, not started** (2026-11-27). The current host is end-of-life and will not go past PHP 8.2. A new server on PHP 8.5 is available. This moves the whole site: the large database and a lot of images.

## Open questions (answers change the plan)

1. How big are the database and the production `storage/app/public`, and how long a window is acceptable?
2. Which MySQL version is on each server, and is the DB local, or managed by Forge or a hosting provider?
3. Are we staying on Forge for the new server?
4. Does Cloudflare proxy the site, or is it DNS only?
5. How comfortable are we moving images to object storage (S3 or Cloudflare R2) at the same time?

## Phase 0: make the code 8.5-ready (small PRs, no server needed)

- **CI:** add PHP 8.5 to the matrix in `.github/workflows/ci.yml` (now `8.2`, `8.4`) and fix what breaks.
- **Known item:** `imagedestroy()` is deprecated in 8.5. It is called in `CrewAdminController`, `MiniatureAdminController` and `CreateCombinationImages`, and is safe to remove.
- **Platform pin:** raise `config.platform.php` in `composer.json` (now 8.2.1) only after the 8.5 job is green. Keep the code working on 8.2 until cutover so rolling back stays possible.
- **Packages:** check Laravel 12 and the packages (Horizon, Reverb, Pennant, Browsershot, DomPDF) resolve on 8.5.

## Phase 1: build the new server from the existing docs

- Provision with Forge if staying on it.
- Same PHP extensions as the old server: GD or Imagick, phpredis, intl, mbstring and so on.
- Install Redis, Node, Chrome (Browsershot), nginx (config in `docs/performance.md`) and the Inertia SSR daemon (`docs/ssr.md`).
- Set up Horizon, the Reverb daemon and the scheduler cron.
- Copy `.env` with the **same `APP_KEY`**, or sessions and encrypted data break.
- Get a working copy running on a temporary hostname.

## Phase 2: move the images (sync early and often)

- `rsync -a --partial` for the bulk copy in the background. It resumes if interrupted; repeat it for the last delta at cutover.
- Catalogue images (`packages/`, `characters/`, `upgrades/`) are imported from Wyrd and can probably be re-fetched if lost.
- User content (custom characters and upgrades, uploads) cannot be regenerated. Verify by file count and checksum.
- Recommended: use this move to put images on object storage with a CDN. The `s3` disk is already configured and `FILESYSTEM_DISK` is the switch (124 uses of `Storage::disk('public')` to review). Optional, but it makes every later server move trivial. The catch is a one-time migration of paths and URLs.

## Phase 3: the database (choose by size)

- **Under about 10 GB, or a maintenance window is fine:** `mysqldump --single-transaction` (or `mydumper`) and restore. Rehearse it to learn the real time.
- **Large, or near-zero downtime wanted:** MySQL replication from old to new, let it catch up, then cut over. Alternative: Percona XtraBackup plus binlog catch-up.
- **Version parity:** match the MySQL version and flavour (MySQL or MariaDB), or rehearse the upgrade separately.

## Phase 4: rehearse twice

- Run the full cutover against staging with a stopwatch.
- Verify login, uploads, a queue job, a websocket event, a PDF and card-image generation, SSR (`/privacy` has an `<h1>`) and the scheduler.
- Run the nightly browser flows against the new box.

## Phase 5: cutover (aim for under 30 minutes of read-only or maintenance)

1. Freeze merges. Push-to-deploy is the hazard: a stray merge during the window deploys to the wrong server.
2. Put the old site in maintenance mode (`artisan down`).
3. Final DB sync and final image rsync delta.
4. Point the new server's Forge deploy hook and the GitHub webhook at the new site.
5. Switch traffic. Behind Cloudflare, changing the origin IP takes effect immediately.
6. Smoke test with the rehearsal checks, then `artisan up`.

## Phase 6: rollback window and cleanup

- Keep the old server running read-only for about a week. Rolling back means pointing Cloudflare back, as long as nothing was written to the new DB. Decide that window in advance.
- Then decommission, after a fresh backup and a restore test on the new box.

## Easy to miss

- **Email:** the new IP needs SPF and DKIM, or password-reset mail lands in spam.
- **Certificates:** issue the Let's Encrypt certificate in advance, using a DNS challenge.
- **`/version`:** reads the server's `.git`, and `deploy-notify` depends on it. The new box needs a working checkout.
- **Queued jobs:** drain Horizon before the final DB sync.
- **Bot and webhooks:** check whether the Discord bot heartbeat or any webhook has the old IP allow-listed.
- **Backups:** no backup job exists in the code. Confirm what the old server does and replicate it.
