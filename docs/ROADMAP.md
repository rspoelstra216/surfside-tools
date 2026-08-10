# Surfside Tools Roadmap

Surfside Tools is a front-end website-management platform for Surfside Community Fellowship. The roadmap keeps completed milestones, the current development focus, and future ideas in one easy-to-scan place.

The detailed project history, durable decisions, and complete Nice Ideas backlog remain in the [Surfside Tools Development Handbook](DEVELOPMENT.md).

## Current release

**Version:** `3.0.0`  
**Current milestone:** Milestone 10 complete; next milestone not yet committed

## Milestone status

| Milestone | Outcome | Release |
| --- | --- | --- |
| 1 | Weekly Update Foundation | 1.x |
| 2 | Native Calendar | 1.x |
| 3 | Google Places | 1.x |
| 4 | Staff Dashboard | 1.x |
| 5 | Platform Consolidation | 2.0.0 |
| 6 | Dashboard Intelligence | 2.1.0 |
| 7 | Calendar Experience | 2.2.0 |
| 8 | Church Portal | 2.3.0 |
| 9 | Sitewide Information and V2 Foundation | 2.4.0 |
| 10 | V2 Website Experience | 3.0.0 |

Completed implementation details are preserved in the [changelog](../CHANGELOG.md), GitHub Releases, and merged pull requests.

## Completed milestone

### Milestone 10 — V2 Website Experience

Version 3.0.0 completes the page-by-page V2 website redesign while preserving independent WordPress editing where appropriate.

Delivered outcomes:

- A consistent blue-led coastal design system across every primary navigation page
- Redesigned Home, Plan Your Visit, Watch Live, Events, Ministries, Staff, Give, and Contact experiences
- Centralized service, location, contact, navigation, streaming, and adult-ministry data
- Dynamic service, contact, ministry, ministry-event, and Watch Live components
- Twitch-aware live status with a local announcement-video fallback while offline
- Consolidated Manage Website navigation for staff
- Shared spacing, animation, button, card, media, and responsive behavior
- Final page-wide checks for headings, widths, links, accessibility basics, and legacy styling

No new milestone is committed yet. The next phase should begin with an explicit scope decision rather than treating post-release ideas as an automatic continuation of Milestone 10.

## Future ideas

Future ideas are preserved in the handbook rather than duplicated here. Review the organized backlog before beginning a new milestone or feature:

- [Nice Ideas](DEVELOPMENT.md#nice-ideas)
- [Rejected or Deferred Ideas](DEVELOPMENT.md#rejected-or-deferred-ideas)

## Development workflow

- Build one focused feature per pull request.
- Merge and verify each pull request before beginning the next.
- Update the README, changelog, roadmap, and handbook when project direction or delivered capability changes.
- Cut releases after a milestone or meaningful group of user-facing improvements is complete.

See the [Development Workflow](DEVELOPMENT.md#development-workflow) for the full process.
