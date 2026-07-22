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
            ),
            array(
                'key' => 'sunday',
                'weekday' => 7,
                'day' => 'Sunday',
                'label' => 'Sunday Worship',
                'time' => '09:45',
            ),
        ),
        'navigation' => array(
            'plan_visit' => array('label' => 'Plan Your Visit', 'url' => '/plan-your-visit/'),
            'ministries' => array('label' => 'Ministries', 'url' => '/ministries/'),
            'events' => array('label' => 'Events', 'url' => '/events/'),
            'watch_live' => array('label' => 'Watch Live', 'url' => '/watch-live/'),
            'staff' => array('label' => 'Staff', 'url' => '/staff/'),
            'give' => array('label' => 'Give', 'url' => '/give/'),
            'contact' => array('label' => 'Contact', 'url' => '/contact/#Contact'),
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

        $key = sanitize_key($service['key'] ?? ($service['day'] ?? 'service-' . $index));
        $clean['services'][] = array(
            'key' => $key !== '' ? $key : 'service-' . $index,
            'weekday' => $weekday,
            'day' => sanitize_text_field($service['day'] ?? ''),
            'label' => sanitize_text_field($service['label'] ?? 'Worship Service'),
            'time' => $time,
        );
    }

    if (empty($clean['services'])) {
        $clean['services'] = $defaults['services'];
    }

    foreach ($defaults['navigation'] as $key => $default_link) {
        $link = isset($value['navigation'][$key]) && is_array($value['navigation'][$key])
            ? $value['navigation'][$key]
            : $default_link;
        $clean['navigation'][$key] = array(
            'label' => sanitize_text_field($link['label'] ?? $default_link['label']),
            'url' => surfside_tools_site_information_sanitize_url($link['url'] ?? $default_link['url']),
        );
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

    return apply_filters(
        'surfside_tools_site_information',
        surfside_tools_site_information_sanitize($merged)
    );
}

function surfside_tools_update_site_information($value) {
    return update_option(
        SURFSIDE_TOOLS_SITE_INFORMATION_OPTION,
        surfside_tools_site_information_sanitize($value),
        false
    );
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
 * Return the shared schedule keyed by ISO weekday for service-aware features.
 */
function surfside_tools_site_information_service_schedule() {
    $information = surfside_tools_get_site_information();
    $schedule = array();

    foreach ((array) ($information['services'] ?? array()) as $service) {
        $weekday = absint($service['weekday'] ?? 0);
        if ($weekday < 1 || $weekday > 7) {
            continue;
        }

        $schedule[$weekday] = array(
            'key' => sanitize_key($service['key'] ?? ''),
            'day' => (string) ($service['day'] ?? ''),
            'label' => (string) ($service['label'] ?? 'Worship Service'),
            'time' => surfside_tools_site_information_format_time($service['time'] ?? ''),
            'time_24' => (string) ($service['time'] ?? ''),
        );
    }

    ksort($schedule);
    return apply_filters('surfside_tools_site_information_service_schedule', $schedule);
}
