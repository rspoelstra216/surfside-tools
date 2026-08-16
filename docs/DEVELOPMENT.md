# Surfside Tools Development Handbook

This handbook records durable architecture, operating principles, and decisions for Surfside Tools. It intentionally does **not** repeat PR-by-PR implementation history; use the [Changelog](../CHANGELOG.md), GitHub Releases, and merged pull requests for that detail.

## Vision

Surfside Tools is the WordPress platform behind Surfside Community Fellowship's website and shared mobile-app services. It should let church staff perform routine work through clear front-end workflows while keeping shared content, configuration, and server-side integrations centralized.

## Project DNA

- **Staff first.** Build workflows for church staff, not developers.
- **Front end first.** Routine maintenance should not require WordPress Admin.
- **One source of truth.** Website and app features should reuse centralized content and services whenever practical.
- **Automate when confidence is high.** Detect dates, recurrence, duplicates, locations, and reusable settings rather than requiring repeated entry.
- **Ask when needed.** Do not guess when important information is missing or ambiguous.
- **Keep workflows together.** Avoid unnecessary navigation and duplicate management surfaces.
- **Preserve safety and review.** Validation, duplicate protection, confirmation, undo, privacy, and access control should support normal workflows.
- **Build focused changes.** Small, testable PRs remain the standard even when releases group many PRs.
- **Document outcomes, not iteration noise.** Current docs describe the product; PRs preserve the detailed implementation trail.

## Architecture

```text
Staff Dashboard
├── Weekly Update
├── Calendar Manager
├── Manage Website
│   ├── Surfside Information
│   ├── Homepage media
│   ├── Navigation
│   ├── Streaming
│   ├── Ministries
│   ├── Contact routing
│   └── Settings
└── Manage Mobile App
    └── App-specific presentation settings

Public Website
├── WordPress editorial pages
├── Plugin-owned reusable sections and design system
├── Native calendar and Today at Surfside
├── Watch Live
├── Church Portal
└── Native Contact form

Surfside Mobile API
├── Church/app configuration
├── Events
├── Weekly content and formatted notes
├── Livestream/offline media
├── App presentation settings
└── Connect submission

Infrastructure
├── GitHub source control and pull requests
├── cPanel Git deployment
└── GitHub Actions validation, versioning, releases, and ZIP artifacts
```

The repository root is the WordPress plugin root. `surfside-tools.php` should remain primarily a module loader; focused functionality belongs under `includes/`.

## Current baseline — 3.1.0

The platform is mature through the V2 Website Experience and the first mobile-app integrations.

### Staff and publishing

- Weekly DOCX announcement and sermon-note publishing.
- Calendar suggestions with date/time/recurrence/location detection, duplicate protection, review, batch creation, and undo.
- Native one-time, multi-day, and recurring calendar management.
- Google Places and saved locations.
- Front-end homepage, site information, navigation, streaming, ministry, contact-routing, and app-management workflows.

### Public website

- Blue-led coastal design system and opt-in Gutenberg standards.
- Plugin-owned responsive header/footer and complex reusable sections.
- Upcoming, weekly, monthly, event-detail, Today at Surfside, print, and calendar-export experiences.
- Church Portal with accessible dialogs and Live Slides routing.
- Twitch-aware Watch Live with visitor-initiated playback, next-service state, and offline announcement media.
- Native contact form using shared category routing and Cloudflare Turnstile.

### Mobile app services

- Versioned `/wp-json/surfside/v1/` API surface.
- Approved church identity, location, services, links, and livestream data.
- Published event occurrences with bounded queries.
- Announcements and formatted message notes.
- Managed Home hero image, focal position, and zoom.
- Managed offline Worship media.
- Validated Connect/contact submission with shared category routing.

Administrative settings, credentials, drafts, and unrelated internal data must remain private unless an authenticated administrative endpoint is intentionally designed.

## Architecture boundary

### Keep in Surfside Tools when

- the value is shared by website and app;
- staff need a durable management interface;
- the app needs server-side validation, mail, credentials, or protected integration logic;
- the content is dynamic and reused in multiple public experiences; or
- a complex Gutenberg layout has proven unreliable to maintain manually.

### Keep in WordPress page content when

- the content is unique to one page;
- it is straightforward editorial copy/media; and
- Gutenberg can maintain it reliably without custom behavior.

### Keep in the mobile app when

- the behavior is native navigation, presentation, orientation, interaction, or device behavior; and
- no shared server-side source of truth or protected integration is required.

Surfside Tools is not intended to become a general-purpose page builder or a duplicate CMS for app-only copies of shared church content.

## Durable public-experience decisions

- Use Surfside Information as the canonical source for identity, logo, contact, location, services, navigation, and social destinations.
- Store internal navigation destinations by page ID; use custom URLs for anchored, seasonal, or external links.
- Keep substantial reusable markup, behavior, and CSS in version-controlled plugin modules rather than page-specific Custom HTML/CSS.
- Keep public-page styling opt-in and Gutenberg-compatible.
- Keep the public header opaque white, navigation flat, and the active-page treatment understated; reserve the prominent red treatment for Live Now.
- Detect Twitch live status automatically but require a visitor gesture for reliable unmuted playback.
- Treat multi-day events as inclusive date ranges distinct from recurrence.
- Keep compact calendar views scan-friendly while preserving full details in event views.
- Treat Today at Surfside as dynamic output and keep the compact homepage variant visually transparent.
- Keep the Church Portal mobile-focused and use accessible dialogs for embedded weekly/event content.
- Use one Manage Website entry point for related website-management areas rather than duplicate dashboard actions.
- Keep Manage Mobile App separate for settings that are truly app-specific.
- Expose only approved data through versioned public endpoints.
- Use shared contact categories/routing for both app and website; keep recipient addresses and Turnstile secrets server-side.

## Current development direction

Primary feature development is now in the **Surfside mobile app**, beginning with **Giving**. Surfside Tools returns to a supporting role: add or modify Tools functionality only when the app needs shared data, a staff-managed source of truth, protected server-side integration, or when a separate website improvement is deliberately scheduled.

The next app feature should be evaluated against the architecture boundary before adding new WordPress code.

## Nice Ideas

These remain unscheduled until intentionally promoted into active work.

- Drag-and-drop calendar editing and event duplication.
- Expanded event categories, filters, bulk editing, RSVP/registration, and ministry presentation options.
- Additional Weekly Update automation and staff-approved AI assistance.
- Volunteer, prayer, follow-up, directory, attendance, and other ministry-management workflows.
- Additional dashboard intelligence only where it creates a clear staff action.
- Push-notification management when the mobile app reaches that phase.

## Development workflow

1. Confirm the objective and whether it belongs in Tools.
2. Create a focused branch from `main` using `feature/`, `fix/`, or `docs/`.
3. Implement the smallest useful, testable change.
4. Open a pull request with Summary, categorized Release Notes, and Testing instructions.
5. Merge after review.
6. In cPanel, run **Update from Remote** and **Deploy HEAD Commit**.
7. Verify the affected live workflow.
8. Update durable documentation when capability, architecture, or direction changes.

## Validation checklist

Verify as applicable:

- PHP syntax and automated checks pass.
- The plugin remains active after deployment.
- Existing weekly publishing and calendar workflows still function.
- Mobile API responses remain backward-compatible unless a deliberate version change is made.
- Public forms validate and protect server-side operations.
- Keyboard, mobile, responsive, and reduced-motion behavior remain usable.
- Administrative data and credentials are not exposed through public endpoints.
- Repository-only documentation remains excluded from the production ZIP where intended.

## Release process

1. Finish and verify the grouped release work.
2. Confirm merged PRs contain useful release-note source material.
3. Run **Release Surfside Tools** in GitHub Actions with the intended version increment.
4. Verify the workflow, plugin version, tag, GitHub Release, changelog update, and attached `surfside-tools.zip`.
5. Deploy the release commit through cPanel when appropriate.
6. After a release, consolidate `CHANGELOG.md` to release-level outcomes when generated notes are overly granular.

## Documentation ownership

- **README.md:** current product overview and navigation.
- **Root DEVELOPMENT.md:** current baseline, architecture boundary, and active direction.
- **This handbook:** durable architecture, decisions, workflow, and intentionally unscheduled ideas.
- **ROADMAP.md:** concise release/milestone progression and current focus.
- **CHANGELOG.md:** concise release-level outcomes.
- **GitHub Releases:** versioned artifacts and generated release notes.
- **Merged pull requests:** detailed implementation, iteration, testing, and troubleshooting history.

Chat is where decisions may be discussed. The repository is where durable decisions live.