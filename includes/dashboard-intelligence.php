<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_dashboard_status_data() {
    $announcements = function_exists('surfside_tools_get_announcements_data') ? (array) surfside_tools_get_announcements_data() : array();
    $message = function_exists('surfside_tools_get_message_data') ? (array) surfside_tools_get_message_data() : array();

    $announcement_items = array();
    foreach (array('items', 'announcement_items') as $key) {
        if (!empty($announcements[$key]) && is_array($announcements[$key])) {
            $announcement_items = $announcements[$key];
            break;
        }
    }

    $homepage_images = function_exists('surfside_tools_homepage_get_images') ? (array) surfside_tools_homepage_get_images() : array();
    $homepage_updated = 0;
    foreach ($homepage_images as $image) {
        $homepage_updated = max($homepage_updated, absint(is_array($image) ? ($image['updated'] ?? 0) : 0));
    }

    $today = wp_date('Y-m-d');
    $range_end = wp_date('Y-m-d', current_time('timestamp') + (DAY_IN_SECONDS * 366));
    $occurrences = array();
    $active_event_ids = array();

    if (function_exists('surfside_tools_calendar_get_all_events') && function_exists('surfside_tools_calendar_event_occurrences')) {
        foreach (surfside_tools_calendar_get_all_events() as $event) {
            $event_occurrences = surfside_tools_calendar_event_occurrences($event, $today, $range_end);
            if ($event_occurrences) {
                $active_event_ids[absint($event['id'] ?? 0)] = true;
                foreach ($event_occurrences as $occurrence) {
                    $occurrences[] = $occurrence;
                }
            }
        }
    }

    usort($occurrences, function ($left, $right) {
        $left_key = ($left['occurrence_date'] ?? $left['date'] ?? '') . ' ' . ($left['start_time'] ?? '00:00');
        $right_key = ($right['occurrence_date'] ?? $right['date'] ?? '') . ' ' . ($right['start_time'] ?? '00:00');
        return strcmp($left_key, $right_key);
    });

    $settings = (array) get_option('surfside_tools_settings', array());
    $saved_places = function_exists('surfside_tools_calendar_get_saved_locations') ? (array) surfside_tools_calendar_get_saved_locations() : array();
    $visual_css = trim((string) get_option('surfside_tools_visual_custom_css', ''));

    return array(
        'weekly' => array(
            'announcement_date' => (string) ($announcements['announcement_date'] ?? ''),
            'announcement_count' => count($announcement_items),
            'message_title' => (string) ($message['title'] ?? ''),
            'message_date' => (string) ($message['date'] ?? ''),
        ),
        'calendar' => array(
            'upcoming_count' => count(array_filter(array_keys($active_event_ids))),
            'next' => $occurrences ? $occurrences[0] : null,
        ),
        'homepage' => array(
            'photo_count' => count($homepage_images),
            'last_updated' => $homepage_updated,
        ),
        'settings' => array(
            'google_maps_connected' => !empty($settings['google_maps_api_key']),
            'saved_places_count' => count($saved_places),
            'visual_css_enabled' => $visual_css !== '',
        ),
    );
}

function surfside_tools_dashboard_format_date($date) {
    $date = trim((string) $date);
    if ($date === '') {
        return 'Not published yet';
    }

    $timestamp = strtotime($date);
    return $timestamp ? wp_date('F j, Y', $timestamp) : $date;
}

function surfside_tools_dashboard_next_event_text($event) {
    if (!$event || empty($event['title'])) {
        return 'No upcoming events found';
    }

    $date = (string) ($event['occurrence_date'] ?? $event['date'] ?? '');
    $date_text = $date ? wp_date('F j, Y', strtotime($date)) : 'Date not set';
    $time = trim((string) ($event['start_time'] ?? ''));
    if ($time !== '') {
        $timestamp = strtotime($date . ' ' . $time);
        if ($timestamp) {
            $date_text .= ' at ' . wp_date('g:i A', $timestamp);
        }
    }

    return (string) $event['title'] . ' — ' . $date_text;
}

function surfside_tools_dashboard_intelligence_styles() {
    wp_add_inline_style('surfside-tools-staff-dashboard', '
        .surfside-dashboard-greeting{margin:0 0 24px}.surfside-dashboard-greeting h2{margin:0 0 6px;font-size:clamp(27px,4vw,38px);letter-spacing:-.035em;color:#071b3a}.surfside-dashboard-section-title{margin:0 0 16px;font-size:clamp(22px,3vw,30px);color:#071b3a}.surfside-dashboard-status-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-bottom:34px}.surfside-dashboard-status-card{display:flex;flex-direction:column;min-height:225px;padding:24px;border:1px solid rgba(7,27,58,.12);border-radius:18px;background:#fff;box-shadow:0 10px 26px rgba(7,27,58,.065)}.surfside-dashboard-status-head{display:flex;align-items:center;gap:13px;margin-bottom:18px}.surfside-dashboard-status-head .surfside-staff-icon{width:48px;height:48px}.surfside-dashboard-status-head .surfside-staff-icon svg{width:25px;height:25px}.surfside-dashboard-status-card h3{margin:0;font-size:23px;letter-spacing:-.025em;color:#071b3a}.surfside-dashboard-stat{font-size:30px;line-height:1;font-weight:800;color:#071b3a;margin-bottom:8px}.surfside-dashboard-detail{margin:5px 0;color:#46526a;line-height:1.45}.surfside-dashboard-detail strong{color:#071b3a}.surfside-dashboard-status-card .surfside-staff-actions{margin-top:auto;padding-top:18px}.surfside-dashboard-quick-actions{margin-top:10px}.surfside-dashboard-quick-actions .surfside-staff-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.surfside-dashboard-quick-actions .surfside-staff-card{min-height:230px}.surfside-dashboard-status-good{display:inline-flex;align-items:center;gap:6px;color:#148944;font-weight:700}.surfside-dashboard-status-neutral{color:#637086;font-weight:700}@media(max-width:1000px){.surfside-dashboard-quick-actions .surfside-staff-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.surfside-dashboard-status-grid,.surfside-dashboard-quick-actions .surfside-staff-grid{grid-template-columns:1fr}.surfside-dashboard-status-card{min-height:auto}}
    ');
}

function surfside_tools_dashboard_intelligence_shortcode() {
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
    $data = surfside_tools_dashboard_status_data();
    $user = wp_get_current_user();
    $first_name = trim((string) $user->first_name);
    $greeting_name = $first_name !== '' ? $first_name : $user->display_name;
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

        <h2 class="surfside-dashboard-section-title">Website Status</h2>
        <div class="surfside-dashboard-status-grid">
            <article class="surfside-dashboard-status-card">
                <div class="surfside-dashboard-status-head"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('upload'); ?></span><h3>Weekly Update</h3></div>
                <div class="surfside-dashboard-stat"><?php echo esc_html($data['weekly']['announcement_count']); ?> announcements</div>
                <p class="surfside-dashboard-detail"><strong>Last published:</strong> <?php echo esc_html(surfside_tools_dashboard_format_date($data['weekly']['announcement_date'])); ?></p>
                <p class="surfside-dashboard-detail"><strong>Sermon notes:</strong> <?php echo esc_html($data['weekly']['message_title'] ?: 'Not published yet'); ?></p>
                <div class="surfside-staff-actions"><a class="surfside-staff-button" href="<?php echo esc_url(surfside_tools_staff_page_url('weekly-update')); ?>">Update Weekly Content <span class="surfside-staff-arrow">›</span></a></div>
            </article>

            <article class="surfside-dashboard-status-card">
                <div class="surfside-dashboard-status-head"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('calendar'); ?></span><h3>Calendar</h3></div>
                <div class="surfside-dashboard-stat"><?php echo esc_html($data['calendar']['upcoming_count']); ?> active events</div>
                <p class="surfside-dashboard-detail"><strong>Next event:</strong><br><?php echo esc_html(surfside_tools_dashboard_next_event_text($data['calendar']['next'])); ?></p>
                <div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url('calendar')); ?>">Manage Calendar <span class="surfside-staff-arrow">›</span></a></div>
            </article>

            <article class="surfside-dashboard-status-card">
                <div class="surfside-dashboard-status-head"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('document'); ?></span><h3>Homepage</h3></div>
                <div class="surfside-dashboard-stat"><?php echo esc_html($data['homepage']['photo_count']); ?> photos</div>
                <p class="surfside-dashboard-detail"><strong>Last updated:</strong> <?php echo $data['homepage']['last_updated'] ? esc_html(wp_date('F j, Y g:i A', $data['homepage']['last_updated'])) : 'No update date recorded'; ?></p>
                <div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url('homepage')); ?>">Manage Homepage <span class="surfside-staff-arrow">›</span></a></div>
            </article>

            <article class="surfside-dashboard-status-card">
                <div class="surfside-dashboard-status-head"><span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('settings'); ?></span><h3>Settings</h3></div>
                <p class="surfside-dashboard-detail"><strong>Google Places:</strong> <span class="<?php echo $data['settings']['google_maps_connected'] ? 'surfside-dashboard-status-good' : 'surfside-dashboard-status-neutral'; ?>"><?php echo $data['settings']['google_maps_connected'] ? 'Connected' : 'Not configured'; ?></span></p>
                <p class="surfside-dashboard-detail"><strong>Saved Places:</strong> <?php echo esc_html($data['settings']['saved_places_count']); ?></p>
                <p class="surfside-dashboard-detail"><strong>Visual CSS overrides:</strong> <?php echo $data['settings']['visual_css_enabled'] ? 'Enabled' : 'Using defaults'; ?></p>
                <div class="surfside-staff-actions"><a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url('settings')); ?>">Open Settings <span class="surfside-staff-arrow">›</span></a></div>
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
    add_shortcode('surfside_staff_dashboard', 'surfside_tools_dashboard_intelligence_shortcode');
}, 40);
