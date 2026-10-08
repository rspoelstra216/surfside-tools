<?php
/** Member prayer history and authenticated management. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_prayer_member_status_value($item) {
    $status = sanitize_key($item['status'] ?? '');
    if ($status === 'published' && !surfside_tools_prayer_list_is_active($item)) return 'expired';
    return in_array($status, array('pending','published','private','archived','answered'), true) ? $status : 'unknown';
}

function surfside_tools_prayer_member_metadata($item) {
    return array(
        'api_version'=>1, 'id'=>(string)$item['id'],
        'status'=>surfside_tools_prayer_member_status_value($item),
        'submitted_at'=>absint($item['submitted_at'] ?? 0),
        'approved_at'=>absint($item['approved_at'] ?? 0),
        'expires_at'=>absint($item['expires_at'] ?? 0),
        'answered_at'=>absint($item['answered_at'] ?? 0),
    );
}

/** Never infer ownership from a submitted name, email, UUID, or client UID. */
function surfside_tools_prayer_member_uid(WP_REST_Request $request, $optional=false) {
    $header = trim((string)$request->get_header('authorization'));
    if ($optional && $header === '') return '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
        return new WP_Error('surfside_prayer_sign_in', 'Please sign in to manage your prayer request.', array('status'=>401));
    }
    $claims = surfside_tools_verify_firebase_id_token($matches[1]);
    if (is_wp_error($claims)) return $claims;
    $uid = (string)($claims['sub'] ?? '');
    if ($uid === '') return new WP_Error('surfside_prayer_sign_in', 'Please sign in again.', array('status'=>401));
    return $uid;
}

function surfside_tools_prayer_member_status_response(WP_REST_Request $request) {
    $id = sanitize_text_field($request->get_param('id'));
    foreach (surfside_tools_prayer_list_requests() as $item) {
        if ((string)($item['id'] ?? '') === $id) return rest_ensure_response(surfside_tools_prayer_member_metadata($item));
    }
    return new WP_Error('surfside_prayer_status_not_found', 'Prayer request not found.', array('status'=>404));
}

function surfside_tools_prayer_member_owned_response($item) {
    $data = surfside_tools_prayer_member_metadata($item);
    // Answered/archived requests cannot be reopened through member controls.
    $data['can_manage'] = in_array($item['status'] ?? '', array('pending','published','private'), true);
    $data['request'] = (string)($item['message'] ?? '');
    $data['name_display'] = (string)($item['name_display'] ?? 'named');
    $data['duration_days'] = absint($item['duration_days'] ?? 14);
    $response = rest_ensure_response($data);
    if (is_object($response) && method_exists($response, 'header')) {
        $response->header('Cache-Control', 'private, no-store');
        $response->header('Vary', 'Authorization');
    }
    return $response;
}

function surfside_tools_prayer_member_manage(WP_REST_Request $request) {
    $uid = surfside_tools_prayer_member_uid($request);
    if (is_wp_error($uid)) return $uid;
    $id = sanitize_text_field($request->get_param('id'));
    $items = surfside_tools_prayer_list_requests();
    foreach ($items as &$item) {
        if ((string)($item['id'] ?? '') !== $id) continue;
        // Ownerless legacy records stay read-only. Return no private content.
        if (empty($item['owner_uid']) || !hash_equals((string)$item['owner_uid'], $uid)) {
            return new WP_Error('surfside_prayer_not_owned', 'This request cannot be managed from your account. Please contact church staff.', array('status'=>403));
        }
        if ($request->get_method() === 'GET') return surfside_tools_prayer_member_owned_response($item);
        $params = (array)$request->get_json_params();
        $action = sanitize_key($params['action'] ?? '');
        if (!in_array($action, array('update','answered','extend'), true)) {
            return new WP_Error('surfside_prayer_invalid_action', 'Choose a valid action.', array('status'=>400));
        }
        if (!in_array($item['status'] ?? '', array('pending','published','private'), true)) {
            return new WP_Error('surfside_prayer_closed', 'This request is already closed.', array('status'=>409));
        }
        if ($action === 'update') {
            $message = sanitize_textarea_field($params['message'] ?? '');
            $display = sanitize_key($params['name_display'] ?? '');
            $days = absint($params['duration_days'] ?? 0);
            if ($message === '' || strlen($message) > 2000 || !in_array($display,array('named','anonymous'),true) || !in_array($days,array(7,14,30),true)) {
                return new WP_Error('surfside_prayer_invalid_update', 'Provide a message up to 2000 characters, a name display, and a valid duration.', array('status'=>400));
            }
            $item['message'] = $message;
            $item['name_display'] = $display;
            $item['duration_days'] = $days;
            $item['status'] = 'pending';
            $item['updated_at'] = current_time('timestamp');
            // Retain approval/notification history so an edited request stays silent.
        } elseif ($action === 'answered') {
            $item['status'] = 'answered';
            $item['answered_at'] = current_time('timestamp');
        } else {
            $days = absint($params['duration_days'] ?? 0);
            if (($item['status'] ?? '') !== 'published' || !in_array($days,array(7,14,30),true)) {
                return new WP_Error('surfside_prayer_invalid_extension', 'Only an approved request can be extended by 7, 14, or 30 days.', array('status'=>400));
            }
            $item['expires_at'] = max(current_time('timestamp'),absint($item['expires_at'] ?? 0)) + $days * DAY_IN_SECONDS;
        }
        surfside_tools_prayer_list_save_requests($items);
        return surfside_tools_prayer_member_owned_response($item);
    }
    unset($item);
    return new WP_Error('surfside_prayer_status_not_found', 'Prayer request not found.', array('status'=>404));
}

add_action('rest_api_init', function () {
    register_rest_route('surfside/v1','/prayer-request-status/(?P<id>[A-Za-z0-9-]+)',array(
        'methods'=>WP_REST_Server::READABLE, 'callback'=>'surfside_tools_prayer_member_status_response', 'permission_callback'=>'__return_true',
    ));
    // Authentication and owner authorization run in the callback for both methods.
    register_rest_route('surfside/v1','/my-prayer-requests/(?P<id>[A-Za-z0-9-]+)',array(
        'methods'=>array('GET','POST'), 'callback'=>'surfside_tools_prayer_member_manage', 'permission_callback'=>'__return_true',
    ));
});
