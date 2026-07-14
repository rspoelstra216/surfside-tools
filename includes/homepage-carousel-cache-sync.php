<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep the public homepage carousel synchronized with Manage Homepage changes.
 *
 * The homepage is cached by LiteSpeed, so saved carousel changes may otherwise
 * remain invisible until the public page cache expires or is purged manually.
 */
function surfside_tools_purge_homepage_carousel_cache() {
    $front_page_id = (int) get_option('page_on_front');

    if ($front_page_id) {
        clean_post_cache($front_page_id);
    }

    // LiteSpeed Cache: purge only the public homepage when available.
    if (has_action('litespeed_purge_url')) {
        do_action('litespeed_purge_url', home_url('/'));
    }

    if ($front_page_id && has_action('litespeed_purge_post')) {
        do_action('litespeed_purge_post', $front_page_id);
    }

    // Common fallback hook used by WP Rocket.
    if (has_action('rocket_clean_home')) {
        do_action('rocket_clean_home');
    }
}

function surfside_tools_homepage_carousel_option_updated($old_value, $new_value, $option_name) {
    if ($old_value === $new_value) {
        return;
    }

    surfside_tools_purge_homepage_carousel_cache();
}
add_action(
    'update_option_surfside_tools_homepage_carousel_images',
    'surfside_tools_homepage_carousel_option_updated',
    10,
    3
);

function surfside_tools_homepage_carousel_option_added($option_name, $value) {
    surfside_tools_purge_homepage_carousel_cache();
}
add_action(
    'add_option_surfside_tools_homepage_carousel_images',
    'surfside_tools_homepage_carousel_option_added',
    10,
    2
);

/**
 * Ensure Surfside Tools owns the public shortcode even while the legacy Code
 * Snippet remains enabled during migration testing.
 */
function surfside_tools_force_photo_carousel_shortcode() {
    if (!function_exists('surfside_tools_photo_carousel_shortcode')) {
        return;
    }

    remove_shortcode('surfside_photo_carousel');
    add_shortcode('surfside_photo_carousel', 'surfside_tools_photo_carousel_shortcode');
}
add_action('init', 'surfside_tools_force_photo_carousel_shortcode', 999);
add_action('wp', 'surfside_tools_force_photo_carousel_shortcode', 1);
