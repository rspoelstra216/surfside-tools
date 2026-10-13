<?php
/** Shared Cloudflare Stream live and last-service integration. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_stream_config() {
    $stored = get_option('surfside_tools_cloudflare_stream', array());
    return is_array($stored) ? $stored : array();
}
function surfside_tools_stream_api($path, $query = array()) {
    $config = surfside_tools_stream_config();
    if (empty($config['token'])) return new WP_Error('not_configured', 'Cloudflare Stream is not configured.');
    $url = 'https://api.cloudflare.com/client/v4/accounts/309f7bb0dd0cb9843f69802903b48fc0/stream/' . ltrim($path, '/');
    if ($query) $url = add_query_arg($query, $url);
    $response = wp_remote_get($url, array('timeout' => 12, 'headers' => array('Authorization' => 'Bearer ' . $config['token'])));
    if (is_wp_error($response)) return $response;
    if (wp_remote_retrieve_response_code($response) !== 200) return new WP_Error('stream_api_error', 'Cloudflare Stream request failed.');
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || empty($data['success'])) return new WP_Error('stream_api_error', 'Cloudflare Stream returned an error.');
    return $data['result'] ?? array();
}
function surfside_tools_stream_recordings($refresh = false) {
    $key = 'surfside_stream_recordings_v1';
    if (!$refresh) {
        $cached = get_transient($key);
        if ($cached !== false) return $cached;
    }
    $result = surfside_tools_stream_api('live_inputs/d8fe3de81677a407a70b62f0bb470769/videos');
    if (is_wp_error($result)) return $result;
    $items = array();
    foreach ((array) $result as $video) {
        if (!is_array($video) || empty($video['uid'])) continue;
        if (($video['status']['state'] ?? '') !== 'ready' || empty($video['readyToStream'])) continue;
        $items[] = array(
            'id' => sanitize_text_field($video['uid']),
            'created' => sanitize_text_field($video['created'] ?? ''),
            'duration' => (float) ($video['duration'] ?? 0),
            'title' => sanitize_text_field($video['meta']['name'] ?? 'Service recording'),
        );
    }
    usort($items, function($a, $b) { return strcmp($b['created'], $a['created']); });
    set_transient($key, $items, 120);
    return $items;
}
/**
 * Cloudflare Stream has returned both a scalar status and an object with
 * status.current.state. Support both documented/observed response shapes.
 *
 * @param array $input Live input result from Cloudflare.
 * @return bool Whether Cloudflare reports an active source connection.
 */
function surfside_tools_stream_is_live($input) {
    if (!is_array($input)) return false;

    $status = $input['status'] ?? null;
    if (is_array($status)) {
        $current = $status['current'] ?? null;
        $state = is_array($current) ? ($current['state'] ?? '') : '';
    } else {
        $state = $status;
    }
    return in_array($state, array('connected', 'reconnected'), true);
}

function surfside_tools_stream_public_data() {
    $recordings = surfside_tools_stream_recordings();
    $excluded = get_option('surfside_tools_stream_excluded', array());
    $excluded = is_array($excluded) ? $excluded : array();
    $last = null;
    if (!is_wp_error($recordings)) foreach ($recordings as $video) {
        if (in_array($video['id'], $excluded, true)) continue;
        $last = array(
            'id' => $video['id'], 'title' => $video['title'],
            'created' => $video['created'], 'duration' => $video['duration'],
            'hls_url' => 'https://customer-8je7me9ta294mn10.cloudflarestream.com/' . rawurlencode($video['id']) . '/manifest/video.m3u8',
        );
        break;
    }
    $live = get_transient('surfside_stream_live_v1');
    if ($live === false) {
        $status = surfside_tools_stream_api('live_inputs/d8fe3de81677a407a70b62f0bb470769');
        $live = is_wp_error($status) ? null : surfside_tools_stream_is_live($status);
        if ($live !== null) set_transient('surfside_stream_live_v1', $live ? 'yes' : 'no', 30);
    }
    if ($live === 'yes') $live = true;
    elseif ($live === 'no') $live = false;
    return array(
        'live' => $live,
        'live_hls_url' => 'https://customer-8je7me9ta294mn10.cloudflarestream.com/d8fe3de81677a407a70b62f0bb470769/manifest/video.m3u8',
        'last_service' => $last,
        'youtube_url' => 'https://www.youtube.com/@addpastor',
    );
}
add_action('rest_api_init', function() {
    register_rest_route('surfside/v1', '/stream', array(
        'methods' => 'GET', 'callback' => 'surfside_tools_stream_public_data',
        'permission_callback' => '__return_true',
    ));
});
function surfside_tools_staff_stream_shortcode() {
    surfside_tools_prevent_cache();
    surfside_tools_staff_enqueue_styles();
    if (!is_user_logged_in()) return surfside_tools_staff_login_box('Please log in to manage streaming.');
    if (!current_user_can('manage_options')) return '<p>You do not have permission to manage streaming.</p>';
    $notice = '';
    $new_webhook_secret = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['surfside_stream_action'])) {
        $nonce = sanitize_text_field(wp_unslash($_POST['surfside_stream_nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'surfside_stream_manage')) $notice = 'Security check failed. Refresh and retry.';
        else {
            $action = sanitize_key(wp_unslash($_POST['surfside_stream_action']));
            if ($action === 'token') {
                $config = surfside_tools_stream_config();
                $token = trim((string) wp_unslash($_POST['stream_token'] ?? ''));
                if ($token !== '') $config['token'] = sanitize_text_field($token);
                update_option('surfside_tools_cloudflare_stream', $config, false);
                $notice = 'Connection settings saved.';
            } elseif ($action === 'exclude') {
                $id = sanitize_text_field(wp_unslash($_POST['recording_id'] ?? ''));
                $recordings = surfside_tools_stream_recordings();
                $known = !is_wp_error($recordings) ? wp_list_pluck($recordings, 'id') : array();
                if (in_array($id, $known, true)) {
                    $excluded = get_option('surfside_tools_stream_excluded', array());
                    if (!is_array($excluded)) $excluded = array();
                    if (!empty($_POST['exclude'])) $excluded[] = $id;
                    else $excluded = array_diff($excluded, array($id));
                    update_option('surfside_tools_stream_excluded', array_values(array_unique($excluded)), false);
                    $notice = 'Recording preference saved.';
                }
            } elseif ($action === 'webhook_secret') {
                // Only an authorized staff POST may generate/rotate the secret.
                // Display it on this response exactly once; do not put it in a
                // page URL, public API, cache, or persistent rendered markup.
                $new_webhook_secret = bin2hex(random_bytes(32));
                update_option('surfside_tools_cf_live_push_webhook_secret', $new_webhook_secret, false);
                update_option('surfside_tools_cf_live_push_state', array(), false);
                $notice = 'New Cloudflare go-live webhook secret saved. Copy it below now and update the Cloudflare webhook destination.';
            } elseif ($action === 'refresh') {
                delete_transient('surfside_stream_recordings_v1');
                delete_transient('surfside_stream_live_v1');
                $notice = 'Cloudflare data refreshed.';
            }
        }
    }
    $config = surfside_tools_stream_config();
    $has_webhook_secret = (string) get_option('surfside_tools_cf_live_push_webhook_secret', '') !== '';
    $webhook_url = rest_url('surfside/v1/cloudflare/live-event');
    $webhook_health = get_option('surfside_tools_cf_live_push_health', array());
    if (!is_array($webhook_health)) $webhook_health = array();
    $recordings = surfside_tools_stream_recordings();
    $excluded = get_option('surfside_tools_stream_excluded', array());
    if (!is_array($excluded)) $excluded = array();
    ob_start(); ?>
    <div class="surfside-staff-shell"><div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('site-settings')); ?>">← Church Settings</a></div>
    <section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Shared Services</p><h1>Streaming Management</h1><p>Manage Cloudflare Stream for the website, app, and Roku.</p></section>
    <?php if ($notice): ?><p role="status"><?php echo esc_html($notice); ?></p><?php endif; ?>
    <h2>Cloudflare connection</h2><p><?php echo !empty($config['token']) ? 'API token saved. Enter a new token only to replace it.' : 'Enter the read-only Stream API token.'; ?></p>
    <form class="surfside-information-form" method="post"><?php wp_nonce_field('surfside_stream_manage', 'surfside_stream_nonce'); ?><input type="hidden" name="surfside_stream_action" value="token"><div class="surfside-information-field surfside-information-field-wide"><label for="stream_token">Cloudflare Stream API token</label><input id="stream_token" type="password" name="stream_token" autocomplete="new-password" placeholder="Leave blank to keep existing token"><p class="surfside-information-help">The existing token is never displayed. Leave this blank to keep it unchanged.</p></div><div><button class="button button-primary" type="submit">Save connection</button></div></form>
    <section class="surfside-staff-panel">
      <h2>Automatic go-live notifications</h2>
      <p>Cloudflare Stream is the authoritative go-live source. When your configured live input connects, Cloudflare sends one authenticated webhook to WordPress, which notifies app users subscribed to Livestream notifications. Twitch does not send go-live alerts.</p>
      <p><strong>Webhook URL:</strong> <code><?php echo esc_html($webhook_url); ?></code></p>
      <p><strong>Cloudflare Live Input ID:</strong> <code><?php echo esc_html(SURFSIDE_TOOLS_CF_LIVE_PUSH_INPUT); ?></code></p>
      <p><strong>Shared secret:</strong> <?php echo $has_webhook_secret ? 'Configured — hidden for security.' : 'Not configured yet.'; ?></p>
      <?php if ($new_webhook_secret !== '') : ?>
        <div role="status" class="surfside-information-field surfside-information-field-wide">
          <label for="cf_new_webhook_secret"><strong>Copy this secret now (shown only once)</strong></label>
          <input id="cf_new_webhook_secret" type="text" readonly autocomplete="off"
            value="<?php echo esc_attr($new_webhook_secret); ?>" onclick="this.select()">
          <p>Enter this exact value in Cloudflare’s generic webhook destination <strong>Secret</strong> field. Don't share it publicly. Regenerating invalidates the previous secret.</p>
        </div>
      <?php endif; ?>
      <form method="post"><?php wp_nonce_field('surfside_stream_manage', 'surfside_stream_nonce'); ?>
        <input type="hidden" name="surfside_stream_action" value="webhook_secret">
        <button class="button" type="submit"><?php echo $has_webhook_secret ? 'Rotate webhook secret' : 'Generate webhook secret'; ?></button>
      </form>
      <p>In Cloudflare: <strong>Alerts → Destinations → Webhooks → Create</strong>. Choose a generic webhook using the URL and secret above; then create a Stream Live alert for <code>live_input.connected</code>, restricted to this Live Input ID. The Cloudflare “Save and Test” sample should not send a phone notification.</p>
      <?php if (!empty($webhook_health['outcome'])) : ?>
        <p><strong>Last push handoff:</strong> <?php echo esc_html($webhook_health['outcome']); ?>
          <?php if (!empty($webhook_health['accepted'])) : ?> (<?php echo esc_html((int) $webhook_health['accepted']); ?> submitted to Expo)<?php endif; ?>
          <?php if (!empty($webhook_health['attempted_at'])) : ?> — <?php echo esc_html(gmdate('Y-m-d H:i:s', (int) $webhook_health['attempted_at'])); ?> UTC<?php endif; ?>
        </p>
      <?php else : ?><p>No Cloudflare go-live push has been attempted yet.</p><?php endif; ?>
    </section>
    <h2>Recordings</h2><p>New completed recordings are eligible for Last Service automatically. Exclude funerals, tests, and other recordings here. Excluding does not delete a Cloudflare video or revoke its direct URL.</p>
    <form method="post"><?php wp_nonce_field('surfside_stream_manage', 'surfside_stream_nonce'); ?><input type="hidden" name="surfside_stream_action" value="refresh"><button type="submit">Refresh recordings</button></form>
    <?php if (is_wp_error($recordings)): ?><p role="alert"><?php echo esc_html($recordings->get_error_message()); ?></p>
    <?php elseif (!$recordings): ?><p>No completed recordings found for this live input.</p>
    <?php else: ?><table class="widefat striped"><thead><tr><th>Recording</th><th>Date</th><th>Duration</th><th>Last Service</th></tr></thead><tbody>
    <?php foreach ($recordings as $video): $is_excluded = in_array($video['id'], $excluded, true); ?>
    <tr><td><?php echo esc_html($video['title']); ?></td><td><?php echo esc_html($video['created']); ?></td><td><?php echo esc_html(round($video['duration'] / 60)); ?> min</td><td>
    <form method="post"><?php wp_nonce_field('surfside_stream_manage', 'surfside_stream_nonce'); ?><input type="hidden" name="surfside_stream_action" value="exclude"><input type="hidden" name="recording_id" value="<?php echo esc_attr($video['id']); ?>"><input type="hidden" name="exclude" value="<?php echo $is_excluded ? '0' : '1'; ?>"><button type="submit"><?php echo $is_excluded ? 'Include' : 'Exclude'; ?></button></form>
    </td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
    <?php return ob_get_clean();
}
add_shortcode('surfside_staff_streaming', 'surfside_tools_staff_stream_shortcode');
// Streaming Management page is provisioned by the versioned, admin-only
// staff-page migration (staff-page-provisioning.php), not on visitor requests.
