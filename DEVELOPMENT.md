# Surfside Tools Development

This file is the concise entry point for active Surfside Tools development. Durable architecture and decision history live in the [Development Handbook](docs/DEVELOPMENT.md); release outcomes live in the [Changelog](CHANGELOG.md).

## Current baseline

**Surfside Tools 3.3.0** — Clean audited shared-services baseline for September 2026.

The website-management platform is mature through Milestones 1–10. The 3.0.x line added the mobile-app data bridge and app-management plumbing; 3.1.0 completed the shared native Connect/contact service; 3.2.0 added the shared Ministries platform, Calendar Ministry/Bible Study classification, Giving/push plumbing, and runtime stabilization. 3.3.0 closes the post-3.2.0 audit cycle and establishes the cleaned, consolidated, hardened repository as the new baseline.

Two repository-wide audits were completed before 3.3.0. The work removed dead/orphaned code, superseded renderers, post-render compatibility layers, duplicate calendar/Google Places behavior, request-wide staff/page-repair work, and hidden ownership dependencies. The follow-up hardening pass centralized staff-page provisioning, consolidated authentication/session ownership, protected public mobile APIs, added PR-time PHP validation, and changed cPanel deployment to remove stale files that no longer exist in Git.

There is no standing repository-cleanup backlog. See the [Code Audit Closeout](docs/CODE-AUDIT-CLOSEOUT.md) for the detailed findings, final verification, and durable ownership rules.

Primary feature development remains in the **Surfside mobile app**. Tools should receive new work when the app needs shared server-side data, management, or integration plumbing, or when a separate website improvement is intentionally scheduled.

## Architecture boundary

### Surfside Tools owns

- front-end staff workflows and centralized church information;
- navigation, header/footer, dynamic public widgets, and complex reusable sections;
- calendar data and public calendar experiences;
- the canonical Ministry Manager and shared ministry publication state;
- Member Engagement tools such as Prayer Requests and Volunteer Needs;
- shared design standards;
- the versioned mobile-app API and app-specific management settings;
- shared Connect/contact routing and website contact handling; and
- server-side integrations and credentials that should not live in the mobile app.

### WordPress pages own

- unique editorial content;
- straightforward page-specific layouts; and
- Gutenberg content that does not need dynamic plugin behavior.

### Mobile app owns

- native app navigation, presentation, interaction, and device behavior;
- consumption of approved public/shared data from Surfside Tools; and
- app features that do not require a server-side WordPress component.

Website and app features should consume the same centralized content and service plumbing whenever practical rather than creating parallel management workflows.

## Current staff information architecture

- **Weekly Update** — weekly content ingestion, review, publishing, and calendar suggestions.
- **Calendar Manager** — event management, recurrence, locations, classifications, and public calendar data.
- **Manage Website** — website-specific presentation such as navigation and homepage media.
- **Manage Mobile App** — Home Experience, Featured Announcement, and Push Notifications.
- **Member Engagement** — Prayer Requests and Volunteer Needs.
- **Church Settings** — shared/infrequently changed information and integrations, including Ministries, Surfside Information, Contact Routing, and Integrations.

The same subsystem should own its permanent fields, markup, persistence, and behavior. Avoid later filters or browser mutation whose only purpose is to repair another module's finished output.

## Current capabilities relevant to app development

- Read-only app configuration and church identity.
- Published event occurrences with validated date ranges and response limits.
- Announcements and formatted message-note HTML.
- Livestream configuration and managed offline Worship media.
- Dashboard-managed app Home hero image, focal position, zoom, and Featured Announcement.
- Managed Giving URL.
- Published Ministries API with audience classification, Featured state, schedule/location/description, and resolved contact data.
- Calendar Manager Ministry and Bible Study classifications; Ministry can seed a draft Ministry Manager record for staff completion/publishing.
- Push-device registration and staff sender, with bounded new-device registration and Expo receipt cleanup.
- Public Bible passage/version API backed by YouVersion with caching and uncached-request protection.
- Native Connect submissions with shared category routing, prayer privacy, pastor preferred contact, validation, and rate limiting.
- Native website contact form using the same routing, protected by Cloudflare Turnstile.

## Runtime stability baseline

3.3.0 preserves and strengthens an important operating rule: **do not run page-creation/ensure checks, migrations, broad staff queries, or other staff-only work on every WordPress request.**

Staff-page provisioning is now centralized and version-gated rather than scattered through ordinary `init` hooks. Calendar Manager no longer performs a second five-year recurrence pass merely to correct its own rendered event list. Public mobile endpoints validate and bound expensive work, and shared upstream Bible data is cached before additional external requests are made.

Runtime-sensitive changes should still be deployed in small steps and checked against hosting Resource Usage.

## Ministries workflow

- **Church Settings → Ministries** is the canonical manager shared by website and app.
- Records support icon, name, schedule, location, description, Kids/Youth/Adults/All Ages audiences, ordering, Featured status, Published status, contact name/email/phone, and a default ministry email fallback.
- New manual ministries start Draft. Calendar Manager's **Ministry** checkbox can create a Draft record if no matching/linked ministry exists.
- Staff complete the record and check **Published** before it appears in public ministry shortcodes or `/surfside/v1/ministries`.
- **Featured Ministry** controls the Serve & Get Involved block; the Ministry Directory remains the broader compact listing.
- **Bible Study** is an independent Calendar Manager classification. A separate Bible Study Manager is not currently required.

## Current direction

Continue mobile-app development from the 3.3.0 clean shared-services baseline. The repository audits are complete; do not continue cleanup for its own sake. Before adding Tools code for an app feature, first determine whether the requirement is truly server-side. Reuse existing shared settings or public URLs when appropriate. Add new API fields or management controls only when they create a durable shared source of truth or keep sensitive/integration logic off the device.

## Milestone history

| Milestone / release | Outcome |
| --- | --- |
| 1–4 / 1.x | Weekly Update, native calendar, Google Places, Staff Dashboard |
| 5 / 2.0.0 | Platform Consolidation |
| 6 / 2.1.0 | Dashboard Intelligence |
| 7 / 2.2.0 | Calendar Experience |
| 8 / 2.3.0 | Church Portal |
| 9 / 2.4.0 | Sitewide Information and V2 Foundation |
| 10 / 3.0.0 | V2 Website Experience |
| 3.0.1–3.0.2 | Mobile App Data Bridge and integration plumbing |
| 3.1.0 | Native Connect workflow |
| 3.2.0 | Shared Ministries, Calendar classification, app-service expansion, runtime stabilization |
| 3.3.0 | Repository audit closeout, ownership consolidation, runtime/API/auth hardening, CI/deployment hygiene |

The changelog, GitHub Releases, and merged pull requests are the implementation history. Completed PR-by-PR detail should not be duplicated here.

## Development workflow

1. Confirm the current objective and architecture boundary.
2. Create a focused `feature/`, `fix/`, or `docs/` branch from `main`.
3. Implement a subsystem-sized, useful, testable change.
4. Open a pull request with Summary, categorized Release Notes, and Testing instructions.
5. Confirm automated PHP/plugin-structure validation passes.
6. Merge after review.
7. Deploy through cPanel Git Version Control.
8. Verify the affected live workflow; watch Resource Usage when runtime behavior changes.
9. Update documentation when capability, direction, or a durable decision changes.

## Documentation ownership

- `README.md` — current product overview
- `DEVELOPMENT.md` — current baseline, architecture boundary, and active direction
- `docs/DEVELOPMENT.md` — durable architecture, decisions, and operating guidance
- `docs/ROADMAP.md` — concise milestone/release roadmap
- `docs/CODE-AUDIT-CLOSEOUT.md` — completed 2026 repository-audit outcomes and cleanup guardrails
- `CHANGELOG.md` — concise release-level outcomes
- GitHub Releases and merged PRs — detailed implementation history and installable artifacts
