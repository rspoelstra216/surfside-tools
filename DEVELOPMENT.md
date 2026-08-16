# Surfside Tools Development

This file is the concise entry point for active Surfside Tools development. Durable architecture and decision history live in the [Development Handbook](docs/DEVELOPMENT.md); release outcomes live in the [Changelog](CHANGELOG.md).

## Current baseline

**Surfside Tools 3.1.0** — Native Connect released August 16, 2026.

The website-management platform is mature through Milestones 1–10. The 3.0.x line added the mobile-app data bridge and app-management plumbing; 3.1.0 completed the shared native Connect/contact service.

Primary feature development is now in the **Surfside mobile app**. Tools should receive new work when the app needs shared server-side data, management, or integration plumbing, or when a separate website improvement is intentionally scheduled.

## Architecture boundary

### Surfside Tools owns

- front-end staff workflows and centralized site information;
- navigation, header/footer, dynamic public widgets, and complex reusable sections;
- calendar data and public calendar experiences;
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

## Current capabilities relevant to app development

- Read-only app configuration and church identity.
- Published event occurrences with validated date ranges and response limits.
- Announcements and formatted message-note HTML.
- Livestream configuration and managed offline Worship media.
- Dashboard-managed app Home hero image, focal position, and zoom.
- Native Connect submissions with shared category routing, prayer privacy, pastor preferred contact, validation, and rate limiting.
- Native website contact form using the same routing, protected by Cloudflare Turnstile.

## Current direction

**Next app feature: Giving.**

Before adding Tools code for an app feature, first determine whether the requirement is truly server-side. Reuse existing website settings or public URLs when appropriate. Add new API fields or management controls only when they create a durable shared source of truth or keep sensitive/integration logic off the device.

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

The changelog, GitHub Releases, and merged pull requests are the implementation history. Completed PR-by-PR detail should not be duplicated here.

## Development workflow

1. Confirm the current objective and architecture boundary.
2. Create a focused `feature/`, `fix/`, or `docs/` branch from `main`.
3. Implement the smallest useful, testable change.
4. Open a pull request with Summary, categorized Release Notes, and Testing instructions.
5. Merge after review.
6. Deploy through cPanel Git Version Control.
7. Verify the affected live workflow.
8. Update documentation when capability, direction, or a durable decision changes.

## Documentation ownership

- `README.md` — current product overview
- `DEVELOPMENT.md` — current baseline, architecture boundary, and active direction
- `docs/DEVELOPMENT.md` — durable architecture, decisions, and operating guidance
- `docs/ROADMAP.md` — concise milestone/release roadmap
- `CHANGELOG.md` — concise release-level outcomes
- GitHub Releases and merged PRs — detailed implementation history and installable artifacts