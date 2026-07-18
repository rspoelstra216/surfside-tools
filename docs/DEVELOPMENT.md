# Surfside Tools Development Handbook

This is the primary project handbook for Surfside Tools. It records what the plugin does, how the project is developed, where it is heading, and the decisions that should not depend on chat history.

When planning or beginning work, start here.

## Vision

Surfside Tools is a WordPress plugin that lets church staff manage website content through clear front-end workflows instead of requiring routine access to WordPress administration.

The project began as a simpler way to publish weekly announcements and sermon notes. It has grown into a Staff Dashboard that connects Weekly Update, calendar management, homepage management, saved locations, settings, publishing tools, and release automation.

The immediate goal is to reduce repetitive work and uncertainty for Surfside Community Fellowship. Features should remain reusable and configurable where practical.

## Project DNA

- **Staff first.** Build for church staff, not developers.
- **Front end first.** Staff should remain in the Staff Dashboard whenever practical.
- **Automate when confidence is high.** Detect dates, recurrence, duplicates, and locations rather than requiring repeated entry.
- **Ask only when needed.** When information is missing or ambiguous, prompt clearly instead of guessing.
- **Keep workflows together.** Preserve the user's place and avoid unnecessary navigation.
- **Prefer one clear action.** Reduce clicks without removing review or safety.
- **Make safe behavior easy.** Duplicate protection, review states, and undo should support the normal workflow.
- **End with confirmation.** Staff should know what was saved, published, or skipped.
- **Preserve detail without clutter.** Compact views may hide detail, but the detail must remain available.
- **Build in small, testable increments.** Deploy and verify focused changes before layering on more complexity.

## Architecture

```text
Staff Dashboard
├── Weekly Update
│   ├── DOCX upload and parsing
│   ├── Announcement review and publishing
│   ├── Message-note review and publishing
│   └── Calendar Suggestions
├── Calendar Manager
│   ├── Event creation and editing
│   ├── Recurrence
│   ├── Saved and Google locations
│   └── Event search and active-event management
├── Manage Homepage
│   └── Homepage carousel photos
└── Settings
    ├── Google Maps integration
    ├── Calendar defaults
    └── Saved Places management

Public Displays
├── Upcoming events
├── This Week
├── Month calendar
├── Event details
├── Homepage photo carousel
└── Church portal

Infrastructure
├── GitHub branches and pull requests
├── cPanel Git deployment
├── Automated validation and ZIP builds
└── Automated versioning, changelog, tags, and GitHub Releases
```

The repository root is also the WordPress plugin root. `surfside-tools.php` should remain a small loader, while focused functional modules belong under `includes/`.

## Current Capabilities

### Staff Dashboard

- Front-end dashboard
- Consistent navigation to Weekly Update, Calendar, Manage Homepage, and Settings
- Front-end Settings page with a WordPress-admin fallback
- Login and capability protection

### Weekly Update

- DOCX upload
- Announcement parsing and editable review
- Message-note parsing and editable review
- Unified publishing workflow
- Publish completion summary

### Calendar Suggestions

- Detect event dates and times from announcements
- Conservative event-title cleanup
- Detect recurring schedules and date ranges
- Detect rooms and meeting locations
- Prompt for a missing primary venue
- Search saved locations and Google Places
- Confidence-based duplicate detection and explanations
- One-click one-time and recurring event creation
- In-page review modal
- Batch creation of selected new events
- Undo newly created events
- Completion tracking without leaving Weekly Update

### Calendar Manager

- Create and edit one-time events
- Daily, weekly, and monthly recurrence
- Repeat-until dates
- Venue, street address, and separate meeting-location fields
- Google Places and saved locations
- Active-event management that hides ended events without deleting history
- Date-range summaries for limited recurring series
- Recently past event display

### Manage Homepage

- Front-end homepage photo management
- Automatic one-time import from the former ACF carousel fields
- Upload multiple new photos
- Replace or remove individual photos
- Drag-and-drop photo ordering
- Existing `[surfside_photo_carousel]` shortcode preserved
- Carousel styles included in Surfside Tools

### Public calendar displays

- Upcoming event list
- This Week list
- Monthly calendar
- Equal-height desktop weeks
- Compact event cards with `+N more` overflow
- Event-detail modal with venue, address, meeting location, and map information

### Church Portal

- Plugin-owned `[surfside_portal]` launcher
- Established nine-destination card hierarchy
- Responsive desktop and mobile presentation
- Message Notes and Announcements dialogs using current weekly content
- This Week events dialog using the native calendar
- Live Slides connection-instructions route
- Prayer Request Contact anchor
- Keyboard, native dialog, scrolling, and reduced-motion support

### Project infrastructure

- GitHub as the source of truth
- Feature branches and pull requests
- cPanel Git deployment
- WordPress-ready ZIP builds
- Automated version bumps
- Automated changelog and GitHub Release generation
- Categorized PR release notes: Added, Improved, and Fixed

## Milestones

### Complete — Milestones 1–8

Surfside Tools has completed Weekly Update Foundation, Native Calendar, Google Places, Staff Dashboard, Platform Consolidation, Dashboard Intelligence, Calendar Experience, and Church Portal.

Version 2.2.0 completed Calendar Experience. Church Portal is complete through PR #83 and is planned for the next minor release.

### Complete — Milestone 8: Church Portal

Milestone 8 moved the public church portal into Surfside Tools through the plugin-owned `[surfside_portal]` shortcode.

#### Delivered

- Portal foundation and nine-destination hierarchy
- Existing card markup and CSS captured inside the plugin
- Two-column desktop and single-column mobile layouts
- Message Notes and Announcements dialogs using current Surfside Tools content
- This Week events dialog using the native calendar
- Prayer Request routing to `/contact/#Contact`
- Live Slides routing through `/live-slides/` for Wi-Fi instructions
- Visible keyboard focus, native Escape behavior, focus restoration, scroll containment, and reduced-motion support

#### Durable portal decisions

- Use `[surfside_portal]` as the single page-level launcher integration.
- Keep the site header, welcome image, and footer outside the shortcode.
- Preserve the prominent Live Slides hierarchy and two-column desktop launcher.
- Reuse existing Surfside Tools content and calendar sources instead of creating duplicate pages.
- Use full-screen dialogs on mobile for Message Notes, Announcements, and This Week's Events.
- Route Live Slides through the public instructions page instead of attempting unreliable IP-based network detection.
- Keep portal markup, styling, and interaction behavior version-controlled in the plugin.

### Current — Milestone 9: Website Management

Expand front-end management to additional high-value website content. Candidate areas include homepage hero content, service times and locations, featured events, ministry highlights, staff directory content, livestream destinations, giving content, footer and contact information, and additional homepage blocks.

Feature order will continue to be defined one focused pull request at a time.

## Nice Ideas

Nice Ideas are intentionally unscheduled. They remain here until the project commits to building them.

### Calendar and events

- Interactive `+N more` day view
- Drag-and-drop calendar editing
- Duplicate an existing event
- Event categories with colors
- Printable monthly calendar
- Add to Apple Calendar and Google Calendar through ICS
- Featured images for events
- Multiple campuses and locations
- Event RSVP or registration
- Ministry color themes
- Mini map preview on public event details
- Better bulk event editing and deletion
- Expanded search and filters

### Weekly Update and productivity

- Create an announcement from an event
- AI suggestions from Weekly Update content
- AI-assisted wording or title refinement with staff approval
- Additional parser confidence explanations
- Saved recurring ministry templates
- More detailed publication history

### Staff Dashboard

- Featured Event homepage widget
- Upcoming-events widget
- Announcement and sermon-note status widgets
- Recent activity feed
- Website-content health indicators
- Ministry dashboards
- Additional homepage content controls
- Digital bulletin tools

### Future ministry tools

- Volunteer management
- Prayer-request workflows
- Forms and follow-up workflows
- Member or contact directory
- Attendance tools
- Additional integrations

## Rejected or Deferred Ideas

### Inline Add to Calendar beside every formatted announcement

**Decision:** Deferred in favor of the Calendar Suggestions panel, which already handles recurrence, duplicates, locations, confidence, review, batch creation, and completion status.

### GitHub epics as another planning layer

**Decision:** Not needed at the current project size. Milestone sections already group related outcomes.

### GitHub Projects or Milestones as the only project memory

**Decision:** Do not rely on them as the sole source of truth. This handbook must remain complete because connected tooling may not maintain every GitHub planning feature.

### Use “Homepage” as a dashboard action label

**Decision:** Use **Manage Homepage** so staff understand the action opens editing tools rather than the public homepage.

## Decision Log

### 2026-07 — GitHub is the source of truth

Production code changes begin on a GitHub branch and are reviewed through a pull request before deployment.

### 2026-07 — Deploy through cPanel Git

After merge, cPanel uses **Update from Remote** and **Deploy HEAD Commit**. ZIP installation remains a fallback and release-distribution method.

### 2026-07 — Use focused modules

Functional areas belong in focused files under `includes/`; the root plugin file primarily loads modules and shared constants.

### 2026-07 — Staff Dashboard is the standard term

The front-end staff workspace is called the **Staff Dashboard**. “Portal” is reserved for a possible future authenticated member experience.

### 2026-07 — Front-end Settings is primary

Routine settings and Saved Places management belong in the Staff Dashboard. WordPress administration remains a fallback.

### 2026-07 — Use native Google Places autocomplete

Weekly Update uses the same native Google Places behavior as Calendar Manager. The API must be enqueued early while dynamic fields may initialize later.

### 2026-07 — Preserve historical event data

Ended events can be hidden from active management without deleting records or past occurrences.

### 2026-07 — Month calendar prioritizes scanability

Desktop weeks use a consistent height, cards are compact, and additional events use `+N more`; full details remain in event views.

### 2026-07 — Releases are milestone-oriented

Routine PRs may be deployed without an official release. Official versions group meaningful completed work.

### 2026-07 — Pull requests use categorized release notes

New PRs include user-facing entries under **Added**, **Improved**, and **Fixed**. Release automation groups them and falls back to Summary for older PRs.

### 2026-07 — Consolidate tools before redesigning the dashboard

The dashboard should reflect the actual completed toolset. Manage Homepage comes first, followed by a snippet audit, then dashboard refinement.

## Development Workflow

1. Check this handbook for current milestone context and Nice Ideas.
2. Define the intended outcome and acceptance criteria.
3. Create a focused branch from `main` using `feature/`, `fix/`, or `docs/`.
4. Implement the smallest useful, testable change.
5. Open a pull request using the repository template.
6. Include Summary, categorized Release Notes, and Testing steps.
7. Merge after review.
8. In cPanel, run **Update from Remote** and **Deploy HEAD Commit**.
9. Verify the affected live workflow.
10. Update this handbook when capability, direction, or a durable decision changes.

## Validation Checklist

Verify as applicable:

- PHP syntax checks pass
- The plugin remains active
- Existing events still load and save
- Recurring events generate correctly
- List, week, month, and modal views work
- Weekly Update parsing and publishing still work
- Google Places and Saved Places still work
- Homepage carousel remains populated during ACF migration
- Homepage uploads, replacements, removals, and ordering persist
- Keyboard and mobile behavior remain usable
- Repository-only documentation is excluded from the production ZIP

## Release Process

1. Finish and verify the milestone or grouped release work.
2. Confirm merged PRs contain useful Release Notes.
3. Open GitHub **Actions**.
4. Run **Release Surfside Tools**.
5. Choose patch, minor, major, or a custom version.
6. Verify the workflow completed successfully.
7. Verify the plugin version and changelog on `main`.
8. Verify the Git tag and GitHub Release.
9. Verify `surfside-tools.zip` is attached.
10. Deploy the new `main` commit through cPanel when the live site should receive the release commit.

## Documentation Ownership

- **This handbook:** capabilities, milestones, Nice Ideas, decisions, workflow, and project direction
- **CHANGELOG.md:** release history generated for official versions
- **GitHub Releases:** user-facing release packages and notes
- **Pull requests:** implementation details, tests, and release-note source material
- **Issues:** optional detailed planning and acceptance criteria for committed work

Chat is where decisions may be discussed. This repository is where durable decisions live.
