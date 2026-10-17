<?php
/**
 * Read-only public REST interface for the Surfside mobile Messages archive.
 *
 * These routes reuse the same local WordPress metadata queries, date
 * precedence and staff visibility decisions as the website archive. Nothing
 * is fetched from YouTube or Cloudflare, imported, or written on a GET.
 */
if (!defined('ABSPATH')) { exit; }

add_action('rest_api_init', 'surfside_tools_mobile_messages_register_routes');

function surfside_tools_mobile_messages_register_routes() {
    $public = array('methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true');

    register_rest_route('surfside/v1', '/messages', array_merge($public, array(
        'callback' => 'surfside_tools_mobile_messages_list',
    )));
    register_rest_route('surfside/v1', '/messages/series', array_merge($public, array(
        'callback' => 'surfside_tools_mobile_messages_series',
    )));
    register_rest_route('surfside/v1', '/messages/(?P<video_id>[A-Za-z0-9_-]{11})', array_merge($public, array(
        'callback' => 'surfside_tools_mobile_messages_detail',
    )));
}

/**
 * Reject malformed REST filters rather than silently expanding an invalid
 * series/year/page request into an unfiltered archive.
 */
function surfside_tools_mobile_messages_filters(WP_REST_Request $request) {
    $search = $request->get_param('search');
    $year = $request->get_param('year');
    $series = $request->get_param('series');
    $page = $request->get_param('page');

    foreach (array($search, $year, $series, $page) as $value) {
        if ($value !== null && !is_string($value) && !is_int($value)) {
            return new WP_Error('surfside_messages_invalid_filter',
                'Invalid message archive filter.', array('status' => 400));
        }
    }
    $search = $search === null ? '' : (string) $search;
    $year = $year === null ? '' : trim((string) $year);
    $series = $series === null ? '' : trim((string) $series);
    $page = $page === null ? '1' : (string) $page;

    if (($year !== '' && !preg_match('/^(19|20)\d{2}$/', $year))
        || ($series !== '' && !surfside_tools_youtube_valid_playlist_id($series))
        || !preg_match('/^[1-9]\d{0,5}$/', $page) || (int) $page > 100000) {
        return new WP_Error('surfside_messages_invalid_filter',
            'Invalid archive year, series or page.', array('status' => 400));
    }

    // Match the public site's established bounds and filter behavior.
    return surfside_tools_public_messages_filters(array(
        'sm_search' => $search,
        'sm_year' => $year,
        'sm_series' => $series,
        'sm_page' => $page,
    ));
}

/** Date labels are explicit when the fallback is YouTube publication, not a sermon date. */
function surfside_tools_mobile_messages_serialize_video($row) {
    $id = (string) ($row['video_id'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) return null;
    $original = (string) ($row['title'] ?? '');
    $date = (string) ($row['message_date'] ?? '');
    $source = (string) ($row['date_source'] ?? 'unknown');
    if (!in_array($source, array('sermon', 'youtube', 'unknown'), true)) $source = 'unknown';
    $heading = surfside_tools_public_messages_featured_heading($original);

    return array(
        'id' => $id,
        'title' => $original,
        'display_title' => (string) ($heading['heading'] ?? $original),
        'subtitle' => (string) ($heading['context'] ?? ''),
        'date' => array(
            'value' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
            'source' => $date !== '' ? $source : 'unknown',
            'label' => surfside_tools_public_messages_date_label($date, $source),
        ),
        'thumbnail_url' => esc_url_raw((string) ($row['thumbnail_url'] ?? ''), array('https')),
        'watch_url' => 'https://www.youtube.com/watch?v=' . rawurlencode($id),
        'embed_url' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id),
    );
}

/** Only publicly visible series are sent to the app. */
function surfside_tools_mobile_messages_serialize_series($row) {
    return array(
        'id' => (string) ($row['playlist_id'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'message_count' => max(0, (int) ($row['message_count'] ?? 0)),
        'thumbnail_url' => esc_url_raw((string) ($row['sample_thumbnail'] ?? ''), array('https')),
    );
}

/** GET /wp-json/surfside/v1/messages?search=&year=&series=&page= */
function surfside_tools_mobile_messages_list(WP_REST_Request $request) {
    $filters = surfside_tools_mobile_messages_filters($request);
    if (is_wp_error($filters)) return $filters;

    $master = surfside_tools_public_messages_master_id();
    if ($master === '') {
        return rest_ensure_response(array(
            'api_version' => 1,
            'available' => false,
            'latest' => null,
            'years' => array(),
            'items' => array(),
            'pagination' => array('page' => 1, 'page_size' => SURFSIDE_PUBLIC_MESSAGES_PER_PAGE,
                'total' => 0, 'total_pages' => 1, 'has_more' => false),
        ));
    }

    if ($filters['series'] !== '') {
        $visible_series = surfside_tools_public_messages_series($master);
        $allowed = array_column($visible_series, 'playlist_id');
        if (!in_array($filters['series'], $allowed, true)) {
            // This includes staff-hidden series. Never permit direct access
            // to a hidden series via a guessed playlist ID.
            return new WP_Error('surfside_messages_series_unavailable',
                'This sermon series is not available.', array('status' => 404));
        }
    }

    $list = surfside_tools_public_messages_list($master, $filters);
    $items = array();
    foreach ($list['rows'] as $row) {
        $video = surfside_tools_mobile_messages_serialize_video($row);
        if ($video !== null) $items[] = $video;
    }

    $is_home = $filters['q'] === '' && $filters['year'] === '' && $filters['series'] === ''
        && $filters['page'] === 1;
    $latest = $is_home ? surfside_tools_public_messages_latest($master) : null;
    $page = (int) $list['page'];
    $pages = (int) $list['pages'];
    return rest_ensure_response(array(
        'api_version' => 1,
        'available' => true,
        'latest' => $latest ? surfside_tools_mobile_messages_serialize_video($latest) : null,
        'years' => surfside_tools_public_messages_years($master),
        'items' => $items,
        'pagination' => array(
            'page' => $page,
            'page_size' => SURFSIDE_PUBLIC_MESSAGES_PER_PAGE,
            'total' => (int) $list['total'],
            'total_pages' => $pages,
            'has_more' => $page < $pages,
        ),
    ));
}

/** GET /wp-json/surfside/v1/messages/series */
function surfside_tools_mobile_messages_series(WP_REST_Request $request) {
    $master = surfside_tools_public_messages_master_id();
    if ($master === '') {
        return rest_ensure_response(array('api_version' => 1, 'available' => false, 'series' => array()));
    }

    $rows = surfside_tools_public_messages_series($master);
    $series = array();
    foreach ($rows as $row) $series[] = surfside_tools_mobile_messages_serialize_series($row);
    return rest_ensure_response(array('api_version' => 1, 'available' => true, 'series' => $series));
}

/** GET /wp-json/surfside/v1/messages/<youtube-id> */
function surfside_tools_mobile_messages_detail(WP_REST_Request $request) {
    $id = $request->get_param('video_id');
    if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
        return new WP_Error('surfside_messages_invalid_video',
            'Invalid message video ID.', array('status' => 400));
    }

    $master = surfside_tools_public_messages_master_id();
    if ($master === '') {
        return new WP_Error('surfside_messages_not_found',
            'This message is not available.', array('status' => 404));
    }
    $row = surfside_tools_public_messages_detail($master, $id);
    if (!$row) {
        // Staff-excluded and non-master videos also return 404 here.
        return new WP_Error('surfside_messages_not_found',
            'This message is not available.', array('status' => 404));
    }

    $visible_series = surfside_tools_public_messages_series($master);
    $memberships = surfside_tools_public_messages_video_series($master, $id, $visible_series);
    $series = array();
    foreach ($memberships as $membership) {
        $series[] = array('id' => (string) $membership['playlist_id'],
            'title' => (string) $membership['title']);
    }

    return rest_ensure_response(array(
        'api_version' => 1,
        'message' => surfside_tools_mobile_messages_serialize_video($row),
        'series' => $series,
    ));
}
