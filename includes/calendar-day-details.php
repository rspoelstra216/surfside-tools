<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Milestone 7: interactive overflow details for the public monthly calendar.
 */
function surfside_tools_calendar_day_details_assets() {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    wp_add_inline_style('surfside-tools-calendar-manager', '
        .surfside-month-calendar-more{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:30px;border:1px solid rgba(11,79,156,.25);border-radius:9px;padding:6px 8px;background:#fff;color:#0b4f9c;font:inherit;font-size:11px;font-weight:900;line-height:1.15;cursor:pointer;box-sizing:border-box}
        .surfside-month-calendar-more:hover,.surfside-month-calendar-more:focus-visible{background:#eef6ff;border-color:#0b4f9c}.surfside-month-calendar-more:focus-visible{outline:3px solid rgba(11,79,156,.24);outline-offset:2px}
        .surfside-day-modal[hidden]{display:none!important}.surfside-day-modal{position:fixed;inset:0;z-index:999998;display:flex;align-items:center;justify-content:center;padding:24px;opacity:0;visibility:hidden;transition:opacity 180ms ease,visibility 180ms ease}.surfside-day-modal.is-open{opacity:1;visibility:visible}
        .surfside-day-modal-backdrop{position:absolute;inset:0;background:rgba(7,27,58,.62);backdrop-filter:blur(3px)}.surfside-day-modal-card{position:relative;z-index:1;width:min(680px,100%);max-height:min(760px,calc(100vh - 48px));overflow:auto;border-radius:20px;padding:30px;background:#fff;box-shadow:0 24px 80px rgba(7,27,58,.28);color:#34425e;transform:translateY(10px) scale(.985);transition:transform 180ms ease}.surfside-day-modal.is-open .surfside-day-modal-card{transform:none}
        .surfside-day-modal-card h2{margin:0 44px 6px 0;color:#071b3a;font-size:clamp(1.7rem,4vw,2.35rem);line-height:1.12}.surfside-day-modal-summary{margin:0 0 20px;color:#5b667a}.surfside-day-modal-list{display:grid;gap:12px}
        .surfside-day-modal-event{width:100%;border:1px solid rgba(7,27,58,.12);border-left:4px solid #0b4f9c;border-radius:13px;padding:14px 16px;background:#f8fbff;text-align:left;color:inherit;font:inherit;cursor:pointer}.surfside-day-modal-event:hover,.surfside-day-modal-event:focus-visible{border-color:#0b4f9c;background:#eef6ff}.surfside-day-modal-event:focus-visible{outline:3px solid rgba(11,79,156,.24);outline-offset:2px}.surfside-day-modal-event strong{display:block;margin-bottom:5px;color:#071b3a;font-size:1.05rem}.surfside-day-modal-event span{display:block;color:#46536a;font-size:.92rem;line-height:1.4}
        .surfside-day-modal-close{position:absolute;top:14px;right:16px;width:38px;height:38px;border:0;border-radius:999px;background:#eef6ff;color:#0b4f9c;font-size:28px;line-height:1;cursor:pointer}
        @media(max-width:900px){.surfside-month-calendar-more{min-height:44px;font-size:14px}.surfside-day-modal{align-items:flex-end;padding:12px}.surfside-day-modal-card{width:100%;max-height:85vh;border-radius:20px 20px 0 0;padding:24px 20px}}
        @media(prefers-reduced-motion:reduce){.surfside-day-modal,.surfside-day-modal-card{transition:none}}
    ');

    wp_register_script('surfside-tools-calendar-day-details', '', array('surfside-tools-calendar-recurrence'), defined('SURFSIDE_TOOLS_VERSION') ? SURFSIDE_TOOLS_VERSION : '2.1.0', true);
    wp_enqueue_script('surfside-tools-calendar-day-details');
    wp_add_inline_script('surfside-tools-calendar-day-details', <<<'JS'
(function(){
'use strict';
var activeDayModal=null,dayTrigger=null,returnDayModal=null,returnDayButton=null,closeTimer=null;
function focusable(container){return Array.prototype.slice.call(container.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'));}
function openDayModal(modal,trigger){if(!modal)return;window.clearTimeout(closeTimer);activeDayModal=modal;dayTrigger=trigger||null;modal.removeAttribute('hidden');document.body.classList.add('surfside-modal-open');window.requestAnimationFrame(function(){modal.classList.add('is-open');var close=modal.querySelector('.surfside-day-modal-close');if(close)close.focus();});}
function hideDayModal(modal,restoreFocus){if(!modal)return;modal.classList.remove('is-open');closeTimer=window.setTimeout(function(){modal.setAttribute('hidden','');if(!document.querySelector('.surfside-event-modal:not([hidden])'))document.body.classList.remove('surfside-modal-open');},window.matchMedia('(prefers-reduced-motion: reduce)').matches?0:180);if(restoreFocus&&dayTrigger)dayTrigger.focus();if(activeDayModal===modal)activeDayModal=null;}
function openEventFromDay(button){var dayModal=button.closest('.surfside-day-modal');var targetId=button.getAttribute('aria-controls');var eventModal=targetId?document.getElementById(targetId):null;if(!dayModal||!eventModal)return;returnDayModal=dayModal;returnDayButton=button;hideDayModal(dayModal,false);window.clearTimeout(closeTimer);dayModal.setAttribute('hidden','');eventModal.removeAttribute('hidden');document.body.classList.add('surfside-modal-open');var close=eventModal.querySelector('.surfside-event-modal-close');if(close)close.focus();}
function returnToDay(eventModal){if(!returnDayModal)return false;eventModal.setAttribute('hidden','');var modal=returnDayModal;var button=returnDayButton;returnDayModal=null;returnDayButton=null;openDayModal(modal,dayTrigger);window.setTimeout(function(){if(button)button.focus();},0);return true;}
document.addEventListener('click',function(event){var trigger=event.target.closest('[data-surfside-day-open]');if(trigger){openDayModal(document.getElementById(trigger.getAttribute('aria-controls')),trigger);return;}var dayEvent=event.target.closest('[data-surfside-day-event]');if(dayEvent){event.preventDefault();openEventFromDay(dayEvent);return;}var dayClose=event.target.closest('[data-surfside-day-close]');if(dayClose)hideDayModal(dayClose.closest('.surfside-day-modal'),true);});
document.addEventListener('click',function(event){if(!returnDayModal)return;var eventClose=event.target.closest('[data-surfside-modal-close]');if(!eventClose)return;var eventModal=eventClose.closest('.surfside-event-modal');if(!eventModal)return;event.preventDefault();event.stopImmediatePropagation();returnToDay(eventModal);},true);
document.addEventListener('keydown',function(event){if(event.key==='Escape'&&returnDayModal){var eventModal=document.querySelector('.surfside-event-modal:not([hidden])');if(eventModal){event.preventDefault();event.stopImmediatePropagation();returnToDay(eventModal);return;}}if(event.key==='Escape'&&activeDayModal){event.preventDefault();hideDayModal(activeDayModal,true);return;}if(event.key==='Tab'&&activeDayModal){var items=focusable(activeDayModal);if(!items.length)return;var first=items[0],last=items[items.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}}},true);
})();
JS
    );
}

function surfside_tools_calendar_render_month_grid_interactive($events, $month_start, $show_description = false) {
    $events_by_date = surfside_tools_calendar_events_by_date($events);
    $first_ts = strtotime($month_start . ' 12:00:00');
    $month_label = date_i18n('F Y', $first_ts);
    $month_number = date('m', $first_ts);
    $grid_start = date('Y-m-d', strtotime('last sunday', strtotime($month_start . ' +1 day')));
    $grid_end = date('Y-m-d', strtotime('next saturday', strtotime(date('Y-m-t', $first_ts) . ' -1 day')));
    $weekdays = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
    $event_modals = array();

    ob_start();
    ?>
    <div class="surfside-month-calendar-grid-wrap">
        <div class="surfside-month-calendar-grid" role="table" aria-label="<?php echo esc_attr($month_label); ?> calendar">
            <div class="surfside-month-calendar-weekdays" role="row">
                <?php foreach ($weekdays as $weekday) : ?><div role="columnheader"><?php echo esc_html($weekday); ?></div><?php endforeach; ?>
            </div>
            <div class="surfside-month-calendar-days">
                <?php for ($day_ts = strtotime($grid_start . ' 12:00:00'); $day_ts <= strtotime($grid_end . ' 12:00:00'); $day_ts = strtotime('+1 day', $day_ts)) :
                    $date = date('Y-m-d', $day_ts);
                    $is_current_month = date('m', $day_ts) === $month_number;
                    $day_events = isset($events_by_date[$date]) ? array_values($events_by_date[$date]) : array();
                    $has_overflow = count($day_events) >= 3;
                    $visible_events = array_slice($day_events, 0, $has_overflow ? 1 : 2);
                    $overflow_count = $has_overflow ? count($day_events) - 1 : 0;
                    $day_modal_id = 'surfside-day-detail-' . str_replace('-', '', $date);
                    ?>
                    <div class="surfside-month-calendar-day<?php echo $is_current_month ? '' : ' surfside-month-calendar-muted'; ?><?php echo $day_events ? ' surfside-month-calendar-has-events' : ''; ?><?php echo $has_overflow ? ' surfside-month-calendar-has-overflow' : ''; ?>" role="cell">
                        <div class="surfside-month-calendar-date-number"><span><?php echo esc_html(date_i18n('D', $day_ts)); ?></span><strong><?php echo esc_html(date_i18n('j', $day_ts)); ?></strong></div>
                        <?php if ($day_events) : ?>
                            <div class="surfside-month-calendar-day-events">
                                <?php foreach ($visible_events as $event) :
                                    $detail_id = 'surfside-event-detail-' . absint($event['id']) . '-' . str_replace('-', '', $event['date']);
                                    $event_modals[$detail_id] = $event;
                                    ?>
                                    <article class="surfside-month-calendar-item<?php echo !empty($event['featured']) ? ' surfside-month-calendar-item-featured' : ''; ?>">
                                        <button type="button" class="surfside-month-calendar-event-button surfside-event-detail-button" aria-haspopup="dialog" aria-controls="<?php echo esc_attr($detail_id); ?>">
                                            <span class="surfside-month-calendar-event-title"><?php echo esc_html($event['title']); ?></span>
                                            <span><?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></span>
                                            <?php if (!empty($event['location'])) : ?><span class="surfside-month-calendar-location">📍 <?php echo esc_html($event['location']); ?></span><?php endif; ?>
                                        </button>
                                    </article>
                                <?php endforeach; ?>
                                <?php if ($overflow_count > 0) : ?>
                                    <button type="button" class="surfside-month-calendar-more" data-surfside-day-open aria-haspopup="dialog" aria-controls="<?php echo esc_attr($day_modal_id); ?>">View <?php echo esc_html($overflow_count); ?> more →</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($overflow_count > 0) : ?>
                        <div id="<?php echo esc_attr($day_modal_id); ?>" class="surfside-day-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($day_modal_id); ?>-title" hidden>
                            <div class="surfside-day-modal-backdrop" data-surfside-day-close></div>
                            <div class="surfside-day-modal-card" role="document">
                                <button type="button" class="surfside-day-modal-close" data-surfside-day-close aria-label="Close day details">×</button>
                                <h2 id="<?php echo esc_attr($day_modal_id); ?>-title"><?php echo esc_html(date_i18n('l, F j', $day_ts)); ?></h2>
                                <p class="surfside-day-modal-summary"><?php echo esc_html(count($day_events)); ?> event<?php echo count($day_events) === 1 ? '' : 's'; ?> scheduled</p>
                                <div class="surfside-day-modal-list">
                                    <?php foreach ($day_events as $event) :
                                        $detail_id = 'surfside-event-detail-' . absint($event['id']) . '-' . str_replace('-', '', $event['date']);
                                        $event_modals[$detail_id] = $event;
                                        ?>
                                        <button type="button" class="surfside-day-modal-event" data-surfside-day-event aria-haspopup="dialog" aria-controls="<?php echo esc_attr($detail_id); ?>">
                                            <strong><?php echo esc_html($event['title']); ?></strong>
                                            <span><?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></span>
                                            <?php if (!empty($event['location_name']) || !empty($event['location'])) : ?><span>📍 <?php echo esc_html($event['location_name'] ?: $event['location']); ?></span><?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <?php foreach ($event_modals as $detail_id => $event) : echo surfside_tools_calendar_render_event_modal($event, $detail_id); endforeach; ?>
    <?php
    return ob_get_clean();
}

function surfside_tools_calendar_month_shortcode_interactive($atts = array()) {
    surfside_tools_calendar_enqueue_styles();
    surfside_tools_calendar_day_details_assets();
    $atts = shortcode_atts(array('month'=>'','show_description'=>'no','empty_message'=>'No events are scheduled for this month.'), $atts, 'surfside_month_calendar');
    $requested_month = isset($_GET['surfside_month']) ? sanitize_text_field(wp_unslash($_GET['surfside_month'])) : '';
    $shortcode_month = sanitize_text_field((string) $atts['month']);
    $month = $requested_month ?: $shortcode_month;
    $base = $month && preg_match('/^\d{4}-\d{2}$/', $month) ? $month . '-01' : current_time('Y-m-01');
    $start = date('Y-m-01', strtotime($base));
    $end = date('Y-m-t', strtotime($base));
    $events = surfside_tools_calendar_get_occurrences($start, $end);
    $prev_month = date('Y-m', strtotime($start . ' -1 month'));
    $next_month = date('Y-m', strtotime($start . ' +1 month'));
    ob_start();
    ?>
    <div class="surfside-month-calendar" data-month="<?php echo esc_attr(date('Y-m', strtotime($start))); ?>">
        <div class="surfside-month-calendar-nav" aria-label="Calendar navigation">
            <a class="surfside-month-calendar-nav-button" href="<?php echo esc_url(add_query_arg('surfside_month', $prev_month)); ?>">‹ <?php echo esc_html(date_i18n('F', strtotime($prev_month . '-01'))); ?></a>
            <div class="surfside-month-calendar-nav-title"><h2 class="surfside-month-calendar-title"><?php echo esc_html(date_i18n('F Y', strtotime($start))); ?></h2><a class="surfside-month-calendar-today" href="<?php echo esc_url(remove_query_arg('surfside_month')); ?>">Today</a></div>
            <a class="surfside-month-calendar-nav-button" href="<?php echo esc_url(add_query_arg('surfside_month', $next_month)); ?>"><?php echo esc_html(date_i18n('F', strtotime($next_month . '-01'))); ?> ›</a>
        </div>
        <?php if (!$events) : ?><div class="surfside-public-calendar-empty"><strong>No events this month</strong><p><?php echo esc_html($atts['empty_message']); ?></p></div><?php else : echo surfside_tools_calendar_render_month_grid_interactive($events, $start, strtolower((string)$atts['show_description']) !== 'no'); endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

add_action('init', function () {
    remove_shortcode('surfside_month_calendar');
    add_shortcode('surfside_month_calendar', 'surfside_tools_calendar_month_shortcode_interactive');
}, 60);
