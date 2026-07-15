<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_dashboard_action_label($key, $status) {
    $needs_attention = in_array($status['level'] ?? 'good', array('warning', 'critical'), true);

    $labels = array(
        'weekly' => $needs_attention ? 'Prepare Weekly Update' : 'Open Weekly Update',
        'calendar' => $needs_attention ? 'Review Calendar' : 'Open Calendar',
        'homepage' => $needs_attention ? 'Review Homepage Photos' : 'Open Homepage Manager',
        'settings' => $needs_attention ? 'Fix Settings' : 'Open Settings',
    );

    return $labels[$key] ?? 'Open';
}

function surfside_tools_dashboard_stat_block($number, $label) {
    return '<div class="surfside-dashboard-metric"><strong>' . esc_html($number) . '</strong><span>' . esc_html($label) . '</span></div>';
}

function surfside_tools_dashboard_polish_styles() {
    wp_add_inline_style('surfside-tools-staff-dashboard', '
        .surfside-dashboard-status-grid{align-items:stretch}.surfside-dashboard-status-card{min-height:100%;padding:26px}.surfside-dashboard-status-head{justify-content:space-between;align-items:flex-start}.surfside-dashboard-status-title{display:flex;align-items:center;gap:13px}.surfside-dashboard-health{margin:0}.surfside-dashboard-metric{display:flex;align-items:baseline;gap:10px;margin:8px 0 14px}.surfside-dashboard-metric strong{font-size:clamp(42px,6vw,58px);line-height:.9;letter-spacing:-.055em;color:#071b3a}.surfside-dashboard-metric span{max-width:170px;font-size:15px;line-height:1.25;font-weight:750;color:#556178}.surfside-dashboard-status-content{display:flex;flex-direction:column;flex:1}.surfside-dashboard-status-card .surfside-staff-actions{padding-top:20px}.surfside-dashboard-status-card .surfside-staff-button,.surfside-dashboard-status-card .surfside-staff-button-secondary{width:100%;justify-content:center}.surfside-dashboard-activity-item{align-items:flex-start;min-height:100%}.surfside-dashboard-activity-item>span:last-child{min-width:0}.surfside-dashboard-activity-time{font-size:12px!important;text-transform:uppercase;letter-spacing:.045em;font-weight:700;color:#7a8496!important}.surfside-dashboard-quick-actions .surfside-staff-card{min-height:215px;padding:22px}.surfside-dashboard-quick-actions .surfside-staff-card h2{font-size:21px}.surfside-dashboard-quick-actions .surfside-staff-card p{font-size:14px}.surfside-dashboard-summary{position:relative;overflow:hidden}.surfside-dashboard-summary:before{content:"";position:absolute;inset:0 auto 0 0;width:6px;background:currentColor;opacity:.55}@media(max-width:760px){.surfside-staff-shell{padding-left:14px;padding-right:14px}.surfside-dashboard-greeting{margin-bottom:18px}.surfside-dashboard-greeting h2{font-size:30px}.surfside-dashboard-summary{padding:20px 20px 20px 22px}.surfside-dashboard-status-card{padding:20px}.surfside-dashboard-status-head{gap:12px}.surfside-dashboard-status-title{align-items:flex-start}.surfside-dashboard-status-head .surfside-staff-icon{width:42px;height:42px}.surfside-dashboard-status-card h3{font-size:21px}.surfside-dashboard-metric{align-items:flex-end}.surfside-dashboard-metric strong{font-size:48px}.surfside-dashboard-metric span{padding-bottom:3px}.surfside-dashboard-detail{font-size:15px}.surfside-dashboard-status-card .surfside-staff-actions a{min-height:48px}.surfside-dashboard-activity-item{padding:16px}.surfside-dashboard-quick-actions .surfside-staff-card{min-height:auto}.surfside-dashboard-quick-actions .surfside-staff-actions a{width:100%;justify-content:center}}
    ');
}

function surfside_tools_dashboard_intelligence_shortcode_v3() {
    if (function_exists('surfside_tools_prevent_cache')) {
        surfside_tools_prevent_cache();
    }
    if (function_exists('surfside_tools_staff_enqueue_styles')) {
        surfside_tools_staff_enqueue_styles();
    }
    if (!is_user_logged_in()) {
        return function_exists('surfside_tools_staff_login_box') ? surfside_tools_staff_login_box() : '<p>Please log in.</p>';
    }
    if (!current_user_can('upload_files')) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to access Surfside staff tools.</p></div>';
    }

    surfside_tools_dashboard_intelligence_styles();
    surfside_tools_dashboard_recent_activity_styles();
    surfside_tools_dashboard_polish_styles();

    $data = surfside_tools_dashboard_status_data();
    $context = surfside_tools_dashboard_activity_context($data);
    $evaluation = surfside_tools_dashboard_evaluate_status_v2($data, $context);
    $statuses = $evaluation['statuses'];
    $alerts = $evaluation['alerts'];
    $user = wp_get_current_user();
    $greeting_name = trim((string) $user->first_name) ?: $user->display_name;
    $hour = (int) wp_date('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

    $cards = array(
        'weekly' => array(
            'title' => 'Weekly Update',
            'icon' => 'upload',
            'metric' => $data['weekly']['announcement_count'],
            'metric_label' => 'announcements published',
            'details' => array(
                '<strong>Announcement date:</strong> ' . esc_html(surfside_tools_dashboard_format_date($data['weekly']['announcement_date'])),
                '<strong>Sermon notes:</strong> ' . esc_html($data['weekly']['message_title'] ?: 'Not published yet'),
            ),
        ),
        'calendar' => array(
            'title' => 'Calendar',
            'icon' => 'calendar',
            'metric' => $context['occurrence_count_30'],
            'metric_label' => 'events in the next 30 days',
            'details' => array('<strong>Next event:</strong><br>' . esc_html(surfside_tools_dashboard_next_event_text($data['calendar']['next']))),
        ),
        'homepage' => array(
            'title' => 'Homepage',
            'icon' => 'document',
            'metric' => $data['homepage']['photo_count'],
            'metric_label' => 'photos in the carousel',
            'details' => array('<strong>Last updated:</strong> ' . ($data['homepage']['last_updated'] ? esc_html(wp_date('F j, Y g:i A', $data['homepage']['last_updated'])) : 'No update date recorded')),
        ),
        'settings' => array(
            'title' => 'Settings',
            'icon' => 'settings',
            'metric' => $data['settings']['saved_places_count'],
            'metric_label' => 'saved places',
            'details' => array(
                '<strong>Google Places:</strong> ' . ($data['settings']['google_maps_connected'] ? 'Connected' : 'Not configured'),
                '<strong>Visual CSS overrides:</strong> ' . ($data['settings']['visual_css_enabled'] ? 'Enabled' : 'Using defaults'),
            ),
        ),
    );

    ob_start();
    ?>
    <section class="surfside-staff-dashboard-hero"><h1>Staff Dashboard</h1><p>Tools and current website information in one place.</p></section>
    <div class="surfside-staff-shell">
        <div class="surfside-dashboard-greeting"><h2><?php echo esc_html($greeting . ', ' . $greeting_name . '!'); ?></h2><p class="surfside-staff-muted">Here’s a quick look at the website.</p></div>

        <?php if (!$alerts) : ?>
            <section class="surfside-dashboard-summary surfside-dashboard-summary-good"><h3>Everything looks good.</h3><p>Weekly content, calendar, homepage photos, and key settings are in good shape.</p></section>
        <?php else : ?>
            <section class="surfside-dashboard-summary surfside-dashboard-summary-attention">
                <h3><?php echo esc_html(count($alerts)); ?> item<?php echo count($alerts) === 1 ? '' : 's'; ?> need attention</h3>
                <p>Choose an item below to open the page where it can be resolved.</p>
                <ul class="surfside-dashboard-alert-list">
                    <?php foreach ($alerts as $alert) : ?><li class="surfside-dashboard-alert-<?php echo esc_attr($alert['level']); ?>"><a href="<?php echo esc_url($alert['url']); ?>"><span class="surfside-dashboard-alert-dot" aria-hidden="true"></span><?php echo esc_html($alert['message']); ?></a></li><?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <h2 class="surfside-dashboard-section-title">Website Status</h2>
        <div class="surfside-dashboard-status-grid">
            <?php foreach ($cards as $key => $card) : $status = $statuses[$key]; ?>
                <article class="surfside-dashboard-status-card surfside-dashboard-status-card-<?php echo esc_attr($status['level']); ?>">
                    <div class="surfside-dashboard-status-head">
                        <div class="surfside-dashboard-status-title"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon($card['icon']); ?></span><h3><?php echo esc_html($card['title']); ?></h3></div>
                        <?php echo surfside_tools_dashboard_status_badge($status); ?>
                    </div>
                    <div class="surfside-dashboard-status-content">
                        <?php echo surfside_tools_dashboard_stat_block($card['metric'], $card['metric_label']); ?>
                        <?php foreach ($card['details'] as $detail) : ?><p class="surfside-dashboard-detail"><?php echo wp_kses_post($detail); ?></p><?php endforeach; ?>
                        <p class="surfside-dashboard-status-message"><?php echo esc_html($status['message']); ?></p>
                        <div class="surfside-staff-actions"><a class="<?php echo $key === 'weekly' ? 'surfside-staff-button' : 'surfside-staff-button-secondary'; ?>" href="<?php echo esc_url($status['url']); ?>"><?php echo esc_html(surfside_tools_dashboard_action_label($key, $status)); ?> <span class="surfside-staff-arrow">›</span></a></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <section class="surfside-dashboard-activity">
            <h2 class="surfside-dashboard-section-title">Recent Activity</h2>
            <?php if ($context['activities']) : ?>
                <div class="surfside-dashboard-activity-list">
                    <?php foreach ($context['activities'] as $activity) : ?>
                        <a class="surfside-dashboard-activity-item" href="<?php echo esc_url($activity['url']); ?>">
                            <span class="surfside-dashboard-activity-marker" aria-hidden="true"></span>
                            <span><strong><?php echo esc_html($activity['title']); ?></strong><span><?php echo esc_html($activity['detail']); ?></span><span class="surfside-dashboard-activity-time"><?php echo esc_html(wp_date('F j, Y g:i A', $activity['timestamp'])); ?></span></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="surfside-dashboard-activity-empty">Recent activity will appear as staff use Surfside Tools.</div>
            <?php endif; ?>
        </section>

        <section class="surfside-dashboard-quick-actions">
            <h2 class="surfside-dashboard-section-title">Quick Actions</h2>
            <div class="surfside-staff-grid">
                <article class="surfside-staff-card"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('upload'); ?></span><h2>Weekly Update</h2><p>Upload DOCX files, review, and publish.</p><div class="surfside-staff-actions"><a class="surfside-staff-button" href="<?php echo esc_url(surfside_tools_staff_page_url('weekly-update')); ?>">Open Weekly Update <span class="surfside-staff-arrow">›</span></a></div></article>
                <article class="surfside-staff-card"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('calendar'); ?></span><h2>Calendar</h2><p>Manage church calendar events.</p><div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url('calendar')); ?>">Open Calendar <span class="surfside-staff-arrow">›</span></a></div></article>
                <article class="surfside-staff-card"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('document'); ?></span><h2>Manage Homepage</h2><p>Manage homepage carousel photos.</p><div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url('homepage')); ?>">Open Homepage Manager <span class="surfside-staff-arrow">›</span></a></div></article>
                <article class="surfside-staff-card"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('settings'); ?></span><h2>Settings</h2><p>Manage Google Maps, saved places, and preferences.</p><div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url('settings')); ?>">Open Settings <span class="surfside-staff-arrow">›</span></a></div></article>
            </div>
        </section>
    </div>
    <?php
    return ob_get_clean();
}

add_action('init', function () {
    remove_shortcode('surfside_staff_dashboard');
    add_shortcode('surfside_staff_dashboard', 'surfside_tools_dashboard_intelligence_shortcode_v3');
}, 60);
