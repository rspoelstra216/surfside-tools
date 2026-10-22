<?php
/**
 * Phase 2C integration page layout and form isolation regression tests.
 *
 * Intentionally standalone: tests page rendering without an installed WP site.
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['is_admin'] = true;
$GLOBALS['logged_in'] = true;
$GLOBALS['called'] = array();
$GLOBALS['shortcodes'] = array();
$GLOBALS['saved_options'] = array(
    'surfside_tools_settings' => array(
        'google_maps_api_key' => 'existing-google-key',
        'this_week_mode' => 'sunday',
        'default_event_duration' => 90,
        'future_option' => 'preserve',
    ),
);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = array();
$_GET = array();

function check($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
}
function add_shortcode($name, $callback) { $GLOBALS['shortcodes'][$name] = $callback; }
function add_action($name, $callback, $priority = 10) { $GLOBALS['actions'][$name] = $callback; }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function current_user_can($cap) { return $cap === 'manage_options' && $GLOBALS['is_admin']; }
function surfside_tools_prevent_cache() { $GLOBALS['called'][] = 'prevent_cache'; }
function surfside_tools_staff_enqueue_styles() { $GLOBALS['called'][] = 'enqueue_styles'; }
function surfside_tools_staff_page_url($path = '') {
    return 'https://surfsidefellowship.org/dashboard/' . trim($path, '/') . '/';
}
function wp_json_encode($value) { return json_encode($value); }
function wp_unslash($value) { return stripslashes($value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string) $value)); }
function sanitize_text_field($value) { return (string) $value; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid-' . $action; }
function wp_nonce_field($action, $name) {
    echo '<input type="hidden" name="' . $name . '" value="valid-' . $action . '">';
}
function get_option($key, $default = false) { return $GLOBALS['saved_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = false) { $GLOBALS['saved_options'][$key] = $value; return true; }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function add_query_arg($key, $value, $url = null) {
    if (is_array($key)) {
        $url = $value;
        $values = $key;
    } else {
        $values = array($key => $value);
    }
    return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($values);
}
function surfside_tools_church_settings_shared_save() { return ''; }
function surfside_tools_twitch_settings_handle_post() { return ''; }
function surfside_tools_church_settings_shared_integrations_panel() {
    return '<form method="post" class="surfside-shared-integrations-form">' .
      '<input type="hidden" name="surfside_church_integrations_action" value="save_shared_integrations">' .
      '<details class="surfside-integration-card"><summary>Giving</summary></details>' .
      '<details class="surfside-integration-card"><summary>Cloudflare Turnstile</summary></details>' .
      '<p class="surfside-shared-integrations-save"><button type="submit">Save Giving &amp; Turnstile</button></p></form>';
}
function surfside_tools_youtube_settings_panel() {
    return '<details id="surfside-youtube" class="surfside-front-settings-card">' .
      '<summary>YouTube Sermon Archive</summary>' .
      '<form method="post"><button type="submit">Save YouTube Settings</button></form></details>';
}
function surfside_tools_youversion_settings_panel() {
    return '<details id="surfside-youversion" class="surfside-front-settings-card">' .
      '<summary>YouVersion</summary><form method="post" class="surfside-youversion-form">' .
      '<button name="surfside_youversion_settings_action" value="save">Save</button></form></details>';
}
function surfside_tools_visual_css_settings_panel() {
    return '<details class="surfside-front-settings-card surfside-visual-css-settings-card">' .
      '<summary>Reveal &amp; Countdown Styling</summary>' .
      '<form method="post"><button type="submit">Save Visual CSS</button></form></details>';
}
function surfside_tools_staff_login_box() { return 'login'; }

require dirname(__DIR__) . '/includes/frontend-settings.php';
check(isset($GLOBALS['shortcodes']['surfside_staff_settings']),
    'Existing shortcode retained');

$html = surfside_tools_staff_settings_shortcode();
$sections = array(
    'External connections',
    'Church services',
    'Advanced appearance',
);
$last = 0;
foreach ($sections as $label) {
    $pos = strpos($html, '>' . $label . '</h2>');
    check($pos !== false && $pos >= $last, 'Logical integration section in order: ' . $label);
    $last = $pos;
}
foreach (array(
    'Google Maps', 'YouTube Sermon Archive', 'YouVersion',
    'Giving', 'Cloudflare Turnstile', 'Reveal &amp; Countdown Styling',
) as $label) {
    check(strpos($html, $label) !== false, 'Integration remains available: ' . $label);
}
foreach (array(
    'Save Google Maps Settings',
    'Save YouTube Settings',
    'Save Giving &amp; Turnstile',
    'Save Visual CSS',
) as $control) {
    check(strpos($html, $control) !== false, 'Visible local save action: ' . $control);
}
check(strpos($html, 'surfside-front-settings-form') !== false &&
      strpos($html, 'surfside-shared-integrations-form') !== false &&
      strpos($html, 'surfside-youversion-form') !== false,
    'Independent service forms preserved');
check(strpos($html, 'Save Integrations</button>') === false &&
      strpos($html, 'surfside-integrations-save-all') === false &&
      strpos($html, "const forms=[") === false,
    'Old fetch-based global Save Integrations control removed');
check(strpos($html, 'Open Streaming Settings') === false &&
      strpos($html, 'Open Twitch Connection') === false &&
      strpos($html, 'Open Calendar Defaults') === false &&
      strpos($html, 'Open Saved Places') === false,
    'No duplicate external-workspace cards clutter Integrations');
check(strpos($html, '#surfside-focus-calendar-defaults') !== false &&
      strpos($html, '#surfside-focus-saved-places') !== false &&
      strpos($html, '#surfside-focus-twitch') !== false &&
      strpos($html, 'view=defaults') !== false &&
      strpos($html, 'view=places') !== false &&
      strpos($html, 'stream_tab=twitch') !== false &&
      strpos($html, 'window.location.replace(target)') !== false,
    'Old hash bookmarks route to the new Calendar and Streaming workspaces');
check(strpos($html, "'#surfside-youtube': 'surfside-youtube'") !== false &&
      strpos($html, "'#surfside-youversion': 'surfside-youversion'") !== false,
    'POST redirect to YouTube/YouVersion reopens the correct accordion');
check(strpos($html, 'display:none!important}.surfside-youversion-row') === false,
    'YouVersion Save button is not hidden');
check(strpos($html, 'surfside-front-settings .surfside-shared-integrations-save{display:block!important') !== false &&
      strpos($html, 'surfside-front-settings .surfside-visual-css-settings-card form>p{display:block!important') !== false,
    'Shared services and appearance Save buttons are visible');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
    'surfside_front_settings_action' => 'save_settings',
    'surfside_front_settings_nonce' => 'valid-surfside_front_settings',
    'google_maps_api_key' => 'changed-key',
);
$notice = surfside_tools_frontend_settings_handle_post();
check(strpos($notice, 'Google Maps settings saved') !== false, 'Maps own submit still supported');
$stored = get_option('surfside_tools_settings');
check($stored['google_maps_api_key'] === 'changed-key' &&
      $stored['this_week_mode'] === 'sunday' &&
      $stored['default_event_duration'] === 90 &&
      $stored['future_option'] === 'preserve',
    'Independent Google save preserves calendar and unrelated options');

$_POST['surfside_front_settings_nonce'] = 'wrong';
surfside_tools_frontend_settings_handle_post();
check(get_option('surfside_tools_settings')['google_maps_api_key'] === 'changed-key',
    'Invalid nonce cannot overwrite Maps');
$GLOBALS['is_admin'] = false;
$denied = surfside_tools_staff_settings_shortcode();
check(strpos($denied, 'do not have permission') !== false,
    'Non-admin does not get settings');
$GLOBALS['is_admin'] = true;
$GLOBALS['logged_in'] = false;
check(surfside_tools_staff_settings_shortcode() === 'login',
    'Anonymous visitors still get login screen');

$youtube = file_get_contents(dirname(__DIR__) . '/includes/youtube-sermon-settings.php');
$youversion = file_get_contents(dirname(__DIR__) . '/includes/youversion-settings.php');
$visual = file_get_contents(dirname(__DIR__) . '/includes/visual-utilities-settings.php');
$shared = file_get_contents(dirname(__DIR__) . '/includes/church-settings-polish.php');
check(strpos($youtube, "wp_nonce_field('surfside_youtube_settings'") !== false &&
      strpos($youtube, 'Save YouTube Settings') !== false &&
      strpos($youtube, 'Sync Now') !== false,
    'YouTube nonce, save and sync remain');
check(strpos($youversion, 'name="surfside_youversion_settings_action" value="save"') !== false &&
      strpos($youversion, "'surfside_youversion_settings'") !== false,
    'YouVersion nonce and save remain');
check(strpos($visual, 'Save Visual CSS') !== false &&
      strpos($visual, "'surfside_visual_css_settings'") !== false,
    'Visual CSS nonce and save remain');
check(strpos($shared, 'Save Giving &amp; Turnstile') !== false &&
      strpos($shared, "'surfside_church_integrations_save'") !== false,
    'Shared service save and nonce remain');

echo "PASS: Integrations grouping, local saves, old-link redirects, permissions and options\n";
