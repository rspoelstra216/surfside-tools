# Surfside Tools Code Audit Closeout

**Status:** Complete  
**Completed:** September 16, 2026  
**Baseline:** Post-3.2.0 cleanup, verification, and hardening

This document records the outcome of the repository-wide cleanup and the independent verification audit that followed it. Detailed implementation and regression history remains in the merged pull requests; this file captures the durable architectural result and the conditions for considering the audit complete.

## Objective

The audit focused on reducing accumulated implementation layers without redesigning working features. The primary targets were dead/orphaned code, superseded renderers, duplicate compatibility layers, request-wide staff/runtime work, repeated Google Places/calendar behavior, management screens that depended on post-render mutation instead of a clear owner, and public/deployment paths that needed stronger operational safeguards.

The cleanup deliberately favored behavior-preserving consolidation. Each production change was tested through the affected live workflow before the next subsystem was addressed.

## First cleanup phase

### Runtime and dead code

- Removed proven orphaned and unreachable modules, including the final orphaned Weekly Update Google predictions implementation.
- Retired page-ensure and route-repair behavior that no longer belonged on normal requests.
- Scoped staff-only queries, calendar calculations, footer assets, observers, and helper work to the screens that actually need them.
- Preserved the runtime rule that migrations, page checks, broad staff queries, and staff rendering must not run globally unless every request truly requires them.

### Authentication and Staff Access

- Consolidated Staff Access around the current unified people model.
- Removed superseded Staff Access and Firebase login renderers, obsolete shortcode resets, duplicate authorization flows, and retired permission-seeding behavior.
- Kept Tools Access independent from WordPress Site Access while preserving supported Google/Firebase and WordPress credential paths.

### Staff Dashboard and management hubs

- Established `dashboard-overview.php` as the current Staff Dashboard renderer.
- Removed retired Recent Activity/database bookkeeping and later dashboard mutation layers.
- Made Member Engagement routing and pending-prayer alerts part of the normal server-side dashboard evaluation.
- Consolidated Church Settings and Integrations toward direct rendering instead of later regex/DOM rewrites.

### Homepage and Ministries

- Consolidated homepage carousel storage/runtime/cache ownership and removed superseded manager, migration, repair, styling, and drag-fix layers.
- Made the Ministry Manager render its permanent Published, Featured, contact, icon, and layout behavior directly.
- Kept public ministry-directory presentation separate from canonical ministry storage and management.

### Calendar, Weekly Update, Google Places, and Saved Places

- Consolidated Calendar Suggestion matching/location support and eliminated duplicate Google Places initializers.
- Centralized Saved Places discovery/actions and filtered hidden locations before data is sent to the browser.
- Consolidated public calendar action branding with its actual calendar integration owner.
- Made monthly-calendar overflow/Day Details rendering authoritative instead of rewriting shortcode output afterward.
- Made Calendar Manager own Venue, Street Address, Meeting Location, Google Places status, and public meeting-location rendering directly.
- Removed the `location-clarity.php` compatibility layer and its additional query/DOM work.

## Independent verification audit

After the first closeout was merged in PR #382, a fresh read-only audit was run from current `main` rather than assuming the cleanup was complete. That second pass found several concrete issues that the earlier signature sweep had missed. The repository therefore remained under audit until those findings were resolved and deployed.

The second audit produced PRs #383 through #391:

- **#383 — Staff-page lifecycle/provisioning:** centralized the staff page tree behind one versioned install/upgrade registry and removed remaining request-time page repair work.
- **#384 — Mobile App settings ownership:** retired the destructive legacy wp-admin Mobile App settings writer and made the current staff tools authoritative.
- **#385 — Authentication consolidation/hardening:** removed the parallel Firebase authorization generation, added bounded WordPress credential throttling, and unified custom Tools-session logout behavior.
- **#386 — Dashboard and Member Engagement ownership:** removed the remaining obsolete Dashboard renderer/filter layer and made Volunteer Needs natively owned by Member Engagement.
- **#387 — Volunteer Needs styling:** removed its hidden dependency on Surfside Information button CSS and gave the subsystem its own controls.
- **#388 — Church Settings and ministry ownership:** removed duplicate Turnstile ownership, the unused legacy Ministries editor, and remaining Church Settings/Ministry post-render corrections.
- **#389 — Calendar Manager rendering:** removed the duplicate five-year recurrence pass and made counts, expiration handling, recurrence labels, and month navigation authoritative at render time.
- **#390 — Public API protection:** added bounded public-resource protection for Expo registration and uncached YouVersion work, with caching that preserves normal app behavior.
- **#391 — CI and deployment hygiene:** added pre-merge PHP/structure validation and changed cPanel deployment to synchronize tracked runtime directories so deleted files do not remain on production.

All of those production changes were functionally tested after deployment before the audit was closed.

## Final end-state verification

The final verification sweep of current `main` found no remaining repository matches for the three implementation-debt signatures that were repeatedly responsible for layered behavior during this work:

- `do_shortcode_tag` post-render filters;
- `MutationObserver` DOM-repair layers; and
- `remove_shortcode()` compatibility resets.

The second audit also rechecked staff-page lifecycle, authentication ownership, calendar recurrence work, public API resource protection, CI validation, and deployment synchronization. No additional production-code cleanup item was identified after deployed PR #391.

The initial PR #382 closeout was therefore an intermediate milestone rather than the final verification point. The repository audit is considered complete only after the independent second audit and deployed PR #391.

These checks are not a rule that the APIs above can never be used. Future use should require a concrete feature need rather than serving as a compatibility patch over an existing owner.

## Durable ownership rules

Going forward:

1. **One authoritative owner per surface.** Permanent fields, labels, layout, and behavior should be rendered by the subsystem that owns them rather than added by a later filter or browser mutation.
2. **Scope runtime work.** Staff queries, migrations, calendar expansion, page checks, and support assets should run only on the page/action that needs them.
3. **Prefer source-level data filtering.** Do not emit data to the browser only to remove or repair it afterward when the server can produce the correct payload directly.
4. **Keep compatibility code temporary and explicit.** A compatibility path should have a specific external dependency or migration reason; remove it when that reason is gone.
5. **Do not create cleanup PRs without a concrete finding.** A filename such as `polish`, `refinement`, or `compatibility` is not itself evidence that code should be rewritten.
6. **Preserve subsystem-sized PRs.** Related cleanup belongs together when it has one clear owner and regression surface; avoid both risky repository-wide rewrites and unnecessary one-line micro-PR chains.
7. **Validate live behavior before continuing.** Cleanup is complete only after the affected production workflow has been tested successfully.
8. **Validate before merge.** Pull requests targeting `main` should pass the repository PHP/structure check before merge.
9. **Deploy as a mirror of tracked runtime code.** Files deleted from Git must not remain indefinitely on the production plugin filesystem.

## Separate policy decisions

The verification audit also identified prayer-request data retention as a product/privacy policy decision rather than a code-cleanup defect. Prayer submissions contain personal information and currently remain available as historical records. Any retention or deletion policy should be decided intentionally before code changes are made; it is not treated as unfinished repository cleanup in this closeout.

## Closeout decision

The repository audit is considered **complete after deployed PR #391**. There is no standing production-code cleanup backlog from either audit phase.

Future repository cleanup should be driven by a specific defect, measurable runtime concern, proven duplication, orphaned code, security issue, or architectural conflict discovered during normal feature work. Otherwise, development should return to the product roadmap, with primary feature work continuing in the Surfside mobile app and Surfside Tools changing when shared/server-side support is actually required.
