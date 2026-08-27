<?php
/** Front-end hub for member-facing church engagement tools and dashboard navigation. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_member_engagement_url($tool = '') {
    $url = add_query_arg('view', 'member-engagement', surfside_tools_staff_page_url(''));
    return $tool !== '' ? add_query_arg('tool', sanitize_key($tool), $url) : $url;
}

function surfside_tools_staff_member_engagement_view() {
    if (function_exists('surfside_tools_prevent_cache')) surfside_tools_prevent_cache();
    if (function_exists('surfside_tools_staff_enqueue_styles')) surfside_tools_staff_enqueue_styles();
    if (!is_user_logged_in()) return function_exists('surfside_tools_staff_login_box') ? surfside_tools_staff_login_box('Please log in to manage member engagement.') : '<p>Please log in.</p>';
    if (!current_user_can('upload_files')) return '<div class="surfside-staff-shell"><p>You do not have permission to manage member engagement.</p></div>';

    $tool = isset($_GET['tool']) ? sanitize_key(wp_unslash($_GET['tool'])) : '';
    if ($tool === 'prayer-requests' && function_exists('surfside_tools_prayer_list_manager_panel')) {
        return '<div class="surfside-staff-shell">' . surfside_tools_prayer_list_manager_panel() . '</div>';
    }
    if ($tool === 'volunteer-needs' && function_exists('surfside_tools_staff_volunteer_needs_view')) {
        $html = surfside_tools_staff_volunteer_needs_view();
        $back = '<div class="surfside-staff-back"><a href="' . esc_url(surfside_tools_member_engagement_url()) . '">← Back to Member Engagement</a></div>';
        $html = preg_replace('/<div class="surfside-staff-back"><a href="[^"]+">← Back to Manage Mobile App<\/a><\/div>/', $back, $html, 1);
        $html = str_replace('<p class="surfside-staff-eyebrow">Mobile App</p>', '<p class="surfside-staff-eyebrow">Member Engagement</p>', $html);
        $html = str_replace('Publish timely serving opportunities in the Surfside app.', 'Publish timely serving opportunities for the Surfside church community.', $html);
        return $html;
    }

    $pending = function_exists('surfside_tools_prayer_list_pending_count') ? surfside_tools_prayer_list_pending_count() : 0;
    $active = function_exists('surfside_tools_prayer_list_active_count') ? surfside_tools_prayer_list_active_count() : 0;
    $needs = function_exists('surfside_tools_get_volunteer_needs') ? array_filter(surfside_tools_get_volunteer_needs(), function($need){ return !empty($need['active']); }) : array();

    ob_start(); ?>
    <div class="surfside-staff-shell surfside-member-engagement">
      <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">← Back to Dashboard</a></div>
      <section class="surfside-staff-hero">
        <p class="surfside-staff-eyebrow">Member Engagement</p>
        <h1>Member Engagement</h1>
        <p class="surfside-staff-muted">Manage ways Surfside members can pray, serve, and respond to current church needs.</p>
      </section>
      <div class="surfside-staff-grid">
        <article class="surfside-staff-card">
          <span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('document'); ?></span>
          <h2>Prayer Requests</h2>
          <p><?php echo esc_html($pending); ?> pending review · <?php echo esc_html($active); ?> active on the Church Prayer List.</p>
          <div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_member_engagement_url('prayer-requests')); ?>">Manage Prayer Requests <span class="surfside-staff-arrow">›</span></a></div>
        </article>
        <article class="surfside-staff-card">
          <span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('settings'); ?></span>
          <h2>Volunteer Needs</h2>
          <p><?php echo esc_html(count($needs)); ?> active serving opportunit<?php echo count($needs) === 1 ? 'y' : 'ies'; ?> currently published.</p>
          <div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_member_engagement_url('volunteer-needs')); ?>">Manage Volunteer Needs <span class="surfside-staff-arrow">›</span></a></div>
        </article>
      </div>
    </div>
    <?php return ob_get_clean();
}

function surfside_tools_dashboard_management_areas() {
    $cards = array(
        array('title'=>'Weekly Update','description'=>'Announcements, sermon notes, and weekly publishing.','url'=>surfside_tools_staff_page_url('weekly-update'),'icon'=>'upload'),
        array('title'=>'Calendar','description'=>'Church events, recurring schedules, and calendar details.','url'=>surfside_tools_staff_page_url('calendar'),'icon'=>'calendar'),
        array('title'=>'Member Engagement','description'=>'Prayer requests and current volunteer needs.','url'=>surfside_tools_member_engagement_url(),'icon'=>'document'),
        array('title'=>'Mobile App','description'=>'Home experience, featured content, and push notifications.','url'=>surfside_tools_staff_page_url('mobile-app'),'icon'=>'settings'),
        array('title'=>'Website','description'=>'Website-specific content, homepage presentation, and navigation.','url'=>surfside_tools_staff_page_url('site-management'),'icon'=>'document'),
        array('title'=>'Settings','description'=>'Church information, integrations, saved places, and preferences.','url'=>surfside_tools_staff_page_url('settings'),'icon'=>'settings'),
    );
    ob_start(); ?>
    <section class="surfside-dashboard-quick-actions surfside-dashboard-management-areas">
      <h2 class="surfside-dashboard-section-title">Management Areas</h2>
      <div class="surfside-staff-grid">
        <?php foreach ($cards as $card): ?>
          <article class="surfside-staff-card">
            <span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon($card['icon']); ?></span>
            <h2><?php echo esc_html($card['title']); ?></h2>
            <p><?php echo esc_html($card['description']); ?></p>
            <div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url($card['url']); ?>">Open <?php echo esc_html($card['title']); ?> <span class="surfside-staff-arrow">›</span></a></div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
    <style>.surfside-dashboard-management-areas .surfside-staff-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.surfside-dashboard-management-areas .surfside-staff-card{min-height:230px}@media(max-width:1000px){.surfside-dashboard-management-areas .surfside-staff-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.surfside-dashboard-management-areas .surfside-staff-grid{grid-template-columns:1fr}}</style>
    <?php return ob_get_clean();
}

add_filter('do_shortcode_tag', function($output, $tag) {
    if ($tag !== 'surfside_staff_dashboard') return $output;
    $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
    if ($view === 'member-engagement') return surfside_tools_staff_member_engagement_view();
    if ($view !== '') return $output;

    $output = str_replace('Tools and current website information in one place.', 'Tools and current church information in one place.', $output);
    $output = str_replace('Here’s a quick look at the website.', 'Here’s a quick look at Surfside.', $output);
    $output = str_replace('<h2 class="surfside-dashboard-section-title">Website Status</h2>', '<h2 class="surfside-dashboard-section-title">Current Status</h2>', $output);
    $management = surfside_tools_dashboard_management_areas();
    $output = preg_replace('/<section class="surfside-dashboard-quick-actions">.*?<\/section>/s', $management, $output, 1);
    return $output;
}, 30, 2);
