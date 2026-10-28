<?php
/** Scheduled Weekly Update (Saturday 5 PM Eastern) regression. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['options'] = array();
$GLOBALS['hooks'] = array();
$GLOBALS['scheduled'] = array();
$GLOBALS['test_user'] = true;
$GLOBALS['test_can_upload'] = true;
$GLOBALS['test_fail_cron'] = false;
$GLOBALS['test_archive_failure'] = false;
$GLOBALS['test_archive_calls'] = 0;
$GLOBALS['test_purges'] = 0;
$GLOBALS['test_now'] = 0;

class WP_Error {
    private $code;
    private $message;
    public function __construct($code, $message) { $this->code=$code; $this->message=$message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function check($ok,$msg) { if (!$ok) throw new RuntimeException($msg); }
function add_action($name, $callback, $priority=10) { $GLOBALS['hooks'][$name][]=$callback; }
function add_filter(...$args) {}
function add_shortcode(...$args) {}
function has_action(...$args) { return false; }
function is_user_logged_in() { return $GLOBALS['test_user']; }
function current_user_can($cap) { return $cap === 'upload_files' && $GLOBALS['test_can_upload']; }
function get_option($name,$default=false) { return $GLOBALS['options'][$name] ?? $default; }
function add_option($name,$value,$deprecated='',$autoload=false) {
    if (array_key_exists($name,$GLOBALS['options'])) return false;
    $GLOBALS['options'][$name]=$value;
    return true;
}
function update_option($name,$value,$autoload=false) {
    if (array_key_exists($name,$GLOBALS['options']) && $GLOBALS['options'][$name] === $value) return false;
    $GLOBALS['options'][$name]=$value;
    return true;
}
function delete_option($name) { $existed=array_key_exists($name,$GLOBALS['options']); unset($GLOBALS['options'][$name]); return $existed; }
function wp_schedule_single_event($when,$hook) {
    if ($GLOBALS['test_fail_cron']) return false;
    $GLOBALS['scheduled'][$hook]=(int)$when; return true;
}
function wp_clear_scheduled_hook($hook) { unset($GLOBALS['scheduled'][$hook]); }
function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
function sanitize_textarea_field($v) { return trim(strip_tags((string)$v)); }
function absint($value) { return abs((int) $value); }
function wp_kses_post($v) { return (string)$v; }
function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_attr($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($v) { return esc_html($v); }
function wp_unslash($v) { return is_array($v) ? array_map('stripslashes',$v) : stripslashes((string)$v); }
function current_time($fmt,$gmt=false) { return $fmt==='timestamp' ? $GLOBALS['test_now'] : '2026-10-24 17:00:00'; }
function date_i18n($fmt,$time) { return gmdate($fmt,$time); }
function wp_date($fmt,$timestamp=null,$timezone=null) {
    $tz=$timezone ?: new DateTimeZone('America/New_York');
    $date=new DateTimeImmutable('@'.($timestamp??$GLOBALS['test_now']));
    return $date->setTimezone($tz)->format($fmt);
}
function wp_nonce_field($action,$name) { echo '<input name="'.esc_attr($name).'" value="test-nonce">'; }
function wp_verify_nonce($nonce,$action) { return $nonce === 'test-nonce'; }
function nocache_headers() {}
function surfside_tools_sermon_archive_on_publish($data) {
    $GLOBALS['test_archive_calls']++;
    if ($GLOBALS['test_archive_failure'])
        return new WP_Error('archive_failed','The sermon archive could not be saved.');
    return true;
}
function has_cached_files() { return false; }

require dirname(__DIR__) . '/includes/core-weekly-tools.php';
require dirname(__DIR__) . '/includes/weekly-schedule.php';
require dirname(__DIR__) . '/includes/weekly-workspace-ui.php';

$tz = new DateTimeZone('America/New_York');
function eastern($date) { return (new DateTimeImmutable($date,new DateTimeZone('America/New_York')))->getTimestamp(); }
// Fixed dates intentionally test weekend boundaries and DST transitions.
$fixed_friday=eastern('2026-10-23 13:00:00');
$fixed_saturday=eastern('2026-10-24 17:00:00');
check(surfside_tools_weekly_next_release_at($fixed_friday)===$fixed_saturday,'Friday release is Saturday 5 PM');
check(surfside_tools_weekly_next_release_at(eastern('2026-10-24 16:59:59'))===$fixed_saturday,
    'Before 5 PM on Saturday is still this Saturday');
check(surfside_tools_weekly_next_release_at($fixed_saturday)===eastern('2026-10-31 17:00:00'),
    'At exactly 5 PM Saturday moves to next weekend');
check(surfside_tools_weekly_next_release_at(eastern('2026-11-01 08:00:00'))===eastern('2026-11-07 17:00:00'),
    'Fall DST uses Eastern wall clock for following Saturday');
check(surfside_tools_weekly_next_release_at(eastern('2027-03-13 18:00:00'))===eastern('2027-03-20 17:00:00'),
    'Spring DST uses Eastern wall clock for following Saturday');
check(strpos(surfside_tools_weekly_release_label($fixed_saturday),'5:00 PM EDT')!==false,'Schedule label includes EDT');

// The staged/publication scenario must be in the future relative to the CI runner.
// Public getters deliberately call maybe_activate() with real time(), whereas
// explicit scheduling tests pass $GLOBALS['test_now']. Fixed October 2026
// staging dates started publishing themselves once that weekend had passed.
// Keep the real-time fallback intact and instead place the simulated Friday
// ahead of the current wall clock; all other timestamps derive from it.
$scenario_friday=(new DateTimeImmutable('next friday',$tz))->setTime(13,0,0);
$friday=$scenario_friday->getTimestamp();
$saturday=$scenario_friday->modify('+1 day')->setTime(17,0,0)->getTimestamp();
$nextSat=$scenario_friday->modify('+8 days')->setTime(17,0,0)->getTimestamp();
$old_date=$scenario_friday->modify('-6 days')->format('F j, Y');
$new_date=$scenario_friday->modify('+1 day')->format('F j, Y');

$old_ann=array('announcement_date'=>$old_date,'items'=>array('Previous announcement'),
    'announcements'=>'<ol><li>Previous announcement</li></ol>','timestamp'=>1);
$old_sermon=array('title'=>'Previous Message','series'=>'Old series','date'=>$old_date,
    'main_heading'=>'Old title','notes'=>'Old notes','prayer'=>'Old prayer','timestamp'=>1);
$GLOBALS['options']['surfside_tools_announcements_current']=$old_ann;
$GLOBALS['options']['surfside_tools_message_current']=$old_sermon;
$GLOBALS['test_now']=$friday;
$queued=surfside_tools_weekly_schedule(array('announcement_date'=>$new_date,'items'=>array(
    'New announcement','  Second announcement  ')),array(
        'title'=>'Next Message','notes'=>'New notes','date'=>$new_date),$friday);
check(!is_wp_error($queued)&&$queued['publish_at']===$saturday,'Both documents scheduled successfully');
check($GLOBALS['scheduled'][SURFSIDE_TOOLS_WEEKLY_RELEASE_HOOK]===$saturday,'WP-Cron event set at the release time');
check(get_option('surfside_tools_announcements_current')===$old_ann
    && get_option('surfside_tools_message_current')===$old_sermon,
    'Staging does not expose new data or overwrite last week');
check($GLOBALS['test_archive_calls']===0 && $GLOBALS['test_purges']===0,
    'Staging does not archive or purge public content');

$staff = surfside_tools_weekly_staff_status_markup($queued, $saturday);
check(strpos($staff, 'Currently published') !== false &&
      strpos($staff, 'Previous Message') !== false &&
      strpos($staff, $old_date) !== false,
    'Staff clearly sees last week as currently public');
check(strpos($staff, 'Ready for Saturday') !== false &&
      strpos($staff, 'Next Message') !== false &&
      strpos($staff, 'New announcement') !== false &&
      strpos($staff, 'Review scheduled documents') !== false,
    'Scheduled summary and a read-only preview are visible');
check(strpos($staff, 'surfside_tools_weekly_cancel_nonce') !== false &&
      strpos($staff, 'Cancel Scheduled Update') !== false,
    'Scheduled cancellation keeps the existing secured form');

$changed=surfside_tools_weekly_schedule(array(
    'announcement_date'=>$new_date,'items'=>array('Corrected announcement')),null,$friday+60);
check($changed['sermon']['title']==='Next Message' &&
    $changed['announcements']['items']===array('Corrected announcement'),
    'Correcting only announcements preserves the staged sermon notes');
check(surfside_tools_weekly_maybe_activate($saturday-1)===false,'One second early does not publish');
check(get_option('surfside_tools_announcements_current')===$old_ann &&
    get_option('surfside_tools_message_current')===$old_sermon,'Both public options unchanged until cutoff');

$GLOBALS['test_now']=$saturday;
check(surfside_tools_weekly_maybe_activate($saturday)===true,'Queued documents activate at Saturday 5 PM');
$published_ann=surfside_tools_get_announcements_data();
$published_sermon=surfside_tools_get_message_data();
check($published_ann['items']===array('Corrected announcement') &&
    $published_sermon['title']==='Next Message','Both documents appear in public getters after release');
check(get_option('surfside_tools_announcements_backup')['items']===array('Previous announcement') &&
    get_option('surfside_tools_message_backup')['title']==='Previous Message',
    'Previous publication backed up at activation, not during stage');
check($GLOBALS['test_archive_calls']===1 && !surfside_tools_weekly_pending() &&
    !isset($GLOBALS['scheduled'][SURFSIDE_TOOLS_WEEKLY_RELEASE_HOOK]),
    'Archive happens once and draft/cron are removed after successful release');
check(surfside_tools_weekly_maybe_activate($saturday+1)===false &&
    $GLOBALS['test_archive_calls']===1,'Repeat cron/public reads are idempotent');

$planned=surfside_tools_weekly_schedule(null,array('title'=>'November Sermon','notes'=>'Nov notes'),$saturday+30);
check($planned['publish_at']===$nextSat,'Another upload following the cutoff targets next weekend');
check(surfside_tools_weekly_cancel_pending()===true,'Authorized staff can cancel pending release');
check(!surfside_tools_weekly_pending() && !isset($GLOBALS['scheduled'][SURFSIDE_TOOLS_WEEKLY_RELEASE_HOOK]),
    'Cancel removes the draft and its cron event');
$emptyStatus = surfside_tools_weekly_staff_status_markup(array(), $nextSat);
check(strpos($emptyStatus, 'Nothing scheduled yet') !== false &&
      strpos($emptyStatus, 'Currently published') !== false &&
      strpos($emptyStatus, 'Cancel Scheduled Update') === false,
    'Unsheduled view still shows live documents without cancel action');
check(get_option('surfside_tools_message_current')['title']==='Next Message',
    'Cancel does not change published sermon');

$GLOBALS['test_can_upload']=false;
check(is_wp_error(surfside_tools_weekly_schedule(null,array('title'=>'Wrong','notes'=>'Denied'),$saturday+40)),
    'Unauthorized uploader cannot schedule');
check(is_wp_error(surfside_tools_weekly_cancel_pending()),'Unauthorized person cannot cancel');
$GLOBALS['test_can_upload']=true;
check(is_wp_error(surfside_tools_weekly_schedule(null,null,$saturday+40)),
    'Empty upload cannot schedule');

$GLOBALS['test_fail_cron']=true;
$failedCron=surfside_tools_weekly_schedule(array('announcement_date'=>'Oct 31','items'=>array('Draft')),null,$saturday+40);
check(is_wp_error($failedCron) &&
    !empty(surfside_tools_weekly_pending()['last_error']) &&
    get_option('surfside_tools_announcements_current')['items']===array('Corrected announcement'),
    'Failed event registration warns staff without publishing draft');
$GLOBALS['test_fail_cron']=false;
$planned=surfside_tools_weekly_schedule(null,array('title'=>'Fix','notes'=>'Pending notes'),$saturday+41);
check(empty($planned['last_error']) &&
    $planned['announcements']['items']===array('Draft') &&
    $planned['sermon']['title']==='Fix','Retry clears cron error and merges both sections');

$GLOBALS['test_archive_failure']=true;
$before=get_option('surfside_tools_message_current');
$beforeAnn=get_option('surfside_tools_announcements_current');
$failed=surfside_tools_weekly_maybe_activate($nextSat);
check(is_wp_error($failed) &&
    get_option('surfside_tools_message_current')===$before &&
    get_option('surfside_tools_announcements_current')===$beforeAnn,
    'Archive failure leaves both last-week documents visible');
check(surfside_tools_weekly_pending()['retry_after']===$nextSat+300 &&
    $GLOBALS['scheduled'][SURFSIDE_TOOLS_WEEKLY_RELEASE_HOOK]===$nextSat+300,
    'Archive failure schedules five-minute retry');
check(surfside_tools_weekly_maybe_activate($nextSat+1)===false,
    'Retry backoff avoids repeated archive writes on every visitor');

$GLOBALS['test_archive_failure']=false;
$GLOBALS['test_now']=$nextSat+300;
check(surfside_tools_weekly_maybe_activate($nextSat+300)===true,
    'Retry publishes both sections once archive recovers');
check(get_option('surfside_tools_message_current')['title']==='Fix' &&
    get_option('surfside_tools_announcements_current')['items']===array('Draft'),
    'Retry publishes complete intended release');

$screen=surfside_tools_weekly_update_shortcode();
check(strpos($screen,'Saturday at 5:00 PM Eastern')!==false &&
    strpos($screen,'Nothing scheduled yet')!==false &&
    strpos($screen,'Currently published')!==false &&
    strpos($screen,'Upload next week')!==false &&
    strpos($screen,'aria-current="step"')!==false &&
    strpos($screen,'surfside-weekly-announcements-docx')!==false,
    'Staff page renders live/next cards, accessible steps and labeled upload fields');
check(strpos($screen,'Publish Weekly Update')===false,
    'Main weekly flow no longer advertises immediate publication');

$dashboard=file_get_contents(dirname(__DIR__) . '/includes/dashboard-intelligence.php');
$overview=file_get_contents(dirname(__DIR__) . '/includes/dashboard-overview.php');
check(strpos($dashboard,'Weekly content became stale')===false &&
    strpos($dashboard,'still from last week')===false &&
    strpos($overview,'Weekly content became stale')===false &&
    strpos($overview,'still from last week')===false,
    'Tuesday stale warnings and week-old alerts removed from both dashboard evaluators');

$staffCode = file_get_contents(dirname(__DIR__) . '/includes/staff-dashboard.php');
$stylesheet = file_get_contents(dirname(__DIR__) . '/assets/css/weekly-workspace.css');
check(strpos($staffCode, '<h1>Weekly Update</h1>') !== false &&
      strpos($stylesheet, '.surfside-weekly-status-grid') !== false &&
      strpos($stylesheet, '@media (max-width: 760px)') !== false &&
      strpos($stylesheet, 'grid-template-columns: 1fr;') !== false,
    'Staff header and mobile stacking are integrated without altering core actions');

$unsafe = array('publish_at' => $nextSat,
    'sermon' => array('title' => '<script>alert(1)</script>',
                     'notes' => '<img src=x onerror=alert(1)>'));
$escaped = surfside_tools_weekly_staff_status_markup($unsafe, $nextSat);
check(strpos($escaped, '<script>') === false &&
      strpos($escaped, '<img ') === false &&
      strpos($escaped, '&lt;script&gt;') !== false,
    'Staged document review escapes unsafe content');

echo "PASS: Scheduled weekly publication, DST, backups, retries, cancellation and polished staff status\n";
