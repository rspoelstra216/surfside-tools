<?php
/** Isolated tests for the OpenAI credential editor, never making network requests. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['options'] = array();
$GLOBALS['is_admin'] = true;
$GLOBALS['logged_in'] = true;
$GLOBALS['remote_calls'] = array();
$GLOBALS['test_http_status'] = 200;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = array();

class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function add_action(...$args) {}
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function current_user_can($permission) { return $permission === 'manage_options' && $GLOBALS['is_admin']; }
function get_option($name, $default=false) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name, $value, $autoload=false) {
    check($autoload === false, 'Credentials must not be autoloaded');
    $GLOBALS['options'][$name] = $value;
    return true;
}
function delete_option($name) { unset($GLOBALS['options'][$name]); return true; }
function sanitize_key($v) { return strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string) $v)); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function wp_unslash($v) { return stripslashes((string) $v); }
function wp_verify_nonce($nonce, $purpose) { return $nonce === 'valid-' . $purpose; }
function wp_nonce_field($purpose, $name) { echo '<input name="' . $name . '" value="valid-' . $purpose . '">'; }
function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_html($v) { return esc_attr($v); }
function wp_remote_get($url, $opts) {
    $GLOBALS['remote_calls'][] = array('url' => $url, 'options' => $opts);
    return array('response' => array('code' => $GLOBALS['test_http_status']));
}
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function surfside_tools_frontend_settings_notice($message, $type='success') {
    return '<notice type="' . esc_attr($type) . '">' . esc_html($message) . '</notice>';
}

require dirname(__DIR__) . '/includes/propresenter-announcements.php';
require dirname(__DIR__) . '/includes/openai-integration.php';

function post_openai($action, $key='', $nonce='valid-surfside_openai_integration') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = array(
        'surfside_openai_integration_action' => $action,
        'surfside_openai_integration_nonce' => $nonce,
        'surfside_openai_api_key' => $key,
    );
    return surfside_tools_openai_integration_handle_post();
}

check(surfside_tools_propresenter_api_key() === '', 'No key initially configured');
$empty_panel = surfside_tools_openai_integration_panel();
check(strpos($empty_panel, 'OpenAI — ProPresenter Announcements') !== false,
    'Dedicated OpenAI card appears in Integrations');
check(strpos($empty_panel, 'Save OpenAI Key') !== false, 'Dedicated Save button present');
check(strpos($empty_panel, 'Not configured') !== false, 'Shows unconfigured status');

$secret = 'sk-proj-test-test-123456789012345';
$notice = post_openai('save', $secret);
check(strpos($notice, 'API key saved') !== false, 'Administrator can save key');
check(surfside_tools_openai_stored_api_key() === $secret, 'Key stored without modification');
check(surfside_tools_propresenter_api_key() === $secret, 'Slide generator reads dashboard key');
$panel = surfside_tools_openai_integration_panel();
check(strpos($panel, 'Key saved') !== false, 'Shows configured status');
check(strpos($panel, 'Test OpenAI Connection') !== false, 'Test button appears');
check(strpos($panel, 'Remove saved key') !== false, 'Remove control appears');
check(strpos($panel, $secret) === false, 'Saved key never rendered in page markup');
check(strpos($panel, 'value=""') !== false, 'API input is blank after saving');

$notice = post_openai('save', '');
check(strpos($notice, 'No change made') !== false &&
    surfside_tools_openai_stored_api_key() === $secret,
    'Submitting blank key preserves existing credential');

$notice = post_openai('test');
check(strpos($notice, 'accepted the saved key') !== false, 'Test confirms key and model');
check(count($GLOBALS['remote_calls']) === 1, 'Connection test performs one request');
check($GLOBALS['remote_calls'][0]['url'] ===
    'https://api.openai.com/v1/models/gpt-5.4-mini', 'Checks the configured model');
check($GLOBALS['remote_calls'][0]['options']['headers']['Authorization'] === 'Bearer ' . $secret,
    'Secret sent only as an authorization header');

$GLOBALS['test_http_status'] = 401;
$notice = post_openai('test');
check(strpos($notice, 'rejected the API key') !== false, 'Unauthorized status handled safely');
check(strpos($notice, $secret) === false, 'Never reports raw secret in errors');

$notice = post_openai('save', 'sk-proj-bogus', 'invalid');
check(strpos($notice, 'Security check failed') !== false &&
    surfside_tools_openai_stored_api_key() === $secret,
    'Invalid CSRF nonce cannot overwrite key');

$GLOBALS['is_admin'] = false;
check(surfside_tools_openai_integration_panel() === '', 'Non-admin cannot view key editor');
$denied = post_openai('remove');
check(strpos($denied, 'Only administrators') !== false &&
    surfside_tools_openai_stored_api_key() === $secret,
    'Non-admin cannot remove saved key');
$GLOBALS['is_admin'] = true;

$removed = post_openai('remove');
check(strpos($removed, 'removed') !== false &&
    surfside_tools_openai_stored_api_key() === '' &&
    surfside_tools_propresenter_api_key() === '', 'Remove clears stored key');

post_openai('save', $secret);
define('SURFSIDE_TOOLS_OPENAI_API_KEY', 'sk-config-priority-1234567890123');
check(surfside_tools_propresenter_api_key() === 'sk-config-priority-1234567890123',
    'Server constant overrides dashboard key');
$server_panel = surfside_tools_openai_integration_panel();
check(strpos($server_panel, 'Managed by server') !== false &&
    strpos($server_panel, 'Save OpenAI Key') === false,
    'Dashboard cannot overwrite a configured server credential');
$notice = post_openai('save', 'sk-proj-unapproved-1234567890');
check(strpos($notice, 'managed through server configuration') !== false &&
    surfside_tools_openai_stored_api_key() === $secret,
    'Cannot replace key when server credential takes priority');

echo "OpenAI dashboard integration tests passed.\n";
