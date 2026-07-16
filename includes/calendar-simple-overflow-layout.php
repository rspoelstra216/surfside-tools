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
            background: #f7fbff !important;
        }

        .surfside-month-calendar-more-item .surfside-month-calendar-more {
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
            color: inherit !important;
            text-align: left !important;
            font: inherit !important;
            cursor: pointer !important;
            box-shadow: none !important;
            overflow: visible !important;
        }

        .surfside-month-calendar-more-item .surfside-month-calendar-event-title {
            display: block !important;
            margin: 0 0 3px !important;
            color: #071b3a !important;
            font-size: 15px !important;
            font-weight: 900 !important;
            line-height: 1.18 !important;
        }

        .surfside-month-calendar-more-prompt {
            display: block !important;
            margin: 0 !important;
            color: #34425e !important;
            font-size: 12px !important;
            line-height: 1.35 !important;
        }

        .surfside-month-calendar-more-item:hover,
        .surfside-month-calendar-more-item:focus-within {
            background: #eef6ff !important;
        }

        .surfside-month-calendar-more-item .surfside-month-calendar-more:focus-visible {
            outline: 3px solid rgba(11,79,156,.24) !important;
            outline-offset: 3px !important;
            border-radius: 4px !important;
        }

        @media (max-width: 900px) {
            .surfside-month-calendar-more-item {
                padding: 10px 12px !important;
            }

            .surfside-month-calendar-more-item .surfside-month-calendar-event-title {
                font-size: 17px !important;
            }

            .surfside-month-calendar-more-prompt {
                font-size: 14px !important;
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'surfside_tools_calendar_simple_overflow_layout', 99);

/**
 * Convert the renderer's overflow button into a normal two-line calendar card
 * before the shortcode HTML is sent to the browser.
 */
function surfside_tools_calendar_render_overflow_card($output, $tag, $attr, $m) {
    if ($tag !== 'surfside_month_calendar' || strpos($output, 'surfside-month-calendar-more') === false) {
        return $output;
    }

    return preg_replace_callback(
        '~<button\b([^>]*\bclass="[^"]*\bsurfside-month-calendar-more\b[^"]*"[^>]*)>(.*?)</button>~s',
        function ($matches) {
            $attributes = preg_replace_callback(
                '~\bclass="([^"]*)"~',
                function ($class_match) {
                    $classes = trim($class_match[1] . ' surfside-month-calendar-event-button');
                    return 'class="' . esc_attr($classes) . '"';
                },
                $matches[1],
                1
            );

            $label = trim(wp_strip_all_tags($matches[2]));
            $count = 0;
            if (preg_match('/(\d+)/', $label, $count_match)) {
                $count = absint($count_match[1]);
            }

            $title = $count === 1 ? '1 more event' : $count . ' more events';

            return '<article class="surfside-month-calendar-item surfside-month-calendar-more-item">'
                . '<button' . $attributes . '>'
                . '<span class="surfside-month-calendar-event-title">' . esc_html($title) . '</span>'
                . '<span class="surfside-month-calendar-more-prompt">Tap to view →</span>'
                . '</button>'
                . '</article>';
        },
        $output
    );
}
add_filter('do_shortcode_tag', 'surfside_tools_calendar_render_overflow_card', 20, 4);
