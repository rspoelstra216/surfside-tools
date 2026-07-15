<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parse the first date represented by a weekly announcement label.
 * Supports values such as "July 11/12, 2026" and "July 18, 2026".
 */
function surfside_tools_dashboard_announcement_date_timestamp($label) {
    $label = trim((string) $label);
    if ($label === '') {
        return 0;
    }

    if (preg_match('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2})(?:\s*\/\s*\d{1,2})?\s*,\s*(\d{4})\b/i', $label, $matches)) {
        $timestamp = strtotime($matches[1] . ' ' . $matches[2] . ', ' . $matches[3]);
        return $timestamp ?: 0;
    }

    $timestamp = strtotime($label);
    return $timestamp ?: 0;
}

function surfside_tools_dashboard_clarity_data() {
    $data = surfside_tools_dashboard_status_data();

    $announcement_timestamp = surfside_tools_dashboard_announcement_date_timestamp($data['weekly']['announcement_date'] ?? '');
    if ($announcement_timestamp) {
        $data['weekly']['freshness_timestamp'] = $announcement_timestamp;
    } else {
        $data['weekly']['freshness_timestamp'] = absint($data['weekly']['published_timestamp'] ?? 0);
    }

    $today = wp_date('Y-m-d');
    $thirty_days = wp_date('Y-m-d', current_time('timestamp') + (DAY_IN_SECONDS * 30));
    $occurrences = array();

    if (function_exists('surfside_tools_calendar_get_all_events') && function_exists('surfside_tools_calendar_event_occurrences')) {
        foreach (surfside_tools_calendar_get_all_events() as $event) {
            foreach (surfside_tools_calendar_event_occurrences($event, $today, $thirty_days) as $occurrence) {
                $occurrences[] = $occurrence;
            }
        }
    }

    $data['calendar']['occurrences_30_days'] = count($occurrences);
    return $data;
}

function surfside_tools_dashboard_clarity_evaluate_status($data) {
    $evaluation = surfside_tools_dashboard_evaluate_status($data);
    $now = current_time('timestamp');
    $weekday = (int) wp_date('N', $now);
    $monday = strtotime('monday this week 00:00:00', $now);
    $freshness_timestamp = absint($data['weekly']['freshness_timestamp'] ?? 0);
    $weekly_current = $freshness_timestamp && $freshness_timestamp >= $monday;

    if ($weekly_current) {
        $weekly = array('level' => 'good', 'label' => 'Current', 'message' => 'This week’s content has been published.', 'url' => surfside_tools_staff_page_url('weekly-update'));
    } elseif ($weekday === 1) {
        $weekly = array('level' => 'warning', 'label' => 'Attention', 'message' => 'Weekly content became stale today. Prepare this week’s update.', 'url' => surfside_tools_staff_page_url('weekly-update'));
    } else {
        $weekly = array('level' => 'critical', 'label' => 'Action required', 'message' => 'Weekly content is still from last week.', 'url' => surfside_tools_staff_page_url('weekly-update'));
    }

    $evaluation['statuses']['weekly'] = $weekly;
    $evaluation['alerts'] = array();

    foreach ($evaluation['statuses'] as $key => $status) {
        if (($status['level'] ?? 'good') !== 'good') {
            $evaluation['alerts'][] = array(
                'key' => $key,
                'level' => $status['level'],
                'message' => $status['message'],
                'url' => $status['url'],
            );
        }
    }

    return $evaluation;
}

function surfside_tools_dashboard_clarity_shortcode() {
    if (function_exists('surfside_tools_prevent_cache')) {
        surfside_tools_prevent_cache();
    }
    surfside_tools_staff_enqueue_styles();

    if (!is_user_logged_in()) {
        return surfside_tools_staff_login_box();
    }
    if (!current_user_can('upload_files')) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to access Surfside staff tools.</p></div>';
    }

    surfside_tools_dashboard_intelligence_styles();
    $data = surfside_tools_dashboard_clarity_data();
    $evaluation = surfside_tools_dashboard_clarity_evaluate_status($data);
    $statuses = $evaluation['statuses'];
    $alerts = $evaluation['alerts'];
    $user = wp_get_current_user();
    $greeting_name = trim((string) $user->first_name) ?: $user->display_name;
    $hour = (int) wp_date('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

    ob_start();
    ?>
    <section class="surfside-staff-dashboard-hero">
        <h1>Staff Dashboard</h1>
        <p>Tools and current website information in one place.</p>
    </section>

    <div class="surfside-staff-shell">
        <div class="surfside-dashboard-greeting">
            <h2><?php echo esc_html($greeting . ', ' . $greeting_name . '!'); ?></h2>
            <p class="surfside-staff-muted">Here’s a quick look at the website.</p>
        </div>

        <?php if (!$alerts) : ?>
            <section class="surfside-dashboard-summary surfside-dashboard-summary-good">
                <h3>Everything looks good.</h3>
                <p>Weekly content, calendar, homepage photos, and key settings are in good shape.</p>
            </section>
        <?php else : ?>
            <section class="surfside-dashboard-summary surfside-dashboard-summary-attention">
                <h3><?php echo esc_html(count($alerts)); ?> item<?php echo count($alerts) === 1 ? '' : 's'; ?> need attention</h3>
                <p>Choose an item below to open the page where it can be resolved.</p>
                <ul class="surfside-dashboard-alert-list">
                    <?php foreach ($alerts as $alert) : ?>
                        <li class="surfside-dashboard-alert-<?php echo esc_attr($alert['level']); ?>"><a href="<?php echo esc_url($alert['url']); ?>"><span class="surfside-dashboard-alert-dot" aria-hidden="true"></span><?php echo esc_html($alert['message']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <h2 class="surfside-dashboard-section-title">Website Status</h2>
        <div class="surfside-dashboard-status-grid">
            <article class="surfside-dashboard-status-card surfside-dashboard-status-card-<?php echo esc_attr($statuses['weekly']['level']); ?>">
                <div class="surfside-dashboard-status-head"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('upload'); ?></span><h3>Weekly Update</h3></div>
                <?php echo surfside_tools_dashboard_status_badge($statuses['weekly']); ?>
                <div class="surfside-dashboard-stat"><?php echo esc_html($data['weekly']['announcement_count']); ?> announcements</div>
                <p class="surfside-dashboard-detail"><strong>Announcement date:</strong> <?php echo esc_html(surfside_tools_dashboard_format_date($data['weekly']['announcement_date'])); ?></p>
                <p class="surfside-dashboard-detail"><strong>Sermon notes:</strong> <?php echo esc_html($data['weekly']['message_title'] ?: 'Not published yet'); ?></p>
                <p class="surfside-dashboard-status-message"><?php echo esc_html($statuses['weekly']['message']); ?></p>
                <div class="surfside-staff-actions"><a class="surfside-staff-button" href="<?php echo esc_url($statuses['weekly']['url']); ?>">Update Weekly Content <span class="surfside-staff-arrow">›</span></a></div>
            </article>

            <article class="surfside-dashboard-status-card surfside-dashboard-status-card-<?php echo esc_attr($statuses['calendar']['level']); ?>">
                <div class="surfside-dashboard-status-head"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('calendar'); ?></span><h3>Calendar</h3></div>
                <?php echo surfside_tools_dashboard_status_badge($statuses['calendar']); ?>
                <div class="surfside-dashboard-stat"><?php echo esc_html($data['calendar']['occurrences_30_days']); ?> events in the next 30 days</div>
                <p class="surfside-dashboard-detail"><strong>Next event:</strong><br><?php echo esc_html(surfside_tools_dashboard_next_event_text($data['calendar']['next'])); ?></p>
                <p class="surfside-dashboard-status-message"><?php echo esc_html($statuses['calendar']['message']); ?></p>
                <div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url($statuses['calendar']['url']); ?>">Manage Calendar <span class="surfside-staff-arrow">›</span></a></div>
            </article>

            <article class="surfside-dashboard-status-card surfside-dashboard-status-card-<?php echo esc_attr($statuses['homepage']['level']); ?>">
                <div class="surfside-dashboard-status-head"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('document'); ?></span><h3>Homepage</h3></div>
                <?php echo surfside_tools_dashboard_status_badge($statuses['homepage']); ?>
                <div class="surfside-dashboard-stat"><?php echo esc_html($data['homepage']['photo_count']); ?> photos</div>
                <p class="surfside-dashboard-detail"><strong>Last updated:</strong> <?php echo $data['homepage']['last_updated'] ? esc_html(wp_date('F j, Y g:i A', $data['homepage']['last_updated'])) : 'No update date recorded'; ?></p>
                <p class="surfside-dashboard-status-message"><?php echo esc_html($statuses['homepage']['message']); ?></p>
                <div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url($statuses['homepage']['url']); ?>">Manage Homepage <span class="surfside-staff-arrow">›</span></a></div>
            </article>

            <article class="surfside-dashboard-status-card surfside-dashboard-status-card-<?php echo esc_attr($statuses['settings']['level']); ?>">
                <div class="surfside-dashboard-status-head"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('settings'); ?></span><h3>Settings</h3></div>
                <?php echo surfside_tools_dashboard_status_badge($statuses['settings']); ?>
                <p class="surfside-dashboard-detail"><strong>Google Places:</strong> <?php echo $data['settings']['google_maps_connected'] ? 'Connected' : 'Not configured'; ?></p>
                <p class="surfside-dashboard-detail"><strong>Saved Places:</strong> <?php echo esc_html($data['settings']['saved_places_count']); ?></p>
                <p class="surfside-dashboard-detail"><strong>Visual CSS overrides:</strong> <?php echo $data['settings']['visual_css_enabled'] ? 'Enabled' : 'Using defaults'; ?></p>
                <p class="surfside-dashboard-status-message"><?php echo esc_html($statuses['settings']['message']); ?></p>
                <div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url($statuses['settings']['url']); ?>">Open Settings <span class="surfside-staff-arrow">›</span></a></div>
            </article>
        </div>

        <section class="surfside-dashboard-quick-actions">
            <h2 class="surfside-dashboard-section-title">Quick Actions</h2>
            <div class="surfside-staff-grid">
                <article class="surfside-staff-card"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('upload'); ?></span><h2>Weekly Update</h2><p>Upload DOCX files, review, and publish.</p><div class="surfside-staff-actions"><a class="surfside-staff-button" href="<?php echo esc_url(surfside_tools_staff_page_url('weekly-update')); ?>">Go to Weekly Update <span class="surfside-staff-arrow">›</span></a></div></article>
                <article class="surfside-staff-card"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('calendar'); ?></span><h2>Calendar</h2><p>Manage church calendar events.</p><div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url('calendar')); ?>">Go to Calendar <span class="surfside-staff-arrow">›</span></a></div></article>
                <article class="surfside-staff-card"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('document'); ?></span><h2>Manage Homepage</h2><p>Manage homepage carousel photos.</p><div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url('homepage')); ?>">Manage Homepage <span class="surfside-staff-arrow">›</span></a></div></article>
                <article class="surfside-staff-card"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('settings'); ?></span><h2>Settings</h2><p>Manage Google Maps, saved places, and preferences.</p><div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url('settings')); ?>">Go to Settings <span class="surfside-staff-arrow">›</span></a></div></article>
            </div>
        </section>
    </div>
    <?php
    return ob_get_clean();
}

add_action('init', function () {
    remove_shortcode('surfside_staff_dashboard');
    add_shortcode('surfside_staff_dashboard', 'surfside_tools_dashboard_clarity_shortcode');
}, 50);
