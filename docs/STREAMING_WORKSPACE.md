# Streaming workspace: Cloudflare, Watch Live and Twitch

## Scope

Phase 2 of Surfside Tools' dashboard redesign provides one streaming workspace
at /dashboard/site-settings/streaming/, reached from Messages & Media >
Streaming in the navigation or tool search.

The existing Streaming Management WordPress page stays in place. This change
reuses its shortcode, URL, permissions and Cloudflare handlers rather than
provisioning a replacement page or changing the page hierarchy.

## Sections

- Cloudflare & Recordings (default): Cloudflare Stream API connection, live
  webhook configuration/health, recording refresh and Last Service
  include/exclude. Reuses the existing surfside_stream_manage POST handler.
- Watch Live: Twitch channel name, announcement video from Media Library,
  and YouTube/Facebook Watch Live destinations. Reuses the existing
  surfside_information_update nonce and streaming-only save section.
- Twitch Connection: optional Twitch Client ID/secret and connection test.
  Reuses the existing Twitch settings handler and nonce.

The Twitch editor includes a visible Save Twitch Settings button so it works
standalone, without relying on Integrations-wide save JavaScript. Server-side
validation and preservation of existing secrets are unchanged.

The Watch Live shortcode has an optional embedded mode to avoid repeating
its old page header inside the consolidated workspace. Its form is unchanged;
the Media Library picker is enqueued on the unified Watch Live tab.

## Backward compatibility

- The old /dashboard/site-streaming/ page remains functional, as do existing
  Church Settings and Integrations links and bookmarks.
- No database tables or option migrations, Cloudflare credential rotations,
  webhook changes, public media playback changes or API changes.
- Streaming and Twitch panels in Integrations now link to the unified
  workspace, rather than duplicating configuration forms.
- Navigation contains one Streaming item. Search accepts Twitch, YouTube,
  Watch Live, Cloudflare, Facebook, recordings and notifications.
- Permissions remain manage_options for all three tabs.
- No per-visitor Cloudflare calls. The recording query runs only when staff
  open the Cloudflare tab, not on every dashboard visit.

## Manual smoke test

1. Open Tools & Search > Streaming on desktop and phone, and use all three
   tabs. Confirm the same page with tab-specific query parameters.
2. Cloudflare & Recordings: confirm current connection and recording list.
   Optionally refresh the listing. Do not rotate the webhook secret.
3. Watch Live: verify saved Twitch channel, YouTube/Facebook destinations
   and offline announcement media. Test the Media Library picker; verify
   an authorized reversible change saves and is reflected on reload.
4. Twitch Connection: verify existing credentials status, test the connection
   and see the Save Twitch Settings button. Do not replace a working secret.
5. Open Integrations > Streaming/Twitch; both should link to the new unified
   editor instead of presenting duplicate forms.
6. Confirm old /dashboard/site-streaming/ bookmarks remain functional.
7. Ordinary (non-admin) staff must not see Streaming in search or gain direct
   access to any new tab.
8. Public Watch Live, app and Roku playback and notifications stay unchanged.

## Follow-up

Calendar Defaults and Saved Places are still in Integrations. Consolidating
those under Calendar is a separate PR; redesigning the content of individual
streaming forms would also be separate scope.
