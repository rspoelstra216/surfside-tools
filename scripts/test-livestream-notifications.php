<?php
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
function add_action(...$args){}
function add_filter(...$args){}
$GLOBALS['options']=array();$GLOBALS['sent']=array();
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,...$args){$GLOBALS['options'][$key]=$value;return true;}
function delete_option($key){unset($GLOBALS['options'][$key]);}
function add_option($key,$value,...$args){if(isset($GLOBALS['options'][$key]))return false;$GLOBALS['options'][$key]=$value;return true;}
function surfside_tools_twitch_live_status(){return $GLOBALS['status'];}
function is_wp_error($value){return false;}
function surfside_tools_push_send(...$args){$GLOBALS['sent'][]=$args;return array('sent'=>1);}
require __DIR__.'/../includes/livestream-notifications.php';
function check($condition,$label){if(!$condition)throw new RuntimeException($label);}
$now=time();
$live=array('status'=>'live','is_live'=>true,'channel'=>'surfsidecf','stream_id'=>'one','started_at'=>gmdate(DATE_ATOM,$now-20),'checked_at'=>gmdate(DATE_ATOM,$now));
check(surfside_tools_live_push_candidate($live,array(),$now)!=='','fresh stream');
foreach(array('unknown','offline') as $status){$x=$live;$x['status']=$status;check(surfside_tools_live_push_candidate($x,array(),$now)==='',$status);}
$x=$live;$x['started_at']=gmdate(DATE_ATOM,$now-601);check(surfside_tools_live_push_candidate($x,array(),$now)==='','old stream');
$x=$live;$x['checked_at']=gmdate(DATE_ATOM,$now-91);check(surfside_tools_live_push_candidate($x,array(),$now)==='','stale check');
$x=$live;$x['started_at']='invalid';check(surfside_tools_live_push_candidate($x,array(),$now)==='','bad timestamp');
$GLOBALS['status']=$live;surfside_tools_live_push_check();surfside_tools_live_push_check();
check(count($GLOBALS['sent'])===1,'repeat poll dedup');
check($GLOBALS['sent'][0][2]==='worship'&&$GLOBALS['sent'][0][3]===array('livestream'),'destination and opt-in audience');
$GLOBALS['status']['status']='unknown';surfside_tools_live_push_check();
$GLOBALS['status']=$live;$GLOBALS['status']['stream_id']='reconnect';surfside_tools_live_push_check();
check(count($GLOBALS['sent'])===1,'unknown/reconnect dedup');
$GLOBALS['options']['surfside_tools_live_push_state']['last_attempt']=$now-7201;
surfside_tools_live_push_check();check(count($GLOBALS['sent'])===2,'later new stream allowed');
$GLOBALS['status']=$live;surfside_tools_live_push_check();check(count($GLOBALS['sent'])===2,'previous stream ID still suppressed');
$GLOBALS['status']['stream_id']='locked';$GLOBALS['options']['surfside_tools_live_push_state']['last_attempt']=0;
$GLOBALS['options']['surfside_tools_live_push_lock']=time();surfside_tools_live_push_check();check(count($GLOBALS['sent'])===2,'concurrent lock');
echo "Livestream notification fixtures passed.\n";
