# Contributing to Surfside Tools

This guide documents the lightweight development process used for Surfside Tools.

## Before starting work

- Check `ROADMAP.md` for milestone context and Nice Ideas.
- Check GitHub Issues for planned work and acceptance criteria.
- Confirm whether the task belongs to the current milestone.
- Create a focused feature branch from `main`.

Recommended branch names:

- `feature/short-description`
- `fix/short-description`
- `docs/short-description`

## Development principles

- Keep pull requests focused on one clear outcome.
- Preserve existing event data and recurrence behavior unless the change explicitly requires a migration.
- Prefer reusable, configurable behavior over unnecessary Surfside-specific hard-coding.
- Keep `surfside-tools.php` small and place functional code in `includes/`.
- Reuse existing rendering and occurrence logic rather than duplicating it.
- Sanitize saved input and escape displayed output.
- Maintain keyboard accessibility for buttons, dialogs, and interactive calendar controls.
- Keep desktop and mobile behavior in mind when changing public layouts.

## Pull requests

A pull request should include:

- A plain-language summary
- The user-visible effect
- Any data or compatibility considerations
- Clear deployment and testing steps
- A linked GitHub Issue when one exists

Small related fixes may be included when they are low risk and directly connected to the PR objective.

## Validation

Before merging, verify as applicable:

- PHP syntax checks pass
- The WordPress plugin remains active
- Existing events still load and save
- Recurring events continue to generate correctly
- Calendar list, week, month, and modal views remain functional
- Desktop and mobile layouts remain usable
- Google Places and saved locations still work
- No repository or documentation files are included in the production plugin package

## Deployment

After merging:

1. Open cPanel Git Version Control.
2. Open the Surfside Tools repository.
3. Select **Update from Remote**.
4. Select **Deploy HEAD Commit**.
5. Test the affected feature on the live site.

## Releases

Official releases are created separately from routine deployments:

1. Open GitHub Actions.
2. Run **Release Surfside Tools**.
3. Choose a patch, minor, major, or custom version.
4. GitHub updates the version and changelog, validates PHP, creates a tag, publishes a GitHub Release, and attaches `surfside-tools.zip`.

Do not run an official release for every small pull request. Group useful completed work into milestone-oriented releases.

## Documentation ownership

- Add unscheduled ideas to `ROADMAP.md` under **Nice Ideas**.
- Create a GitHub Issue when the project commits to building an idea.
- Assign planned issues to the appropriate GitHub Milestone.
- Record durable architectural choices in `DECISIONS.md`.
- Update this guide when the working process changes.
