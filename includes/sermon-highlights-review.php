<?php
/**
 * Staff-only review of private AI sermon suggestions and manually rendered MP4 previews.
 * No public routes, automatic highlights, or publishing. WordPress signs Worker requests
 * server-side; HMAC credentials are never sent to the browser.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_highlights_can_review() {
    return is_user_logged_in() && current_user_can('upload_files') &&
        (function_exists('surfside_tools_staff_has_permission')
            ? surfside_tools_staff_has_permission('weekly_media') : current_user_can('manage_options'));
}

/**
 * Safe, staff-only configuration checks. Never serialize raw configuration
 * values, secret lengths, hashes, or paths to a browser.
 */
function surfside_tools_highlights_config_diagnostics() {
    $url_defined = defined('SURFSIDE_PODCAST_WORKER_URL');
    $secret_defined = defined('SURFSIDE_PODCAST_SHARED_SECRET');
    $url_string = $url_defined && is_string(constant('SURFSIDE_PODCAST_WORKER_URL'));
    $secret_string = $secret_defined && is_string(constant('SURFSIDE_PODCAST_SHARED_SECRET'));
    $url_valid = $url_string && (bool) preg_match(
        '~^https://[a-z0-9.-]+(?::443)?$~iD',
        untrailingslashit(constant('SURFSIDE_PODCAST_WORKER_URL')));
    $secret_valid = $secret_string && strlen(constant('SURFSIDE_PODCAST_SHARED_SECRET')) >= 32;
    return array(
        'worker_url_defined' => $url_defined,
        'worker_url_string' => $url_string,
        'worker_url_valid' => $url_valid,
        'secret_defined' => $secret_defined,
        'secret_string' => $secret_string,
        'secret_length_valid' => $secret_valid,
    );
}

function surfside_tools_highlights_configured() {
    foreach (surfside_tools_highlights_config_diagnostics() as $valid) {
        if (!$valid) return false;
    }
    return true;
}

function surfside_tools_highlights_worker($path, $data) {
    $allowed = array('/highlights/jobs', '/highlights/status',
        '/highlights/clips/jobs', '/highlights/clips/status',
        '/highlights/exports/jobs', '/highlights/exports/status');
    if (!in_array($path, $allowed, true) || !surfside_tools_highlights_configured()) {
        return new WP_Error('highlights_not_configured',
            'The private highlights service is not configured.', array('status' => 503));
    }
    $json = wp_json_encode($data);
    if (!is_string($json)) {
        return new WP_Error('highlights_bad_request', 'Could not encode the request.', array('status' => 400));
    }
    $time = (string) time();
    $response = wp_remote_post(untrailingslashit(SURFSIDE_PODCAST_WORKER_URL) . $path, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-Surfside-Time' => $time,
            'X-Surfside-Signature' => hash_hmac('sha256', $time . '.' . $json, SURFSIDE_PODCAST_SHARED_SECRET),
        ),
        'body' => $json,
        'timeout' => 15,
        'redirection' => 0,
        'reject_unsafe_urls' => true,
        'limit_response_size' => 262144,
    ));
    if (is_wp_error($response)) {
        return new WP_Error('highlights_unavailable',
            'The private highlights service could not be reached.', array('status' => 502));
    }
    $http = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body) || !in_array($http, array(200, 202), true)) {
        $reason = is_array($body) && isset($body['error']) && is_string($body['error'])
            ? sanitize_key($body['error']) : 'upstream_failure';
        return new WP_Error('highlights_worker_' . $reason,
            'The private highlights service returned ' . $reason . '.',
            array('status' => $http >= 400 && $http < 500 ? $http : 502));
    }
    return $body;
}

function surfside_tools_highlights_valid_id($id) {
    return is_string($id) && (bool) preg_match('/^[A-Za-z0-9_-]{11}$/D', $id);
}

/** Only verified MP4s from the master YouTube Messages playlist are eligible. */
function surfside_tools_highlights_sermon_rows($id = '') {
    if (!function_exists('surfside_tools_r2_master_id') ||
        !function_exists('surfside_tools_r2_sermon_table') ||
        !function_exists('surfside_tools_youtube_tables')) return array();
    $master = surfside_tools_r2_master_id();
    if (!$master) return array();
    global $wpdb;
    $tables = surfside_tools_youtube_tables();
    $r2 = surfside_tools_r2_sermon_table();
    $where = $id !== '' ? ' AND v.video_id = %s' : '';
    $query = "SELECT v.video_id, v.title, v.sermon_date
              FROM {$tables['videos']} v
              INNER JOIN {$tables['members']} m ON m.video_id = v.video_id
              INNER JOIN {$r2} r ON r.video_id = v.video_id
              WHERE m.playlist_id = %s AND r.status = 'ready'{$where}
              ORDER BY v.sermon_date DESC, v.video_id DESC LIMIT 100";
    $args = $id === '' ? array($master) : array($master, $id);
    return $wpdb->get_results($wpdb->prepare($query, $args), ARRAY_A) ?: array();
}

function surfside_tools_highlights_request_id($request) {
    $id = $request->get_param('video_id');
    if (!surfside_tools_highlights_valid_id($id)) {
        return new WP_Error('highlights_invalid_video', 'Choose a valid sermon.', array('status' => 400));
    }
    if (!surfside_tools_highlights_sermon_rows($id)) {
        return new WP_Error('highlights_unverified_sermon',
            'Choose a verified MP4 from the Messages playlist.', array('status' => 409));
    }
    return $id;
}

function surfside_tools_highlights_saved_review($id) {
    $saved = get_option('surfside_highlight_review_' . $id, array());
    return is_array($saved) && isset($saved['items']) && is_array($saved['items'])
        ? $saved['items'] : array();
}

function surfside_tools_highlights_rest_sermons() {
    return rest_ensure_response(array(
        'configured' => surfside_tools_highlights_configured(),
        'config_protocol' => 'highlights-config-v2',
        'config_checks' => surfside_tools_highlights_config_diagnostics(),
        'sermons' => surfside_tools_highlights_sermon_rows(),
    ));
}
function surfside_tools_highlights_rest_analyze($request) {
    $id = surfside_tools_highlights_request_id($request);
    return is_wp_error($id) ? $id : rest_ensure_response(surfside_tools_highlights_worker('/highlights/jobs', array('video_id' => $id)));
}
function surfside_tools_highlights_rest_status($request) {
    $id = surfside_tools_highlights_request_id($request);
    if (is_wp_error($id)) return $id;
    $worker = surfside_tools_highlights_worker('/highlights/status', array('video_id' => $id));
    if (is_wp_error($worker)) return $worker;
    $worker['saved_reviews'] = surfside_tools_highlights_saved_review($id);
    return rest_ensure_response($worker);
}

/** Render five seconds of editable handles on both sides, once per suggestion. */
function surfside_tools_highlights_rest_previews($request) {
    $id = surfside_tools_highlights_request_id($request);
    if (is_wp_error($id)) return $id;
    $status = surfside_tools_highlights_worker('/highlights/status', array('video_id' => $id));
    if (is_wp_error($status)) return $status;
    $suggestions = $status['result']['highlights'] ?? array();
    if (($status['status'] ?? '') !== 'ready' || !is_array($suggestions) || !$suggestions) {
        return new WP_Error('highlights_not_ready', 'Analyze the sermon first.', array('status' => 409));
    }
    $clips = array();
    foreach (array_slice($suggestions, 0, 5) as $highlight) {
        if (!isset($highlight['number'], $highlight['start'], $highlight['end']) ||
            !is_numeric($highlight['start']) || !is_numeric($highlight['end'])) continue;
        $start = max(0, round((float) $highlight['start'] - 5, 2));
        $end = min(72 * 60, round((float) $highlight['end'] + 5, 2));
        // The existing video renderer requires 15–120 seconds per preview.
        if ($end - $start < 15 || $end - $start > 120) continue;
        $clips[] = array('number' => (int) $highlight['number'], 'start' => $start, 'end' => $end);
    }
    if (!$clips) {
        return new WP_Error('highlights_no_previews',
            'No suggestions have valid preview boundaries.', array('status' => 409));
    }
    return rest_ensure_response(surfside_tools_highlights_worker('/highlights/clips/jobs',
        array('video_id' => $id, 'clips' => $clips)));
}
function surfside_tools_highlights_rest_preview_status($request) {
    $id = surfside_tools_highlights_request_id($request);
    return is_wp_error($id) ? $id : rest_ensure_response(surfside_tools_highlights_worker(
        '/highlights/clips/status', array('video_id' => $id)));
}

/**
 * Export only explicitly approved reviews with their exact, persisted trims.
 * Draft/saved and rejected suggestions cannot enter a final render job.
 */
function surfside_tools_highlights_approved_clips($id) {
    $reviews = surfside_tools_highlights_saved_review($id);
    $clips = array();
    foreach ($reviews as $item) {
        if (!is_array($item) || ($item['decision'] ?? '') !== 'approved') continue;
        $number = $item['number'] ?? null;
        $start = $item['start'] ?? null;
        $end = $item['end'] ?? null;
        if (!is_int($number) || $number < 1 || $number > 5 ||
            !is_numeric($start) || !is_numeric($end)) continue;
        $clips[] = array('number' => $number,
            'start' => round((float) $start, 2),
            'end' => round((float) $end, 2));
    }
    usort($clips, static function ($a, $b) {
        return $a['number'] <=> $b['number'];
    });
    return array_slice($clips, 0, 5);
}
function surfside_tools_highlights_rest_export($request) {
    $id = surfside_tools_highlights_request_id($request);
    if (is_wp_error($id)) return $id;
    $clips = surfside_tools_highlights_approved_clips($id);
    if (!$clips) return new WP_Error('highlights_no_approvals',
        'Approve at least one highlight before exporting.', array('status' => 409));
    // The signed Worker independently validates every exact trim against
    // immutable AI timestamps and refuses jobs outside the ±5s edit range.
    return rest_ensure_response(surfside_tools_highlights_worker(
        '/highlights/exports/jobs', array('video_id' => $id, 'clips' => $clips)));
}
function surfside_tools_highlights_rest_export_status($request) {
    $id = surfside_tools_highlights_request_id($request);
    if (is_wp_error($id)) return $id;
    $result = surfside_tools_highlights_worker(
        '/highlights/exports/status', array('video_id' => $id));
    if (is_wp_error($result)) return $result;
    $approved = surfside_tools_highlights_approved_clips($id);
    $result['approved_count'] = count($approved);
    // A later Save/Reject or changed approved trim invalidates old download
    // links. Keep immutable old R2 objects private, but don't offer them.
    $current = count($approved) > 0 && isset($result['clips']) &&
        is_array($result['clips']) && count($approved) === count($result['clips']);
    if ($current) {
        foreach ($approved as $i => $clip) {
            $export = $result['clips'][$i] ?? null;
            if (!is_array($export) || (int) ($export['number'] ?? 0) !== $clip['number'] ||
                abs((float) ($export['start'] ?? -9999) - $clip['start']) > 0.001 ||
                abs((float) ($export['end'] ?? -9999) - $clip['end']) > 0.001) {
                $current = false; break;
            }
        }
    }
    $result['matches_current_approvals'] = $current;
    if (!$current) {
        unset($result['clips']);
        if (($result['status'] ?? '') === 'ready') {
            $result['status'] = 'outdated';
            $result['detail'] = 'review_changed';
        }
    }
    return rest_ensure_response($result);
}

/** A review choice affects private staff metadata only; it never publishes clips. */
function surfside_tools_highlights_rest_decision($request) {
    $id = surfside_tools_highlights_request_id($request);
    if (is_wp_error($id)) return $id;
    $number = $request->get_param('number');
    $decision = $request->get_param('decision');
    $start = $request->get_param('start');
    $end = $request->get_param('end');
    if (!is_int($number) || $number < 1 || $number > 5 ||
        !in_array($decision, array('saved', 'approved', 'rejected'), true) ||
        !is_numeric($start) || !is_numeric($end)) {
        return new WP_Error('highlights_invalid_review', 'Invalid review decision.', array('status' => 400));
    }
    $status = surfside_tools_highlights_worker('/highlights/status', array('video_id' => $id));
    if (is_wp_error($status)) return $status;
    if (($status['status'] ?? '') !== 'ready') {
        return new WP_Error('highlights_not_ready', 'The analysis is not ready.', array('status' => 409));
    }
    $original = null;
    foreach (($status['result']['highlights'] ?? array()) as $candidate) {
        if (($candidate['number'] ?? null) === $number) { $original = $candidate; break; }
    }
    if (!$original) return new WP_Error('highlights_unknown_clip', 'Unknown suggestion.', array('status' => 404));
    $a = round((float) $start, 2);
    $b = round((float) $end, 2);
    if (abs($a - (float) $original['start']) > 5.001 ||
        abs($b - (float) $original['end']) > 5.001 || $a < 0 ||
        $b > 72 * 60 || $b - $a < 15 || $b - $a > 120) {
        return new WP_Error('highlights_invalid_trim',
            'Start and end must each remain within five seconds of the AI suggestion.',
            array('status' => 400));
    }
    $items = surfside_tools_highlights_saved_review($id);
    $items[(string) $number] = array(
        'number' => $number,
        'start' => $a,
        'end' => $b,
        'decision' => $decision,
        'reviewed_by' => get_current_user_id(),
        'updated_at' => gmdate('c'),
    );
    update_option('surfside_highlight_review_' . $id, array('items' => $items), false);
    return rest_ensure_response(array('ok' => true, 'review' => $items[(string) $number]));
}

/**
 * The staff website uses scoped Surfside Tools login cookies rather than a
 * WordPress-authenticated REST session. WordPress REST cookie auth rejects the
 * resulting wp_rest nonce with "Cookie check failed". Follow the established
 * Weekly Media / R2 pattern: POST a tiny nonce-verified action to the signed-in
 * dashboard page, where the scoped Tools session is valid.
 *
 * This handler never accepts MP4 bytes or exposes Worker signing credentials.
 */
function surfside_tools_highlights_browser_dispatch($data) {
    if (!surfside_tools_highlights_can_review()) {
        return new WP_Error('highlights_forbidden',
            'Sign in with Weekly Media permission to review highlights.',
            array('status' => 403));
    }
    $nonce = isset($data['highlight_nonce']) && is_string($data['highlight_nonce'])
        ? $data['highlight_nonce'] : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'surfside_highlights_browser_review')) {
        return new WP_Error('highlights_bad_nonce',
            'Your review session expired. Reload the Sermon Highlights page and try again.',
            array('status' => 403));
    }
    $mode = isset($data['mode']) && is_string($data['mode'])
        ? sanitize_key($data['mode']) : '';
    $routes = array(
        'sermons' => 'surfside_tools_highlights_rest_sermons',
        'analyze' => 'surfside_tools_highlights_rest_analyze',
        'status' => 'surfside_tools_highlights_rest_status',
        'previews' => 'surfside_tools_highlights_rest_previews',
        'preview-status' => 'surfside_tools_highlights_rest_preview_status',
        'export' => 'surfside_tools_highlights_rest_export',
        'export-status' => 'surfside_tools_highlights_rest_export_status',
        'review' => 'surfside_tools_highlights_rest_decision',
    );
    if (!isset($routes[$mode])) {
        return new WP_Error('highlights_bad_mode', 'Unknown highlight review action.',
            array('status' => 400));
    }
    if ($mode === 'sermons') {
        // rest_ensure_response() returns WP_REST_Response in live WordPress.
        // As with all other modes below, unwrap before wp_send_json_success().
        // Otherwise the browser receives a nested response object and cannot
        // find configured, config_protocol, config_checks or sermon rows.
        $result = surfside_tools_highlights_rest_sermons();
        return is_object($result) && method_exists($result, 'get_data')
            ? $result->get_data() : $result;
    }
    $id = $data['video_id'] ?? null;
    if (!surfside_tools_highlights_valid_id($id)) {
        return new WP_Error('highlights_invalid_video', 'Choose a valid sermon.',
            array('status' => 400));
    }
    $request = new WP_REST_Request('POST', '/surfside/v1/highlights/' . $mode);
    $request->set_param('video_id', $id);
    if ($mode === 'review') {
        $number = $data['number'] ?? null;
        $decision = $data['decision'] ?? null;
        $start = $data['start'] ?? null;
        $end = $data['end'] ?? null;
        if (!is_string($number) || !preg_match('/^[1-5]$/D', $number) ||
            !is_string($decision) ||
            !is_string($start) || !is_numeric($start) ||
            !is_string($end) || !is_numeric($end)) {
            return new WP_Error('highlights_invalid_review', 'Invalid review fields.',
                array('status' => 400));
        }
        $request->set_param('number', (int) $number);
        $request->set_param('decision', $decision);
        $request->set_param('start', $start);
        $request->set_param('end', $end);
    }
    $handler = $routes[$mode];
    $result = $handler($request);
    // REST handlers are internal helpers here, not public REST routes.
    return is_object($result) && method_exists($result, 'get_data')
        ? $result->get_data() : $result;
}

function surfside_tools_highlights_browser_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
        !is_page('site-management') ||
        !isset($_GET['view']) || !is_string($_GET['view']) ||
        sanitize_key(wp_unslash($_GET['view'])) !== 'sermon-highlights' ||
        !isset($_POST['action']) || !is_string($_POST['action']) ||
        sanitize_key(wp_unslash($_POST['action'])) !== 'surfside_highlights_browser_review') {
        return;
    }
    nocache_headers();
    $result = surfside_tools_highlights_browser_dispatch(wp_unslash($_POST));
    if (is_wp_error($result)) {
        $details = $result->get_error_data();
        $status = is_array($details) && isset($details['status'])
            ? (int) $details['status'] : 400;
        wp_send_json_error(array('message' => $result->get_error_message()), $status);
    }
    wp_send_json_success($result);
}
add_action('template_redirect', 'surfside_tools_highlights_browser_post', 1);

/** Routed from the existing /dashboard/site-management?view=sermon-highlights workspace. */
function surfside_tools_staff_sermon_highlights_shortcode() {
    if (function_exists('surfside_tools_prevent_cache')) surfside_tools_prevent_cache();
    if (function_exists('surfside_tools_staff_enqueue_styles')) surfside_tools_staff_enqueue_styles();
    if (!is_user_logged_in()) return function_exists('surfside_tools_staff_login_box')
        ? surfside_tools_staff_login_box('Log in to review sermon highlights.') : '<p>Login required.</p>';
    if (!surfside_tools_highlights_can_review())
        return '<div class="surfside-staff-shell"><p>You do not have permission to review sermon highlights.</p></div>';

    $style = SURFSIDE_TOOLS_PATH . 'assets/css/sermon-highlights-review.css';
    $script = SURFSIDE_TOOLS_PATH . 'assets/js/sermon-highlights-review.js';
    wp_enqueue_style('surfside-sermon-highlights-review',
        SURFSIDE_TOOLS_URL . 'assets/css/sermon-highlights-review.css', array(),
        file_exists($style) ? filemtime($style) : SURFSIDE_TOOLS_VERSION);
    wp_enqueue_script('surfside-sermon-highlights-review',
        SURFSIDE_TOOLS_URL . 'assets/js/sermon-highlights-review.js', array(),
        file_exists($script) ? filemtime($script) : SURFSIDE_TOOLS_VERSION, true);
    wp_add_inline_script('surfside-sermon-highlights-review', 'window.SurfsideHighlightsReview=' .
        wp_json_encode(array(
            'endpoint' => esc_url_raw(add_query_arg('view', 'sermon-highlights',
                surfside_tools_staff_page_url('site-management'))),
            'nonce' => wp_create_nonce('surfside_highlights_browser_review'),
            // Capture the same safe booleans at page render time. If they
            // differ from the authenticated POST response, it is a runtime
            // or deployment mismatch rather than a bad credential.
            // Bootstrap from the authenticated PHP page render instead of a
            // second request. This exact request has the verified Worker
            // configuration, and the sermon inventory is staff-only.
            'configured' => surfside_tools_highlights_configured(),
            'sermons' => surfside_tools_highlights_sermon_rows(),
            'configProtocol' => 'highlights-config-v2',
            'pageChecks' => surfside_tools_highlights_config_diagnostics(),
            'initialId' => isset($_GET['video_id']) && surfside_tools_highlights_valid_id(wp_unslash($_GET['video_id']))
                ? sanitize_text_field(wp_unslash($_GET['video_id'])) : '',
        )) . ';', 'before');
    ob_start(); ?>
    <div class="surfside-staff-shell surfside-highlights-review" id="surfside-highlights-review">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('messages-media')); ?>">← Messages &amp; Media</a></div>
      <section class="surfside-staff-hero">
        <p class="surfside-staff-eyebrow">Messages &amp; Media</p>
        <h1>Sermon Highlights</h1>
        <p class="surfside-staff-muted">New weekly sermon uploads automatically prepare AI highlights and private video previews alongside podcast processing. Review up to five clips, adjust each edge by five seconds, and approve or reject. Nothing publishes automatically.</p>
      </section>
      <div class="surfside-highlights-toolbar">
        <label for="surfside-highlights-sermon">Uploaded sermon</label>
        <select id="surfside-highlights-sermon"><option value="">Loading verified sermon videos…</option></select>
        <button type="button" id="surfside-highlights-analyze">Analyze / Prepare now</button>
        <button type="button" id="surfside-highlights-render" disabled>Generate video previews</button>
      </div>
      <p id="surfside-highlights-message" role="status" aria-live="polite">Choose a sermon to begin.</p>
      <section class="surfside-highlight-export-panel" id="surfside-highlight-export-panel" hidden>
        <h2>Approved Clips</h2>
        <p>Approve the clips you want to keep, then generate final MP4 files using your saved trim points. Exported videos stay private until you download and share them.</p>
        <button type="button" id="surfside-highlight-export-button" disabled>Export approved clips</button>
        <p id="surfside-highlight-export-message" role="status" aria-live="polite"></p>
        <div id="surfside-highlight-export-downloads"></div>
      </section>
      <div id="surfside-highlights-results"></div>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('surfside_staff_sermon_highlights', 'surfside_tools_staff_sermon_highlights_shortcode');
