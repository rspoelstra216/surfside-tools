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

function surfside_tools_highlights_configured() {
    if (!defined('SURFSIDE_PODCAST_WORKER_URL') || !defined('SURFSIDE_PODCAST_SHARED_SECRET') ||
        !is_string(SURFSIDE_PODCAST_WORKER_URL) || !is_string(SURFSIDE_PODCAST_SHARED_SECRET) ||
        strlen(SURFSIDE_PODCAST_SHARED_SECRET) < 32) return false;
    return (bool) preg_match('~^https://[a-z0-9.-]+(?::443)?$~iD',
        untrailingslashit(SURFSIDE_PODCAST_WORKER_URL));
}

function surfside_tools_highlights_worker($path, $data) {
    $allowed = array('/highlights/jobs', '/highlights/status',
        '/highlights/clips/jobs', '/highlights/clips/status');
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
    if (abs($a - (float) $original['start']) > 5.011 ||
        abs($b - (float) $original['end']) > 5.011 || $a < 0 ||
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

add_action('rest_api_init', function () {
    $routes = array(
        '/sermons' => array('GET', 'surfside_tools_highlights_rest_sermons'),
        '/analyze' => array('POST', 'surfside_tools_highlights_rest_analyze'),
        '/status' => array('POST', 'surfside_tools_highlights_rest_status'),
        '/previews' => array('POST', 'surfside_tools_highlights_rest_previews'),
        '/preview-status' => array('POST', 'surfside_tools_highlights_rest_preview_status'),
        '/review' => array('POST', 'surfside_tools_highlights_rest_decision'),
    );
    foreach ($routes as $route => $handler) {
        register_rest_route('surfside/v1', '/highlights' . $route, array(
            'methods' => $handler[0], 'callback' => $handler[1],
            'permission_callback' => 'surfside_tools_highlights_can_review',
        ));
    }
});

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
            'api' => esc_url_raw(rest_url('surfside/v1/highlights/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'initialId' => isset($_GET['video_id']) && surfside_tools_highlights_valid_id(wp_unslash($_GET['video_id']))
                ? sanitize_text_field(wp_unslash($_GET['video_id'])) : '',
        )) . ';', 'before');
    ob_start(); ?>
    <div class="surfside-staff-shell surfside-highlights-review" id="surfside-highlights-review">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('messages-media')); ?>">← Messages &amp; Media</a></div>
      <section class="surfside-staff-hero">
        <p class="surfside-staff-eyebrow">Messages &amp; Media</p>
        <h1>Sermon Highlights</h1>
        <p class="surfside-staff-muted">Review up to five AI suggestions in the actual sermon video. Adjust each edge by five seconds, approve or reject, and save private review decisions. Nothing publishes automatically.</p>
      </section>
      <div class="surfside-highlights-toolbar">
        <label for="surfside-highlights-sermon">Uploaded sermon</label>
        <select id="surfside-highlights-sermon"><option value="">Loading verified sermon videos…</option></select>
        <button type="button" id="surfside-highlights-analyze">Analyze sermon</button>
        <button type="button" id="surfside-highlights-render" disabled>Generate video previews</button>
      </div>
      <p id="surfside-highlights-message" role="status" aria-live="polite">Choose a sermon to begin.</p>
      <div id="surfside-highlights-results"></div>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('surfside_staff_sermon_highlights', 'surfside_tools_staff_sermon_highlights_shortcode');
