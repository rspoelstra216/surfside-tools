<?php
/** Featured Announcement staff screen: read-only presentation plus unchanged API contract. */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_TOOLS_VERSION', 'test');
$GLOBALS['options'] = array(
    'surfside_tools_app_settings' => array(
        'featured_enabled' => 1,
        'featured_headline' => 'Beach Baptism',
        'featured_message' => 'Join the church this weekend.',
        'featured_button_label' => 'Learn More',
        'featured_button_url' => 'https://example.org/event',
        'featured_starts_at' => '2026-10-23T17:00',
        'featured_ends_at' => '2026-10-25T20:00',
    )
);
$GLOBALS['allow_staff'] = false;
$GLOBALS['logged_in'] = false;
$GLOBALS['today'] = '2026-10-22T12:00:00';

function add_filter(...$args) {}
function add_shortcode(...$args) {}
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function current_user_can($cap) { return $cap === 'upload_files' && $GLOBALS['allow_staff']; }
function surfside_tools_staff_login_box($text='') { return 'LOGIN REQUIRED'; }
function surfside_tools_prevent_cache() {}
function surfside_tools_staff_enqueue_styles() {}
function surfside_tools_staff_page_url($path='') { return 'https://example.org/dashboard/'.trim($path, '/').'/'; }
function surfside_tools_app_settings() { return get_option('surfside_tools_app_settings',array()); }
function get_option($key,$default=array()) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key,$value) { $GLOBALS['options'][$key]=$value; return true; }
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function current_datetime() { return new DateTimeImmutable($GLOBALS['today'], wp_timezone()); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string)$value)); }
function wp_unslash($value) { return $value; }
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_textarea($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function esc_url_raw($value) { return (string)$value; }
function wp_nonce_field($action,$name) { echo '<input type="hidden" name="'.esc_attr($name).'" value="test-nonce">'; }
function wp_verify_nonce($input,$action) { return $input==='test-nonce'; }
function checked($enabled) { if ($enabled) echo ' checked="checked"'; }
function check($ok,$why) { if (!$ok) throw new RuntimeException($why); }

require dirname(__DIR__).'/includes/featured-home-announcement.php';

$_SERVER['REQUEST_METHOD']='GET';
$_GET=array();$_POST=array();
$html=surfside_tools_staff_featured_announcement_shortcode();
check(str_contains($html,'LOGIN REQUIRED'), 'Anonymous visitors do not see the feature editor');
$GLOBALS['logged_in']=true;
$html=surfside_tools_staff_featured_announcement_shortcode();
check(str_contains($html,'do not have permission') && !str_contains($html,'Beach Baptism'),
    'Nonstaff visitors do not see saved content');
$GLOBALS['allow_staff']=true;

$html=surfside_tools_staff_featured_announcement_shortcode();
check(str_contains($html,'Scheduled to start') &&
    str_contains($html,'Saved publication status') &&
    str_contains($html,'Coming Up card remains'),
    'Future enabled feature reports its scheduled status correctly');
check(str_contains($html,'Saved content preview') &&
    str_contains($html,'Beach Baptism') &&
    str_contains($html,'Join the church this weekend.') &&
    str_contains($html,'not unsaved edits'),
    'Preview reflects only the saved announcement content');
check(str_contains($html,'name="featured_enabled"') &&
    str_contains($html,'name="featured_headline"') &&
    str_contains($html,'name="featured_message"') &&
    str_contains($html,'name="featured_starts_at"') &&
    str_contains($html,'name="featured_ends_at"') &&
    str_contains($html,'name="featured_button_label"') &&
    str_contains($html,'name="featured_button_url"') &&
    str_contains($html,'surfside_featured_home_nonce'),
    'Existing featured-Home POST fields and nonce are unchanged');

$GLOBALS['today']='2026-10-24T12:00:00';
$featured=surfside_tools_app_featured_announcement();
$html=surfside_tools_staff_featured_announcement_shortcode();
check($featured['active'] === true &&
    str_contains($html,'Currently showing') &&
    str_contains($html,'visible in the app Home screen'),
    'Active publication state agrees with existing mobile API');
$GLOBALS['today']='2026-10-26T12:00:00';
$html=surfside_tools_staff_featured_announcement_shortcode();
check(str_contains($html,'Expired') &&
    str_contains($html,'normal Coming Up card has returned'),
    'Expired scheduled content is described accurately');

$GLOBALS['options']['surfside_tools_app_settings']['featured_enabled']=0;
$html=surfside_tools_staff_featured_announcement_shortcode();
check(str_contains($html,'Not enabled') &&
    str_contains($html,'normal Coming Up card'),
    'Disabled announcement explicitly shows no active feature');
$GLOBALS['options']['surfside_tools_app_settings']['featured_enabled']=1;
$GLOBALS['options']['surfside_tools_app_settings']['featured_headline']='<script>alert(1)</script> Special Sunday';
$html=surfside_tools_staff_featured_announcement_shortcode();
check(strpos($html,'<script>alert(1)</script>') === false &&
    str_contains($html,'&lt;script&gt;'),
    'Featured preview escapes untrusted saved content');

$GLOBALS['options']['surfside_tools_app_settings']['featured_headline']='Beach Baptism';
$GLOBALS['options']['surfside_tools_app_settings']['featured_starts_at']='2026-10-26T18:00';
$GLOBALS['options']['surfside_tools_app_settings']['featured_ends_at']='2026-10-25T20:00';
$html=surfside_tools_staff_featured_announcement_shortcode();
check(str_contains($html,'Check schedule') &&
    str_contains($html,'Run Until must be later than Start Showing'),
    'Impossible end-before-start window displays attention state');

$_SERVER['REQUEST_METHOD']='POST';
$_POST=array(
  'surfside_featured_home_nonce'=>'test-nonce',
  'featured_enabled'=>'1',
  'featured_headline'=>'Annual Picnic',
  'featured_message'=>'Save the date',
  'featured_button_label'=>'Join Us',
  'featured_button_url'=>'https://example.org/picnic',
  'featured_starts_at'=>'2026-10-27T10:00',
  'featured_ends_at'=>'2026-10-30T19:00',
);
$html=surfside_tools_staff_featured_announcement_shortcode();
$settings=$GLOBALS['options']['surfside_tools_app_settings'];
check(str_contains($html,'Featured Announcement saved.') &&
    $settings['featured_headline']==='Annual Picnic' &&
    $settings['featured_ends_at']==='2026-10-30T19:00' &&
    $settings['featured_button_url']==='https://example.org/picnic',
    'Original save action and field names persist');
check(str_contains($html,'Annual Picnic') &&
    str_contains($html,'Scheduled to start'),
    'Successful save shows the newly saved announcement status');

$css=file_get_contents(dirname(__DIR__).'/assets/css/content-media-workspace.css');
$loader=file_get_contents(dirname(__DIR__).'/includes/staff-workspace-navigation.php');
check(str_contains($css,'.surfside-featured-publishing') &&
    str_contains($css,'.surfside-sermon-archive') &&
    str_contains($css,'.surfside-yt-manager') &&
    str_contains($css,'@media(max-width:760px)'),
    'Three management screens share scoped responsive CSS');
check(str_contains($loader,"dashboard/mobile-app/featured-announcement") &&
    str_contains($loader,"$" . "view === 'sermon-archive'") &&
    str_contains($loader,"$" . "view === 'message-archive'") &&
    str_contains($loader,'surfside_tools_workspace_is_staff_page()') &&
    str_contains($loader,'surfside_tools_workspace_asset_version($content_css)'),
    'Staff-only asset loader targets the exact publishing and archive pages');

echo "PASS: Featured announcement staff statuses, preview, privacy and saving\n";
