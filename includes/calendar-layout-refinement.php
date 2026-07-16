<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Refine the public monthly calendar width and keep event text inside its cell.
 */
function surfside_tools_calendar_layout_refinement() {
    if (!wp_style_is('surfside-tools-calendar-manager', 'enqueued')) {
        return;
    }

    wp_add_inline_style('surfside-tools-calendar-manager', '
        /* Keep the calendar generous without stretching it across the full screen. */
        .entry-content .surfside-month-calendar,
        .wp-site-blocks .surfside-month-calendar,
        body .surfside-month-calendar {
            width: min(1180px, calc(100vw - 32px)) !important;
            max-width: 1180px !important;
            position: relative !important;
            left: calc(50vw - 50%) !important;
            right: auto !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            transform: translateX(-50%) !important;
            box-sizing: border-box !important;
        }

        .surfside-month-calendar-grid-wrap,
        .surfside-month-calendar-grid,
        .surfside-month-calendar-days,
        .surfside-month-calendar-day,
        .surfside-month-calendar-day-events,
        .surfside-month-calendar-item,
        .surfside-month-calendar-event-button,
        .surfside-month-calendar-more-wrap,
        .surfside-month-calendar-more {
            min-width: 0 !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }

        .surfside-month-calendar-event-title,
        .surfside-month-calendar-event-button span,
        .surfside-month-calendar-more {
            white-space: normal !important;
            overflow-wrap: anywhere !important;
            word-break: normal !important;
            text-overflow: clip !important;
        }

        .surfside-month-calendar-event-title {
            line-height: 1.18 !important;
        }

        .surfside-month-calendar-event-button,
        .surfside-month-calendar-more {
            overflow: visible !important;
        }

        @media (max-width: 900px) {
            .entry-content .surfside-month-calendar,
            .wp-site-blocks .surfside-month-calendar,
            body .surfside-month-calendar {
                width: calc(100vw - 24px) !important;
                max-width: none !important;
            }
        }
    ');
}
add_action('wp_enqueue_scripts', 'surfside_tools_calendar_layout_refinement', 130);
