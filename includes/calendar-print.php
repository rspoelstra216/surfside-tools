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
        . '<span aria-hidden="true">🖨️</span> Print Calendar'
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
            margin: 0 0 14px;
        }

        .surfside-print-calendar-button {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: 1px solid rgba(11, 79, 156, .25);
            border-radius: 999px;
            padding: 9px 14px;
            background: #fff;
            color: #0b4f9c;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(7, 27, 58, .04);
        }

        .surfside-print-calendar-button:hover,
        .surfside-print-calendar-button:focus-visible {
            background: #eef6ff;
            border-color: #0b4f9c;
        }

        .surfside-print-calendar-button:focus-visible {
            outline: 3px solid rgba(11, 79, 156, .24);
            outline-offset: 2px;
        }

        .surfside-print-calendar-only {
            display: none;
        }

        @media print {
            @page {
                size: landscape;
                margin: .35in;
            }

            html,
            body {
                background: #fff !important;
            }

            body * {
                visibility: hidden !important;
            }

            .surfside-print-calendar-only,
            .surfside-print-calendar-only * {
                visibility: visible !important;
            }

            .surfside-print-calendar-only {
                display: block !important;
                position: absolute;
                inset: 0 auto auto 0;
                width: 100%;
                margin: 0;
                color: #000;
                background: #fff;
                font-family: Arial, Helvetica, sans-serif;
            }

            .surfside-print-calendar-header {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                margin: 0 0 10pt;
                padding-bottom: 6pt;
                border-bottom: 2px solid #000;
            }

            .surfside-print-calendar-header h1 {
                margin: 0;
                color: #000;
                font-size: 22pt;
                line-height: 1;
            }

            .surfside-print-calendar-header p {
                margin: 0;
                color: #000;
                font-size: 9pt;
                font-weight: 700;
            }

            .surfside-print-calendar-grid {
                display: grid;
                grid-template-columns: repeat(7, minmax(0, 1fr));
                width: 100%;
                border-top: 1px solid #000;
                border-left: 1px solid #000;
                break-inside: avoid-page;
                page-break-inside: avoid;
            }

            .surfside-print-calendar-weekday {
                padding: 4pt 3pt;
                border-right: 1px solid #000;
                border-bottom: 1px solid #000;
                text-align: center;
                text-transform: uppercase;
                font-size: 7.5pt;
                font-weight: 800;
            }

            .surfside-print-calendar-day {
                min-height: .92in;
                padding: 4pt;
                border-right: 1px solid #000;
                border-bottom: 1px solid #000;
                overflow: hidden;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .surfside-print-calendar-day.is-outside-month {
                color: #777;
                background: #f3f3f3 !important;
            }

            .surfside-print-calendar-date {
                display: block;
                margin-bottom: 3pt;
                font-size: 8.5pt;
            }

            .surfside-print-calendar-event {
                margin: 0 0 3pt;
                padding-top: 2pt;
                border-top: .5pt solid #888;
                font-size: 6.8pt;
                line-height: 1.15;
            }

            .surfside-print-calendar-event strong,
            .surfside-print-calendar-event span {
                display: block;
                color: inherit;
            }

            .surfside-print-calendar-event strong {
                font-size: 7.2pt;
            }

            .surfside-print-calendar-controls,
            .surfside-month-calendar-nav,
            .surfside-month-calendar-grid-wrap,
            .surfside-day-modal,
            .surfside-event-modal,
            #wpadminbar {
                display: none !important;
            }
        }
    </style>
    <script id="surfside-calendar-print-script">
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-surfside-print-calendar]');
        if (!button) return;
        window.print();
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_print_assets', 100);
