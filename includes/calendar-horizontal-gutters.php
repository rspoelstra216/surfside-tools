<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep the public monthly calendar centered against the browser viewport,
 * rather than against the theme's narrower content column.
 */
function surfside_tools_calendar_horizontal_gutters() {
    if (!wp_style_is('surfside-tools-calendar-manager', 'enqueued')) {
        return;
    }

    wp_add_inline_style('surfside-tools-calendar-manager', '
        .entry-content .surfside-month-calendar,
        .wp-site-blocks .surfside-month-calendar,
        body .surfside-month-calendar {
            box-sizing: border-box !important;
            width: calc(100vw - 32px) !important;
            max-width: none !important;
            margin-left: calc(50% - 50vw + 16px) !important;
            margin-right: 0 !important;
            transform: none !important;
            left: auto !important;
            right: auto !important;
        }

        .surfside-month-calendar-grid-wrap,
        .surfside-month-calendar-grid {
            box-sizing: border-box !important;
            width: 100% !important;
            max-width: none !important;
        }

        @media (max-width: 600px) {
            .entry-content .surfside-month-calendar,
            .wp-site-blocks .surfside-month-calendar,
            body .surfside-month-calendar {
                width: calc(100vw - 24px) !important;
                margin-left: calc(50% - 50vw + 12px) !important;
            }
        }
    ');
}
add_action('wp_enqueue_scripts', 'surfside_tools_calendar_horizontal_gutters', 120);
