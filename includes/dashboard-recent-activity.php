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
        'occurrence_count_30' => absint($data['calendar']['occurrence_count_30'] ?? 0),
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
