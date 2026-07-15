<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parse the visible announcement date and use the latest day in a weekend range.
 * Examples: "July 11/12, 2026" and "July 12, 2026".
 */
function surfside_tools_dashboard_announcement_timestamp($date_text, $fallback = 0) {
    $date_text = trim((string) $date_text);
    $timezone = wp_timezone();

    if ($date_text !== '' && preg_match('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2})(?:\s*\/\s*(\d{1,2}))?,\s*(\d{4})\b/i', $date_text, $matches)) {
        $month = $matches[1];
        $day = !empty($matches[3]) ? $matches[3] : $matches[2];
        try {
            $date = new DateTimeImmutable($month . ' ' . $day . ', ' . $matches[4] . ' 12:00:00', $timezone);
            return $date->getTimestamp();
        } catch (Exception $e) {
            // Fall through to the saved timestamp.
        }
    }

    return absint($fallback);
}

function surfside_tools_dashboard_activity_context($data) {
    $today = wp_date('Y-m-d');
    $thirty_days = wp_date('Y-m-d', current_time('timestamp') + (DAY_IN_SECONDS * 30));
    $occurrences_30 = array();

    if (function_exists('surfside_tools_calendar_get_all_events') && function_exists('surfside_tools_calendar_event_occurrences')) {
        foreach (surfside_tools_calendar_get_all_events() as $event) {
            foreach (surfside_tools_calendar_event_occurrences($event, $today, $thirty_days) as $occurrence) {
                $occurrences_30[] = $occurrence;
            }
        }
    }

    usort($occurrences_30, function ($left, $right) {
        $left_key = ($left['occurrence_date'] ?? $left['date'] ?? '') . ' ' . ($left['start_time'] ?? '00:00');
        $right_key = ($right['occurrence_date'] ?? $right['date'] ?? '') . ' ' . ($right['start_time'] ?? '00:00');
        return strcmp($left_key, $right_key);
    });

    $activities = array();
    $weekly_saved = absint($data['weekly']['published_timestamp'] ?? 0);
    if ($weekly_saved) {
        $activities[] = array(
            'timestamp' => $weekly_saved,
            'title' => 'Weekly Update published',
            'detail' => (string) ($data['weekly']['announcement_date'] ?? ''),
            'url' => surfside_tools_staff_page_url('weekly-update'),
        );
    }

    $homepage_updated = absint($data['homepage']['last_updated'] ?? 0);
    if ($homepage_updated) {
        $activities[] = array(
            'timestamp' => $homepage_updated,
            'title' => 'Homepage photos updated',
            'detail' => absint($data['homepage']['photo_count'] ?? 0) . ' photos currently in the carousel',
            'url' => surfside_tools_staff_page_url('homepage'),
        );
    }

    $latest_event = get_posts(array(
        'post_type' => 'surfside_event',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'modified',
        'order' => 'DESC',
        'fields' => 'ids',
        'no_found_rows' => true,
    ));
    if ($latest_event) {
        $event_id = absint($latest_event[0]);
        $event_timestamp = get_post_modified_time('U', true, $event_id);
        if ($event_timestamp) {
            $activities[] = array(
                'timestamp' => $event_timestamp,
                'title' => 'Calendar event updated',
                'detail' => get_the_title($event_id),
                'url' => surfside_tools_staff_page_url('calendar'),
            );
        }
    }

    $settings_updated = absint(get_option('surfside_tools_settings_updated', 0));
    if ($settings_updated) {
        $activities[] = array(
            'timestamp' => $settings_updated,
            'title' => 'Settings updated',
            'detail' => 'Surfside Tools settings were saved',
            'url' => surfside_tools_staff_page_url('settings'),
        );
    }

    usort($activities, function ($left, $right) {
        return ($right['timestamp'] ?? 0) <=> ($left['timestamp'] ?? 0);
    });

    return array(
        'announcement_timestamp' => surfside_tools_dashboard_announcement_timestamp(
            $data['weekly']['announcement_date'] ?? '',
            $weekly_saved
        ),
        'occurrences_30' => $occurrences_30,
        'occurrence_count_30' => count($occurrences_30),
        'activities' => array_slice($activities, 0, 4),
    );
}

function surfside_tools_dashboard_evaluate_status_v2($data, $context) {
    $evaluation = surfside_tools_dashboard_evaluate_status($data);
    $statuses = $evaluation['statuses'];

    $now = current_time('timestamp');
    $weekday = (int) wp_date('N', $now);
    $monday = strtotime('monday this week 00:00:00', $now);
    $weekly_current = !empty($context['announcement_timestamp']) && $context['announcement_timestamp'] >= $monday;

    if ($weekly_current) {
        $statuses['weekly'] = array(
            'level' => 'good',
            'label' => 'Current',
            'message' => 'This week’s content has been published.',
            'url' => surfside_tools_staff_page_url('weekly-update'),
        );
    } elseif ($weekday === 1) {
        $statuses['weekly'] = array(
            'level' => 'warning',
            'label' => 'Attention',
            'message' => 'Weekly content became stale today. Prepare this week’s update.',
            'url' => surfside_tools_staff_page_url('weekly-update'),
        );
    } else {
        $statuses['weekly'] = array(
            'level' => 'critical',
            'label' => 'Action required',
            'message' => 'Weekly content is still from last week.',
            'url' => surfside_tools_staff_page_url('weekly-update'),
        );
    }

    if (empty($data['calendar']['next'])) {
        $statuses['calendar'] = array(
            'level' => 'critical',
            'label' => 'Action required',
            'message' => 'The calendar has no future events.',
            'url' => surfside_tools_staff_page_url('calendar'),
        );
    } elseif (empty($context['occurrence_count_30'])) {
        $statuses['calendar'] = array(
            'level' => 'warning',
            'label' => 'Attention',
            'message' => 'There are no events scheduled in the next 30 days.',
            'url' => surfside_tools_staff_page_url('calendar'),
        );
    } else {
        $statuses['calendar'] = array(
            'level' => 'good',
            'label' => 'Healthy',
            'message' => 'Upcoming events are available.',
            'url' => surfside_tools_staff_page_url('calendar'),
        );
    }

    $alerts = array();
    foreach ($statuses as $key => $status) {
        if (($status['level'] ?? 'good') !== 'good') {
            $alerts[] = array(
                'key' => $key,
                'level' => $status['level'],
                'message' => $status['message'],
                'url' => $status['url'],
            );
        }
    }

    return array('statuses' => $statuses, 'alerts' => $alerts);
}

function surfside_tools_dashboard_track_settings_update($option, $old_value, $value) {
    if (in_array($option, array('surfside_tools_settings', 'surfside_tools_visual_custom_css'), true)) {
        update_option('surfside_tools_settings_updated', current_time('timestamp'), false);
    }
}
add_action('updated_option', 'surfside_tools_dashboard_track_settings_update', 10, 3);

function surfside_tools_dashboard_recent_activity_styles() {
    wp_add_inline_style('surfside-tools-staff-dashboard', '
        .surfside-dashboard-activity{margin:0 0 34px}.surfside-dashboard-activity-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.surfside-dashboard-activity-item{display:flex;gap:14px;padding:18px;border:1px solid rgba(7,27,58,.12);border-radius:15px;background:#fff;text-decoration:none;color:inherit}.surfside-dashboard-activity-item:hover{border-color:#8eb9eb;box-shadow:0 7px 20px rgba(7,27,58,.07)}.surfside-dashboard-activity-marker{width:11px;height:11px;margin-top:5px;border-radius:999px;background:#1d68b4;flex:0 0 auto}.surfside-dashboard-activity-item strong{display:block;color:#071b3a}.surfside-dashboard-activity-item span{display:block;margin-top:3px;color:#556178;font-size:14px;line-height:1.4}.surfside-dashboard-activity-empty{padding:18px;border:1px dashed rgba(7,27,58,.22);border-radius:15px;color:#637086}@media(max-width:760px){.surfside-dashboard-activity-list{grid-template-columns:1fr}}
    ');
}

function surfside_tools_dashboard_intelligence_shortcode_v2() {
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
    $data = surfside_tools_dashboard_status_data();
    $context = surfside_tools_dashboard_activity_context($data);
    $evaluation = surfside_tools_dashboard_evaluate_status_v2($data, $context);
    $statuses = $evaluation['statuses'];
    $alerts = $evaluation['alerts'];
    $user = wp_get_current_user();
    $greeting_name = trim((string) $user->first_name) ?: $user->display_name;
    $hour = (int) wp_date('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

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
                <div class="surfside-dashboard-stat"><?php echo esc_html($context['occurrence_count_30']); ?> events in the next 30 days</div>
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

        <section class="surfside-dashboard-activity">
            <h2 class="surfside-dashboard-section-title">Recent Activity</h2>
            <?php if ($context['activities']) : ?>
                <div class="surfside-dashboard-activity-list">
                    <?php foreach ($context['activities'] as $activity) : ?>
                        <a class="surfside-dashboard-activity-item" href="<?php echo esc_url($activity['url']); ?>">
                            <span class="surfside-dashboard-activity-marker" aria-hidden="true"></span>
                            <span><strong><?php echo esc_html($activity['title']); ?></strong><span><?php echo esc_html($activity['detail']); ?></span><span><?php echo esc_html(wp_date('F j, Y g:i A', $activity['timestamp'])); ?></span></span>
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
    add_shortcode('surfside_staff_dashboard', 'surfside_tools_dashboard_intelligence_shortcode_v2');
}, 50);
