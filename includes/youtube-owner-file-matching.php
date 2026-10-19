<?php
/**
 * Owner-authorized YouTube upload filenames for the historical Roku/R2 import.
 * Explicit, staff-only actions; no public requests, media uploads, or mutations
 * to the canonical YouTube message catalog.
 */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_YT_OWNER_TOKEN_OPTION = 'surfside_yt_owner_oauth_tokens';
const SURFSIDE_YT_OWNER_FILES_OPTION = 'surfside_yt_owner_file_details';
const SURFSIDE_YT_OWNER_STATE_OPTION = 'surfside_yt_owner_file_sync_state';
const SURFSIDE_YT_OWNER_TICK = 'surfside_yt_owner_file_sync_tick';

function surfside_yt_owner_client() {
    return array(
        'id' => defined('SURFSIDE_YOUTUBE_OAUTH_CLIENT_ID') ? trim((string) SURFSIDE_YOUTUBE_OAUTH_CLIENT_ID) : '',
        'secret' => defined('SURFSIDE_YOUTUBE_OAUTH_CLIENT_SECRET') ? trim((string) SURFSIDE_YOUTUBE_OAUTH_CLIENT_SECRET) : '',
    );
}

function surfside_yt_owner_callback_url() {
    return surfside_tools_r2_manager_url(array('owner_oauth' => 'callback'));
}

function surfside_yt_owner_notice($message, $error = false) {
    set_transient('surfside_yt_owner_notice_' . get_current_user_id(),
        array('text' => sanitize_text_field($message), 'error' => (bool) $error), 180);
}

function surfside_yt_owner_tokens() {
    $tokens = get_option(SURFSIDE_YT_OWNER_TOKEN_OPTION, array());
    return is_array($tokens) ? $tokens : array();
}

/** Tokens belong to the WordPress server, never to the browser or API. */
function surfside_yt_owner_token_request($body) {
    $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
        'timeout' => 15,
        'redirection' => 0,
        'body' => $body,
    ));
    if (is_wp_error($response)) return new WP_Error('surfside_yt_owner_network', 'Google token service could not be reached.');
    $value = json_decode(wp_remote_retrieve_body($response), true);
    if (wp_remote_retrieve_response_code($response) !== 200 || !is_array($value) || empty($value['access_token'])) {
        return new WP_Error('surfside_yt_owner_token', 'Google authorization failed or expired. Reconnect the YouTube owner account.');
    }
    return $value;
}

function surfside_yt_owner_access_token() {
    $stored = surfside_yt_owner_tokens();
    if (!empty($stored['access_token']) && (int) ($stored['expires_at'] ?? 0) > time() + 90) {
        return $stored['access_token'];
    }
    $client = surfside_yt_owner_client();
    if (empty($stored['refresh_token']) || $client['id'] === '' || $client['secret'] === '') {
        return new WP_Error('surfside_yt_owner_not_connected', 'Connect the YouTube channel owner in Roku Copies first.');
    }
    $fresh = surfside_yt_owner_token_request(array(
        'client_id' => $client['id'], 'client_secret' => $client['secret'],
        'refresh_token' => $stored['refresh_token'], 'grant_type' => 'refresh_token',
    ));
    if (is_wp_error($fresh)) return $fresh;
    $stored['access_token'] = $fresh['access_token'];
    $stored['expires_at'] = time() + max(60, (int) ($fresh['expires_in'] ?? 3600));
    update_option(SURFSIDE_YT_OWNER_TOKEN_OPTION, $stored, false);
    return $stored['access_token'];
}

function surfside_yt_owner_get($resource, $params, $token) {
    if (!in_array($resource, array('channels', 'videos'), true)) {
        return new WP_Error('surfside_yt_owner_resource', 'Unsupported YouTube resource.');
    }
    $url = add_query_arg($params, 'https://www.googleapis.com/youtube/v3/' . $resource);
    $response = wp_remote_get($url, array(
        'timeout' => 18, 'redirection' => 0,
        'headers' => array('Authorization' => 'Bearer ' . $token),
    ));
    if (is_wp_error($response)) return new WP_Error('surfside_yt_owner_network', 'YouTube did not respond; retry later.');
    $status = wp_remote_retrieve_response_code($response);
    $result = json_decode(wp_remote_retrieve_body($response), true);
    if ($status !== 200 || !is_array($result)) {
        return new WP_Error('surfside_yt_owner_http',
            $status === 403 ? 'YouTube denied owner metadata or quota was exceeded. Verify channel authorization and quota.'
                : 'YouTube owner metadata request failed (HTTP ' . absint($status) . ').');
    }
    return $result;
}

function surfside_yt_owner_master_rows() {
    $master = surfside_tools_r2_master_id();
    if ($master === '') return new WP_Error('surfside_yt_owner_no_master', 'Synchronize the master Messages playlist first.');
    global $wpdb;
    $tables = surfside_tools_youtube_tables();
    return $wpdb->get_results($wpdb->prepare(
        "SELECT v.video_id, v.title, v.sermon_date FROM {$tables['videos']} v
         INNER JOIN {$tables['members']} m ON m.video_id = v.video_id
         WHERE m.playlist_id = %s ORDER BY v.sermon_date DESC, v.video_id", $master
    ), ARRAY_A);
}

function surfside_yt_owner_connect() {
    $client = surfside_yt_owner_client();
    $redirect = surfside_yt_owner_callback_url();
    if ($client['id'] === '' || $client['secret'] === '' || strpos($redirect, 'https://') !== 0) {
        return new WP_Error('surfside_yt_owner_config', 'Set the OAuth client ID and secret in wp-config.php and use an HTTPS dashboard.');
    }
    $sync = surfside_tools_youtube_state();
    if (empty($sync['channel_id'])) {
        return new WP_Error('surfside_yt_owner_channel', 'Complete the YouTube archive sync before connecting the owner.');
    }
    $nonce = bin2hex(random_bytes(24));
    set_transient('surfside_yt_owner_state_' . get_current_user_id(),
        array('state' => $nonce, 'channel_id' => $sync['channel_id']), 10 * MINUTE_IN_SECONDS);
    $url = add_query_arg(array(
        'client_id' => $client['id'], 'redirect_uri' => $redirect,
        'response_type' => 'code', 'scope' => 'https://www.googleapis.com/auth/youtube.readonly',
        'access_type' => 'offline', 'prompt' => 'consent', 'state' => $nonce,
    ), 'https://accounts.google.com/o/oauth2/v2/auth');
    wp_redirect($url, 302, 'Surfside Tools'); // Google is intentionally external.
    exit;
}

function surfside_yt_owner_callback() {
    $key = 'surfside_yt_owner_state_' . get_current_user_id();
    $expected = get_transient($key);
    delete_transient($key); // Single use, including failed attempts.
    $state = isset($_GET['state']) && is_string($_GET['state']) ? wp_unslash($_GET['state']) : '';
    $code = isset($_GET['code']) && is_string($_GET['code']) ? wp_unslash($_GET['code']) : '';
    if (!is_array($expected) || !is_string($expected['state'] ?? null)
        || !hash_equals($expected['state'], $state) || $code === '' || isset($_GET['error'])) {
        return new WP_Error('surfside_yt_owner_state', 'YouTube authorization was canceled, expired, or did not match your session.');
    }
    $client = surfside_yt_owner_client();
    $token = surfside_yt_owner_token_request(array(
        'code' => $code, 'client_id' => $client['id'], 'client_secret' => $client['secret'],
        'redirect_uri' => surfside_yt_owner_callback_url(), 'grant_type' => 'authorization_code',
    ));
    if (is_wp_error($token)) return $token;
    if (empty($token['refresh_token'])) {
        return new WP_Error('surfside_yt_owner_refresh', 'Google did not issue an offline refresh token. Reconnect and approve access.');
    }
    // Prevent importing another Google account's / Brand Account's filenames.
    $channels = surfside_yt_owner_get('channels', array('part' => 'id', 'mine' => 'true'), $token['access_token']);
    if (is_wp_error($channels)) return $channels;
    $authorized = false;
    foreach (($channels['items'] ?? array()) as $channel) {
        if (($channel['id'] ?? '') === ($expected['channel_id'] ?? '')) $authorized = true;
    }
    if (!$authorized) return new WP_Error('surfside_yt_owner_wrong_channel',
        'The authorized Google/Brand Account does not own the synchronized Surfside YouTube channel.');
    update_option(SURFSIDE_YT_OWNER_TOKEN_OPTION, array(
        'access_token' => $token['access_token'],
        'refresh_token' => $token['refresh_token'],
        'expires_at' => time() + max(60, (int) ($token['expires_in'] ?? 3600)),
        'channel_id' => $expected['channel_id'],
    ), false);
    return true;
}

function surfside_yt_owner_schedule($seconds = 10) {
    if (!wp_next_scheduled(SURFSIDE_YT_OWNER_TICK)) {
        wp_schedule_single_event(time() + $seconds, SURFSIDE_YT_OWNER_TICK);
    }
}

function surfside_yt_owner_start_scan() {
    $access = surfside_yt_owner_access_token();
    if (is_wp_error($access)) return $access;
    $rows = surfside_yt_owner_master_rows();
    if (is_wp_error($rows)) return $rows;
    $ids = array();
    foreach ($rows as $row) {
        if (preg_match('/^[A-Za-z0-9_-]{11}$/D', (string) $row['video_id'])) $ids[] = $row['video_id'];
    }
    if (!$ids) return new WP_Error('surfside_yt_owner_empty', 'No master Messages videos were found.');
    $previous = get_option(SURFSIDE_YT_OWNER_STATE_OPTION, array());
    if (($previous['status'] ?? '') === 'running') {
        return new WP_Error('surfside_yt_owner_busy', 'Filename scan is already running.');
    }
    update_option(SURFSIDE_YT_OWNER_FILES_OPTION, array(), false);
    update_option(SURFSIDE_YT_OWNER_STATE_OPTION, array(
        'status' => 'running', 'ids' => $ids, 'cursor' => 0,
        'total' => count($ids), 'available' => 0, 'missing' => 0, 'message' => 'Queued',
    ), false);
    wp_clear_scheduled_hook(SURFSIDE_YT_OWNER_TICK);
    surfside_yt_owner_schedule(5);
    return true;
}

/** One bounded videos.list call per WP-Cron execution (50 IDs, 1 quota unit). */
function surfside_yt_owner_scan_tick() {
    $state = get_option(SURFSIDE_YT_OWNER_STATE_OPTION, array());
    if (!is_array($state) || ($state['status'] ?? '') !== 'running') return;
    $ids = array_slice($state['ids'] ?? array(), (int) ($state['cursor'] ?? 0), 50);
    if (!$ids) {
        $state['status'] = 'complete';
        $state['message'] = 'Scan complete.';
        unset($state['ids']);
        update_option(SURFSIDE_YT_OWNER_STATE_OPTION, $state, false);
        return;
    }
    $token = surfside_yt_owner_access_token();
    $response = is_wp_error($token) ? $token : surfside_yt_owner_get('videos', array(
        'part' => 'fileDetails,processingDetails',
        'id' => implode(',', $ids),
    ), $token);
    if (is_wp_error($response)) {
        $state['status'] = 'error';
        $state['message'] = $response->get_error_message();
        update_option(SURFSIDE_YT_OWNER_STATE_OPTION, $state, false);
        return;
    }
    $known = array_fill_keys($ids, true);
    $files = get_option(SURFSIDE_YT_OWNER_FILES_OPTION, array());
    if (!is_array($files)) $files = array();
    foreach ($ids as $id) $files[$id] = array('file_name' => '', 'size_bytes' => '');
    foreach (($response['items'] ?? array()) as $item) {
        $id = (string) ($item['id'] ?? '');
        if (!isset($known[$id])) continue;
        $name = (string) ($item['fileDetails']['fileName'] ?? '');
        $name = trim(sanitize_text_field(basename(str_replace('\\', '/', $name))));
        $size = (string) ($item['fileDetails']['fileSize'] ?? '');
        if ($name !== '' && preg_match('/^[0-9]+$/D', $size) && (float) $size > 0) {
            $files[$id] = array('file_name' => $name, 'size_bytes' => $size);
            $state['available']++;
        } else {
            $state['missing']++;
        }
    }
    // Items not returned by YouTube still need explicit review.
    $state['missing'] += count($ids) - count(array_filter($response['items'] ?? array(), function ($item) use ($known) {
        return isset($known[$item['id'] ?? '']);
    }));
    $state['cursor'] = (int) $state['cursor'] + count($ids);
    if ($state['cursor'] >= $state['total']) {
        $state['status'] = 'complete';
        $state['message'] = 'Filename scan complete.';
        unset($state['ids']);
    } else {
        $state['message'] = 'Scanning owner filenames…';
        surfside_yt_owner_schedule(15);
    }
    update_option(SURFSIDE_YT_OWNER_FILES_OPTION, $files, false);
    update_option(SURFSIDE_YT_OWNER_STATE_OPTION, $state, false);
}
add_action(SURFSIDE_YT_OWNER_TICK, 'surfside_yt_owner_scan_tick');

/** Strictly relative source paths; the user's local importer remains read-only until --upload. */
function surfside_yt_owner_valid_source_path($path) {
    if (!is_string($path) || $path === '' || strlen($path) > 1500 || preg_match('/[\x00-\x1f\x7f]/', $path)) return false;
    $path = str_replace('\\', '/', $path);
    if ($path[0] === '/' || preg_match('/^[A-Za-z]:/', $path)) return false;
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.' || $part === '..') return false;
    }
    return strtolower(substr($path, -4)) === '.mp4';
}

/** Pure deterministic matcher. A name alone never overrides a conflicting size. */
function surfside_yt_owner_match_file($detail, $index) {
    $name = (string) ($detail['file_name'] ?? '');
    $expected = (string) ($detail['size_bytes'] ?? '');
    if ($name === '' || !preg_match('/^[0-9]+$/D', $expected)) {
        return array('status' => 'youtube_details_unavailable', 'source_path' => '');
    }
    if (!isset($index[$name])) return array('status' => 'no_filename_match', 'source_path' => '');
    $matches = $index[$name];
    if (count($matches) !== 1) return array('status' => 'duplicate_filename', 'source_path' => '');
    $local = $matches[0];
    if ($local['size_bytes'] === '' || !preg_match('/^[0-9]+$/D', $local['size_bytes'])) {
        return array('status' => 'local_size_missing', 'source_path' => '');
    }
    if (ltrim($expected, '0') !== ltrim($local['size_bytes'], '0')) {
        return array('status' => 'size_mismatch', 'source_path' => '');
    }
    return array('status' => 'matched', 'source_path' => $local['source_path']);
}

function surfside_yt_owner_csv_safe($value) {
    $value = (string) $value;
    return preg_match('/^\s*[=+@-]/', $value) ? "'" . $value : $value;
}

function surfside_yt_owner_export_matches() {
    $state = get_option(SURFSIDE_YT_OWNER_STATE_OPTION, array());
    if (($state['status'] ?? '') !== 'complete') return new WP_Error('surfside_yt_owner_pending', 'Complete the owner filename scan before exporting matches.');
    if (!isset($_FILES['inventory']) || !is_array($_FILES['inventory'])
        || (int) ($_FILES['inventory']['error'] ?? -1) !== UPLOAD_ERR_OK
        || (int) ($_FILES['inventory']['size'] ?? 0) > 3 * 1024 * 1024
        || !is_uploaded_file($_FILES['inventory']['tmp_name'] ?? '')) {
        return new WP_Error('surfside_yt_owner_inventory', 'Upload a NAS inventory CSV (3 MiB maximum).');
    }
    $handle = fopen($_FILES['inventory']['tmp_name'], 'r');
    if (!$handle) return new WP_Error('surfside_yt_owner_inventory', 'Could not read the inventory.');
    $headers = fgetcsv($handle);
    if (is_array($headers)) $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    $path_pos = is_array($headers) ? array_search('source_path', $headers, true) : false;
    $size_pos = is_array($headers) ? array_search('size_bytes', $headers, true) : false;
    if ($path_pos === false || $size_pos === false) {
        fclose($handle);
        return new WP_Error('surfside_yt_owner_columns', 'Inventory needs source_path and size_bytes columns.');
    }
    $index = array();
    $rows_read = 0;
    while (($row = fgetcsv($handle)) !== false) {
        if (++$rows_read > 20000) {
            fclose($handle);
            return new WP_Error('surfside_yt_owner_large', 'Inventory contains too many rows.');
        }
        $path = $row[$path_pos] ?? '';
        $size = trim((string) ($row[$size_pos] ?? ''));
        if (!surfside_yt_owner_valid_source_path($path) || !preg_match('/^[0-9]+$/D', $size)) continue;
        $name = basename(str_replace('\\', '/', $path));
        $index[$name][] = array('source_path' => $path, 'size_bytes' => $size);
    }
    fclose($handle);
    $messages = surfside_yt_owner_master_rows();
    if (is_wp_error($messages)) return $messages;
    $details = get_option(SURFSIDE_YT_OWNER_FILES_OPTION, array());
    if (!is_array($details)) $details = array();
    // All validation must finish before output headers are sent.
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="surfside-r2-matched-manifest.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('video_id', 'title', 'sermon_date', 'source_path',
        'match_status', 'youtube_file_name', 'youtube_size_bytes'));
    foreach ($messages as $message) {
        $id = (string) $message['video_id'];
        $detail = $details[$id] ?? array();
        $match = surfside_yt_owner_match_file($detail, $index);
        $source = $match['source_path'];
        // Avoid formula injection without corrupting a runnable local path.
        if (preg_match('/^\s*[=+@-]/', $source)) {
            $source = '';
            $match['status'] = 'review_unsafe_csv_path';
        }
        fputcsv($out, array($id, surfside_yt_owner_csv_safe($message['title']),
            $message['sermon_date'], $source, $match['status'],
            surfside_yt_owner_csv_safe($detail['file_name'] ?? ''), (string) ($detail['size_bytes'] ?? '')));
    }
    fclose($out);
    exit;
}

function surfside_yt_owner_handle_post() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Not authorized.', '', array('response' => 403));
    }
    check_admin_referer('surfside_yt_owner_files', 'surfside_yt_owner_nonce');
    $action = isset($_POST['owner_action']) && is_string($_POST['owner_action'])
        ? sanitize_key(wp_unslash($_POST['owner_action'])) : '';
    if ($action === 'connect') surfside_yt_owner_connect();
    if ($action === 'export') {
        $result = surfside_yt_owner_export_matches();
        surfside_yt_owner_notice(is_wp_error($result) ? $result->get_error_message() : 'Manifest prepared.', is_wp_error($result));
    } elseif ($action === 'disconnect') {
        delete_option(SURFSIDE_YT_OWNER_TOKEN_OPTION);
        delete_option(SURFSIDE_YT_OWNER_FILES_OPTION);
        delete_option(SURFSIDE_YT_OWNER_STATE_OPTION);
        wp_clear_scheduled_hook(SURFSIDE_YT_OWNER_TICK);
        surfside_yt_owner_notice('Locally disconnected the YouTube owner and cleared imported filenames. Revoke access in Google Account settings if desired.');
    } elseif ($action === 'scan') {
        $result = surfside_yt_owner_start_scan();
        surfside_yt_owner_notice(is_wp_error($result) ? $result->get_error_message() : 'Owner filename scan queued. Return here to check progress.', is_wp_error($result));
    } elseif ($action !== 'export') {
        surfside_yt_owner_notice('Unknown owner action.', true);
    }
    wp_safe_redirect(surfside_tools_r2_manager_url(), 303);
    exit;
}
add_action('admin_post_surfside_yt_owner_files', 'surfside_yt_owner_handle_post');

function surfside_yt_owner_frontend_dispatch() {
    if (!is_page('site-management') || !isset($_GET['view']) || sanitize_key(wp_unslash($_GET['view'])) !== 'roku-media') return;
    if (isset($_GET['owner_oauth']) && $_GET['owner_oauth'] === 'callback') {
        if (!is_user_logged_in() || !current_user_can('manage_options')) wp_die('Not authorized.', '', array('response' => 403));
        $result = surfside_yt_owner_callback();
        surfside_yt_owner_notice(is_wp_error($result) ? $result->get_error_message() : 'YouTube owner connected to the synchronized channel.', is_wp_error($result));
        wp_safe_redirect(surfside_tools_r2_manager_url(), 303);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])
        && is_string($_POST['action'])
        && sanitize_key(wp_unslash($_POST['action'])) === 'surfside_yt_owner_files') {
        surfside_yt_owner_handle_post();
    }
}
add_action('template_redirect', 'surfside_yt_owner_frontend_dispatch', 1);

function surfside_yt_owner_panel() {
    if (!current_user_can('manage_options')) return '';
    $config = surfside_yt_owner_client();
    $configured = $config['id'] !== '' && $config['secret'] !== '';
    $connected = !empty(surfside_yt_owner_tokens()['refresh_token']);
    $state = get_option(SURFSIDE_YT_OWNER_STATE_OPTION, array());
    if (!is_array($state)) $state = array();
    $key = 'surfside_yt_owner_notice_' . get_current_user_id();
    $notice = get_transient($key);
    delete_transient($key);
    $action_url = surfside_tools_r2_manager_url();
    ob_start(); ?>
    <section class="surfside-yt-panel" id="surfside-owner-file-matching">
      <h2>Historical import · Match NAS filenames</h2>
      <p class="surfside-yt-muted">Optional owner-only YouTube access retrieves the original uploaded filename and size. Public message syncing still uses the existing API key. No videos are uploaded or changed here.</p>
      <?php if (is_array($notice)) : ?><p role="status"><?php echo esc_html($notice['text']); ?></p><?php endif; ?>
      <?php if (!$configured) : ?>
        <p>To enable, add <code>SURFSIDE_YOUTUBE_OAUTH_CLIENT_ID</code> and <code>SURFSIDE_YOUTUBE_OAUTH_CLIENT_SECRET</code> to wp-config.php (Google OAuth Web Application).</p>
      <?php endif; ?>
      <p><strong>Google OAuth redirect URI:</strong><br><code style="overflow-wrap:anywhere"><?php echo esc_html(surfside_yt_owner_callback_url()); ?></code></p>
      <p><strong>Owner connection:</strong> <?php echo $connected ? 'Connected' : 'Not connected'; ?></p>
      <form method="post" action="<?php echo esc_url($action_url); ?>">
        <input type="hidden" name="action" value="surfside_yt_owner_files">
        <input type="hidden" name="owner_action" value="<?php echo $connected ? 'disconnect' : 'connect'; ?>">
        <?php wp_nonce_field('surfside_yt_owner_files', 'surfside_yt_owner_nonce'); ?>
        <button type="submit" <?php disabled(!$configured); ?>><?php echo $connected ? 'Disconnect owner account' : 'Connect YouTube owner'; ?></button>
      </form>
      <?php if ($connected) : ?>
        <form method="post" action="<?php echo esc_url($action_url); ?>" style="margin-top:12px">
          <input type="hidden" name="action" value="surfside_yt_owner_files">
          <input type="hidden" name="owner_action" value="scan">
          <?php wp_nonce_field('surfside_yt_owner_files', 'surfside_yt_owner_nonce'); ?>
          <button type="submit" <?php disabled(($state['status'] ?? '') === 'running'); ?>>Retrieve original filenames from YouTube</button>
        </form>
        <p class="surfside-yt-muted">Scan: <?php echo esc_html((string) ($state['status'] ?? 'not started')); ?>;
          <?php echo esc_html((int) ($state['cursor'] ?? 0)); ?> / <?php echo esc_html((int) ($state['total'] ?? 0)); ?> checked;
          <?php echo esc_html((int) ($state['available'] ?? 0)); ?> with usable filenames.
          <?php echo esc_html((string) ($state['message'] ?? '')); ?></p>
        <?php if (($state['status'] ?? '') === 'running') : ?>
          <p><a href="<?php echo esc_url($action_url . '#surfside-owner-file-matching'); ?>">Check progress</a></p>
        <?php elseif (($state['status'] ?? '') === 'complete') : ?>
          <p>Generate an inventory on your NAS or Mac with <code>scripts/inventory-sermon-files.py</code>, then upload that small CSV to download a matched manifest. Only unique filename AND byte-size matches populate <code>source_path</code>; all other rows require review.</p>
          <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($action_url); ?>">
            <input type="hidden" name="action" value="surfside_yt_owner_files">
            <input type="hidden" name="owner_action" value="export">
            <?php wp_nonce_field('surfside_yt_owner_files', 'surfside_yt_owner_nonce'); ?>
            <label for="surfside-owner-inventory">NAS inventory CSV</label>
            <input id="surfside-owner-inventory" type="file" name="inventory" accept=".csv,text/csv" required>
            <button type="submit">Download matched R2 manifest</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <?php return ob_get_clean();
}
