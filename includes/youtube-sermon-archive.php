<?php
/**
 * YouTube message metadata importer.
 *
 * Nothing here runs on visitor page views. Staff explicitly start the first
 * import; subsequent scans run in bounded WP-Cron batches. Videos and images
 * remain on YouTube; only small metadata and playlist relations live locally.
 */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_YT_SCHEMA_VERSION = 1;
const SURFSIDE_YT_SCHEMA_OPTION = 'surfside_tools_youtube_schema';
const SURFSIDE_YT_CONFIG_OPTION = 'surfside_tools_youtube_config';
const SURFSIDE_YT_STATE_OPTION = 'surfside_tools_youtube_sync_state';
const SURFSIDE_YT_TICK = 'surfside_tools_youtube_sync_tick';

function surfside_tools_youtube_tables() {
    global $wpdb;
    return array(
        'videos' => $wpdb->prefix . 'surfside_yt_videos',
        'playlists' => $wpdb->prefix . 'surfside_yt_playlists',
        'members' => $wpdb->prefix . 'surfside_yt_members',
    );
}

function surfside_tools_youtube_config() {
    $stored = get_option(SURFSIDE_YT_CONFIG_OPTION, array());
    return wp_parse_args(is_array($stored) ? $stored : array(), array(
        'channel_ref' => '',
        'master_playlist_id' => '',
    ));
}

function surfside_tools_youtube_api_key() {
    if (defined('SURFSIDE_YOUTUBE_API_KEY')) {
        return trim((string) SURFSIDE_YOUTUBE_API_KEY);
    }
    return trim((string) get_option('surfside_tools_youtube_api_key', ''));
}

function surfside_tools_youtube_channel_identifier($raw) {
    $value = trim((string) $raw);
    if (preg_match('~^https?://(?:www\.)?youtube\.com/@([a-zA-Z0-9._-]+)/?(?:playlists)?/?$~i', $value, $m)) {
        $value = '@' . $m[1];
    } elseif (preg_match('~^https?://(?:www\.)?youtube\.com/channel/(UC[a-zA-Z0-9_-]{22})/?$~i', $value, $m)) {
        $value = $m[1];
    }
    if (preg_match('/^UC[a-zA-Z0-9_-]{22}$/', $value)) return $value;
    if (preg_match('/^@[A-Za-z0-9._-]{3,30}$/', $value)) return $value;
    return '';
}

function surfside_tools_youtube_valid_playlist_id($id) {
    return is_string($id) && (bool) preg_match('/^[A-Za-z0-9_-]{10,100}$/', $id);
}

/** Allow either a playlist ID or the link copied from YouTube. */
function surfside_tools_youtube_playlist_identifier($input) {
    $input = trim((string) $input);
    if ($input === '') return '';
    if (surfside_tools_youtube_valid_playlist_id($input)) return $input;
    $parts = wp_parse_url($input);
    if (!is_array($parts) || !in_array(strtolower((string) ($parts['host'] ?? '')), array('youtube.com', 'www.youtube.com', 'm.youtube.com'), true)) return '';
    parse_str((string) ($parts['query'] ?? ''), $query);
    $id = $query['list'] ?? '';
    return surfside_tools_youtube_valid_playlist_id($id) ? $id : '';
}

/** Read-only, server-to-server YouTube Data API client. No API key is exposed. */
function surfside_tools_youtube_request($resource, $parameters) {
    $key = surfside_tools_youtube_api_key();
    if ($key === '') return new WP_Error('surfside_youtube_missing_key', 'Save a YouTube Data API key first.');
    if (!in_array($resource, array('channels', 'playlists', 'playlistItems'), true)) {
        return new WP_Error('surfside_youtube_invalid_resource', 'Unsupported YouTube request.');
    }

    $url = add_query_arg(array_merge($parameters, array('key' => $key)),
        'https://www.googleapis.com/youtube/v3/' . $resource);
    $response = wp_remote_get($url, array('timeout' => 12, 'redirection' => 0, 'reject_unsafe_urls' => true));
    if (is_wp_error($response)) {
        return new WP_Error('surfside_youtube_network', 'YouTube could not be reached. Please retry the connection.');
    }
    $status = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($status !== 200 || !is_array($body)) {
        $upstream = is_array($body) ? (string) ($body['error']['message'] ?? '') : '';
        // Upstream messages can contain identifiers; never expose the saved key.
        $upstream = str_replace($key, '[redacted]', sanitize_text_field($upstream));
        $message = 'YouTube API returned HTTP ' . absint($status) . '.';
        if ($status === 403 && stripos($upstream, 'quota') !== false) {
            $message .= ' API quota exceeded; retry after the quota resets.';
        } elseif ($status === 403) {
            $message .= ' Check API permissions and key restrictions.';
        } elseif ($status === 404) {
            $message .= ' The configured channel or playlist was not found.';
        }
        return new WP_Error('surfside_youtube_http', $message);
    }
    return $body;
}

function surfside_tools_youtube_resolve_channel($reference) {
    $reference = surfside_tools_youtube_channel_identifier($reference);
    if ($reference === '') return new WP_Error('surfside_youtube_channel_invalid', 'Enter a YouTube @handle or channel ID.');
    $filter = substr($reference, 0, 1) === '@' ? 'forHandle' : 'id';
    $result = surfside_tools_youtube_request('channels', array(
        'part' => 'snippet',
        $filter => $reference,
        'maxResults' => 1,
    ));
    if (is_wp_error($result)) return $result;
    $channel = $result['items'][0] ?? null;
    if (!is_array($channel) || !preg_match('/^UC[A-Za-z0-9_-]{22}$/', (string) ($channel['id'] ?? ''))) {
        return new WP_Error('surfside_youtube_channel_missing', 'The public YouTube channel could not be found.');
    }
    return array(
        'id' => (string) $channel['id'],
        'title' => sanitize_text_field($channel['snippet']['title'] ?? ''),
    );
}

/**
 * Sermon date comes from the title: "04 October 26" => 2026-10-04.
 * Never substitute YouTube upload time for sermon time.
 */
function surfside_tools_youtube_sermon_date($title) {
    if (!preg_match('/\b([0-3]?\d)\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{2}|\d{4})\b/i', (string) $title, $m)) {
        return '';
    }
    $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
    $date = DateTimeImmutable::createFromFormat('!j F Y', $m[1] . ' ' . ucfirst(strtolower($m[2])) . ' ' . $year);
    return $date && (int) $date->format('j') === (int) $m[1] ? $date->format('Y-m-d') : '';
}

function surfside_tools_youtube_install_schema() {
    if ((int) get_option(SURFSIDE_YT_SCHEMA_OPTION, 0) >= SURFSIDE_YT_SCHEMA_VERSION) return true;
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $collation = $wpdb->get_charset_collate();
    if (!function_exists('dbDelta')) require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE {$t['videos']} (
        video_id varchar(32) NOT NULL,
        title text NOT NULL,
        sermon_date date DEFAULT NULL,
        thumbnail_url text NOT NULL,
        uploaded_at varchar(40) NOT NULL DEFAULT '',
        updated_at datetime NOT NULL,
        PRIMARY KEY  (video_id),
        KEY sermon_date (sermon_date)
    ) {$collation};");
    dbDelta("CREATE TABLE {$t['playlists']} (
        playlist_id varchar(100) NOT NULL,
        title varchar(255) NOT NULL,
        category varchar(20) NOT NULL DEFAULT 'other',
        seen_run varchar(40) NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (playlist_id),
        KEY category (category)
    ) {$collation};");
    dbDelta("CREATE TABLE {$t['members']} (
        playlist_id varchar(100) NOT NULL,
        video_id varchar(32) NOT NULL,
        seen_run varchar(40) NOT NULL,
        PRIMARY KEY  (playlist_id,video_id),
        KEY video_id (video_id)
    ) {$collation};");
    foreach ($t as $table) {
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($found !== $table) {
            return new WP_Error('surfside_youtube_schema', 'YouTube metadata tables could not be initialized.');
        }
    }
    update_option(SURFSIDE_YT_SCHEMA_OPTION, SURFSIDE_YT_SCHEMA_VERSION, false);
    return true;
}

function surfside_tools_youtube_state() {
    $state = get_option(SURFSIDE_YT_STATE_OPTION, array());
    return is_array($state) ? $state : array();
}

function surfside_tools_youtube_queue_tick($delay = 30) {
    if (!wp_next_scheduled(SURFSIDE_YT_TICK)) {
        wp_schedule_single_event(time() + max(20, (int) $delay), SURFSIDE_YT_TICK);
    }
}

/** A new scan is requested only by a staff action or the scheduled worker. */
function surfside_tools_youtube_start_sync() {
    $config = surfside_tools_youtube_config();
    if (surfside_tools_youtube_api_key() === '' || surfside_tools_youtube_channel_identifier($config['channel_ref']) === '') {
        return new WP_Error('surfside_youtube_not_configured', 'Save a valid channel and YouTube API key before synchronizing.');
    }
    $ready = surfside_tools_youtube_install_schema();
    if (is_wp_error($ready)) return $ready;

    $state = array(
        'status' => 'running',
        'phase' => 'discover',
        'run_id' => wp_generate_uuid4(),
        'page_token' => '',
        'series_offset' => 0,
        'master_id' => '',
        'channel_id' => '',
        'requests' => 0,
        'started_at' => time(),
        'last_success' => (int) (surfside_tools_youtube_state()['last_success'] ?? 0),
        'message' => 'Discovering public playlists.',
    );
    update_option(SURFSIDE_YT_STATE_OPTION, $state, false);
    wp_clear_scheduled_hook(SURFSIDE_YT_TICK);
    surfside_tools_youtube_queue_tick(20);
    return true;
}

/** Only videos known to be in the master Messages playlist qualify as sermons. */
function surfside_tools_youtube_master_video_ids($ids, $master_id) {
    if (!$ids) return array();
    global $wpdb;
    $table = surfside_tools_youtube_tables()['members'];
    $sql = "SELECT video_id FROM {$table} WHERE playlist_id = %s AND video_id IN (" .
        implode(',', array_fill(0, count($ids), '%s')) . ')';
    $rows = $wpdb->get_col($wpdb->prepare($sql, array_merge(array($master_id), $ids)));
    return is_array($rows) ? $rows : array();
}

function surfside_tools_youtube_import_items($response, $playlist_id, $is_master, $state) {
    global $wpdb;
    $t = surfside_tools_youtube_tables();
    $items = is_array($response['items'] ?? null) ? $response['items'] : array();
    $videos = array();
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $id = (string) ($item['snippet']['resourceId']['videoId'] ?? $item['contentDetails']['videoId'] ?? '');
        $title = sanitize_text_field($item['snippet']['title'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $id) || in_array(strtolower($title), array('private video', 'deleted video'), true)) continue;
        $videos[$id] = $item;
    }
    $ids = array_keys($videos);
    if (!$is_master) {
        $allowed = surfside_tools_youtube_master_video_ids($ids, $state['master_id']);
        $videos = array_intersect_key($videos, array_flip($allowed));
    }

    $now = current_time('mysql', true);
    foreach ($videos as $id => $item) {
        if ($is_master) {
            $snippet = $item['snippet'] ?? array();
            $thumb = (string) ($snippet['thumbnails']['medium']['url'] ?? $snippet['thumbnails']['default']['url'] ?? '');
            $date = surfside_tools_youtube_sermon_date($snippet['title'] ?? '');
            $written = $wpdb->replace($t['videos'], array(
                'video_id' => $id,
                'title' => sanitize_text_field($snippet['title'] ?? ''),
                'sermon_date' => $date !== '' ? $date : null,
                'thumbnail_url' => esc_url_raw($thumb, array('https')),
                'uploaded_at' => sanitize_text_field($snippet['publishedAt'] ?? ''),
                'updated_at' => $now,
            ));
            if ($written === false) return new WP_Error('surfside_youtube_video_write', 'Could not store YouTube video metadata.');
        }
        $written = $wpdb->replace($t['members'], array(
            'playlist_id' => $playlist_id,
            'video_id' => $id,
            'seen_run' => $state['run_id'],
        ));
        if ($written === false) return new WP_Error('surfside_youtube_membership_write', 'Could not store YouTube playlist membership.');
    }
    return true;
}

function surfside_tools_youtube_process_page(&$state) {
    global $wpdb;
    $tables = surfside_tools_youtube_tables();
    $config = surfside_tools_youtube_config();
    $run_id = $state['run_id'];
    $page_token = (string) ($state['page_token'] ?? '');

    if ($state['phase'] === 'discover') {
        if ($state['channel_id'] === '') {
            $channel = surfside_tools_youtube_resolve_channel($config['channel_ref']);
            if (is_wp_error($channel)) return $channel;
            $state['channel_id'] = $channel['id'];
        }
        $args = array('part' => 'snippet', 'channelId' => $state['channel_id'], 'maxResults' => 50);
        if ($page_token !== '') $args['pageToken'] = $page_token;
        $response = surfside_tools_youtube_request('playlists', $args);
        if (is_wp_error($response)) return $response;
        foreach (($response['items'] ?? array()) as $playlist) {
            $id = (string) ($playlist['id'] ?? '');
            if (!surfside_tools_youtube_valid_playlist_id($id)) continue;
            $row = array(
                'playlist_id' => $id,
                'title' => sanitize_text_field($playlist['snippet']['title'] ?? ''),
                'category' => 'other',
                'seen_run' => $run_id,
                'updated_at' => current_time('mysql', true),
            );
            if ($wpdb->replace($tables['playlists'], $row) === false) {
                return new WP_Error('surfside_youtube_playlist_write', 'Could not store YouTube playlist metadata.');
            }
        }
        $state['page_token'] = (string) ($response['nextPageToken'] ?? '');
        if ($state['page_token'] === '') {
            $master = trim((string) $config['master_playlist_id']);
            if ($master === '') {
                $ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT playlist_id FROM {$tables['playlists']} WHERE seen_run = %s AND LOWER(title) = %s LIMIT 2",
                    $run_id, 'messages'
                ));
                if (count((array) $ids) !== 1) {
                    return new WP_Error('surfside_youtube_master_missing', 'Select the master Messages playlist ID in Integrations (none or multiple exact matches were found).');
                }
                $master = $ids[0];
            }
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT playlist_id FROM {$tables['playlists']} WHERE playlist_id = %s AND seen_run = %s",
                $master, $run_id
            ));
            if ($exists !== $master) {
                return new WP_Error('surfside_youtube_master_not_owned', 'The Messages playlist was not found among this channel’s public playlists.');
            }
            $state['master_id'] = $master;
            $state['phase'] = 'master';
            $state['page_token'] = '';
            $state['message'] = 'Importing the master Messages playlist.';
        }
        return true;
    }

    if ($state['phase'] === 'master' || $state['phase'] === 'series') {
        $master = $state['phase'] === 'master';
        $playlist_id = $master ? $state['master_id'] : $wpdb->get_var($wpdb->prepare(
            "SELECT playlist_id FROM {$tables['playlists']} WHERE seen_run = %s AND playlist_id != %s ORDER BY playlist_id LIMIT 1 OFFSET %d",
            $run_id, $state['master_id'], (int) $state['series_offset']
        ));
        if (!$playlist_id) {
            $state['phase'] = 'complete';
            $state['status'] = 'complete';
            $state['last_success'] = time();
            $state['message'] = 'YouTube metadata synchronized successfully.';
            $state['page_token'] = '';
            return true;
        }
        $args = array('part' => 'snippet,contentDetails', 'playlistId' => $playlist_id, 'maxResults' => 50);
        if ($page_token !== '') $args['pageToken'] = $page_token;
        $response = surfside_tools_youtube_request('playlistItems', $args);
        if (is_wp_error($response)) return $response;
        $stored = surfside_tools_youtube_import_items($response, $playlist_id, $master, $state);
        if (is_wp_error($stored)) return $stored;
        $state['page_token'] = (string) ($response['nextPageToken'] ?? '');

        if ($state['page_token'] === '') {
            // Prune only after a complete playlist scan; partial scans never
            // delete old memberships when an upstream page fails.
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$tables['members']} WHERE playlist_id = %s AND seen_run != %s",
                $playlist_id, $run_id
            ));
            if ($deleted === false) return new WP_Error('surfside_youtube_membership_prune', 'Could not finalize playlist membership.');
            $category = $master ? 'master' : 'other';
            if (!$master) {
                $count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['members']} WHERE playlist_id = %s",
                    $playlist_id
                ));
                if ($count > 0) $category = 'series';
            }
            if ($wpdb->update($tables['playlists'], array('category' => $category), array('playlist_id' => $playlist_id)) === false) {
                return new WP_Error('surfside_youtube_playlist_finalize', 'Could not finalize the YouTube playlist classification.');
            }
            if ($master) {
                $state['phase'] = 'series';
                $state['message'] = 'Matching public series playlists to Messages.';
            } else {
                $state['series_offset']++;
            }
        }
        return true;
    }
    return new WP_Error('surfside_youtube_state', 'Unknown YouTube synchronization phase.');
}

/** Each cron invocation does at most two upstream playlist page requests. */
function surfside_tools_youtube_sync_tick() {
    $state = surfside_tools_youtube_state();
    if (($state['status'] ?? '') !== 'running') {
        // After a completed daily scan, the scheduled tick begins the next scan.
        surfside_tools_youtube_start_sync();
        return;
    }
    // WP-Cron is normally single-runner; this lock also prevents overlapping
    // workers if visitors and an external cron trigger at the same time.
    $lock_key = 'surfside_tools_youtube_sync_lock';
    if (!add_option($lock_key, time(), '', false)) {
        $locked_at = (int) get_option($lock_key, 0);
        if ($locked_at && $locked_at > time() - 120) {
            surfside_tools_youtube_queue_tick(90);
            return;
        }
        delete_option($lock_key);
        if (!add_option($lock_key, time(), '', false)) return;
    }
    try {
        for ($i = 0; $i < 2; $i++) {
            $result = surfside_tools_youtube_process_page($state);
            if (is_wp_error($result)) {
                $state['status'] = 'error';
                $state['message'] = $result->get_error_message();
                break;
            }
            $state['requests'] = (int) ($state['requests'] ?? 0) + 1;
            if ($state['status'] !== 'running') break;
        }
        update_option(SURFSIDE_YT_STATE_OPTION, $state, false);
    } finally {
        delete_option($lock_key);
    }

    if ($state['status'] === 'running') surfside_tools_youtube_queue_tick(30);
    elseif ($state['status'] === 'complete') surfside_tools_youtube_queue_tick(DAY_IN_SECONDS);
    elseif ($state['status'] === 'error') surfside_tools_youtube_queue_tick(6 * HOUR_IN_SECONDS);
}
add_action(SURFSIDE_YT_TICK, 'surfside_tools_youtube_sync_tick');
