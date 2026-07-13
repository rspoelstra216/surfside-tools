# Surfside Tools Decisions

This document records durable project decisions so future work does not depend on chat history or memory.

## 2026-07 — GitHub is the source of truth

All production code changes begin in GitHub on a feature branch and are reviewed through a pull request before deployment.

## 2026-07 — Deploy through cPanel Git

The live plugin is deployed from the GitHub-backed cPanel repository to:

`/home/surfuqnb/public_html/wp-content/plugins/surfside-tools/`

The standard deployment process is:

1. Merge the pull request into `main`.
2. In cPanel Git Version Control, select **Update from Remote**.
3. Select **Deploy HEAD Commit**.
4. Verify the change on the live site.

ZIP uploads are a fallback, not the primary deployment method.

## 2026-07 — Keep Git metadata outside the public web root

The cPanel repository is stored outside `public_html`. The `.cpanel.yml` deployment recipe copies only the plugin files into WordPress.

## 2026-07 — Repository root equals plugin root

`surfside-tools.php` and `includes/` live at the repository root. This avoids nested plugin folders and simplifies development, packaging, and deployment.

## 2026-07 — Use modular plugin files

New functional areas should be placed in focused modules under `includes/` rather than added to one large plugin file. The root plugin file should primarily define constants and load modules.

## 2026-07 — Milestones guide development

Version numbers identify releases, but milestones define progress. GitHub Milestones track committed work; `ROADMAP.md` tracks direction and unscheduled ideas.

## 2026-07 — Issues require useful acceptance criteria

Planned work should become a GitHub Issue before implementation when practical. Issues should explain the intended outcome and how completion will be verified.

## 2026-07 — Release automation is separate from deployment

GitHub Actions handles version updates, changelog generation, validation, tags, GitHub Releases, and installable ZIPs. cPanel Git remains the live deployment mechanism.

## 2026-07 — Public displays preserve detail without clutter

Calendar month cells prioritize scanability. Full venue, meeting location, street address, and map details remain available in event-detail views even when the month grid uses compact cards.

## 2026-07 — Build reusable features where practical

Surfside is the first installation, but new features should avoid unnecessary church-specific hard-coding when a configurable approach is reasonable and does not complicate the immediate need.
