# Surfside Tools Development Handbook

This is the primary project handbook for Surfside Tools. It records what the plugin does, how the project is developed, where it is heading, and the decisions that should not depend on chat history.

When planning or beginning work, start here.

## Vision

Surfside Tools is a WordPress plugin that lets church staff manage website content through clear front-end workflows instead of requiring routine access to WordPress administration.

The project began as a simpler way to publish weekly announcements and sermon notes. It has grown into a staff portal that connects the Weekly Update, calendar management, saved locations, publishing tools, and release automation.

The immediate goal is to reduce repetitive work and uncertainty for Surfside Community Fellowship. Features should remain reusable and configurable where practical so the plugin can mature without unnecessary church-specific hard-coding.

## Project DNA

- **Staff first.** Build for church staff, not developers.
- **Front end first.** Staff should remain in the portal whenever practical.
- **Automate when confidence is high.** Detect dates, recurrence, duplicates, and locations rather than requiring repeated entry.
- **Ask only when needed.** When information is missing or ambiguous, prompt clearly instead of guessing.
- **Keep workflows together.** Preserve the user's place and avoid unnecessary navigation.
- **Prefer one clear action.** Reduce clicks without removing review or safety.
- **Make safe behavior easy.** Duplicate protection, review states, and undo should support the normal workflow.
- **End with confirmation.** Staff should know what was saved, published, or skipped.
- **Preserve detail without clutter.** Compact public and staff views may hide detail, but the detail must remain available.
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
└── Settings
    ├── Google Maps integration
    ├── Calendar defaults
    └── Saved Places management

Public Displays
├── Upcoming events
├── This Week
├── Month calendar
└── Event details

Infrastructure
├── GitHub branches and pull requests
├── cPanel Git deployment
├── Automated validation and ZIP builds
└── Automated versioning, changelog, tags, and GitHub Releases
```

The repository root is also the WordPress plugin root. `surfside-tools.php` should remain a small loader, while focused functional modules belong under `includes/`.

## Current Capabilities

### Staff portal

- Front-end dashboard
- Consistent navigation to Weekly Update, Calendar, and Settings
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
- Confidence-based duplicate detection
- Explanation of match confidence
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

### Public calendar displays

- Upcoming event list
- This Week list
- Monthly calendar
- Equal-height desktop weeks
- Compact event cards with `+N more` overflow
- Event-detail modal with venue, address, meeting location, and map information

### Project infrastructure

- GitHub as the source of truth
- Feature branches and pull requests
- cPanel Git deployment
- WordPress-ready ZIP builds
- Automated version bumps
- Automated changelog and GitHub Release generation
- Categorized PR release notes: Added, Improved, and Fixed

## Milestones

### Complete — Foundation

The initial foundation established the plugin structure, Weekly Update import and publishing, public shortcodes, and the front-end staff dashboard.

### Complete — Calendar (1.2.x)

The Calendar milestone established event management, recurrence, weekly and monthly displays, Google Places, venue and meeting-location support, calendar layout polish, cPanel deployment, and automated releases.

### Complete — Productivity (v1.3.0)

The Productivity milestone transformed Weekly Update from a document importer into an intelligent publishing workflow. It added calendar suggestions, recurrence and location detection, duplicate confidence scoring, one-click and batch event creation, undo, Saved Places management, front-end Settings, and polished release automation.

**Retrospective**

- What went well: the feature was developed through small deploy-and-verify pull requests; the secretary workflow remained on one page; duplicate, recurrence, and location intelligence reduced manual work.
- What should improve: avoid long planning loops after approval; diagnose the actual runtime path before stacking multiple compatibility fixes; keep planning information in this handbook rather than chat.
- What we learned: dynamic front-end integrations must enqueue dependencies early; focused screenshots and browser inspection can isolate issues quickly; a milestone should end with both a release and updated project memory.

### Current — Church Portal (1.4)

The next milestone should make the dashboard a useful overview, not merely a launch page.

Planned direction:

- Dashboard widgets for upcoming events and current content
- Content-health indicators for announcements and message notes
- Recent activity and last-updated information
- Clear items needing staff attention
- Homepage or featured-content controls where they provide immediate value
- Continued front-end workflow consistency

These are directions, not a promise that every item ships in 1.4. Work becomes committed when it is defined in an issue or an implementation PR.

### Future — Public Release (2.0)

- Reduce Surfside-specific assumptions where practical
- Add configuration and onboarding guidance
- Complete installation and administrator documentation
- Review security, accessibility, migration, and upgrade behavior
- Establish a stable public-facing feature set

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

### Dashboard and church portal

- Featured Event homepage widget
- Upcoming-events widget
- Announcement and sermon-note status widgets
- Recent activity feed
- Website-content health indicators
- Ministry dashboards
- Homepage content controls
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

**Decision:** Deferred in favor of the Calendar Suggestions panel.

The suggestions panel already handles recurrence, duplicates, locations, confidence, review, batch creation, and completion status. Repeating a simpler action beside each formatted announcement would add clutter and create two competing workflows.

### GitHub epics as another planning layer

**Decision:** Not needed at the current project size.

Milestone sections in this handbook already group related outcomes. Issues and pull requests provide the implementation detail when needed.

### GitHub Projects or Milestones as the only project memory

**Decision:** Do not rely on them as the sole source of truth.

They can be useful visual tools, but this handbook must remain complete because connected tooling may not be able to maintain every GitHub planning feature.

## Decision Log

### 2026-07 — GitHub is the source of truth

Production code changes begin on a GitHub branch and are reviewed through a pull request before deployment.

### 2026-07 — Deploy through cPanel Git

After merge, cPanel uses **Update from Remote** and **Deploy HEAD Commit**. ZIP installation remains a fallback and a release-distribution method.

### 2026-07 — Repository root equals plugin root

The root contains `surfside-tools.php` and `includes/`, simplifying development, packaging, and deployment.

### 2026-07 — Use focused modules

Functional areas belong in focused files under `includes/`; the root plugin file primarily loads modules and defines shared constants.

### 2026-07 — Front-end Settings is the primary staff experience

Routine settings and Saved Places management belong in the staff portal. The WordPress-admin page remains a fallback.

### 2026-07 — Use native Google Places autocomplete

The Weekly Update venue field uses the same native Google Places behavior as Calendar Manager. The API must be enqueued early through WordPress, while dynamic fields may initialize later.

### 2026-07 — Preserve historical event data

Ended events can be hidden from active management views without deleting the underlying event record or past occurrences.

### 2026-07 — Month calendar prioritizes scanability

Desktop weeks use a consistent height, event cards are compact, and additional events use `+N more`; full location details remain available in event details.

### 2026-07 — Releases are milestone-oriented

Routine merged PRs may be deployed without creating an official release. Official versions group meaningful completed work, such as `1.3.0 — Productivity`.

### 2026-07 — Pull requests use categorized release notes

New PRs should include user-facing entries under **Added**, **Improved**, and **Fixed**. Release automation groups these entries and falls back to Summary for older PRs.

## Development Workflow

1. Check this handbook for current milestone context and Nice Ideas.
2. Define the intended outcome and acceptance criteria.
3. Create a focused branch from `main` using `feature/`, `fix/`, or `docs/`.
4. Implement the smallest useful, testable change.
5. Open a pull request using the repository template.
6. Include plain-language Summary, categorized Release Notes, and Testing steps.
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
- Keyboard and mobile behavior remain usable
- Repository-only documentation is excluded from the production ZIP

## Release Process

1. Finish and verify the milestone or grouped release work.
2. Confirm merged PRs contain useful Release Notes.
3. Open GitHub **Actions**.
4. Run **Release Surfside Tools**.
5. Choose patch, minor, major, or a custom version.
6. Verify the workflow completed successfully.
7. Verify the plugin version and new changelog entry on `main`.
8. Verify the Git tag and GitHub Release exist.
9. Verify `surfside-tools.zip` is attached.
10. Deploy the new `main` commit through cPanel when the live site should receive the version bump and changelog commit.

## Documentation Ownership

- **This handbook:** capabilities, milestones, Nice Ideas, decisions, workflow, and project direction
- **CHANGELOG.md:** release history generated for official versions
- **GitHub Releases:** user-facing release packages and notes
- **Pull requests:** implementation details, tests, and release-note source material
- **Issues:** optional detailed planning and acceptance criteria for committed work

Chat is where decisions may be discussed. This repository is where durable decisions live.
