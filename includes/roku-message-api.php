<?php
/**
 * Roku-ready sermon catalog. Metadata and staff visibility remain owned by
 * the canonical YouTube archive; only already verified R2 MP4s are listed.
 *
 * Public GETs perform bounded local WordPress database reads ONLY. No schema
 * changes, uploads, reconciliation or upstream requests run on a Roku GET.
 */
if (!defined('ABSPATH')) { exit; }

add_action('rest_api_init', 'surfside_tools_roku_messages_register_routes');

function surfside_tools_roku_messages_register_routes() {
    $public = array('methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true');
    register_rest_route('surfside/v1', '/roku/messages', array_merge($public, array(
        'callback' => 'surfside_tools_roku_messages_list',
    )));
    register_rest_route('surfside/v1', '/roku/series', array_merge($public, array(
        'callback' => 'surfside_tools_roku_messages_series',
    )));
    register_rest_route('surfside/v1', '/roku/messages/(?P<video_id>[A-Za-z0-9_-]{11})',
        array_merge($public, array('callback' => 'surfside_tools_roku_messages_detail')));
}

/** Only a vetted HTTPS base may be returned to unauthenticated Roku clients. */
function surfside_tools_roku_public_base() {
    $cfg = surfside_tools_r2_config();
    $base = rtrim((string) ($cfg['public_base'] ?? ''), '/');
    if ($base === '' || !preg_match('~^https://[a-z0-9.-]+(?:/[\w./-]*)?$~iD', $base)) return '';
    $host = (string) parse_url($base, PHP_URL_HOST);
    if ($host === '' || !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) return '';
    return $base;
}

function surfside_tools_roku_playback_url($id) {
    $base = surfside_tools_roku_public_base();
    if ($base === '' || !surfside_tools_r2_video_id($id)) return '';
    // The caller has already proven "ready", canonical object_key, master
    // membership and staff visibility via the ready-only SQL query.
    return esc_url_raw($base . '/' . surfside_tools_r2_sermon_key($id), array('https'));
}

/** Don't attempt to query an absent R2 status table on fresh installations. */
function surfside_tools_roku_catalog_ready() {
    return surfside_tools_roku_public_base() !== ''
        && (int) get_option(SURFSIDE_R2_SERMON_SCHEMA_OPTION, 0) >= SURFSIDE_R2_SERMON_SCHEMA_VERSION
        && surfside_tools_public_messages_master_id() !== '';
}

function surfside_tools_roku_serialize_video($row) {
    $video = surfside_tools_mobile_messages_serialize_video($row);
    if (!is_array($video)) return null;
    $url = surfside_tools_roku_playback_url($video['id']);
    if ($url === '') return null;
    $video['playback'] = array('url' => $url, 'format' => 'mp4', 'content_type' => 'video/mp4');
    return $video;
}

/** GET /wp-json/surfside/v1/roku/messages?search=&year=&series=&page= */
function surfside_tools_roku_messages_list(WP_REST_Request $request) {
    // Reuse mobile's strict validation and public archive filter semantics.
    $filters = surfside_tools_mobile_messages_filters($request);
    if (is_wp_error($filters)) return $filters;
    if (!surfside_tools_roku_catalog_ready()) {
        return rest_ensure_response(array(
            'api_version' => 1, 'available' => false, 'latest' => null,
            'items' => array(), 'pagination' => array(
                'page' => 1, 'page_size' => SURFSIDE_PUBLIC_MESSAGES_PER_PAGE,
                'total' => 0, 'total_pages' => 1, 'has_more' => false,
            ),
        ));
    }
    $master = surfside_tools_public_messages_master_id();
    if ($filters['series'] !== '') {
        $allowed = array_column(surfside_tools_public_messages_series($master, true), 'playlist_id');
        if (!in_array($filters['series'], $allowed, true)) {
            return new WP_Error('surfside_roku_series_unavailable',
                'This sermon series is not available on Roku.', array('status' => 404));
        }
    }
    // Filtering occurs IN SQL, before pagination; a page never fills with
    // unplayable YouTube-only messages and series counts stay consistent.
    $list = surfside_tools_public_messages_list($master, $filters, true);
    $items = array();
    foreach ($list['rows'] as $row) {
        $item = surfside_tools_roku_serialize_video($row);
        if ($item !== null) $items[] = $item;
    }
    $home = $filters['q'] === '' && $filters['year'] === ''
        && $filters['series'] === '' && (int) $list['page'] === 1;
    return rest_ensure_response(array(
        'api_version' => 1, 'available' => true,
        'latest' => $home && $items ? $items[0] : null,
        'items' => $items,
        'pagination' => array(
            'page' => (int) $list['page'],
            'page_size' => SURFSIDE_PUBLIC_MESSAGES_PER_PAGE,
            'total' => (int) $list['total'],
            'total_pages' => (int) $list['pages'],
            'has_more' => (int) $list['page'] < (int) $list['pages'],
        ),
    ));
}

/** GET /wp-json/surfside/v1/roku/series */
function surfside_tools_roku_messages_series(WP_REST_Request $request) {
    if (!surfside_tools_roku_catalog_ready()) {
        return rest_ensure_response(array('api_version' => 1, 'available' => false, 'series' => array()));
    }
    $master = surfside_tools_public_messages_master_id();
    $rows = surfside_tools_public_messages_series($master, true);
    $series = array();
    foreach ($rows as $row) $series[] = surfside_tools_mobile_messages_serialize_series($row);
    return rest_ensure_response(array('api_version' => 1, 'available' => true, 'series' => $series));
}

/** GET /wp-json/surfside/v1/roku/messages/<youtube-id> */
function surfside_tools_roku_messages_detail(WP_REST_Request $request) {
    $id = $request->get_param('video_id');
    if (!surfside_tools_r2_video_id($id)) {
        return new WP_Error('surfside_roku_invalid_video', 'Invalid YouTube video ID.', array('status' => 400));
    }
    if (!surfside_tools_roku_catalog_ready()) {
        return new WP_Error('surfside_roku_unavailable', 'The recording is not available on Roku.',
            array('status' => 404));
    }
    $master = surfside_tools_public_messages_master_id();
    $row = surfside_tools_public_messages_detail($master, $id, true);
    if (!$row) {
        // Missing R2, staff-excluded and non-master IDs all return 404.
        return new WP_Error('surfside_roku_unavailable', 'The recording is not available on Roku.',
            array('status' => 404));
    }
    $video = surfside_tools_roku_serialize_video($row);
    if (!$video) {
        return new WP_Error('surfside_roku_unavailable', 'The recording is not available on Roku.',
            array('status' => 404));
    }
    $allowed = surfside_tools_public_messages_series($master, true);
    $memberships = surfside_tools_public_messages_video_series($master, $id, $allowed);
    $series = array();
    foreach ($memberships as $member) {
        $series[] = array('id' => (string) $member['playlist_id'],
            'title' => (string) $member['title']);
    }
    return rest_ensure_response(array('api_version' => 1, 'message' => $video, 'series' => $series));
}
