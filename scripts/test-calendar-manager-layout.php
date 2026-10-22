<?php
/** Render the staff Calendar Manager with fake WordPress events. */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_TOOLS_VERSION', '3.3.0');
$GLOBALS['allow'] = true;
$GLOBALS['query'] = array();
$GLOBALS['fake_posts'] = array(
    37 => (object) array(
        'ID' => 37, 'post_type' => 'surfside_event',
        'post_status' => 'publish', 'post_title' => 'Senior Potluck Luncheon',
        'post_content' => 'Bring a dish to share.',
    ),
);
$GLOBALS['meta'] = array(
    37 => array(
        '_surfside_event_date' => '2026-10-31',
        '_surfside_event_start_time' => '12:00',
        '_surfside_event_end_time' => '13:00',
        '_surfside_event_location' => 'Fellowship Hall',
        '_surfside_event_location_name' => 'Fellowship Hall',
        '_surfside_event_recurrence_type' => 'none',
        '_surfside_event_recurrence_interval' => 1,
    ),
);
class WP_Query {
    public $posts = array();
    public function __construct($args) {
        $this->posts = $args['post_type'] === 'surfside_event'
            ? array_values($GLOBALS['fake_posts']) : array();
    }
}
function add_action(...$args) {}
function add_shortcode(...$args) {}
function wp_register_style(...$args) {}
function wp_enqueue_style(...$args) {}
function wp_add_inline_style(...$args) {}
function wp_register_script(...$args) {}
function wp_enqueue_script(...$args) {}
function wp_add_inline_script(...$args) {}
function is_user_logged_in() { return true; }
function current_user_can($cap) { return $GLOBALS['allow'] && $cap === 'upload_files'; }
function surfside_tools_prevent_cache() {}
function surfside_tools_staff_enqueue_styles() {}
function current_time($format) { return $format === 'Y-m-d' ? '2026-10-22' : 0; }
function absint($value) { return abs((int) $value); }
function sanitize_text_field($v) { return trim((string)$v); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v)); }
function wp_unslash($v) { return stripslashes((string)$v); }
function get_post($id) { return $GLOBALS['fake_posts'][$id] ?? null; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function get_the_title($id) { return $GLOBALS['fake_posts'][$id]->post_title ?? ''; }
function wp_reset_postdata() {}
function date_i18n($format, $timestamp) { return date($format, $timestamp); }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return esc_html($s); }
function esc_textarea($s) { return esc_html($s); }
function esc_js($s) { return addslashes((string)$s); }
function checked($value, $expected = true) { if ($value == $expected) echo ' checked="checked"'; }
function selected($value, $expected = true) { if ($value == $expected) echo ' selected="selected"'; }
function wp_nonce_field($name,$field) {
    echo '<input type="hidden" name="'.esc_attr($field).'" value="nonce">';
}
function remove_query_arg($keys) { return '/dashboard/calendar/'; }
function add_query_arg($key, $value = null) {
    return '/dashboard/calendar/?' . (is_array($key)
        ? http_build_query($key)
        : http_build_query(array($key=>$value)));
}
function wp_kses_post($s) { return $s; }
function check($condition,$reason) { if (!$condition) throw new RuntimeException($reason); }

require dirname(__DIR__) . '/includes/calendar-manager.php';

$_GET = array();
$_POST = array();
$html = surfside_tools_calendar_manager_shortcode();
check(strpos($html, 'id="surfside-calendar-event-editor"') !== false &&
    strpos($html, 'id="surfside-calendar-event-list"') !== false,
    'Editor and browse sections have stable deep-link targets');
check(strpos($html, 'Browse events') !== false &&
    strpos($html, 'Add an event') !== false &&
    strpos($html, '1 active event') !== false,
    'Staff can immediately browse/add events and see accurate count');
check(strpos($html, 'surfside-calendar-panel-list') !== false &&
    strpos($html, 'surfside-calendar-panel-editor') !== false &&
    strpos($html, 'aria-labelledby="surfside-calendar-list-title"') !== false,
    'Task areas have distinct accessible landmarks');
check(strpos($html, 'Senior Potluck Luncheon') !== false &&
    strpos($html, 'Fellowship Hall') !== false &&
    strpos($html, 'Edit Senior Potluck Luncheon') !== false &&
    strpos($html, '?edit_event=37#surfside-calendar-event-editor') !== false,
    'Event listing retains data and Edit deep-link to existing form');
check(strpos($html, 'Skip next occurrence of') === false &&
    strpos($html, 'Delete Senior Potluck Luncheon') !== false,
    'Existing non-recurring events do not show skip action but retain Delete');
check(strpos($html, 'name="surfside_calendar_action" value="save"') !== false &&
    strpos($html, 'name="surfside_calendar_action" value="delete"') !== false &&
    strpos($html, 'name="surfside_calendar_nonce"') !== false,
    'Underlying save/delete actions and security nonce are unchanged');

$_GET = array('event_search'=>'nonexistent');
$empty = surfside_tools_calendar_manager_shortcode();
check(strpos($empty, '0 matching events') !== false &&
    strpos($empty, '0 results') !== false &&
    strpos($empty, 'No events matched your search.') !== false &&
    strpos($empty, 'Show all events') !== false,
    'Search shows accurate empty result count and clear recovery');

$_GET = array('event_search'=>'Potluck');
$filtered = surfside_tools_calendar_manager_shortcode();
check(strpos($filtered, '1 matching event') !== false &&
    strpos($filtered, '1 result') !== false &&
    strpos($filtered, 'Senior Potluck Luncheon') !== false,
    'Search count is scoped to matching events rather than all events');

$_GET = array('edit_event'=>37);
$editing = surfside_tools_calendar_manager_shortcode();
check(strpos($editing, 'surfside-calendar-layout is-editing') !== false &&
    strpos($editing, 'Editing existing event') !== false &&
    strpos($editing, 'Add another event') !== false &&
    strpos($editing, 'name="event_id" value="37"') !== false &&
    strpos($editing, 'Save Changes') !== false,
    'Editing preserves current event, edit behavior and add-another shortcut');

$GLOBALS['allow'] = false;
check(strpos(surfside_tools_calendar_manager_shortcode(), 'do not have permission') !== false,
    'Nonstaff cannot read or change Calendar Manager');

$css = file_get_contents(dirname(__DIR__) . '/assets/css/calendar-workspace.css');
check(strpos($css, '.surfside-calendar-layout:not(.is-editing) .surfside-calendar-panel-list') !== false &&
    strpos($css, 'order: -1') !== false &&
    strpos($css, '@media (max-width: 900px)') !== false,
    'Mobile view puts event list first except during editing');
check(strpos($css, '.surfside-calendar-event-actions .surfside-calendar-delete-action') !== false &&
    strpos($css, ':focus-visible') !== false &&
    strpos($css, 'scroll-margin-top') !== false,
    'Action contrast, keyboard focus and anchor offsets styled');
echo "PASS: Calendar Manager staff layout, browse/add/edit links, search and mobile tests\n";
