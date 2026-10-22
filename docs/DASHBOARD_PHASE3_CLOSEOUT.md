# Surfside Tools — Phase 3 Dashboard Consolidated Audit

**Audit point:** October 22, 2026, after Phase 3E / PR #471 merged.
**Scope:** Staff dashboard navigation, current health/status cards and the
remaining existing management hubs following the Phase 3 usability work.

This is a **targeted UX and correctness closeout**, not a new architectural
repository audit. The production database and a real secretary session were
not available to this source review.

## Completed dashboard phases

| Phase | PR | Management improvements |
| --- | --- | --- |
| 3A | #467 | Weekly Update: currently published versus Saturday queue, private preview, clearer two-step upload workflow |
| 3B | #468 | Calendar Manager: browse/edit shortcuts, search and phone layout |
| 3C | #469 | Member Engagement, Prayers and Praises, Volunteer Needs, Contact Routing |
| 3D | #470 | Featured Announcement, Archived Sermon Notes, YouTube Message Archive Manager |
| 3E | #471 | Homepage Photos, Website Navigation, App Home Experience |

The six-part task navigation is already shared with dashboard tool groups,
uses capability filtering, and includes keyboard/mobile search. Existing
hub routes for Manage Website, Manage Mobile App and Church Settings preserve
bookmarks. Separate systems such as R2 media, Streaming, Ministries, Calendar
Defaults, Saved Places and external Integrations have established owners
and dedicated controls; no evidence from this audit warrants rewriting them
merely for consistent cosmetics.

## Phase 3F corrections

1. **Calendar badge/alerts had two independent rule paths.** The lower
   evaluator compared a date with an undefined `$now` variable and a
   14-day threshold; the overview then overrode it with a 30-day count.
   Consolidate the rule in one evaluator, using the existing displayed
   30-day occurrence count. No calendar event storage or recurrence
   calculation is changed.
2. **Weekly green Published state could appear with no live documents.**
   With neither announcements nor sermon notes published, show a
   non-panic **Not published** warning; a Saturday queue is not a
   substitute for material currently visible to the public. Existing
   documents without a queue remain healthy, even when old, because
   Saturday scheduling is intentional. Explicit release failures
   continue to take priority.
3. **Google Maps connection wording overstated its check.** A configured
   key is a saved configuration value, not proof that Google can be
   reached. Keep the existing missing-key warning, but show **Key saved**
   instead of making an unverified live-connection claim.
4. **Dashboard Tools by task hid everything beyond four links in each
   group.** Keep the concise first four but add an accessible
   expandable **Show N more tools** with remaining authorized links.
   This reuses the same capability-filtered catalog, not another index.

All changes are limited to the existing dashboard overview and evaluation
owner; the last item is display-only. No new requests, external lookups,
database migrations, public CSS, navigation URLs or authentication changes.

## Remaining verification and ownership

**Deferred real-world checks (not failures):**
- Archived Sermon Notes: confirm the first replaced sermon is added to
  history after an actual weekly Saturday release; the archive currently
  has no historical messages.
- Saturday weekly auto-publishing: the server cron invocation succeeds,
  but the first scheduled public replacement should be validated on
  the website and app after a real queued update.

**Outside this dashboard phase:** Roku/R2 media uploads, mobile OS release
work, push/live-stream notifications, website media player behavior and
external API connectivity belong to their existing subsystem testing.
Avoid adding more cosmetic PRs without a user-visible problem or
verified functional gap.

## Validation checklist

1. Dashboard with current published weekend documents but **no queued
   update**: Weekly Update badge remains **Published**.
2. A newly initialized, empty weekly system: status is **Not published**
   rather than green.
3. A real pending Saturday schedule: shows **Scheduled**; release error
   overrides with **Release issue**.
4. Calendar with no events: **Action required**. With events only
   outside 30 days: **Attention**. With near events: **Healthy**.
   Count and status use the same window.
5. Admin Integration status is based on presence of a saved Google
   Maps API key, not an asserted successful connection test.
6. Dashboard **Tools by task**: expand a group that has more than four
   items and verify the rest of the group's links, including permission
   filtering for ordinary staff users.
7. Test on a phone: details expansion works and does not introduce
   horizontal scrolling. Existing dashboard tool search, breadcrumbs,
   and legacy hub bookmarks remain functional.

## Regression

`scripts/test-dashboard-phase3-audit.php` checks the real status
helpers and dashboard renderer using PHP fixtures. It asserts 30-day
consistency, empty/current/scheduled/error weekly states, prayer alerts,
honest Google Maps config wording, complete grouped tool discoverability
and anonymous/nonstaff access control. GitHub Actions runs it alongside
all existing plugin tests.

## Recommendation

After Phase 3F is merged and tested, conclude the dashboard UX overhaul
rather than continuing a long sequence of cosmetic adjustments. Resume
app and streaming/media roadmap priorities when deliberately scheduled.
