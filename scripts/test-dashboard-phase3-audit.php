<?php
/**
 * Phase 3F staff dashboard audit: health rules, consistency, and discoverability.
 * Actual helper/render calls; no WordPress runtime or DB required.
 */
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
$GLOBALS['audit_logged_in'] = true;
$GLOBALS['audit_staff'] = true;
$GLOBALS['audit_pending_prayers'] = 0;
$GLOBALS['audit_announcements'] = array(
    'announcement_date' => 'October 24/25, 2026',
    'items' => array('Welcome','Baptism','Food Drive','Bible Study','Volunteer'),
);
$GLOBALS['audit_message'] = array('title' => 'A Faithful Foundation');
$GLOBALS['audit_schedule'] = array();
$GLOBALS['audit_home_images'] = array_fill(0, 6, array('updated'=>1792400000));
$GLOBALS['audit_occurrences'] = array(
    array('title'=>'Sunday Worship','date'=>'2026-10-25','start_time'=>'10:00')
);
$GLOBALS['audit_options'] = array(
    'surfside_tools_settings'=>array('google_maps_api_key'=>'configured-but-untested'),
);
$GLOBALS['audit_now'] = (new DateTimeImmutable('2026-10-22T10:00:00', new DateTimeZone('America/New_York')))->getTimestamp();
$GLOBALS['audit_date'] = '2026-10-22';
$_GET = array();

function add_shortcode(...$args) {}
function check($ok,$why) { if(!$ok) throw new RuntimeException($why); }
function absint($v) { return abs((int)$v); }
function esc_html($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function esc_attr($v) { return esc_html($v); }
function esc_url($v) { return esc_html($v); }
function sanitize_html_class($v) { return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v)); }
function sanitize_key($v) { return sanitize_html_class($v); }
function wp_unslash($v) { return $v; }
function wp_kses_post($s) { return $s; }
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function wp_date($format,$timestamp=null) {
    $ts = $timestamp === null ? $GLOBALS['audit_now'] : $timestamp;
    return (new DateTimeImmutable('@'.(int)$ts))->setTimezone(wp_timezone())->format($format);
}
function current_time($format) {
    return $format === 'timestamp' ? $GLOBALS['audit_now'] : $GLOBALS['audit_date'];
}
function surfside_tools_staff_page_url($path='') {
    return 'https://example.org/dashboard/'.trim($path,'/').'/';
}
function surfside_tools_sermon_archive_viewer_url() {
    return 'https://example.org/dashboard/?view=sermon-archive';
}
function surfside_tools_weekly_release_label($ts) { return wp_date('F j, Y g:i A', $ts); }
function surfside_tools_prayer_list_pending_count() { return $GLOBALS['audit_pending_prayers']; }
function surfside_tools_prayer_list_page_url($section) {
    return 'https://example.org/dashboard/?view=member-engagement&tool=prayer-requests&section='.$section;
}
function surfside_tools_get_announcements_data() { return $GLOBALS['audit_announcements']; }
function surfside_tools_get_message_data() { return $GLOBALS['audit_message']; }
function surfside_tools_weekly_pending() { return $GLOBALS['audit_schedule']; }
function surfside_tools_homepage_get_images() { return $GLOBALS['audit_home_images']; }
function surfside_tools_calendar_get_all_events() {
    return array(array('id'=>12,'title'=>'Church Service'));
}
function surfside_tools_calendar_event_occurrences($event,$start,$end) {
    return $GLOBALS['audit_occurrences'];
}
function surfside_tools_calendar_get_saved_locations() { return array(); }
function get_option($key,$fallback=false) {
    return $GLOBALS['audit_options'][$key] ?? $fallback;
}
function surfside_tools_prevent_cache() {}
function surfside_tools_staff_enqueue_styles() {}
function wp_add_inline_style($handle,$css) {}
function is_user_logged_in() { return $GLOBALS['audit_logged_in']; }
function current_user_can($cap) { return $cap==='upload_files' && $GLOBALS['audit_staff']; }
function surfside_tools_staff_icon($icon) { return '<span aria-hidden="true">Icon</span>'; }
function surfside_tools_staff_login_box() { return '<p>Log in to continue.</p>'; }
function wp_get_current_user() { return (object)array('first_name'=>'Staff','display_name'=>'Surfside'); }
function surfside_tools_workspace_catalog() {
    return array(
        array('title'=>'Content & Publishing','url'=>'https://example.org/dashboard/content-publishing/','items'=>array(
            array('label'=>'Weekly Update','url'=>'https://example.org/dashboard/weekly-update/'),
            array('label'=>'Archived Notes','url'=>'https://example.org/dashboard/?view=sermon-archive'),
            array('label'=>'Featured Announcement','url'=>'https://example.org/dashboard/mobile-app/featured-announcement/'),
            array('label'=>'Ministries','url'=>'https://example.org/dashboard/site-ministries/'),
            array('label'=>'Fifth Tool','url'=>'https://example.org/dashboard/fifth/'),
            array('label'=>'Sixth Tool','url'=>'https://example.org/dashboard/sixth/'),
        )),
        array('title'=>'Events & Serving','url'=>'https://example.org/dashboard/events-serving/','items'=>array(
            array('label'=>'Calendar Manager','url'=>'https://example.org/dashboard/calendar/'),
        )),
    );
}
function note_value($base,$update) {
    return array_replace_recursive($base,$update);
}
// Fail if evaluation introduces PHP warnings such as the former undefined $now.
set_error_handler(function ($severity,$message) {
    if (error_reporting() & $severity) {
        throw new RuntimeException('PHP warning during dashboard audit: '.$message);
    }
    return false;
});

require dirname(__DIR__).'/includes/dashboard-intelligence.php';
require dirname(__DIR__).'/includes/dashboard-overview.php';

$healthy = array(
    'weekly'=>array('announcement_count'=>5,'announcement_date'=>'October 24/25, 2026',
        'message_title'=>'A Faithful Foundation','scheduled_at'=>0,'release_error'=>''),
    'calendar'=>array('next'=>array('title'=>'Worship','date'=>'2026-10-25'),
        'upcoming_count'=>1,'occurrence_count_30'=>3),
    'homepage'=>array('photo_count'=>6),
    'settings'=>array('google_maps_connected'=>true),
);
$status = surfside_tools_dashboard_evaluate_status($healthy)['statuses'];
check($status['weekly']['level']==='good' && $status['weekly']['label']==='Published',
    'Existing weekly content remains healthy without a pending Saturday update');
check($status['calendar']['level']==='good' && $status['calendar']['label']==='Healthy',
    '30-day event supply makes calendar healthy');
check($status['settings']['level']==='good'
    && $status['settings']['label']==='Key saved'
    && strpos($status['settings']['message'],'Connectivity has not been verified')!==false,
    'Configured Google Maps key is not claimed to prove real-time API connectivity');

$emptyWeek = note_value($healthy,array('weekly'=>array(
    'announcement_count'=>0,'announcement_date'=>'','message_title'=>'','scheduled_at'=>0)));
$weekly = surfside_tools_dashboard_evaluate_status($emptyWeek)['statuses']['weekly'];
check($weekly['level']==='warning' && $weekly['label']==='Not published',
    'Unpublished weekly documents must not incorrectly show green Published');

$scheduledAt = (new DateTimeImmutable('2026-10-24T17:00:00', wp_timezone()))->getTimestamp();
$emptyScheduled = note_value($emptyWeek,array('weekly'=>array('scheduled_at'=>$scheduledAt)));
$weekly = surfside_tools_dashboard_evaluate_status($emptyScheduled)['statuses']['weekly'];
check($weekly['level']==='warning' && strpos($weekly['message'],'Nothing is published yet')!==false,
    'Scheduled next-week content is not mistaken for currently published content');
$scheduled = note_value($healthy,array('weekly'=>array('scheduled_at'=>$scheduledAt)));
$weekly = surfside_tools_dashboard_evaluate_status($scheduled)['statuses']['weekly'];
check($weekly['level']==='good' && $weekly['label']==='Scheduled',
    'Published content plus pending Saturday release remains healthy');
$releaseError = note_value($scheduled,array('weekly'=>array('release_error'=>'Failed test release')));
$weekly = surfside_tools_dashboard_evaluate_status($releaseError)['statuses']['weekly'];
check($weekly['level']==='warning' && $weekly['label']==='Release issue',
    'Explicit weekly release errors take priority over a scheduled badge');

$noEvents = note_value($healthy,array('calendar'=>array(
    'next'=>null,'upcoming_count'=>0,'occurrence_count_30'=>0)));
check(surfside_tools_dashboard_evaluate_status($noEvents)['statuses']['calendar']['level']==='critical',
    'Calendar with no future events is critical');
$farEvent = note_value($healthy,array('calendar'=>array(
    'next'=>array('title'=>'Retreat','date'=>'2027-01-09'),
    'upcoming_count'=>1,'occurrence_count_30'=>0)));
$calendar = surfside_tools_dashboard_evaluate_status($farEvent)['statuses']['calendar'];
check($calendar['level']==='warning'
    && strpos($calendar['message'],'next 30 days')!==false,
    'Future events outside the same 30-day dashboard metric give an honest warning');
$cal2 = surfside_tools_dashboard_current_evaluation($farEvent);
check($cal2['statuses']['calendar']===$calendar
    && count(array_filter($cal2['alerts'],fn($a)=>$a['key']==='calendar'))===1,
    'Dashboard overview does not override or duplicate calendar evaluator state');

$GLOBALS['audit_pending_prayers']=2;
$withPrayers=surfside_tools_dashboard_current_evaluation($healthy);
check(count($withPrayers['alerts'])===1
    && $withPrayers['alerts'][0]['key']==='prayer'
    && strpos($withPrayers['alerts'][0]['message'],'2 prayer requests')!==false,
    'Pending prayer review still triggers one actionable alert');

$missingKey = note_value($healthy,array('settings'=>array('google_maps_connected'=>false)));
check(surfside_tools_dashboard_evaluate_status($missingKey)['statuses']['settings']['level']==='critical',
    'Missing saved Google Maps key still triggers attention');

$GLOBALS['audit_pending_prayers']=0;
$markup=surfside_tools_dashboard_overview_shortcode();
check(strpos($markup,'Everything looks good.')!==false
    && strpos($markup,'5</strong><span>announcements published')!==false
    && strpos($markup,'3</strong><span>events in the next 30 days')===false,
    'Dashboard displays healthy weekly summary without stale alerts or hardcoded event fixtures');
// The simulated calendar supplies one real occurrence.
check(strpos($markup,'1</strong><span>events in the next 30 days')!==false
    && strpos($markup,'Current Status')!==false,
    'Rendered calendar count matches actual occurrence window');
check(strpos($markup,'<details class="surfside-dashboard-more-tools">')!==false
    && strpos($markup,'Show 2 more tools')!==false
    && strpos($markup,'Fifth Tool')!==false
    && strpos($markup,'Sixth Tool')!==false
    && strpos($markup,'Calendar Manager')!==false,
    'All authorized task links remain discoverable beyond the first four per group');
check(strpos($markup, 'class="surfside-dashboard-category-link" href="https://example.org/dashboard/content-publishing/"')!==false,
    'Dashboard category heading navigates to its real overview');
check(strpos($markup,'data-surfside-workspace')===false,
    'Dashboard shortcode leaves global staff workspace wrapping to its owner');
check(strpos($markup,'View Archived Notes')!==false,
    'Existing written-sermon-archive shortcut is preserved');

$GLOBALS['audit_staff']=false;
$denied=surfside_tools_dashboard_overview_shortcode();
check(strpos($denied,'do not have permission')!==false
    && strpos($denied,'Fifth Tool')===false,
    'Unprivileged visitors cannot inspect dashboard tool cards');
$GLOBALS['audit_staff']=true;
$GLOBALS['audit_logged_in']=false;
check(strpos(surfside_tools_dashboard_overview_shortcode(),'Log in to continue')!==false,
    'Logged-out visitors still see only the staff login message');

restore_error_handler();
echo "PASS: Dashboard status alignment, no empty-week false positive, tool discovery and privacy\n";
