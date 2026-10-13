# Automatic livestream notifications (Cloudflare Stream)

Cloudflare Stream is the single authoritative go-live source for Surfside. Home's **Live Now**, website playback, recent-service replay, and the app's go-live push all reflect the same Cloudflare input. Twitch can still be used as a viewing fallback, but is **not a go-live notification trigger**.

The previous Twitch EventSub notification endpoint acknowledges valid legacy notifications without creating Expo pushes. Its existing queued jobs are discarded without sending. No polling job is required to detect a Cloudflare stream.

## Configure (admin-only; no new API credentials)

1. Merge and deploy Surfside Tools, then open **Church Settings → Streaming Management**.
2. Under **Automatic go-live notifications**, click **Generate webhook secret**. Copy the displayed secret immediately: it is only shown once. Do **not** paste it into GitHub, chat, a URL, or an app file. The secret is stored privately as a WordPress option with autoload disabled. Rotate only when also updating Cloudflare.
3. In your Cloudflare account, open **Alerts → Destinations → Webhooks → Create** (Cloudflare may label the area Notifications). Create a **generic webhook** destination. Set the URL to:
   `https://surfsidefellowship.org/wp-json/surfside/v1/cloudflare/live-event`
4. Paste the one-time secret in Cloudflare's **Secret** field. Cloudflare sends it as the `cf-webhook-auth` header. **Save and Test** sends a generic test message; it should be acknowledged without sending an app push.
5. Go to **Alerts / Notifications → All Notifications → Add**. Select product **Stream**, choose your newly saved webhook destination, and set the alert to **live_input.connected** only. Restrict it to the Surfside live input:
   `d8fe3de81677a407a70b62f0bb470769`
   Save the alert. Do not use the unrelated **Stream video-ready** webhook; that fires when recordings finish, not when the church goes live.
6. On a physical phone with the Surfside app, allow system notifications, open **More → Notification Preferences**, and turn **Livestream Reminders** on. Existing registered opt-in devices are reused.
7. Start a new Cloudflare-only test stream from vMix. Confirm the phone gets **Surfside is live** and tapping opens **Worship**. Afterward, Streaming Management shows **Last push handoff** (submitted or a specific error code).

## Performance & security

- There is **no periodic polling** or per-visitor WordPress page setup. Cloudflare sends an HTTPS webhook only on a configured Stream connection transition. One POST is authenticated by the 64-character secret, checked against the exact Surfside Input ID and event type, and rejected if its timestamps are too old or in the future.
- Repeated/late events, disconnections, and **Save and Test** messages do not send pushes. WordPress uses a short database lock and queues a single delivery job; a nonblocking local HTTP worker normally submits it promptly, with one WordPress cron backup in case of a loopback failure.
- An additional connected event during an active broadcast does not alert. A disconnect/reconnect or another new start **within two hours of the previous attempt** also does not alert. This preserves the previous two-hour anti-spam policy. All broadcasts, including rehearsals, are eligible on their first connection outside the cooldown.
- Every push is addressed to existing Expo registrations that **opted in to the livestream audience**. The destination is Worship. There are no new app builds or client permissions associated with this WordPress-only change.
- The admin-only **Last push handoff: submitted** message means the existing Expo sender accepted the request, *not* that a phone necessarily received it; check an actual subscribed test device and Expo receipts.

## Troubleshooting

If live playback works but no push arrives:

1. Ensure Cloudflare's **notification policy exists and is enabled**, not merely the webhook destination.
2. Check the destination Secret against the value generated in Streaming Management. If the secret is lost, rotate in WordPress and update Cloudflare; the old secret immediately stops working.
3. Verify the Stream event is restricted to the correct live input and includes **connected** events.
4. Confirm the phone has system permission, a valid Expo push token, and the **Livestream Reminders** audience enabled. No eligible devices is recorded as `surfside_push_no_devices`.
5. Make sure the prior go-live attempt was more than two hours ago. For a meaningful retest, wait for the cooldown rather than repeatedly restarting vMix.
6. Confirm Cloudflare can reach HTTPS port 443 and no firewall/login redirect blocks POST to `/wp-json/surfside/v1/cloudflare/live-event`.
7. If handoff does not appear, check that the WordPress loopback to `/cloudflare/live-deliver` is reachable. WordPress cron is a **one-shot backup**, not a frequent polling task.

Never expose the webhook secret or the Cloudflare Stream read-only API token in public logs, screenshots, GitHub, or support messages.

## Tests

`php scripts/test-cloudflare-live-notifications.php` validates secret authentication, Cloudflare test messages, input filtering, stale/out-of-order events, single-delivery claiming, webhook/worker authentication, cooldown, disconnects, and Expo error outcomes.

`php scripts/test-livestream-notifications.php` now ensures old signed Twitch EventSub events cannot queue or send go-live pushes. Live browser tests and phone notification delivery remain necessary after deployment.
