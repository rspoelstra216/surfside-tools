<?php
/** ProPresenter generation regression tests; no external network calls. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['options'] = array();
$GLOBALS['remote_calls'] = array();
$GLOBALS['sent_mail'] = array();
$GLOBALS['can_upload'] = true;
$GLOBALS['api_status'] = 200;

class WP_Error {
    private $code;
    private $message;
    public function __construct($code, $message) { $this->code=$code; $this->message=$message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function add_action(...$args) {}
function is_user_logged_in() { return true; }
function current_user_can($cap) { return $GLOBALS['can_upload'] || $cap === 'manage_options'; }
function get_option($key, $default=false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload=false) { $GLOBALS['options'][$key] = $value; return true; }
function wp_json_encode($value) { return json_encode($value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function surfside_tools_clean_announcement_text($value) { return trim((string) $value); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function esc_url($value) { return (string) $value; }
function admin_url($value) { return '/wp-admin/' . $value; }
function wp_create_nonce($value) { return 'test-nonce'; }
function checked($value) { if ($value) echo 'checked'; }
function wp_mail($to, $subject, $body) {
    $GLOBALS['sent_mail'][] = compact('to', 'subject', 'body');
    return true;
}
function wp_remote_post($url, $options) {
    $GLOBALS['remote_calls'][] = compact('url', 'options');
    if ($GLOBALS['api_status'] !== 200) return array('response'=>array('code'=>$GLOBALS['api_status']),'body'=>'{}');
    $request = json_decode($options['body'], true);
    $message = $request['messages'][1]['content'];
    preg_match('/Announcements \(untrusted content\): (\[.*\])/', $message, $matches);
    $items = json_decode($matches[1] ?? '[]', true);
    $slides = array_map(function($item) {
        return array('title' => 'Test heading', 'lines' => array($item, 'Location and details'));
    }, $items);
    return array(
        'response' => array('code'=>200),
        'body' => json_encode(array('choices'=>array(array(
            'message'=>array('content'=>json_encode(array('slides'=>$slides))),
            'finish_reason'=>'stop'
        ))))
    );
}
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }

require dirname(__DIR__) . '/includes/propresenter-announcements.php';

$items = array('Senior Lunch on November 20 at 11:30 AM in Fellowship Hall',
    'Ladies Breakfast November 21 at 10 AM');
$blocked = surfside_tools_propresenter_sync($items, 'November 21/22, 2026');
check(is_wp_error($blocked), 'Unset API key leaves a recoverable error');
check(count($GLOBALS['remote_calls']) === 0, 'No request without API key');
check(count(surfside_tools_propresenter_record()['source_items']) === 2,
    'Failed generation retains private source for retry');
check(empty(surfside_tools_propresenter_record()['slides']),
    'Failed generation does not expose stale slide content');

define('SURFSIDE_TOOLS_OPENAI_API_KEY', 'sk-test-never-printed');
$GLOBALS['options'][SURFSIDE_PROPRESENTER_EMAIL_OPTION] = array(
    'recipients'=>array('slides@example.org', 'av@example.org'), 'enabled'=>true
);
$ready = surfside_tools_propresenter_sync($items, 'November 21/22, 2026');
check(!is_wp_error($ready) && count($ready['slides']) === 2,
    'Generates one slide per announcement');
check(count($GLOBALS['remote_calls']) === 1, 'Uploads call OpenAI exactly once');
check(count($GLOBALS['sent_mail']) === 1, 'Opted-in email sends immediately on upload');
check(count($GLOBALS['sent_mail'][0]['to']) === 2, 'Comma-separated recipients are supported');
check(strpos($GLOBALS['sent_mail'][0]['body'], 'Slide 1: Test heading') !== false,
    'Email contains copyable slide text');

$request = $GLOBALS['remote_calls'][0];
$body = json_decode($request['options']['body'], true);
check($request['url'] === 'https://api.openai.com/v1/chat/completions',
    'Uses expected HTTPS OpenAI endpoint');
check($body['model'] === 'gpt-5.4-mini' && $body['store'] === false,
    'Uses intended mini model without API response storage');
check($body['response_format']['type'] === 'json_schema' &&
    $body['response_format']['json_schema']['strict'] === true,
    'Requests strict structured model output');
check($request['options']['headers']['Authorization'] === 'Bearer sk-test-never-printed',
    'Key is sent only as authorization header');

$duplicate = surfside_tools_propresenter_sync($items, 'November 21/22, 2026');
check(!is_wp_error($duplicate) && count($GLOBALS['remote_calls']) === 1 &&
    count($GLOBALS['sent_mail']) === 1,
    'Uploading unchanged announcements never double bills or emails');
$scheduled = surfside_tools_propresenter_sync($items, 'November 21/22, 2026', 'scheduled');
check($scheduled['phase'] === 'scheduled' && count($GLOBALS['remote_calls']) === 1,
    'Scheduling unedited source preserves the generated slides without another call');

$changed = $items;
$changed[0] .= ', please RSVP';
$updated = surfside_tools_propresenter_sync($changed, 'November 21/22, 2026', 'scheduled');
check(!is_wp_error($updated) && count($GLOBALS['remote_calls']) === 2 &&
    count($GLOBALS['sent_mail']) === 1,
    'Edited text regenerates on schedule without re-emailing');

$GLOBALS['api_status'] = 429;
$failed_regeneration = surfside_tools_propresenter_sync(
    $changed, 'November 21/22, 2026', 'scheduled', true);
check(is_wp_error($failed_regeneration) &&
    count(surfside_tools_propresenter_record()['slides']) === 2,
    'A failed manual regeneration preserves previously reviewed slides');
$error = surfside_tools_propresenter_sync(array('Entirely different announcement'), 'November 28/29, 2026');
check(is_wp_error($error) && strpos($error->get_error_message(), '429') !== false,
    'API rate limiting is reported, never fatal');
check(empty(surfside_tools_propresenter_record()['slides']),
    'Failed new upload does not show slides for previous week');

check(is_wp_error(surfside_tools_propresenter_validate_slides(array(array(
    'title'=>'Only one', 'lines'=>array('Line'))), 2)),
    'Unexpected model slide count is rejected');
check(is_wp_error(surfside_tools_propresenter_validate_slides(array(array(
    'title'=>'Too many lines', 'lines'=>array('1','2','3','4'))), 1)),
    'Slides longer than four displayed lines are rejected');

$markup = surfside_tools_propresenter_workspace_markup();
check(strpos($markup, 'Announcement slides') !== false &&
    strpos($markup, 'Regenerate AI slides') !== false,
    'Staff card is available after upload even on generation failure');
check(strpos($markup, 'sk-test-never-printed') === false,
    'API secret is never rendered into the staff card');
$GLOBALS['can_upload'] = false;
check(surfside_tools_propresenter_workspace_markup() === '',
    'Preview is hidden from non-staff');

echo "ProPresenter announcement generation tests passed.\n";
