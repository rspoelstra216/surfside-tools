<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep the simplified crowded-day layout in normal flow: one standard event
 * card followed by the Day Details action, without changing calendar rows.
 */
function surfside_tools_calendar_simple_overflow_layout() {
    if (!wp_style_is('surfside-tools-calendar-manager', 'enqueued')) {
        return;
    }

    wp_add_inline_style('surfside-tools-calendar-manager', '
        .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-day-events {
            display: grid !important;
            grid-template-rows: auto 30px !important;
            gap: 6px !important;
            height: auto !important;
            max-height: none !important;
            padding: 0 !important;
            overflow: visible !important;
            align-content: start !important;
        }

        .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-item,
        .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-event-button {
            width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
            box-sizing: border-box !important;
        }

        .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-more {
            position: static !important;
            inset: auto !important;
            display: inline-flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            width: 100% !important;
            min-width: 0 !important;
            height: 30px !important;
            min-height: 30px !important;
            max-height: 30px !important;
            margin: 0 !important;
            overflow: visible !important;
            z-index: auto !important;
        }

        @media (max-width: 900px) {
            .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-day-events {
                grid-template-rows: auto auto !important;
            }

            .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-more {
                height: auto !important;
                min-height: 44px !important;
                max-height: none !important;
            }
        }
    ');
}
add_action('wp_enqueue_scripts', 'surfside_tools_calendar_simple_overflow_layout', 200);
