<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_calendar_render_print_month($events, $month_start) {
    $events_by_date = surfside_tools_calendar_events_by_date($events);
    $first_ts = strtotime($month_start . ' 12:00:00');
    $month_number = date('m', $first_ts);
    $grid_start = date('Y-m-d', strtotime('last sunday', strtotime($month_start . ' +1 day')));
    $grid_end = date('Y-m-d', strtotime('next saturday', strtotime(date('Y-m-t', $first_ts) . ' -1 day')));
    $weekdays = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');

    ob_start();
    ?>
    <section class="surfside-print-calendar-only" aria-hidden="true">
        <header class="surfside-print-calendar-header">
            <h1><?php echo esc_html(date_i18n('F Y', $first_ts)); ?></h1>
            <p>Surfside Community Fellowship</p>
        </header>

        <div class="surfside-print-calendar-grid" role="presentation">
            <?php foreach ($weekdays as $weekday) : ?>
                <div class="surfside-print-calendar-weekday"><?php echo esc_html($weekday); ?></div>
            <?php endforeach; ?>

            <?php for ($day_ts = strtotime($grid_start . ' 12:00:00'); $day_ts <= strtotime($grid_end . ' 12:00:00'); $day_ts = strtotime('+1 day', $day_ts)) :
                $date = date('Y-m-d', $day_ts);
                $day_events = isset($events_by_date[$date]) ? array_values($events_by_date[$date]) : array();
                $is_current_month = date('m', $day_ts) === $month_number;
                ?>
                <div class="surfside-print-calendar-day<?php echo $is_current_month ? '' : ' is-outside-month'; ?>">
                    <strong class="surfside-print-calendar-date"><?php echo esc_html(date_i18n('j', $day_ts)); ?></strong>
                    <?php foreach ($day_events as $event) : ?>
                        <div class="surfside-print-calendar-event">
                            <strong><?php echo esc_html($event['title']); ?></strong>
                            <span><?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></span>
                            <?php if (!empty($event['location_name']) || !empty($event['location'])) : ?>
                                <span><?php echo esc_html($event['location_name'] ?: $event['location']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

function surfside_tools_calendar_add_print_view($output, $tag, $attr, $m) {
    if ($tag !== 'surfside_month_calendar' || strpos($output, 'surfside-month-calendar') === false) {
        return $output;
    }

    $requested_month = isset($_GET['surfside_month']) ? sanitize_text_field(wp_unslash($_GET['surfside_month'])) : '';
    $shortcode_month = isset($attr['month']) ? sanitize_text_field((string) $attr['month']) : '';
    $month = $requested_month ?: $shortcode_month;
    $base = $month && preg_match('/^\d{4}-\d{2}$/', $month) ? $month . '-01' : current_time('Y-m-01');
    $start = date('Y-m-01', strtotime($base));
    $end = date('Y-m-t', strtotime($base));
    $events = surfside_tools_calendar_get_occurrences($start, $end);

    $button = '<div class="surfside-print-calendar-controls">'
        . '<button type="button" class="surfside-print-calendar-button" data-surfside-print-calendar>'
        . '<span aria-hidden="true">🖨️</span><span>Print</span>'
        . '</button>'
        . '</div>';

    $output = preg_replace(
        '~(<div class="surfside-month-calendar"[^>]*>)~',
        '$1' . $button,
        $output,
        1
    );

    $print_view = surfside_tools_calendar_render_print_month($events, $start);
    $closing_position = strrpos($output, '</div>');
    if ($closing_position !== false) {
        $output = substr_replace($output, $print_view . '</div>', $closing_position, 6);
    } else {
        $output .= $print_view;
    }

    return $output;
}
add_filter('do_shortcode_tag', 'surfside_tools_calendar_add_print_view', 30, 4);

function surfside_tools_calendar_print_assets() {
    ?>
    <style id="surfside-calendar-print-styles">
        .surfside-print-calendar-controls {
            display: flex;
            justify-content: flex-end;
            margin: -2px 0 8px;
        }

        .surfside-print-calendar-button {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 0;
            border-radius: 7px;
            padding: 5px 8px;
            background: transparent;
            color: #0b4f9c;
            font: inherit;
            font-size: .82rem;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            opacity: .78;
        }

        .surfside-print-calendar-button:hover,
        .surfside-print-calendar-button:focus-visible {
            background: #eef6ff;
            opacity: 1;
        }

        .surfside-print-calendar-button:focus-visible {
            outline: 2px solid rgba(11, 79, 156, .28);
            outline-offset: 2px;
        }

        .surfside-print-calendar-only {
            display: none;
        }
    </style>
    <script id="surfside-calendar-print-script">
    (function () {
        'use strict';

        var printStyles = `
            @page { size: landscape; margin: .28in; }
            * { box-sizing: border-box; }
            html, body { margin: 0; padding: 0; background: #fff; color: #000; font-family: Arial, Helvetica, sans-serif; }
            .surfside-print-calendar-only { display: block; width: 100%; }
            .surfside-print-calendar-header { display: flex; align-items: flex-end; justify-content: space-between; margin: 0 0 7pt; padding-bottom: 5pt; border-bottom: 1.5pt solid #000; }
            .surfside-print-calendar-header h1 { margin: 0; font-size: 19pt; line-height: 1; }
            .surfside-print-calendar-header p { margin: 0; font-size: 8pt; font-weight: 700; }
            .surfside-print-calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); width: 100%; border-top: 1px solid #000; border-left: 1px solid #000; break-inside: avoid-page; page-break-inside: avoid; }
            .surfside-print-calendar-weekday { padding: 3pt 2pt; border-right: 1px solid #000; border-bottom: 1px solid #000; text-align: center; text-transform: uppercase; font-size: 7pt; font-weight: 800; }
            .surfside-print-calendar-day { min-height: .82in; padding: 3pt; border-right: 1px solid #000; border-bottom: 1px solid #000; overflow: hidden; break-inside: avoid; page-break-inside: avoid; }
            .surfside-print-calendar-day.is-outside-month { color: #666; background: #f3f3f3; }
            .surfside-print-calendar-date { display: block; margin-bottom: 2pt; font-size: 8pt; }
            .surfside-print-calendar-event { margin: 0 0 2pt; padding-top: 1.5pt; border-top: .5pt solid #888; font-size: 6.2pt; line-height: 1.08; }
            .surfside-print-calendar-event strong, .surfside-print-calendar-event span { display: block; color: inherit; }
            .surfside-print-calendar-event strong { font-size: 6.7pt; }
        `;

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-surfside-print-calendar]');
            if (!button) return;

            var calendar = button.closest('.surfside-month-calendar');
            var printView = calendar ? calendar.querySelector('.surfside-print-calendar-only') : null;
            if (!printView) return;

            var printWindow = window.open('', 'surfsideCalendarPrint', 'width=1200,height=800');
            if (!printWindow) {
                window.print();
                return;
            }

            printWindow.document.open();
            printWindow.document.write(
                '<!doctype html><html><head><meta charset="utf-8">' +
                '<meta name="viewport" content="width=device-width,initial-scale=1">' +
                '<title>Surfside Monthly Calendar</title>' +
                '<style>' + printStyles + '</style></head><body>' +
                printView.outerHTML.replace(' aria-hidden="true"', '') +
                '</body></html>'
            );
            printWindow.document.close();
            printWindow.focus();

            window.setTimeout(function () {
                printWindow.print();
            }, 250);
        });
    })();
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_print_assets', 100);
