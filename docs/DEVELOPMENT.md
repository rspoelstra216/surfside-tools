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
- **Preserve safety and review.** Validation, duplicate protection, confirmation, undo, privacy, draft/publish states, and access control should support normal workflows.
- **One authoritative owner.** Permanent fields, markup, persistence, and behavior should live in the subsystem that owns them rather than in later correction layers.
- **Scope runtime work.** Staff-only queries, migrations, page checks, and writes belong on the explicit page/action that needs them, not every WordPress request.
- **Build focused changes.** Subsystem-sized, testable PRs remain the standard even when releases group many PRs.
- **Document outcomes, not iteration noise.** Current docs describe the product; PRs preserve the detailed implementation and troubleshooting trail.

## Architecture

```text
Staff Dashboard
├── Weekly Update
├── Calendar Manager
├── Manage Website
│   ├── Navigation
│   └── Homepage media
├── Manage Mobile App
│   ├── Home Experience
│   ├── Featured Announcement
│   └── Push Notifications
├── Member Engagement
│   ├── Prayer Requests
│   └── Volunteer Needs
└── Church Settings
    ├── Surfside Information
    ├── Ministries
    ├── Contact Routing
    └── Integrations

Public Website
├── WordPress editorial pages
├── Plugin-owned reusable sections and design system
├── Native calendar and Today at Surfside
├── Featured Ministries / Serve & Get Involved
├── Ministry Directory
├── Watch Live
├── Church Portal
└── Native Contact form

Surfside Mobile API
├── Church/app configuration and Giving URL
├── Events
├── Published Ministries
├── Weekly content and formatted notes
├── Livestream/offline media
├── App presentation settings
├── Bible passage/version proxy
├── Push registration
└── Connect submission

Infrastructure
├── GitHub source control and pull requests
├── Pull-request PHP/plugin validation
├── cPanel Git deployment with stale-file removal
└── GitHub Actions versioning, releases, and ZIP artifacts
```

The repository root is the WordPress plugin root. `surfside-tools.php` should remain primarily a module loader; focused functionality belongs under `includes/`.

## Current baseline — 3.3.0

3.3.0 is the clean post-audit shared-services baseline. It preserves the product behavior established through 3.2.0 while consolidating ownership, hardening runtime and public APIs, and making repository validation/deployment more reliable.

Two repository-wide audits were completed before this baseline. The first removed the large layers of dead/orphaned code, superseded renderers, compatibility modules, duplicate calendar/Google Places behavior, and broadly scoped runtime work. The independent second audit then found and resolved remaining staff-page provisioning, settings-integrity, authentication/session, dashboard/Member Engagement, Church Settings/Ministry, calendar, public-API, CI, and deployment issues.

See [Code Audit Closeout](CODE-AUDIT-CLOSEOUT.md) for the detailed audit record and verification criteria.

### Staff and publishing

- Weekly DOCX announcement and sermon-note publishing.
- Calendar suggestions with date/time/recurrence/location detection, duplicate protection, review, batch creation, and undo.
- Native one-time, multi-day, and recurring calendar management.
- Independent Calendar Manager **Ministry** and **Bible Study** classifications.
- Calendar Ministry classification can create a draft Ministry Manager record when one does not already exist.
- Google Places and saved locations.
- Front-end homepage, church-information, navigation, ministry, contact-routing, integrations, push, engagement, and app-management workflows.
- Version-gated centralized staff-page provisioning instead of request-time page repair.

### Ministry Manager

**Church Settings → Ministries** is the canonical source for ongoing ministry information shared by website and app.

Each ministry can store:

- icon/emoji and ministry name;
- usual schedule and location;
- description;
- Kids, Youth, Adults, and All Ages audience selections;
- ordering;
- Featured Ministry status;
- Draft/Published status; and
- optional contact name, email, and phone.

A default ministry contact email can be configured. Ministry-specific email overrides it; blank ministry email falls back to the default. Phone is ministry-specific and never falls back.

New manually added ministries start Draft. Calendar-created ministry records also start Draft. Draft records stay out of the public website and `/surfside/v1/ministries` until staff complete the details and explicitly publish them. Reusing an existing ministry must not silently unpublish it.

Featured and Published are separate concepts: **Published** controls whether a ministry is public at all; **Featured Ministry** controls placement in the Serve & Get Involved block.

### Public website

- Blue-led coastal design system and opt-in Gutenberg standards.
- Plugin-owned responsive header/footer and complex reusable sections.
- Upcoming, weekly, monthly, event-detail, Today at Surfside, print, and calendar-export experiences.
- Church Portal with accessible dialogs and Live Slides routing.
- Twitch-aware Watch Live with visitor-initiated playback, next-service state, and offline announcement media.
- Native contact form using shared category routing and Cloudflare Turnstile.
- Featured Ministries / Serve & Get Involved reads published Featured Ministry records.
- Ministry Directory is full width, audience-filterable, and provides selectable details/contact actions plus a serving CTA.
- Website phone numbers are displayed/copyable rather than forcing desktop calling software; North American numbers are normalized for display.

### Mobile app services

- Versioned `/wp-json/surfside/v1/` API surface.
- Approved church identity, location, services, links, Giving URL, and livestream data.
- Published event occurrences with bounded queries.
- Published Ministry Manager records in manager order with audience, Featured status, schedule/location/description, and resolved contact data.
- Announcements and formatted message notes.
- Managed Home hero image, focal position, zoom, and Featured Announcement.
- Managed offline Worship media.
- Push-device registration/server sender with bounded new-device registration and stale-token/receipt cleanup.
- Public Bible version/passage proxy backed by YouVersion, with server-side caching and protection of uncached upstream work.
- Validated Connect/contact submission with shared category routing.

Administrative settings, credentials, draft ministries, push tokens, and unrelated internal data must remain private unless an authenticated administrative endpoint is intentionally designed.

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

## Runtime and migration rule

The durable production constraint remains: **do not attach page-ensure, migration polling, broad staff queries, or staff-only rendering work to global request hooks unless the work is truly required on every request.**

Prefer:

- direct shortcode registration;
- `rest_api_init` for REST routes;
- explicit manager-page rendering;
- explicit POST/save handlers;
- version-gated provisioning/migrations; and
- narrowly scoped work on the page/action that needs it.

Avoid using ordinary `init` as a convenient place to repeatedly check/create staff pages. Production testing showed that global page-ensure work could drive sustained CPU/database load. The 3.3.0 baseline centralizes staff-page provisioning and removes the known request-time repair paths.

Runtime-sensitive changes should be deployed in small steps and checked against hosting Resource Usage.

## Public API rule

Intentionally public endpoints still need resource boundaries.

- Validate and sanitize every public input.
- Bound result sizes/date ranges and expensive work.
- Keep credentials and administrative data server-side.
- Cache safe shared upstream responses where practical.
- Apply rate limiting to expensive unauthenticated work where abuse could consume quota or displace legitimate state.
- Existing legitimate client refresh/update paths should not be needlessly throttled when they do not create new resource pressure.

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
- Keep **Manage Website**, **Manage Mobile App**, **Member Engagement**, and **Church Settings** distinct according to ownership.
- Keep Ministries in Church Settings because ministry records are shared website/app content.
- Keep Bible Study as an independent Calendar classification; do not create a separate manager until there is a real management need beyond the calendar.
- Use draft/publish state when an automated workflow creates an incomplete public-content record.
- Expose only approved published data through versioned public endpoints.
- Use shared contact categories/routing for both app and website; keep recipient addresses and Turnstile secrets server-side.

## Authentication and access rule

Staff authentication may use approved Google/Firebase or WordPress credential paths, but Tools authorization must have one authoritative role/permission model rather than parallel generations. Custom Tools sessions must be signed, bounded, and cleared with normal logout behavior. Public credential endpoints must include attempt throttling.

## Repository and deployment rule

- Pull requests targeting `main` must pass PHP syntax/plugin-structure validation before merge.
- Packaging remains a post-merge/manual concern rather than producing ZIP artifacts for every PR.
- cPanel deployment must mirror tracked production directories, including deletion of files removed from Git.
- Repository-only documentation, scripts, and workflow files remain excluded from the production plugin ZIP.

## Current development direction

Primary feature development continues in the **Surfside mobile app** from the 3.3.0 clean shared-services baseline. Surfside Tools remains supporting infrastructure: add or modify Tools functionality only when the app needs shared data, a staff-managed source of truth, protected server-side integration, or when a separate website improvement is deliberately scheduled.

The next app feature should be evaluated against the architecture boundary before adding new WordPress code.

## Nice Ideas

These remain unscheduled until intentionally promoted into active work.

- Drag-and-drop calendar editing and event duplication.
- Expanded event categories, filters, bulk editing, RSVP/registration, and ministry presentation options.
- A separate Bible Study Manager only if calendar-managed studies eventually need durable non-event metadata.
- Additional Weekly Update automation and staff-approved AI assistance.
- Expanded volunteer, prayer, follow-up, directory, attendance, and other ministry-management workflows.
- Additional dashboard intelligence only where it creates a clear staff action.
- Push-notification segmentation, scheduling, history, and additional delivery tooling after the basic app/device workflow is established.
- A deliberate prayer-request retention policy, if Surfside wants automatic deletion/aging of archived personal data.

## Development workflow

1. Confirm the objective and whether it belongs in Tools.
2. Create a focused branch from `main` using `feature/`, `fix/`, or `docs/`.
3. Implement a subsystem-sized, useful, testable change.
4. Open a pull request with Summary, categorized Release Notes, and Testing instructions.
5. Confirm the automated PHP/plugin-structure validation passes.
6. Merge after review.
7. In cPanel, run **Update from Remote** and **Deploy HEAD Commit**.
8. Verify the affected live workflow; monitor Resource Usage for runtime-sensitive changes.
9. Update durable documentation when capability, architecture, or direction changes.

## Validation checklist

Verify as applicable:

- PHP syntax and automated checks pass before merge.
- The plugin remains active after deployment.
- Existing weekly publishing and calendar workflows still function.
- Mobile API responses remain backward-compatible unless a deliberate version change is made.
- Draft/private records are excluded from public endpoints and shortcodes.
- Public forms and endpoints validate and protect server-side operations.
- Keyboard, mobile, responsive, and reduced-motion behavior remain usable.
- Administrative data and credentials are not exposed through public endpoints.
- Runtime changes do not create sustained hosting CPU/database load.
- Deleted production files are removed by deployment rather than left stranded on the server.
- Repository-only documentation remains excluded from the production ZIP where intended.

## Release process

1. Finish and verify the grouped release work.
2. Roll current documentation forward to the intended release baseline.
3. Confirm merged PRs contain useful release-note source material.
4. Run **Release Surfside Tools** in GitHub Actions with the intended version increment.
5. Verify the workflow, plugin version, tag, GitHub Release, changelog update, and attached `surfside-tools.zip`.
6. Deploy the release commit through cPanel when appropriate.
7. After a release, consolidate `CHANGELOG.md` to release-level outcomes when generated notes are overly granular.

## Documentation ownership

- **README.md:** current product overview and navigation.
- **Root DEVELOPMENT.md:** current baseline, architecture boundary, and active direction.
- **This handbook:** durable architecture, decisions, workflow, and intentionally unscheduled ideas.
- **ROADMAP.md:** concise release/milestone progression and current focus.
- **CODE-AUDIT-CLOSEOUT.md:** completed 2026 audit findings, fixes, and cleanup guardrails.
- **CHANGELOG.md:** concise release-level outcomes.
- **GitHub Releases:** versioned artifacts and generated release notes.
- **Merged pull requests:** detailed implementation, iteration, testing, and troubleshooting history.

Chat is where decisions may be discussed. The repository is where durable decisions live.
