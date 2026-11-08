<?php
/**
 * No-WordPress regression test for short TV About Surfside text.
 * Run: php scripts/test-site-about-description.php
 */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_TOOLS_URL', 'https://surfsidefellowship.org/wp-content/plugins/surfside-tools/');
$GLOBALS['options'] = array();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = array();

function check($value, $message) { if (!$value) throw new RuntimeException($message); }
function add_action(...$args) {}
function add_shortcode(...$args) {}
function register_setting(...$args) {}
function apply_filters($name, $value) { return $value; }
function get_option($key, $default = null) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = false) {
    $GLOBALS['options'][$key] = $value;
    return true;
}
function absint($v) { return abs((int) $v); }
function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
function sanitize_textarea_field($v) { return trim(strip_tags((string)$v)); }
function sanitize_email($v) { return trim((string)$v); }
function sanitize_key($v) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$v)); }
function esc_url_raw($v) { return (string)$v; }
function wp_json_encode($v) { return json_encode($v); }
function wp_unslash($v) { return $v; }
function wp_verify_nonce($token, $action) { return $token === 'good' && $action === 'surfside_information_update'; }
function current_user_can($capability) { return $capability === 'manage_options'; }
function esc_attr($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function home_url($path = '') { return 'https://surfsidefellowship.org' . $path; }
function get_permalink($post) { return ''; }
function date_i18n($format, $stamp) { return date($format, $stamp); }
function current_datetime() { return new DateTimeImmutable('2026-11-07T10:00:00-05:00'); }
function surfside_tools_get_announcements_data() { return array('items' => array()); }
function surfside_tools_get_message_data() { return array(); }
function rest_ensure_response($v) { return $v; }
function wp_kses_post($v) { return $v; }

require dirname(__DIR__) . '/includes/site-information.php';
require dirname(__DIR__) . '/includes/site-information-manager.php';
require dirname(__DIR__) . '/includes/mobile-api.php';

$original = 'Join us as we worship a perfect God, grow in Christ, and do life together.';
$defaults = surfside_tools_site_information_defaults();
check($defaults['identity']['about_description'] === $original,
    'Existing TV introduction remains the default');

$old = $defaults;
unset($old['identity']['about_description']);
$GLOBALS['options'][SURFSIDE_TOOLS_SITE_INFORMATION_OPTION] = $old;
$read = surfside_tools_get_site_information();
check($read['identity']['about_description'] === $original,
    'Pre-existing WordPress options gain the new default without migration');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
    'surfside_information_action' => 'save',
    'surfside_information_nonce' => 'good',
    'surfside_information_section' => 'information',
    'church_name' => $defaults['identity']['name'],
    'tagline' => $defaults['identity']['tagline'],
    'about_description' => '<b>Come worship together.</b>',
    'phone' => $defaults['identity']['phone'],
    'email' => $defaults['identity']['email'],
    'contact_url' => $defaults['identity']['contact_url'],
    'venue' => $defaults['location']['venue'],
    'street' => $defaults['location']['street'],
    'city' => $defaults['location']['city'],
    'state' => $defaults['location']['state'],
    'postal_code' => $defaults['location']['postal_code'],
);
$notice = surfside_tools_site_information_manager_handle_post();
check(str_contains($notice, 'Changes saved'), 'Site Information editor accepts About text');
$after = surfside_tools_get_site_information();
check($after['identity']['about_description'] === 'Come worship together.',
    'About text is sanitized and saved in canonical site information');
check($after['identity']['tagline'] === $defaults['identity']['tagline'],
    'Updating About text does not alter the existing tagline');

$api = surfside_tools_mobile_api_app();
check($api['site']['about_description'] === 'Come worship together.',
    'Public /app exposes the saved About description');
check($api['site']['tagline'] === $defaults['identity']['tagline'],
    'Public /app preserves existing site tagline');
check($api['location']['street'] === $defaults['location']['street']
    && count($api['services']) === 2,
    'Existing location and weekly services remain stable');

unset($_POST['about_description']);
surfside_tools_site_information_manager_handle_post();
check(surfside_tools_get_site_information()['identity']['about_description'] === 'Come worship together.',
    'Legacy or partial information forms do not erase the saved About description');

$managerSource = file_get_contents(dirname(__DIR__) . '/includes/site-information-manager.php');
check(str_contains($managerSource, 'name="about_description"')
    && str_contains($managerSource, 'esc_textarea(')
    && str_contains($managerSource, 'maxlength="280"'),
    'Site Information UI contains a short, escaped About text editor');

echo "About Surfside WordPress setting and public app API checks passed.\n";
