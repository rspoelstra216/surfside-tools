<?php
/**
 * Phase 3E no-WordPress smoke contract for staff appearance editors.
 * Rendering, existing POST fields and navigation/app-state saving.
 */
define('ABSPATH', __DIR__ . '/');
define('SURFSIDE_TOOLS_VERSION', '3.3.0');
define('SURFSIDE_TOOLS_URL', 'https://surfsidefellowship.org/wp-content/plugins/surfside-tools/');
$GLOBALS['logged_in']=true;
$GLOBALS['can_upload']=true;
$GLOBALS['can_admin']=true;
$GLOBALS['image_items']=array(
    array('id'=>101,'updated'=>1792000000),
    array('id'=>102,'updated'=>1792000001),
    array('id'=>103,'updated'=>1792000002),
);
$GLOBALS['app_settings']=array(
    'home_hero_image_id'=>101,
    'home_hero_focal_x'=>48,
    'home_hero_focal_y'=>53,
    'home_hero_zoom'=>120,
);
$GLOBALS['site_info']=array(
    'identity'=>array('name'=>'Surfside','logo_id'=>0,'tagline'=>'Welcome home.'),
    'location'=>array(),'services'=>array(),'streaming'=>array(),'social'=>array(),
    'navigation'=>array(
        array('key'=>'home','label'=>'Home','type'=>'page','page_id'=>5,'url'=>'','new_tab'=>false),
        array('key'=>'visit','label'=>'Visit Us','type'=>'custom','page_id'=>0,'url'=>'https://example.org/visit','new_tab'=>false),
    ),
);
$_SERVER['REQUEST_METHOD']='GET';
$_POST=array(); $_GET=array();

function apply_filters($name,$value){return $value;}
function add_action(...$args){}
function add_shortcode(...$args){}
function is_user_logged_in(){return $GLOBALS['logged_in'];}
function current_user_can($cap) {return $cap==='upload_files'?$GLOBALS['can_upload']:$GLOBALS['can_admin'];}
function surfside_tools_staff_login_box($m=''){return '<p>Login required</p>';}
function surfside_tools_prevent_cache(){}
function surfside_tools_staff_enqueue_styles(){}
function surfside_tools_staff_page_url($path=''){return 'https://surfsidefellowship.org/dashboard/'.trim($path,'/').'/';}
function surfside_tools_homepage_get_images(){return $GLOBALS['image_items'];}
function surfside_tools_homepage_handle_post($images){return array($images,'');}
function wp_get_attachment_image($id,$size,$icon=false,$attr=array()){
    return '<img src="https://example.org/images/'.(int)$id.'.jpg" alt="" draggable="false">';
}
function wp_get_attachment_image_url($id,$size){return 'https://example.org/images/'.(int)$id.'.jpg';}
function wp_get_attachment_url($id){return 'https://example.org/images/'.(int)$id.'.jpg';}
function wp_basename($s){return basename($s);}
function get_attached_file($id){return '/uploads/'.(int)$id.'.jpg';}
function date_i18n($fmt,$timestamp){return gmdate($fmt,$timestamp);}
function surfside_tools_app_settings(){return $GLOBALS['app_settings'];}
function get_option($key,$fallback=array()){return $key==='surfside_tools_app_settings'?$GLOBALS['app_settings']:$fallback;}
function update_option($key,$v){if($key==='surfside_tools_app_settings')$GLOBALS['app_settings']=$v;return true;}
function surfside_tools_site_information_defaults(){return array('social'=>array());}
function surfside_tools_get_site_information(){return $GLOBALS['site_info'];}
function surfside_tools_update_site_information($data){$GLOBALS['site_info']=$data;return true;}
function surfside_tools_site_information_logo_url($info,$size){return 'https://example.org/logo.png';}
function get_pages($args=array()){return array(
    (object)array('ID'=>5,'post_title'=>'Home'),
    (object)array('ID'=>6,'post_title'=>'Watch Live'),
);}
function shortcode_atts($defaults,$atts,$tag=''){return array_merge($defaults,(array)$atts);}
function sanitize_text_field($value){return trim(strip_tags((string)$value));}
function sanitize_key($value){return strtolower(preg_replace('/[^a-z0-9_-]/','',(string)$value));}
function wp_unslash($value){return $value;}
function absint($value){return abs((int)$value);}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function esc_html($v){return esc_attr($v);}
function esc_url($v){return esc_attr($v);}
function esc_textarea($v){return esc_attr($v);}
function wp_nonce_field($action,$name){
    echo '<input type="hidden" name="'.esc_attr($name).'" value="valid">';
}
function wp_verify_nonce($v,$action){return $v==='valid';}
function disabled($value,$expected=true){if($value==$expected)echo ' disabled="disabled"';}
function selected($a,$b){if($a==$b)echo ' selected="selected"';}
function checked($a,$b=true){if($a==$b)echo ' checked="checked"';}
function wp_register_style(...$args){}
function wp_enqueue_style(...$args){}
function wp_add_inline_style(...$args){}
function wp_register_script(...$args){}
function wp_enqueue_script(...$args){}
function wp_add_inline_script(...$args){}
function wp_enqueue_media(){}
function check($ok,$description){if(!$ok)throw new RuntimeException($description);}

require dirname(__DIR__).'/includes/homepage-manager-compact.php';
require dirname(__DIR__).'/includes/mobile-app-home-manager.php';
require dirname(__DIR__).'/includes/site-information-manager.php';

$photos=surfside_tools_staff_homepage_compact_shortcode();
check(substr_count($photos,'class="surfside-homepage-photo"')===3,
    'Homepage editor renders the existing three images');
preg_match_all('/<button\b[^>]*\bdata-homepage-move-up\b/', $photos, $upButtons);
preg_match_all('/<button\b[^>]*\bdata-homepage-move-down\b/', $photos, $downButtons);
check(count($upButtons[0])===3
    && count($downButtons[0])===3
    && strpos($photos,'Move photo 1 up')!==false
    && strpos($photos,'Move photo 3 down')!==false,
    'Home gallery provides accessible move buttons for every photo');
check(strpos($photos,'data-homepage-order-status')!==false
    && strpos($photos,'Remaining photo slots')!==false
    && strpos($photos,'Save Homepage Photos')!==false,
    'Home photo counts and save boundary are explicit');
check(strpos($photos,'name="surfside_homepage_action" value="save"')!==false
    && strpos($photos,'name="image_order[]"')!==false
    && strpos($photos,'surfside_homepage_nonce')!==false
    && strpos($photos,'name="new_images[]"')!==false
    && strpos($photos,'name="remove_images[]"')!==false
    && strpos($photos,'name="replace_image_101"')!==false,
    'Homepage underlying POST fields and nonce remain unchanged');

$nav=surfside_tools_staff_site_information_shortcode(array('section'=>'navigation'));
check(strpos($nav,'surfside-navigation-workspace')!==false
    && strpos($nav,'data-surfside-nav-count')!==false
    && strpos($nav,'data-surfside-nav-position')!==false
    && strpos($nav,'Link 1')!==false
    && strpos($nav,'Link 2')!==false
    && strpos($nav,'Save Navigation')!==false,
    'Navigation editor has clear link count, order and save instructions');
check(strpos($nav,'name="navigation[0][label]"')!==false
    && strpos($nav,'name="navigation[0][destination]"')!==false
    && strpos($nav,'name="navigation[1][url]"')!==false
    && strpos($nav,'data-surfside-nav-up')!==false
    && strpos($nav,'data-surfside-nav-down')!==false
    && strpos($nav,'data-surfside-nav-remove')!==false
    && strpos($nav,'name="surfside_information_section" value="navigation"')!==false,
    'Navigation actions, fields and section-scoped saving retain established handler');

$_SERVER['REQUEST_METHOD']='POST';
$_POST=array(
    'surfside_information_action'=>'save',
    'surfside_information_section'=>'navigation',
    'surfside_information_nonce'=>'valid',
    'navigation'=>array(
        1=>array('key'=>'visit','label'=>'Visit Us','destination'=>'custom',
                 'url'=>'https://example.org/visit','new_tab'=>'1'),
        0=>array('key'=>'home','label'=>'Home','destination'=>'page:5','url'=>''),
    ),
);
$navSaved=surfside_tools_staff_site_information_shortcode(array('section'=>'navigation'));
check(strpos($navSaved,'Changes saved.')!==false
    && $GLOBALS['site_info']['navigation'][0]['key']==='visit'
    && $GLOBALS['site_info']['navigation'][1]['key']==='home'
    && $GLOBALS['site_info']['navigation'][1]['page_id']===5,
    'Navigation submit preserves the DOM order and original page/custom link semantics');

$_SERVER['REQUEST_METHOD']='GET';$_POST=array();
$home=surfside_tools_staff_mobile_app_home_shortcode();
check(strpos($home,'Saved image')!==false
    && strpos($home,'Custom hero image')!==false
    && strpos($home,'Saved crop')!==false
    && strpos($home,'120% zoom')!==false
    && strpos($home,'Horizontal position 48%')!==false
    && strpos($home,'not updated until')!==false,
    'App Home shows saved values separately from an unsaved editor preview');
check(strpos($home,'name="home_hero_image_id"')!==false
    && strpos($home,'name="home_hero_focal_x"')!==false
    && strpos($home,'name="home_hero_focal_y"')!==false
    && strpos($home,'name="home_hero_zoom"')!==false
    && strpos($home,'surfside_mobile_home_nonce')!==false
    && strpos($home,'id="surfside-mobile-position-reset"')!==false,
    'App Home form fields, media and crop controls are intact');

$_SERVER['REQUEST_METHOD']='POST';
$_POST=array('surfside_mobile_home_nonce'=>'valid',
    'home_hero_image_id'=>'102','home_hero_focal_x'=>'35',
    'home_hero_focal_y'=>'64','home_hero_zoom'=>'160');
$savedHome=surfside_tools_staff_mobile_app_home_shortcode();
check(strpos($savedHome,'Home Experience saved.')!==false
    && $GLOBALS['app_settings']['home_hero_image_id']===102
    && $GLOBALS['app_settings']['home_hero_focal_x']===35
    && $GLOBALS['app_settings']['home_hero_focal_y']===64
    && $GLOBALS['app_settings']['home_hero_zoom']===160,
    'App Home save still updates only the existing image and crop settings');

$GLOBALS['can_upload']=false;
$blocked=surfside_tools_staff_homepage_compact_shortcode();
check(strpos($blocked,'do not have permission')!==false
    && strpos($blocked,'https://example.org/images')===false,
    'Nonstaff may not inspect homepage management images');
$blocked=surfside_tools_staff_mobile_app_home_shortcode();
check(strpos($blocked,'do not have permission')!==false,
    'Nonstaff may not access App Home editor');
$GLOBALS['can_upload']=true;
$GLOBALS['can_admin']=false;
$blocked=surfside_tools_staff_site_information_shortcode(array('section'=>'navigation'));
check(strpos($blocked,'do not have permission')!==false,
    'Website Navigation retains admin-only permission gate');

$css=file_get_contents(dirname(__DIR__).'/assets/css/appearance-workspace.css');
$loader=file_get_contents(dirname(__DIR__).'/includes/staff-workspace-navigation.php');
$homepageSource=file_get_contents(dirname(__DIR__).'/includes/homepage-manager-compact.php');
$navigationSource=file_get_contents(dirname(__DIR__).'/includes/site-information-manager.php');
check(strpos($css,'.surfside-navigation-workspace')!==false
    && strpos($css,'.surfside-homepage-reorder')!==false
    && strpos($css,'.surfside-appearance-app-home')!==false
    && strpos($css,'@media(max-width:460px)')!==false,
    'All three appearance screens share responsive styling');
check(strpos($homepageSource,"grid.addEventListener('click'")!==false
    && strpos($homepageSource,"grid.insertBefore(card,card.previousElementSibling)")!==false
    && strpos($homepageSource,"grid.insertBefore(card.nextElementSibling,card)")!==false
    && strpos($homepageSource,"control.focus()")!==false,
    'Touch and keyboard ordering uses existing gallery DOM and hidden-order form values');
check(strpos($navigationSource,'count.textContent = String(rows.length)')!==false
    && strpos($navigationSource,'position.textContent = "Link " + (index + 1)')!==false,
    'Navigation labels and counts update as links are moved/added/removed');
check(strpos($loader,'dashboard/homepage')!==false
    && strpos($loader,'dashboard/site-navigation')!==false
    && strpos($loader,'dashboard/mobile-app/home-experience')!==false
    && strpos($loader,'surfside_tools_workspace_is_staff_page()')!==false
    && strpos($loader,'surfside_tools_workspace_asset_version($appearance_css)')!==false,
    'Staff-only appearance CSS loads on exactly the intended editors');

echo "PASS: Phase 3E photo ordering, navigation editor, app Home crop and permissions\n";
