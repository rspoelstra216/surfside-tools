<?php
define('ABSPATH',__DIR__); define('DAY_IN_SECONDS',86400);
$GLOBALS['options']=array(); $GLOBALS['sent']=0; $GLOBALS['jobs']=array();
function add_action(...$args) {} function add_filter(...$args) {}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,...$args){$GLOBALS['options'][$key]=$value;return true;}
function add_option($key,$value,...$args){if(isset($GLOBALS['options'][$key]))return false;$GLOBALS['options'][$key]=$value;return true;}
function delete_option($key){unset($GLOBALS['options'][$key]);}
function wp_schedule_single_event($time,$hook,$args){$GLOBALS['jobs'][]=$args;}
function wp_clear_scheduled_hook(...$args){}
function wp_remote_post(...$args){return true;}
function rest_url($path){return 'https://example.org/wp-json/'.$path;}
function wp_json_encode($data){return json_encode($data);}
function sanitize_key($s){return $s;}
function surfside_tools_get_site_information(){return array('streaming'=>array('twitch_channel'=>'surfsidecf'));}
function surfside_tools_push_send($title,$body,$destination,$audiences){check($destination==='worship' && $audiences===array('livestream'),'push routing');$GLOBALS['sent']++;return array('sent'=>1);}
function is_wp_error($r){return $r instanceof WP_Error;}
class WP_Error {function __construct($code,$message,$data=array()){$this->code=$code;$this->data=$data;} function get_error_code(){return $this->code;}}
class WP_REST_Response {function __construct($data,$status=200,$headers=array()){$this->data=$data;$this->status=$status;$this->headers=$headers;}}
class Request {public $headers,$body;function get_body(){return $this->body;}function get_header($key){return $this->headers[$key]??'';}function get_param($key){return json_decode($this->body,true)[$key]??null;}}
function check($ok,$label){if(!$ok)throw new Exception($label);}
require __DIR__.'/../includes/livestream-notifications.php';
$config=array('secret'=>str_repeat('a',64),'channel'=>'surfsidecf','broadcaster_id'=>'123','callback'=>rest_url('surfside/v1/twitch/eventsub'),'id'=>'sub','status'=>'verification_pending');
update_option('surfside_eventsub_config',$config);
$sub=array('id'=>'sub','type'=>'stream.online','version'=>'1','condition'=>array('broadcaster_user_id'=>'123'),'transport'=>array('callback'=>$config['callback']));
function request_for($type,$data,$age=0){$r=new Request;$r->body=json_encode($data);$at=gmdate(DATE_ATOM,time()-$age);$r->headers=array('Twitch-Eventsub-Message-Id'=>'message','Twitch-Eventsub-Message-Timestamp'=>$at,'Twitch-Eventsub-Message-Type'=>$type,'Twitch-Eventsub-Message-Signature'=>'sha256='.hash_hmac('sha256','message'.$at.$r->body,str_repeat('a',64)));return $r;}
$r=surfside_tools_eventsub_receive(request_for('webhook_callback_verification',array('subscription'=>$sub,'challenge'=>'raw-challenge')));
check($r->status===200 && $r->data==='raw-challenge' && $r->headers['Content-Length']==='13','raw challenge');
$event=array('id'=>'stream1','type'=>'live','broadcaster_user_id'=>'123','broadcaster_user_login'=>'surfsidecf','started_at'=>gmdate(DATE_ATOM));
$data=array('subscription'=>$sub,'event'=>$event);
$bad=request_for('notification',$data);$bad->body.=' ';
check(is_wp_error(surfside_tools_eventsub_receive($bad)),'tampered body');
check(is_wp_error(surfside_tools_eventsub_receive(request_for('notification',$data,601))),'stale signature');
$wrong=$data;$wrong['subscription']['condition']['broadcaster_user_id']='999';
check(is_wp_error(surfside_tools_eventsub_receive(request_for('notification',$wrong))),'wrong broadcaster');
check(surfside_tools_eventsub_receive(request_for('notification',$data))->status===204,'queue acknowledgment');
check($GLOBALS['sent']===0,'no synchronous push');
$key=hash('sha256','surfsidecf|stream1');
surfside_tools_eventsub_deliver($key);check($GLOBALS['sent']===1,'worker delivery');
surfside_tools_eventsub_receive(request_for('notification',$data));surfside_tools_eventsub_deliver($key);check($GLOBALS['sent']===1,'duplicate suppression');
$data['event']['id']='reconnect';surfside_tools_eventsub_receive(request_for('notification',$data));surfside_tools_eventsub_deliver(hash('sha256','surfsidecf|reconnect'));check($GLOBALS['sent']===1,'reconnect guard');
$sub['status']='notification_failures_exceeded';
surfside_tools_eventsub_receive(request_for('revocation',array('subscription'=>$sub)));
check(get_option('surfside_eventsub_config')['status']==='notification_failures_exceeded','revocation recorded');
check(is_wp_error(surfside_tools_eventsub_receive(request_for('notification',$data))),'revoked no sending');
echo "EventSub security, challenge, queue, deduplication, and revocation fixtures passed.\n";
