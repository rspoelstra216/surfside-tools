# Surfside Tools Roadmap

Surfside Tools is a front-end website-management platform for Surfside Community Fellowship. The roadmap keeps completed milestones, the current development focus, and future ideas in one easy-to-scan place.

The detailed project history, durable decisions, and complete Nice Ideas backlog remain in the [Surfside Tools Development Handbook](DEVELOPMENT.md).

## Current release

**Version:** `2.4.0`  
**Current milestone:** Milestone 10 — V2 Website Experience

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
| 10 | V2 Website Experience | In progress |

Completed implementation details are preserved in the [changelog](../CHANGELOG.md), GitHub Releases, and merged pull requests.

## Current milestone

### Milestone 10 — V2 Website Experience

Apply the V2 foundation to the public website while keeping individual WordPress pages independently editable.

Completed foundation:

- Central Surfside Information and weekly service schedule
- Blue-led coastal design tokens and restored replaceable logo
- Plugin-owned footer and sticky responsive header
- Ordered navigation shared by the header and footer
- Accessible current-page and livestream navigation states
- Cache-resilient header and countdown behavior

Next phase: audit public pages together, prioritize the most visible inconsistencies, and add reusable plugin styles or widgets only where they provide clear sitewide value.

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
