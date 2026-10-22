<?php
/** Member care dashboard rendering contract: no WordPress install required. */
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
define('SURFSIDE_TOOLS_VERSION', '3.3.0');

$GLOBALS['test_logged_in'] = true;
$GLOBALS['test_upload'] = true;
$GLOBALS['test_admin'] = true;
$GLOBALS['options'] = array(
    'surfside_tools_prayer_list_requests' => array(
        array('id'=>'pending1', 'status'=>'pending', 'name'=>'Church Member',
            'name_display'=>'named', 'message'=>'Please pray for our family.',
            'submitted_at'=>1792580000, 'duration_days'=>14),
        array('id'=>'active1', 'status'=>'published', 'name'=>'Private Person',
            'name_display'=>'anonymous', 'message'=>'Confidential prayer request.',
            'submitted_at'=>1792400000, 'approved_at'=>1792450000,
            'expires_at'=>1810000000),
        array('id'=>'history1', 'status'=>'answered', 'name'=>'Member in History',
            'name_display'=>'named', 'message'=>'Answered.',
            'submitted_at'=>1792200000, 'answered_at'=>1792400000),
    ),
    'surfside_tools_volunteer_needs' => array(
        array('key'=>'need-1','title'=>'Children Welcome Team',
            'ministry_key'=>'kids','description'=>'Help families.',
            'commitment'=>'One Sunday a month','active'=>true),
        array('key'=>'need-2','title'=>'Setup Crew',
            'ministry_key'=>'','description'=>'Set up chairs.',
            'commitment'=>'Saturday mornings','active'=>false),
    ),
    'surfside_tools_contact_settings' => array(
        'recipients'=>array('general'=>'info@example.org',
            'prayer'=>'prayer@example.org','pastor'=>'pastor@example.org'),
        'turnstile_site_key'=>'secret-site-value',
        'turnstile_secret_key'=>'secret-api-value',
    ),
);
$GLOBALS['test_time'] = 1792630000;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = array();
$_POST = array();

class WP_Error {
    private $code; private $message;
    public function __construct($code,$message='') {$this->code=$code;$this->message=$message;}
    public function get_error_message(){return $this->message;}
}
function add_action(...$args) {}
function add_filter(...$args) {}
function add_shortcode(...$args) {}
function is_user_logged_in(){return $GLOBALS['test_logged_in'];}
function current_user_can($cap) {
    return $cap==='upload_files' ? $GLOBALS['test_upload'] :
        ($cap==='manage_options' ? $GLOBALS['test_admin'] : false);
}
function surfside_tools_prevent_cache() {}
function surfside_tools_staff_enqueue_styles() {}
function surfside_tools_staff_login_box($text=''){return '<p>Login required</p>';}
function surfside_tools_staff_page_url($path='') {
    return 'https://surfsidefellowship.org/dashboard/'.trim($path,'/').'/';
}
function surfside_tools_staff_icon($name){return '<span aria-hidden="true">icon</span>';}
function add_query_arg($key,$value,$url) {
    return $url . (strpos($url,'?')===false ? '?' : '&') . rawurlencode($key) . '=' . rawurlencode($value);
}
function wp_unslash($value){return $value;}
function sanitize_key($value){return strtolower(preg_replace('/[^a-z0-9_-]/','',(string)$value));}
function sanitize_text_field($value){return trim(strip_tags((string)$value));}
function sanitize_textarea_field($value){return trim(strip_tags((string)$value));}
function sanitize_email($value){return filter_var($value,FILTER_VALIDATE_EMAIL) ? $value : '';}
function is_email($value){return filter_var($value,FILTER_VALIDATE_EMAIL)!==false;}
function absint($value){return abs((int)$value);}
function current_time($type){return $GLOBALS['test_time'];}
function wp_date($format,$timestamp){return gmdate($format,$timestamp);}
function get_option($key,$fallback=false){
    if($key==='admin_email')return 'admin@example.org';
    return $GLOBALS['options'][$key]??$fallback;
}
function update_option($key,$value,$autoload=false){$GLOBALS['options'][$key]=$value;return true;}
function wp_nonce_field($action,$name){
    echo '<input type="hidden" name="'.esc_attr($name).'" value="nonce">';
}
function wp_verify_nonce($a,$b){return false;}
function selected($value,$expected){if($value===$expected)echo ' selected="selected"';}
function checked($value,$expected=true){if($value===$expected)echo ' checked="checked"';}
function esc_html($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function esc_attr($value){return esc_html($value);}
function esc_url($value){return esc_html($value);}
function esc_textarea($value){return esc_html($value);}
function wp_json_encode($value){return json_encode($value);}
function check($condition,$label){if(!$condition)throw new RuntimeException($label);}
function surfside_tools_get_published_ministries(){
    return array(array('key'=>'kids','name'=>'Children','icon'=>''));
}
function surfside_tools_get_site_information(){
    return array('identity'=>array('email'=>'church@example.org'));
}
function is_wp_error($item){return $item instanceof WP_Error;}
function surfside_tools_purge_cache() {}
function wp_generate_uuid4(){return 'test-id';}

require dirname(__DIR__).'/includes/prayer-list.php';
require dirname(__DIR__).'/includes/volunteer-needs.php';
require dirname(__DIR__).'/includes/contact-management.php';
require dirname(__DIR__).'/includes/member-engagement.php';

$dashboard = surfside_tools_staff_member_engagement_view();
check(str_contains($dashboard,'Prayers and Praises') &&
      str_contains($dashboard,'1</strong><span>Prayers awaiting review') &&
      str_contains($dashboard,'1</strong><span>Active prayer requests') &&
      str_contains($dashboard,'1</strong><span>Published volunteer needs'),
      'Member Engagement summary reads current prayer and volunteer counts');
check(str_contains($dashboard,'Manage Prayer Requests') &&
      str_contains($dashboard,'Manage Volunteer Needs'),
      'Member Engagement links preserve established management routes');

$_GET['tool']='prayer-requests';
$pending = surfside_tools_staff_member_engagement_view();
check(str_contains($pending,'surfside-prayer-workspace') &&
      str_contains($pending,'Prayers and Praises') &&
      str_contains($pending,'Please pray for our family.') &&
      str_contains($pending,'Approve &amp; Publish') &&
      str_contains($pending,'value="approve"') &&
      str_contains($pending,'value="private"') &&
      str_contains($pending,'value="archive"') &&
      str_contains($pending,'surfside_prayer_review_nonce'),
      'Pending staff prayer actions remain secured and unchanged');
check(substr_count($pending,'aria-current="page"')===1 &&
      str_contains($pending,'Awaiting review') &&
      str_contains($pending,'On prayer list'),
      'Prayer summary and active tab are accessible');

$_GET['section']='active';
$active = surfside_tools_staff_member_engagement_view();
check(str_contains($active,'Anonymous in prayer list') &&
      str_contains($active,'Name hidden from the public prayer list') &&
      !str_contains($active,'Private Person') &&
      str_contains($active,'value="answered"') &&
      str_contains($active,'value="extend-7"') &&
      str_contains($active,'value="extend-14"') &&
      str_contains($active,'value="extend-30"'),
      'Anonymous display and active prayer actions are preserved');
$_GET['section']='history';
$history=surfside_tools_staff_member_engagement_view();
check(str_contains($history,'Member in History') &&
      str_contains($history,'In history') &&
      !str_contains($history,'value="approve"'),
      'History remains read-only and distinct from approval list');

$GLOBALS['options']['surfside_tools_prayer_list_requests']=array();
$_GET['section']='pending';
$empty=surfside_tools_staff_member_engagement_view();
check(str_contains($empty,'All caught up') &&
      str_contains($empty,'no new prayer requests'),
      'Empty pending tab provides accurate next action without inventing records');

$_GET=array('tool'=>'volunteer-needs');
$volunteer=surfside_tools_staff_member_engagement_view();
check(str_contains($volunteer,'1</strong><span>Visible in app') &&
      str_contains($volunteer,'1</strong><span>Not published') &&
      str_contains($volunteer,'2</strong><span>Total saved') &&
      str_contains($volunteer,'Children Welcome Team') &&
      str_contains($volunteer,'Setup Crew') &&
      str_contains($volunteer,'Show in app'),
      'Serving opportunities clearly distinguish current active and inactive');
check(str_contains($volunteer,'surfside_volunteer_needs_nonce') &&
      str_contains($volunteer,'data-volunteer-add') &&
      str_contains($volunteer,'data-volunteer-remove') &&
      str_contains($volunteer,'data-volunteer-up') &&
      str_contains($volunteer,'data-volunteer-down') &&
      str_contains($volunteer,'Save Volunteer Needs'),
      'Volunteer form storage fields and reorder controls are unchanged');
check(str_contains($volunteer,'data-volunteer-template') &&
      str_contains($volunteer,'volunteer_needs[__INDEX__][active]'),
      'Dynamic volunteer creation preserves existing template field naming');

$contact=surfside_tools_staff_contact_management_shortcode();
check(str_contains($contact,'surfside-contact-routing-workspace') &&
      str_contains($contact,'Pastoral privacy') &&
      str_contains($contact,'Dedicated address configured') &&
      str_contains($contact,'Privacy-sensitive') &&
      str_contains($contact,'Pastoral Staff Only'),
      'Admin contact routing distinguishes pastoral privacy from ordinary fallback');
check(str_contains($contact,'name="recipient_pastor"') &&
      str_contains($contact,'value="pastor@example.org"') &&
      str_contains($contact,'surfside_contact_nonce') &&
      str_contains($contact,'Save Contact Settings'),
      'Routing recipient fields and POST nonce remain unchanged');

$GLOBALS['options']['surfside_tools_contact_settings']['recipients']['pastor']='';
$noPastoral=surfside_tools_staff_contact_management_shortcode();
check(str_contains($noPastoral,'Dedicated address required') &&
      str_contains($noPastoral,'name="recipient_pastor" value=""') &&
      !str_contains($noPastoral,'name="recipient_pastor" value="church@example.org"'),
      'Pastoral-only recipient never displays an unsafe fallback as configured');

$GLOBALS['test_admin']=false;
$denied=surfside_tools_staff_contact_management_shortcode();
check(str_contains($denied,'do not have permission') &&
      !str_contains($denied,'prayer@example.org'),
      'Routing addresses never render for non-admin staff');
$GLOBALS['test_upload']=false;
$noStaff=surfside_tools_staff_member_engagement_view();
check(str_contains($noStaff,'do not have permission') &&
      !str_contains($noStaff,'Confidential prayer request.'),
      'Prayer and volunteer management remain staff-only');

$css=file_get_contents(dirname(__DIR__).'/assets/css/member-care-workspace.css');
$loader=file_get_contents(dirname(__DIR__).'/includes/staff-workspace-navigation.php');
check(str_contains($css,'.surfside-prayer-workspace') &&
      str_contains($css,'.surfside-volunteer-needs-manager') &&
      str_contains($css,'.surfside-contact-routing-workspace') &&
      str_contains($css,'@media(max-width:530px)'),
      'All three staff screens share a mobile-friendly scoped stylesheet');
check(str_contains($loader,"$" . "view === 'member-engagement'") &&
      str_contains($loader,"$" . "uri === 'dashboard/contact-routing'") &&
      str_contains($loader,'surfside_tools_workspace_is_staff_page()') &&
      str_contains($loader,'surfside_tools_workspace_asset_version($care_css)'),
      'Shared stylesheet is content-versioned and staff-route restricted');

echo "PASS: Member engagement, prayer review privacy/actions, volunteers and contact routing\n";
