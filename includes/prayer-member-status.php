<?php
/** Member-facing prayer request lifecycle support for the Surfside app. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_prayer_member_status_value($item) {
    $status = sanitize_key($item['status'] ?? '');
    if ($status === 'published' && function_exists('surfside_tools_prayer_list_is_active') && !surfside_tools_prayer_list_is_active($item)) {
        return 'expired';
    }
    return in_array($status, array('pending','published','private','archived','answered'), true) ? $status : 'unknown';
}

function surfside_tools_prayer_member_status_response(WP_REST_Request $request) {
    $id = sanitize_text_field($request->get_param('id'));
    if ($id === '' || !function_exists('surfside_tools_prayer_list_requests')) {
        return new WP_Error('surfside_prayer_status_not_found','Prayer request not found.',array('status'=>404));
    }
    foreach (surfside_tools_prayer_list_requests() as $item) {
        if (!hash_equals((string)($item['id'] ?? ''), $id)) continue;
        return rest_ensure_response(array(
            'api_version' => 1,
            'id' => $id,
            'status' => surfside_tools_prayer_member_status_value($item),
            'submitted_at' => absint($item['submitted_at'] ?? 0),
            'approved_at' => absint($item['approved_at'] ?? 0),
            'expires_at' => absint($item['expires_at'] ?? 0),
            'answered_at' => absint($item['answered_at'] ?? 0),
        ));
    }
    return new WP_Error('surfside_prayer_status_not_found','Prayer request not found.',array('status'=>404));
}

function surfside_tools_prayer_member_status_register_api() {
    register_rest_route('surfside/v1','/prayer-request-status/(?P<id>[A-Za-z0-9-]+)',array(
        'methods'=>WP_REST_Server::READABLE,
        'callback'=>'surfside_tools_prayer_member_status_response',
        'permission_callback'=>'__return_true',
    ));
}
add_action('rest_api_init','surfside_tools_prayer_member_status_register_api');

/**
 * Add the WordPress prayer-list UUID to successful app contact responses.
 * The existing contact callback already creates the pending record; this locates
 * that just-created record without changing the established submission path.
 */
function surfside_tools_prayer_member_status_attach_id($response, $handler, $request) {
    if (!($request instanceof WP_REST_Request) || $request->get_route() !== '/surfside/v1/contact' || $request->get_method() !== 'POST') return $response;
    if (is_wp_error($response) || !function_exists('surfside_tools_prayer_list_requests')) return $response;
    $params = (array)$request->get_json_params();
    if (sanitize_key($params['category'] ?? '') !== 'prayer' || sanitize_key($params['prayer_privacy'] ?? '') !== 'church-list') return $response;

    $message = sanitize_textarea_field($params['message'] ?? '');
    $email = sanitize_email($params['email'] ?? '');
    $name = sanitize_text_field($params['name'] ?? '');
    $items = array_reverse(surfside_tools_prayer_list_requests());
    $matched_id = '';
    foreach ($items as $item) {
        if (($item['status'] ?? '') !== 'pending') continue;
        if (sanitize_textarea_field($item['message'] ?? '') !== $message) continue;
        if (sanitize_email($item['email'] ?? '') !== $email) continue;
        if (sanitize_text_field($item['name'] ?? '') !== $name) continue;
        $matched_id = (string)($item['id'] ?? '');
        break;
    }
    if ($matched_id === '') return $response;

    $rest_response = rest_ensure_response($response);
    $data = (array)$rest_response->get_data();
    $data['prayer_request_id'] = $matched_id;
    $rest_response->set_data($data);
    return $rest_response;
}
add_filter('rest_request_after_callbacks','surfside_tools_prayer_member_status_attach_id',10,3);
