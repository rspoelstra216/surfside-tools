# Surfside Tools Roadmap

This roadmap is intentionally concise. Completed implementation detail belongs in the [Changelog](../CHANGELOG.md), GitHub Releases, and merged pull requests; durable architecture and unscheduled ideas live in the [Development Handbook](DEVELOPMENT.md).

## Current release

**Version:** `3.1.0`  
**Current focus:** Surfside mobile app development; Tools integrations as needed

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

## Current direction

The website V2 platform and native Connect workflow are complete. Primary feature development now continues in the **Surfside mobile app**, beginning with **Giving**.

Surfside Tools remains the shared server-side platform. New Tools work should be added when an app feature needs centralized content, API access, staff management, protected credentials/integration logic, or when a separate website improvement is deliberately scheduled.

No additional website milestone is currently committed.

## Planning rules

- Build focused, testable pull requests and verify them after deployment.
- Group meaningful completed work into releases rather than treating every PR as a release-level feature.
- Keep the changelog concise at the release/outcome level.
- Preserve detailed implementation history in merged PRs and GitHub Releases.
- Promote items from the handbook's [Nice Ideas](DEVELOPMENT.md#nice-ideas) section only when intentionally scheduled.

See the [Development Handbook](DEVELOPMENT.md) for architecture and operating guidance and the root [Development status](../DEVELOPMENT.md) for the active baseline.