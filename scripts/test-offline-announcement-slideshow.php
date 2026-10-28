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
$GLOBALS['test_next_attachment_id'] = 40;
$GLOBALS['test_upload_calls'] = array();
$GLOBALS['test_deleted_ids'] = array();
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
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_file_name($name) { return basename((string) $name); }
function media_handle_upload($field, $parent_id) {
    $file = $_FILES[$field];
    $GLOBALS['test_upload_calls'][] = $file['name'];
    if (strpos($file['name'], 'failed') !== false) return new WP_Error('upload_failed', 'Simulated upload failure');
    $id = ++$GLOBALS['test_next_attachment_id'];
    $GLOBALS['test_media'][] = $id;
    if (strpos($file['name'], '.pdf') === false) $GLOBALS['test_images'][] = $id;
    return $id;
}
function wp_delete_attachment($id, $force) { $GLOBALS['test_deleted_ids'][] = $id; return true; }
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
must(strpos($manager, 'data-slideshow-add') !== false && strpos($manager, 'Save &amp; publish slideshow') !== false, 'Manager exposes optional media reuse and publishing controls');
must(strpos($manager, 'enctype="multipart/form-data"') !== false
    && strpos($manager, 'name="surfside_slideshow_new_images[]"') !== false
    && strpos($manager, 'data-slideshow-upload') !== false,
    'Primary upload uses Homepage Photos-style multipart form');
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

// Direct multi-image uploads follow the homepage form pattern with per-file errors.
$_FILES['surfside_slideshow_new_images'] = array(
    'name' => array('opening-1.jpg', 'failed-image.jpg', 'not-an-image.pdf', 'opening-2.jpg'),
    'type' => array('image/jpeg', 'image/jpeg', 'application/pdf', 'image/jpeg'),
    'tmp_name' => array('/tmp/a', '/tmp/b', '/tmp/c', '/tmp/d'),
    'size' => array(100, 100, 100, 100),
    'error' => array(UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_OK),
);
$_POST['surfside_slideshow_slides'] = json_encode(array(array(
    'id' => 11, 'enabled' => false, 'duration_seconds' => 14,
)));
$notice = surfside_tools_offline_slideshow_handle_post();
must($notice['type'] === 'error' && strpos($notice['message'], 'failed-image.jpg') !== false
    && strpos($notice['message'], 'not-an-image.pdf') !== false,
    'Partial upload failures produce actionable message');
$saved = surfside_tools_offline_slideshow_settings()['slides'];
must(count($saved) === 3, 'Valid new images appended to existing playlist');
must($saved[0]['id'] === 11 && !$saved[0]['enabled'] && $saved[0]['duration_seconds'] === 14,
    'Existing metadata and ordering preserved after adding images');
must($saved[1]['id'] === 41 && $saved[2]['id'] === 43
    && $saved[1]['duration_seconds'] === 0 && $saved[1]['enabled'],
    'New uploads append enabled with default timing');
must($GLOBALS['test_deleted_ids'] === array(42), 'Nonimage upload attachment cleaned up');
must(count($GLOBALS['test_upload_calls']) === 4, 'Files processed individually through media_handle_upload');
must(!isset($_FILES['surfside_slideshow_single_image']), 'Temporary upload field cleaned up');
must(count(surfside_tools_offline_slideshow_public_data()['slides']) === 2,
    'New images exposed on API excluding disabled images');
must($GLOBALS['test_options']['surfside_tools_site_information'] === $legacy,
    'Direct upload preserves legacy MP4 settings');

$GLOBALS['test_nonce'] = false;
$call_count = count($GLOBALS['test_upload_calls']);
must(surfside_tools_offline_slideshow_handle_post()['type'] === 'error', 'Upload requires valid nonce');
must(count($GLOBALS['test_upload_calls']) === $call_count, 'Invalid nonce never processes file');
$GLOBALS['test_nonce'] = true;

$large_list = array();
for ($i = 100; $i < 150; $i++) {
    $GLOBALS['test_media'][] = $i;
    $GLOBALS['test_images'][] = $i;
    $large_list[] = array('id' => $i, 'enabled' => true, 'duration_seconds' => 0);
}
$_POST['surfside_slideshow_slides'] = json_encode($large_list);
$notice = surfside_tools_offline_slideshow_handle_post();
must($notice['type'] === 'error' && strpos($notice['message'], 'full') !== false,
    'Additional upload is rejected when playlist is full');
must(count(surfside_tools_offline_slideshow_settings()['slides']) === 50, 'Full list retained');
must(count($GLOBALS['test_upload_calls']) === $call_count, 'No extra attachments uploaded when full');

echo "PASS: Offline slideshow direct uploads, editor, API, permissions, and MP4 fallback\n";
