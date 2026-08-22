<?php
/**
 * Canonical ministry data model with audience classification and contact details.
 *
 * Existing Adult Ministries records remain supported as a fallback so the
 * website can migrate without losing any current content.
 */
if (!defined('ABSPATH')) { exit; }

const SURFSIDE_TOOLS_MINISTRIES_OPTION = 'surfside_tools_ministries';
const SURFSIDE_TOOLS_MINISTRY_DEFAULT_EMAIL_OPTION = 'surfside_tools_ministry_default_email';

function surfside_tools_ministry_audience_choices() {
    return array(
        'kids' => 'Kids',
        'youth' => 'Youth',
        'adults' => 'Adults',
        'all_ages' => 'All Ages',
    );
}

function surfside_tools_sanitize_ministries($ministries) {
    $ministries = is_array($ministries) ? $ministries : array();
    $allowed_audiences = array_keys(surfside_tools_ministry_audience_choices());
    $clean = array();

    foreach ($ministries as $index => $ministry) {
        if (!is_array($ministry)) {
            continue;
        }

        $name = sanitize_text_field($ministry['name'] ?? '');
        if ($name === '') {
            continue;
        }

        $key = sanitize_key($ministry['key'] ?? '');
        if ($key === '') {
            $key = 'ministry-' . substr(md5(wp_json_encode(array($name, $index))), 0, 12);
        }

        $audiences = isset($ministry['audiences']) && is_array($ministry['audiences'])
            ? array_map('sanitize_key', $ministry['audiences'])
            : array('adults');
        $audiences = array_values(array_unique(array_intersect($audiences, $allowed_audiences)));
        if (empty($audiences)) {
            $audiences = array('adults');
        }

        // Existing records predate this field. Keep them featured until staff
        // explicitly changes the setting and saves the Ministry Manager.
        $featured = array_key_exists('featured', $ministry) ? !empty($ministry['featured']) : true;

        $clean[] = array(
            'key' => $key,
            'icon' => sanitize_text_field($ministry['icon'] ?? ''),
            'name' => $name,
            'schedule' => sanitize_text_field($ministry['schedule'] ?? ''),
            'location' => sanitize_text_field($ministry['location'] ?? ''),
            'description' => sanitize_textarea_field($ministry['description'] ?? ''),
            'audiences' => $audiences,
            'featured' => $featured,
            'contact_name' => sanitize_text_field($ministry['contact_name'] ?? ''),
            'contact_email' => sanitize_email($ministry['contact_email'] ?? ''),
            'contact_phone' => sanitize_text_field($ministry['contact_phone'] ?? ''),
        );
    }

    return $clean;
}

function surfside_tools_get_ministries() {
    $stored = get_option(SURFSIDE_TOOLS_MINISTRIES_OPTION, null);
    if (is_array($stored) && !empty($stored)) {
        return surfside_tools_sanitize_ministries($stored);
    }

    $information = function_exists('surfside_tools_get_site_information')
        ? surfside_tools_get_site_information()
        : array();
    $legacy = isset($information['adult_ministries']) && is_array($information['adult_ministries'])
        ? $information['adult_ministries']
        : array();

    foreach ($legacy as &$ministry) {
        if (is_array($ministry) && empty($ministry['audiences'])) {
            $ministry['audiences'] = array('adults');
        }
        if (is_array($ministry) && !array_key_exists('featured', $ministry)) {
            $ministry['featured'] = true;
        }
    }
    unset($ministry);

    return surfside_tools_sanitize_ministries($legacy);
}

function surfside_tools_update_ministries($ministries) {
    $clean = surfside_tools_sanitize_ministries($ministries);
    $updated = update_option(SURFSIDE_TOOLS_MINISTRIES_OPTION, $clean, false);
    if (function_exists('surfside_tools_purge_cache')) {
        surfside_tools_purge_cache();
    }
    return $updated;
}

function surfside_tools_get_ministry_default_email() {
    return sanitize_email((string) get_option(SURFSIDE_TOOLS_MINISTRY_DEFAULT_EMAIL_OPTION, ''));
}

function surfside_tools_update_ministry_default_email($email) {
    return update_option(SURFSIDE_TOOLS_MINISTRY_DEFAULT_EMAIL_OPTION, sanitize_email($email), false);
}

function surfside_tools_resolve_ministry_contact($ministry) {
    $email = sanitize_email($ministry['contact_email'] ?? '');
    $source = 'ministry';
    if ($email === '') {
        $email = surfside_tools_get_ministry_default_email();
        $source = 'default';
    }

    return array(
        'name' => sanitize_text_field($ministry['contact_name'] ?? ''),
        'email' => $email,
        'phone' => sanitize_text_field($ministry['contact_phone'] ?? ''),
        'source' => $source,
    );
}

function surfside_tools_ministry_audience_labels($ministry) {
    $choices = surfside_tools_ministry_audience_choices();
    $audiences = isset($ministry['audiences']) && is_array($ministry['audiences'])
        ? $ministry['audiences']
        : array('adults');
    $labels = array();
    foreach ($audiences as $audience) {
        if (isset($choices[$audience])) {
            $labels[] = $choices[$audience];
        }
    }
    return $labels;
}
