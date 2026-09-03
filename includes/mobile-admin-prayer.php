<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_mobile_admin_prayer_item($item) {
    return array(
        'id' => (string) ($item['id'] ?? ''),
        'status' => surfside_tools_prayer_list_is_active($item) ? 'active' : sanitize_key($item['status'] ?? 'unknown'),
        'name' => sanitize_text_field($item['name'] ?? ''),
        'email' => sanitize_email($item['email'] ?? ''),
        'phone' => sanitize_text_field($item['phone'] ?? ''),
        'request' => sanitize_textarea_field($item['message'] ?? ''),
        'anonymous' => ($item['name_display'] ?? '') === 'anonymous',
        'duration_days' => absint($item['duration_days'] ?? 14),
        'submitted_at' => absint($item['submitted_at'] ?? 0),
        'approved_at' => absint($item['approved_at'] ?? 0),
        'expires_at' => absint($item['expires_at'] ?? 0),
        'answered_at' => absint($item['answered_at'] ?? 0),
    );
}

function surfside_tools_mobile_admin_prayer_list(WP_REST_Request $request) {
    $access = surfside_tools_mobile_admin_require_admin($request);
    if (is_wp_error($access)) {
        return $access;
    }

    $items = surfside_tools_prayer_list_requests();
    usort($items, function ($a, $b) {
        return absint($b['submitted_at'] ?? 0) <=> absint($a['submitted_at'] ?? 0);
    });

    $pending = array();
    $active = array();
    $history = array();

    foreach ($items as $item) {
        $normalized = surfside_tools_mobile_admin_prayer_item($item);
        if (($item['status'] ?? '') === 'pending') {
            $pending[] = $normalized;
        } elseif (surfside_tools_prayer_list_is_active($item)) {
            $active[] = $normalized;
        } else {
            $history[] = $normalized;
        }
    }

    return rest_ensure_response(array(
        'pending' => $pending,
        'active' => $active,
        'history' => $history,
        'counts' => array(
            'pending' => count($pending),
            'active' => count($active),
            'history' => count($history),
        ),
    ));
}

function surfside_tools_mobile_admin_prayer_action(WP_REST_Request $request) {
    $access = surfside_tools_mobile_admin_require_admin($request);
    if (is_wp_error($access)) {
        return $access;
    }

    $id = sanitize_text_field((string) $request->get_param('id'));
    $action = sanitize_key((string) $request->get_param('action'));
    $allowed = array('approve', 'private', 'archive', 'answered', 'extend-7', 'extend-14', 'extend-30');

    if ($id === '' || !in_array($action, $allowed, true)) {
        return new WP_Error('surfside_mobile_prayer_action_invalid', 'A valid prayer request and action are required.', array('status' => 400));
    }

    $items = surfside_tools_prayer_list_requests();
    $found = false;
    $send_published_notification = false;

    foreach ($items as &$item) {
        if (($item['id'] ?? '') !== $id) {
            continue;
        }

        $found = true;
        if ($action === 'approve') {
            $was_published = (($item['status'] ?? '') === 'published');
            $days = absint($item['duration_days'] ?? 14);
            $item['status'] = 'published';
            $item['approved_at'] = current_time('timestamp');
            $item['expires_at'] = $item['approved_at'] + ($days * DAY_IN_SECONDS);
            $send_published_notification = !$was_published;
        } elseif ($action === 'private') {
            $item['status'] = 'private';
        } elseif ($action === 'archive') {
            $item['status'] = 'archived';
        } elseif ($action === 'answered') {
            $item['status'] = 'answered';
            $item['answered_at'] = current_time('timestamp');
        } elseif (strpos($action, 'extend-') === 0) {
            $days = absint(substr($action, 7));
            $base = max(current_time('timestamp'), absint($item['expires_at'] ?? 0));
            $item['status'] = 'published';
            $item['expires_at'] = $base + ($days * DAY_IN_SECONDS);
        }
        break;
    }
    unset($item);

    if (!$found) {
        return new WP_Error('surfside_mobile_prayer_not_found', 'Prayer request not found.', array('status' => 404));
    }

    surfside_tools_prayer_list_save_requests($items);
    if ($send_published_notification) {
        surfside_tools_prayer_list_send_published_notification();
    }

    return rest_ensure_response(array('updated' => true, 'id' => $id, 'action' => $action));
}

add_action('rest_api_init', function () {
    register_rest_route('surfside/v1', '/mobile-admin/prayer-requests', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'surfside_tools_mobile_admin_prayer_list',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('surfside/v1', '/mobile-admin/prayer-requests/action', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'surfside_tools_mobile_admin_prayer_action',
        'permission_callback' => '__return_true',
    ));
});
