# Surfside Tools Roadmap

Surfside Tools is a front-end website-management platform for Surfside Community Fellowship. The roadmap keeps completed milestones, the current development focus, and future ideas in one easy-to-scan place.

The detailed project history, durable decisions, and complete Nice Ideas backlog remain in the [Surfside Tools Development Handbook](DEVELOPMENT.md).

## Current release

**Version:** `3.1.0`  
**Current focus:** Website V2 and native Connect complete; primary development returned to the Surfside mobile app

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
| — | Mobile App Data Bridge | 3.0.1–3.0.2 |
| — | Native Connect Workflow | 3.1.0 |

Completed implementation details are preserved in the [changelog](../CHANGELOG.md), GitHub Releases, and merged pull requests.

## Completed platform work

### Milestone 10 — V2 Website Experience

Version 3.0.0 completes the page-by-page V2 website redesign while preserving independent WordPress editing where appropriate.

Delivered outcomes include the blue-led coastal design system across every primary navigation page; centralized service, location, contact, navigation, streaming, and adult-ministry data; dynamic reusable components; Twitch-aware live/offline behavior; consolidated Manage Website navigation; and final page-wide accessibility and consistency checks.

### Versions 3.0.1–3.0.2 — Mobile App Data Bridge

These patches establish and refine the versioned API bridge used by the Surfside mobile app. Approved church, service, livestream, weekly-content, link, event, sermon-note formatting, and app-presentation data remain sourced from WordPress and Surfside Tools rather than a separate mobile content system.

### Version 3.1.0 — Native Connect Workflow

Version 3.1.0 completes a shared contact system for the mobile app and public website:

- Category-based recipient routing managed through Surfside Tools
- Successful app-to-WordPress-to-email submission
- Native website `[surfside_contact_form]` using the same routing configuration
- Prayer-team sharing and pastor preferred-contact handling
- Cloudflare Turnstile with server-side verification
- Nonce, honeypot, rate limiting, and input validation
- Forminator removed after production verification

## Current direction

Primary feature development returns to the Surfside mobile app, beginning with Giving functionality. Surfside Tools remains the shared server-side platform and should gain new functionality when an app feature needs centralized content, API access, management controls, or integration plumbing.

No additional website milestone is currently committed. New website work should begin with an explicit scope decision rather than automatically extending the completed V2 work.

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
