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

    $weekly_timestamp = absint($announcements['timestamp'] ?? 0);
    if (!$weekly_timestamp && !empty($announcements['announcement_date'])) {
        $parsed = strtotime((string) $announcements['announcement_date']);
        $weekly_timestamp = $parsed ?: 0;
    }

    $homepage_images = function_exists('surfside_tools_homepage_get_images') ? (array) surfside_tools_homepage_get_images() : array();
    $homepage_updated = 0;
    foreach ($homepage_images as $image) {
        $homepage_updated = max($homepage_updated, absint(is_array($image) ? ($image['updated'] ?? 0) : 0));
    }

    $today = wp_date('Y-m-d');
    $thirty_days = wp_date('Y-m-d', current_time('timestamp') + (DAY_IN_SECONDS * 30));
    $range_end = wp_date('Y-m-d', current_time('timestamp') + (DAY_IN_SECONDS * 366));
    $occurrences = array();
    $active_event_ids = array();
    $occurrence_count_30 = 0;

    if (function_exists('surfside_tools_calendar_get_all_events') && function_exists('surfside_tools_calendar_event_occurrences')) {
        foreach (surfside_tools_calendar_get_all_events() as $event) {
            $event_occurrences = surfside_tools_calendar_event_occurrences($event, $today, $range_end);
            if (!$event_occurrences) {
                continue;
            }

            $active_event_ids[absint($event['id'] ?? 0)] = true;
            foreach ($event_occurrences as $occurrence) {
                $occurrences[] = $occurrence;
                $occurrence_date = (string) ($occurrence['occurrence_date'] ?? $occurrence['date'] ?? '');
                if ($occurrence_date !== '' && $occurrence_date <= $thirty_days) {
                    $occurrence_count_30++;
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
            'published_timestamp' => $weekly_timestamp,
            'message_title' => (string) ($message['title'] ?? ''),
            'message_date' => (string) ($message['date'] ?? ''),
        ),
        'calendar' => array(
            'upcoming_count' => count(array_filter(array_keys($active_event_ids))),
            'occurrence_count_30' => $occurrence_count_30,
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

function surfside_tools_dashboard_evaluate_status($data) {
    $urls = array(
        'weekly' => surfside_tools_staff_page_url('weekly-update'),
        'calendar' => surfside_tools_staff_page_url('calendar'),
        'homepage' => surfside_tools_staff_page_url('homepage'),
        'settings' => surfside_tools_staff_page_url('settings'),
    );

    $now = current_time('timestamp');
    $weekday = (int) wp_date('N', $now);
    $monday = strtotime('monday this week 00:00:00', $now);
    $weekly_current = !empty($data['weekly']['published_timestamp']) && $data['weekly']['published_timestamp'] >= $monday;

    if ($weekly_current) {
        $weekly = array('level' => 'good', 'label' => 'Current', 'message' => 'This week’s content has been published.');
    } elseif ($weekday === 1) {
        $weekly = array('level' => 'warning', 'label' => 'Attention', 'message' => 'Weekly content became stale today. Prepare this week’s update.');
    } else {
        $weekly = array('level' => 'critical', 'label' => 'Action required', 'message' => 'Weekly content is still from last week.');
    }

    $next_event_date = '';
    if (!empty($data['calendar']['next'])) {
        $next_event_date = (string) ($data['calendar']['next']['occurrence_date'] ?? $data['calendar']['next']['date'] ?? '');
    }
    if (empty($data['calendar']['upcoming_count']) || $next_event_date === '') {
        $calendar = array('level' => 'critical', 'label' => 'Action required', 'message' => 'The calendar has no future events.');
    } elseif (strtotime($next_event_date) > strtotime('+14 days', $now)) {
        $calendar = array('level' => 'warning', 'label' => 'Attention', 'message' => 'There are no events scheduled in the next 14 days.');
    } else {
        $calendar = array('level' => 'good', 'label' => 'Healthy', 'message' => 'Upcoming events are available.');
    }

    $photo_count = absint($data['homepage']['photo_count'] ?? 0);
    if ($photo_count === 0) {
        $homepage = array('level' => 'critical', 'label' => 'Action required', 'message' => 'The homepage carousel has no photos.');
    } elseif ($photo_count < 5) {
        $homepage = array('level' => 'warning', 'label' => 'Attention', 'message' => 'The homepage carousel has fewer than five photos.');
    } else {
        $homepage = array('level' => 'good', 'label' => 'Healthy', 'message' => 'The homepage carousel has a healthy photo selection.');
    }

    if (empty($data['settings']['google_maps_connected'])) {
        $settings = array('level' => 'critical', 'label' => 'Action required', 'message' => 'Google Places is not configured.');
    } else {
        $settings = array('level' => 'good', 'label' => 'Configured', 'message' => 'Google Places is connected.');
    }

    $statuses = array(
        'weekly' => $weekly + array('url' => $urls['weekly']),
        'calendar' => $calendar + array('url' => $urls['calendar']),
        'homepage' => $homepage + array('url' => $urls['homepage']),
        'settings' => $settings + array('url' => $urls['settings']),
    );

    $alerts = array();
    foreach ($statuses as $key => $status) {
        if ($status['level'] !== 'good') {
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

function surfside_tools_dashboard_status_badge($status) {
    $level = sanitize_html_class($status['level'] ?? 'good');
    $label = (string) ($status['label'] ?? 'Healthy');
    return '<span class="surfside-dashboard-health surfside-dashboard-health-' . esc_attr($level) . '"><span aria-hidden="true"></span>' . esc_html($label) . '</span>';
}

function surfside_tools_dashboard_intelligence_styles() {
    wp_add_inline_style('surfside-tools-staff-dashboard', '
        .surfside-dashboard-greeting{margin:0 0 24px}.surfside-dashboard-greeting h2{margin:0 0 6px;font-size:clamp(27px,4vw,38px);letter-spacing:-.035em;color:#071b3a}.surfside-dashboard-summary{margin:0 0 28px;padding:22px 24px;border-radius:18px;border:1px solid}.surfside-dashboard-summary-good{background:#effaf2;border-color:#a4d8b0;color:#155f2b}.surfside-dashboard-summary-attention{background:#fff8e8;border-color:#e8c979;color:#694a00}.surfside-dashboard-summary h3{margin:0 0 8px;font-size:24px;color:inherit}.surfside-dashboard-alert-list{display:grid;gap:8px;margin:14px 0 0;padding:0;list-style:none}.surfside-dashboard-alert-list a{display:flex;align-items:center;gap:9px;color:inherit;font-weight:700;text-decoration:none}.surfside-dashboard-alert-list a:hover{text-decoration:underline}.surfside-dashboard-alert-dot{width:9px;height:9px;border-radius:999px;background:#c07a00;flex:0 0 auto}.surfside-dashboard-alert-critical .surfside-dashboard-alert-dot{background:#b42318}.surfside-dashboard-section-title{margin:0 0 16px;font-size:clamp(22px,3vw,30px);color:#071b3a}.surfside-dashboard-status-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-bottom:34px}.surfside-dashboard-status-card{display:flex;flex-direction:column;min-height:245px;padding:24px;border:1px solid rgba(7,27,58,.12);border-radius:18px;background:#fff;box-shadow:0 10px 26px rgba(7,27,58,.065)}.surfside-dashboard-status-card-warning{border-color:#e4c46a;background:#fffdf7}.surfside-dashboard-status-card-critical{border-color:#e1a29d;background:#fffafa}.surfside-dashboard-status-head{display:flex;align-items:center;gap:13px;margin-bottom:13px}.surfside-dashboard-status-head .surfside-staff-icon{width:48px;height:48px}.surfside-dashboard-status-head .surfside-staff-icon svg{width:25px;height:25px}.surfside-dashboard-status-card h3{margin:0;font-size:23px;letter-spacing:-.025em;color:#071b3a}.surfside-dashboard-health{display:inline-flex;align-items:center;gap:7px;width:max-content;margin:0 0 14px;padding:5px 10px;border-radius:999px;font-size:13px;font-weight:800}.surfside-dashboard-health span{width:8px;height:8px;border-radius:999px;background:currentColor}.surfside-dashboard-health-good{background:#e8f7ed;color:#14783a}.surfside-dashboard-health-warning{background:#fff1c9;color:#8a5a00}.surfside-dashboard-health-critical{background:#fde9e7;color:#a32a21}.surfside-dashboard-stat{font-size:30px;line-height:1;font-weight:800;color:#071b3a;margin-bottom:8px}.surfside-dashboard-detail{margin:5px 0;color:#46526a;line-height:1.45}.surfside-dashboard-detail strong{color:#071b3a}.surfside-dashboard-status-message{margin:8px 0 0;font-weight:700;color:#46526a}.surfside-dashboard-status-card .surfside-staff-actions{margin-top:auto;padding-top:18px}.surfside-dashboard-quick-actions{margin-top:10px}.surfside-dashboard-quick-actions .surfside-staff-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.surfside-dashboard-quick-actions .surfside-staff-card{min-height:230px}.surfside-dashboard-status-good{display:inline-flex;align-items:center;gap:6px;color:#148944;font-weight:700}.surfside-dashboard-status-neutral{color:#637086;font-weight:700}@media(max-width:1000px){.surfside-dashboard-quick-actions .surfside-staff-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.surfside-dashboard-status-grid,.surfside-dashboard-quick-actions .surfside-staff-grid{grid-template-columns:1fr}.surfside-dashboard-status-card{min-height:auto}}
    ');
}
