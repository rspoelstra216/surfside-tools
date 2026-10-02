# Twitch live-status setup

This backend supports Home Live Now without loading a video player. Automatic notifications and app UI changes are separate roadmap items.

## Configure in Integrations

Register a Confidential Twitch application with Website Integration as its category. Keep the existing embedded player and configured Twitch channel.

Open **Church Settings → Integrations → Twitch**, enter the Client ID and Client Secret, and click **Save Integrations**. Then use **Test Connection** to check the saved settings. A successful offline result confirms the connection even when no stream is running. Blank secret input preserves the saved secret; use Remove secret on Save to clear it. Secrets are stored server-side in non-autoloaded WordPress options and are never rendered back into the form or public APIs.

### Optional server configuration

Existing wp-config.php constants remain supported and override the corresponding dashboard fields. Those fields are disabled in Integrations. To manage both credentials entirely through Integrations, remove the constants and enter the credentials there.

In cPanel File Manager, edit the WordPress installation's wp-config.php. Add the following before the stop-editing comment, replacing the placeholders privately:

```php
define('SURFSIDE_TWITCH_CLIENT_ID', 'YOUR_CLIENT_ID');
define('SURFSIDE_TWITCH_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
```

Do not put real values in GitHub, the mobile app, chat, or screenshots. These constants are read only on the server. The existing Site Information Twitch channel is used; no duplicate channel setting is added.

## Verify after deployment

Open https://surfsidefellowship.org/wp-json/surfside/v1/livestream/status

- Offline: status=offline, is_live=false.
- Live: status=live, is_live=true, with stream_id and started_at.
- Unconfigured, failed request, or malformed response: status=unknown, is_live=null. Unknown is not a confirmed offline state.
- No credentials or access tokens appear in the public response.
- Status is cached server-side for 30 seconds; concurrent requests are coalesced.
- App tokens are validated when obtained and reused for at most 55 minutes. A revoked token is discarded and renewed once.
- No browser/CDN caching should extend the internal cache.
- Test stream start/stop and wait at least 30 seconds between transition checks.
- Failed checks replace stale live state with unknown; the mobile client should only show Live Now for a fresh confirmed live response.

## Validation

The PR workflow runs PHP syntax checks and scripts/test-twitch-live-status.php with mocked WordPress HTTP/cache functions. The tests cover live/offline/unknown, malformed data, token client mismatch, token renewal, cache reuse, failure backoff, and lock cleanup. Real credentials and WordPress deployment still require the verification above.

No scheduled polling, automatic notification sending, or mobile UI change is introduced here.
