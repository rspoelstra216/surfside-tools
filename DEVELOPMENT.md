# Surfside Tools Development

This is the concise entry point for current Surfside Tools development. For architecture, detailed capabilities, the Nice Ideas backlog, decision history, validation checklists, and release procedures, see the [Development Handbook](docs/DEVELOPMENT.md).

## Current version

**2.1.0** — released July 2026

Version 2.1 completes Dashboard Intelligence and gives church staff a concise, actionable overview of weekly content, calendar activity, homepage photos, settings health, and recent changes.

## Project vision

Surfside Tools should let church staff perform routine website maintenance through clear front-end workflows without needing WordPress Admin, while giving visitors a useful and accessible public website experience.

## Design principles

- Staff first.
- Front end first.
- Automate when confidence is high.
- Prompt clearly when information is missing.
- Keep related workflows together.
- Preserve review, confirmation, duplicate protection, and undo.
- Build focused, testable pull requests.
- Avoid adding dashboard information that is not clearly actionable.
- Favor accessible public experiences that work well on desktop and mobile.

## Completed milestones

### Milestone 1 — Weekly Update Foundation

- DOCX upload and parsing
- Announcement publishing
- Sermon-note publishing

### Milestone 2 — Native Calendar

- Calendar Manager
- Recurring events
- Public calendar views
- Meeting-location support

### Milestone 3 — Google Places Integration

- Google Places search
- Saved Places
- Address and location reuse

### Milestone 4 — Staff Dashboard

- Front-end management interface
- Weekly Update, Calendar, Manage Homepage, and Settings workflows

### Milestone 5 — Platform Consolidation

**Released as 2.0.0**

- Homepage carousel management
- Front-end Settings
- Reveal and countdown utilities
- Visual CSS controls
- Retirement of routine Simple Calendar, ACF carousel, and Code Snippets dependencies

### Milestone 6 — Dashboard Intelligence

**Released as 2.1.0**

- Website Status cards
- Actionable summary alerts
- Weekly freshness detection
- Thirty-day calendar intelligence
- Homepage and settings health
- Recent Activity
- Context-aware actions
- Desktop and mobile dashboard polish

## Current milestone

### Milestone 7 — Calendar Experience

Improve the public calendar so visitors can explore busy days, print a useful monthly schedule, save events to personal calendars, and discover what is happening at Surfside today.

### Milestone roadmap

- **Interactive Day Details — complete (PRs #52–#66):** accessible crowded-day modal and the final one-event-plus-overflow-card presentation.
- **Printable Monthly Calendar — complete (PRs #67–#68):** dedicated print document, reliable one-page landscape output, and a restrained on-page Print control.
- **Calendar Integration — complete (PRs #69–#72):** individual-occurrence Apple Calendar, Google Calendar, and ICS actions with branded, responsive controls.
- **Event Images — complete (PR #73):** optional Media Library images in Calendar Manager and standard public event details without cluttering compact calendar views.
- **Today at Surfside — in review (PR #74):** adds a `[surfside_today]` shortcode for homepage and other public placements. On service days it includes service information and the current sermon title; on other days it shows today’s events or the next upcoming event.

### Interactive Day Details decisions

- Show one normal event card plus an overflow card on dates with three or more events.
- Display the overflow card as `N more events` and `Tap to view →`.
- Use a modal rather than inline expansion.
- Display all events for the selected date in the modal.
- Keep every event clickable and connected to the standard event-detail modal.
- Support Escape, click-outside closing, keyboard focus containment, and focus return.
- Reserve the dedicated full-day page as a future enhancement.

### Calendar Integration decisions

- Export the individual occurrence the visitor opened, not an entire recurring series.
- Offer Apple Calendar, Google Calendar, and standards-based ICS download actions.
- Include title, occurrence date, time, description, and location details when available.
- Treat events without a start time as all-day events.
- Keep calendar actions inside the standard event-details modal so compact month cells and Day Details remain uncluttered.

### Event Images decisions

- Make the image optional for every event.
- Let staff choose, replace, or remove an image from the front-end Calendar Manager.
- Reuse the WordPress Media Library rather than creating a separate upload system.
- Show images in standard public event-detail modals.
- Keep images out of compact monthly calendar cells and crowded-day lists.
- Use the attachment alt text when available and fall back to the event title.

### Today at Surfside decisions

- Provide a reusable `[surfside_today]` shortcode suitable for the homepage or other public pages.
- Use the site timezone and native recurring-event occurrence engine.
- Treat Saturday at 6:00 PM and Sunday at 9:45 AM as service-day defaults while keeping the schedule filterable.
- Show the current sermon title on service days when one has been published.
- Show all events scheduled today; when there are none on a non-service day, show the next upcoming event.
- Reuse optional event images in the larger Today at Surfside cards without adding them to compact calendar cells.
- Include a configurable link to the full calendar.

### Success criteria

- Busy calendar days remain readable without hiding events.
- Monthly calendar interaction is accessible by keyboard and touch.
- Visitors can print and save calendar information without manual copying.
- Event imagery improves discovery without cluttering compact views.
- The homepage can automatically show what is happening today.
- The milestone creates a reusable foundation for a future dedicated day page.

## Future milestone direction

### Website Management

Website Management remains planned for a later milestone. Potential areas include homepage hero content, featured events, ministry highlights, service information, livestream links, staff information, and other frequently updated content that clearly benefits from a dedicated front-end workflow.

## Release history

| Version | Focus |
| --- | --- |
| 1.0.0 | Initial Surfside Tools release |
| 1.5.0 | Major feature expansion |
| 2.0.0 | Platform Consolidation |
| 2.1.0 | Dashboard Intelligence |

## Development workflow

1. Confirm the current objective here and in the detailed handbook.
2. Create a focused branch from `main` using `feature/`, `fix/`, or `docs/`.
3. Implement the smallest useful and testable change.
4. Open a pull request with Summary, categorized Release Notes, and Testing instructions.
5. Merge after review.
6. Deploy through cPanel Git Version Control.
7. Verify the affected live workflow.
8. Update documentation when capability, direction, or a durable decision changes.

## Documentation ownership

- `README.md` — product overview and documentation links
- `DEVELOPMENT.md` — current version, completed milestones, and active development direction
- `docs/DEVELOPMENT.md` — detailed living handbook
- `CHANGELOG.md` — official release history generated by the release workflow
- GitHub Releases — versioned notes and installable ZIP packages
