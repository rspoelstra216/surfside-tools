# Phase 2C — Integrations cleanup

## Intent

The Surfside Tools staff Integrations page has grown from a small collection
of API credentials to a mixture of unrelated editors, links to tools that
already have their own homes, and one global "Save Integrations" action.

This PR reorganizes the existing /dashboard/settings/ page without changing
the saved values, login or roles, APIs, or external service credentials.

## Page structure

**External connections**
- Google Maps: Google Places API key and Test Connection. Submit with
  "Save Google Maps Settings". The existing save handler remains responsible
  for merging this key into surfside_tools_settings, preserving Calendar data.
- YouTube Sermon Archive: API key, YouTube channel, master playlist, review
  status, Test Connection and Sync Now. Save YouTube Settings remains the
  existing form and POST-redirect-GET handler.
- YouVersion: Bible App Key, Test and Verse. The existing Save button is now
  actually visible, with the existing save/nonce/redirect logic intact.

**Church services**
- Giving and Cloudflare Turnstile retain a *shared form* and its current
  secure save behavior. "Save Giving & Turnstile" is now visible on the
  screen. As in previous versions, submitting this form saves both fields.
  A blank saved Turnstile secret preserves the existing secret.

**Advanced appearance**
- Reveal & Countdown Styling remains accessible through Integrations for
  compatibility with the current Appearance search link. Its own "Save
  Visual CSS" is visible, and the existing CSS sanitation/redirect remains.

There is no single "Save Integrations" action anymore. That old button used
JavaScript to POST every unrelated form in turn; error responses that
returned HTTP 200 could be mistaken for success and hidden Save controls
made individual edits difficult to reason about. Each form now has its
original, visible, purpose-specific Save or Sync/Test control.

## Duplicate panels removed

Integrations no longer renders duplicate "Calendar Defaults", "Saved
Places", "Streaming", or "Twitch" accordion links. Those management screens
live in their task-based workspaces.

Old *fragment* bookmarks still work via a small client-side redirect on
the existing Integrations page:

- #surfside-focus-calendar-defaults -> Calendar / view=defaults
- #surfside-focus-saved-places -> Calendar / view=places
- #surfside-focus-streaming -> Streaming workspace
- #surfside-focus-twitch and #surfside-twitch -> Streaming / stream_tab=twitch

Fragments never reach WordPress/PHP, which is why these redirects need to
run in the browser. Existing links to Integrations itself, the Google Maps,
YouTube, YouVersion, Giving, Turnstile and Reveal/Countdown accordions
remain valid. The YouTube/YouVersion action return hashes now reopen their
appropriate accordions so staff can actually see status messages.

The existing /dashboard/site-streaming/ and /dashboard/site-settings/
pages remain untouched, and Calendar/Streaming URLs do not change.

## Manual QA before merge

1. Visit Integrations on desktop and mobile: expect the three section
   headings and six editors in their groups. No redundant Calendar,
   Streaming or Twitch cards and no Save All.
2. Open Google Maps; confirm the saved API key field and Test Connection.
   Change nothing unless necessary; click Save Google Maps Settings and
   verify Calendar Defaults still contain the expected week mode/duration.
3. Open YouTube; verify the saved channel, playlist and state, Test
   Connection, and Sync Now remain. Do not run a needless sync. Confirm the
   "Save YouTube Settings" button is visible.
4. Open YouVersion; verify Save, Test and Verse buttons. If using Test,
   verify its result is displayed in the automatically reopened accordion.
5. Open Giving and Turnstile; verify their existing values are not cleared.
   Confirm Save Giving & Turnstile is visible and the secret is not shown.
6. Open Advanced appearance; verify Save Visual CSS is visible but do not
   change production CSS as part of navigation testing.
7. Open the old fragment bookmarks above. They must navigate to their
   new Calendar or Streaming destination. Google Maps and YouTube search
   links should still open the matching accordion.
8. Ordinary staff and signed-out users should still be denied access to
   this admin-only page. All public site, mobile and Roku features must
   behave exactly as before.

## Automated checks

- scripts/test-integrations-cleanup.php checks rendering, headings,
  individual forms, removed duplicate panels, old hash redirects, access
  control, existing nonces and option preservation.
- scripts/test-calendar-workspace.php updated to verify old fragment
  aliases, not duplicate old accordions.
- Existing tests for Calendar, Twitch/Streaming, YouTube POST routing,
  YouVersion and public APIs remain in the CI workflow.

## Future scope

A later PR may create a dedicated Appearance screen for the Reveal &
Countdown styling editor and remove it from this page. That is not part
of this consolidation; this release makes no data migration or change
to that editor's public CSS behavior.
