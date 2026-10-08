<?php
/** Real prayer callbacks with stubbed identity verification and storage; no emails/pushes sent. */
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
class WP_Error {
    public $code; public $data;
    function __construct($code,$message='',$data=array()) { $this->code=$code; $this->data=$data; }
}
class WP_REST_Request {
    private $params; private $method; private $header;
    function __construct($params=array(),$method='GET',$header='Bearer owner') { $this->params=$params; $this->method=$method; $this->header=$header; }
    function get_param($key) { return $this->params[$key] ?? null; }
    function get_json_params() { return $this->params; }
    function get_header($key) { return $this->header; }
    function get_method() { return $this->method; }
}
function add_action(...$args) {}
function add_filter(...$args) {}
function sanitize_key($value) { return strtolower((string)$value); }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string)$value)); }
function sanitize_email($value) { return (string)$value; }
function is_email($value) { return filter_var($value,FILTER_VALIDATE_EMAIL)!==false; }
function absint($value) { return abs((int)$value); }
function current_time($type) { return 100000; }
function wp_generate_uuid4() { return 'request-'.(++$GLOBALS['uuid']); }
function get_option($key,$default=false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key,$value,$autoload=false) { $GLOBALS['options'][$key]=$value; }
function get_transient($key) { return 0; }
function set_transient(...$args) {}
function rest_ensure_response($value) { return $value; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function surfside_tools_verify_firebase_id_token($token) {
    return in_array($token,array('owner','other'),true) ? array('sub'=>$token) : new WP_Error('bad_token','Invalid token',array('status'=>401));
}
function surfside_tools_contact_submission_recipient(...$args) { return 'secretary@example.org'; }
function wp_mail(...$args) { ++$GLOBALS['mail_count']; return true; }
function surfside_tools_mobile_admin_require_admin($request) { return array('uid'=>'staff'); }
function surfside_tools_push_send(...$args) { ++$GLOBALS['push_count']; return array(); }
require __DIR__.'/../includes/prayer-list.php';
require __DIR__.'/../includes/prayer-member-status.php';
require __DIR__.'/../includes/mobile-api.php';
require __DIR__.'/../includes/mobile-admin-prayer.php';
function check($condition,$label) { if (!$condition) throw new RuntimeException($label); }
function action($id,$action,$extra=array(),$token='owner') {
    return surfside_tools_prayer_member_manage(new WP_REST_Request(array_merge(array('id'=>$id,'action'=>$action),$extra),'POST','Bearer '.$token));
}
$GLOBALS['options']=array(); $GLOBALS['uuid']=0; $GLOBALS['push_count']=0; $GLOBALS['mail_count']=0;
$submission=array('name'=>'Member','email'=>'member@example.org','category'=>'prayer','prayer_privacy'=>'church-list','prayer_name_display'=>'anonymous','prayer_duration'=>14,'message'=>'Original request','owner_uid'=>'other');
$first=surfside_tools_mobile_api_contact(new WP_REST_Request($submission,'POST'));
$second=surfside_tools_mobile_api_contact(new WP_REST_Request($submission,'POST'));
$id=$first['prayer_request_id'];
check($id!==$second['prayer_request_id'],'Identical submissions return their own exact IDs');
check($first['prayer_request_manageable']===true,'Signed-in submission linked');
$items=surfside_tools_prayer_list_requests();
check($items[0]['owner_uid']==='owner','Body UID cannot spoof verified owner');
$before=$items;
foreach (array('','Bearer invalid','Bearer other') as $header) {
    $result=surfside_tools_prayer_member_manage(new WP_REST_Request(array('id'=>$id,'action'=>'answered'),'POST',$header));
    check(is_wp_error($result),'Unsigned, invalid and different accounts rejected');
    check(surfside_tools_prayer_list_requests()===$before,'Rejected request changes nothing');
}
$bad=surfside_tools_mobile_api_contact(new WP_REST_Request($submission,'POST','Bearer invalid'));
check(is_wp_error($bad)&&$GLOBALS['mail_count']===2,'Invalid submission token fails before email');
$owned=surfside_tools_prayer_member_manage(new WP_REST_Request(array('id'=>$id)));
check($owned['request']==='Original request'&&$owned['can_manage'],'Owner can read current content');
check(!isset($owned['owner_uid'])&&!isset($owned['email']),'Private identity fields not returned');
check(!isset(surfside_tools_prayer_member_status_response(new WP_REST_Request(array('id'=>$id)))['request']),'Legacy public status reveals no request content');
surfside_tools_mobile_admin_prayer_action(new WP_REST_Request(array('id'=>$id,'action'=>'approve'),'POST'));
check($GLOBALS['push_count']===1,'First approval notifies once');
$edit=action($id,'update',array('message'=>'Updated request','name_display'=>'named','duration_days'=>7,'status'=>'published','owner_uid'=>'other'));
check($edit['status']==='pending'&&$edit['request']==='Updated request','Edit returns to staff review; ignores injected status/owner');
check(!surfside_tools_prayer_list_is_active(surfside_tools_prayer_list_requests()[0]),'Edited request removed from public list pending review');
surfside_tools_mobile_admin_prayer_action(new WP_REST_Request(array('id'=>$id,'action'=>'approve'),'POST'));
check($GLOBALS['push_count']===1,'Edited approval does not notify again');
$old_expiry=surfside_tools_prayer_list_requests()[0]['expires_at'];
$extended=action($id,'extend',array('duration_days'=>14));
check($extended['expires_at']===$old_expiry+14*DAY_IN_SECONDS&&$GLOBALS['push_count']===1,'Extension stays silent');
$invalid=action($id,'update',array('message'=>'','name_display'=>'named','duration_days'=>7));
check(is_wp_error($invalid),'Empty update rejected');
$answered=action($id,'answered');
check($answered['status']==='answered'&&!$answered['can_manage'],'Answered request moves to history with no reopen');
check(!surfside_tools_prayer_list_is_active(surfside_tools_prayer_list_requests()[0]),'Answered request no longer public');
check(is_wp_error(action($id,'extend',array('duration_days'=>7))),'Answered request cannot be extended/reopened');
check(is_wp_error(action($id,'update',array('message'=>'Reopen','name_display'=>'named','duration_days'=>7))),'Answered request cannot be edited/reopened');
$anonymous=surfside_tools_mobile_api_contact(new WP_REST_Request($submission,'POST',''));
check($anonymous['prayer_request_manageable']===false,'Anonymous submission stays supported without member controls');
check(is_wp_error(action($anonymous['prayer_request_id'],'answered')),'Ownerless legacy record stays read-only');
$legacy=array('status'=>'pending','approved_at'=>123);
check(!surfside_tools_prayer_list_claim_publication($legacy),'Previously approved legacy record stays silent');
check($GLOBALS['push_count']===1,'Member actions send no extra push');
echo "Member prayer ownership and lifecycle fixtures passed.\n";
