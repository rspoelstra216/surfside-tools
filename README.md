# Surfside Tools

Custom WordPress tools for Surfside Community Fellowship.

## Repository structure

The repository root is the WordPress plugin root.

- `surfside-tools.php` — plugin entry point
- `includes/` — functional modules
- `docs/` — project handbook and supporting documentation
- `.github/workflows/` — build and release automation
- `.cpanel.yml` — cPanel deployment recipe

## Project documentation

Start with the [Surfside Tools Development Handbook](docs/DEVELOPMENT.md). It is the living source of truth for:

- Current capabilities
- Completed and upcoming milestones
- The organized Nice Ideas backlog
- Architecture and design principles
- Durable project decisions
- Development, deployment, and release processes

Additional references:

- [Release changelog](CHANGELOG.md)
- [Roadmap compatibility link](docs/ROADMAP.md)
- [Decisions compatibility link](docs/DECISIONS.md)
- [Contributing compatibility link](docs/CONTRIBUTING.md)

## Included tools

- Weekly Update DOCX importer
- Announcements parser
- Sermon notes parser
- Unified weekly update workflow
- Front-end Staff Dashboard pages
- Manage Homepage photo workflow and public carousel
- Calendar Manager and recurring events
- Calendar suggestions, recurrence, location, and duplicate detection
- Google Places and saved locations
- Front-end Settings and Saved Places management
- Public event list, weekly, and monthly displays
- Automated builds and releases

## Shortcodes

- `[surfside_weekly_update]`
- `[surfside_tools_announcements]`
- `[surfside_tools_message]`
- `[surfside_staff_dashboard]`
- `[surfside_staff_weekly_update]`
- `[surfside_staff_calendar]`
- `[surfside_staff_homepage]`
- `[surfside_staff_settings]`
- `[surfside_photo_carousel]`
- `[surfside_tools_upcoming_events]`
- `[surfside_tools_calendar]`
- `[surfside_events]`
- `[surfside_this_week]`
- `[surfside_month_calendar]`

## Staff URLs

- `/dashboard`
- `/dashboard/weekly-update`
- `/dashboard/calendar`
- `/dashboard/homepage`
- `/dashboard/settings`

## Deployment

The live plugin is deployed through cPanel Git Version Control after pull requests are merged into `main`. See the [Development Handbook](docs/DEVELOPMENT.md#development-workflow) for the complete workflow.
