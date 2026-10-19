<?php
/**
 * Staff-only YouTube Message Archive review.
 *
 * Imported YouTube metadata remains authoritative and untouched. Staff choices
 * live in a separate review table/option and survive future metadata syncs.
 * This file adds no public API and no visitor-request database work.
 */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_YT_REVIEW_SCHEMA_OPTION = 'surfside_tools_youtube_review_schema';
const SURFSIDE_YT_REVIEW_SCHEMA_VERSION = 1;
const SURFSIDE_YT_SERIES_EXCLUDED_OPTION = 'surfside_tools_youtube_series_exclusions';
const SURFSIDE_YT_REVIEW_PAGE_SIZE = 20;

function surfside_tools_youtube_review_table() {
    global $wpdb;
    return $wpdb->prefix . 'surfside_yt_video_review';
}

function surfside_tools_youtube_review_is_ready() {
    return (int) get_option(SURFSIDE_YT_REVIEW_SCHEMA_OPTION, 0) >= SURFSIDE_YT_REVIEW_SCHEMA_VERSION;
}

/** Create the correction table only when staff explicitly save a decision. */
function surfside_tools_youtube_review_ensure_table() {
    if (surfside_tools_youtube_review_is_ready()) return true;

    global $wpdb;
    $table = surfside_tools_youtube_review_table();
    if (!function_exists('dbDelta')) require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $collation = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
        video_id varchar(32) NOT NULL,
        manual_date date DEFAULT NULL,
        disposition varchar(20) NOT NULL DEFAULT 'review',
        updated_at datetime NOT NULL,
        PRIMARY KEY  (video_id),
        KEY disposition (disposition)
    ) {$collation};");
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    if ($found !== $table) {
        return new WP_Error('surfside_youtube_review_schema', 'The review table could not be created. No changes were saved.');
    }
    update_option(SURFSIDE_YT_REVIEW_SCHEMA_OPTION, SURFSIDE_YT_REVIEW_SCHEMA_VERSION, false);
    return true;
}

/** A blank date clears a correction; invalid dates are never guessed. */
function surfside_tools_youtube_review_validate_date($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    if (!preg_match('/^(19|20)\d{2}-(0[1-9]|1[0-2])-([0-2]\d|3[01])$/', $value)) return false;
    $parts = explode('-', $value);
    return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]) ? $value : false;
}

function surfside_tools_youtube_review_disposition($row) {
    $decision = (string) ($row['disposition'] ?? '');
    if ($decision === 'exclude') return 'exclude';
    if ($decision === 'review') return 'review';
    if ($decision === 'include') return 'include';
    return empty($row['effective_date']) ? 'review' : 'include';
}

/** Staff-only URLs; never accept arbitrary redirect destinations from a POST. */
function surfside_tools_youtube_manager_url($args = array()) {
    return add_query_arg(array_merge(array('view' => 'message-archive'), (array) $args),
        surfside_tools_staff_page_url('site-management'));
}

function surfside_tools_youtube_review_excluded_series() {
    $saved = get_option(SURFSIDE_YT_SERIES_EXCLUDED_OPTION, array());
    return is_array($saved) ? $saved : array();
}

function surfside_tools_youtube_review_master_id() {
    $state = surfside_tools_youtube_state();
    $id = isset($state['master_id']) ? (string) $state['master_id'] : '';
    return surfside_tools_youtube_valid_playlist_id($id) ? $id : '';
}

/** Avoid JOINs against a table that hasn't been created yet. */
function surfside_tools_youtube_review_sql_parts() {
    $review = surfside_tools_youtube_review_is_ready();
    $join = $review ? ' LEFT JOIN ' . surfside_tools_youtube_review_table() . ' r ON r.video_id = v.video_id' : '';
    $date = $review ? 'COALESCE(r.manual_date, v.sermon_date)' : 'v.sermon_date';
    $disposition = $review ? 'r.disposition' : 'NULL';
    $manual = $review ? 'r.manual_date' : 'NULL';
    $needs = $review
        ? "(r.disposition = 'review' OR (r.disposition IS NULL AND v.sermon_date IS NULL))"
        : '(v.sermon_date IS NULL)';
    return array(
        'join' => $join,
        'date' => $date,
        'disposition' => $disposition,
        'manual' => $manual,
        'needs' => $needs,
        'excluded' => $review ? "(r.disposition = 'exclude')" : '(1 = 0)',
        'included' => $review ? "(r.disposition IS NULL OR r.disposition <> 'exclude')" : '(1 = 1)',
    );
}

/** One aggregate query: no per-video queries for the dashboard counters. */
function surfside_tools_youtube_review_summary($master) {
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $sql = surfside_tools_youtube_review_sql_parts();
    $query = "SELECT COUNT(*) AS total,
        SUM(CASE WHEN {$sql['needs']} THEN 1 ELSE 0 END) AS needs_review,
        SUM(CASE WHEN {$sql['excluded']} THEN 1 ELSE 0 END) AS excluded,
        SUM(CASE WHEN {$sql['date']} IS NULL THEN 1 ELSE 0 END) AS undated
        FROM {$t['videos']} v
        INNER JOIN {$t['members']} m ON m.video_id = v.video_id
        {$sql['join']} WHERE m.playlist_id = %s";
    $row = $wpdb->get_row($wpdb->prepare($query, $master), ARRAY_A);
    return array(
        'total' => (int) ($row['total'] ?? 0),
        'needs_review' => (int) ($row['needs_review'] ?? 0),
        'excluded' => (int) ($row['excluded'] ?? 0),
        'undated' => (int) ($row['undated'] ?? 0),
    );
}

/** All filters are parameterized; all list queries are capped at 20 rows. */
function surfside_tools_youtube_review_list($master, $filters) {
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $sql = surfside_tools_youtube_review_sql_parts();
    $where = array('m.playlist_id = %s');
    $params = array($master);

    if ($filters['search'] !== '') {
        $like = '%' . $wpdb->esc_like($filters['search']) . '%';
        $where[] = "(v.title LIKE %s OR EXISTS (
            SELECT 1 FROM {$t['members']} sm
            INNER JOIN {$t['playlists']} sp ON sp.playlist_id = sm.playlist_id
            WHERE sm.video_id = v.video_id AND sp.category = 'series' AND sp.title LIKE %s
        ))";
        $params[] = $like;
        $params[] = $like;
    }
    if ($filters['status'] === 'review') $where[] = $sql['needs'];
    elseif ($filters['status'] === 'exclude') $where[] = $sql['excluded'];
    elseif ($filters['status'] === 'include') $where[] = $sql['included'];
    elseif ($filters['status'] === 'undated') $where[] = $sql['date'] . ' IS NULL';

    if ($filters['year'] !== '') {
        $where[] = "YEAR({$sql['date']}) = %d";
        $params[] = (int) $filters['year'];
    }
    if ($filters['series'] !== '') {
        $where[] = "EXISTS (SELECT 1 FROM {$t['members']} fm
            WHERE fm.video_id = v.video_id AND fm.playlist_id = %s)";
        $params[] = $filters['series'];
    }

    $base = " FROM {$t['videos']} v
        INNER JOIN {$t['members']} m ON m.video_id = v.video_id
        {$sql['join']} WHERE " . implode(' AND ', $where);

    $count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*)' . $base, $params));
    $per_page = SURFSIDE_YT_REVIEW_PAGE_SIZE;
    $pages = max(1, (int) ceil($count / $per_page));
    $page = min(max(1, (int) $filters['page']), $pages);
    $offset = ($page - 1) * $per_page;

    // A review queue must have stable ordering when a staff member corrects
    // the sermon date: otherwise the next item can unexpectedly move.
    $order = $filters['status'] === 'review'
        ? "v.uploaded_at DESC, v.video_id ASC"
        : "({$sql['date']} IS NULL) ASC, {$sql['date']} DESC, v.uploaded_at DESC, v.video_id ASC";
    $list_sql = "SELECT v.video_id, v.title, v.thumbnail_url, v.uploaded_at, v.sermon_date,
            {$sql['manual']} AS manual_date,
            {$sql['disposition']} AS disposition,
            {$sql['date']} AS effective_date"
        . $base . " ORDER BY {$order} LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare($list_sql, array_merge($params, array($per_page, $offset))), ARRAY_A);

    return array('rows' => is_array($rows) ? $rows : array(),
        'count' => $count, 'page' => $page, 'pages' => $pages);
}

function surfside_tools_youtube_review_video($master, $video_id) {
    if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $video_id)) return null;
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $sql = surfside_tools_youtube_review_sql_parts();

    $query = "SELECT v.video_id, v.title, v.thumbnail_url, v.uploaded_at, v.sermon_date,
        {$sql['manual']} AS manual_date,
        {$sql['disposition']} AS disposition,
        {$sql['date']} AS effective_date
        FROM {$t['videos']} v
        INNER JOIN {$t['members']} m ON m.video_id = v.video_id
        {$sql['join']} WHERE m.playlist_id = %s AND v.video_id = %s";
    $row = $wpdb->get_row($wpdb->prepare($query, $master, $video_id), ARRAY_A);
    return is_array($row) ? $row : null;
}

function surfside_tools_youtube_review_series($master) {
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    // Series membership was already restricted to the master Messages playlist
    // by the importer. Here we only display those discovered relationships.
    $rows = $wpdb->get_results(
        "SELECT p.playlist_id, p.title, COUNT(m.video_id) AS video_count
         FROM {$t['playlists']} p
         LEFT JOIN {$t['members']} m ON m.playlist_id = p.playlist_id
         WHERE p.category = 'series'
         GROUP BY p.playlist_id, p.title ORDER BY p.title ASC LIMIT 500",
        ARRAY_A
    );
    return is_array($rows) ? $rows : array();
}

function surfside_tools_youtube_review_video_series($video_id) {
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    return $wpdb->get_col($wpdb->prepare(
        "SELECT p.title FROM {$t['playlists']} p
         INNER JOIN {$t['members']} m ON m.playlist_id = p.playlist_id
         WHERE m.video_id = %s AND p.category = 'series'
         ORDER BY p.title LIMIT 30", $video_id
    ));
}

/**
 * Persist only staff decisions, separate from YouTube's synchronized metadata.
 * These functions have no effect on the master playlist or YouTube titles.
 */
function surfside_tools_youtube_review_save_video($master, $id, $date, $decision) {
    if (!preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $id)
        || !in_array($decision, array('include', 'review', 'exclude'), true)
        || surfside_tools_youtube_review_validate_date($date) === false
        || !surfside_tools_youtube_review_video($master, $id)) {
        return new WP_Error('surfside_youtube_review_invalid_video', 'The video or review decision is invalid.');
    }

    $ready = surfside_tools_youtube_review_ensure_table();
    if (is_wp_error($ready)) return $ready;

    global $wpdb;
    $saved = $wpdb->replace(surfside_tools_youtube_review_table(), array(
        'video_id' => $id,
        'manual_date' => $date !== '' ? $date : null,
        'disposition' => $decision,
        'updated_at' => current_time('mysql', true),
    ));
    return $saved !== false ? true : new WP_Error('surfside_youtube_review_write', 'The review could not be saved.');
}

function surfside_tools_youtube_review_save_series($id, $hide) {
    if (!surfside_tools_youtube_valid_playlist_id($id)) {
        return new WP_Error('surfside_youtube_review_invalid_series', 'Invalid playlist.');
    }
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT playlist_id FROM {$t['playlists']} WHERE playlist_id = %s AND category = 'series'",
        $id
    ));
    if ($exists !== $id) {
        return new WP_Error('surfside_youtube_review_unknown_series', 'Playlist is not a discovered sermon series.');
    }
    $excluded = surfside_tools_youtube_review_excluded_series();
    if ($hide) $excluded[$id] = 1;
    else unset($excluded[$id]);
    update_option(SURFSIDE_YT_SERIES_EXCLUDED_OPTION, $excluded, false);
    return true;
}

/** Save one staff decision, never modifying the imported video data. */
function surfside_tools_youtube_review_handle_post() {
    if (!current_user_can('upload_files')) {
        wp_die('You do not have permission to review sermons.', '', array('response' => 403));
    }
    check_admin_referer('surfside_youtube_review', 'surfside_youtube_review_nonce');

    $mode = isset($_POST['review_mode']) && is_string($_POST['review_mode'])
        ? sanitize_key(wp_unslash($_POST['review_mode'])) : '';
    $master = surfside_tools_youtube_review_master_id();
    $notice = 'invalid';
    // Return to the same search, status, year, series and page after saving.
    $filters = surfside_tools_youtube_review_filters($_POST);
    $redirect_args = surfside_tools_youtube_review_filter_args($filters);
    $position_raw = $_POST['archive_position'] ?? null;
    $position = is_string($position_raw) && preg_match('/^\d{1,6}$/', $position_raw)
        ? min(100000, absint($position_raw)) : null;

    if ($master !== '' && $mode === 'video') {
        $id = isset($_POST['video_id']) && is_string($_POST['video_id'])
            ? sanitize_text_field(wp_unslash($_POST['video_id'])) : '';
        $decision = isset($_POST['disposition']) && is_string($_POST['disposition'])
            ? sanitize_key(wp_unslash($_POST['disposition'])) : '';
        $date_input = isset($_POST['manual_date']) && is_string($_POST['manual_date'])
            ? sanitize_text_field(wp_unslash($_POST['manual_date'])) : '';
        $date = surfside_tools_youtube_review_validate_date($date_input);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) $redirect_args['video'] = $id;
        if ($position !== null) $redirect_args['archive_position'] = $position;

        if ($date === false) {
            $notice = 'bad_date';
        } else {
            $saved = surfside_tools_youtube_review_save_video($master, $id, $date, $decision);
            $notice = is_wp_error($saved) ? 'error' : 'saved';
            $after = isset($_POST['review_after']) && is_string($_POST['review_after'])
                ? sanitize_key(wp_unslash($_POST['review_after'])) : 'stay';
            if ($notice === 'saved' && $after === 'next' && $position !== null && $filters['status'] === 'review') {
                $next = surfside_tools_youtube_review_next_in_queue($master, $filters, $position, $id);
                if ($next) {
                    $redirect_args['video'] = $next['video'];
                    $redirect_args['archive_position'] = $next['position'];
                    if ($next['page'] > 1) $redirect_args['archive_page'] = $next['page'];
                    else unset($redirect_args['archive_page']);
                } else {
                    unset($redirect_args['video'], $redirect_args['archive_position']);
                    $notice = 'end_of_queue';
                }
            }
        }
    } elseif ($master !== '' && $mode === 'series') {
        $id = isset($_POST['playlist_id']) && is_string($_POST['playlist_id'])
            ? sanitize_text_field(wp_unslash($_POST['playlist_id'])) : '';
        $hide = !empty($_POST['hide_series']);
        $redirect_args['tab'] = 'series';
        $saved = surfside_tools_youtube_review_save_series($id, $hide);
        $notice = is_wp_error($saved) ? 'invalid' : 'saved';
    }

    $redirect_args['review_notice'] = $notice;
    $destination = surfside_tools_youtube_manager_url($redirect_args);
    // Use POST/Redirect/GET so refreshing does not resubmit a decision.
    // A failed redirect must not silently leave staff on an empty response.
    if (!wp_safe_redirect($destination, 303, 'Surfside Tools')) {
        wp_die(
            'The review action was processed, but WordPress could not redirect. ' .
            '<a href="' . esc_url($destination) . '">Return to Message Archive</a>.',
            'Message Archive',
            array('response' => 500)
        );
    }
    exit;
}
add_action('admin_post_surfside_youtube_review', 'surfside_tools_youtube_review_handle_post');

/**
 * Review forms are part of the front-end staff portal, not WP Admin.
 * Handle only Message Archive POSTs before the theme outputs page content.
 * Retain the admin-post hook above as compatibility for old/previous forms.
 */
function surfside_tools_youtube_review_frontend_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !is_page('site-management')
        || !isset($_GET['view'])
        || !is_string($_GET['view'])
        || sanitize_key(wp_unslash($_GET['view'])) !== 'message-archive'
        || !isset($_POST['action'])
        || !is_string($_POST['action'])
        || sanitize_key(wp_unslash($_POST['action'])) !== 'surfside_youtube_review') {
        return;
    }
    surfside_tools_youtube_review_handle_post();
}
add_action('template_redirect', 'surfside_tools_youtube_review_frontend_post', 1);

/**
 * Share the same allowlisted filter parsing across list links and the save
 * handler. The POST may only preserve supported fields, never a redirect URL.
 */
function surfside_tools_youtube_review_filters($source = null) {
    $source = is_array($source) ? $source : $_GET;
    $search = isset($source['archive_q']) && is_string($source['archive_q'])
        ? sanitize_text_field(wp_unslash($source['archive_q'])) : '';
    $status = isset($source['archive_status']) && is_string($source['archive_status'])
        ? sanitize_key(wp_unslash($source['archive_status'])) : 'all';
    $year = isset($source['archive_year']) && is_string($source['archive_year'])
        ? sanitize_text_field(wp_unslash($source['archive_year'])) : '';
    $series = isset($source['archive_series']) && is_string($source['archive_series'])
        ? sanitize_text_field(wp_unslash($source['archive_series'])) : '';
    return array(
        'search' => substr(trim($search), 0, 90),
        'status' => in_array($status, array('all', 'review', 'include', 'exclude', 'undated'), true) ? $status : 'all',
        'year' => preg_match('/^(19|20)\d{2}$/', $year) ? $year : '',
        'series' => surfside_tools_youtube_valid_playlist_id($series) ? $series : '',
        'page' => isset($source['archive_page']) ? max(1, min(100000, absint($source['archive_page']))) : 1,
    );
}

/** Round-trip filters and pagination through video links and review saves. */
function surfside_tools_youtube_review_filter_args($filters) {
    $args = array();
    if ($filters['search'] !== '') $args['archive_q'] = $filters['search'];
    if ($filters['status'] !== 'all') $args['archive_status'] = $filters['status'];
    if ($filters['year'] !== '') $args['archive_year'] = $filters['year'];
    if ($filters['series'] !== '') $args['archive_series'] = $filters['series'];
    if ($filters['page'] > 1) $args['archive_page'] = $filters['page'];
    return $args;
}

/**
 * Continue after the saved item in the same Needs Review queue.
 * When inclusion/exclusion removes the current item, the next one slides into
 * its old position; if it remains under review, advance one position instead.
 */
function surfside_tools_youtube_review_next_in_queue($master, $filters, $position, $current_id) {
    if ($filters['status'] !== 'review' || $position < 0 || $position > 100000) return null;

    $page_size = SURFSIDE_YT_REVIEW_PAGE_SIZE;
    $filters['page'] = intdiv($position, $page_size) + 1;
    $list = surfside_tools_youtube_review_list($master, $filters);
    if ($position >= $list['count']) return null;

    $slot = $position % $page_size;
    if (($list['rows'][$slot]['video_id'] ?? '') === $current_id) {
        $position++;
        if ($position >= $list['count']) return null;
        $next_page = intdiv($position, $page_size) + 1;
        if ($next_page !== $filters['page']) {
            $filters['page'] = $next_page;
            $list = surfside_tools_youtube_review_list($master, $filters);
        }
        $slot = $position % $page_size;
    }
    $next_id = (string) ($list['rows'][$slot]['video_id'] ?? '');
    return preg_match('/^[A-Za-z0-9_-]{11}$/', $next_id)
        ? array('video' => $next_id, 'position' => $position, 'page' => $filters['page'])
        : null;
}

function surfside_tools_youtube_review_ui_styles() {
    wp_register_style('surfside-youtube-review-ui', false, array(), SURFSIDE_TOOLS_VERSION);
    wp_enqueue_style('surfside-youtube-review-ui');
    wp_add_inline_style('surfside-youtube-review-ui', <<<'CSS'
.surfside-yt-manager .surfside-staff-hero{margin-bottom:22px}
.surfside-yt-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
.surfside-yt-tabs a{display:inline-block;padding:10px 16px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#0b4f9c;font-weight:750;background:#fff}
.surfside-yt-tabs a[aria-current="page"]{background:#0b4f9c;color:#fff;border-color:#0b4f9c}
.surfside-yt-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:20px}
.surfside-yt-metric,.surfside-yt-panel{border-radius:14px;background:#fff;border:1px solid #dce3eb;padding:18px;box-shadow:0 5px 15px rgba(7,27,58,.05)}
.surfside-yt-metric strong{font-size:28px;display:block;line-height:1.25;color:#071b3a}
.surfside-yt-metric span{color:#526279;font-size:13px;font-weight:650}
.surfside-yt-panel{margin-bottom:18px}
.surfside-yt-panel h2{margin:0 0 12px;color:#071b3a}
.surfside-yt-filters{display:grid;grid-template-columns:minmax(150px,2fr) repeat(3,minmax(110px,1fr)) auto;gap:10px;align-items:end}
.surfside-yt-filters label,.surfside-yt-edit label{font-weight:700;color:#263953;display:block;margin-bottom:6px}
.surfside-yt-manager input,.surfside-yt-manager select{width:100%;max-width:100%;min-height:43px;padding:9px 10px;border:1px solid #a6b6c9;border-radius:7px;box-sizing:border-box;font:inherit}
.surfside-yt-manager button{background:#0b4f9c;color:white;border:0;border-radius:8px;min-height:43px;padding:10px 17px;font:inherit;font-weight:700;cursor:pointer}
.surfside-yt-link{color:#0b4f9c;font-weight:700;text-decoration:none}
.surfside-yt-link:hover,.surfside-yt-link:focus{text-decoration:underline}
.surfside-yt-list{margin:0;padding:0;list-style:none}
.surfside-yt-list li+li{border-top:1px solid #e2e8f0}
.surfside-yt-item{display:flex;align-items:center;gap:15px;padding:15px 0}
.surfside-yt-thumb{width:126px;aspect-ratio:16/9;object-fit:cover;background:#edf2f8;border-radius:7px;flex:none}
.surfside-yt-item-content{min-width:0;flex:1}
.surfside-yt-item strong{font-size:17px;color:#071b3a;display:block;line-height:1.35}
.surfside-yt-muted{font-size:13px;color:#586b82;margin:6px 0 0}
.surfside-yt-chip{display:inline-block;padding:4px 9px;border-radius:14px;background:#edf2f7;color:#40536b;font-size:12px;font-weight:700;white-space:nowrap}
.surfside-yt-chip.review{background:#fff3d6;color:#805800}
.surfside-yt-chip.exclude{background:#fde9e7;color:#922b21}
.surfside-yt-notice{border-left:4px solid #0b4f9c;background:#ecf5ff;padding:12px 16px;margin-bottom:16px}
.surfside-yt-notice.error{border-color:#b42318;background:#fff1f0}
.surfside-yt-pager{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:20px}
.surfside-yt-preview{width:100%;aspect-ratio:16/9;border:0;background:#071b3a;border-radius:12px;max-width:880px;display:block;margin:14px 0}
.surfside-yt-edit{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;max-width:800px}
.surfside-yt-edit .surfside-yt-full{grid-column:1/-1}
.surfside-yt-save-actions{display:flex;gap:12px;flex-wrap:wrap}
.surfside-yt-manager .surfside-yt-button-secondary{background:#fff;color:#0b4f9c;border:1px solid #0b4f9c}
.surfside-yt-series-item{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:15px 0;border-top:1px solid #e2e8f0}
.surfside-yt-series-item form{flex-shrink:0}
.surfside-yt-series-item button{background:#fff;border:1px solid #0b4f9c;color:#0b4f9c}
@media(max-width:850px){.surfside-yt-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.surfside-yt-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.surfside-yt-filters .surfside-yt-search{grid-column:1/-1}}
@media(max-width:520px){.surfside-yt-item{align-items:flex-start;flex-wrap:wrap}.surfside-yt-thumb{width:105px}.surfside-yt-chip{margin-left:120px}.surfside-yt-filters{grid-template-columns:1fr}.surfside-yt-filters .surfside-yt-search{grid-column:auto}.surfside-yt-edit{grid-template-columns:1fr}.surfside-yt-series-item{align-items:flex-start;flex-wrap:wrap}.surfside-yt-metric{padding:14px}}
CSS
    );
}

/** Front-end staff page only. No data is returned to unauthenticated visitors. */
function surfside_tools_staff_youtube_message_manager_shortcode() {
    if (function_exists('surfside_tools_prevent_cache')) surfside_tools_prevent_cache();
    if (function_exists('surfside_tools_staff_enqueue_styles')) surfside_tools_staff_enqueue_styles();

    if (!is_user_logged_in()) {
        return function_exists('surfside_tools_staff_login_box')
            ? surfside_tools_staff_login_box('Please sign in to review the Message Archive.')
            : '<p>Please sign in.</p>';
    }
    if (!current_user_can('upload_files')) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to review messages.</p></div>';
    }
    surfside_tools_youtube_review_ui_styles();

    $master = surfside_tools_youtube_review_master_id();
    $base_ready = (int) get_option(SURFSIDE_YT_SCHEMA_OPTION, 0) >= SURFSIDE_YT_SCHEMA_VERSION && $master !== '';
    $filters = surfside_tools_youtube_review_filters();
    $tab = isset($_GET['tab']) && $_GET['tab'] === 'series' ? 'series' : 'videos';
    $requested_video = isset($_GET['video']) && is_string($_GET['video'])
        ? sanitize_text_field(wp_unslash($_GET['video'])) : '';
    $video = $base_ready && $tab === 'videos' && $requested_video !== ''
        ? surfside_tools_youtube_review_video($master, $requested_video) : null;

    $series_rows = $base_ready ? surfside_tools_youtube_review_series($master) : array();
    $excluded_series = surfside_tools_youtube_review_excluded_series();
    $summary = $base_ready ? surfside_tools_youtube_review_summary($master) : array('total'=>0,'needs_review'=>0,'excluded'=>0,'undated'=>0);
    $list = $base_ready && $tab === 'videos' && $requested_video === ''
        ? surfside_tools_youtube_review_list($master, $filters) : null;
    $list_args = surfside_tools_youtube_review_filter_args($filters);
    $position_get = $_GET['archive_position'] ?? null;
    $review_position = is_string($position_get) && preg_match('/^\d{1,6}$/', $position_get)
        ? min(100000, absint($position_get)) : null;

    $notice_code = isset($_GET['review_notice']) && is_string($_GET['review_notice'])
        ? sanitize_key(wp_unslash($_GET['review_notice'])) : '';
    $notice_text = array(
        'saved' => 'Review decision saved. Future YouTube syncs will not overwrite it.',
        'end_of_queue' => 'Saved. You have reached the end of this filtered review list.',
        'bad_date' => 'Please enter a valid sermon date or leave it blank.',
        'error' => 'The review change could not be saved. Please try again.',
        'invalid' => 'The selected message or playlist could not be found.',
    );
    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-yt-manager">
        <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('site-management')); ?>">← Back to Manage Website</a></div>
        <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Website · Messages</p>
            <h1>Message Archive Manager</h1>
            <p class="surfside-staff-muted">Review YouTube sermons, correct dates, and control which videos and series will appear in the future public archive. No YouTube videos or notes are changed.</p>
        </section>

        <?php if (isset($notice_text[$notice_code])) : ?>
            <div class="surfside-yt-notice<?php echo !in_array($notice_code, array('saved','end_of_queue'), true) ? ' error' : ''; ?>"><?php echo esc_html($notice_text[$notice_code]); ?></div>
        <?php endif; ?>

        <?php if (!$base_ready) : ?>
            <div class="surfside-yt-panel"><h2>Archive not ready</h2><p>Finish the first YouTube metadata synchronization under Church Settings → Integrations before reviewing messages.</p></div>
        <?php else : ?>
            <div class="surfside-yt-metrics">
                <div class="surfside-yt-metric"><strong><?php echo esc_html(number_format_i18n($summary['total'])); ?></strong><span>Master Messages videos</span></div>
                <div class="surfside-yt-metric"><strong><?php echo esc_html(number_format_i18n($summary['needs_review'])); ?></strong><span>Needs review</span></div>
                <div class="surfside-yt-metric"><strong><?php echo esc_html(number_format_i18n($summary['undated'])); ?></strong><span>Missing sermon dates</span></div>
                <div class="surfside-yt-metric"><strong><?php echo esc_html(number_format_i18n($summary['excluded'])); ?></strong><span>Excluded videos</span></div>
            </div>

            <nav class="surfside-yt-tabs" aria-label="Message Archive sections">
                <a href="<?php echo esc_url(surfside_tools_youtube_manager_url()); ?>" <?php echo $tab === 'videos' ? 'aria-current="page"' : ''; ?>>Videos</a>
                <a href="<?php echo esc_url(surfside_tools_youtube_manager_url(array('tab'=>'series'))); ?>" <?php echo $tab === 'series' ? 'aria-current="page"' : ''; ?>>Series (<?php echo esc_html(number_format_i18n(count($series_rows))); ?>)</a>
                <?php if (function_exists('surfside_tools_r2_manager_url')) : ?><a href="<?php echo esc_url(surfside_tools_r2_manager_url()); ?>">Roku Copies</a><?php endif; ?>
            </nav>

            <?php if ($tab === 'series') : ?>
                <section class="surfside-yt-panel">
                    <h2>Sermon Series Playlists</h2>
                    <p class="surfside-staff-muted">These playlists contain videos also found in Messages. Hiding a series will hide its series page in the future public archive, but those individual sermons remain in All Messages unless you exclude the videos separately.</p>
                    <?php if (!$series_rows) : ?><p>No series playlists were discovered.</p><?php endif; ?>
                    <?php foreach ($series_rows as $row) :
                        $id = (string) $row['playlist_id'];
                        $hidden = !empty($excluded_series[$id]);
                        ?>
                        <div class="surfside-yt-series-item">
                            <div><strong><?php echo esc_html($row['title']); ?></strong>
                                <p class="surfside-yt-muted"><?php echo esc_html(number_format_i18n((int) $row['video_count'])); ?> matching messages · <?php echo $hidden ? 'Series excluded from public browsing' : 'Series included'; ?></p>
                            </div>
                            <form method="post" action="<?php echo esc_url(surfside_tools_youtube_manager_url()); ?>">
                                <input type="hidden" name="action" value="surfside_youtube_review">
                                <input type="hidden" name="review_mode" value="series">
                                <input type="hidden" name="playlist_id" value="<?php echo esc_attr($id); ?>">
                                <input type="hidden" name="hide_series" value="<?php echo $hidden ? '0' : '1'; ?>">
                                <?php wp_nonce_field('surfside_youtube_review', 'surfside_youtube_review_nonce'); ?>
                                <button type="submit"><?php echo $hidden ? 'Restore Series' : 'Hide Series'; ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </section>

            <?php elseif ($requested_video !== '') : ?>
                <section class="surfside-yt-panel">
                    <p><a class="surfside-yt-link" href="<?php echo esc_url(surfside_tools_youtube_manager_url($list_args)); ?>">← <?php echo $list_args ? 'Back to filtered videos' : 'Back to video library'; ?></a></p>
                    <?php if (!$video) : ?><p>This video was not found in the master Messages playlist.</p>
                    <?php else :
                        $decision = surfside_tools_youtube_review_disposition($video);
                        $watch_url = 'https://www.youtube.com/watch?v=' . rawurlencode($video['video_id']);
                        $series = surfside_tools_youtube_review_video_series($video['video_id']);
                        ?>
                        <h2><?php echo esc_html($video['title']); ?></h2>
                        <p><span class="surfside-yt-chip <?php echo esc_attr($decision); ?>"><?php echo esc_html($decision === 'review' ? 'Needs Review' : ($decision === 'exclude' ? 'Excluded' : 'Included')); ?></span></p>
                        <iframe class="surfside-yt-preview" loading="lazy" title="<?php echo esc_attr($video['title']); ?>"
                            src="<?php echo esc_url('https://www.youtube-nocookie.com/embed/' . $video['video_id']); ?>"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                        <p><a class="surfside-yt-link" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($watch_url); ?>">Open original video on YouTube ↗</a></p>
                        <p><strong>Detected sermon date:</strong> <?php echo esc_html($video['sermon_date'] ?: 'Not found in video title'); ?><br>
                            <strong>Effective sermon date:</strong> <?php echo esc_html($video['effective_date'] ?: 'Not assigned'); ?><br>
                            <strong>YouTube upload timestamp (reference only):</strong> <?php echo esc_html($video['uploaded_at'] ?: 'Unavailable'); ?><br>
                            <strong>Series:</strong> <?php echo esc_html($series ? implode(' · ', $series) : 'No matching sermon series'); ?></p>
                        <form class="surfside-yt-edit" method="post" action="<?php echo esc_url(surfside_tools_youtube_manager_url()); ?>">
                            <input type="hidden" name="action" value="surfside_youtube_review">
                            <input type="hidden" name="review_mode" value="video">
                            <input type="hidden" name="video_id" value="<?php echo esc_attr($video['video_id']); ?>">
                            <?php foreach ($list_args as $name => $value) : ?>
                                <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>">
                            <?php endforeach; ?>
                            <?php if ($review_position !== null) : ?>
                                <input type="hidden" name="archive_position" value="<?php echo esc_attr($review_position); ?>">
                            <?php endif; ?>
                            <?php wp_nonce_field('surfside_youtube_review', 'surfside_youtube_review_nonce'); ?>
                            <div>
                                <label for="surfside-yt-manual-date">Sermon date override</label>
                                <input type="date" id="surfside-yt-manual-date" name="manual_date"
                                    value="<?php echo esc_attr($video['manual_date'] ?: ''); ?>">
                                <p class="surfside-yt-muted">Optional. Leave blank to use the title's date. Does not change the YouTube title.</p>
                            </div>
                            <div>
                                <label for="surfside-yt-disposition">Archive status</label>
                                <select id="surfside-yt-disposition" name="disposition">
                                    <option value="review" <?php selected($decision, 'review'); ?>>Needs Review — include while investigating</option>
                                    <option value="include" <?php selected($decision, 'include'); ?>>Include — publish in archive</option>
                                    <option value="exclude" <?php selected($decision, 'exclude'); ?>>Exclude — hide from public archive</option>
                                </select>
                                <p class="surfside-yt-muted">Needs Review still appears publicly by default; Exclude is the only hidden status.</p>
                            </div>
                            <div class="surfside-yt-full surfside-yt-save-actions">
                                <?php if ($filters['status'] === 'review' && $review_position !== null) : ?>
                                    <button type="submit" name="review_after" value="next">Save &amp; Next Review</button>
                                    <button type="submit" name="review_after" value="stay" class="surfside-yt-button-secondary">Save Only</button>
                                <?php else : ?>
                                    <button type="submit" name="review_after" value="stay">Save Review Decision</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

            <?php else : ?>
                <section class="surfside-yt-panel">
                    <form class="surfside-yt-filters" method="get" action="<?php echo esc_url(surfside_tools_staff_page_url('site-management')); ?>">
                        <input type="hidden" name="view" value="message-archive">
                        <div class="surfside-yt-search">
                            <label for="surfside-yt-q">Search videos or series</label>
                            <input id="surfside-yt-q" type="search" name="archive_q" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Title or series">
                        </div>
                        <div>
                            <label for="surfside-yt-status">Status</label>
                            <select id="surfside-yt-status" name="archive_status">
                                <?php foreach (array('all'=>'All videos','review'=>'Needs review','include'=>'Included','exclude'=>'Excluded','undated'=>'Missing date') as $key=>$label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($filters['status'], $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="surfside-yt-year">Year</label>
                            <input id="surfside-yt-year" type="number" name="archive_year" min="1900" max="2099" placeholder="All years" value="<?php echo esc_attr($filters['year']); ?>">
                        </div>
                        <div>
                            <label for="surfside-yt-series">Series</label>
                            <select id="surfside-yt-series" name="archive_series">
                                <option value="">All series</option>
                                <?php foreach ($series_rows as $row) : ?>
                                    <option value="<?php echo esc_attr($row['playlist_id']); ?>" <?php selected($filters['series'], $row['playlist_id']); ?>><?php echo esc_html($row['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><button type="submit">Filter</button></div>
                    </form>
                    <p class="surfside-yt-muted"><?php echo esc_html(number_format_i18n($list['count'])); ?> matching messages.
                        <?php if ($filters['search'] || $filters['status'] !== 'all' || $filters['year'] || $filters['series']) : ?>
                            <a class="surfside-yt-link" href="<?php echo esc_url(surfside_tools_youtube_manager_url()); ?>">Clear filters</a>
                        <?php endif; ?>
                    </p>
                </section>
                <section class="surfside-yt-panel">
                    <?php if (!$list['rows']) : ?><p>No messages match these filters.</p>
                    <?php else : ?><ul class="surfside-yt-list">
                        <?php foreach ($list['rows'] as $item_index => $row) :
                            $decision = surfside_tools_youtube_review_disposition($row);
                            $absolute_position = ($list['page'] - 1) * SURFSIDE_YT_REVIEW_PAGE_SIZE + (int) $item_index;
                            ?>
                            <li><div class="surfside-yt-item">
                                <?php if (!empty($row['thumbnail_url'])) : ?>
                                    <img class="surfside-yt-thumb" loading="lazy" alt="" src="<?php echo esc_url($row['thumbnail_url']); ?>">
                                <?php endif; ?>
                                <div class="surfside-yt-item-content">
                                    <strong><?php echo esc_html($row['title']); ?></strong>
                                    <p class="surfside-yt-muted"><?php echo esc_html($row['effective_date'] ?: 'Sermon date not assigned'); ?>
                                        <?php if (!empty($row['manual_date'])) : ?> · Corrected manually<?php endif; ?></p>
                                    <p><a class="surfside-yt-link" href="<?php echo esc_url(surfside_tools_youtube_manager_url(array_merge($list_args, array('video'=>$row['video_id'], 'archive_position'=>$absolute_position)))); ?>">Review Video ›</a></p>
                                </div>
                                <span class="surfside-yt-chip <?php echo esc_attr($decision); ?>"><?php echo esc_html($decision === 'review' ? 'Needs Review' : ($decision === 'exclude' ? 'Excluded' : 'Included')); ?></span>
                            </div></li>
                        <?php endforeach; ?>
                    </ul><?php endif; ?>
                    <?php if ($list['pages'] > 1) : ?>
                        <nav class="surfside-yt-pager" aria-label="Video library pages">
                            <span><?php if ($list['page'] > 1) : ?><a class="surfside-yt-link" href="<?php echo esc_url(surfside_tools_youtube_manager_url(array_merge($list_args, array('archive_page'=>$list['page']-1)))); ?>">← Previous</a><?php endif; ?></span>
                            <span><?php echo esc_html(sprintf('Page %d of %d', $list['page'], $list['pages'])); ?></span>
                            <span><?php if ($list['page'] < $list['pages']) : ?><a class="surfside-yt-link" href="<?php echo esc_url(surfside_tools_youtube_manager_url(array_merge($list_args, array('archive_page'=>$list['page']+1)))); ?>">Next →</a><?php endif; ?></span>
                        </nav>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_staff_youtube_message_manager', 'surfside_tools_staff_youtube_message_manager_shortcode');
