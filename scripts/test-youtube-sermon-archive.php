<?php
/**
 * Regression coverage for YouTube sermon metadata importer.
 * Run: php scripts/test-youtube-sermon-archive.php
 */
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['options'] = array();
$GLOBALS['actions'] = array();
$GLOBALS['events'] = array();
$GLOBALS['api_calls'] = array();
$GLOBALS['youtube_key'] = 'TEST_KEY_DO_NOT_EXPOSE_1234567890';
$GLOBALS['channel_id'] = 'UC' . str_repeat('c', 22);

class WP_Error {
    private $code;
    private $message;
    public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($name, $callback) { $GLOBALS['actions'][] = $name; }
function get_option($name, $fallback = false) { return array_key_exists($name, $GLOBALS['options']) ? $GLOBALS['options'][$name] : $fallback; }
function update_option($name, $value, $autoload = false) { $GLOBALS['options'][$name] = $value; return true; }
function add_option($name, $value, $deprecated = '', $autoload = false) {
    if (array_key_exists($name, $GLOBALS['options'])) return false;
    $GLOBALS['options'][$name] = $value;
    return true;
}
function delete_option($name) { unset($GLOBALS['options'][$name]); }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function wp_generate_uuid4() { return 'df94a2bd-d7e7-42d1-84e7-faeda84d9975'; }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function absint($v) { return abs((int) $v); }
function esc_url_raw($url, $protocols = array()) { return stripos($url, 'https://') === 0 ? $url : ''; }
function current_time($format, $gmt = false) { return '2026-10-15 20:00:00'; }
function add_query_arg($params, $url) { return $url . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986); }
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return json_encode($response['body']); }
function wp_next_scheduled($hook) { return $GLOBALS['events'][$hook] ?? false; }
function wp_schedule_single_event($time, $hook) { $GLOBALS['events'][$hook] = $time; return true; }
function wp_clear_scheduled_hook($hook) { unset($GLOBALS['events'][$hook]); }
function dbDelta($sql) { $GLOBALS['wpdb']->dbdelta_count++; return array(); }

function wp_remote_get($url, $args) {
    $parsed = parse_url($url);
    parse_str($parsed['query'] ?? '', $q);
    $GLOBALS['api_calls'][] = array('url' => $url, 'q' => $q, 'args' => $args);
    if ($parsed['host'] !== 'www.googleapis.com' || !str_starts_with($parsed['path'], '/youtube/v3/')) {
        throw new RuntimeException('Unexpected upstream host or path');
    }
    $resource = basename($parsed['path']);
    if (($q['key'] ?? '') !== $GLOBALS['youtube_key']) throw new RuntimeException('Wrong key');
    if ($resource === 'channels') {
        return array('code' => 200, 'body' => array('items' => array(array(
            'id' => $GLOBALS['channel_id'],
            'snippet' => array('title' => 'Surfside Messages'),
        ))));
    }
    if ($resource === 'playlists') {
        return array('code' => 200, 'body' => array('items' => array(
            array('id' => 'PLMESSAGES1234', 'snippet' => array('title' => 'Messages')),
            array('id' => 'PLSERIES123456', 'snippet' => array('title' => 'Made for More')),
            array('id' => 'PLMUSIC123456', 'snippet' => array('title' => 'Music')),
        )));
    }
    if ($resource === 'playlistItems') {
        $entry = function($id, $title) {
            return array('snippet' => array('resourceId' => array('videoId' => $id),
                'title' => $title,
                'thumbnails' => array('medium' => array('url' => 'https://i.ytimg.com/' . $id . '.jpg')),
                'publishedAt' => '2026-10-20T00:00:00Z',
            ));
        };
        $items = match ($q['playlistId']) {
            'PLMESSAGES1234' => array($entry('abcdEF12_34', 'Made to Serve 18 October 26'), $entry('abcdEF12_35', 'Sermon with no date')),
            'PLSERIES123456' => array($entry('abcdEF12_34', 'Made to Serve 18 October 26'), $entry('music987654', 'Unrelated music')),
            'PLMUSIC123456' => array($entry('music987654', 'Unrelated music')),
            default => throw new RuntimeException('Wrong playlist selected'),
        };
        return array('code' => 200, 'body' => array('items' => $items));
    }
    throw new RuntimeException('Unexpected resource');
}

class TestYouTubeDB {
    public $prefix = 'wp_';
    public $dbdelta_count = 0;
    public $videos = array();
    public $playlists = array();
    public $members = array();
    public function esc_like($str) { return addcslashes($str, '_%\\'); }
    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
    public function prepare($sql, ...$values) {
        if (count($values) === 1 && is_array($values[0])) $values = $values[0];
        $i = 0;
        return preg_replace_callback('/%[ds]/', function ($m) use ($values, &$i) {
            $v = $values[$i++];
            return $m[0] === '%d' ? (string) (int) $v : "'" . str_replace("'", "''", (string) $v) . "'";
        }, $sql);
    }
    public function replace($table, $data) {
        if (str_contains($table, '_yt_videos')) $this->videos[$data['video_id']] = $data;
        elseif (str_contains($table, '_yt_playlists')) $this->playlists[$data['playlist_id']] = $data;
        elseif (str_contains($table, '_yt_members')) $this->members[$data['playlist_id'] . '/' . $data['video_id']] = $data;
        else throw new RuntimeException('Unexpected replace table');
        return 1;
    }
    public function get_var($sql) {
        if (str_starts_with($sql, 'SHOW TABLES LIKE')) {
            if (preg_match("/LIKE '([^']+)'/", $sql, $m)) return str_replace('\\_', '_', $m[1]);
        }
        if (preg_match("/SELECT playlist_id FROM wp_surfside_yt_playlists WHERE playlist_id = '([^']+)' AND seen_run = '([^']+)'/", $sql, $m)) {
            $v = $this->playlists[$m[1]] ?? null;
            return $v && $v['seen_run'] === $m[2] ? $m[1] : null;
        }
        if (str_contains($sql, 'ORDER BY playlist_id LIMIT 1 OFFSET')) {
            preg_match("/seen_run = '([^']+)' AND playlist_id != '([^']+)'.*OFFSET (\d+)/", $sql, $m);
            $ids = array_keys(array_filter($this->playlists, fn($p) => $p['seen_run'] === $m[1] && $p['playlist_id'] !== $m[2]));
            sort($ids);
            return $ids[(int) $m[3]] ?? null;
        }
        if (str_contains($sql, 'SELECT COUNT(*) FROM wp_surfside_yt_members')) {
            preg_match("/playlist_id = '([^']+)'/", $sql, $m);
            return count(array_filter($this->members, fn($x) => $x['playlist_id'] === $m[1]));
        }
        throw new RuntimeException('Unexpected get_var SQL: ' . $sql);
    }
    public function get_col($sql) {
        if (str_contains($sql, 'LOWER(title)')) {
            return array_keys(array_filter($this->playlists, fn($p) => $p['seen_run'] === $GLOBALS['options'][SURFSIDE_YT_STATE_OPTION]['run_id'] && strtolower($p['title']) === 'messages'));
        }
        if (str_contains($sql, 'SELECT video_id FROM wp_surfside_yt_members')) {
            preg_match("/playlist_id = '([^']+)'/", $sql, $m);
            $ids = array();
            foreach ($this->members as $row) {
                if ($row['playlist_id'] === $m[1] && str_contains($sql, "'" . $row['video_id'] . "'")) $ids[] = $row['video_id'];
            }
            return $ids;
        }
        throw new RuntimeException('Unexpected get_col SQL: ' . $sql);
    }
    public function query($sql) {
        if (str_starts_with($sql, 'DELETE FROM wp_surfside_yt_members')) {
            preg_match("/playlist_id = '([^']+)' AND seen_run != '([^']+)'/", $sql, $m);
            $deleted = 0;
            foreach ($this->members as $key => $row) {
                if ($row['playlist_id'] === $m[1] && $row['seen_run'] !== $m[2]) { unset($this->members[$key]); $deleted++; }
            }
            return $deleted;
        }
        throw new RuntimeException('Unexpected query SQL: ' . $sql);
    }
    public function update($table, $values, $where) {
        if ($table !== 'wp_surfside_yt_playlists') throw new RuntimeException('Unexpected update table');
        $id = $where['playlist_id'];
        $this->playlists[$id] = array_merge($this->playlists[$id], $values);
        return 1;
    }
}
$GLOBALS['wpdb'] = new TestYouTubeDB();

require dirname(__DIR__) . '/includes/youtube-sermon-archive.php';

function check($condition, $reason) { if (!$condition) throw new RuntimeException($reason); }

check($GLOBALS['api_calls'] === array() && $GLOBALS['wpdb']->dbdelta_count === 0,
    'Including module must not query YouTube or create tables');
check(surfside_tools_youtube_channel_identifier('https://www.youtube.com/@addpastor/playlists') === '@addpastor',
    'Public channel handle URL normalization');
check(surfside_tools_youtube_channel_identifier('https://example.com/@other') === '',
    'Unknown website URLs rejected');
check(surfside_tools_youtube_sermon_date('04 October 26 My Message') === '2026-10-04', 'Sermon date is parsed from title');
check(surfside_tools_youtube_sermon_date('31 February 26') === '', 'Impossible dates are rejected');
check(surfside_tools_youtube_sermon_date('No date in title') === '', 'No substitution with upload date');

update_option('surfside_tools_youtube_api_key', $GLOBALS['youtube_key']);
update_option(SURFSIDE_YT_CONFIG_OPTION, array('channel_ref' => '@addpastor', 'master_playlist_id' => ''));
check(surfside_tools_youtube_start_sync() === true, 'Explicit admin action initiates import');
check($GLOBALS['wpdb']->dbdelta_count === 3, 'Three metadata tables created only at sync start');
check(isset($GLOBALS['events'][SURFSIDE_YT_TICK]), 'Background batch event scheduled');

$state = surfside_tools_youtube_state();
for ($i = 0; $i < 12 && ($state['status'] ?? '') === 'running'; $i++) {
    $out = surfside_tools_youtube_process_page($state);
    check(!is_wp_error($out), 'Import page must succeed: ' . (is_wp_error($out) ? $out->get_error_message() : ''));
    update_option(SURFSIDE_YT_STATE_OPTION, $state);
}
check($state['status'] === 'complete', 'Resumable three-stage import finished');
check($state['master_id'] === 'PLMESSAGES1234', 'Exact-title Messages playlist detected');
check(count($GLOBALS['wpdb']->videos) === 2, 'Master imports videos once, unrelated music excluded');
check($GLOBALS['wpdb']->videos['abcdEF12_34']['sermon_date'] === '2026-10-18', 'Recorded sermon date matches title');
check($GLOBALS['wpdb']->videos['abcdEF12_35']['sermon_date'] === null, 'Missing sermon date flagged, never replaced by upload time');
check(count($GLOBALS['wpdb']->members) === 3, 'Two master memberships and one series membership only');
check($GLOBALS['wpdb']->playlists['PLSERIES123456']['category'] === 'series', 'Playlist overlapping Messages marked series');
check($GLOBALS['wpdb']->playlists['PLMUSIC123456']['category'] === 'other', 'Unrelated playlist excluded from series');

$key_exposed = json_encode($GLOBALS['wpdb']->videos) . json_encode($GLOBALS['options'][SURFSIDE_YT_STATE_OPTION]);
check(!str_contains($key_exposed, $GLOBALS['youtube_key']), 'API key never persisted in metadata or state');
check(count($GLOBALS['api_calls']) === 5, 'Bounded API calls: channel, playlists, master, series, other');
check(count(array_filter($GLOBALS['api_calls'], fn($x) => $x['args']['timeout'] === 12 && $x['args']['redirection'] === 0)) === 5,
    'YouTube requests have bounded timeouts and no redirects');

echo "YouTube sermon archive metadata regression checks passed.\n";
