<?php
/** Standalone checks for the shared offline announcement playlist. */
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
$GLOBALS['test_options'] = array();
$GLOBALS['test_transients'] = array();
$GLOBALS['test_allowed'] = true;
$GLOBALS['test_nonce'] = true;
$GLOBALS['test_images'] = array(11, 12, 13);
$GLOBALS['test_media'] = array(11, 12, 13, 14);
function add_filter($name, $callback) { $GLOBALS['test_filter_' . $name] = $callback; }
function add_action($name, $callback, $priority = 10) {}
function get_option($name, $default = false) { return $GLOBALS['test_options'][$name] ?? $default; }
function update_option($name, $value, $autoload = false) { $GLOBALS['test_options'][$name] = $value; return true; }
function get_transient($name) { return $GLOBALS['test_transients'][$name] ?? false; }
function set_transient($name, $value, $ttl) { $GLOBALS['test_transients'][$name] = $value; return true; }
function delete_transient($name) { unset($GLOBALS['test_transients'][$name]); return true; }
function absint($value) { return abs((int) $value); }
function get_post_type($id) { return in_array((int) $id, $GLOBALS['test_media'], true) ? 'attachment' : false; }
function wp_attachment_is_image($id) { return in_array((int) $id, $GLOBALS['test_images'], true); }
function wp_get_attachment_image_url($id, $size) { return wp_attachment_is_image($id) ? 'https://example.org/images/' . $id . '.jpg' : false; }
function esc_url_raw($url) { return $url; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wp_json_encode($value) { return json_encode($value); }
function get_the_title($id) { return 'Slide ' . $id; }
function current_user_can($cap) { return $cap === 'manage_options' && $GLOBALS['test_allowed']; }
function is_user_logged_in() { return true; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return strip_tags((string) $value); }
function wp_verify_nonce($nonce, $action) { return $GLOBALS['test_nonce'] && $nonce === 'valid' && $action === 'surfside_slideshow_save'; }
function wp_nonce_field($action, $name) { echo '<input name="' . $name . '" value="valid">'; }
function surfside_tools_purge_cache() { $GLOBALS['purges'] = ($GLOBALS['purges'] ?? 0) + 1; }
function must($condition, $message) { if (!$condition) throw new RuntimeException($message); }

require dirname(__DIR__) . '/includes/offline-announcement-slideshow.php';
must(isset($GLOBALS['test_filter_surfside_tools_mobile_api_app']), 'API filter is registered');
$empty = surfside_tools_offline_slideshow_public_data();
must($empty['slides'] === array() && $empty['default_duration_seconds'] === 8, 'Empty playlist defaults safely');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
    'surfside_slideshow_action' => 'save',
    'surfside_slideshow_nonce' => 'valid',
    'surfside_slideshow_default_duration' => '9',
    'surfside_slideshow_slides' => json_encode(array(
        array('id' => 12, 'enabled' => true, 'duration_seconds' => 14),
        array('id' => 11, 'enabled' => false, 'duration_seconds' => 0),
        array('id' => 13, 'enabled' => true, 'duration_seconds' => 0),
        array('id' => 12, 'enabled' => true, 'duration_seconds' => 7),
        array('id' => 14, 'enabled' => true, 'duration_seconds' => 7),
        array('id' => 100, 'enabled' => true, 'duration_seconds' => 7),
    )),
);
$legacy = array('announcement_video_id' => 555);
$GLOBALS['test_options']['surfside_tools_site_information'] = $legacy;
$notice = surfside_tools_offline_slideshow_handle_post();
must($notice['type'] === 'success', 'Admin save accepted');
$settings = surfside_tools_offline_slideshow_settings();
must(count($settings['slides']) === 3, 'Only unique existing images are saved');
must($settings['slides'][0]['id'] === 12 && $settings['slides'][1]['id'] === 11 && $settings['slides'][2]['id'] === 13, 'Custom order is retained');
must($settings['default_duration_seconds'] === 9, 'Default duration saved');
must(!$settings['slides'][1]['enabled'], 'Disabled image remains editable');
must($GLOBALS['test_options']['surfside_tools_site_information'] === $legacy, 'Legacy video settings never rewritten');

$api = surfside_tools_offline_slideshow_mobile_api(array('livestream' => array(
    'announcement_video_url' => 'https://example.org/legacy.mp4',
), 'site' => array('name' => 'Surfside')));
must($api['livestream']['announcement_video_url'] === 'https://example.org/legacy.mp4', 'Legacy MP4 contract preserved');
must($api['site']['name'] === 'Surfside', 'Unrelated API data preserved');
$slides = $api['livestream']['announcement_slideshow']['slides'];
must(count($slides) === 2 && $slides[0]['id'] === 12 && $slides[1]['id'] === 13, 'Only active slides published in order');
must($slides[0]['duration_seconds'] === 14 && $slides[1]['duration_seconds'] === 9, 'Per-slide and default durations resolved');
must($slides[0]['image_url'] === 'https://example.org/images/12.jpg', 'URLs derived from Media Library, not client input');
must(!empty($api['livestream']['announcement_slideshow']['updated_at']), 'Playlist revision exposed');
must(count($GLOBALS['test_transients']) === 1, 'Published response is cached');

$manager = surfside_tools_offline_slideshow_manager();
must(strpos($manager, 'data-slideshow-add') !== false && strpos($manager, 'Publish slideshow') !== false, 'Manager exposes upload and publish controls');
must(strpos($manager, 'data-slideshow-preview-button') !== false, 'Manager exposes preview control');

$_POST['surfside_slideshow_slides'] = '{not json';
$bad = surfside_tools_offline_slideshow_handle_post();
must($bad['type'] === 'error' && count(surfside_tools_offline_slideshow_settings()['slides']) === 3, 'Malformed JSON cannot overwrite playlist');

$GLOBALS['test_nonce'] = false;
$_POST['surfside_slideshow_slides'] = '[]';
must(surfside_tools_offline_slideshow_handle_post()['type'] === 'error', 'Invalid nonce blocked');
$GLOBALS['test_nonce'] = true;
$GLOBALS['test_allowed'] = false;
must(surfside_tools_offline_slideshow_handle_post()['type'] === 'error', 'Unauthorized save blocked');
$GLOBALS['test_allowed'] = true;

$notice = surfside_tools_offline_slideshow_handle_post();
must($notice['type'] === 'success', 'Clearing playlist is an explicit valid publish');
must(surfside_tools_offline_slideshow_public_data()['slides'] === array(), 'Empty publish deactivates slideshow');
must($GLOBALS['test_options']['surfside_tools_site_information'] === $legacy, 'Clearing playlist keeps fallback MP4');

echo "PASS: Offline slideshow editor, validation, permissions, API, caching, and MP4 fallback\n";
