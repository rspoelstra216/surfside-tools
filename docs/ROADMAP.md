# Surfside Tools Roadmap

This roadmap is intentionally concise. Completed implementation detail belongs in the [Changelog](../CHANGELOG.md), GitHub Releases, and merged pull requests; durable architecture and unscheduled ideas live in the [Development Handbook](DEVELOPMENT.md).

## Current release baseline

**Version:** `3.3.0`  
**Current focus:** Surfside mobile app development; Tools integrations and shared services as needed

## Delivered progression

| Milestone / release | Outcome |
| --- | --- |
| 1–4 / 1.x | Weekly Update, Native Calendar, Google Places, Staff Dashboard |
| 5 / 2.0.0 | Platform Consolidation |
| 6 / 2.1.0 | Dashboard Intelligence |
| 7 / 2.2.0 | Calendar Experience |
| 8 / 2.3.0 | Church Portal |
| 9 / 2.4.0 | Sitewide Information and V2 Foundation |
| 10 / 3.0.0 | V2 Website Experience |
| 3.0.1–3.0.2 | Mobile App Data Bridge and integration plumbing |
| 3.1.0 | Native Connect workflow shared by website and app |
| 3.2.0 | Shared Ministries platform, Calendar classification, app-service expansion, runtime stabilization |
| 3.3.0 | Repository audit closeout, ownership consolidation, runtime/API/auth hardening, CI/deployment hygiene |

## 3.3.0 baseline

3.3.0 establishes the clean post-audit baseline rather than another feature-generation reset.

- Two repository-wide audits were completed, and every production subsystem changed by the cleanup was live-tested before closeout.
- Dead/orphaned and superseded modules were removed; permanent screens now have clearer authoritative owners.
- Staff-page provisioning is centralized and version-gated rather than checked/repaired on ordinary public requests.
- Staff authentication/session handling is consolidated and WordPress credential login is throttled.
- Dashboard, Member Engagement, Church Settings, Ministries, Calendar Manager, and public calendar rendering no longer depend on the known post-render repair layers removed during the audits.
- Calendar Manager avoids the duplicate five-year recurrence correction pass identified in the second audit.
- Public mobile API resources are bounded: Expo registration cannot evict legitimate devices at capacity, and YouVersion passage/version traffic uses server-side caching plus protection of uncached upstream work.
- Pull requests targeting `main` now run PHP/plugin-structure validation before merge.
- cPanel deployment mirrors tracked production directories so files removed from Git are removed from production as well.

The detailed audit record and ownership rules live in [Code Audit Closeout](CODE-AUDIT-CLOSEOUT.md).

## Current staff information architecture

- **Weekly Update** — weekly publishing and calendar suggestions.
- **Calendar Manager** — event management and public calendar data.
- **Manage Website** — website-specific presentation.
- **Manage Mobile App** — Home Experience, Featured Announcement, and Push Notifications.
- **Member Engagement** — Prayer Requests and Volunteer Needs.
- **Church Settings** — shared/infrequent configuration including Surfside Information, Ministries, Contact Routing, and Integrations.

## Current direction

Primary feature development continues in the **Surfside mobile app** from the 3.3.0 clean shared-services baseline.

Surfside Tools remains the shared server-side platform. New Tools work should be added when an app feature needs centralized content, API access, staff management, protected credentials/integration logic, or when a separate website improvement is deliberately scheduled.

No additional website milestone is currently committed.

## Planning rules

- Build focused, testable pull requests and verify them after deployment.
- Prefer subsystem-sized PRs when related work has one clear owner and regression surface; avoid both broad rewrites and unnecessary micro-PR chains.
- Keep one authoritative renderer/owner for permanent fields, layout, persistence, and behavior instead of layering later compatibility mutation over finished output.
- Avoid request-wide migrations/page-ensure work; scope staff behavior to versioned provisioning, the relevant page, or the explicit save action that needs it.
- Prefer server/source-level filtering over emitting data that browser-side cleanup then removes or repairs.
- Bound expensive unauthenticated API work and cache safe shared upstream data where practical.
- Require automated PHP/plugin-structure validation before merging to `main`.
- Keep deployment synchronized with source so deleted repository files do not remain on production.
- Watch hosting Resource Usage when runtime hooks, queries, or public-request behavior change.
- Group meaningful completed work into releases rather than treating every PR as a release-level feature.
- Keep the changelog concise at the release/outcome level.
- Preserve detailed implementation and troubleshooting history in merged PRs and GitHub Releases.
- Promote items from the handbook's [Nice Ideas](DEVELOPMENT.md#nice-ideas) section only when intentionally scheduled.

## Future maintenance trigger

There is **no standing cleanup backlog** after 3.3.0. Run another broad repository audit only after a substantial feature phase, before a major architectural release, when performance symptoms return, or when duplicate/compatibility layers begin accumulating again.

Prayer-request retention remains a product/privacy-policy decision rather than unfinished repository cleanup.

See the [Development Handbook](DEVELOPMENT.md) for architecture and operating guidance and the root [Development status](../DEVELOPMENT.md) for the active baseline.
