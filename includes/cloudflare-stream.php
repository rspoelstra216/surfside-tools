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
        $live = is_wp_error($status) ? null : in_array(($status['status'] ?? ''), array('connected', 'reconnected'), true);
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
            } elseif ($action === 'refresh') {
                delete_transient('surfside_stream_recordings_v1');
                delete_transient('surfside_stream_live_v1');
                $notice = 'Cloudflare data refreshed.';
            }
        }
    }
    $config = surfside_tools_stream_config();
    $recordings = surfside_tools_stream_recordings();
    $excluded = get_option('surfside_tools_stream_excluded', array());
    if (!is_array($excluded)) $excluded = array();
    ob_start(); ?>
    <div class="surfside-staff-shell"><div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('site-settings')); ?>">← Church Settings</a></div>
    <section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Shared Services</p><h1>Streaming Management</h1><p>Manage Cloudflare Stream for the website, app, and Roku.</p></section>
    <?php if ($notice): ?><p role="status"><?php echo esc_html($notice); ?></p><?php endif; ?>
    <h2>Cloudflare connection</h2><p><?php echo !empty($config['token']) ? 'API token saved. Enter a new token only to replace it.' : 'Enter the read-only Stream API token.'; ?></p>
    <form class="surfside-information-form" method="post"><?php wp_nonce_field('surfside_stream_manage', 'surfside_stream_nonce'); ?><input type="hidden" name="surfside_stream_action" value="token"><div class="surfside-information-field surfside-information-field-wide"><label for="stream_token">Cloudflare Stream API token</label><input id="stream_token" type="password" name="stream_token" autocomplete="new-password" placeholder="Leave blank to keep existing token"><p class="surfside-information-help">The existing token is never displayed. Leave this blank to keep it unchanged.</p></div><div><button class="button button-primary" type="submit">Save connection</button></div></form>
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
add_action('init', function() {
    if (!function_exists('surfside_tools_ensure_staff_page')) return;
    $parent = get_page_by_path('dashboard/site-settings');
    if ($parent) surfside_tools_ensure_staff_page('Streaming Management', 'streaming', '[surfside_staff_streaming]', (int) $parent->ID);
}, 90);
