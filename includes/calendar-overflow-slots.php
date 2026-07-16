<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fit two event links and the Day Details action inside the original
 * fixed-height monthly-calendar cell without changing calendar dimensions.
 */
function surfside_tools_calendar_overflow_slots() {
    if (!wp_style_is('surfside-tools-calendar-manager', 'enqueued')) {
        return;
    }

    wp_add_inline_style('surfside-tools-calendar-manager', '
        @media (min-width: 901px) {
            body .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-day-events {
                display: grid !important;
                grid-template-rows: 24px 24px 20px !important;
                grid-auto-rows: 0 !important;
                gap: 2px !important;
                height: 72px !important;
                min-height: 72px !important;
                max-height: 72px !important;
                padding: 0 !important;
                overflow: visible !important;
                align-content: start !important;
            }

            body .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-item,
            body .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-event-button {
                width: 100% !important;
                min-width: 0 !important;
                height: 24px !important;
                min-height: 24px !important;
                max-height: 24px !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            body .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-event-button {
                display: block !important;
                padding: 3px 6px !important;
                text-align: left !important;
            }

            body .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-event-title {
                display: block !important;
                width: 100% !important;
                overflow: hidden !important;
                white-space: nowrap !important;
                text-overflow: ellipsis !important;
                font-size: 10px !important;
                line-height: 18px !important;
            }

            body .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-event-button span:not(.surfside-month-calendar-event-title) {
                display: none !important;
            }

            body .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-more {
                grid-row: 3 !important;
                position: static !important;
                inset: auto !important;
                display: flex !important;
                visibility: visible !important;
                opacity: 1 !important;
                align-items: center !important;
                justify-content: center !important;
                width: 100% !important;
                min-width: 0 !important;
                height: 20px !important;
                min-height: 20px !important;
                max-height: 20px !important;
                margin: 0 !important;
                padding: 1px 4px !important;
                font-size: 9px !important;
                line-height: 16px !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                box-sizing: border-box !important;
                z-index: 1 !important;
            }
        }
    ');
}
add_action('wp_enqueue_scripts', 'surfside_tools_calendar_overflow_slots', 140);
