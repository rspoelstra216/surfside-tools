<?php

if (!defined('ABSPATH')) {
    exit;
}

const SURFSIDE_TOOLS_SITE_INFORMATION_OPTION = 'surfside_tools_site_information';

/**
 * Confirmed public information used throughout Surfside's website.
 */
function surfside_tools_site_information_defaults() {
    $defaults = array(
        'identity' => array(
            'name' => 'Surfside Community Fellowship',
            'logo_id' => 0,
            'tagline' => 'The Perfect Church for Imperfect People.',
            'phone' => '(321) 617-2111',
            'contact_url' => '/contact/#Contact',
        ),
        'location' => array(
            'venue' => 'Clearlake First Baptist Church',
            'street' => '1640 Minnie Street',
            'city' => 'Cocoa',
            'state' => 'FL',
            'postal_code' => '32926',
        ),
        'services' => array(
            array(
                'key' => 'saturday',
                'weekday' => 6,
                'day' => 'Saturday',
                'label' => 'Saturday Worship',
                'time' => '18:00',
                'livestream' => false,
            ),
            array(
                'key' => 'sunday',
                'weekday' => 7,
                'day' => 'Sunday',
                'label' => 'Sunday Worship',
                'time' => '09:45',
                'livestream' => true,
            ),
        ),
        'navigation' => array(
            array('key' => 'plan-visit', 'label' => 'Plan Your Visit', 'type' => 'custom', 'page_id' => 0, 'url' => '/plan-your-visit/', 'new_tab' => false),
            array('key' => 'ministries', 'label' => 'Ministries', 'type' => 'custom', 'page_id' => 0, 'url' => '/ministries/', 'new_tab' => false),
            array('key' => 'events', 'label' => 'Events', 'type' => 'custom', 'page_id' => 0, 'url' => '/events/', 'new_tab' => false),
            array('key' => 'watch-live', 'label' => 'Watch Live', 'type' => 'custom', 'page_id' => 0, 'url' => '/watch-live/', 'new_tab' => false),
            array('key' => 'staff', 'label' => 'Staff', 'type' => 'custom', 'page_id' => 0, 'url' => '/staff/', 'new_tab' => false),
            array('key' => 'give', 'label' => 'Give', 'type' => 'custom', 'page_id' => 0, 'url' => '/give/', 'new_tab' => false),
            array('key' => 'contact', 'label' => 'Contact', 'type' => 'custom', 'page_id' => 0, 'url' => '/contact/#Contact', 'new_tab' => false),
        ),
        'social' => array(
            'facebook' => array(
                'label' => 'Facebook',
                'url' => 'https://www.facebook.com/SurfsideCommunityFellowship',
            ),
            'youtube' => array(
                'label' => 'YouTube',
                'url' => 'https://www.youtube.com/@addpastor',
            ),
            'instagram' => array(
                'label' => 'Instagram',
                'url' => 'https://www.instagram.com/surfside_fellowship',
            ),
        ),
    );

    return apply_filters('surfside_tools_site_information_defaults', $defaults);
}

function surfside_tools_site_information_sanitize_url($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (strpos($value, '/') === 0 || strpos($value, '#') === 0) {
        return sanitize_text_field($value);
    }

    return esc_url_raw($value);
}

function surfside_tools_site_information_sanitize($value) {
    $defaults = surfside_tools_site_information_defaults();
    $value = is_array($value) ? $value : array();

    $identity = isset($value['identity']) && is_array($value['identity']) ? $value['identity'] : array();
    $location = isset($value['location']) && is_array($value['location']) ? $value['location'] : array();

    $clean = array(
        'identity' => array(
            'name' => sanitize_text_field($identity['name'] ?? $defaults['identity']['name']),
            'logo_id' => absint($identity['logo_id'] ?? 0),
            'tagline' => sanitize_text_field($identity['tagline'] ?? $defaults['identity']['tagline']),
            'phone' => sanitize_text_field($identity['phone'] ?? $defaults['identity']['phone']),
            'contact_url' => surfside_tools_site_information_sanitize_url($identity['contact_url'] ?? $defaults['identity']['contact_url']),
        ),
        'location' => array(
            'venue' => sanitize_text_field($location['venue'] ?? $defaults['location']['venue']),
            'street' => sanitize_text_field($location['street'] ?? $defaults['location']['street']),
            'city' => sanitize_text_field($location['city'] ?? $defaults['location']['city']),
            'state' => strtoupper(substr(sanitize_text_field($location['state'] ?? $defaults['location']['state']), 0, 2)),
            'postal_code' => sanitize_text_field($location['postal_code'] ?? $defaults['location']['postal_code']),
        ),
        'services' => array(),
        'navigation' => array(),
        'social' => array(),
    );

    $services = isset($value['services']) && is_array($value['services']) ? $value['services'] : $defaults['services'];
    foreach ($services as $index => $service) {
        if (!is_array($service)) {
            continue;
        }

        $time = sanitize_text_field($service['time'] ?? '');
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $time = '';
        }

        $weekday = absint($service['weekday'] ?? 0);
        if ($weekday < 1 || $weekday > 7) {
            continue;
        }

        $key = sanitize_key($service['key'] ?? '');
        if ($key === '') {
            $key = 'service-' . substr(md5(wp_json_encode(array(
                $weekday,
                $service['label'] ?? '',
                $time,
                $index,
            ))), 0, 12);
        }

        $weekday_names = array(
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
            5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
        );
        $clean['services'][] = array(
            'key' => $key,
            'weekday' => $weekday,
            'day' => $weekday_names[$weekday],
            'label' => sanitize_text_field($service['label'] ?? 'Worship Service'),
            'time' => $time,
            'livestream' => array_key_exists('livestream', $service)
                ? !empty($service['livestream'])
                : sanitize_key($service['key'] ?? '') === 'sunday',
        );
    }

    if (empty($clean['services'])) {
        $clean['services'] = $defaults['services'];
    }

    $navigation = isset($value['navigation']) && is_array($value['navigation']) ? $value['navigation'] : $defaults['navigation'];
    foreach ($navigation as $index => $link) {
        if (!is_array($link)) {
            continue;
        }
        $label = sanitize_text_field($link['label'] ?? '');
        if ($label === '') {
            continue;
        }
        $key = sanitize_key($link['key'] ?? (is_string($index) ? $index : ''));
        if ($key === '') {
            $key = 'nav-' . substr(md5(wp_json_encode(array($label, $index))), 0, 12);
        }
        $type = sanitize_key($link['type'] ?? '');
        $page_id = absint($link['page_id'] ?? 0);
        $url = surfside_tools_site_information_sanitize_url($link['url'] ?? '');
        if ($type !== 'page' && $type !== 'custom') {
            $type = 'custom';
        }
        if ($type === 'page') {
            $page = $page_id ? get_post($page_id) : null;
            if (!($page instanceof WP_Post) || $page->post_type !== 'page' || $page->post_status !== 'publish') {
                $type = 'custom';
                $page_id = 0;
            } else {
                $url = '';
            }
        }
        $clean['navigation'][] = array(
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'page_id' => $page_id,
            'url' => $url,
            'new_tab' => $type === 'custom' && !empty($link['new_tab']),
        );
    }
    if (empty($clean['navigation'])) {
        $clean['navigation'] = $defaults['navigation'];
    }

    foreach ($defaults['social'] as $key => $default_link) {
        $link = isset($value['social'][$key]) && is_array($value['social'][$key])
            ? $value['social'][$key]
            : $default_link;
        $clean['social'][$key] = array(
            'label' => sanitize_text_field($link['label'] ?? $default_link['label']),
            'url' => surfside_tools_site_information_sanitize_url($link['url'] ?? $default_link['url']),
        );
    }

    return $clean;
}

/**
 * Read the canonical site information, filling newly introduced fields safely.
 */
function surfside_tools_get_site_information() {
    $defaults = surfside_tools_site_information_defaults();
    $stored = get_option(SURFSIDE_TOOLS_SITE_INFORMATION_OPTION, array());
    if (!is_array($stored)) {
        $stored = array();
    }

    $merged = array_replace_recursive($defaults, $stored);
    if (isset($stored['services']) && is_array($stored['services'])) {
        $merged['services'] = $stored['services'];
    }
    if (isset($stored['navigation']) && is_array($stored['navigation'])) {
        $merged['navigation'] = $stored['navigation'];
    }

    return apply_filters(
        'surfside_tools_site_information',
        surfside_tools_site_information_sanitize($merged)
    );
}

function surfside_tools_update_site_information($value) {
    $updated = update_option(
        SURFSIDE_TOOLS_SITE_INFORMATION_OPTION,
        surfside_tools_site_information_sanitize($value),
        false
    );

    if (function_exists('surfside_tools_purge_cache')) {
        surfside_tools_purge_cache();
    }

    return $updated;
}

/**
 * Persist the confirmed defaults for existing installations after deployment.
 */
function surfside_tools_site_information_seed() {
    if (get_option(SURFSIDE_TOOLS_SITE_INFORMATION_OPTION, null) === null) {
        add_option(
            SURFSIDE_TOOLS_SITE_INFORMATION_OPTION,
            surfside_tools_site_information_defaults(),
            '',
            false
        );
    }
}
add_action('init', 'surfside_tools_site_information_seed', 5);

function surfside_tools_site_information_register_setting() {
    register_setting('surfside_tools', SURFSIDE_TOOLS_SITE_INFORMATION_OPTION, array(
        'type' => 'array',
        'sanitize_callback' => 'surfside_tools_site_information_sanitize',
        'default' => surfside_tools_site_information_defaults(),
        'show_in_rest' => false,
    ));
}
add_action('admin_init', 'surfside_tools_site_information_register_setting');

function surfside_tools_site_information_url($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (strpos($value, '/') === 0) {
        return home_url('/' . ltrim($value, '/'));
    }
    if (strpos($value, '#') === 0) {
        return $value;
    }
    return $value;
}

function surfside_tools_site_information_navigation_url($link) {
    if (!is_array($link)) {
        return '';
    }
    if (($link['type'] ?? '') === 'page') {
        $page_id = absint($link['page_id'] ?? 0);
        $permalink = $page_id ? get_permalink($page_id) : '';
        return $permalink ? $permalink : '';
    }
    return surfside_tools_site_information_url($link['url'] ?? '');
}

function surfside_tools_site_information_logo_url($information = null, $size = 'full') {
    $information = is_array($information) ? $information : surfside_tools_get_site_information();
    $logo_id = absint($information['identity']['logo_id'] ?? 0);

    if ($logo_id > 0) {
        $logo_url = wp_get_attachment_image_url($logo_id, $size);
        if ($logo_url) {
            return $logo_url;
        }
    }

    return SURFSIDE_TOOLS_URL . 'assets/images/surfside-logo-restored.png';
}

function surfside_tools_site_information_address($information = null) {
    $information = is_array($information) ? $information : surfside_tools_get_site_information();
    $location = $information['location'] ?? array();
    $city = trim((string) ($location['city'] ?? ''));
    $region = trim(implode(' ', array_filter(array(
        trim((string) ($location['state'] ?? '')),
        trim((string) ($location['postal_code'] ?? '')),
    ))));
    $city_line = $city;
    if ($city !== '' && $region !== '') {
        $city_line .= ', ' . $region;
    } elseif ($region !== '') {
        $city_line = $region;
    }

    return implode(', ', array_filter(array(
        trim((string) ($location['street'] ?? '')),
        $city_line,
    )));
}

function surfside_tools_site_information_maps_url($information = null) {
    $address = surfside_tools_site_information_address($information);
    if ($address === '') {
        return '';
    }
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
}

function surfside_tools_site_information_format_time($time) {
    $timestamp = strtotime((string) $time);
    return $timestamp ? date_i18n('g:i A', $timestamp) : '';
}

/**
 * Return every weekly service in weekday and start-time order.
 */
function surfside_tools_site_information_services() {
    $information = surfside_tools_get_site_information();
    $services = array();

    foreach ((array) ($information['services'] ?? array()) as $service) {
        $weekday = absint($service['weekday'] ?? 0);
        $time = (string) ($service['time'] ?? '');
        if ($weekday < 1 || $weekday > 7 || $time === '') {
            continue;
        }

        $services[] = array(
            'key' => sanitize_key($service['key'] ?? ''),
            'weekday' => $weekday,
            'day' => (string) ($service['day'] ?? ''),
            'label' => (string) ($service['label'] ?? 'Worship Service'),
            'time' => surfside_tools_site_information_format_time($time),
            'time_24' => $time,
            'livestream' => !empty($service['livestream']),
        );
    }

    usort($services, function ($first, $second) {
        $day_order = $first['weekday'] <=> $second['weekday'];
        return $day_order !== 0 ? $day_order : strcmp($first['time_24'], $second['time_24']);
    });

    return apply_filters('surfside_tools_site_information_services', $services);
}

/**
 * Return the first service on each ISO weekday for legacy service-aware features.
 */
function surfside_tools_site_information_service_schedule() {
    $schedule = array();

    foreach (surfside_tools_site_information_services() as $service) {
        $weekday = (int) $service['weekday'];
        if (!isset($schedule[$weekday])) {
            $schedule[$weekday] = $service;
        }
    }

    return apply_filters('surfside_tools_site_information_service_schedule', $schedule);
}
