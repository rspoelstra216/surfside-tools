<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep crowded days consistent with the normal monthly-calendar layout:
 * one standard event card followed by a second card that opens Day Details.
 */
function surfside_tools_calendar_simple_overflow_layout() {
    ?>
    <style id="surfside-calendar-simple-overflow-layout">
        .surfside-month-calendar-day.surfside-month-calendar-has-overflow .surfside-month-calendar-day-events {
            display: grid !important;
            gap: 7px !important;
            height: auto !important;
            min-height: 0 !important;
            max-height: none !important;
            padding: 0 !important;
            overflow: visible !important;
            align-content: start !important;
        }

        .surfside-month-calendar-more-item {
            border-left-color: #0b4f9c !important;
            background: #ffffff !important;
        }

        .surfside-month-calendar-more-item .surfside-month-calendar-more {
            position: static !important;
            inset: auto !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            width: 100% !important;
            min-width: 0 !important;
            min-height: 0 !important;
            height: auto !important;
            max-height: none !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            color: #0b4f9c !important;
            text-align: left !important;
            font: inherit !important;
            cursor: pointer !important;
            box-shadow: none !important;
            overflow: visible !important;
        }

        .surfside-month-calendar-more-item .surfside-month-calendar-event-title {
            margin-bottom: 0 !important;
            color: #0b4f9c !important;
        }

        .surfside-month-calendar-more-item:hover,
        .surfside-month-calendar-more-item:focus-within {
            background: #eef6ff !important;
        }

        @media (max-width: 900px) {
            .surfside-month-calendar-more-item {
                padding: 10px 12px !important;
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'surfside_tools_calendar_simple_overflow_layout', 99);

/**
 * The original calendar renderer outputs the Day Details trigger as a bare
 * button. Wrap it in the same article structure as event cards so it follows
 * the calendar's proven two-card layout instead of relying on special sizing.
 */
function surfside_tools_calendar_wrap_overflow_action() {
    ?>
    <script id="surfside-calendar-wrap-overflow-action">
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.surfside-month-calendar-more').forEach(function (button) {
            if (button.closest('.surfside-month-calendar-more-item')) {
                return;
            }

            var card = document.createElement('article');
            card.className = 'surfside-month-calendar-item surfside-month-calendar-more-item';

            var label = document.createElement('span');
            label.className = 'surfside-month-calendar-event-title';
            label.textContent = button.textContent.trim();

            button.textContent = '';
            button.classList.add('surfside-month-calendar-event-button');
            button.appendChild(label);

            button.parentNode.insertBefore(card, button);
            card.appendChild(button);
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_wrap_overflow_action', 99);
