<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_mobile_admin_featured_payload() {
    $settings = function_exists('surfside_tools_app_settings') ? surfside_tools_app_settings() : array();
    $featured = function_exists('surfside_tools_app_featured_announcement') ? surfside_tools_app_featured_announcement() : array();
    $enabled = !empty($settings['featured_enabled']);
    $active = !empty($featured['active']);

    $status = 'Not enabled';
    if ($enabled) {
        $status = $active ? 'Currently showing' : 'Scheduled or expired';
    }

    return array(
        'enabled' => $enabled,
        'active' => $active,
        'status' => $status,
        'headline' => sanitize_text_field((string) ($settings['featured_headline'] ?? '')),
        'message' => sanitize_textarea_field((string) ($settings['featured_message'] ?? '')),
        'button_label' => sanitize_text_field((string) ($settings['featured_button_label'] ?? '')),
        'button_url' => esc_url_raw((string) ($settings['featured_button_url'] ?? '')),
        'starts_at' => sanitize_text_field((string) ($settings['featured_starts_at'] ?? '')),
        'ends_at' => sanitize_text_field((string) ($settings['featured_ends_at'] ?? '')),
    );
}

function surfside_tools_mobile_admin_featured_get(WP_REST_Request $request) {
    $access = surfside_tools_mobile_admin_require_admin($request);
    if (is_wp_error($access)) {
        return $access;
    }

    return rest_ensure_response(surfside_tools_mobile_admin_featured_payload());
}

function surfside_tools_mobile_admin_featured_save(WP_REST_Request $request) {
    $access = surfside_tools_mobile_admin_require_admin($request);
    if (is_wp_error($access)) {
        return $access;
    }

    $params = (array) $request->get_json_params();
    $enabled = !empty($params['enabled']);
    $headline = sanitize_text_field((string) ($params['headline'] ?? ''));
    $message = sanitize_textarea_field((string) ($params['message'] ?? ''));
    $button_label = sanitize_text_field((string) ($params['button_label'] ?? ''));
    $button_url = esc_url_raw(trim((string) ($params['button_url'] ?? '')));
    $starts_at = surfside_tools_featured_announcement_valid_local_datetime(sanitize_text_field((string) ($params['starts_at'] ?? '')));
    $ends_at = surfside_tools_featured_announcement_valid_local_datetime(sanitize_text_field((string) ($params['ends_at'] ?? '')));

    if (strlen($headline) > 90 || strlen($message) > 240 || strlen($button_label) > 30) {
        return new WP_Error('surfside_mobile_featured_too_long', 'Featured announcement content is too long.', array('status' => 400));
    }

    if ($enabled && $ends_at === '') {
        return new WP_Error('surfside_mobile_featured_end_required', 'Run Until is required when the featured announcement is enabled.', array('status' => 400));
    }

    $settings = function_exists('surfside_tools_app_settings') ? surfside_tools_app_settings() : array();
    $settings['featured_enabled'] = $enabled ? 1 : 0;
    $settings['featured_headline'] = $headline;
    $settings['featured_message'] = $message;
    $settings['featured_button_label'] = $button_label;
    $settings['featured_button_url'] = $button_url;
    $settings['featured_starts_at'] = $starts_at;
    $settings['featured_ends_at'] = $ends_at;
    update_option('surfside_tools_app_settings', $settings);

    return rest_ensure_response(array(
        'success' => true,
        'featured_announcement' => surfside_tools_mobile_admin_featured_payload(),
    ));
}

add_action('rest_api_init', function () {
    register_rest_route('surfside/v1', '/mobile-admin/featured-announcement', array(
        array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'surfside_tools_mobile_admin_featured_get',
            'permission_callback' => '__return_true',
        ),
        array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'surfside_tools_mobile_admin_featured_save',
            'permission_callback' => '__return_true',
        ),
    ));
});
