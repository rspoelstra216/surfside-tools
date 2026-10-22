<?php
/** No-WordPress regression contract for front-end Surfside Tools workspace. */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_TOOLS_URL', 'https://surfsidefellowship.org/wp-content/plugins/surfside-tools/');
define('SURFSIDE_TOOLS_VERSION', '3.3.0');

$GLOBALS['ws_admin'] = true;
$GLOBALS['ws_logged_in'] = true;
$GLOBALS['ws_upload'] = true;
$GLOBALS['ws_options'] = true;
$GLOBALS['ws_tools_admin'] = true;
$GLOBALS['ws_uri'] = 'dashboard';
$GLOBALS['ws_main'] = true;
$GLOBALS['ws_loop'] = true;
$GLOBALS['ws_hooks'] = array();
$GLOBALS['ws_assets'] = array();

function add_action($hook,$fn,$priority=10) { $GLOBALS['ws_hooks'][$hook] = $fn; }
function add_filter($hook,$fn,$priority=10) { $GLOBALS['ws_hooks'][$hook] = $fn; }
function is_admin() { return $GLOBALS['ws_admin']; }
function is_page() { return true; }
function is_user_logged_in() { return $GLOBALS['ws_logged_in']; }
function current_user_can($cap) {
    if ($cap === 'upload_files') return $GLOBALS['ws_upload'];
    if ($cap === 'manage_options') return $GLOBALS['ws_options'];
    return false;
}
function surfside_tools_current_user_is_tools_admin() { return $GLOBALS['ws_tools_admin']; }
function get_queried_object_id() { return 100; }
function get_the_ID() { return 100; }
function get_page_uri($id) { return $GLOBALS['ws_uri']; }
function get_the_title($id) { return 'Legacy Management Screen'; }
function is_main_query() { return $GLOBALS['ws_main']; }
function in_the_loop() { return $GLOBALS['ws_loop']; }
function get_permalink($id=null) { return 'https://surfsidefellowship.org/' . trim($GLOBALS['ws_uri'],'/') . '/'; }
function surfside_tools_staff_page_url($path='') {
    $p='dashboard' . ($path!==''?'/'.trim($path,'/'):'');
    return 'https://surfsidefellowship.org/'.$p.'/';
}
function surfside_tools_youtube_manager_url($args=array()) {
    return add_query_arg(array_merge(array('view'=>'message-archive'),$args),surfside_tools_staff_page_url('site-management'));
}
function surfside_tools_r2_manager_url($args=array()) {
    return add_query_arg(array_merge(array('view'=>'roku-media'),$args),surfside_tools_staff_page_url('site-management'));
}
function add_query_arg($key,$value=null,$url=null) {
    if (is_array($key)) { $args=$key; $url=$value; } else { $args=array($key=>$value); }
    $parts=parse_url((string)$url); $query=array();
    parse_str((string)($parts['query']??''),$query);
    foreach($args as $k=>$v) $query[$k]=$v;
    return ($parts['scheme']??'https').'://'.($parts['host']??'surfsidefellowship.org')
        .($parts['path']??'/').($query?'?'.http_build_query($query):'');
}
function wp_parse_url($url,$part=-1) { return parse_url($url,$part); }
function wp_unslash($v) { return stripslashes($v); }
function sanitize_key($v) { return strtolower(preg_replace('/[^a-z0-9_-]/i','',(string)$v)); }
function esc_url($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_attr($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function wp_enqueue_style($name,$url,$deps,$ver) { $GLOBALS['ws_assets'][]=$name; }
function wp_enqueue_script($name,$url,$deps,$ver,$footer) { $GLOBALS['ws_assets'][]=$name; }
function check($assertion,$message) { if (!$assertion) throw new RuntimeException($message); }

require dirname(__DIR__) . '/includes/staff-workspace-navigation.php';
check(isset($GLOBALS['ws_hooks']['the_content']) && isset($GLOBALS['ws_hooks']['wp_enqueue_scripts']),
    'Registers scoped staff content and asset hooks');
$GLOBALS['ws_admin']=false;
check(surfside_tools_workspace_is_staff_page(),'Live authenticated staff dashboard detected');
$groups=surfside_tools_workspace_catalog();
check(array_keys($groups)===array('content','media','events','community','appearance','settings'),
    'Six task-based management sections');
$labels=array();
foreach($groups as $section)foreach($section['items'] as $item)$labels[$item['label']]=$item['url'];
foreach(array('Weekly Update','Archived Sermon Notes','Message Archive','Sermon Series',
    'Roku MP4 Uploads','Calendar Manager','Volunteer Needs','Prayer Requests',
    'Push Notifications','Website Navigation','YouVersion Integration','Staff Access') as $label) {
    check(isset($labels[$label]),'Search/navigation indexes '.$label);
}
check(str_contains($labels['Roku MP4 Uploads'],'/dashboard/site-management/?view=roku-media'),
    'Roku copies preserves the established ?view= route');
check(str_contains($labels['Prayer Requests'],'view=member-engagement')
    && str_contains($labels['Prayer Requests'],'tool=prayer-requests'),
    'Prayer review preserves deep links from existing email');
check(str_contains($labels['YouVersion Integration'],'#surfside-focus-youversion'),
    'Integrations search uses a focused accordion deep link');
$wrapped=surfside_tools_workspace_wrap_content('<p>Existing editor remains unchanged</p>');
check(str_contains($wrapped,'data-surfside-workspace')
    && str_contains($wrapped,'Find a tool')
    && str_contains($wrapped,'Existing editor remains unchanged')
    && str_contains($wrapped,'aria-label="Breadcrumb"'),
    'Content shell preserves original rendered editor and provides search/breadcrumb');
check(substr_count($wrapped,'data-ss-group')===6,'All permitted navigation groups rendered');
check(!str_contains($wrapped,'<form method="post"'),
    'Navigation wrapper creates no new write actions');
surfside_tools_workspace_enqueue_assets();
check($GLOBALS['ws_assets']===array('surfside-workspace','surfside-workspace'),
    'Dashboard receives scoped style and JS only');

$_GET=array('view'=>'roku-media');
$GLOBALS['ws_uri']='dashboard/site-management';
$active=surfside_tools_workspace_current($groups);
check($active['label']==='Roku MP4 Uploads' && $active['group']==='Messages & Media',
    'Roku ?view= route gets the correct breadcrumb');
$_GET=array('view'=>'message-archive','tab'=>'series');
$active=surfside_tools_workspace_current($groups);
check($active['label']==='Sermon Series','Nested archive tab gets specific breadcrumb');
$GLOBALS['ws_uri']='dashboard';
$_GET=array('view'=>'member-engagement','tool'=>'prayer-requests','section'=>'pending');
$active=surfside_tools_workspace_current($groups);
check($active['label']==='Prayer Requests','Review email route retains a proper breadcrumb');

$GLOBALS['ws_options']=false;
$GLOBALS['ws_tools_admin']=false;
$staff=surfside_tools_workspace_catalog();
$staff_labels=array();
foreach($staff as $section)foreach($section['items'] as $item)$staff_labels[]=$item['label'];
foreach(array('Integrations','Staff Access','Streaming Management','Contact Routing',
    'YouTube Integration','Saved Places','Ministries') as $hidden) {
    check(!in_array($hidden,$staff_labels,true),'Privileged tool hidden from staff search: '.$hidden);
}
check(in_array('Prayer Requests',$staff_labels,true)&&in_array('Roku MP4 Uploads',$staff_labels,true),
    'Staff retain ordinary tools');
$GLOBALS['ws_upload']=false;
check(!surfside_tools_workspace_is_staff_page(),'No workspace access without upload_files capability');
check(surfside_tools_workspace_wrap_content('Original')==='Original',
    'Non-staff view not wrapped');
$GLOBALS['ws_upload']=true;
$GLOBALS['ws_admin']=true;
check(!surfside_tools_workspace_is_staff_page(),'WordPress admin menu not affected');
$GLOBALS['ws_admin']=false;
$GLOBALS['ws_uri']='contact';
check(!surfside_tools_workspace_is_staff_page(),'Public church website does not show staff workspace');
$GLOBALS['ws_uri']='dashboard';
$GLOBALS['ws_main']=false;
check(surfside_tools_workspace_wrap_content('Original')==='Original',
    'Secondary content queries are not wrapped');
$GLOBALS['ws_main']=true;
$GLOBALS['ws_loop']=false;
check(surfside_tools_workspace_wrap_content('Original')==='Original',
    'Only the main post loop is wrapped');

echo "Staff workspace routing, search, permissions and scope checks passed.\n";
