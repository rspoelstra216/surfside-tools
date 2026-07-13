# Surfside Tools

Custom WordPress tools for Surfside Community Fellowship.

## Repository structure

The repository root is the WordPress plugin root.

- `surfside-tools.php` — plugin entry point
- `includes/` — functional modules
- `docs/` — roadmap, architectural decisions, and contribution process
- `.github/workflows/` — build and release automation
- `.cpanel.yml` — cPanel deployment recipe

## Project documentation

- [Roadmap and Nice Ideas](docs/ROADMAP.md)
- [Architecture and project decisions](docs/DECISIONS.md)
- [Development and deployment process](docs/CONTRIBUTING.md)
- [Release changelog](CHANGELOG.md)

GitHub Milestones track committed outcomes. GitHub Issues track individual tasks. The roadmap preserves future ideas that are not yet scheduled.

## Included tools

- Weekly Update DOCX importer
- Announcements parser
- Sermon notes parser
- Unified weekly update workflow
- Front-end staff dashboard pages
- Calendar Manager and recurring events
- Google Places and saved locations
- Public event list, weekly, and monthly displays

## Shortcodes

- `[surfside_weekly_update]`
- `[surfside_tools_announcements]`
- `[surfside_tools_message]`
- `[surfside_staff_dashboard]`
- `[surfside_staff_weekly_update]`
- `[surfside_staff_calendar]`
- `[surfside_tools_upcoming_events]`
- `[surfside_tools_calendar]`
- `[surfside_events]`
- `[surfside_this_week]`
- `[surfside_month_calendar]`

## Staff URLs

- `/dashboard`
- `/dashboard/weekly-update`
- `/dashboard/calendar`

## Deployment

The live plugin is deployed through cPanel Git Version Control after pull requests are merged into `main`. See [CONTRIBUTING.md](docs/CONTRIBUTING.md) for the complete process.
