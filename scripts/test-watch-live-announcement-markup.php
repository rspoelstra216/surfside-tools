<?php
/** Website Watch Live integration for managed offline announcement images. */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_TOOLS_PATH', dirname(__DIR__) . '/');
define('SURFSIDE_TOOLS_URL', 'https://example.org/wp-content/plugins/surfside-tools/');
define('SURFSIDE_TOOLS_VERSION', '3.3.0');
$GLOBALS['loaded_assets'] = array();
function add_shortcode(...$args) {}
function add_filter(...$args) {}
function shortcode_atts($defaults, $attributes, $name) { return array_merge($defaults, $attributes); }
function wp_enqueue_style($key, $url, $deps, $ver) { $GLOBALS['loaded_assets'][$key] = compact('url','deps','ver'); }
function wp_enqueue_script($key, $url, $deps, $ver, $footer) { $GLOBALS['loaded_assets'][$key] = compact('url','deps','ver'); }
function current_datetime() { return new DateTimeImmutable('2026-10-28T11:00:00-04:00'); }
function surfside_tools_site_information_services() { return array(); }
function surfside_tools_get_site_information() {
    return array('streaming' => array(
        'twitch_channel' => 'surfsidecf',
        'announcement_video_id' => $GLOBALS['video_id'],
        'youtube_url' => '',
        'facebook_url' => ''
    ));
}
function surfside_tools_offline_slideshow_public_data() {
    return array('slides' => $GLOBALS['published_slides']);
}
function surfside_tools_site_information_url($url) { return (string) $url; }
function wp_get_attachment_url($id) { return 'https://example.org/uploads/offline.mp4'; }
function wp_json_encode($value) { return json_encode($value); }
function rest_url($route) { return 'https://example.org/wp-json/' . $route; }
function is_page($slug) { return $slug === 'watch-live'; }
function sanitize_key($value) { return strtolower((string) $value); }
function absint($v) { return abs((int) $v); }
function wp_unique_id($prefix) { static $i=0; return $prefix . ++$i; }
function esc_attr($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function must($ok, $message) { if (!$ok) throw new RuntimeException($message); }

$GLOBALS['video_id'] = 123;
$GLOBALS['published_slides'] = array(
    array('id' => 5, 'image_url' => 'https://example.org/announcements/one.jpg', 'duration_seconds' => 8),
    array('id' => 9, 'image_url' => 'https://example.org/announcements/two.jpg', 'duration_seconds' => 12),
);
require dirname(__DIR__) . '/includes/watch-live-stream.php';
$html = surfside_tools_watch_live_shortcode();
must(strpos($html, 'data-announcement-endpoint="https://example.org/wp-json/surfside/v1/announcement-slideshow"') !== false,
    'Website uses dedicated published playlist refresh endpoint');
must(strpos($html, 'data-announcement-slides=') !== false && strpos($html, 'one.jpg') !== false,
    'Published playlist is embedded for immediate first-frame rendering');
must(strpos($html, 'data-announcement-slideshow hidden') !== false
    && strpos($html, 'data-announcement-image') !== false,
    'Slideshow never becomes visible until offline status has been verified');
must(strpos($html, 'https://example.org/uploads/offline.mp4') !== false
    && strpos($html, 'data-announcement-fallback-message') !== false,
    'Legacy MP4 and plain offline message retained');
must(strpos($html, 'data-stream-state="live"') !== false
    && strpos($html, 'data-stream-state="replay"') !== false
    && strpos($html, 'data-stream-state="offline"') !== false,
    'Existing live/replay/offline stage structure retained');
must(strpos($html, 'data-announcement-badge hidden') !== false,
    'Announcement label is only shown while announcing');
must(strpos($html, 'autoplay') === false, 'Hidden MP4 never autoplays before state check');
must(isset($GLOBALS['loaded_assets']['surfside-tools-watch-live-announcements']),
    'Slideshow script loaded with Watch Live shortcode');
must(in_array('surfside-tools-watch-live-announcements', $GLOBALS['loaded_assets']['surfside-tools-watch-live']['deps'], true),
    'Livestream state script depends on announcement controller');

$GLOBALS['video_id'] = 0;
$GLOBALS['published_slides'] = array();
$empty = surfside_tools_watch_live_shortcode();
must(strpos($empty, 'data-announcement-slideshow hidden') !== false, 'Future slides supported when initially empty');
must(strpos($empty, 'data-announcement-fallback-message') !== false
    && strpos($empty, 'We are offline right now') !== false,
    'No slides and no MP4 retains the offline message');
echo "PASS: Website Watch Live slideshow markup, state visibility, legacy fallback and asset dependencies\n";
