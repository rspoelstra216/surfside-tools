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
    return rest_ensure_response(array('verified' => true, 'video_id' => $id, 'bytes' => $head['size']));
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
    if (!is_user_logged_in()) return surfside_tools_staff_login_box('Sign in to manage Roku sermon files.');
    if (!current_user_can('upload_files')) return '<div class="surfside-staff-shell"><p>You do not have permission to manage sermon files.</p></div>';
    $schema = surfside_tools_r2_ensure_table();
    $cfg = surfside_tools_r2_config();
    $master = surfside_tools_r2_master_id();
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
    $ready_count = count(array_filter($rows, function ($row) { return ($row['status'] ?? '') === 'ready'; }));
    $notice = get_transient('surfside_r2_notice_' . get_current_user_id());
    delete_transient('surfside_r2_notice_' . get_current_user_id());
    wp_enqueue_script('surfside-r2-sermon-upload', SURFSIDE_TOOLS_URL . 'assets/js/r2-sermon-upload.js', array(), SURFSIDE_TOOLS_VERSION, true);
    wp_add_inline_script('surfside-r2-sermon-upload', 'window.SurfsideR2Upload = ' . wp_json_encode(array(
        'base' => rest_url('surfside/v1/r2/sermons/'), 'nonce' => wp_create_nonce('wp_rest'),
    )) . ';', 'before');
    ob_start(); ?>
    <div class="surfside-staff-shell surfside-yt-manager" id="surfside-r2-sermons">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_youtube_manager_url()); ?>">← Back to Message Archive</a></div>
      <section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Website · Messages · Roku</p>
        <h1>Roku Sermon Copies</h1><p class="surfside-staff-muted">YouTube remains the public archive. This workspace manages matching MP4 files in R2 without uploading through WordPress.</p>
      </section>
      <?php if (is_array($notice)) : ?><p role="status" style="padding:12px;border:1px solid #b8c9dc"><?php echo esc_html($notice['text']); ?></p><?php endif; ?>
      <?php if (is_wp_error($schema)) : ?><p><?php echo esc_html($schema->get_error_message()); ?></p>
      <?php elseif ($master === '') : ?><p>Run the initial YouTube playlist sync in Integrations before uploading.</p>
      <?php else : ?>
        <section class="surfside-yt-panel"><h2>R2 readiness</h2>
          <p><strong><?php echo esc_html(number_format_i18n($ready_count)); ?> of <?php echo esc_html(number_format_i18n(count($rows))); ?></strong> master YouTube videos have registered R2 copies.</p>
          <p><?php echo $cfg['ready'] ? 'R2 credentials detected.' : 'R2 credentials missing. Configure bucket-scoped access in wp-config.php (see docs/R2_SERMON_ARCHIVE.md).'; ?></p>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="surfside_r2_reconcile">
            <?php wp_nonce_field('surfside_r2_reconcile'); ?>
            <button type="submit" <?php disabled(!$cfg['ready']); ?>>Reconcile R2 objects</button>
          </form>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px">
            <input type="hidden" name="action" value="surfside_r2_export">
            <?php wp_nonce_field('surfside_r2_export'); ?>
            <button type="submit">Download bulk-import CSV template</button>
          </form>
          <p class="surfside-yt-muted">For historic files: fill in source_path in the CSV, run the local CLI uploader, then Reconcile. For weekly messages: use the form below.</p>
        </section>
        <section class="surfside-yt-panel"><h2>Upload this week's MP4</h2>
          <form id="surfside-r2-upload-form">
            <label for="surfside-r2-id">YouTube message</label>
            <select id="surfside-r2-id" required>
              <option value="">Choose a YouTube video</option>
              <?php foreach ($rows as $row) : ?>
                <option value="<?php echo esc_attr($row['video_id']); ?>" <?php disabled(($row['status'] ?? '') === 'ready'); ?>><?php echo esc_html((($row['status'] ?? '') === 'ready' ? '✓ ' : '') . $row['title'] . ' (' . $row['video_id'] . ')'); ?></option>
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
        <section class="surfside-yt-panel"><h2>Registered copies</h2><p class="surfside-yt-muted">These statuses are refreshed after an upload or an explicit R2 reconciliation.</p>
          <div style="max-height:480px;overflow:auto">
            <table class="widefat striped" style="width:100%;border-collapse:collapse"><thead><tr><th>Message</th><th>Video ID</th><th>R2</th></tr></thead><tbody>
              <?php foreach ($rows as $row) : ?><tr>
                <td><?php echo esc_html($row['title']); ?></td><td><code><?php echo esc_html($row['video_id']); ?></code></td>
                <td><?php echo ($row['status'] ?? '') === 'ready' ? esc_html(size_format((int) $row['size_bytes'])) : 'Not registered'; ?></td>
              </tr><?php endforeach; ?>
            </tbody></table>
          </div>
        </section>
      <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('surfside_staff_r2_sermons', 'surfside_tools_staff_r2_sermons_shortcode');
