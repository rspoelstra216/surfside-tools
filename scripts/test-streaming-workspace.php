<?php
/** Streaming workspace routing, access, rendering and old-handler safety. */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_TOOLS_URL', 'https://surfsidefellowship.org/wp-content/plugins/surfside-tools/');
define('SURFSIDE_TOOLS_VERSION', '3.3.0');
$GLOBALS['allowed'] = true;
$GLOBALS['logged'] = true;
$GLOBALS['watch_attrs'] = null;
$GLOBALS['expanded'] = false;
$GLOBALS['hooks'] = array();
function add_action($name,$fn,$priority=10){$GLOBALS['hooks'][$name]=$fn;}
function is_user_logged_in(){return $GLOBALS['logged'];}
function current_user_can($cap){return $cap === 'manage_options' && $GLOBALS['allowed'];}
function is_page(){return true;}
function surfside_tools_prevent_cache(){}
function surfside_tools_staff_enqueue_styles(){}
function surfside_tools_staff_page_url($path=''){
    return 'https://surfsidefellowship.org/dashboard/'.trim($path,'/').'/';
}
function add_query_arg($k,$v,$url){return $url.(strpos($url,'?')===false?'?':'&').urlencode($k).'='.urlencode($v);}
function wp_unslash($v){return stripslashes($v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function esc_url($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function surfside_tools_staff_site_information_shortcode($attrs=array()){
    $GLOBALS['watch_attrs']=$attrs;
    return '<form method="post" data-watch-form="true"><input name="surfside_information_section" value="streaming"></form>';
}
function surfside_tools_twitch_settings_handle_post(){return '<p role="status">Existing Twitch POST handler invoked</p>';}
function surfside_tools_twitch_settings_panel($expanded=false){
    $GLOBALS['expanded']=$expanded;
    return '<details open><form method="post"><button>Save Twitch Settings</button></form></details>';
}
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}

require dirname(__DIR__).'/includes/streaming-workspace.php';
check(surfside_tools_streaming_workspace_tab()==='cloudflare','Cloudflare is default tab');
check(surfside_tools_streaming_workspace_url()==='https://surfsidefellowship.org/dashboard/site-settings/streaming/',
    'Existing Cloudflare management URL unchanged');
foreach(array('watch','twitch') as $name){
    $_GET['stream_tab']=$name;
    check(surfside_tools_streaming_workspace_tab()===$name,'Expected tab '.$name);
    $tabs=surfside_tools_streaming_workspace_tabs();
    check(substr_count($tabs,'aria-current="page"')===1,'Exactly one selected tab');
    check(strpos($tabs,'stream_tab='.$name)!==false,'Selected tab URL is stable');
}
$_GET['stream_tab']='unknown';
check(surfside_tools_streaming_workspace_tab()==='cloudflare','Invalid tab defaults to Cloudflare');
$_GET['stream_tab']='"><script>alert(1)</script>';
check(surfside_tools_streaming_workspace_tab()==='cloudflare','Malformed tab is rejected');

$_GET['stream_tab']='watch';
$watch=surfside_tools_streaming_workspace_secondary();
check(strpos($watch,'Streaming')!==false && strpos($watch,'data-watch-form="true"')!==false,
    'Watch Live editor is rendered inside unified workspace');
check($GLOBALS['watch_attrs']===array('section'=>'streaming','embedded'=>true),
    'Existing streaming-only site information form and save handler reused');
$_GET['stream_tab']='twitch';
$twitch=surfside_tools_streaming_workspace_secondary();
check($GLOBALS['expanded']===true,'Twitch form is expanded for first-time discoverability');
check(strpos($twitch,'Save Twitch Settings')!==false
    && strpos($twitch,'Existing Twitch POST handler invoked')!==false,
    'Existing Twitch save/test controls are reused');
check(strpos($twitch,'cloudflare')!==false,'Every view keeps cloudflare navigation');
$GLOBALS['allowed']=false;
check(strpos(surfside_tools_streaming_workspace_secondary(),'permission')!==false,
    'Non-admin user cannot open editors');
$GLOBALS['allowed']=true;
$GLOBALS['logged']=false;
check(strpos(surfside_tools_streaming_workspace_secondary(),'permission')!==false,
    'Unauthenticated visitor cannot open editors');

$source=file_get_contents(dirname(__DIR__).'/includes/cloudflare-stream.php');
check(strpos($source,"surfside_tools_streaming_workspace_secondary()")!==false,
    'Cloudflare shortcode forwards secondary tabs to shared editor');
check(strpos($source,"surfside_tools_stream_workspace")===false,
    'No second Cloudflare save implementation');
foreach(array("surfside_stream_action", "'exclude'", "'webhook_secret'", "'token'", "'refresh'") as $item){
    check(strpos($source,$item)!==false,'Existing Cloudflare action preserved: '.$item);
}
$info=file_get_contents(dirname(__DIR__).'/includes/site-information-manager.php');
check(strpos($info,"'embedded' => false")!==false
    && strpos($info,"if (!\$embedded)")!==false
    && strpos($info,"surfside_information_section")!==false,
    'Watch Live editor can be embedded without breaking original page');
$twitch_source=file_get_contents(dirname(__DIR__).'/includes/twitch-settings.php');
check(strpos($twitch_source,'Save Twitch Settings')!==false
    && strpos($twitch_source,"'surfside_twitch_settings'")!==false,
    'Twitch form has an explicit save button with its original nonce');

echo "PASS: Streaming workspace routing, tabs, reuse of existing handlers, and permissions\n";
