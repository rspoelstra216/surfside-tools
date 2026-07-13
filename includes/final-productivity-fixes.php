<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repair the newly introduced nested Settings page and refresh permalink rules
 * once. This is intentionally independent of the plugin version because the
 * release workflow does not bump the installed option during normal cPanel
 * deployments.
 */
function surfside_tools_repair_frontend_settings_route() {
    $dashboard = get_page_by_path('dashboard');
    if (!$dashboard) {
        return;
    }

    $settings = get_page_by_path('dashboard/settings');
    if (!$settings) {
        $standalone = get_page_by_path('settings');
        if ($standalone) {
            wp_update_post(array(
                'ID' => $standalone->ID,
                'post_name' => 'settings',
                'post_parent' => $dashboard->ID,
                'post_status' => 'publish',
                'post_content' => '[surfside_staff_settings]',
            ));
            $settings = get_post($standalone->ID);
        } else {
            $settings_id = wp_insert_post(array(
                'post_title' => 'Settings',
                'post_name' => 'settings',
                'post_content' => '[surfside_staff_settings]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_parent' => $dashboard->ID,
            ));
            if ($settings_id && !is_wp_error($settings_id)) {
                $settings = get_post($settings_id);
            }
        }
    }

    if ($settings && ((int) $settings->post_parent !== (int) $dashboard->ID || $settings->post_name !== 'settings')) {
        wp_update_post(array(
            'ID' => $settings->ID,
            'post_name' => 'settings',
            'post_parent' => $dashboard->ID,
            'post_status' => 'publish',
        ));
    }

    if (get_option('surfside_tools_settings_route_rewrite_2026_07') !== 'complete') {
        flush_rewrite_rules(false);
        update_option('surfside_tools_settings_route_rewrite_2026_07', 'complete', false);
    }
}
add_action('init', 'surfside_tools_repair_frontend_settings_route', 99);

/**
 * Final UI compatibility fixes for the Productivity milestone.
 *
 * The local saved-place menu was sitting above Google's .pac-container and
 * remained open with an empty-state message. Google Places could be working
 * underneath it while appearing broken. Hide the local menu when it has no
 * real results and ensure Google's suggestion list is always on top.
 */
function surfside_tools_final_productivity_fix_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }
    ?>
    <style>
        .pac-container {
            z-index: 2147483647 !important;
        }

        .surfside-staff-grid .surfside-staff-button-secondary {
            background: #0b4f9c;
            color: #fff !important;
            border-color: #0b4f9c;
            box-shadow: 0 10px 18px rgba(11, 79, 156, .22);
        }
        .surfside-staff-grid .surfside-staff-button-secondary:hover,
        .surfside-staff-grid .surfside-staff-button-secondary:focus {
            background: #083f7d;
            color: #fff !important;
            transform: translateY(-1px);
        }
    </style>
    <script>
    (function () {
        function hideEmptyLocalMenu(input) {
            if (!input) return;
            const box = input.closest('.surfside-calendar-location-required');
            if (!box) return;
            const menu = box.querySelector('.surfside-known-location-menu');
            if (!menu) return;

            const hasSavedResult = !!menu.querySelector('.surfside-known-location-option');
            if (!hasSavedResult) {
                menu.hidden = true;
                input.setAttribute('aria-expanded', 'false');
            }
        }

        function repairVenueField(input) {
            if (!input || input.dataset.surfsideFinalPlacesFix === '1') return;
            input.dataset.surfsideFinalPlacesFix = '1';

            input.addEventListener('input', function () {
                window.setTimeout(function () {
                    hideEmptyLocalMenu(input);
                }, 0);
            });
            input.addEventListener('focus', function () {
                window.setTimeout(function () {
                    hideEmptyLocalMenu(input);
                }, 0);
            });
        }

        function repairPage() {
            document.querySelectorAll('.surfside-calendar-required-venue').forEach(repairVenueField);
            document.querySelectorAll('.surfside-known-location-menu').forEach(function (menu) {
                if (!menu.querySelector('.surfside-known-location-option')) {
                    menu.hidden = true;
                }
            });

            document.querySelectorAll('.surfside-staff-grid a.surfside-staff-button-secondary').forEach(function (button) {
                button.classList.remove('surfside-staff-button-secondary');
                button.classList.add('surfside-staff-button');
            });
        }

        document.addEventListener('DOMContentLoaded', repairPage);
        repairPage();
        new MutationObserver(repairPage).observe(document.documentElement, { childList: true, subtree: true });
    })();
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_final_productivity_fix_assets', 120);
