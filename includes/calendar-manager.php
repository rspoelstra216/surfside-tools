<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Surfside Tools - Calendar Manager
 * Phase 4: Local event source of truth with recurrence foundation and shared occurrence engine.
 */

function surfside_tools_calendar_register_event_type() {
    register_post_type('surfside_event', array(
        'labels' => array(
            'name' => 'Surfside Events',
            'singular_name' => 'Surfside Event',
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'supports' => array('title', 'editor'),
        'capability_type' => 'post',
    ));
    register_post_type('surfside_location', array(
        'labels' => array('name' => 'Surfside Locations', 'singular_name' => 'Surfside Location'),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'supports' => array('title'),
        'capability_type' => 'post',
    ));
}
add_action('init', 'surfside_tools_calendar_register_event_type');

function surfside_tools_calendar_get_saved_locations() {
    $query = new WP_Query(array('post_type'=>'surfside_location','post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','no_found_rows'=>true));
    $locations = array();
    foreach ($query->posts as $post) {
        $locations[] = array('id'=>(int)$post->ID,'name'=>get_the_title($post->ID),'address'=>get_post_meta($post->ID,'_surfside_location_address',true),'notes'=>get_post_meta($post->ID,'_surfside_location_notes',true));
    }
    wp_reset_postdata();
    return $locations;
}

function surfside_tools_calendar_save_location($name, $address, $notes = '') {
    $name = sanitize_text_field($name); $address = sanitize_text_field($address); $notes = sanitize_textarea_field($notes);
    if ($name === '') return new WP_Error('missing_location_name','Please enter a location name.');
    $location_id = wp_insert_post(array('post_type'=>'surfside_location','post_status'=>'publish','post_title'=>$name), true);
    if (is_wp_error($location_id)) return $location_id;
    update_post_meta($location_id,'_surfside_location_address',$address);
    update_post_meta($location_id,'_surfside_location_notes',$notes);
    return $location_id;
}

function surfside_tools_calendar_date_for_input($value) {
    $value = sanitize_text_field((string) $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    return '';
}

function surfside_tools_calendar_time_for_input($value) {
    $value = sanitize_text_field((string) $value);
    if ($value === '' || preg_match('/^\d{2}:\d{2}$/', $value)) {
        return $value;
    }
    return '';
}

function surfside_tools_calendar_get_event($event_id) {
    $event_id = absint($event_id);
    if (!$event_id) {
        return null;
    }

    $post = get_post($event_id);
    if (!$post || $post->post_type !== 'surfside_event' || $post->post_status === 'trash') {
        return null;
    }

    return array(
        'id' => $event_id,
        'title' => get_the_title($event_id),
        'description' => $post->post_content,
        'date' => get_post_meta($event_id, '_surfside_event_date', true),
        'end_date' => get_post_meta($event_id, '_surfside_event_end_date', true),
        'start_time' => get_post_meta($event_id, '_surfside_event_start_time', true),
        'end_time' => get_post_meta($event_id, '_surfside_event_end_time', true),
        'location' => get_post_meta($event_id, '_surfside_event_location_name', true) ?: get_post_meta($event_id, '_surfside_event_location', true),
        'location_name' => get_post_meta($event_id, '_surfside_event_location_name', true) ?: get_post_meta($event_id, '_surfside_event_location', true),
        'location_address' => get_post_meta($event_id, '_surfside_event_location_address', true),
        'location_id' => absint(get_post_meta($event_id, '_surfside_event_location_id', true)),
        'location_place_id' => get_post_meta($event_id, '_surfside_event_location_place_id', true),
        'location_lat' => get_post_meta($event_id, '_surfside_event_location_lat', true),
        'location_lng' => get_post_meta($event_id, '_surfside_event_location_lng', true),
        'location_maps_url' => get_post_meta($event_id, '_surfside_event_location_maps_url', true),
        'all_day' => (bool) get_post_meta($event_id, '_surfside_event_all_day', true),
        'featured' => (bool) get_post_meta($event_id, '_surfside_event_featured', true),
        'recurrence_type' => get_post_meta($event_id, '_surfside_event_recurrence_type', true) ?: 'none',
        'recurrence_interval' => max(1, absint(get_post_meta($event_id, '_surfside_event_recurrence_interval', true))),
        'recurrence_weekdays' => (array) get_post_meta($event_id, '_surfside_event_recurrence_weekdays', true),
        'recurrence_day_of_month' => absint(get_post_meta($event_id, '_surfside_event_recurrence_day_of_month', true)),
        'recurrence_week_of_month' => absint(get_post_meta($event_id, '_surfside_event_recurrence_week_of_month', true)),
        'recurrence_weekday' => absint(get_post_meta($event_id, '_surfside_event_recurrence_weekday', true)),
        'recurrence_end_date' => get_post_meta($event_id, '_surfside_event_recurrence_end_date', true),
    );
}

function surfside_tools_calendar_get_all_events() {
    $query = new WP_Query(array(
        'post_type' => 'surfside_event', 'post_status' => 'publish', 'posts_per_page' => -1,
        'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true,
    ));
    $events = array();
    foreach ($query->posts as $post) {
        $event = surfside_tools_calendar_get_event($post->ID);
        if ($event) { $events[] = $event; }
    }
    wp_reset_postdata();
    return $events;
}

function surfside_tools_calendar_event_occurrences($event, $range_start, $range_end) {
    $out = array();
    if (empty($event['date'])) { return $out; }
    try {
        $start = new DateTimeImmutable($event['date']);
        $from = new DateTimeImmutable($range_start);
        $to = new DateTimeImmutable($range_end);
    } catch (Exception $e) { return $out; }
    $type = !empty($event['recurrence_type']) ? $event['recurrence_type'] : 'none';
    $interval = max(1, absint($event['recurrence_interval'] ?? 1));
    $until = !empty($event['recurrence_end_date']) ? new DateTimeImmutable($event['recurrence_end_date']) : $to;
    if ($until > $to) { $until = $to; }
    if ($start > $until) { return $out; }
    $add = function($date) use (&$out, $event, $from, $until) {
        if ($date >= $from && $date <= $until) {
            $occ = $event; $occ['event_start_date'] = $event['date']; $occ['date'] = $date->format('Y-m-d'); $occ['occurrence_date'] = $occ['date']; $out[] = $occ;
        }
    };
    if ($type === 'none') {
        $multi_day_end = !empty($event['end_date']) ? new DateTimeImmutable($event['end_date']) : null;
        if ($multi_day_end && $multi_day_end >= $start) {
            $until = $multi_day_end > $to ? $to : $multi_day_end;
            $cursor = $start < $from ? $from : $start;
            while ($cursor <= $until) {
                $add($cursor);
                $cursor = $cursor->modify('+1 day');
            }
            return $out;
        }
        $add($start);
        return $out;
    }
    if ($type === 'daily') {
        $days = array_map('intval', (array)($event['recurrence_weekdays'] ?? array()));
        $cursor = $start;
        while ($cursor <= $until) {
            $days_since_start = (int) $start->diff($cursor)->days;
            $weekday_ok = !$days || in_array((int)$cursor->format('N'), $days, true);
            if ($days_since_start % $interval === 0 && $weekday_ok) { $add($cursor); }
            $cursor = $cursor->modify('+1 day');
        }
    } elseif ($type === 'weekly') {
        $days = array_map('intval', (array)($event['recurrence_weekdays'] ?? array()));
        if (!$days) { $days = array((int)$start->format('N')); }
        $cursor = $start;
        while ($cursor <= $until) {
            $weeks = intdiv((int)$start->diff($cursor)->days, 7);
            if ($weeks % $interval === 0 && in_array((int)$cursor->format('N'), $days, true)) { $add($cursor); }
            $cursor = $cursor->modify('+1 day');
        }
    } elseif ($type === 'monthly_date') {
        $day = absint($event['recurrence_day_of_month'] ?? 0) ?: (int)$start->format('j');
        $cursor = new DateTimeImmutable($start->format('Y-m-01'));
        $month_index = 0;
        while ($cursor <= $until) {
            if ($month_index % $interval === 0) {
                $last = (int)$cursor->format('t');
                if ($day <= $last) { $candidate = $cursor->setDate((int)$cursor->format('Y'), (int)$cursor->format('m'), $day); if ($candidate >= $start) { $add($candidate); } }
            }
            $cursor = $cursor->modify('first day of next month'); $month_index++;
        }
    } elseif ($type === 'monthly_weekday') {
        $week = absint($event['recurrence_week_of_month'] ?? 0) ?: 1;
        $weekday = absint($event['recurrence_weekday'] ?? 0) ?: (int)$start->format('N');
        $cursor = new DateTimeImmutable($start->format('Y-m-01')); $month_index = 0;
        while ($cursor <= $until) {
            if ($month_index % $interval === 0) {
                $offset = ($weekday - (int)$cursor->format('N') + 7) % 7;
                $candidate = $cursor->modify('+' . ($offset + (($week - 1) * 7)) . ' days');
                if ($candidate->format('m') === $cursor->format('m') && $candidate >= $start) { $add($candidate); }
            }
            $cursor = $cursor->modify('first day of next month'); $month_index++;
        }
    }
    return $out;
}

function surfside_tools_calendar_get_occurrences($range_start, $range_end, $limit = 0) {
    $occurrences = array();
    foreach (surfside_tools_calendar_get_all_events() as $event) {
        $occurrences = array_merge($occurrences, surfside_tools_calendar_event_occurrences($event, $range_start, $range_end));
    }
    usort($occurrences, function($a, $b) {
        $ak = $a['date'] . ' ' . ($a['start_time'] ?: '00:00'); $bk = $b['date'] . ' ' . ($b['start_time'] ?: '00:00');
        return strcmp($ak, $bk);
    });
    return $limit > 0 ? array_slice($occurrences, 0, $limit) : $occurrences;
}


function surfside_tools_calendar_get_manage_events($search = '', $page = 1, $per_page = 20) {
    $search = trim((string) $search);
    $page = max(1, absint($page));
    $per_page = max(1, absint($per_page));
    $today = current_time('Y-m-d');
    $range_end = date('Y-m-d', strtotime($today . ' +5 years'));
    $rows = array();

    foreach (surfside_tools_calendar_get_all_events() as $event) {
        $haystack = strtolower(trim(($event['title'] ?? '') . ' ' . ($event['location_name'] ?? '') . ' ' . ($event['location_address'] ?? '')));
        if ($search !== '' && strpos($haystack, strtolower($search)) === false) {
            continue;
        }

        $occurrences = surfside_tools_calendar_event_occurrences($event, $today, $range_end);
        $event['next_occurrence_date'] = !empty($occurrences[0]['date']) ? $occurrences[0]['date'] : '';
        $rows[] = $event;
    }

    usort($rows, function($a, $b) {
        $a_date = !empty($a['next_occurrence_date']) ? $a['next_occurrence_date'] : '9999-12-31';
        $b_date = !empty($b['next_occurrence_date']) ? $b['next_occurrence_date'] : '9999-12-31';
        $date_compare = strcmp($a_date, $b_date);
        if ($date_compare !== 0) {
            return $date_compare;
        }
        return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
    });

    $total = count($rows);
    $total_pages = max(1, (int) ceil($total / $per_page));
    if ($page > $total_pages) {
        $page = $total_pages;
    }
    $offset = ($page - 1) * $per_page;

    return array(
        'events' => array_slice($rows, $offset, $per_page),
        'total' => $total,
        'page' => $page,
        'total_pages' => $total_pages,
    );
}

function surfside_tools_calendar_get_upcoming_events($limit = 12) {
    $today = current_time('Y-m-d');
    $end = date('Y-m-d', strtotime($today . ' +2 years'));
    return surfside_tools_calendar_get_occurrences($today, $end, absint($limit));
}

function surfside_tools_calendar_get_past_events($limit = 8) {
    $today = current_time('Y-m-d');
    $start = date('Y-m-d', strtotime($today . ' -1 year'));
    $events = surfside_tools_calendar_get_occurrences($start, date('Y-m-d', strtotime($today . ' -1 day')));
    $events = array_reverse($events);
    return array_slice($events, 0, absint($limit));
}

function surfside_tools_calendar_recurrence_label($event) {
    $type = $event['recurrence_type'] ?? 'none';
    if ($type === 'daily') {
        $days = array_map('intval', (array)($event['recurrence_weekdays'] ?? array()));
        if ($days === array(1,2,3,4,5)) { return 'Repeats weekdays'; }
        return 'Repeats daily';
    }
    if ($type === 'weekly') return 'Repeats weekly';
    if ($type === 'monthly_date') return 'Repeats monthly on day ' . ($event['recurrence_day_of_month'] ?: date('j', strtotime($event['date'])));
    if ($type === 'monthly_weekday') {
        $weeks = array(1=>'first',2=>'second',3=>'third',4=>'fourth',5=>'fifth');
        $days = array(1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday');
        return 'Repeats monthly on the ' . ($weeks[$event['recurrence_week_of_month']] ?? 'first') . ' ' . ($days[$event['recurrence_weekday']] ?? 'day');
    }
    return '';
}

function surfside_tools_calendar_next_event_label($events) {
    if (empty($events[0])) {
        return 'No upcoming events';
    }
    $event = $events[0];
    return $event['title'] . ' — ' . surfside_tools_calendar_format_date($event['date']);
}

function surfside_tools_calendar_format_date($date) {
    if (!$date) {
        return 'Date not set';
    }
    $timestamp = strtotime($date . ' 12:00:00');
    return $timestamp ? date_i18n('F j, Y', $timestamp) : $date;
}

function surfside_tools_calendar_format_event_dates($event) {
    $start = !empty($event['event_start_date']) ? $event['event_start_date'] : ($event['date'] ?? '');
    $end = $event['end_date'] ?? '';
    if ($start && $end && $end > $start) {
        return surfside_tools_calendar_format_date($start) . '–' . surfside_tools_calendar_format_date($end);
    }
    return surfside_tools_calendar_format_date($event['date'] ?? $start);
}

function surfside_tools_calendar_format_time_range($event) {
    if (!empty($event['all_day'])) {
        return 'All day';
    }

    $start = !empty($event['start_time']) ? date_i18n('g:i A', strtotime($event['start_time'])) : '';
    $end = !empty($event['end_time']) ? date_i18n('g:i A', strtotime($event['end_time'])) : '';

    if ($start && $end) {
        return $start . '–' . $end;
    }
    if ($start) {
        return $start;
    }
    return 'Time not set';
}

function surfside_tools_calendar_handle_submission() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return '';
    }

    if (empty($_POST['surfside_calendar_action'])) {
        return '';
    }

    $action = sanitize_key($_POST['surfside_calendar_action']);

    if (!isset($_POST['surfside_calendar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_calendar_nonce'])), 'surfside_calendar_manager')) {
        return '<div class="surfside-calendar-notice surfside-calendar-error">Security check failed. Please refresh and try again.</div>';
    }

    if ($action === 'save_location') {
        $location_name = isset($_POST['location_name']) ? sanitize_text_field(wp_unslash($_POST['location_name'])) : '';
        $location_address = isset($_POST['location_address']) ? sanitize_text_field(wp_unslash($_POST['location_address'])) : '';
        $location_notes = isset($_POST['location_notes']) ? sanitize_textarea_field(wp_unslash($_POST['location_notes'])) : '';
        $location_id = surfside_tools_calendar_save_location($location_name, $location_address, $location_notes);
        if (is_wp_error($location_id)) return '<div class="surfside-calendar-notice surfside-calendar-error">' . esc_html($location_id->get_error_message()) . '</div>';
        return '<div class="surfside-calendar-notice surfside-calendar-success">Location saved. You can now select it below.</div>';
    }

    if ($action === 'delete') {
        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $event = surfside_tools_calendar_get_event($event_id);
        if (!$event) {
            return '<div class="surfside-calendar-notice surfside-calendar-error">That event could not be found.</div>';
        }
        wp_trash_post($event_id);
        return '<div class="surfside-calendar-notice surfside-calendar-success">Event moved to trash.</div>';
    }

    if ($action !== 'save') {
        return '';
    }

    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $title = isset($_POST['event_title']) ? sanitize_text_field(wp_unslash($_POST['event_title'])) : '';
    $date = isset($_POST['event_date']) ? surfside_tools_calendar_date_for_input(wp_unslash($_POST['event_date'])) : '';
    $spans_multiple_days = !empty($_POST['event_spans_multiple_days']);
    $event_end_date = $spans_multiple_days && isset($_POST['event_end_date']) ? surfside_tools_calendar_date_for_input(wp_unslash($_POST['event_end_date'])) : '';
    $all_day = !empty($_POST['event_all_day']) ? 1 : 0;
    $start_time = $all_day ? '' : (isset($_POST['event_start_time']) ? surfside_tools_calendar_time_for_input(wp_unslash($_POST['event_start_time'])) : '');
    $end_time = $all_day ? '' : (isset($_POST['event_end_time']) ? surfside_tools_calendar_time_for_input(wp_unslash($_POST['event_end_time'])) : '');
    $location_name = isset($_POST['event_location_name']) ? sanitize_text_field(wp_unslash($_POST['event_location_name'])) : '';
    $location_address = isset($_POST['event_location_address']) ? sanitize_text_field(wp_unslash($_POST['event_location_address'])) : '';
    $location_id = isset($_POST['event_location_id']) ? absint($_POST['event_location_id']) : 0;
    $location_place_id = isset($_POST['event_location_place_id']) ? sanitize_text_field(wp_unslash($_POST['event_location_place_id'])) : '';
    $location_lat = isset($_POST['event_location_lat']) ? sanitize_text_field(wp_unslash($_POST['event_location_lat'])) : '';
    $location_lng = isset($_POST['event_location_lng']) ? sanitize_text_field(wp_unslash($_POST['event_location_lng'])) : '';
    $location_maps_url = isset($_POST['event_location_maps_url']) ? esc_url_raw(wp_unslash($_POST['event_location_maps_url'])) : '';
    $location = $location_name;
    $description = isset($_POST['event_description']) ? wp_kses_post(wp_unslash($_POST['event_description'])) : '';
    $featured = !empty($_POST['event_featured']) ? 1 : 0;
    $recurrence_type = isset($_POST['event_recurrence_type']) ? sanitize_key(wp_unslash($_POST['event_recurrence_type'])) : 'none';
    if (!in_array($recurrence_type, array('none','daily','weekly','monthly_date','monthly_weekday'), true)) { $recurrence_type = 'none'; }
    if ($spans_multiple_days) { $recurrence_type = 'none'; }
    $recurrence_interval = max(1, isset($_POST['event_recurrence_interval']) ? absint($_POST['event_recurrence_interval']) : 1);
    $recurrence_weekdays = isset($_POST['event_recurrence_weekdays']) ? array_values(array_intersect(array_map('absint', (array) $_POST['event_recurrence_weekdays']), range(1,7))) : array();
    $recurrence_day_of_month = isset($_POST['event_recurrence_day_of_month']) ? min(31, max(1, absint($_POST['event_recurrence_day_of_month']))) : 0;
    $recurrence_week_of_month = isset($_POST['event_recurrence_week_of_month']) ? min(5, max(1, absint($_POST['event_recurrence_week_of_month']))) : 1;
    $recurrence_weekday = isset($_POST['event_recurrence_weekday']) ? min(7, max(1, absint($_POST['event_recurrence_weekday']))) : 1;
    $recurrence_end_date = isset($_POST['event_recurrence_end_date']) ? surfside_tools_calendar_date_for_input(wp_unslash($_POST['event_recurrence_end_date'])) : '';

    if ($title === '' || $date === '') {
        return '<div class="surfside-calendar-notice surfside-calendar-error">Please enter at least an event title and date.</div>';
    }
    if ($spans_multiple_days && ($event_end_date === '' || $event_end_date <= $date)) {
        return '<div class="surfside-calendar-notice surfside-calendar-error">Please choose an end date after the start date.</div>';
    }

    $post_data = array(
        'post_title' => $title,
        'post_content' => $description,
        'post_status' => 'publish',
        'post_type' => 'surfside_event',
    );

    if ($event_id) {
        $existing = surfside_tools_calendar_get_event($event_id);
        if (!$existing) {
            return '<div class="surfside-calendar-notice surfside-calendar-error">That event could not be found.</div>';
        }
        $post_data['ID'] = $event_id;
        $saved_id = wp_update_post($post_data, true);
    } else {
        $saved_id = wp_insert_post($post_data, true);
    }

    if (is_wp_error($saved_id)) {
        return '<div class="surfside-calendar-notice surfside-calendar-error">Unable to save event: ' . esc_html($saved_id->get_error_message()) . '</div>';
    }

    update_post_meta($saved_id, '_surfside_event_date', $date);
    update_post_meta($saved_id, '_surfside_event_end_date', $event_end_date);
    update_post_meta($saved_id, '_surfside_event_start_time', $start_time);
    update_post_meta($saved_id, '_surfside_event_end_time', $end_time);
    update_post_meta($saved_id, '_surfside_event_location', $location_name); // Legacy compatibility.
    update_post_meta($saved_id, '_surfside_event_location_name', $location_name);
    update_post_meta($saved_id, '_surfside_event_location_address', $location_address);
    update_post_meta($saved_id, '_surfside_event_location_id', $location_id);
    update_post_meta($saved_id, '_surfside_event_location_place_id', $location_place_id);
    update_post_meta($saved_id, '_surfside_event_location_lat', $location_lat);
    update_post_meta($saved_id, '_surfside_event_location_lng', $location_lng);
    update_post_meta($saved_id, '_surfside_event_location_maps_url', $location_maps_url);
    update_post_meta($saved_id, '_surfside_event_all_day', $all_day);
    update_post_meta($saved_id, '_surfside_event_featured', $featured);
    update_post_meta($saved_id, '_surfside_event_recurrence_type', $recurrence_type);
    update_post_meta($saved_id, '_surfside_event_recurrence_interval', $recurrence_interval);
    update_post_meta($saved_id, '_surfside_event_recurrence_weekdays', $recurrence_weekdays);
    update_post_meta($saved_id, '_surfside_event_recurrence_day_of_month', $recurrence_day_of_month);
    update_post_meta($saved_id, '_surfside_event_recurrence_week_of_month', $recurrence_week_of_month);
    update_post_meta($saved_id, '_surfside_event_recurrence_weekday', $recurrence_weekday);
    update_post_meta($saved_id, '_surfside_event_recurrence_end_date', $recurrence_end_date);

    return '<div class="surfside-calendar-notice surfside-calendar-success">Event saved.</div>';
}

function surfside_tools_calendar_manager_shortcode() {
    if (function_exists('surfside_tools_prevent_cache')) {
        surfside_tools_prevent_cache();
    }

    if (function_exists('surfside_tools_staff_enqueue_styles')) {
        surfside_tools_staff_enqueue_styles();
    }

    surfside_tools_calendar_enqueue_styles();
    surfside_tools_calendar_enqueue_google_places();

    if (!is_user_logged_in()) {
        return function_exists('surfside_tools_staff_login_box') ? surfside_tools_staff_login_box('Please log in to access the calendar manager.') : '<p>Please log in to access the calendar manager.</p>';
    }

    if (!current_user_can('upload_files')) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to access the calendar manager.</p></div>';
    }

    $notice = surfside_tools_calendar_handle_submission();
    $edit_id = isset($_GET['edit_event']) ? absint($_GET['edit_event']) : 0;
    $editing = $edit_id ? surfside_tools_calendar_get_event($edit_id) : null;
    $event_search = isset($_GET['event_search']) ? sanitize_text_field(wp_unslash($_GET['event_search'])) : '';
    $event_page = isset($_GET['event_page']) ? max(1, absint($_GET['event_page'])) : 1;
    $managed_events = surfside_tools_calendar_get_manage_events($event_search, $event_page, 20);
    $events = $managed_events['events'];
    $past_events = surfside_tools_calendar_get_past_events(6);
    $saved_locations = surfside_tools_calendar_get_saved_locations();

    $blank = array(
        'id' => 0,
        'title' => '',
        'description' => '',
        'date' => '',
        'end_date' => '',
        'start_time' => '',
        'end_time' => '',
        'location' => '',
        'location_name' => '',
        'location_address' => '',
        'location_id' => 0,
        'location_place_id' => '',
        'location_lat' => '',
        'location_lng' => '',
        'location_maps_url' => '',
        'all_day' => false,
        'featured' => false,
        'recurrence_type' => 'none', 'recurrence_interval' => 1, 'recurrence_weekdays' => array(),
        'recurrence_day_of_month' => 0, 'recurrence_week_of_month' => 1, 'recurrence_weekday' => 1, 'recurrence_end_date' => '',
    );
    $form_event = $editing ? $editing : $blank;

    ob_start();
    ?>
    <div class="surfside-calendar-manager">
        <?php echo $notice; ?>

        <div class="surfside-calendar-note surfside-calendar-status">
            <div>
                <strong>Manage Church Events</strong><br>
                Add, update, and organize the events that appear on the Surfside website.
            </div>
            <div class="surfside-calendar-status-pill"><?php echo esc_html($managed_events['total']); ?> event<?php echo $managed_events['total'] === 1 ? '' : 's'; ?></div>
        </div>

        <div class="surfside-calendar-layout">
            <section class="surfside-calendar-panel">
                <h2><?php echo $editing ? 'Edit Event' : 'Add Event'; ?></h2>
                <form method="post" class="surfside-calendar-form" data-default-duration="<?php echo esc_attr(function_exists('surfside_tools_get_setting') ? (int) surfside_tools_get_setting('default_event_duration', 60) : 60); ?>">
                    <?php wp_nonce_field('surfside_calendar_manager', 'surfside_calendar_nonce'); ?>
                    <input type="hidden" name="surfside_calendar_action" value="save">
                    <input type="hidden" name="event_id" value="<?php echo esc_attr($form_event['id']); ?>">

                    <label>
                        <span>Event Title</span>
                        <input type="text" name="event_title" value="<?php echo esc_attr($form_event['title']); ?>" required>
                    </label>

                    <div class="surfside-calendar-form-row">
                        <label>
                            <span>Start Date</span>
                            <input type="date" name="event_date" value="<?php echo esc_attr($form_event['date']); ?>" required>
                        </label>
                        <label class="surfside-calendar-checkbox">
                            <input type="checkbox" name="event_all_day" value="1" <?php checked(!empty($form_event['all_day'])); ?>>
                            <span>All day</span>
                        </label>
                    </div>

                    <label class="surfside-calendar-checkbox">
                        <input type="checkbox" name="event_spans_multiple_days" value="1" data-surfside-multi-day-toggle aria-controls="surfside-event-end-date" aria-expanded="<?php echo !empty($form_event['end_date']) ? 'true' : 'false'; ?>" <?php checked(!empty($form_event['end_date'])); ?>>
                        <span>This event lasts multiple days</span>
                    </label>
                    <div id="surfside-event-end-date" data-surfside-multi-day-fields <?php echo empty($form_event['end_date']) ? 'hidden' : ''; ?>>
                        <label>
                            <span>End Date</span>
                            <input type="date" name="event_end_date" value="<?php echo esc_attr($form_event['end_date']); ?>" min="<?php echo esc_attr($form_event['date']); ?>">
                        </label>
                        <p class="surfside-location-help">The event will appear on every calendar day through the end date. Multi-day events do not use recurrence.</p>
                    </div>

                    <div class="surfside-calendar-form-row">
                        <label>
                            <span>Start Time</span>
                            <input type="time" name="event_start_time" value="<?php echo esc_attr($form_event['start_time']); ?>">
                        </label>
                        <label>
                            <span>End Time</span>
                            <input type="time" name="event_end_time" value="<?php echo esc_attr($form_event['end_time']); ?>">
                        </label>
                    </div>

                    <div class="surfside-location-picker surfside-google-location-picker" data-surfside-google-location-picker>
                        <label>
                            <span>Location</span>
                            <input type="search" class="surfside-location-search" data-surfside-google-place placeholder="Start typing a place, church, restaurant, or address..." autocomplete="off" value="<?php echo esc_attr($form_event['location_name'] ?? $form_event['location']); ?>">
                        </label>
                        <p class="surfside-location-help">Choose a Google suggestion to fill the address automatically. For internal locations such as Building 4, enter the details manually below.</p>
                        <p class="surfside-google-status" data-surfside-google-status aria-live="polite">Loading Google Places…</p>
                        <input type="hidden" name="event_location_id" class="surfside-location-id" value="<?php echo esc_attr($form_event['location_id'] ?? 0); ?>">
                        <input type="hidden" name="event_location_place_id" class="surfside-location-place-id" value="<?php echo esc_attr($form_event['location_place_id'] ?? ''); ?>">
                        <input type="hidden" name="event_location_lat" class="surfside-location-lat" value="<?php echo esc_attr($form_event['location_lat'] ?? ''); ?>">
                        <input type="hidden" name="event_location_lng" class="surfside-location-lng" value="<?php echo esc_attr($form_event['location_lng'] ?? ''); ?>">
                        <input type="hidden" name="event_location_maps_url" class="surfside-location-maps-url" value="<?php echo esc_attr($form_event['location_maps_url'] ?? ''); ?>">
                        <div class="surfside-calendar-form-row surfside-location-fields">
                            <label><span>Location Name</span><input type="text" name="event_location_name" class="surfside-location-name" value="<?php echo esc_attr($form_event['location_name'] ?? $form_event['location']); ?>" placeholder="Fellowship Hall, Building 3, Cozy Corner Café, etc."></label>
                            <label><span>Full Address</span><input type="text" name="event_location_address" class="surfside-location-address" value="<?php echo esc_attr($form_event['location_address'] ?? ''); ?>" placeholder="123 Main St, Cocoa, FL 32922"></label>
                        </div>
                        <div class="surfside-location-selected" data-surfside-location-selected <?php echo empty($form_event['location_place_id']) ? 'hidden' : ''; ?>>Google place selected. You can still edit the name or address before saving.</div>
                    </div>

                    <label>
                        <span>Description</span>
                        <textarea name="event_description" rows="5"><?php echo esc_textarea($form_event['description']); ?></textarea>
                    </label>

                    <fieldset class="surfside-calendar-recurrence" data-surfside-recurrence-fields <?php echo !empty($form_event['end_date']) ? 'hidden' : ''; ?>>
                        <legend>Repeats</legend>
                        <label><span>Recurrence</span><select name="event_recurrence_type" class="surfside-recurrence-type">
                            <option value="none" <?php selected($form_event['recurrence_type'], 'none'); ?>>Does not repeat</option>
                            <option value="daily" <?php selected($form_event['recurrence_type'], 'daily'); ?>>Daily / selected days</option>
                            <option value="weekly" <?php selected($form_event['recurrence_type'], 'weekly'); ?>>Weekly</option>
                            <option value="monthly_date" <?php selected($form_event['recurrence_type'], 'monthly_date'); ?>>Monthly on a fixed date</option>
                            <option value="monthly_weekday" <?php selected($form_event['recurrence_type'], 'monthly_weekday'); ?>>Monthly on a fixed weekday</option>
                        </select></label>
                        <div class="surfside-recurrence-options surfside-repeat-daily surfside-repeat-weekly">
                            <span class="surfside-field-label">Repeat on</span><div class="surfside-weekday-pills">
                            <?php foreach (array(1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun') as $num=>$day) : ?>
                                <label><input type="checkbox" name="event_recurrence_weekdays[]" value="<?php echo esc_attr($num); ?>" <?php checked(in_array($num, (array)$form_event['recurrence_weekdays'], true)); ?>><span><?php echo esc_html($day); ?></span></label>
                            <?php endforeach; ?></div>
                        </div>
                        <div class="surfside-recurrence-options surfside-repeat-monthly-date"><label><span>Day of month</span><input type="number" min="1" max="31" name="event_recurrence_day_of_month" value="<?php echo esc_attr($form_event['recurrence_day_of_month'] ?: ($form_event['date'] ? date('j', strtotime($form_event['date'])) : 1)); ?>"></label></div>
                        <div class="surfside-recurrence-options surfside-repeat-monthly-weekday surfside-calendar-form-row">
                            <label><span>Week</span><select name="event_recurrence_week_of_month"><?php foreach(array(1=>'First',2=>'Second',3=>'Third',4=>'Fourth',5=>'Fifth') as $n=>$label): ?><option value="<?php echo $n; ?>" <?php selected($form_event['recurrence_week_of_month'],$n); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                            <label><span>Weekday</span><select name="event_recurrence_weekday"><?php foreach(array(1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday') as $n=>$label): ?><option value="<?php echo $n; ?>" <?php selected($form_event['recurrence_weekday'],$n); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                        </div>
                        <div class="surfside-recurrence-options surfside-repeat-common surfside-calendar-form-row">
                            <label><span>Every</span><input type="number" min="1" max="52" name="event_recurrence_interval" value="<?php echo esc_attr($form_event['recurrence_interval']); ?>"></label>
                            <label><span>Repeat until (optional)</span><input type="date" name="event_recurrence_end_date" value="<?php echo esc_attr($form_event['recurrence_end_date']); ?>"></label>
                        </div>
                    </fieldset>

                    <label class="surfside-calendar-checkbox surfside-calendar-featured-check">
                        <input type="checkbox" name="event_featured" value="1" <?php checked(!empty($form_event['featured'])); ?>>
                        <span>Feature this event</span>
                    </label>

                    <div class="surfside-calendar-form-actions">
                        <button type="submit" class="surfside-staff-button"><?php echo $editing ? 'Save Changes' : 'Add Event'; ?></button>
                        <?php if ($editing) : ?>
                            <a class="surfside-calendar-cancel" href="<?php echo esc_url(remove_query_arg('edit_event')); ?>">Cancel edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="surfside-calendar-panel">
                <div class="surfside-calendar-manage-heading">
                    <div>
                        <h2>Manage Events</h2>
                        <p class="surfside-staff-muted">Search or browse all event records, including events farther in the future.</p>
                    </div>
                    <form method="get" class="surfside-calendar-search-form">
                        <label class="screen-reader-text" for="surfside-event-search">Search events</label>
                        <input id="surfside-event-search" type="search" name="event_search" value="<?php echo esc_attr($event_search); ?>" placeholder="Search events or locations">
                        <button type="submit">Search</button>
                        <?php if ($event_search !== '') : ?><a href="<?php echo esc_url(remove_query_arg(array('event_search','event_page'))); ?>">Clear</a><?php endif; ?>
                    </form>
                </div>
                <?php if (empty($events)) : ?>
                    <p class="surfside-staff-muted">No matching events were found.</p>
                <?php else : ?>
                    <div class="surfside-calendar-event-list">
                        <?php foreach ($events as $event) : ?>
                            <article class="surfside-calendar-event">
                                <div>
                                    <h3><?php echo esc_html($event['title']); ?><?php if (!empty($event['featured'])) : ?> <span class="surfside-calendar-featured-badge">Featured</span><?php endif; ?></h3>
                                    <?php $manage_date = !empty($event['next_occurrence_date']) ? $event['next_occurrence_date'] : $event['date']; ?>
                                    <p><strong><?php echo esc_html(surfside_tools_calendar_format_date($manage_date)); ?></strong> · <?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></p>
                                    <?php if (empty($event['next_occurrence_date'])) : ?><p class="surfside-calendar-recurrence-label">No future occurrences</p><?php endif; ?>
                                    <?php if (surfside_tools_calendar_recurrence_label($event)) : ?><p class="surfside-calendar-recurrence-label"><?php echo esc_html(surfside_tools_calendar_recurrence_label($event)); ?></p><?php endif; ?>
                                    <?php if (!empty($event['location'])) : ?><p><?php echo esc_html($event['location']); ?></p><?php endif; ?>
                                </div>
                                <div class="surfside-calendar-event-actions">
                                    <a href="<?php echo esc_url(add_query_arg('edit_event', $event['id'])); ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Move this event to trash?');">
                                        <?php wp_nonce_field('surfside_calendar_manager', 'surfside_calendar_nonce'); ?>
                                        <input type="hidden" name="surfside_calendar_action" value="delete">
                                        <input type="hidden" name="event_id" value="<?php echo esc_attr($event['id']); ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($managed_events['total_pages'] > 1) : ?>
                        <nav class="surfside-calendar-pagination" aria-label="Event pages">
                            <?php if ($managed_events['page'] > 1) : ?>
                                <a href="<?php echo esc_url(add_query_arg(array('event_page'=>$managed_events['page']-1,'event_search'=>$event_search))); ?>">← Previous</a>
                            <?php endif; ?>
                            <span>Page <?php echo esc_html($managed_events['page']); ?> of <?php echo esc_html($managed_events['total_pages']); ?></span>
                            <?php if ($managed_events['page'] < $managed_events['total_pages']) : ?>
                                <a href="<?php echo esc_url(add_query_arg(array('event_page'=>$managed_events['page']+1,'event_search'=>$event_search))); ?>">Next →</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>

                <h2 class="surfside-calendar-past-heading">Recently Past</h2>
                <?php if (empty($past_events)) : ?>
                    <p class="surfside-staff-muted">No past Surfside Tools events yet.</p>
                <?php else : ?>
                    <div class="surfside-calendar-past-list">
                        <?php foreach ($past_events as $event) : ?>
                            <article class="surfside-calendar-past-event">
                                <strong><?php echo esc_html($event['title']); ?></strong>
                                <span><?php echo esc_html(surfside_tools_calendar_format_date($event['date'])); ?></span>
                                <a href="<?php echo esc_url(add_query_arg('edit_event', $event['id'])); ?>">Edit</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <div class="surfside-location-modal" role="dialog" aria-modal="true" aria-labelledby="surfside-location-modal-title" hidden>
            <div class="surfside-location-modal-backdrop" data-surfside-location-close></div>
            <div class="surfside-location-modal-card">
                <button type="button" class="surfside-event-modal-close" data-surfside-location-close aria-label="Close">×</button>
                <h2 id="surfside-location-modal-title">Create New Location</h2>
                <form method="post" class="surfside-location-new-form">
                    <?php wp_nonce_field('surfside_calendar_manager', 'surfside_calendar_nonce'); ?>
                    <input type="hidden" name="surfside_calendar_action" value="save_location">
                    <label><span>Location Name</span><input type="text" name="location_name" required placeholder="Fellowship Hall"></label>
                    <label><span>Street Address</span><input type="text" name="location_address" placeholder="123 Main St, Cocoa, FL 32922"></label>
                    <label><span>Notes (optional)</span><textarea name="location_notes" rows="3" placeholder="Parking or entrance instructions"></textarea></label>
                    <div class="surfside-calendar-form-actions"><button type="submit" class="surfside-staff-button">Save Location</button><button type="button" class="surfside-calendar-cancel surfside-location-cancel" data-surfside-location-close>Cancel</button></div>
                </form>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_tools_calendar_manager', 'surfside_tools_calendar_manager_shortcode');

function surfside_tools_calendar_group_events_by_month($events) {
    $groups = array();
    foreach ($events as $event) {
        $timestamp = !empty($event['date']) ? strtotime($event['date'] . ' 12:00:00') : false;
        $key = $timestamp ? date_i18n('F Y', $timestamp) : 'Upcoming';
        if (!isset($groups[$key])) {
            $groups[$key] = array();
        }
        $groups[$key][] = $event;
    }
    return $groups;
}


function surfside_tools_calendar_google_maps_url($event) {
    $stored_url = trim((string) ($event['location_maps_url'] ?? ''));
    if ($stored_url !== '') {
        return $stored_url;
    }
    $address = trim((string) ($event['location_address'] ?? ''));
    $name = trim((string) ($event['location_name'] ?? ($event['location'] ?? '')));
    if ($address === '' && $name === '') {
        return '';
    }
    $query = trim($name . ($address !== '' ? ', ' . $address : ''));
    $url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
    $place_id = trim((string) ($event['location_place_id'] ?? ''));
    if ($place_id !== '') {
        $url .= '&query_place_id=' . rawurlencode($place_id);
    }
    return $url;
}

function surfside_tools_calendar_render_event_modal($event, $detail_id) {
    $detail_text = trim(wp_strip_all_tags($event['description'] ?? ''));
    ob_start();
    ?>
    <div id="<?php echo esc_attr($detail_id); ?>" class="surfside-event-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($detail_id); ?>-title" hidden>
        <div class="surfside-event-modal-backdrop" data-surfside-modal-close></div>
        <div class="surfside-event-modal-card" role="document">
            <button type="button" class="surfside-event-modal-close" data-surfside-modal-close aria-label="Close event details">×</button>
            <?php if (!empty($event['featured'])) : ?><span class="surfside-public-calendar-featured-label">Featured Event</span><?php endif; ?>
            <h2 id="<?php echo esc_attr($detail_id); ?>-title"><?php echo esc_html($event['title']); ?></h2>
            <div class="surfside-event-modal-meta">
                <p><strong>Date</strong><span><?php echo esc_html(surfside_tools_calendar_format_event_dates($event)); ?></span></p>
                <p><strong>Time</strong><span><?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></span></p>
                <?php if (!empty($event['location_name']) || !empty($event['location'])) : ?><p><strong>Location</strong><span><?php echo esc_html($event['location_name'] ?: $event['location']); ?></span></p><?php endif; ?>
                <?php if (!empty($event['location_address'])) : ?><p><strong>Address</strong><span><?php echo esc_html($event['location_address']); ?><?php $maps_url = surfside_tools_calendar_google_maps_url($event); if ($maps_url) : ?><br><a class="surfside-event-maps-link" href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer">Open in Google Maps ↗</a><?php endif; ?></span></p><?php endif; ?>
            </div>
            <?php if (!empty($detail_text)) : ?><div class="surfside-event-modal-description"><?php echo wpautop(wp_kses_post($event['description'])); ?></div><?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function surfside_tools_calendar_render_event_cards($events, $show_description = true, $use_grouping = true) {
    if (empty($events)) {
        return '';
    }
    ob_start();
    $groups = $use_grouping ? surfside_tools_calendar_group_events_by_month($events) : array('' => $events);
    $card_counter = 0;
    foreach ($groups as $month_label => $month_events) :
        if ($month_label !== '') : ?>
            <h2 class="surfside-public-calendar-month"><?php echo esc_html($month_label); ?></h2>
        <?php endif; ?>
        <div class="surfside-public-calendar-month-events">
            <?php foreach ($month_events as $event) :
                $card_counter++;
                $timestamp = !empty($event['date']) ? strtotime($event['date'] . ' 12:00:00') : false;
                $month = $timestamp ? date_i18n('M', $timestamp) : '';
                $day = $timestamp ? date_i18n('j', $timestamp) : '';
                $weekday = $timestamp ? date_i18n('D', $timestamp) : '';
                $detail_id = 'surfside-event-detail-card-' . absint($event['id']) . '-' . str_replace('-', '', $event['date']) . '-' . $card_counter;
                ?>
                <article class="surfside-public-calendar-event<?php echo !empty($event['featured']) ? ' surfside-public-calendar-featured' : ''; ?>">
                    <div class="surfside-public-calendar-date">
                        <span><?php echo esc_html($month); ?></span>
                        <strong><?php echo esc_html($day); ?></strong>
                        <em><?php echo esc_html($weekday); ?></em>
                    </div>
                    <button type="button" class="surfside-public-calendar-body surfside-event-detail-button" aria-haspopup="dialog" aria-controls="<?php echo esc_attr($detail_id); ?>">
                        <?php if (!empty($event['featured'])) : ?><span class="surfside-public-calendar-featured-label">Featured Event</span><?php endif; ?>
                        <h3><?php echo esc_html($event['title']); ?></h3>
                        <p class="surfside-public-calendar-meta"><?php echo esc_html(surfside_tools_calendar_format_date($event['date'])); ?> · <?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></p>
                        <?php if (!empty($event['location'])) : ?><p class="surfside-public-calendar-location">📍 <?php echo esc_html($event['location']); ?></p><?php endif; ?>
                        <?php if ($show_description && !empty($event['description'])) : ?><div class="surfside-public-calendar-description"><?php echo wpautop(wp_kses_post($event['description'])); ?></div><?php endif; ?>
                    </button>
                    <?php echo surfside_tools_calendar_render_event_modal($event, $detail_id); ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endforeach;
    return ob_get_clean();
}

function surfside_tools_calendar_public_shortcode($atts = array()) {
    surfside_tools_calendar_enqueue_styles();
    $atts = shortcode_atts(array(
        'limit' => 8,
        'layout' => 'cards',
        'group' => 'month',
        'show_description' => 'yes',
        'empty_message' => 'No upcoming events have been posted yet.',
    ), $atts, 'surfside_tools_calendar');

    $events = surfside_tools_calendar_get_upcoming_events((int) $atts['limit']);
    $layout = sanitize_key($atts['layout']);
    $group = sanitize_key($atts['group']);
    $show_description = strtolower((string) $atts['show_description']) !== 'no';
    $use_compact = $layout === 'compact';
    $use_grouping = $group !== 'no' && $group !== 'none' && !$use_compact;

    ob_start();
    ?>
    <div class="surfside-public-calendar-list surfside-public-calendar-<?php echo esc_attr($use_compact ? 'compact' : 'cards'); ?>">
        <?php if (empty($events)) : ?>
            <div class="surfside-public-calendar-empty">
                <strong>No upcoming events</strong>
                <p><?php echo esc_html($atts['empty_message']); ?></p>
            </div>
        <?php else : ?>
            <?php echo surfside_tools_calendar_render_event_cards($events, $show_description, $use_grouping); ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_tools_calendar', 'surfside_tools_calendar_public_shortcode');
add_shortcode('surfside_tools_upcoming_events', 'surfside_tools_calendar_public_shortcode');
add_shortcode('surfside_events', 'surfside_tools_calendar_public_shortcode');

function surfside_tools_calendar_this_week_shortcode($atts = array()) {
    surfside_tools_calendar_enqueue_styles();
    $atts = shortcode_atts(array(
        'mode' => '',
        'show_description' => 'yes',
        'empty_message' => 'No events are scheduled for this week.',
    ), $atts, 'surfside_this_week');
    $today = current_time('Y-m-d');
    $configured_mode = function_exists('surfside_tools_get_setting') ? surfside_tools_get_setting('this_week_mode', 'next7') : 'next7';
    $mode = sanitize_key($atts['mode'] ?: $configured_mode);
    if ($mode === 'sunday') {
        $start_ts = strtotime('last sunday', strtotime($today . ' +1 day'));
        $start = date('Y-m-d', $start_ts);
        $end = date('Y-m-d', strtotime($start . ' +6 days'));
    } else {
        $start = $today;
        $end = date('Y-m-d', strtotime($today . ' +6 days'));
    }
    $events = surfside_tools_calendar_get_occurrences($start, $end);
    if (empty($events)) {
        return '<div class="surfside-public-calendar-empty"><strong>This Week at Surfside</strong><p>' . esc_html($atts['empty_message']) . '</p></div>';
    }
    return '<div class="surfside-this-week"><h2 class="surfside-public-calendar-month">This Week at Surfside</h2>' . surfside_tools_calendar_render_event_cards($events, strtolower((string)$atts['show_description']) !== 'no', false) . '</div>';
}
add_shortcode('surfside_this_week', 'surfside_tools_calendar_this_week_shortcode');

function surfside_tools_calendar_events_by_date($events) {
    $by_date = array();
    foreach ($events as $event) {
        if (empty($event['date'])) {
            continue;
        }
        if (!isset($by_date[$event['date']])) {
            $by_date[$event['date']] = array();
        }
        $by_date[$event['date']][] = $event;
    }
    return $by_date;
}

function surfside_tools_calendar_render_month_grid($events, $month_start, $show_description = false) {
    $events_by_date = surfside_tools_calendar_events_by_date($events);
    $first_ts = strtotime($month_start . ' 12:00:00');
    $month_label = date_i18n('F Y', $first_ts);
    $month_number = date('m', $first_ts);
    $grid_start = date('Y-m-d', strtotime('last sunday', strtotime($month_start . ' +1 day')));
    $grid_end = date('Y-m-d', strtotime('next saturday', strtotime(date('Y-m-t', $first_ts) . ' -1 day')));
    $weekdays = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');

    ob_start();
    ?>
    <div class="surfside-month-calendar-grid-wrap">
        <div class="surfside-month-calendar-grid" role="table" aria-label="<?php echo esc_attr($month_label); ?> calendar">
            <div class="surfside-month-calendar-weekdays" role="row">
                <?php foreach ($weekdays as $weekday) : ?>
                    <div role="columnheader"><?php echo esc_html($weekday); ?></div>
                <?php endforeach; ?>
            </div>
            <div class="surfside-month-calendar-days">
                <?php for ($day_ts = strtotime($grid_start . ' 12:00:00'); $day_ts <= strtotime($grid_end . ' 12:00:00'); $day_ts = strtotime('+1 day', $day_ts)) :
                    $date = date('Y-m-d', $day_ts);
                    $is_current_month = date('m', $day_ts) === $month_number;
                    $day_events = isset($events_by_date[$date]) ? $events_by_date[$date] : array();
                    ?>
                    <div class="surfside-month-calendar-day<?php echo $is_current_month ? '' : ' surfside-month-calendar-muted'; ?><?php echo !empty($day_events) ? ' surfside-month-calendar-has-events' : ''; ?>" role="cell">
                        <div class="surfside-month-calendar-date-number">
                            <span><?php echo esc_html(date_i18n('D', $day_ts)); ?></span>
                            <strong><?php echo esc_html(date_i18n('j', $day_ts)); ?></strong>
                        </div>
                        <?php if (!empty($day_events)) : ?>
                            <div class="surfside-month-calendar-day-events">
                                <?php foreach ($day_events as $event) : ?>
                                    <?php
                                    $detail_id = 'surfside-event-detail-' . $event['id'] . '-' . str_replace('-', '', $event['date']);
                                    $detail_text = trim(wp_strip_all_tags($event['description'] ?? ''));
                                    ?>
                                    <article class="surfside-month-calendar-item<?php echo !empty($event['featured']) ? ' surfside-month-calendar-item-featured' : ''; ?>">
                                        <button type="button" class="surfside-month-calendar-event-button surfside-event-detail-button" aria-haspopup="dialog" aria-controls="<?php echo esc_attr($detail_id); ?>">
                                            <span class="surfside-month-calendar-event-title"><?php echo esc_html($event['title']); ?></span>
                                            <span><?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></span>
                                            <?php if (!empty($event['location'])) : ?><span class="surfside-month-calendar-location">📍 <?php echo esc_html($event['location']); ?></span><?php endif; ?>
                                        </button>
                                        <div id="<?php echo esc_attr($detail_id); ?>" class="surfside-event-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($detail_id); ?>-title" hidden>
                                            <div class="surfside-event-modal-backdrop" data-surfside-modal-close></div>
                                            <div class="surfside-event-modal-card" role="document">
                                                <button type="button" class="surfside-event-modal-close" data-surfside-modal-close aria-label="Close event details">×</button>
                                                <?php if (!empty($event['featured'])) : ?><span class="surfside-public-calendar-featured-label">Featured Event</span><?php endif; ?>
                                                <h2 id="<?php echo esc_attr($detail_id); ?>-title"><?php echo esc_html($event['title']); ?></h2>
                                                <div class="surfside-event-modal-meta">
                                                    <p><strong>Date</strong><span><?php echo esc_html(surfside_tools_calendar_format_event_dates($event)); ?></span></p>
                                                    <p><strong>Time</strong><span><?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></span></p>
                                                    <?php if (!empty($event['location_name']) || !empty($event['location'])) : ?><p><strong>Location</strong><span><?php echo esc_html($event['location_name'] ?: $event['location']); ?></span></p><?php endif; ?>
                <?php if (!empty($event['location_address'])) : ?><p><strong>Address</strong><span><?php echo esc_html($event['location_address']); ?><?php $maps_url = surfside_tools_calendar_google_maps_url($event); if ($maps_url) : ?><br><a class="surfside-event-maps-link" href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer">Open in Google Maps ↗</a><?php endif; ?></span></p><?php endif; ?>
                                                </div>
                                                <?php if (!empty($detail_text)) : ?><div class="surfside-event-modal-description"><?php echo wpautop(wp_kses_post($event['description'])); ?></div><?php endif; ?>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function surfside_tools_calendar_month_shortcode($atts = array()) {
    surfside_tools_calendar_enqueue_styles();
    $atts = shortcode_atts(array(
        'month' => '',
        'show_description' => 'no',
        'empty_message' => 'No events are scheduled for this month.',
    ), $atts, 'surfside_month_calendar');

    $requested_month = isset($_GET['surfside_month']) ? sanitize_text_field(wp_unslash($_GET['surfside_month'])) : '';
    $shortcode_month = sanitize_text_field((string)$atts['month']);
    $month = $requested_month ?: $shortcode_month;
    $base = $month && preg_match('/^\d{4}-\d{2}$/', $month) ? $month . '-01' : current_time('Y-m-01');
    $start = date('Y-m-01', strtotime($base));
    $end = date('Y-m-t', strtotime($base));
    $events = surfside_tools_calendar_get_occurrences($start, $end);
    $show_description = strtolower((string)$atts['show_description']) !== 'no';

    $month_value = date('Y-m', strtotime($start));
    $prev_month = date('Y-m', strtotime($start . ' -1 month'));
    $next_month = date('Y-m', strtotime($start . ' +1 month'));
    $current_month = current_time('Y-m');
    $prev_url = esc_url(add_query_arg('surfside_month', $prev_month));
    $next_url = esc_url(add_query_arg('surfside_month', $next_month));
    $today_url = esc_url(remove_query_arg('surfside_month'));

    ob_start();
    ?>
    <div class="surfside-month-calendar" data-month="<?php echo esc_attr($month_value); ?>">
        <div class="surfside-month-calendar-nav" aria-label="Calendar navigation">
            <a class="surfside-month-calendar-nav-button" href="<?php echo $prev_url; ?>">‹ <?php echo esc_html(date_i18n('F', strtotime($prev_month . '-01'))); ?></a>
            <div class="surfside-month-calendar-nav-title">
                <h2 class="surfside-month-calendar-title"><?php echo esc_html(date_i18n('F Y', strtotime($start))); ?></h2>
                <a class="surfside-month-calendar-today" href="<?php echo $today_url; ?>">Today</a>
            </div>
            <a class="surfside-month-calendar-nav-button" href="<?php echo $next_url; ?>"><?php echo esc_html(date_i18n('F', strtotime($next_month . '-01'))); ?> ›</a>
        </div>
        <?php if (empty($events)) : ?>
            <div class="surfside-public-calendar-empty"><strong>No events this month</strong><p><?php echo esc_html($atts['empty_message']); ?></p></div>
        <?php else : ?>
            <?php echo surfside_tools_calendar_render_month_grid($events, $start, $show_description); ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_month_calendar', 'surfside_tools_calendar_month_shortcode');

function surfside_tools_calendar_enqueue_google_places() {
    $api_key = function_exists('surfside_tools_get_setting') ? trim((string) surfside_tools_get_setting('google_maps_api_key', '')) : '';
    if ($api_key === '') {
        return;
    }

    $handle = 'surfside-tools-google-places';
    if (!wp_script_is($handle, 'registered')) {
        $src = add_query_arg(array(
            'key' => $api_key,
            'libraries' => 'places',
            'v' => 'weekly',
        ), 'https://maps.googleapis.com/maps/api/js');
        wp_register_script($handle, $src, array(), null, true);
    }

    $initializer = <<<'JS'
(function () {
    'use strict';

    function setStatus(picker, message, state) {
        var status = picker.querySelector('[data-surfside-google-status]');
        if (!status) return;
        status.textContent = message;
        status.dataset.state = state || '';
    }

    function initializePicker(picker) {
        if (picker.dataset.googleReady === '1') return true;
        if (!window.google || !google.maps || !google.maps.places || !google.maps.places.Autocomplete) return false;

        var input = picker.querySelector('[data-surfside-google-place]');
        if (!input) return true;

        try {
            var bounds = new google.maps.LatLngBounds(
                new google.maps.LatLng(27.75, -81.25),
                new google.maps.LatLng(29.05, -80.15)
            );
            var autocomplete = new google.maps.places.Autocomplete(input, {
                bounds: bounds,
                strictBounds: false,
                componentRestrictions: { country: 'us' },
                fields: ['place_id', 'name', 'formatted_address', 'geometry', 'url'],
                types: ['establishment', 'geocode']
            });

            var name = picker.querySelector('.surfside-location-name');
            var address = picker.querySelector('.surfside-location-address');
            var placeId = picker.querySelector('.surfside-location-place-id');
            var lat = picker.querySelector('.surfside-location-lat');
            var lng = picker.querySelector('.surfside-location-lng');
            var mapsUrl = picker.querySelector('.surfside-location-maps-url');
            var savedId = picker.querySelector('.surfside-location-id');
            var selected = picker.querySelector('[data-surfside-location-selected]');

            autocomplete.addListener('place_changed', function () {
                var place = autocomplete.getPlace();
                if (!place || !place.place_id) {
                    setStatus(picker, 'Select a result from the Google suggestions, or enter the location manually below.', 'warning');
                    return;
                }
                var displayName = place.name || input.value || '';
                var formattedAddress = place.formatted_address || '';
                input.value = displayName;
                if (name) name.value = displayName;
                if (address) address.value = formattedAddress;
                if (placeId) placeId.value = place.place_id || '';
                if (lat) lat.value = place.geometry && place.geometry.location ? place.geometry.location.lat() : '';
                if (lng) lng.value = place.geometry && place.geometry.location ? place.geometry.location.lng() : '';
                if (mapsUrl) mapsUrl.value = place.url || ('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(displayName + (formattedAddress ? ', ' + formattedAddress : '')) + '&query_place_id=' + encodeURIComponent(place.place_id));
                if (savedId) savedId.value = '0';
                if (selected) selected.hidden = false;
                setStatus(picker, 'Google location selected. Name and address were filled automatically.', 'success');
            });

            input.addEventListener('input', function () {
                if (name) name.value = input.value;
                if (placeId) placeId.value = '';
                if (lat) lat.value = '';
                if (lng) lng.value = '';
                if (mapsUrl) mapsUrl.value = '';
                if (savedId) savedId.value = '0';
                if (selected) selected.hidden = true;
                setStatus(picker, 'Searching Google Places…', 'ready');
            });

            picker.dataset.googleReady = '1';
            setStatus(picker, 'Google Places is ready. Start typing to search.', 'success');
            return true;
        } catch (error) {
            console.error('Surfside Tools Google Places initialization failed:', error);
            setStatus(picker, 'Google Places could not start. Refresh the page and check the browser console if this continues.', 'error');
            return false;
        }
    }

    function initializeAll() {
        var pending = false;
        document.querySelectorAll('[data-surfside-google-location-picker]').forEach(function (picker) {
            if (!initializePicker(picker)) pending = true;
        });
        return !pending;
    }

    function startInitialization() {
        var attempts = 0;
        var timer = window.setInterval(function () {
            attempts += 1;
            if (initializeAll() || attempts >= 40) {
                window.clearInterval(timer);
                if (attempts >= 40) {
                    document.querySelectorAll('[data-surfside-google-location-picker]:not([data-google-ready="1"])').forEach(function (picker) {
                        setStatus(picker, 'Google Places did not load. Verify the API key website restrictions include this exact domain.', 'error');
                    });
                }
            }
        }, 250);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startInitialization);
    } else {
        startInitialization();
    }
})();
JS;
    wp_add_inline_script($handle, $initializer, 'after');
    wp_enqueue_script($handle);
}

function surfside_tools_calendar_enqueue_styles() {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    wp_register_style('surfside-tools-calendar-manager', false, array(), defined('SURFSIDE_TOOLS_VERSION') ? SURFSIDE_TOOLS_VERSION : '1.2.0-dev15');
    wp_enqueue_style('surfside-tools-calendar-manager');
    wp_add_inline_style('surfside-tools-calendar-manager', '

        .pac-container { z-index: 1000000 !important; font-family: inherit; border-radius: 10px; box-shadow: 0 12px 30px rgba(7,27,58,.18); }
        .surfside-google-status { margin:6px 0 12px; font-size:13px; font-weight:700; color:#526174; }
        .surfside-google-status[data-state=success] { color:#008a20; }
        .surfside-google-status[data-state=warning] { color:#996800; }
        .surfside-google-status[data-state=error] { color:#b32d2e; }
        .surfside-calendar-status { display:flex; align-items:center; justify-content:space-between; gap:16px; }
        .surfside-calendar-status code { background:#fff; border:1px solid rgba(11,79,156,.18); border-radius:6px; padding:2px 6px; }
        .surfside-calendar-status-pill { background:#0b4f9c; color:#fff; border-radius:999px; padding:8px 14px; font-weight:800; white-space:nowrap; }
        .surfside-calendar-featured-badge { display:inline-block; vertical-align:middle; margin-left:6px; background:#fff3cd; color:#7a5300; border:1px solid #ffe08a; border-radius:999px; padding:2px 8px; font-size:12px; font-weight:800; }
        .surfside-calendar-featured-check { background:#fbfdff; border:1px dashed rgba(11,79,156,.24); border-radius:12px; padding:10px 12px; }
        .surfside-calendar-past-heading { margin-top:28px !important; padding-top:22px; border-top:1px solid rgba(7,27,58,.12); }
        .surfside-calendar-past-list { display:grid; gap:10px; }
        .surfside-calendar-past-event { display:grid; grid-template-columns:1fr auto auto; gap:12px; align-items:center; padding:12px 0; border-bottom:1px solid rgba(7,27,58,.08); }
        .surfside-calendar-past-event span { color:#5b667a; }
        .surfside-calendar-past-event a { color:#0b4f9c; font-weight:700; text-decoration:none; }
        .surfside-calendar-note {
            background:#eef6ff;
            border:1px solid rgba(11,79,156,.18);
            border-radius:14px;
            padding:16px 18px;
            margin-bottom:22px;
            color:#193a63;
        }
        .surfside-calendar-layout {
            display:grid;
            grid-template-columns:minmax(0, .95fr) minmax(0, 1.05fr);
            gap:22px;
            align-items:start;
        }
        .surfside-calendar-panel {
            background:#fff;
            border:1px solid rgba(7,27,58,.12);
            border-radius:18px;
            box-shadow:0 12px 32px rgba(7,27,58,.07);
            padding:24px;
        }
        .surfside-calendar-panel h2 { margin:0 0 18px; color:#071b3a; }
        .surfside-calendar-form label { display:block; margin-bottom:16px; }
        .surfside-calendar-form label span { display:block; font-weight:700; margin-bottom:7px; color:#071b3a; }
        .surfside-calendar-form input[type="text"],
        .surfside-calendar-form input[type="date"],
        .surfside-calendar-form input[type="time"],
        .surfside-calendar-form textarea {
            width:100%;
            border:1px solid rgba(7,27,58,.2);
            border-radius:10px;
            padding:11px 12px;
            font:inherit;
        }
        .surfside-calendar-form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .surfside-calendar-checkbox { display:flex !important; align-items:center; gap:10px; margin-top:30px; }
        .surfside-calendar-checkbox input { margin:0; }
        .surfside-calendar-checkbox span { margin:0 !important; }
        .surfside-calendar-form-actions { display:flex; gap:14px; align-items:center; margin-top:8px; }
        .surfside-calendar-form-actions .surfside-staff-button { width:auto; min-width:180px; border:0; cursor:pointer; }
        .surfside-calendar-cancel { font-weight:700; color:#0b4f9c; text-decoration:none; }
        .surfside-calendar-manage-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:18px; margin-bottom:16px; }
        .surfside-calendar-manage-heading h2 { margin-bottom:4px; }
        .surfside-calendar-search-form { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .surfside-calendar-search-form input { min-width:220px; }
        .surfside-calendar-search-form button, .surfside-calendar-search-form a { border:1px solid #0b5fb8; background:#fff; color:#0b4f9c; border-radius:7px; padding:8px 12px; font-weight:700; text-decoration:none; cursor:pointer; }
        .surfside-calendar-search-form button { background:#0b5fb8; color:#fff; }
        .surfside-calendar-event-list { display:grid; gap:14px; }
        .surfside-calendar-pagination { display:flex; justify-content:center; align-items:center; gap:18px; margin:20px 0 4px; }
        .surfside-calendar-pagination a { font-weight:800; text-decoration:none; }
        .surfside-calendar-event {
            display:grid;
            grid-template-columns:1fr auto;
            gap:16px;
            padding:16px;
            border:1px solid rgba(7,27,58,.1);
            border-radius:14px;
            background:#fbfdff;
        }
        .surfside-calendar-event h3 { margin:0 0 6px; color:#071b3a; }
        .surfside-calendar-event p { margin:4px 0; color:#34425e; }
        .surfside-calendar-event-actions { display:flex; gap:12px; align-items:start; }
        .surfside-calendar-event-actions a,
        .surfside-calendar-event-actions button {
            background:none;
            border:0;
            padding:0;
            color:#0b4f9c;
            font-weight:700;
            text-decoration:none;
            cursor:pointer;
            font:inherit;
        }
        .surfside-calendar-notice {
            border-radius:12px;
            padding:12px 14px;
            margin-bottom:16px;
            font-weight:700;
        }
        .surfside-calendar-success { background:#ecfdf3; color:#146c37; border:1px solid #b8eac7; }
        .surfside-calendar-error { background:#fff1f2; color:#9f1239; border:1px solid #fecdd3; }
        .surfside-public-calendar-list { display:grid; gap:16px; }
        .surfside-public-calendar-month { margin:8px 0 0 !important; padding-bottom:8px; border-bottom:1px solid rgba(7,27,58,.12); color:#071b3a; font-size:clamp(1.35rem, 2vw, 1.8rem); }
        .surfside-public-calendar-month-events { display:grid; gap:16px; }
        .surfside-public-calendar-empty { border:1px solid rgba(7,27,58,.12); border-radius:16px; padding:22px; background:#fff; }
        .surfside-public-calendar-empty strong { display:block; color:#071b3a; font-size:1.15rem; margin-bottom:4px; }
        .surfside-public-calendar-empty p { margin:0; color:#34425e; }
        .surfside-public-calendar-event { display:grid; grid-template-columns:76px 1fr; gap:18px; border:1px solid rgba(7,27,58,.12); border-radius:16px; padding:18px; background:#fff; box-shadow:0 8px 22px rgba(7,27,58,.04); }
        .surfside-public-calendar-date { background:#eef6ff; border-radius:14px; min-height:82px; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#0b4f9c; text-transform:uppercase; font-weight:800; }
        .surfside-public-calendar-date strong { display:block; font-size:32px; line-height:1; color:#071b3a; }
        .surfside-public-calendar-date em { font-style:normal; font-size:12px; color:#5b667a; margin-top:4px; }
        .surfside-public-calendar-body.surfside-event-detail-button { border:0; background:transparent; text-align:left; padding:0; margin:0; cursor:pointer; font:inherit; color:inherit; width:100%; }
        .surfside-public-calendar-body.surfside-event-detail-button:hover h3, .surfside-public-calendar-body.surfside-event-detail-button:focus h3 { color:#0b4f9c; text-decoration:underline; }
        .surfside-public-calendar-body.surfside-event-detail-button:focus { outline:3px solid rgba(11,79,156,.25); outline-offset:4px; border-radius:10px; }
        .surfside-public-calendar-event h3 { margin:0 0 6px; color:#071b3a; }
        .surfside-public-calendar-event p { margin:5px 0; }
        .surfside-public-calendar-meta { color:#34425e; font-weight:700; }
        .surfside-public-calendar-location { color:#34425e; }
        .surfside-public-calendar-description { margin-top:10px; }
        .surfside-public-calendar-description p:last-child { margin-bottom:0; }
        .surfside-public-calendar-featured { border-color:rgba(11,79,156,.34); box-shadow:0 12px 30px rgba(11,79,156,.10); }
        .surfside-public-calendar-featured .surfside-public-calendar-date { background:#0b4f9c; color:#fff; }
        .surfside-public-calendar-featured .surfside-public-calendar-date strong,
        .surfside-public-calendar-featured .surfside-public-calendar-date em { color:#fff; }
        .surfside-public-calendar-featured-label { display:inline-flex; background:#eef6ff; color:#0b4f9c; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; margin-bottom:8px; }
        .surfside-public-calendar-compact .surfside-public-calendar-event { grid-template-columns:1fr; }
        .surfside-public-calendar-compact .surfside-public-calendar-date { display:none; }

        .surfside-calendar-recurrence { border:1px solid rgba(7,27,58,.12); border-radius:14px; padding:16px; margin:4px 0 16px; }
        .surfside-calendar-recurrence legend { font-weight:800; color:#071b3a; padding:0 6px; }

        .surfside-location-picker { position:relative; margin-bottom:18px; padding:16px; border:1px solid rgba(7,27,58,.12); border-radius:14px; background:#f8fafc; }
        .surfside-location-help { margin:7px 0 0; color:#60708a; font-size:.88rem; line-height:1.45; }
        .surfside-location-selected { margin-top:10px; padding:9px 11px; border-radius:9px; background:#eaf8ef; color:#176b36; font-size:.86rem; font-weight:700; }
        .surfside-location-selected[hidden] { display:none; }
        .pac-container { z-index:100001 !important; border-radius:10px; box-shadow:0 12px 28px rgba(7,27,58,.18); font-family:inherit; }
        .surfside-location-search { width:100%; border:1px solid rgba(7,27,58,.2); border-radius:10px; padding:11px 12px; font:inherit; background:#fff; }
        .surfside-location-suggestions { position:absolute; z-index:30; left:16px; right:16px; top:86px; max-height:300px; overflow:auto; background:#fff; border:1px solid rgba(7,27,58,.14); border-radius:12px; box-shadow:0 16px 36px rgba(7,27,58,.16); padding:8px; }
        .surfside-location-option { display:block; width:100%; border:0; background:#fff; text-align:left; padding:10px 12px; border-radius:8px; cursor:pointer; }
        .surfside-location-option:hover,.surfside-location-option:focus { background:#eef5ff; }
        .surfside-location-option strong,.surfside-location-option span { display:block; }
        .surfside-location-option span { margin-top:2px; font-size:.88rem; color:#60708a; }
        .surfside-location-new-button { width:100%; border:0; border-top:1px solid #e3e8ef; background:#fff; color:#0755b5; font-weight:700; text-align:left; padding:12px; cursor:pointer; }
        .surfside-location-empty { margin:8px 12px; color:#60708a; }
        .surfside-location-fields { margin-top:12px; }
        .surfside-location-modal[hidden] { display:none; }
        .surfside-location-modal { position:fixed; inset:0; z-index:100000; display:flex; align-items:center; justify-content:center; padding:20px; }
        .surfside-location-modal-backdrop { position:absolute; inset:0; background:rgba(3,15,35,.62); }
        .surfside-location-modal-card { position:relative; z-index:1; width:min(560px,100%); background:#fff; border-radius:18px; padding:26px; box-shadow:0 24px 70px rgba(0,0,0,.28); }
        .surfside-location-modal-card h2 { margin:0 0 20px; }
        .surfside-location-new-form label { display:block; margin-bottom:15px; }
        .surfside-location-new-form label span { display:block; font-weight:700; margin-bottom:7px; }
        .surfside-location-new-form input,.surfside-location-new-form textarea { width:100%; border:1px solid rgba(7,27,58,.2); border-radius:10px; padding:11px 12px; font:inherit; }
        .surfside-location-cancel { border:0; background:transparent; cursor:pointer; }

        .surfside-calendar-form select, .surfside-calendar-form input[type="number"] { width:100%; border:1px solid rgba(7,27,58,.2); border-radius:10px; padding:11px 12px; font:inherit; background:#fff; }
        .surfside-recurrence-options { margin-top:12px; }
        .surfside-field-label { display:block; font-weight:700; margin-bottom:7px; color:#071b3a; }
        .surfside-weekday-pills { display:flex; flex-wrap:wrap; gap:7px; }
        .surfside-weekday-pills label { margin:0; }
        .surfside-weekday-pills input { position:absolute; opacity:0; }
        .surfside-weekday-pills span { display:block !important; margin:0 !important; padding:7px 10px; border:1px solid rgba(7,27,58,.18); border-radius:999px; cursor:pointer; }
        .surfside-weekday-pills input:checked + span { background:#0b4f9c; color:#fff; border-color:#0b4f9c; }
        .surfside-calendar-recurrence-label { color:#0b4f9c !important; font-size:13px; font-weight:800; }

        /* Monthly calendar needs to escape narrow theme/page columns. */
        .entry-content .surfside-month-calendar,
        .wp-site-blocks .surfside-month-calendar,
        body .surfside-month-calendar {
            width:min(1280px, calc(100vw - 48px)) !important;
            max-width:none !important;
            margin-top:28px !important;
            margin-bottom:28px !important;
            margin-left:50% !important;
            margin-right:0 !important;
            padding-left:0 !important;
            padding-right:0 !important;
            position:relative !important;
            left:0 !important;
            right:auto !important;
            transform:translateX(-50%) !important;
            display:block !important;
            box-sizing:border-box !important;
            clear:both;
        }
        .entry-content .surfside-month-calendar-grid-wrap,
        .wp-site-blocks .surfside-month-calendar-grid-wrap,
        body .surfside-month-calendar-grid-wrap { width:100% !important; max-width:none !important; margin-left:auto !important; margin-right:auto !important; }

        .surfside-month-calendar-nav { display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:16px; margin:0 0 18px; }
        .surfside-month-calendar-nav-title { text-align:center; display:flex; flex-direction:column; align-items:center; gap:8px; }
        .surfside-month-calendar-nav-button, .surfside-month-calendar-today { display:inline-flex; align-items:center; justify-content:center; border:1px solid rgba(11,79,156,.22); border-radius:999px; padding:9px 14px; color:#0b4f9c; background:#fff; font-weight:800; text-decoration:none; box-shadow:0 4px 14px rgba(7,27,58,.04); }
        .surfside-month-calendar-nav-button:first-child { justify-self:start; }
        .surfside-month-calendar-nav-button:last-child { justify-self:end; }
        .surfside-month-calendar-today { padding:5px 12px; font-size:13px; background:#eef6ff; }
        .surfside-month-calendar-title { margin:0 !important; color:#071b3a; font-size:clamp(1.7rem, 3vw, 2.4rem); }
        .surfside-month-calendar-grid { width:100%; border:1px solid rgba(7,27,58,.14); border-radius:18px; overflow:hidden; background:#fff; box-shadow:0 12px 32px rgba(7,27,58,.06); }
        .surfside-month-calendar-weekdays { display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); background:#eef6ff; border-bottom:1px solid rgba(7,27,58,.12); }
        .surfside-month-calendar-weekdays div { padding:12px 10px; text-align:center; color:#0b4f9c; font-size:13px; font-weight:900; text-transform:uppercase; letter-spacing:.04em; }
        .surfside-month-calendar-days { display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); }
        .surfside-month-calendar-day { min-height:128px; padding:10px; border-right:1px solid rgba(7,27,58,.10); border-bottom:1px solid rgba(7,27,58,.10); background:#fff; }
        .surfside-month-calendar-day:nth-child(7n) { border-right:0; }
        .surfside-month-calendar-day:nth-last-child(-n+7) { border-bottom:0; }
        .surfside-month-calendar-muted { background:#f8fafc; color:#8a94a6; }
        .surfside-month-calendar-date-number { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; color:#5b667a; }
        .surfside-month-calendar-date-number span { display:none; font-size:12px; font-weight:800; text-transform:uppercase; color:#0b4f9c; }
        .surfside-month-calendar-date-number strong { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:999px; font-size:15px; color:#071b3a; }
        .surfside-month-calendar-has-events .surfside-month-calendar-date-number strong { background:#eef6ff; color:#0b4f9c; }
        .surfside-month-calendar-day-events { display:grid; gap:7px; }
        .surfside-month-calendar-item { border-left:4px solid #0b4f9c; background:#f7fbff; border-radius:9px; padding:7px 8px; }
        .surfside-month-calendar-item-featured { background:#eef6ff; box-shadow:inset 0 0 0 1px rgba(11,79,156,.16); }
        .surfside-month-calendar-event-button { display:block; width:100%; border:0; background:transparent; text-align:left; padding:0; margin:0; cursor:pointer; font:inherit; color:inherit; }
        .surfside-month-calendar-event-title { display:block; margin:0 0 3px; color:#071b3a; font-size:15px; font-weight:900; line-height:1.18; }
        .surfside-month-calendar-event-button span:not(.surfside-month-calendar-event-title) { display:block; margin:0; color:#34425e; font-size:12px; line-height:1.35; }
        .surfside-month-calendar-location { margin-top:3px !important; }
        .surfside-event-modal[hidden] { display:none !important; }
        .surfside-event-modal { position:fixed; inset:0; z-index:999999; display:flex; align-items:center; justify-content:center; padding:24px; }
        .surfside-event-modal-backdrop { position:absolute; inset:0; background:rgba(7,27,58,.62); backdrop-filter:blur(3px); }
        .surfside-event-modal-card { position:relative; width:min(620px, 100%); max-height:min(760px, calc(100vh - 48px)); overflow:auto; background:#fff; border-radius:20px; padding:30px; box-shadow:0 24px 80px rgba(7,27,58,.28); color:#34425e; }
        .surfside-event-modal-card h2 { margin:0 44px 18px 0; color:#071b3a; font-size:clamp(1.7rem, 4vw, 2.35rem); line-height:1.12; }
        .surfside-event-modal-close { position:absolute; top:14px; right:16px; width:38px; height:38px; border:0; border-radius:999px; background:#eef6ff; color:#0b4f9c; font-size:28px; line-height:1; cursor:pointer; }
        .surfside-event-modal-meta { display:grid; gap:0; margin:0 0 18px; border-top:1px solid rgba(7,27,58,.1); border-bottom:1px solid rgba(7,27,58,.1); padding:10px 0; }
        .surfside-event-modal-meta p { display:grid; grid-template-columns:90px 1fr; gap:12px; margin:0; padding:7px 0; }
        .surfside-event-modal-meta strong { color:#071b3a; }
        .surfside-event-modal-description p:first-child { margin-top:0; }
        .surfside-event-modal-description p:last-child { margin-bottom:0; }
        body.surfside-modal-open { overflow:hidden; }
        @media (max-width:900px) {
            .surfside-month-calendar-nav { grid-template-columns:auto 1fr auto; gap:8px; text-align:center; }
            .surfside-month-calendar-nav-button { padding:8px 10px; font-size:0; }
            .surfside-month-calendar-nav-button:first-child::before { content:"‹"; font-size:22px; }
            .surfside-month-calendar-nav-button:last-child::after { content:"›"; font-size:22px; }
            .surfside-month-calendar-nav-button:first-child, .surfside-month-calendar-nav-button:last-child { justify-self:center; }
            .surfside-month-calendar-title { font-size:1.55rem; }
            .surfside-month-calendar-today { font-size:12px; padding:4px 10px; }
            .surfside-event-modal { padding:12px; align-items:flex-end; }
            .surfside-event-modal-card { width:100%; max-height:85vh; border-radius:20px 20px 0 0; padding:24px 20px; }
            .surfside-event-modal-meta p { grid-template-columns:76px 1fr; }

            .surfside-calendar-layout { grid-template-columns:1fr; }
            .surfside-calendar-form-row,
            .surfside-calendar-event { grid-template-columns:1fr; }
            .surfside-calendar-checkbox { margin-top:0; }
            .surfside-calendar-status { align-items:flex-start; flex-direction:column; }
            .surfside-calendar-manage-heading { align-items:stretch; flex-direction:column; }
            .surfside-calendar-search-form input { min-width:0; flex:1 1 180px; }
            .surfside-calendar-past-event { grid-template-columns:1fr; }
            .surfside-public-calendar-event { grid-template-columns:1fr; }
            .surfside-public-calendar-date { width:76px; }
            .surfside-month-calendar-grid { border:0; box-shadow:none; background:transparent; overflow:visible; }
            .surfside-month-calendar-weekdays { display:none; }
            .surfside-month-calendar-days { display:grid; grid-template-columns:1fr; gap:12px; }
            .surfside-month-calendar-day { display:none; min-height:0; border:1px solid rgba(7,27,58,.12); border-radius:16px; padding:14px; box-shadow:0 8px 22px rgba(7,27,58,.04); }
            .surfside-month-calendar-day.surfside-month-calendar-has-events { display:block; }
            .surfside-month-calendar-muted { display:none !important; }
            .surfside-month-calendar-date-number { justify-content:flex-start; gap:10px; margin-bottom:12px; }
            .surfside-month-calendar-date-number span { display:inline; }
            .surfside-month-calendar-date-number strong { background:#eef6ff; color:#0b4f9c; }
            .surfside-month-calendar-item { padding:10px 12px; }
            .surfside-month-calendar-item h3 { font-size:17px; }
            .surfside-month-calendar-item p { font-size:14px; }

        }
    ');
    wp_register_script('surfside-tools-calendar-recurrence', '', array(), defined('SURFSIDE_TOOLS_VERSION') ? SURFSIDE_TOOLS_VERSION : '1.2.0-dev15', true);
    wp_enqueue_script('surfside-tools-calendar-recurrence');
    wp_add_inline_script('surfside-tools-calendar-recurrence', "document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.surfside-calendar-form').forEach(function(form){var sel=form.querySelector('.surfside-recurrence-type');if(!sel)return;function toggle(){var v=sel.value;form.querySelectorAll('.surfside-recurrence-options').forEach(function(el){el.style.display='none';});if(v!=='none'){form.querySelectorAll('.surfside-repeat-common').forEach(function(el){el.style.display='grid';});}form.querySelectorAll('.surfside-repeat-'+v.replace('_','-')).forEach(function(el){el.style.display=el.classList.contains('surfside-calendar-form-row')?'grid':'block';});}sel.addEventListener('change',toggle);toggle();});var lastTrigger=null;function closeModal(modal){if(!modal)return;modal.setAttribute('hidden','');document.body.classList.remove('surfside-modal-open');if(lastTrigger){lastTrigger.focus();lastTrigger=null;}}document.querySelectorAll('.surfside-event-detail-button').forEach(function(btn){btn.addEventListener('click',function(){var id=btn.getAttribute('aria-controls');var modal=id?document.getElementById(id):null;if(!modal)return;lastTrigger=btn;modal.removeAttribute('hidden');document.body.classList.add('surfside-modal-open');var close=modal.querySelector('.surfside-event-modal-close');if(close)close.focus();});});document.querySelectorAll('[data-surfside-modal-close]').forEach(function(el){el.addEventListener('click',function(){closeModal(el.closest('.surfside-event-modal'));});});document.addEventListener('keydown',function(e){if(e.key==='Escape'){var modal=document.querySelector('.surfside-event-modal:not([hidden])');if(modal)closeModal(modal);var lm=document.querySelector('.surfside-location-modal:not([hidden])');if(lm)lm.setAttribute('hidden','');}});var locationModal=document.querySelector('.surfside-location-modal');document.querySelectorAll('[data-surfside-location-new]').forEach(function(btn){btn.addEventListener('click',function(){if(locationModal)locationModal.removeAttribute('hidden');});});document.querySelectorAll('[data-surfside-location-close]').forEach(function(btn){btn.addEventListener('click',function(){if(locationModal)locationModal.setAttribute('hidden','');});});});");
}


/** Apply the configured default duration to new events when a start time is chosen. */
function surfside_tools_calendar_default_duration_script() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.surfside-calendar-form').forEach(function (form) {
            var toggle = form.querySelector('[data-surfside-multi-day-toggle]');
            var fields = form.querySelector('[data-surfside-multi-day-fields]');
            var endDate = form.querySelector('input[name="event_end_date"]');
            var startDate = form.querySelector('input[name="event_date"]');
            var recurrence = form.querySelector('[data-surfside-recurrence-fields]');
            var recurrenceType = form.querySelector('.surfside-recurrence-type');
            if (!toggle || !fields || !endDate || !recurrence || !recurrenceType) return;

            function updateMultiDay() {
                var enabled = toggle.checked;
                fields.hidden = !enabled;
                recurrence.hidden = enabled;
                endDate.disabled = !enabled;
                endDate.required = enabled;
                recurrenceType.disabled = enabled;
                toggle.setAttribute('aria-expanded', enabled ? 'true' : 'false');
                if (startDate && startDate.value) {
                    var minimum = new Date(startDate.value + 'T12:00:00');
                    minimum.setDate(minimum.getDate() + 1);
                    endDate.min = minimum.toISOString().slice(0, 10);
                }
                if (enabled) recurrenceType.value = 'none';
            }

            toggle.addEventListener('change', updateMultiDay);
            if (startDate) startDate.addEventListener('change', updateMultiDay);
            updateMultiDay();
        });

        document.querySelectorAll('.surfside-calendar-form[data-default-duration]').forEach(function (form) {
            var id = form.querySelector('input[name="event_id"]');
            if (id && parseInt(id.value || '0', 10) > 0) return;
            var start = form.querySelector('input[name="event_start_time"]');
            var end = form.querySelector('input[name="event_end_time"]');
            var allDay = form.querySelector('input[name="event_all_day"]');
            if (!start || !end) return;
            start.addEventListener('change', function () {
                if (!start.value || end.value || (allDay && allDay.checked)) return;
                var minutes = parseInt(form.getAttribute('data-default-duration') || '60', 10);
                var parts = start.value.split(':');
                var total = (parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10) + minutes) % 1440;
                end.value = String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
            });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_default_duration_script', 99);
add_action('admin_footer', 'surfside_tools_calendar_default_duration_script', 99);
