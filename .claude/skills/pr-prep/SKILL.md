---
name: pr-prep
description: Generate a PR description from the branch's commits and diff in this project's house style — a short intro, a grouped summary of changes (correctness vs architecture, or by feature), and the verification status. Use when wrapping up a branch for review.
---

# PR prep

Produce a review-ready PR description for the current branch.

## Steps
1. Gather context:
   - `git log --oneline <base>..HEAD` (base is usually `main`) for the commit list.
   - `git diff --stat <base>..HEAD` for the surface area.
   - Read `CLAUDE.md` for any PR conventions; check whether the work spans Malifaux, TOS, or Campaign so you group correctly.
2. Write the description:
   - **One-paragraph intro** — what the branch does and why.
   - **Grouped changes** — use a table or bulleted groups. For rulebook/spec work, split *Correctness* vs *Architecture/quality*. For feature work, group by user-facing feature. Reference files as `path:line` where it helps a reviewer.
   - **Release notes** — a `## Release notes` section, written for players (not developers): a few short bullets in plain words, or `none` for a change players should not hear about (refactors, CI, tests). When the PR deploys, `.github/workflows/deploy-notify.yml` posts this section to Discord. Keep deploy steps, test results and file names out of it.
   - **Verification line** — state the actual gate results (e.g. "Pint/PHPStan/ESLint clean; full suite N passed, M skipped"). Only claim green if you (or the verify-changes skill) actually ran them.
   - **Call-outs** — list anything intentionally deferred or not implemented, honestly.
   - **Help docs** — if the diff touches the Crew Builder, Game Tracker or Tabletop (or any feature with a topic in `resources/js/lib/help/topics/`), say whether its help topic was updated; if it was not, flag it. See `docs/features/help.md`.
3. End the body with the repo's required trailer:
   `🤖 Generated with [Claude Code](https://claude.com/claude-code)`

## Output
Default to printing the markdown in chat for the user to paste. Only run `gh pr create` / push if the user explicitly asks — and branch off `main` first if on the default branch.
