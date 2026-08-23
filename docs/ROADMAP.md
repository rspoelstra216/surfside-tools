# Surfside Tools Roadmap

This roadmap is intentionally concise. Completed implementation detail belongs in the [Changelog](../CHANGELOG.md), GitHub Releases, and merged pull requests; durable architecture and unscheduled ideas live in the [Development Handbook](DEVELOPMENT.md).

## Current release

**Version:** `3.2.0`  
**Current focus:** Surfside mobile app development; Tools integrations and stability as needed

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

## 3.2.0 baseline

- Ministries are centrally managed in Site Settings and shared by website and app.
- Published/Featured states separate editorial readiness from placement in the Serve & Get Involved block.
- The public Ministry Directory is compact, full width, audience-filterable, and provides detail/contact dialogs plus a serving CTA.
- Calendar Manager independently classifies Ministry and Bible Study events; Ministry classification can seed a draft manager record for completion and publishing.
- The mobile API exposes published ministries and resolved contact information while keeping drafts private.
- Giving configuration and push-notification server plumbing are available to support app development.
- Request-wide page-ensure behavior was removed; staff-only work must remain scoped to explicit management pages/actions.

## Current direction

Primary feature development continues in the **Surfside mobile app** from the stable 3.2.0 shared-services baseline.

Surfside Tools remains the shared server-side platform. New Tools work should be added when an app feature needs centralized content, API access, staff management, protected credentials/integration logic, or when a separate website improvement is deliberately scheduled.

No additional website milestone is currently committed.

## Planning rules

- Build focused, testable pull requests and verify them after deployment.
- Avoid request-wide migrations/page-ensure work; scope staff behavior to the page or explicit save action that needs it.
- Watch hosting Resource Usage when runtime hooks, queries, or public-request behavior change.
- Group meaningful completed work into releases rather than treating every PR as a release-level feature.
- Keep the changelog concise at the release/outcome level.
- Preserve detailed implementation and troubleshooting history in merged PRs and GitHub Releases.
- Promote items from the handbook's [Nice Ideas](DEVELOPMENT.md#nice-ideas) section only when intentionally scheduled.

See the [Development Handbook](DEVELOPMENT.md) for architecture and operating guidance and the root [Development status](../DEVELOPMENT.md) for the active baseline.