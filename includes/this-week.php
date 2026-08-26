<?php
/** This Week curation and mobile-app payload support. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_this_week_hash($text){
    return hash('sha256',wp_strip_all_tags((string)$text,true));
}
function surfside_tools_this_week_selected_hashes(){
    $value=get_option('surfside_tools_this_week_announcements',array());
    return is_array($value)?array_values(array_unique(array_filter(array_map('sanitize_text_field',$value)))):array();
}
function surfside_tools_this_week_selected_announcements(){
    $data=surfside_tools_get_announcements_data();$selected=surfside_tools_this_week_selected_hashes();$items=array();
    foreach((array)($data['items']??array()) as $item){if(in_array(surfside_tools_this_week_hash($item),$selected,true))$items[]=(string)$item;}
    return $items;
}

add_filter('surfside_tools_mobile_api_app',function($payload){
    if(!is_array($payload))return $payload;
    if(!isset($payload['app'])||!is_array($payload['app']))$payload['app']=array();
    $payload['app']['this_week']=array('announcements'=>surfside_tools_this_week_selected_announcements());
    return $payload;
},25);

function surfside_tools_staff_this_week_shortcode(){
    surfside_tools_prevent_cache();surfside_tools_staff_enqueue_styles();
    if(!is_user_logged_in())return surfside_tools_staff_login_box('Please log in to manage This Week.');
    if(!current_user_can('upload_files'))return '<div class="surfside-staff-shell"><p>You do not have permission to manage This Week.</p></div>';
    $notice='';
    if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['surfside_this_week_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_this_week_nonce'])),'surfside_this_week_save')){
        $selected=array_map('sanitize_text_field',(array)($_POST['announcement_hashes']??array()));
        update_option('surfside_tools_this_week_announcements',array_values(array_unique($selected)),false);
        $notice='This Week selections saved.';surfside_tools_purge_cache();
    }
    $data=surfside_tools_get_announcements_data();$items=array_values((array)($data['items']??array()));$selected=surfside_tools_this_week_selected_hashes();ob_start();?>
    <div class="surfside-staff-shell surfside-this-week-manager">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('mobile-app'));?>">← Back to Manage Mobile App</a></div>
      <section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Mobile App</p><h1>This Week</h1><p class="surfside-staff-muted">Choose which current announcements should appear in This Week at Surfside. Services and calendar events are added automatically by the app.</p></section>
      <?php if($notice):?><div class="surfside-this-week-notice"><?php echo esc_html($notice);?></div><?php endif;?>
      <form method="post"><?php wp_nonce_field('surfside_this_week_save','surfside_this_week_nonce');?>
        <section class="surfside-staff-panel"><h2>Weekly Announcements</h2><p class="surfside-staff-muted">Only checked announcements will appear in the app's weekly view. Updating the normal weekly announcements does not automatically publish new items here.</p>
        <?php if(!$items):?><p>No current announcements are available.</p><?php else:?><div class="surfside-this-week-list"><?php foreach($items as $index=>$item):$hash=surfside_tools_this_week_hash($item);?><label class="surfside-this-week-item"><input type="checkbox" name="announcement_hashes[]" value="<?php echo esc_attr($hash);?>" <?php checked(in_array($hash,$selected,true));?>><span><strong>Announcement <?php echo esc_html($index+1);?></strong><span><?php echo esc_html($item);?></span></span></label><?php endforeach;?></div><?php endif;?>
        </section><button type="submit" class="surfside-staff-button surfside-this-week-save">Save This Week</button>
      </form>
    </div>
    <style>.surfside-this-week-list{display:grid;gap:12px;margin-top:18px}.surfside-this-week-item{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border:1px solid #d8dee8;border-radius:12px;background:#fff}.surfside-this-week-item input{margin-top:4px}.surfside-this-week-item>span{display:grid;gap:4px}.surfside-this-week-item>span>span{color:#4b5872;line-height:1.5}.surfside-this-week-save{width:auto;min-width:190px;border:0;cursor:pointer}.surfside-this-week-notice{padding:14px 18px;margin-bottom:20px;border-radius:10px;background:#eaf7ef;color:#126b36;font-weight:700}</style><?php return ob_get_clean();
}
add_shortcode('surfside_staff_this_week','surfside_tools_staff_this_week_shortcode');
function surfside_tools_ensure_this_week_page(){if(!function_exists('surfside_tools_ensure_staff_page'))return;$parent=get_page_by_path('dashboard/mobile-app');if(!$parent)return;surfside_tools_ensure_staff_page('This Week','this-week','[surfside_staff_this_week]',(int)$parent->ID);}add_action('init','surfside_tools_ensure_this_week_page',85);
