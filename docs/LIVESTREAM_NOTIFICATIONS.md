# Automatic livestream notifications

The plugin schedules a one-minute WordPress cron check of the configured Twitch channel. It uses existing Twitch credentials and the existing livestream opt-in, which defaults off. No public status request sends notifications.

A fresh confirmed live stream (started within ten minutes, status checked within ninety seconds) triggers one delivery attempt. Stream IDs are remembered for thirty days; attempts within two hours are suppressed to cover production reconnects with a new Twitch ID. This applies to broadcasts on any day so rehearsals work; it does not send scheduled pre-service reminders.

Claims are saved before sending. Failed or partially delivered attempts are not retried automatically because a timeout can conceal successful delivery. Existing Expo receipts and stale-device cleanup remain in use. Health is stored in surfside_tools_live_push_health; no tokens or credentials are logged.

## Hosting requirement

WordPress traffic-driven cron alone can be late when nobody visits. Configure the host to run WordPress due cron every minute, using the site's actual WordPress path:

```
* * * * * /path/to/wp --path=/path/to/wordpress cron event run --due-now --quiet
```

Use the host's supported WP-CLI/PHP path. If wp-config disables traffic-driven cron, the host runner is essential. Confirm the scheduled hook surfside_tools_live_push_check is present and its last checked time advances while the site is idle. The repository change does not configure cPanel cron.

## Deployment and verification

Deploy Tools and merge the companion app routing change. Existing app builds still open Worship; the updated development app also supplies section=live to reset/position the player.

Enable Livestream Reminders on a registered test phone and allow OS notifications. Start a short broadcast on the configured Twitch channel, keeping the app backgrounded; confirm one alert and tap into Worship/live. Verify an opted-out device gets no alert, cold-start taps work, and reconnects do not repeat alerts. Restarting within two hours deliberately stays silent. Also test a tap after streaming ends.

This automation sends real notifications to all opted-in installations. Device delivery and host scheduling verification remain pending.
