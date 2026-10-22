# Staff dashboard caching and stale browser assets

## Symptoms

Chrome may display an old sidebar after a plugin deployment. Some sign-in
sessions show "You are signed in" but redirect back to login, while Incognito
behaves differently.

This change addresses two separate cache layers:

1. HTML, redirects and login: all Surfside Tools dashboard paths including
   /dashboard/, child screens, and /dashboard/login/ receive an early
   DONOTCACHEPAGE flag, the existing LiteSpeed full-page cache bypass, and
   explicit Cache-Control: private, no-store, no-cache, must-revalidate,
   max-age=0; Surrogate-Control: no-store; Pragma: no-cache; and a past
   Expires header. These apply before login as well, since Surfside's custom
   session cookies cannot be identified as native WordPress logins by
   every cache.
2. CSS and JavaScript: workspace asset URLs now carry a SHA-256 prefix of the
   actual deployed file content. The resulting version changes whenever
   those files change, even if the plugin version remains 3.3.0.

Public website and public APIs remain unchanged. No cookie lifetime,
authentication provider, user permissions, or redirect destination changes
are included. Caching is only one possible cause of a login redirect loop.

## One-time cleanup after deploying

1. Deploy the plugin, then purge existing cached /dashboard and
   /dashboard/* pages at the host/server layer. Disabling a cache plugin
   may not evict an old response already cached upstream.
2. In Chrome do Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac).
   If necessary, clear cached images and files for the site. Deleting
   cookies should not be required for stale sidebar styles, and it
   signs staff out.
3. Chrome DevTools > Network: with Disable cache on, open the dashboard.
   The HTML response should say Cache-Control: ...no-store...
   The URLs for staff-workspace.css and staff-workspace.js should each
   have a 12-character content-hash version rather than ver=3.3.0.
4. Try signing in and navigating among dashboard and staff pages.
5. If a loop persists despite a network-fetched response with no-store,
   inspect the auth POST response for Set-Cookie and the following
   dashboard request for a Cookie header. Never share cookie/token values.

## Important limit

PHP cannot change a response already served by a cache before WordPress
runs. If the webserver, reverse proxy, or CDN keeps returning a cache HIT
for /dashboard or /dashboard/*, explicitly exclude those URLs at that layer.
Staff-auth endpoints under /wp-json/surfside-tools/v1/staff-auth/ must
also not be cached. Public Surfside pages may continue caching normally.

## Regression

- php scripts/test-staff-cache.php: staff URL scope, early no-store, public
  page exclusion and plugin cache bypass.
- php scripts/test-staff-workspace.php: content-derived asset URL versions,
  dashboard navigation and authorization.
- Other existing CI PHP syntax and functional regressions remain active.
