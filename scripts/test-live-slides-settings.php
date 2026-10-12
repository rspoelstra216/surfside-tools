<?php
/** Standalone Live Slides settings test; no WordPress installation required. */
define('ABSPATH', __DIR__);
$GLOBALS['surfside_test_options'] = array();
$GLOBALS['surfside_test_authenticated'] = true;
$GLOBALS['surfside_test_authorized'] = true;
$GLOBALS['surfside_test_nonce_valid'] = true;

function add_filter($tag, $callback) { $GLOBALS['surfside_filter_' . $tag] = $callback; }
function add_shortcode($tag, $callback) { $GLOBALS['surfside_shortcode_' . $tag] = $callback; }
function get_option($key, $default = false) { return $GLOBALS['surfside_test_options'][$key] ?? $default; }
function update_option($key, $value) { $GLOBALS['surfside_test_options'][$key] = $value; return true; }
function surfside_tools_app_settings() { return get_option('surfside_tools_app_settings', array()); }
function surfside_tools_prevent_cache() {}
function surfside_tools_staff_enqueue_styles() {}
function surfside_tools_staff_page_url($path) { return 'https://surfsidefellowship.org/dashboard/' . $path . '/'; }
function surfside_tools_staff_login_box($text) { return '<p>' . $text . '</p>'; }
function is_user_logged_in() { return $GLOBALS['surfside_test_authenticated']; }
function current_user_can($role) { return $GLOBALS['surfside_test_authorized'] && $role === 'upload_files'; }
function sanitize_text_field($value) { return strip_tags((string) $value); }
function wp_unslash($value) { return $value; }
function wp_verify_nonce($nonce, $action) { return $GLOBALS['surfside_test_nonce_valid'] && $nonce === 'valid' && $action === 'surfside_live_slides_save'; }
function wp_nonce_field($action, $name) { echo '<input name="' . $name . '" value="valid">'; }
function checked($value) { if ($value) echo ' checked="checked"'; }
function selected($value, $match) { if ($value === $match) echo ' selected="selected"'; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

require dirname(__DIR__) . '/includes/live-slides-settings.php';

function must($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
function must_eq($actual, $expected, $message) {
    must($actual === $expected, $message . ': ' . var_export($actual, true));
}

$default = surfside_tools_live_slides_public_settings();
must_eq($default['enabled'], false, 'Feature defaults off');
must_eq($default['default_theme'], 'system', 'Theme follows device by default');
must_eq($default['default_font_size'], 32, 'Default text size');
must_eq($default['default_text_align'], 'center', 'Default alignment');
must(strpos($default['websocket_url'], 'wss://') === 0, 'WebSocket HTTPS transport');
must(strpos($default['viewer_url'], 'https://') === 0, 'Viewer HTTPS transport');
must(!array_key_exists('PUBLISH_TOKEN', $default), 'No publisher secret');

$normalized = surfside_tools_live_slides_normalize(array(
    'enabled' => true, 'theme' => 'invalid', 'font_size' => 200, 'text_align' => 'right',
));
must_eq($normalized['theme'], 'system', 'Unrecognized theme falls back safely');
must_eq($normalized['font_size'], 60, 'Maximum font size enforced');
must_eq($normalized['text_align'], 'center', 'Unrecognized alignment falls back safely');
must_eq(surfside_tools_live_slides_normalize(array('font_size' => -12))['font_size'], 20, 'Minimum font size enforced');

$GLOBALS['surfside_test_options']['surfside_tools_app_settings'] = array(
    'giving_url' => 'https://example.com/give',
    'home_hero_image_id' => 42,
    'featured_enabled' => 1,
    'publisher_token' => 'DO_NOT_EXPOSE',
);
$filtered = surfside_tools_live_slides_mobile_config(array(
    'app' => array('giving_url' => 'https://example.com/give'),
    'site' => array('name' => 'Surfside'),
));
must_eq($filtered['app']['giving_url'], 'https://example.com/give', 'Other app settings preserved in API');
must_eq($filtered['site']['name'], 'Surfside', 'Site data preserved in API');
must_eq($filtered['app']['live_slides']['enabled'], false, 'API defaults off');
must(strpos(json_encode($filtered), 'DO_NOT_EXPOSE') === false, 'Public payload excludes secrets');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
    'surfside_live_slides_action' => 'save',
    'surfside_live_slides_nonce' => 'valid',
    'live_slides_enabled' => '1',
    'live_slides_theme' => 'dark',
    'live_slides_font_size' => '44',
    'live_slides_text_align' => 'left',
);
$page = surfside_tools_staff_live_slides_shortcode();
must(strpos($page, 'settings saved') !== false, 'Success notice appears');
$saved = surfside_tools_app_settings();
must_eq($saved['home_hero_image_id'], 42, 'Home appearance settings preserved');
must_eq($saved['featured_enabled'], 1, 'Featured announcement settings preserved');
must_eq($saved['giving_url'], 'https://example.com/give', 'Giving URL preserved');
must_eq($saved['live_slides_theme'], 'dark', 'Saved theme');
must_eq($saved['live_slides_font_size'], 44, 'Saved font size');
must_eq(surfside_tools_live_slides_public_settings()['enabled'], true, 'Feature enabled after save');

$GLOBALS['surfside_test_nonce_valid'] = false;
$_POST['live_slides_theme'] = 'light';
$page = surfside_tools_staff_live_slides_shortcode();
must(strpos($page, 'security check expired') !== false, 'Rejected invalid nonce');
must_eq(surfside_tools_app_settings()['live_slides_theme'], 'dark', 'Invalid nonce does not save');

$GLOBALS['surfside_test_nonce_valid'] = true;
$GLOBALS['surfside_test_authorized'] = false;
$page = surfside_tools_staff_live_slides_shortcode();
must(strpos($page, 'do not have permission') !== false, 'Staff permissions enforced');
must_eq(surfside_tools_app_settings()['live_slides_theme'], 'dark', 'Unauthorized request does not save');

echo "Live Slides settings and public API checks passed.\n";
