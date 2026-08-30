<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_mobile_admin_push_summary(WP_REST_Request $request) {
    $access = surfside_tools_mobile_admin_require_admin($request);
    if (is_wp_error($access)) {
        return $access;
    }

    $devices = surfside_tools_push_devices();
    $counts = surfside_tools_push_audience_counts($devices);

    return rest_ensure_response(array(
        'registered_devices' => count($devices),
        'audiences' => array(
            array('key' => 'church_updates', 'label' => 'Church Updates', 'subscribed' => absint($counts['church_updates'] ?? 0)),
            array('key' => 'events_ministries', 'label' => 'Events & Ministries', 'subscribed' => absint($counts['events_ministries'] ?? 0)),
            array('key' => 'kids_ministry', 'label' => 'Kids Ministry', 'subscribed' => absint($counts['kids_ministry'] ?? 0)),
            array('key' => 'livestream', 'label' => 'Livestream Reminders', 'subscribed' => absint($counts['livestream'] ?? 0)),
            array('key' => 'prayer_requests', 'label' => 'Prayer Requests', 'subscribed' => absint($counts['prayer_requests'] ?? 0)),
        ),
        'destinations' => array(
            array('key' => 'home', 'label' => 'Home'),
            array('key' => 'worship', 'label' => 'Worship'),
            array('key' => 'events', 'label' => 'Events'),
            array('key' => 'give', 'label' => 'Give'),
            array('key' => 'connect', 'label' => 'Connect'),
            array('key' => 'prayer-list', 'label' => 'Church Prayer List'),
        ),
    ));
}

function surfside_tools_mobile_admin_push_send(WP_REST_Request $request) {
    $access = surfside_tools_mobile_admin_require_admin($request);
    if (is_wp_error($access)) {
        return $access;
    }

    $params = (array) $request->get_json_params();
    $title = sanitize_text_field((string) ($params['title'] ?? ''));
    $body = sanitize_textarea_field((string) ($params['body'] ?? ''));
    $destination = sanitize_key((string) ($params['destination'] ?? 'home'));
    $audiences = array_values(array_unique(array_map('sanitize_key', (array) ($params['audiences'] ?? array()))));

    if ($title === '' || $body === '') {
        return new WP_Error('surfside_mobile_push_content_required', 'Title and message are required.', array('status' => 400));
    }

    if (strlen($title) > 80 || strlen($body) > 240) {
        return new WP_Error('surfside_mobile_push_content_too_long', 'Notification title or message is too long.', array('status' => 400));
    }

    $allowed_audiences = array_keys(surfside_tools_push_default_preferences());
    $audiences = array_values(array_intersect($allowed_audiences, $audiences));
    if (!$audiences) {
        return new WP_Error('surfside_mobile_push_audience_required', 'Choose at least one notification audience.', array('status' => 400));
    }

    $allowed_destinations = array('home', 'worship', 'events', 'give', 'connect', 'prayer-list');
    if (!in_array($destination, $allowed_destinations, true)) {
        $destination = 'home';
    }

    $result = surfside_tools_push_send($title, $body, $destination, $audiences);
    if (is_wp_error($result)) {
        return $result;
    }

    return rest_ensure_response(array(
        'sent' => absint($result['sent'] ?? 0),
        'removed_stale' => absint($result['removed_stale'] ?? 0),
        'errors' => array_values(array_filter(array_map('sanitize_text_field', (array) ($result['errors'] ?? array())))),
    ));
}

add_action('rest_api_init', function () {
    register_rest_route('surfside/v1', '/mobile-admin/push-notifications', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'surfside_tools_mobile_admin_push_summary',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('surfside/v1', '/mobile-admin/push-notifications/send', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'surfside_tools_mobile_admin_push_send',
        'permission_callback' => '__return_true',
    ));
});
