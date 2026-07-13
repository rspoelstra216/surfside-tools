# Surfside Tools Roadmap

Surfside Tools development is organized around milestones rather than isolated version numbers. Version tags remain useful for releases, but milestone progress is the primary measure of development.

## Milestone 1.2.x — Calendar

### Completed

- Event manager
- Recurrence
- Month view
- Weekly view
- Google Places connection
- Venue and street-address support
- Separate meeting-location field
- Public meeting-location display
- Equal-height month-grid weeks
- Compact month-view event cards
- `+N more` overflow indicator
- cPanel Git deployment workflow
- Automatic version bumping
- Automatic release ZIP creation
- Automatic GitHub Releases
- Changelog generation

### Remaining

- Make `+N more` interactive so visitors can view every event for the date
- Improve generated changelog and release-note wording
- Confirm calendar presentation across desktop, tablet, and mobile
- Improve event search and filters
- Complete final Google Places refinements

## Milestone 1.3 — Productivity

- Announcement-to-calendar suggestions
- One-click event creation
- Better event management
- Search and filter improvements
- Duplicate-event detection
- Smarter recurring-event workflows
- Dashboard workflow improvements

## Milestone 1.4 — Church Portal

- Homepage widgets
- Dashboard improvements
- Additional integrations
- Secretary workflow improvements
- Ministry-facing tools

## Milestone 2.0 — Public Release

- Remove Surfside-specific assumptions where practical
- Add configuration and onboarding guidance
- Complete installation and administrator documentation
- Establish a stable public-facing feature set
- Review security, accessibility, and upgrade behavior

## Nice Ideas

These ideas are intentionally unscheduled. An idea moves into a GitHub Issue and milestone when the project commits to building it.

### Calendar and events

- Google Maps preview in the event editor
- Drag-and-drop calendar editing
- Duplicate an existing event
- Event categories with colors
- Printable monthly calendar
- Add to Apple Calendar and Google Calendar through ICS
- Featured images for events
- Multiple campus and location support
- Event RSVP or registration
- Ministry color themes
- Mini map preview on public event details

### Productivity

- Create an announcement from an event
- AI suggestions from the Weekly Update
- Detect dates, times, venues, and recurrence from announcement text
- Duplicate-event warnings
- Bulk edit or delete events

### Church portal

- Featured Event homepage widget
- Ministry dashboards
- Volunteer management
- Prayer-request workflows
- Digital bulletin tools
- Additional homepage widgets

## How work moves through the project

1. **Nice Idea** — Captured here without committing to a schedule.
2. **GitHub Issue** — Defined work with acceptance criteria.
3. **GitHub Milestone** — Committed work assigned to a larger goal.
4. **Pull Request** — Implementation and review.
5. **Deployment** — cPanel updates from GitHub and deploys the HEAD commit.
6. **Release** — GitHub Actions creates the version, changelog, tag, release, and installable ZIP.

## Repository as project memory

- GitHub Milestones track committed outcomes.
- GitHub Issues track individual tasks and acceptance criteria.
- This roadmap tracks direction and unscheduled ideas.
- `DECISIONS.md` records architectural decisions and their reasoning.
- `CONTRIBUTING.md` records the working process and development conventions.
