<?php
/** Featured Home announcement scheduling for the Surfside mobile app. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_app_featured_announcement() {
    $settings = function_exists('surfside_tools_app_settings') ? surfside_tools_app_settings() : array();
    $enabled = !empty($settings['featured_enabled']);
    $headline = sanitize_text_field((string)($settings['featured_headline'] ?? ''));
    $message = sanitize_textarea_field((string)($settings['featured_message'] ?? ''));
    $button_label = sanitize_text_field((string)($settings['featured_button_label'] ?? ''));
    $button_url = esc_url_raw((string)($settings['featured_button_url'] ?? ''));
    $starts_at = sanitize_text_field((string)($settings['featured_starts_at'] ?? ''));
    $ends_at = sanitize_text_field((string)($settings['featured_ends_at'] ?? ''));
    $timezone = wp_timezone();
    $now = current_datetime();
    $start = $starts_at ? DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $starts_at, $timezone) : null;
    $end = $ends_at ? DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $ends_at, $timezone) : null;
    $active = $enabled && $headline !== '' && $end && (!$start || $now >= $start) && $now <= $end;
    return array('enabled'=>$enabled,'active'=>(bool)$active,'headline'=>$headline,'message'=>$message,'button_label'=>$button_label,'button_url'=>$button_url,'starts_at'=>$start?$start->format(DATE_ATOM):'','ends_at'=>$end?$end->format(DATE_ATOM):'');
}

function surfside_tools_featured_announcement_valid_local_datetime($value) {
    if ($value === '') return '';
    return preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}$/', $value) ? $value : '';
}

add_filter('surfside_tools_mobile_api_app', function ($payload) {
    if (!is_array($payload)) return $payload;
    $featured = surfside_tools_app_featured_announcement();
    if (!isset($payload['app']) || !is_array($payload['app'])) $payload['app'] = array();
    $payload['app']['featured_announcement'] = $featured['active'] ? array('headline'=>$featured['headline'],'message'=>$featured['message'],'button_label'=>$featured['button_label'],'button_url'=>$featured['button_url'],'starts_at'=>$featured['starts_at'],'ends_at'=>$featured['ends_at']) : null;
    return $payload;
}, 20);

function surfside_tools_staff_featured_announcement_shortcode(){
    surfside_tools_prevent_cache();surfside_tools_staff_enqueue_styles();
    if(!is_user_logged_in())return surfside_tools_staff_login_box('Please log in to manage the mobile app.');
    if(!current_user_can('upload_files'))return '<div class="surfside-staff-shell"><p>You do not have permission to manage the mobile app.</p></div>';
    $saved=false;$validation_error='';
    if(($_SERVER['REQUEST_METHOD']??'')==='POST'&&isset($_POST['surfside_featured_home_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_featured_home_nonce'])),'surfside_featured_home_save')){
        $settings=function_exists('surfside_tools_app_settings')?surfside_tools_app_settings():array();
        $starts_at=surfside_tools_featured_announcement_valid_local_datetime(sanitize_text_field(wp_unslash($_POST['featured_starts_at']??'')));
        $ends_at=surfside_tools_featured_announcement_valid_local_datetime(sanitize_text_field(wp_unslash($_POST['featured_ends_at']??'')));
        $enabled_requested=!empty($_POST['featured_enabled']);
        if($enabled_requested&&$ends_at===''){$validation_error='Run Until is required when the featured announcement is enabled.';}else{
            $settings['featured_enabled']=$enabled_requested?1:0;
            $settings['featured_headline']=sanitize_text_field(wp_unslash($_POST['featured_headline']??''));
            $settings['featured_message']=sanitize_textarea_field(wp_unslash($_POST['featured_message']??''));
            $settings['featured_button_label']=sanitize_text_field(wp_unslash($_POST['featured_button_label']??''));
            $settings['featured_button_url']=esc_url_raw(trim((string)wp_unslash($_POST['featured_button_url']??'')));
            $settings['featured_starts_at']=$starts_at;$settings['featured_ends_at']=$ends_at;update_option('surfside_tools_app_settings',$settings);$saved=true;
        }
    }
    $settings=function_exists('surfside_tools_app_settings')?surfside_tools_app_settings():array();$featured=surfside_tools_app_featured_announcement();$enabled=!empty($settings['featured_enabled']);$status='Not enabled';if($enabled)$status=$featured['active']?'Currently showing':'Scheduled or expired';
    ob_start();?><div class="surfside-staff-shell surfside-mobile-app-management"><div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('mobile-app'));?>">← Back to Manage Mobile App</a></div><section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Mobile App</p><h1>Featured Announcement</h1><p class="surfside-staff-muted">Schedule temporary featured content for the app Home screen. While active, it replaces the normal Coming Up card.</p></section><?php if($saved):?><div class="surfside-mobile-notice">Featured Announcement saved.</div><?php endif;?><?php if($validation_error):?><div class="surfside-mobile-error"><?php echo esc_html($validation_error);?></div><?php endif;?><form method="post"><?php wp_nonce_field('surfside_featured_home_save','surfside_featured_home_nonce');?><section class="surfside-staff-panel"><div class="surfside-featured-home-heading"><div><h2>Home Feature</h2><p class="surfside-staff-muted">Use this for VBS, baptisms, special services, ministry opportunities, or other timely information.</p></div><span class="surfside-featured-home-status"><?php echo esc_html($status);?></span></div><label class="surfside-featured-home-toggle"><input type="checkbox" name="featured_enabled" value="1" <?php checked($enabled);?>> <strong>Enable featured announcement</strong></label><div class="surfside-featured-home-grid"><label class="surfside-featured-home-field surfside-featured-home-wide"><span>Headline</span><input type="text" name="featured_headline" value="<?php echo esc_attr($settings['featured_headline']??'');?>" maxlength="90" placeholder="Beach Baptism"></label><label class="surfside-featured-home-field surfside-featured-home-wide"><span>Short message</span><textarea name="featured_message" rows="3" maxlength="240" placeholder="Join us Sunday evening at Cherie Down Park."><?php echo esc_textarea($settings['featured_message']??'');?></textarea></label><label class="surfside-featured-home-field"><span>Start showing</span><input type="datetime-local" name="featured_starts_at" value="<?php echo esc_attr($settings['featured_starts_at']??'');?>"><small>Optional. Leave blank to begin immediately.</small></label><label class="surfside-featured-home-field"><span>Run until</span><input type="datetime-local" name="featured_ends_at" value="<?php echo esc_attr($settings['featured_ends_at']??'');?>" data-featured-run-until><small>Required when enabled.</small></label><label class="surfside-featured-home-field"><span>Button label</span><input type="text" name="featured_button_label" value="<?php echo esc_attr($settings['featured_button_label']??'');?>" maxlength="30" placeholder="Learn More"><small>Optional.</small></label><label class="surfside-featured-home-field"><span>Button link</span><input type="url" name="featured_button_url" value="<?php echo esc_attr($settings['featured_button_url']??'');?>" placeholder="https://..."><small>Optional.</small></label></div></section><button type="submit" class="surfside-staff-button surfside-mobile-save">Save Featured Announcement</button></form></div><style>.surfside-featured-home-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:20px}.surfside-featured-home-heading h2{margin-bottom:6px}.surfside-featured-home-heading p{margin:0}.surfside-featured-home-status{flex:0 0 auto;padding:5px 10px;border:1px solid #ccd7df;border-radius:999px;background:#f7f9fb;color:#61717d;font-size:.8rem;font-weight:800}.surfside-featured-home-toggle{display:block;margin:20px 0;font-size:1rem}.surfside-featured-home-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.surfside-featured-home-field{display:grid;gap:7px}.surfside-featured-home-wide{grid-column:1/-1}.surfside-featured-home-field>span{font-weight:800;color:#26323d}.surfside-featured-home-field input,.surfside-featured-home-field textarea{box-sizing:border-box;width:100%;padding:10px 12px;border:1px solid #aeb9c4;border-radius:9px;background:#fff;color:#26323d;font:inherit}.surfside-featured-home-field textarea{resize:vertical}.surfside-featured-home-field small{color:#687480;font-size:.85rem;line-height:1.4}.surfside-mobile-save{width:auto;min-width:220px;border:0;cursor:pointer}.surfside-mobile-notice,.surfside-mobile-error{padding:14px 18px;margin-bottom:20px;border-radius:10px;font-weight:700}.surfside-mobile-notice{background:#eaf7ef;color:#126b36}.surfside-mobile-error{background:#fff0f0;color:#a12b2b}@media(max-width:720px){.surfside-featured-home-heading{display:block}.surfside-featured-home-status{display:inline-block;margin-top:10px}.surfside-featured-home-grid{grid-template-columns:1fr}.surfside-featured-home-wide{grid-column:auto}}</style><script>document.addEventListener('DOMContentLoaded',function(){const toggle=document.querySelector('input[name="featured_enabled"]');const until=document.querySelector('[data-featured-run-until]');if(!toggle||!until)return;const sync=()=>{until.required=toggle.checked;};toggle.addEventListener('change',sync);sync();});</script><?php return ob_get_clean();
}
add_shortcode('surfside_staff_featured_announcement','surfside_tools_staff_featured_announcement_shortcode');
function surfside_tools_ensure_featured_announcement_page(){if(!function_exists('surfside_tools_ensure_staff_page'))return;$parent=get_page_by_path('dashboard/mobile-app');if(!$parent)$parent=get_page_by_path('mobile-app');if(!$parent)return;surfside_tools_ensure_staff_page('Featured Announcement','featured-announcement','[surfside_staff_featured_announcement]',(int)$parent->ID);}add_action('init','surfside_tools_ensure_featured_announcement_page',84);
