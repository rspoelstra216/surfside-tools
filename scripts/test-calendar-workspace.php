<?php
/** Calendar workspace: routing, isolated saves, Saved Places and permissions. */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_TOOLS_URL', 'https://surfsidefellowship.org/wp-content/plugins/surfside-tools/');
define('SURFSIDE_TOOLS_VERSION', '3.3.0');
$GLOBALS['options'] = array(
    'surfside_tools_settings' => array(
        'google_maps_api_key' => 'map-existing',
        'this_week_mode' => 'sunday',
        'default_event_duration' => 75,
        'future_option' => 'keep-me',
    ),
);
$GLOBALS['logged_in'] = true;
$GLOBALS['admin'] = true;
$GLOBALS['hooks'] = array();
$GLOBALS['places_action'] = '';
$_SERVER['REQUEST_METHOD'] = 'GET';

function add_shortcode($shortcode,$handler) { $GLOBALS['shortcodes'][$shortcode]=$handler; }
function add_action($action,$handler,$priority=10) { $GLOBALS['hooks'][$action]=$handler; }
function check($value,$reason) { if (!$value) throw new RuntimeException($reason); }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function current_user_can($cap) { return $cap === 'manage_options' && $GLOBALS['admin']; }
function sanitize_key($text) { return strtolower(preg_replace('/[^a-z0-9_-]/i','',(string)$text)); }
function sanitize_text_field($text) { return trim((string)$text); }
function wp_unslash($value) { return stripslashes((string)$value); }
function esc_html($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_url($text) { return esc_html($text); }
function absint($number) { return abs((int)$number); }
function checked($given, $value) { if ($given === $value) echo ' checked="checked"'; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'nonce-'.$action; }
function wp_nonce_field($action, $name) { echo '<input type="hidden" name="'.esc_attr($name).'" value="nonce-'.esc_attr($action).'">'; }
function get_option($key,$fallback=null) { return $GLOBALS['options'][$key] ?? $fallback; }
function update_option($key, $value, $autoload=false) { $GLOBALS['options'][$key]=$value; return true; }
function surfside_tools_staff_page_url($slug='') { return 'https://surfsidefellowship.org/dashboard/'.trim($slug,'/').'/'; }
function add_query_arg($key,$value,$url) { return $url.'?'.rawurlencode($key).'='.rawurlencode($value); }
function surfside_tools_saved_places_data() {
    return array(
        array(array('id'=>42,'name'=>'Fellowship Hall','address'=>'42 Pine St')),
        array('old'=>array('name'=>'Old Sanctuary','address'=>'Old Road')),
    );
}
function surfside_tools_get_hidden_place_names() { return array('archive room'); }
function surfside_tools_delete_saved_place_record($id) {
    $GLOBALS['places_action']='delete:'.$id;
    return 'Saved place removed.';
}
function surfside_tools_hide_calendar_place_name($name) {
    $GLOBALS['places_action']='hide:'.$name;
    return 'Place hidden.';
}
function surfside_tools_restore_calendar_place_name($name) {
    $GLOBALS['places_action']='restore:'.$name;
    return 'Place restored.';
}
function is_wp_error($result) { return $result instanceof Exception; }

require dirname(__DIR__).'/includes/frontend-settings.php';
require dirname(__DIR__).'/includes/calendar-workspace.php';

check(isset($GLOBALS['shortcodes']['surfside_staff_settings']), 'Legacy Integrations shortcode retained');
check(surfside_tools_calendar_workspace_view()==='events', 'Manage Events remains default');
check(surfside_tools_calendar_workspace_url()==='https://surfsidefellowship.org/dashboard/calendar/',
    'Existing Calendar URL unchanged');
foreach (array('defaults','places') as $view) {
    $_GET['view']=$view;
    check(surfside_tools_calendar_workspace_view()===$view,'Calendar view is '.$view);
    check(str_contains(surfside_tools_calendar_workspace_url($view),'?view='.$view),'Deep link exists for '.$view);
    $tabs=surfside_tools_calendar_workspace_tabs();
    check(substr_count($tabs,'aria-current="page"')===1,'One current tab for '.$view);
}
$_GET['view']='<script>';
check(surfside_tools_calendar_workspace_view()==='events', 'Unknown view cannot select an editor');
unset($_GET['view']);

$tabs=surfside_tools_calendar_workspace_tabs();
check(str_contains($tabs,'Calendar Defaults') && str_contains($tabs,'Saved Places'),
    'Admin can open every Calendar tab');
$GLOBALS['admin']=false;
$staff=surfside_tools_calendar_workspace_tabs();
check(!str_contains($staff,'Calendar Defaults') && !str_contains($staff,'Saved Places'),
    'Ordinary staff see events, not privileged preferences or location tools');
check(str_contains(surfside_tools_calendar_defaults_panel(),'permission') &&
      str_contains(surfside_tools_calendar_places_panel(),'permission'),
    'Privileged direct panels refuse non-admin access');
$GLOBALS['admin']=true;

$_SERVER['REQUEST_METHOD']='POST';
$_POST=array(
    'surfside_front_settings_action'=>'save_settings',
    'surfside_front_settings_nonce'=>'nonce-surfside_front_settings',
    'google_maps_api_key'=>'map-updated',
);
$notice=surfside_tools_frontend_settings_handle_post();
check(str_contains($notice,'Google Maps settings saved'),'Old integration form saves Maps');
$stored=get_option('surfside_tools_settings');
check($stored['google_maps_api_key']==='map-updated'
    && $stored['this_week_mode']==='sunday'
    && $stored['default_event_duration']===75
    && $stored['future_option']==='keep-me',
    'Saving Google Maps does not reset calendar or future settings');

$_POST=array(
    'surfside_calendar_preferences_action'=>'save',
    'surfside_calendar_preferences_nonce'=>'nonce-surfside_calendar_preferences',
    'this_week_mode'=>'next7',
    'default_event_duration'=>'90',
);
$notice=surfside_tools_calendar_defaults_handle_post();
$stored=get_option('surfside_tools_settings');
check(str_contains($notice,'Calendar defaults saved')
    && $stored['this_week_mode']==='next7'
    && $stored['default_event_duration']===90
    && $stored['google_maps_api_key']==='map-updated'
    && $stored['future_option']==='keep-me',
    'Calendar save updates calendar values only, preserving Maps and other fields');

$_POST['surfside_calendar_preferences_nonce']='bad';
$_POST['default_event_duration']='120';
surfside_tools_calendar_defaults_handle_post();
check(get_option('surfside_tools_settings')['default_event_duration']===90,
    'Invalid calendar nonce prevents modification');
$_POST['surfside_calendar_preferences_nonce']='nonce-surfside_calendar_preferences';
$GLOBALS['admin']=false;
surfside_tools_calendar_defaults_handle_post();
check(get_option('surfside_tools_settings')['default_event_duration']===90,
    'Non-admin calendar POST cannot save');
$GLOBALS['admin']=true;
$_POST['this_week_mode']='invalid';
$_POST['default_event_duration']='999';
surfside_tools_calendar_defaults_handle_post();
check(get_option('surfside_tools_settings')['this_week_mode']==='next7' &&
    get_option('surfside_tools_settings')['default_event_duration']===480,
    'Unsupported week mode and excessive duration normalized');
$_SERVER['REQUEST_METHOD']='GET';
$_POST=array();
$html=surfside_tools_calendar_defaults_panel();
check(str_contains($html,'Save Calendar Defaults') &&
    str_contains($html,'nonce-surfside_calendar_preferences') &&
    str_contains($html,'value="480"'), 'Calendar settings form reflects saved values');

$placeHtml=surfside_tools_calendar_places_panel();
foreach (array('Fellowship Hall','Old Sanctuary','archive room',
    'delete_saved_place','hide_calendar_place','restore_calendar_place') as $expected) {
    check(str_contains($placeHtml,$expected), 'Saved Places UI contains '.$expected);
}
check(substr_count($placeHtml,'nonce-surfside_front_settings')===3,
    'All Saved Places actions retain their existing nonce');
$_SERVER['REQUEST_METHOD']='POST';
foreach (array(
    array('action'=>'delete_saved_place','field'=>'place_id','value'=>'42','expected'=>'delete:42'),
    array('action'=>'hide_calendar_place','field'=>'place_name','value'=>'Old Sanctuary','expected'=>'hide:Old Sanctuary'),
    array('action'=>'restore_calendar_place','field'=>'place_name','value'=>'archive room','expected'=>'restore:archive room'),
) as $case) {
    $_POST=array(
        'surfside_front_settings_action'=>$case['action'],
        'surfside_front_settings_nonce'=>'nonce-surfside_front_settings',
        $case['field']=>$case['value'],
    );
    surfside_tools_calendar_places_handle_post();
    check($GLOBALS['places_action']===$case['expected'],'Legacy Saved Places action reused: '.$case['action']);
}
$GLOBALS['places_action']='';
$_POST['surfside_front_settings_nonce']='bad';
surfside_tools_calendar_places_handle_post();
check($GLOBALS['places_action']==='', 'Invalid place nonce cannot mutate');
$_POST['surfside_front_settings_nonce']='nonce-surfside_front_settings';
$GLOBALS['admin']=false;
surfside_tools_calendar_places_handle_post();
check($GLOBALS['places_action']==='', 'Non-admin cannot mutate Saved Places');
$GLOBALS['admin']=true;
$_POST=array(
    'surfside_front_settings_action'=>'save_settings',
    'surfside_front_settings_nonce'=>'nonce-surfside_front_settings',
    'google_maps_api_key'=>'wrong-context',
);
check(surfside_tools_calendar_places_handle_post()==='',
    'Saved Places view does not accept unrelated Integrations form actions');

$frontend=file_get_contents(dirname(__DIR__).'/includes/frontend-settings.php');
check(str_contains($frontend,'Open Calendar Defaults') &&
    str_contains($frontend,'Open Saved Places') &&
    !str_contains($frontend,'name="this_week_mode"') &&
    !str_contains($frontend,'foreach ($saved as $place)'),
    'Legacy Integrations deep-link sections redirect to Calendar without duplicate editors');
$staffCode=file_get_contents(dirname(__DIR__).'/includes/staff-dashboard.php');
check(str_contains($staffCode,"surfside_tools_calendar_workspace_view()") &&
    str_contains($staffCode,"surfside_tools_calendar_defaults_panel()") &&
    str_contains($staffCode,"surfside_tools_calendar_places_panel()") &&
    str_contains($staffCode,"do_shortcode('[surfside_tools_calendar_manager]')"),
    'Calendar uses existing event manager and renders optional settings and places');
echo "PASS: Calendar tabs, independent setting saves, Saved Places actions, old links and access checks\n";
