<?php
/** Dedicated Home Experience editor reached from the Manage Mobile App hub. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_staff_mobile_app_home_shortcode(){
    if(!is_user_logged_in()) return surfside_tools_staff_login_box('Please log in to manage the mobile app.');
    if(!current_user_can('upload_files')) return '<div class="surfside-staff-shell"><p>You do not have permission to manage the mobile app.</p></div>';
    /* Reuse the established editor, but present it as the nested Home Experience screen. */
    $output=surfside_tools_staff_mobile_app_shortcode();
    $output=str_replace(surfside_tools_staff_page_url(''),surfside_tools_staff_page_url('mobile-app'),$output);
    $output=str_replace('<p class="surfside-staff-eyebrow">Mobile App</p><h1>Manage Mobile App</h1><p class="surfside-staff-muted">Control app-specific presentation now, with notifications and additional mobile tools added here as the app grows.</p>','<p class="surfside-staff-eyebrow">Mobile App</p><h1>Home Experience</h1><p class="surfside-staff-muted">Manage the image and crop used on the app Home screen.</p>',$output);
    $output=preg_replace('~<section class="surfside-staff-panel surfside-mobile-future">.*?</section>~s','',$output,1);
    return $output;
}
add_shortcode('surfside_staff_mobile_app_home','surfside_tools_staff_mobile_app_home_shortcode');
function surfside_tools_ensure_mobile_app_home_page(){if(!function_exists('surfside_tools_ensure_staff_page'))return;$parent=get_page_by_path('dashboard/mobile-app');if(!$parent)$parent=get_page_by_path('mobile-app');if(!$parent)return;surfside_tools_ensure_staff_page('Home Experience','home-experience','[surfside_staff_mobile_app_home]',(int)$parent->ID);}add_action('init','surfside_tools_ensure_mobile_app_home_page',83);
