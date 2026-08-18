<?php
/** Shared and infrequently changed configuration hub. */
if (!defined('ABSPATH')) { exit; }
function surfside_tools_staff_site_settings_shortcode(){
 surfside_tools_prevent_cache();surfside_tools_staff_enqueue_styles();if(!is_user_logged_in())return surfside_tools_staff_login_box('Please log in to manage site settings.');if(!current_user_can('manage_options'))return '<div class="surfside-staff-shell"><p>You do not have permission to manage Site Settings.</p></div>';
 $app=function_exists('surfside_tools_app_settings')?surfside_tools_app_settings():array();$giving_url=esc_url($app['giving_url']??'');$saved=false;
 if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['surfside_site_settings_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_site_settings_nonce'])),'surfside_site_settings_save')){$app['giving_url']=esc_url_raw(trim((string)wp_unslash($_POST['giving_url']??'')));update_option('surfside_tools_app_settings',$app);$giving_url=esc_url($app['giving_url']);$saved=true;}
 $cards=array(array('title'=>'Surfside Information','description'=>'Church identity, location, service schedule, contact information, and social links.','path'=>'surfside-information','icon'=>'document'),array('title'=>'Ministries','description'=>'Manage ministry information shared by the website and future mobile app experiences.','path'=>'site-ministries','icon'=>'document'),array('title'=>'Streaming','description'=>'Livestream channels and shared streaming destinations used across Surfside.','path'=>'site-streaming','icon'=>'settings'),array('title'=>'Contact Routing','description'=>'Message recipients and Cloudflare Turnstile protection for website and app contact forms.','path'=>'contact-routing','icon'=>'settings'),array('title'=>'Integrations','description'=>'Google Maps, calendar defaults, Saved Places, and other shared integrations.','path'=>'settings','icon'=>'settings'));
 ob_start();?><div class="surfside-staff-shell surfside-site-settings"><div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">← Back to Dashboard</a></div><section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Administration</p><h1>Site Settings</h1><p class="surfside-staff-muted">Shared and infrequently changed configuration for the Surfside website and mobile app.</p></section><?php if($saved):?><div class="surfside-site-settings-notice">Site settings saved.</div><?php endif;?><div class="surfside-staff-grid"><?php foreach($cards as $card):?><article class="surfside-staff-card"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon($card['icon']);?></span><h2><?php echo esc_html($card['title']);?></h2><p><?php echo esc_html($card['description']);?></p><div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url($card['path']));?>">Open <?php echo esc_html($card['title']);?> <span class="surfside-staff-arrow">›</span></a></div></article><?php endforeach;?></div><form method="post" class="surfside-site-settings-giving"><?php wp_nonce_field('surfside_site_settings_save','surfside_site_settings_nonce');?><section class="surfside-staff-panel"><h2>Giving</h2><p class="surfside-staff-muted">Set the secure giving form used by Surfside. The mobile app loads this destination directly, and the same setting can support website giving.</p><label for="surfside-site-giving-url"><strong>Giving Form URL</strong></label><input id="surfside-site-giving-url" name="giving_url" type="url" value="<?php echo esc_attr($giving_url);?>" placeholder="https://give.tithe.ly/?formId=..."><p class="surfside-staff-muted">Use the direct Tithely Giving Form URL, not the kiosk URL or embed code.</p><button type="submit" class="surfside-staff-button surfside-site-settings-save">Save Site Settings</button></section></form></div><style>.surfside-site-settings-giving{margin-top:26px}.surfside-site-settings-giving label{display:block;margin-top:20px}.surfside-site-settings-giving input[type=url]{display:block;width:100%;max-width:720px;box-sizing:border-box;margin-top:8px;padding:12px 14px}.surfside-site-settings-save{width:auto;min-width:190px;border:0;cursor:pointer;margin-top:20px}.surfside-site-settings-notice{padding:14px 18px;margin-bottom:20px;border-radius:10px;background:#eaf7ef;color:#126b36;font-weight:700}</style><?php return ob_get_clean();}
add_shortcode('surfside_staff_site_settings','surfside_tools_staff_site_settings_shortcode');
function surfside_tools_ensure_site_settings_page(){if(!function_exists('surfside_tools_ensure_staff_page'))return;$dashboard=get_page_by_path('dashboard');if(!$dashboard)return;surfside_tools_ensure_staff_page('Site Settings','site-settings','[surfside_staff_site_settings]',(int)$dashboard->ID);}add_action('init','surfside_tools_ensure_site_settings_page',82);

/**
 * Migrate the plugin-owned Ministries dashboard page from the legacy
 * Site Information editor to the dedicated audience-aware manager.
 *
 * Only pages that still contain the legacy plugin shortcode are changed;
 * custom WordPress page content is intentionally left untouched.
 */
function surfside_tools_migrate_site_ministries_page(){
 $page=get_page_by_path('dashboard/site-ministries');
 if(!$page)return;
 $content=(string)$page->post_content;
 $legacy_shortcodes=array(
  '[surfside_staff_site_information section="ministries"]',
  "[surfside_staff_site_information section='ministries']",
 );
 $is_legacy=false;
 foreach($legacy_shortcodes as $legacy){if(strpos($content,$legacy)!==false){$is_legacy=true;break;}}
 if(!$is_legacy)return;
 wp_update_post(array(
  'ID'=>$page->ID,
  'post_title'=>'Ministries',
  'post_content'=>'[surfside_staff_ministries_manager]',
  'post_status'=>'publish',
 ));
}
add_action('init','surfside_tools_migrate_site_ministries_page',90);

add_filter('do_shortcode_tag',function($output,$tag){if($tag!=='surfside_staff_dashboard'||!is_user_logged_in()||!current_user_can('manage_options'))return $output;$button='<a class="surfside-staff-button-secondary surfside-site-settings-dashboard-action" href="'.esc_url(surfside_tools_staff_page_url('site-settings')).'">Site Settings <span class="surfside-staff-arrow">›</span></a>';$anchor='(<a class="surfside-staff-button-secondary surfside-mobile-dashboard-action"[^>]*>Manage Mobile App <span class="surfside-staff-arrow">›</span></a>)';if(preg_match('~'.$anchor.'~',$output))return preg_replace('~'.$anchor.'~','$1<style>.surfside-site-settings-dashboard-action{display:flex!important;width:100%!important;box-sizing:border-box;align-items:center;justify-content:center;margin-top:14px!important;text-align:center;text-decoration:none!important}</style>'.$button,$output,1);return $output;},20,2);
/* Giving is shared configuration. Remove its visible panel from Manage Mobile App while preserving the value in that form so saving app presentation settings cannot clear it. */
add_filter('do_shortcode_tag',function($output,$tag){if($tag!=='surfside_staff_mobile_app'||!is_user_logged_in())return $output;$settings=function_exists('surfside_tools_app_settings')?surfside_tools_app_settings():array();$giving=esc_attr($settings['giving_url']??'');$replacement='<input type="hidden" name="giving_url" value="'.$giving.'">';return preg_replace('~<section class="surfside-staff-panel"><h2>Giving</h2>.*?</section>~s',$replacement,$output,1);},30,2);
