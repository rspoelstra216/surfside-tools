<?php
/**
 * Regression test for the homepage Latest Sermon shortcode.
 * Run: php scripts/test-home-latest-sermon.php
 */
define('ABSPATH', __DIR__ . '/');
define('OBJECT', 'OBJECT');
define('SURFSIDE_YT_STATE_OPTION', 'surfside_tools_youtube_sync_state');

$GLOBALS['test_hooks'] = array();
$GLOBALS['test_shortcodes'] = array();
$GLOBALS['test_latest_calls'] = 0;
$GLOBALS['test_purges'] = 0;
$GLOBALS['test_master'] = 'PLMESSAGES1234';
$GLOBALS['test_published'] = true;
$GLOBALS['test_has_video'] = true;
$GLOBALS['test_api_calls'] = 0;
$GLOBALS['test_video'] = array(
    'video_id' => 'abcdEF12_34',
    'title' => 'Discovering the Way – Made for More – Week 2 – 20 September 2026',
    'thumbnail_url' => 'https://i.ytimg.com/vi/abcdEF12_34/hqdefault.jpg',
    'message_date' => '2026-09-20',
    'date_source' => 'sermon',
);

class WP_Post {
    public $post_status;
    public function __construct($status) { $this->post_status = $status; }
}
function add_action($hook, $callback, $priority = 10, $accepted = 1) {
    $GLOBALS['test_hooks'][$hook] = array('callback' => $callback, 'priority' => $priority, 'accepted' => $accepted);
}
function add_shortcode($tag, $callback) { $GLOBALS['test_shortcodes'][$tag] = $callback; }
function get_page_by_path($path, $output, $type) {
    if ($path !== 'messages' || $type !== 'page') throw new RuntimeException('Unexpected page lookup');
    return $GLOBALS['test_published'] ? new WP_Post('publish') : null;
}
function get_permalink($post) { return 'https://example.org/messages/'; }
function home_url($path = '') { return 'https://example.org' . $path; }
function add_query_arg($args, $url) { return $url.'?'.http_build_query($args, '', '&', PHP_QUERY_RFC3986); }
function esc_url($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function surfside_tools_public_messages_master_id() { return $GLOBALS['test_master']; }
function surfside_tools_public_messages_latest($master) {
    $GLOBALS['test_latest_calls']++;
    if ($master !== 'PLMESSAGES1234') throw new RuntimeException('Wrong master playlist');
    return $GLOBALS['test_has_video'] ? $GLOBALS['test_video'] : null;
}
function surfside_tools_public_messages_featured_heading($title) {
    if (str_contains($title, ' – Made for More – Week 2 – ')) {
        return array('heading' => 'Discovering the Way', 'context' => 'Made for More · Week 2');
    }
    return array('heading' => $title, 'context' => '');
}
function surfside_tools_public_messages_date_label($date, $source = 'sermon') {
    return $source === 'sermon' ? 'September 20, 2026' : 'Published on YouTube: September 20, 2026';
}
function surfside_tools_purge_homepage_carousel_cache() { $GLOBALS['test_purges']++; }

require dirname(__DIR__) . '/includes/home-latest-sermon.php';

function check($passed, $reason) {
    if (!$passed) throw new RuntimeException($reason);
}
check($GLOBALS['test_latest_calls'] === 0 && $GLOBALS['test_purges'] === 0,
    'Module inclusion has no archive reads or cache purges');
check(($GLOBALS['test_shortcodes']['surfside_home_latest_sermon'] ?? '') === 'surfside_tools_home_latest_sermon_shortcode',
    'Latest Sermon shortcode registered');
check(($GLOBALS['test_hooks']['update_option_surfside_tools_youtube_sync_state']['callback'] ?? '')
    === 'surfside_tools_home_latest_sermon_on_sync', 'Sync completion listener registered');
check(($GLOBALS['test_hooks']['update_option_surfside_tools_youtube_sync_state']['accepted'] ?? 0) === 3,
    'Sync handler receives before/after state and option name');

$html = surfside_tools_home_latest_sermon_shortcode();
check($GLOBALS['test_latest_calls'] === 1, 'Home preview reuses exactly one local latest-sermon query');
check(str_contains($html, 'Watch a Recent Sermon')
    && str_contains($html, 'Discovering the Way')
    && str_contains($html, 'Made for More · Week 2')
    && str_contains($html, 'September 20, 2026'),
    'Latest sermon heading, series and sermon date displayed');
check(str_contains($html, 'sm_video=abcdEF12_34'), 'Watch Sermon uses internal public Message detail');
check(str_contains($html, 'href="https://example.org/messages/"'),
    'Browse All Messages links to existing published archive page');
check(str_contains($html, 'href="https://example.org/watch-live/"'),
    'Full-service replay remains discoverable through Watch Live');
check(str_contains($html, 'https://i.ytimg.com/vi/abcdEF12_34/hqdefault.jpg'),
    'Existing YouTube thumbnail used without hosting or uploading any image');
check(str_contains($html, 'object-fit:contain') && str_contains($html, 'aspect-ratio:16/9'),
    'Preview image keeps original 16:9 composition');
check(str_contains($html, '@media(max-width:780px)') && str_contains($html, '@media(max-width:480px)'),
    'Preview includes tablet and mobile layout rules');
check(str_contains($html, 'width:100vw')
    && str_contains($html, 'max-width:none!important')
    && str_contains($html, 'margin-left:calc(50% - 50vw)!important')
    && str_contains($html, 'margin-right:calc(50% - 50vw)!important'),
    'Shortcode section escapes constrained WordPress Group and content width');
check(str_contains($html, 'background:#fff;font-family:inherit'),
    'Section background is white rather than tinted gray');
check(str_contains($html, '.surfside-home-sermon__inner{width:100%;max-width:1160px;margin:0 auto}'),
    'Full-width section retains centered, readable inner content');
check(str_contains($html, '.surfside-home-sermon__feature{display:grid;grid-template-columns:')
    && str_contains($html, 'background:#fff;border:1px solid var(--shs-border)'),
    'Existing responsive video preview and neutral card are preserved');

check(!str_contains($html, '<iframe') && !str_contains($html, '<video') &&
    !str_contains($html, 'autoplay') && !str_contains($html, 'manifest/video'),
    'Home preview never embeds or streams the video');
check($GLOBALS['test_api_calls'] === 0,
    'Homepage visitor rendering never uses external service APIs');

$GLOBALS['test_video']['title'] = '<script>alert("x")</script>';
$GLOBALS['test_video']['thumbnail_url'] = 'https://i.ytimg.com/vi/abcdEF12_34/hqdefault.jpg';
$html = surfside_tools_home_latest_sermon_shortcode();
check(!str_contains($html, '<script>alert') && str_contains($html, '&lt;script&gt;'),
    'Video title safely escaped in heading and accessible labels');
$GLOBALS['test_video']['title'] = 'Discovering the Way – Made for More – Week 2 – 20 September 2026';

$GLOBALS['test_video']['video_id'] = '../invalid';
$html = surfside_tools_home_latest_sermon_shortcode();
check(!str_contains($html, 'sm_video') && str_contains($html, 'Browse All Messages'),
    'Malformed video ID falls back to archive without generating invalid detail link');
$GLOBALS['test_video']['video_id'] = 'abcdEF12_34';

$GLOBALS['test_has_video'] = false;
$html = surfside_tools_home_latest_sermon_shortcode();
check(str_contains($html, 'Our messages are available to browse')
    && str_contains($html, 'Browse All Messages') && !str_contains($html, 'sm_video='),
    'Missing archive data degrades gracefully');
$GLOBALS['test_has_video'] = true;

$GLOBALS['test_master'] = '';
$before = $GLOBALS['test_latest_calls'];
$html = surfside_tools_home_latest_sermon_shortcode();
check($GLOBALS['test_latest_calls'] === $before && !str_contains($html, 'sm_video='),
    'No archive ready: no unnecessary latest-video query');
$GLOBALS['test_master'] = 'PLMESSAGES1234';

$GLOBALS['test_published'] = false;
check(surfside_tools_home_latest_sermon_archive_url() === 'https://example.org/messages/',
    'Fallback archive URL works if the WordPress page cannot be resolved');
$GLOBALS['test_published'] = true;

surfside_tools_home_latest_sermon_on_sync(
    array('status'=>'running','last_success'=>100),
    array('status'=>'running','last_success'=>100),
    SURFSIDE_YT_STATE_OPTION
);
check($GLOBALS['test_purges'] === 0, 'No homepage purge during import batches');
surfside_tools_home_latest_sermon_on_sync(
    array('status'=>'running','last_success'=>100),
    array('status'=>'error','last_success'=>100),
    SURFSIDE_YT_STATE_OPTION
);
check($GLOBALS['test_purges'] === 0, 'Failed sync cannot purge');
surfside_tools_home_latest_sermon_on_sync(
    array('status'=>'running','last_success'=>100),
    array('status'=>'complete','last_success'=>200),
    SURFSIDE_YT_STATE_OPTION
);
check($GLOBALS['test_purges'] === 1, 'New completed import purges stale homepage cache');
surfside_tools_home_latest_sermon_on_sync(
    array('status'=>'complete','last_success'=>200),
    array('status'=>'complete','last_success'=>200),
    SURFSIDE_YT_STATE_OPTION
);
check($GLOBALS['test_purges'] === 1, 'Repeated same completion does not purge again');
surfside_tools_home_latest_sermon_on_sync(
    array('status'=>'complete','last_success'=>200),
    array('status'=>'complete','last_success'=>300),
    SURFSIDE_YT_STATE_OPTION
);
check($GLOBALS['test_purges'] === 2, 'A subsequent actual completed sync purges again');

echo "Homepage Latest Sermon shortcode and cache invalidation checks passed.\n";
