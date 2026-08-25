<?php
/** Front-end hub for website content and presentation tools. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_staff_site_management_shortcode() {
    if (function_exists('surfside_tools_prevent_cache')) surfside_tools_prevent_cache();
    if (function_exists('surfside_tools_staff_enqueue_styles')) surfside_tools_staff_enqueue_styles();
    if (!is_user_logged_in()) return function_exists('surfside_tools_staff_login_box') ? surfside_tools_staff_login_box('Please log in to manage the website.') : '<p>Please log in.</p>';
    if (!current_user_can('upload_files')) return '<div class="surfside-staff-shell"><p>You do not have permission to access Site Management.</p></div>';

    $can_configure=current_user_can('manage_options');
    $cards=array();
    if($can_configure){
        $cards[]=array('title'=>'Navigation','description'=>'Header and footer links, destinations, and order.','path'=>'site-navigation','icon'=>'document');
    }
    $cards[]=array('title'=>'Homepage Photos','description'=>'Upload, remove, and reorder carousel photography.','path'=>'homepage','icon'=>'document');

    ob_start(); ?>
    <div class="surfside-staff-shell surfside-site-management">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">← Back to Dashboard</a></div>
      <section class="surfside-staff-hero"><p class="surfside-staff-eyebrow">Website</p><h1>Manage Website</h1><p class="surfside-staff-muted">Update website-specific content and presentation. Shared church information and ministries live in Church Settings.</p></section>
      <div class="surfside-staff-grid"><?php foreach($cards as $card): ?><article class="surfside-staff-card"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon($card['icon']); ?></span><h2><?php echo esc_html($card['title']); ?></h2><p><?php echo esc_html($card['description']); ?></p><div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url($card['path'])); ?>">Open <?php echo esc_html($card['title']); ?> <span class="surfside-staff-arrow">›</span></a></div></article><?php endforeach; ?></div>
    </div><?php return ob_get_clean();
}
add_shortcode('surfside_staff_site_management','surfside_tools_staff_site_management_shortcode');

function surfside_tools_ensure_site_management_pages(){
    if(!function_exists('surfside_tools_ensure_staff_page'))return;
    $dashboard=get_page_by_path('dashboard');if(!$dashboard)return;$parent_id=(int)$dashboard->ID;
    surfside_tools_ensure_staff_page('Site Management','site-management','[surfside_staff_site_management]',$parent_id);
    surfside_tools_ensure_staff_page('Streaming','site-streaming','[surfside_staff_site_information section="streaming"]',$parent_id);
    surfside_tools_ensure_staff_page('Navigation','site-navigation','[surfside_staff_site_information section="navigation"]',$parent_id);
    surfside_tools_ensure_staff_page('Ministries','site-ministries','[surfside_staff_ministries_manager]',$parent_id);
}
add_action('init','surfside_tools_ensure_site_management_pages',75);
