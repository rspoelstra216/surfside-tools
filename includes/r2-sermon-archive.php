<?php
/**
 * R2 copies of YouTube sermons for future Roku playback.
 *
 * YouTube is the catalog. R2 objects are identified by the canonical YouTube
 * video ID; no video metadata is duplicated or changed here.
 * No R2 calls, migrations, or broad database reads on public requests.
 */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_R2_SERMON_SCHEMA_OPTION = 'surfside_r2_sermon_schema';
const SURFSIDE_R2_SERMON_SCHEMA_VERSION = 1;
const SURFSIDE_R2_SERMON_PREFIX = 'sermons/';

function surfside_tools_r2_video_id($value) {
    return is_string($value) && (bool) preg_match('/^[A-Za-z0-9_-]{11}$/D', $value);
}

function surfside_tools_r2_sermon_key($id) {
    return SURFSIDE_R2_SERMON_PREFIX . $id . '.mp4';
}

function surfside_tools_r2_config() {
    $values = array();
    foreach (array(
        'account' => 'SURFSIDE_R2_ACCOUNT_ID',
        'bucket' => 'SURFSIDE_R2_BUCKET',
        'access' => 'SURFSIDE_R2_ACCESS_KEY_ID',
        'secret' => 'SURFSIDE_R2_SECRET_ACCESS_KEY',
        'public_base' => 'SURFSIDE_R2_PUBLIC_BASE_URL',
    ) as $key => $constant) {
        $values[$key] = defined($constant) ? trim((string) constant($constant)) : '';
    }
    $values['ready'] = (bool) (
        preg_match('/^[0-9a-f]{32}$/i', $values['account'])
        && preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $values['bucket'])
        && $values['access'] !== '' && $values['secret'] !== ''
    );
    return $values;
}

/** Self-contained AWS Signature V4 for R2's S3-compatible API. */
function surfside_tools_r2_presign($method, $key = '', $query = array(), $expires = 900, $content_type = '') {
    $cfg = surfside_tools_r2_config();
    if (!$cfg['ready'] || !in_array($method, array('GET','HEAD','PUT'), true)) {
        return new WP_Error('r2_not_configured', 'R2 is not configured for this operation.');
    }
    $host = strtolower($cfg['account']) . '.r2.cloudflarestorage.com';
    $path = '/' . rawurlencode($cfg['bucket']);
    if ($key !== '') {
        $path .= '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
    }
    $when = gmdate('Ymd\THis\Z');
    $day = substr($when, 0, 8);
    $scope = $day . '/auto/s3/aws4_request';
    $signed_headers = $content_type !== '' ? 'content-type;host' : 'host';
    $query = array_merge($query, array(
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Content-Sha256' => 'UNSIGNED-PAYLOAD',
        'X-Amz-Credential' => $cfg['access'] . '/' . $scope,
        'X-Amz-Date' => $when,
        'X-Amz-Expires' => (string) max(1, min(14400, (int) $expires)),
        'X-Amz-SignedHeaders' => $signed_headers,
    ));
    ksort($query, SORT_STRING);
    $pairs = array();
    foreach ($query as $name => $value) {
        $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
    }
    $canonical_query = implode('&', $pairs);
    $headers = ($content_type !== '' ? 'content-type:' . $content_type . "\n" : '') . 'host:' . $host . "\n";
    $canonical = $method . "\n" . $path . "\n" . $canonical_query . "\n" .
        $headers . "\n" . $signed_headers . "\nUNSIGNED-PAYLOAD";
    $to_sign = "AWS4-HMAC-SHA256\n" . $when . "\n" . $scope . "\n" . hash('sha256', $canonical);
    $k_day = hash_hmac('sha256', $day, 'AWS4' . $cfg['secret'], true);
    $k_region = hash_hmac('sha256', 'auto', $k_day, true);
    $k_service = hash_hmac('sha256', 's3', $k_region, true);
    $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
    return 'https://' . $host . $path . '?' . $canonical_query .
        '&X-Amz-Signature=' . hash_hmac('sha256', $to_sign, $k_signing);
}

function surfside_tools_r2_sermon_table() {
    global $wpdb;
    return $wpdb->prefix . 'surfside_r2_sermons';
}

/** Only initialized on a staff manager visit or explicit staff action. */
function surfside_tools_r2_ensure_table() {
    if ((int) get_option(SURFSIDE_R2_SERMON_SCHEMA_OPTION, 0) >= SURFSIDE_R2_SERMON_SCHEMA_VERSION) return true;
    global $wpdb;
    if (!function_exists('dbDelta')) require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = surfside_tools_r2_sermon_table();
    $collation = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
        video_id varchar(32) NOT NULL,
        object_key varchar(120) NOT NULL,
        size_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
        etag varchar(128) NOT NULL DEFAULT '',
        status varchar(20) NOT NULL DEFAULT 'missing',
        verified_at datetime NOT NULL,
        PRIMARY KEY  (video_id),
        KEY status (status)
    ) {$collation};");
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    if ($found !== $table) return new WP_Error('r2_schema', 'R2 archive status table could not be created.');
    update_option(SURFSIDE_R2_SERMON_SCHEMA_OPTION, SURFSIDE_R2_SERMON_SCHEMA_VERSION, false);
    return true;
}

function surfside_tools_r2_master_id() {
    if (!function_exists('surfside_tools_youtube_review_master_id')) return '';
    return surfside_tools_youtube_review_master_id();
}

function surfside_tools_r2_is_master_video($id) {
    if (!surfside_tools_r2_video_id($id) || surfside_tools_r2_master_id() === '') return false;
    global $wpdb;
    $tables = surfside_tools_youtube_tables();
    return (string) $wpdb->get_var($wpdb->prepare(
        "SELECT video_id FROM {$tables['members']} WHERE playlist_id = %s AND video_id = %s LIMIT 1",
        surfside_tools_r2_master_id(), $id
    )) === $id;
}

function surfside_tools_r2_object_head($id) {
    $url = surfside_tools_r2_presign('HEAD', surfside_tools_r2_sermon_key($id));
    if (is_wp_error($url)) return $url;
    $response = wp_remote_head($url, array('timeout' => 20, 'redirection' => 0, 'reject_unsafe_urls' => true));
    if (is_wp_error($response)) return new WP_Error('r2_connection', 'R2 could not be contacted.');
    $status = (int) wp_remote_retrieve_response_code($response);
    if ($status === 404) return null;
    if ($status !== 200) return new WP_Error('r2_head', 'R2 returned HTTP ' . $status . '. Verify the token and bucket permissions.');
    return array(
        'size' => (int) wp_remote_retrieve_header($response, 'content-length'),
        'type' => strtolower(trim((string) wp_remote_retrieve_header($response, 'content-type'))),
        'etag' => trim((string) wp_remote_retrieve_header($response, 'etag'), '"'),
    );
}

function surfside_tools_r2_record($id, $size, $etag) {
    global $wpdb;
    return $wpdb->replace(surfside_tools_r2_sermon_table(), array(
        'video_id' => $id,
        'object_key' => surfside_tools_r2_sermon_key($id),
        'size_bytes' => (int) $size,
        'etag' => substr((string) $etag, 0, 128),
        'status' => 'ready',
        'verified_at' => current_time('mysql', true),
    ), array('%s','%s','%d','%s','%s','%s')) !== false;
}

function surfside_tools_r2_rest_permission() {
    return is_user_logged_in() && current_user_can('upload_files');
}

/** Private REST calls: browser sends the file directly to R2, never to PHP. */
function surfside_tools_r2_upload_ticket($request) {
    $id = $request->get_param('video_id');
    $size = $request->get_param('size');
    if (!surfside_tools_r2_video_id($id) || !surfside_tools_r2_is_master_video($id)) {
        return new WP_Error('r2_invalid_id', 'Select a video in the master YouTube Messages playlist.', array('status' => 400));
    }
    if (!is_numeric($size) || (string) (int) $size !== (string) $size
        || (int) $size < 1024 * 1024 || (int) $size > 5 * 1024 * 1024 * 1024) {
        return new WP_Error('r2_invalid_size', 'Choose an MP4 between 1 MB and 5 GiB. Larger files must use the bulk importer.', array('status' => 400));
    }
    $schema = surfside_tools_r2_ensure_table();
    if (is_wp_error($schema)) return $schema;
    $existing = surfside_tools_r2_object_head($id);
    if (is_wp_error($existing)) return $existing;
    if ($existing !== null) {
        return new WP_Error('r2_exists', 'This video already has an R2 object. Use Reconcile to register it; overwrites are blocked.', array('status' => 409));
    }
    $url = surfside_tools_r2_presign('PUT', surfside_tools_r2_sermon_key($id), array(), 14400, 'video/mp4');
    if (is_wp_error($url)) return $url;
    set_transient('surfside_r2_upload_' . get_current_user_id() . '_' . $id, (int) $size, 4 * HOUR_IN_SECONDS);
    return rest_ensure_response(array('upload_url' => $url, 'video_id' => $id, 'expires_seconds' => 14400));
}

function surfside_tools_r2_upload_complete($request) {
    $id = $request->get_param('video_id');
    if (!surfside_tools_r2_video_id($id) || !surfside_tools_r2_is_master_video($id)) {
        return new WP_Error('r2_invalid_id', 'Unknown YouTube message.', array('status' => 400));
    }
    $key = 'surfside_r2_upload_' . get_current_user_id() . '_' . $id;
    $expected = (int) get_transient($key);
    if ($expected <= 0) return new WP_Error('r2_expired', 'Upload session expired. Start another upload.', array('status' => 400));
    $head = surfside_tools_r2_object_head($id);
    if (is_wp_error($head)) return $head;
    if ($head === null || $head['size'] !== $expected || $head['type'] !== 'video/mp4') {
        return new WP_Error('r2_unverified', 'Object is missing or its size/content type does not match the selected MP4.', array('status' => 409));
    }
    $schema = surfside_tools_r2_ensure_table();
    if (is_wp_error($schema)) return $schema;
    if (!surfside_tools_r2_record($id, $head['size'], $head['etag'])) {
        return new WP_Error('r2_write', 'Uploaded to R2, but could not save verification. Use Reconcile.', array('status' => 500));
    }
    delete_transient($key);
    // Never hold up Roku verification if optional podcast processing is unavailable.
    // Only a successfully completed staff weekly upload queues audio, not migration reconciliation.
    $podcast = function_exists('surfside_tools_podcast_queue_verified')
        ? surfside_tools_podcast_queue_verified($id)
        : array('status' => 'not_configured');
    return rest_ensure_response(array('verified' => true, 'video_id' => $id, 'bytes' => $head['size'], 'podcast' => $podcast));
}

add_action('rest_api_init', function () {
    register_rest_route('surfside/v1', '/r2/sermons/ticket', array(
        'methods' => 'POST', 'callback' => 'surfside_tools_r2_upload_ticket',
        'permission_callback' => 'surfside_tools_r2_rest_permission',
    ));
    register_rest_route('surfside/v1', '/r2/sermons/complete', array(
        'methods' => 'POST', 'callback' => 'surfside_tools_r2_upload_complete',
        'permission_callback' => 'surfside_tools_r2_rest_permission',
    ));
});

/**
 * Front-end staff upload authorization.
 *
 * The staff dashboard's WordPress login works on this page, but its REST
 * cookie nonce can be rejected by the site's auth/security layers. Reuse
 * WordPress's regular authenticated page POST flow (as the YouTube manager
 * already does), with its own action nonce and capability check. This
 * request contains ONLY a video ID and byte count: the MP4 still goes
 * directly from the browser to R2 via a presigned PUT.
 */
function surfside_tools_r2_browser_upload_dispatch($data) {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return new WP_Error('r2_forbidden', 'Sign in with an authorized staff account to upload sermons.', array('status' => 403));
    }
    $nonce = isset($data['r2_nonce']) && is_string($data['r2_nonce']) ? $data['r2_nonce'] : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'surfside_r2_browser_upload')) {
        return new WP_Error('r2_bad_nonce', 'Upload authorization expired. Reload the Roku Copies page and try again.', array('status' => 403));
    }
    $mode = isset($data['mode']) && is_string($data['mode']) ? sanitize_key($data['mode']) : '';
    if (!in_array($mode, array('ticket', 'complete'), true)) {
        return new WP_Error('r2_bad_mode', 'Unknown R2 upload operation.', array('status' => 400));
    }
    $request = new WP_REST_Request('POST', '/surfside/v1/r2/sermons/' . $mode);
    $request->set_param('video_id', $data['video_id'] ?? null);
    if ($mode === 'ticket') $request->set_param('size', $data['size'] ?? null);
    $result = $mode === 'ticket'
        ? surfside_tools_r2_upload_ticket($request)
        : surfside_tools_r2_upload_complete($request);
    // On real WordPress rest_ensure_response wraps arrays in WP_REST_Response.
    return is_object($result) && method_exists($result, 'get_data') ? $result->get_data() : $result;
}

function surfside_tools_r2_browser_upload_frontend_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !is_page('site-management')
        || !isset($_GET['view']) || !is_string($_GET['view'])
        || sanitize_key(wp_unslash($_GET['view'])) !== 'roku-media'
        || !isset($_POST['action']) || !is_string($_POST['action'])
        || sanitize_key(wp_unslash($_POST['action'])) !== 'surfside_r2_browser_upload') {
        return;
    }
    // No response body or credential may be cached by intermediaries.
    nocache_headers();
    $result = surfside_tools_r2_browser_upload_dispatch(wp_unslash($_POST));
    if (is_wp_error($result)) {
        $details = $result->get_error_data();
        $status = is_array($details) && isset($details['status']) ? (int) $details['status'] : 400;
        wp_send_json_error(array('message' => $result->get_error_message()), $status);
    }
    wp_send_json_success($result);
}
add_action('template_redirect', 'surfside_tools_r2_browser_upload_frontend_post', 1);

/** R2 ListObjectsV2; bounded and explicit, never performed during page views. */
function surfside_tools_r2_list_objects() {
    $items = array();
    $continuation = '';
    for ($page = 0; $page < 10; $page++) {
        $query = array('list-type' => '2', 'max-keys' => '1000', 'prefix' => SURFSIDE_R2_SERMON_PREFIX);
        if ($continuation !== '') $query['continuation-token'] = $continuation;
        $url = surfside_tools_r2_presign('GET', '', $query, 60);
        if (is_wp_error($url)) return $url;
        $response = wp_remote_get($url, array('timeout' => 30, 'redirection' => 0, 'reject_unsafe_urls' => true));
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('r2_list', 'Could not list R2 objects. Check the bucket token permissions.');
        }
        if (!function_exists('simplexml_load_string')) return new WP_Error('r2_xml', 'PHP SimpleXML is needed for R2 reconciliation.');
        $xml = simplexml_load_string(wp_remote_retrieve_body($response), 'SimpleXMLElement', LIBXML_NONET);
        if (!$xml) return new WP_Error('r2_xml', 'R2 returned an invalid object listing.');
        foreach ($xml->Contents as $object) {
            $key = (string) $object->Key;
            if (!preg_match('~^sermons/([A-Za-z0-9_-]{11})\.mp4$~D', $key, $m)) continue;
            $size = (int) $object->Size;
            if ($size <= 0) continue;
            $items[$m[1]] = array('size' => $size, 'etag' => trim((string) $object->ETag, '"'));
        }
        if ((string) $xml->IsTruncated !== 'true') return $items;
        $continuation = (string) $xml->NextContinuationToken;
        if ($continuation === '') return new WP_Error('r2_pagination', 'R2 omitted the next listing cursor.');
    }
    return new WP_Error('r2_limit', 'The R2 listing exceeded 10,000 objects; no statuses were modified.');
}

function surfside_tools_r2_reconcile() {
    $schema = surfside_tools_r2_ensure_table();
    if (is_wp_error($schema)) return $schema;
    $master = surfside_tools_r2_master_id();
    if ($master === '') return new WP_Error('r2_master', 'Synchronize the YouTube Messages playlist first.');
    $objects = surfside_tools_r2_list_objects();
    if (is_wp_error($objects)) return $objects;
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $ids = $wpdb->get_col($wpdb->prepare("SELECT video_id FROM {$t['members']} WHERE playlist_id = %s", $master));
    $allowed = array_fill_keys($ids, true);
    $matched = 0;
    foreach ($objects as $id => $object) {
        if (empty($allowed[$id])) continue;
        if (!surfside_tools_r2_record($id, $object['size'], $object['etag'])) {
            return new WP_Error('r2_write', 'A status update failed; run Reconcile again.');
        }
        $matched++;
    }
    // Only mark absent objects missing AFTER a complete successful bucket listing.
    $table = surfside_tools_r2_sermon_table();
    $present = array_intersect(array_keys($objects), $ids);
    $absent = array_diff($ids, $present);
    foreach ($absent as $id) {
        $wpdb->update($table, array('status' => 'missing'), array('video_id' => $id), array('%s'), array('%s'));
    }
    return array('matched' => $matched, 'unmatched_objects' => count($objects) - $matched, 'master' => count($ids));
}

function surfside_tools_r2_manager_url($args = array()) {
    return add_query_arg(array_merge(array('view' => 'roku-media'), $args), surfside_tools_staff_page_url('site-management'));
}

function surfside_tools_r2_reconcile_post() {
    if (!current_user_can('upload_files')) wp_die('Not authorized.', '', array('response' => 403));
    check_admin_referer('surfside_r2_reconcile');
    $result = surfside_tools_r2_reconcile();
    $notice = is_wp_error($result) ? $result->get_error_message()
        : "Matched {$result['matched']} of {$result['master']} YouTube messages. Ignored {$result['unmatched_objects']} unmatched R2 objects.";
    set_transient('surfside_r2_notice_' . get_current_user_id(), array(
        'text' => $notice, 'error' => is_wp_error($result)
    ), 120);
    wp_safe_redirect(surfside_tools_r2_manager_url(), 303);
    exit;
}
add_action('admin_post_surfside_r2_reconcile', 'surfside_tools_r2_reconcile_post');

/** Download a canonical YouTube-ID manifest template for the bulk CLI. */
function surfside_tools_r2_export_post() {
    if (!current_user_can('upload_files')) wp_die('Not authorized.', '', array('response' => 403));
    check_admin_referer('surfside_r2_export');
    if (surfside_tools_r2_master_id() === '') wp_die('YouTube playlist not synchronized.');
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT v.video_id, v.title, v.sermon_date FROM {$t['videos']} v
        INNER JOIN {$t['members']} m ON m.video_id = v.video_id
        WHERE m.playlist_id = %s ORDER BY v.sermon_date DESC, v.video_id", surfside_tools_r2_master_id()
    ), ARRAY_A);
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="surfside-r2-sermon-manifest.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('video_id', 'title', 'sermon_date', 'source_path'));
    foreach ($rows as $row) {
        $title = (string) $row['title'];
        // Avoid CSV formula interpretation in spreadsheet programs.
        if (preg_match('/^[\s]*[=+@-]/', $title)) $title = "'" . $title;
        fputcsv($out, array($row['video_id'], $title, $row['sermon_date'], ''));
    }
    fclose($out);
    exit;
}
add_action('admin_post_surfside_r2_export', 'surfside_tools_r2_export_post');

/**
 * Staff can paste either the 11-character ID or a real YouTube video URL.
 * Reject arbitrary hosts (including lookalike domains).
 */
function surfside_tools_r2_youtube_id_from_input($input) {
    if (!is_string($input)) return '';
    $input = trim($input);
    if (surfside_tools_r2_video_id($input)) return $input;
    $parts = wp_parse_url($input);
    if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), array('http', 'https'), true)) return '';
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');
    $candidate = '';
    if ($host === 'youtu.be' || $host === 'www.youtu.be') {
        $candidate = trim($path, '/');
    } elseif (in_array($host, array('youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'), true)) {
        if ($path === '/watch') {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $candidate = $query['v'] ?? '';
        } elseif (preg_match('~^/(?:live|shorts|embed)/([A-Za-z0-9_-]{11})/?$~D', $path, $matches)) {
            $candidate = $matches[1];
        }
    }
    return surfside_tools_r2_video_id($candidate) ? $candidate : '';
}

/**
 * Explicit on-demand refresh from the Roku manager. Staff can either import a
 * single video using one API call or queue a master-only playlist refresh.
 */
function surfside_tools_r2_youtube_refresh_post() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        wp_die('You do not have permission to refresh messages.', '', array('response' => 403));
    }
    check_admin_referer('surfside_r2_youtube_refresh', 'surfside_r2_youtube_nonce');
    $mode = isset($_POST['refresh_mode']) && is_string($_POST['refresh_mode'])
        ? sanitize_key(wp_unslash($_POST['refresh_mode'])) : '';
    $highlight = '';
    $notice_key = 'surfside_r2_notice_' . get_current_user_id();
    $rate_key = 'surfside_r2_youtube_refresh_throttle_' . get_current_user_id();
    if (get_transient($rate_key)) {
        $result = new WP_Error('surfside_youtube_rate', 'Please allow a few seconds between YouTube refresh requests.');
    } elseif ($mode === 'video') {
        $raw = isset($_POST['youtube_url']) && is_string($_POST['youtube_url'])
            ? wp_unslash($_POST['youtube_url']) : '';
        $id = surfside_tools_r2_youtube_id_from_input($raw);
        $result = $id !== '' ? surfside_tools_youtube_quick_import_video($id)
            : new WP_Error('surfside_youtube_invalid_video', 'Paste a valid YouTube video link or 11-character ID.');
        if (!is_wp_error($result)) $highlight = $id;
    } elseif ($mode === 'playlist') {
        $result = surfside_tools_youtube_start_master_refresh();
    } else {
        $result = new WP_Error('surfside_youtube_invalid_action', 'Unknown YouTube refresh action.');
    }
    if (!is_wp_error($result)) {
        set_transient($rate_key, 1, 12);
    }
    $message = is_wp_error($result) ? $result->get_error_message()
        : ($mode === 'video'
            ? 'YouTube message added or updated. It is now available for an R2 upload.'
            : 'Master-only YouTube refresh queued. Use Check progress until it completes.');
    set_transient($notice_key, array('text' => $message, 'error' => is_wp_error($result)), 120);
    $destination = surfside_tools_r2_manager_url($highlight !== '' ? array('highlight' => $highlight) : array());
    if (!wp_safe_redirect($destination, 303, 'Surfside Tools')) {
        wp_die('Refresh processed. <a href="' . esc_url($destination) . '">Return to Roku Copies</a>.',
            'YouTube Refresh', array('response' => 500));
    }
    exit;
}
add_action('admin_post_surfside_r2_youtube_refresh', 'surfside_tools_r2_youtube_refresh_post');

/** Handle front-end Staff Dashboard forms without requiring wp-admin access. */
function surfside_tools_r2_youtube_refresh_frontend_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !is_page('site-management')
        || !isset($_GET['view']) || !is_string($_GET['view'])
        || sanitize_key(wp_unslash($_GET['view'])) !== 'roku-media'
        || !isset($_POST['action']) || !is_string($_POST['action'])
        || sanitize_key(wp_unslash($_POST['action'])) !== 'surfside_r2_youtube_refresh') return;
    surfside_tools_r2_youtube_refresh_post();
}
add_action('template_redirect', 'surfside_tools_r2_youtube_refresh_frontend_post', 1);

/**
 * Ready-only URL helper for a FUTURE Roku API. No public route in this PR.
 * Never treat the key alone as proof of a playable media object.
 */
function surfside_tools_r2_ready_url($id) {
    $cfg = surfside_tools_r2_config();
    if (!surfside_tools_r2_video_id($id) || $cfg['public_base'] === ''
        || !preg_match('~^https://[a-z0-9.-]+(?:/[\w./-]*)?$~i', $cfg['public_base'])
        || !surfside_tools_r2_is_master_video($id)) return '';
    global $wpdb;
    $table = surfside_tools_r2_sermon_table();
    $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE video_id = %s", $id));
    return $status === 'ready' ? trailingslashit($cfg['public_base']) . surfside_tools_r2_sermon_key($id) : '';
}

function surfside_tools_staff_r2_sermons_shortcode() {
    if (function_exists('surfside_tools_prevent_cache')) surfside_tools_prevent_cache();
    if (function_exists('surfside_tools_staff_enqueue_styles')) surfside_tools_staff_enqueue_styles();
    if (!is_user_logged_in()) return surfside_tools_staff_login_box('Sign in to manage weekly media publishing.');
    if (!current_user_can('upload_files')) return '<div class="surfside-staff-shell"><p>You do not have permission to manage weekly media.</p></div>';
    $schema = surfside_tools_r2_ensure_table();
    $cfg = surfside_tools_r2_config();
    $master = surfside_tools_r2_master_id();
    $youtube_state = surfside_tools_youtube_state();
    $youtube_running = ($youtube_state['status'] ?? '') === 'running';
    $highlight = isset($_GET['highlight']) && is_string($_GET['highlight'])
        ? sanitize_text_field(wp_unslash($_GET['highlight'])) : '';
    if (!surfside_tools_r2_video_id($highlight)) $highlight = '';
    $rows = array();
    if (!is_wp_error($schema) && $master !== '') {
        global $wpdb;
        $t = surfside_tools_youtube_tables();
        $r2 = surfside_tools_r2_sermon_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT v.video_id, v.title, v.sermon_date, r.status, r.size_bytes
            FROM {$t['videos']} v INNER JOIN {$t['members']} m ON m.video_id = v.video_id
            LEFT JOIN {$r2} r ON r.video_id = v.video_id
            WHERE m.playlist_id = %s ORDER BY v.sermon_date DESC, v.video_id", $master
        ), ARRAY_A);
    }
    $notice = get_transient('surfside_r2_notice_' . get_current_user_id());
    delete_transient('surfside_r2_notice_' . get_current_user_id());
    $r2_js_path = SURFSIDE_TOOLS_PATH . 'assets/js/r2-sermon-upload.js';
    $r2_js_version = is_file($r2_js_path) ? (string) filemtime($r2_js_path) : SURFSIDE_TOOLS_VERSION;
    wp_enqueue_script('surfside-r2-sermon-upload', SURFSIDE_TOOLS_URL . 'assets/js/r2-sermon-upload.js', array(), $r2_js_version, true);
    wp_add_inline_script('surfside-r2-sermon-upload', 'window.SurfsideR2Upload = ' . wp_json_encode(array(
        'endpoint' => surfside_tools_r2_manager_url(), 'nonce' => wp_create_nonce('surfside_r2_browser_upload'),
    )) . ';', 'before');
    ob_start(); ?>
    <div class="surfside-staff-shell surfside-yt-manager" id="surfside-r2-sermons">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_youtube_manager_url()); ?>">← Back to Message Archive</a></div>
      <section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Messages &amp; Media · Weekly Publishing</p>
        <h1>Weekly Media Publishing</h1><p class="surfside-staff-muted">Find the weekly YouTube message, upload its MP4 for Roku, and prepare the matching podcast episode using the same sermon metadata.</p>
      </section>
      <?php if (is_array($notice)) : ?><p role="status" style="padding:12px;border:1px solid #b8c9dc"><?php echo esc_html($notice['text']); ?></p><?php endif; ?>
      <?php if (is_wp_error($schema)) : ?><p><?php echo esc_html($schema->get_error_message()); ?></p>
      <?php elseif ($master === '') : ?><p>Run the initial YouTube playlist sync in Integrations before uploading.</p>
      <?php else : ?>

        <section class="surfside-yt-panel">
          <h2>Find this week's message</h2>
          <?php if (!$cfg['ready']) : ?><p role="alert">R2 credentials missing. Configure bucket-scoped access in wp-config.php.</p><?php endif; ?>
          <p class="surfside-yt-muted">Once your sermon is public and in the YouTube Messages playlist, paste its link to add it immediately, without waiting for the nightly sync.</p>
          <form method="post" action="<?php echo esc_url(surfside_tools_r2_manager_url()); ?>">
            <input type="hidden" name="action" value="surfside_r2_youtube_refresh">
            <input type="hidden" name="refresh_mode" value="video">
            <?php wp_nonce_field('surfside_r2_youtube_refresh', 'surfside_r2_youtube_nonce'); ?>
            <label for="surfside-r2-youtube-url" style="display:block">YouTube video link or ID</label>
            <input id="surfside-r2-youtube-url" name="youtube_url" type="text" required
              placeholder="https://www.youtube.com/watch?v=…" style="width:100%;max-width:580px;padding:10px">
            <button type="submit" <?php disabled($youtube_running); ?>>Find This YouTube Message</button>
          </form>
          <?php if ($youtube_running) : ?>
            <p role="status"><strong>YouTube sync running:</strong> <?php echo esc_html((string) ($youtube_state['message'] ?? 'Processing')); ?>
              (<?php echo esc_html(number_format_i18n((int) ($youtube_state['requests'] ?? 0))); ?> API pages so far)</p>
            <p><a href="<?php echo esc_url(surfside_tools_r2_manager_url()); ?>">Check sync progress</a></p>
          <?php endif; ?>
        </section>
        <section class="surfside-yt-panel"><h2>Upload this week's MP4</h2>
          <form id="surfside-r2-upload-form">
            <label for="surfside-r2-id">YouTube message</label>
            <select id="surfside-r2-id" required>
              <option value="">Choose a YouTube video</option>
              <?php foreach ($rows as $row) : $preview = surfside_tools_podcast_episode_preview($row['title'], $row['sermon_date'], $row['video_id']); ?>
                <option value="<?php echo esc_attr($row['video_id']); ?>" data-podcast-title="<?php echo esc_attr($preview['title']); ?>" data-podcast-description="<?php echo esc_attr($preview['description']); ?>" data-podcast-review="<?php echo $preview['needs_review'] ? '1' : '0'; ?>" <?php if ($highlight === $row['video_id']) echo 'selected'; ?> <?php disabled(($row['status'] ?? '') === 'ready'); ?>><?php echo esc_html((($row['status'] ?? '') === 'ready' ? '✓ ' : '') . $row['title'] . ' (' . $row['video_id'] . ')'); ?></option>
              <?php endforeach; ?>
            </select>
            <label for="surfside-r2-file" style="display:block;margin-top:12px">Roku-ready MP4 (H.264/AAC, faststart)</label>
            <input id="surfside-r2-file" type="file" accept=".mp4,video/mp4" required>
            <p class="surfside-yt-muted">Direct upload to R2, up to 5 GiB. Keep this page open until verification completes.</p>
            <button type="submit" <?php disabled(!$cfg['ready']); ?>>Upload to R2</button>
            <button type="button" id="surfside-r2-cancel" hidden>Cancel upload</button>
          </form>
          <progress id="surfside-r2-progress" max="100" value="0" style="display:none;width:100%;margin-top:15px"></progress>
          <p role="status" aria-live="polite" id="surfside-r2-status"></p>
        </section>
        <section class="surfside-yt-panel" id="surfside-podcast-preview">
          <h2>Podcast episode preview</h2>
          <p class="surfside-yt-muted">Preview the title and description from YouTube metadata for an MP4 already verified in R2. Podcast processing and Spreaker publishing are not connected yet; this page will not create or publish an episode.</p>
          <label for="surfside-podcast-id">Uploaded sermon</label>
          <select id="surfside-podcast-id">
            <option value="">Choose an uploaded sermon</option>
            <?php $podcast_visible = 0; foreach ($rows as $row) :
              if (($row['status'] ?? '') !== 'ready' || $podcast_visible >= 12) continue;
              ++$podcast_visible;
              $preview = surfside_tools_podcast_episode_preview($row['title'], $row['sermon_date'], $row['video_id']); ?>
              <option value="<?php echo esc_attr($row['video_id']); ?>" data-podcast-title="<?php echo esc_attr($preview['title']); ?>" data-podcast-description="<?php echo esc_attr($preview['description']); ?>" data-podcast-review="<?php echo $preview['needs_review'] ? '1' : '0'; ?>" <?php selected($highlight, $row['video_id']); ?>><?php echo esc_html($row['title']); ?></option>
            <?php endforeach; ?>
          </select>
          <p id="surfside-podcast-empty" class="surfside-yt-muted">Select an uploaded sermon to see its proposed podcast metadata.</p>
          <div id="surfside-podcast-details" hidden>
            <p><strong>Proposed episode title:</strong> <span id="surfside-podcast-title"></span></p>
            <p><strong>Proposed description:</strong></p>
            <p id="surfside-podcast-description" style="white-space:pre-line"></p>
            <p id="surfside-podcast-review" role="status" hidden>Review this title/date before publishing: it does not fully match the expected weekly naming format.</p>
          </div>
        </section>
      <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('surfside_staff_r2_sermons', 'surfside_tools_staff_r2_sermons_shortcode');
