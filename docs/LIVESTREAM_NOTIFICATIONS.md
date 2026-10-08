# Automatic livestream notifications

Twitch EventSub sends a signed stream.online webhook to Surfside when the configured channel starts broadcasting. The former repeating Twitch polling task is removed on the next WordPress request. The public live-status API still supports the app's live cards.

## Setup and verification
1. Deploy this change.
2. Save the matching Twitch Client ID / Secret in Integrations → Twitch and confirm the channel in Streaming Settings.
3. Press **Enable / Reconnect Go-Live Notifications**, then refresh. Status must become **enabled**. Verification pending alone is not success.
4. Enable Livestream notifications on a physical test phone, with system notification permission allowed.
5. Start a new broadcast. Confirm the push arrives and tapping opens Worship. A stream already running when connected does not produce a retrospective alert.

The HTTPS callback uses port 443 and must accept unauthenticated POST requests without login, challenge pages, caching, or redirects. HMAC authentication occurs inside the handler. Do not expose the stored webhook secret. If a firewall blocks the callback or loopback worker, inspect hosting configuration.

## Delivery and duplicate behavior
The webhook validates the signature over message ID, timestamp, and raw body, rejects messages older than ten minutes, and checks subscription/channel identity. Verification returns the raw challenge. Revocation status is displayed in Integrations; reconnect explicitly after repairing the cause or changing credentials/channel.

The webhook durably queues a job and starts a nonblocking HTTPS loopback worker; it does not wait for Expo push delivery. A one-shot WordPress cron job is a backup if immediate processing fails. This is not Twitch polling. Normal immediate delivery does not require a minute-by-minute cPanel check, but blocked loopbacks require a functioning cron runner for timely backup delivery. Verify actual phone delivery after deployment.

Existing livestream opt-in, Worship destination, stream deduplication, and two-hour reconnect guard remain. All new broadcasts qualify, including rehearsals. Claims are stored before sending: ambiguous or partial delivery failures are not retried to prevent duplicate notifications. A delayed job older than ten minutes is discarded. A two-hour restart will not send another alert. “submitted” means handed to the existing Expo sender, not confirmed phone receipt.

No new app native dependency is introduced. This does not replace the app's periodic screen status refresh. No paid Twitch subscription is required.

## Tests
The PHP fixture tests signed challenge handling, tampering, stale messages, mismatched broadcaster, asynchronous queuing, duplicate streams, reconnect guard, and revocation. Real callback verification and device delivery remain deployment checks.
