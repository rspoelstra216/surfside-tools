<?php
/** Run with: php scripts/test-mobile-calendar-recurrence.php */
define('ABSPATH', __DIR__ . '/');
function add_action(...$args) {}
function add_filter(...$args) {}
function absint($value) { return abs((int)$value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_]/', '', strtolower($value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function is_wp_error($value) { return false; }
function rest_ensure_response($value) { return $value; }
function wp_timezone_string() { return 'America/New_York'; }
function get_post_meta(...$args) { return 'Shared activity'; }
function surfside_tools_calendar_get_event($id) { global $fixture; return $fixture; }
function surfside_tools_calendar_recurrence_label($event) { return 'Repeats monthly'; }
require __DIR__ . '/../includes/calendar-event-groups.php';
function expect($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$fixture = array(
 'date'=>'2026-01-07', 'recurrence_type'=>'monthly_weekday',
 'recurrence_interval'=>2, 'recurrence_weekdays'=>array(3,6,3,9),
 'recurrence_day_of_month'=>0, 'recurrence_week_of_month'=>1,
 'recurrence_weekday'=>3, 'recurrence_end_date'=>'2027-01-06',
 'recurrence_exceptions'=>array('2026-03-04','bad','2026-02-30','2026-03-04'),
);
$rules=surfside_tools_calendar_mobile_recurrence($fixture);
expect($rules['interval']===2 && $rules['weekdays']===array(3,6), 'interval and selected weekdays');
expect($rules['week_of_month']===1 && $rules['weekday']===3, 'monthly weekday rule');
expect($rules['exceptions']===array('2026-03-04'), 'valid deduplicated exceptions');
expect($rules['start_date']==='2026-01-07' && $rules['end_date']==='2027-01-06', 'saved series boundaries');
foreach (array('daily','weekly','monthly_date','monthly_weekday') as $type) {
 $copy=$fixture; $copy['recurrence_type']=$type;
 expect(surfside_tools_calendar_mobile_recurrence($copy)['type']===$type, 'supported recurrence type');
}
expect(surfside_tools_calendar_mobile_recurrence(array('recurrence_type'=>'none'))===null, 'single event');
expect(surfside_tools_calendar_mobile_recurrence(array('recurrence_type'=>'unknown'))===null, 'unsupported rule');
class FixtureResponse {
 private $data;
 function __construct($data) { $this->data=$data; }
 function get_data() { return $this->data; }
 function set_data($data) { $this->data=$data; }
}
class FixtureRequest { function get_route() { return '/surfside/v1/events'; } }
$response=new FixtureResponse(array('events'=>array(
 array('id'=>10,'date'=>'2026-10-07'),array('id'=>10,'date'=>'2026-12-02'),array('id'=>0)
)));
$data=surfside_tools_calendar_add_event_groups_to_mobile_api($response,null,new FixtureRequest())->get_data();
expect($data['timezone']==='America/New_York', 'church timezone');
expect($data['events'][0]['recurrence']===$rules && $data['events'][1]['recurrence']===$rules, 'stable rules across occurrences');
expect($data['events'][0]['date']==='2026-10-07', 'occurrence date retained');
expect($data['events'][2]['recurrence']===null, 'missing source event');
echo "Mobile calendar recurrence tests passed.\n";
