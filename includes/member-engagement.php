<?php
/** Front-end hub for member-facing church engagement tools. */
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
        return surfside_tools_staff_volunteer_needs_view();
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
