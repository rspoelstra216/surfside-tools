# Surfside Tools Code Audit Closeout

**Status:** Complete  
**Completed:** September 11, 2026  
**Baseline:** Post-3.2.0 cleanup and stabilization

This document records the outcome of the repository-wide cleanup performed after the 3.2.0 release. Detailed implementation and regression history remains in the merged pull requests; this file captures the durable architectural result and the conditions for considering the audit complete.

## Objective

The audit focused on reducing accumulated implementation layers without redesigning working features. The primary targets were dead/orphaned code, superseded renderers, duplicate compatibility layers, request-wide staff/runtime work, repeated Google Places/calendar behavior, and management screens that depended on post-render mutation instead of a clear owner.

The cleanup deliberately favored behavior-preserving consolidation. Each production change was tested through the affected live workflow before the next subsystem was addressed.

## Completed workstreams

### Runtime and dead code

- Removed proven orphaned and unreachable modules, including the final orphaned Weekly Update Google predictions implementation.
- Retired page-ensure and route-repair behavior that no longer belonged on normal requests.
- Scoped staff-only queries, calendar calculations, footer assets, observers, and helper work to the screens that actually need them.
- Preserved the 3.2.0 runtime rule that migrations, page checks, broad staff queries, and staff rendering must not run globally unless every request truly requires them.

### Authentication and Staff Access

- Consolidated Staff Access around the current unified people model.
- Removed superseded Staff Access and Firebase login renderers, obsolete shortcode resets, duplicate authorization flows, and retired permission-seeding behavior.
- Kept Tools Access independent from WordPress Site Access while preserving supported Google/Firebase and WordPress credential paths.

### Staff Dashboard and management hubs

- Established `dashboard-overview.php` as the authoritative Staff Dashboard renderer.
- Removed retired Recent Activity/database bookkeeping and later dashboard mutation layers.
- Made Member Engagement routing and pending-prayer alerts part of the normal server-side dashboard evaluation.
- Made Church Settings and Integrations render their final stable information architecture directly rather than depending on later regex/DOM rewrites.

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
- Removed the final `location-clarity.php` compatibility layer and its additional query/DOM work.

## End-state verification

The final structural sweep of `main` found no remaining repository matches for the three cleanup signatures that represented the most common layered implementation debt:

- `do_shortcode_tag` post-render filters;
- `MutationObserver` DOM-repair layers; and
- `remove_shortcode()` compatibility resets.

A final bootstrap/includes review also identified and removed the orphaned `weekly-update-google-predictions.php` file in PR #381. That file was never loaded by the plugin and its removal was deployed and smoke-tested without changing the active Weekly Update venue workflow.

These checks are not a rule that those APIs can never be used. They establish that the known audit debt is gone. Future use should require a concrete feature need rather than serving as a compatibility patch over an existing owner.

## Durable ownership rules

Going forward:

1. **One authoritative owner per surface.** Permanent fields, labels, layout, and behavior should be rendered by the subsystem that owns them rather than added by a later filter or browser mutation.
2. **Scope runtime work.** Staff queries, migrations, calendar expansion, page checks, and support assets should run only on the page/action that needs them.
3. **Prefer source-level data filtering.** Do not emit data to the browser only to remove or repair it afterward when the server can produce the correct payload directly.
4. **Keep compatibility code temporary and explicit.** A compatibility path should have a specific external dependency or migration reason; remove it when that reason is gone.
5. **Do not create cleanup PRs without a concrete finding.** A filename such as `polish`, `refinement`, or `compatibility` is not itself evidence that code should be rewritten.
6. **Preserve subsystem-sized PRs.** Related cleanup belongs together when it has one clear owner and regression surface; avoid both risky repository-wide rewrites and unnecessary one-line micro-PR chains.
7. **Validate live behavior before continuing.** Cleanup is complete only after the affected production workflow has been tested successfully.

## Closeout decision

The repository audit is considered **complete** after deployed PR #381. There is no standing cleanup backlog from this audit.

Future repository cleanup should be driven by a specific defect, measurable runtime concern, proven duplication, orphaned code, or architectural conflict discovered during normal feature work. Otherwise, development should return to the product roadmap, with primary feature work continuing in the Surfside mobile app and Surfside Tools changing when shared/server-side support is actually required.
