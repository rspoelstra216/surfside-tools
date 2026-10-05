<?php
define('ABSPATH', __DIR__);
class WP_REST_Server { const READABLE = 'GET'; }
function add_action($name, $callback) {}
$cache = array(); $options = array(); $responses = array(); $calls = array();
function get_transient($key) { global $cache; return $cache[$key] ?? false; }
function set_transient($key,$value,$ttl) { global $cache; $cache[$key]=$value; }
function delete_transient($key) { global $cache; unset($cache[$key]); }
function get_option($key,$default=false) { global $options; return $options[$key] ?? $default; }
function add_option($key,$value,$deprecated='',$autoload=false) { global $options; if(isset($options[$key]))return false; $options[$key]=$value; return true; }
function update_option($key,$value,$autoload=false) { global $options; $options[$key]=$value; }
function delete_option($key) { global $options; unset($options[$key]); }
function surfside_tools_get_site_information() { return array('streaming'=>array('twitch_channel'=>'surfsidecf')); }
function is_wp_error($value) { return $value instanceof Exception; }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return json_encode($r['body']); }
function request_mock($url) { global $responses,$calls; $calls[]=$url; if(!$responses)throw new Exception('Unexpected request'); return array_shift($responses); }
function wp_remote_get($url,$args) { return request_mock($url); }
function wp_remote_post($url,$args) { return request_mock($url); }
require __DIR__.'/../includes/twitch-settings.php';
require __DIR__.'/../includes/twitch-live-status.php';
function check($condition,$message) { if(!$condition)throw new Exception($message); }
function reset_case($next) { global $cache,$options,$responses,$calls; $cache=array();$options=array();$responses=$next;$calls=array(); }
check(surfside_tools_twitch_credentials()===array('client_id'=>'','secret'=>''),'Missing dashboard credentials');
check(surfside_tools_twitch_save_credentials('dashboardid','dashboardsecret',false),'Save credentials');
check(surfside_tools_twitch_credentials()['secret']==='dashboardsecret','Dashboard fallback');
check(surfside_tools_twitch_save_credentials('dashboardid','',false),'Blank secret save');
check(surfside_tools_twitch_credentials()['secret']==='dashboardsecret','Blank preserves secret');
check(!surfside_tools_twitch_save_credentials('bad id','replacement',false),'Reject invalid ID');
check(surfside_tools_twitch_credentials()['secret']==='dashboardsecret','Invalid input writes nothing');
check(surfside_tools_twitch_save_credentials('dashboardid','',true),'Clear secret');
check(surfside_tools_twitch_credentials()['secret']==='','Secret removed');
define('SURFSIDE_TWITCH_CLIENT_ID', 'testclient');
define('SURFSIDE_TWITCH_CLIENT_SECRET', 'testsecret');
check(surfside_tools_twitch_credentials()===array('client_id'=>'testclient','secret'=>'testsecret'),'Constants override options');
check(surfside_tools_twitch_save_credentials('ignored','ignored',true),'Locked save');
check(surfside_tools_twitch_credentials()['secret']==='testsecret','Constants preserved');
$token=array('code'=>200,'body'=>array('access_token'=>'testtoken','expires_in'=>3600));
$valid=array('code'=>200,'body'=>array('client_id'=>'testclient','expires_in'=>3600));
$live=array('code'=>200,'body'=>array('data'=>array(array('user_login'=>'surfsidecf','type'=>'live','id'=>'123','started_at'=>'2026-10-01T12:00:00Z'))));
$offline=array('code'=>200,'body'=>array('data'=>array()));
reset_case(array($token,$valid,$live));
check(surfside_tools_twitch_live_status()['is_live']===true,'Live response');
check(count($calls)===3,'Token validation and status request');
check(surfside_tools_twitch_live_status()['stream_id']==='123' && count($calls)===3,'Shared status cache');
check(!$options,'Lock released');
reset_case(array($token,$valid,$offline));
check(surfside_tools_twitch_live_status()['is_live']===false,'Confirmed offline');
reset_case(array($token,$valid,array('code'=>429,'body'=>array())));
check(surfside_tools_twitch_live_status()['is_live']===null,'Rate limit is unknown');
reset_case(array(new Exception('Network failure')));
check(surfside_tools_twitch_live_status()['status']==='unknown','Network failure is unknown');
check(!$options,'Failure releases lock');
check(count($calls)===1,'Only one failed request');
surfside_tools_twitch_live_status();
check(count($calls)===1,'Failure backoff');
reset_case(array($token,array('code'=>200,'body'=>array('client_id'=>'other','expires_in'=>3600))));
check(surfside_tools_twitch_live_status()['status']==='unknown','Reject mismatched token client');
reset_case(array($token,$valid,array('code'=>401,'body'=>array()),$token,$valid,$live));
check(surfside_tools_twitch_live_status()['status']==='live','One token renewal retry');
check(count($calls)===6,'Bounded retry');
check(surfside_tools_twitch_parse_streams(array(),'surfsidecf')===null,'Malformed response');
check(surfside_tools_twitch_parse_streams(array('data'=>array(array('user_login'=>'other','type'=>'live'))),'surfsidecf')===null,'Unrelated channel');
check(surfside_tools_twitch_parse_streams(array('data'=>array(array('user_login'=>'surfsidecf','type'=>'live','id'=>'123','started_at'=>'bad'))),'surfsidecf')===null,'Malformed live timestamp');
reset_case(array(array('code'=>401,'body'=>array('message'=>'testsecret testtoken'))));
$diagnostic = '';
$result = surfside_tools_twitch_live_status($diagnostic);
check(strpos($diagnostic,'Token request')!==false && strpos($diagnostic,'HTTP 401')!==false,'Identify auth failure stage');
check(strpos($diagnostic,'testsecret')===false && strpos($diagnostic,'testtoken')===false,'No upstream secrets in diagnostics');
check(!array_key_exists('diagnostic',$result),'Public status excludes diagnostics');
$previous = $diagnostic;
surfside_tools_twitch_live_status($diagnostic);
check($diagnostic===$previous && count($calls)===1,'Cached failure retains reason without extra requests');
reset_case(array($token,$valid,array('code'=>429,'body'=>array())));
surfside_tools_twitch_live_status($diagnostic);
check(strpos($diagnostic,'Stream lookup')!==false && strpos($diagnostic,'HTTP 429')!==false,'Identify stream rate limit');
reset_case(array(new Exception('testsecret')));
surfside_tools_twitch_live_status($diagnostic);
check(strpos($diagnostic,'could not reach Twitch')!==false && strpos($diagnostic,'testsecret')===false,'Safe transport diagnostic');
reset_case(array($token,$valid,$offline));
surfside_tools_twitch_live_status($diagnostic);
check($diagnostic==='','Success clears diagnostics');
echo "Twitch status tests passed.\\n";
