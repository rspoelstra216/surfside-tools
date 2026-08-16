# Surfside Tools Development

This is the concise entry point for current Surfside Tools development. For architecture, detailed capabilities, the Nice Ideas backlog, decision history, validation checklists, and release procedures, see the [Development Handbook](docs/DEVELOPMENT.md).

## Current version

**3.1.0** — Native Connect/contact workflow released August 16, 2026

Version 3.0.0 applies the shared Surfside information and coastal design foundation across the complete public navigation. Versions 3.0.1–3.0.2 established and refined the versioned data bridge used by the Surfside mobile app. Version 3.1.0 completes the shared Connect workflow for the mobile app and website, including category-based routing, native website contact handling, and Cloudflare Turnstile protection.

## Project vision

Surfside Tools should let church staff perform routine website maintenance through clear front-end workflows without needing WordPress Admin, while giving visitors useful and accessible public website and mobile-app experiences.

## Design principles

- Staff first.
- Front end first.
- Automate when confidence is high.
- Prompt clearly when information is missing.
- Keep related workflows together.
- Preserve review, confirmation, duplicate protection, and undo.
- Build focused, testable pull requests.
- Avoid adding dashboard information that is not clearly actionable.
- Favor accessible public experiences that work well on desktop and mobile.
- Move reusable public experiences into version-controlled plugin shortcodes instead of maintaining substantial page-specific CSS.
- Keep website and mobile-app content centralized rather than creating parallel management workflows.

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

Implementation history for completed work belongs in the [changelog](CHANGELOG.md), GitHub Releases, and merged pull requests.

## Current direction

Version 3.1.0 is the stable Surfside Tools baseline. The native Connect/contact system is complete and verified end-to-end on both the mobile app and website.

Current architecture boundary:

- Surfside Tools owns sitewide settings, centralized information, navigation, headers, footers, dynamic widgets, complex reusable sections, shared design standards, the versioned mobile-app API, and shared Connect/contact routing.
- WordPress pages retain unique editorial content and page-specific layouts.
- Gutenberg remains the preferred editor for straightforward content; plugin shortcodes are reserved for dynamic data or layouts that proved unreliable to maintain as nested blocks.
- The Staff Dashboard exposes one Manage Website entry point with organized management areas rather than duplicate quick actions.
- Mobile endpoints expose only the data required by the app while administrative settings, drafts, credentials, and unrelated internal data remain private.
- Website and app features should consume the same centralized content and service plumbing whenever practical.

### Completed in 3.1.0 — Native Connect workflow

- Dashboard-managed recipient routing for General Questions, Prayer Request, Ministry Information, Small Group Information, and Speak to a Pastor.
- Mobile-app Connect submissions routed through Surfside Tools and WordPress mail.
- Category-specific prayer-team sharing and pastor preferred-contact data.
- Native `[surfside_contact_form]` website form using the same routing configuration as the app.
- Cloudflare Turnstile server-side verification plus nonce, honeypot, and rate-limit protections.
- Native website success/error handling that returns visitors to the Contact section.
- Forminator removed after the native website workflow was verified in production.

Primary development now returns to the Surfside mobile app, beginning with Giving functionality. Surfside Tools should receive additional work only when the app requires shared server-side data, management, or integration plumbing, or when a separate website improvement is intentionally scheduled.

## Development workflow

1. Confirm the current objective here and in the detailed handbook.
2. Create a focused branch from `main` using `feature/`, `fix/`, or `docs/`.
3. Implement the smallest useful and testable change.
4. Open a pull request with Summary, categorized Release Notes, and Testing instructions.
5. Merge after review.
6. Deploy through cPanel Git Version Control.
7. Verify the affected live workflow.
8. Update documentation when capability, direction, or a durable decision changes.

## Documentation ownership

- `README.md` — product overview and documentation links
- `DEVELOPMENT.md` — current version, completed milestones, and active development direction
- `docs/DEVELOPMENT.md` — detailed living handbook
- `docs/ROADMAP.md` — concise milestone roadmap and delivery plan
- `CHANGELOG.md` — official release history generated by the release workflow
- GitHub Releases — versioned notes and installable ZIP packages
