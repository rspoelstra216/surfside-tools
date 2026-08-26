<?php
/** Optional presentation groups for related calendar events. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_calendar_save_event_group($post_id) {
    if (!isset($_POST['event_group'])) return;
    $group = sanitize_text_field(wp_unslash($_POST['event_group']));
    if ($group === '') { delete_post_meta($post_id, '_surfside_event_group'); return; }
    update_post_meta($post_id, '_surfside_event_group', $group);
}
add_action('save_post_surfside_event', 'surfside_tools_calendar_save_event_group', 20, 1);

function surfside_tools_calendar_add_event_group_field($output, $tag) {
    if ($tag !== 'surfside_tools_calendar_manager' || !is_user_logged_in() || !current_user_can('upload_files')) return $output;
    $event_id = isset($_GET['edit_event']) ? absint($_GET['edit_event']) : 0;
    $group = $event_id ? (string)get_post_meta($event_id, '_surfside_event_group', true) : '';
    $field = '<label class="surfside-calendar-event-group"><span>Event Group</span><input type="text" name="event_group" value="' . esc_attr($group) . '" maxlength="80" placeholder="Brazilian Jiu Jitsu"><small>Optional. Use only when separate recurring events should appear together in the app.</small></label>';
    $updated = preg_replace('~<fieldset class="surfside-calendar-recurrence"~', $field . '<fieldset class="surfside-calendar-recurrence"', $output, 1);
    if (!is_string($updated)) return $output;
    return $updated . '<style>.surfside-calendar-event-group{display:grid;gap:7px}.surfside-calendar-event-group>span{font-weight:700}.surfside-calendar-event-group small{color:#687480;font-size:.85rem;line-height:1.4}</style>';
}
add_filter('do_shortcode_tag', 'surfside_tools_calendar_add_event_group_field', 30, 2);

/**
 * Add the optional group label to the existing mobile events payload without
 * changing recurrence generation or the public website calendar.
 */
function surfside_tools_calendar_add_event_groups_to_mobile_api($result, $server, $request) {
    if ($request->get_route() !== '/surfside/v1/events' || is_wp_error($result)) return $result;
    $response = rest_ensure_response($result);
    $data = $response->get_data();
    if (!is_array($data) || !isset($data['events']) || !is_array($data['events'])) return $result;
    foreach ($data['events'] as &$event) {
        $event_id = absint($event['id'] ?? 0);
        $event['event_group'] = $event_id ? sanitize_text_field((string)get_post_meta($event_id, '_surfside_event_group', true)) : '';
    }
    unset($event);
    $response->set_data($data);
    return $response;
}
add_filter('rest_post_dispatch', 'surfside_tools_calendar_add_event_groups_to_mobile_api', 20, 3);
