<?php
/** Optional presentation groups for related calendar events. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_calendar_existing_event_groups() {
    global $wpdb;
    $values = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' ORDER BY meta_value ASC",
        '_surfside_event_group'
    ));
    return array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) $values))));
}

function surfside_tools_calendar_save_event_group($post_id) {
    if (!isset($_POST['event_group'])) return;
    $group = sanitize_text_field(wp_unslash($_POST['event_group']));
    if ($group === '__new__') {
        $group = isset($_POST['event_group_new']) ? sanitize_text_field(wp_unslash($_POST['event_group_new'])) : '';
    }
    if ($group === '') { delete_post_meta($post_id, '_surfside_event_group'); return; }
    update_post_meta($post_id, '_surfside_event_group', $group);
}
add_action('save_post_surfside_event', 'surfside_tools_calendar_save_event_group', 20, 1);

function surfside_tools_calendar_add_event_group_field($output, $tag) {
    if ($tag !== 'surfside_tools_calendar_manager' || !is_user_logged_in() || !current_user_can('upload_files')) return $output;
    $event_id = isset($_GET['edit_event']) ? absint($_GET['edit_event']) : 0;
    $group = $event_id ? sanitize_text_field((string)get_post_meta($event_id, '_surfside_event_group', true)) : '';
    $groups = surfside_tools_calendar_existing_event_groups();
    if ($group !== '' && !in_array($group, $groups, true)) { $groups[] = $group; sort($groups, SORT_NATURAL | SORT_FLAG_CASE); }

    $options = '<option value=""' . selected($group, '', false) . '>No group</option>';
    foreach ($groups as $existing_group) {
        $options .= '<option value="' . esc_attr($existing_group) . '"' . selected($group, $existing_group, false) . '>' . esc_html($existing_group) . '</option>';
    }
    $options .= '<option value="__new__">+ Add New Group…</option>';

    $field = '<div class="surfside-calendar-event-group" data-surfside-event-group>'
        . '<label><span>Event Group</span><select name="event_group" data-surfside-event-group-select>' . $options . '</select></label>'
        . '<label data-surfside-event-group-new hidden><span>New Group Name</span><input type="text" name="event_group_new" maxlength="80" placeholder="Brazilian Jiu Jitsu"></label>'
        . '<small>Optional. Use only when separate recurring events should appear together in the app.</small>'
        . '</div>';
    $updated = preg_replace('~<fieldset class="surfside-calendar-recurrence"~', $field . '<fieldset class="surfside-calendar-recurrence"', $output, 1);
    if (!is_string($updated)) return $output;

    $enhancement = '<style>.surfside-calendar-event-group{display:grid;gap:7px}.surfside-calendar-event-group label{display:grid;gap:7px}.surfside-calendar-event-group label>span{font-weight:700}.surfside-calendar-event-group select,.surfside-calendar-event-group input{width:100%;box-sizing:border-box}.surfside-calendar-event-group small{color:#687480;font-size:.85rem;line-height:1.4}</style>'
        . '<script>(function(){const root=document.querySelector("[data-surfside-event-group]");if(!root)return;const select=root.querySelector("[data-surfside-event-group-select]");const add=root.querySelector("[data-surfside-event-group-new]");if(!select||!add)return;const sync=()=>{add.hidden=select.value!=="__new__";const input=add.querySelector("input");if(input)input.required=select.value==="__new__";};select.addEventListener("change",sync);sync();})();</script>';
    return $updated . $enhancement;
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
