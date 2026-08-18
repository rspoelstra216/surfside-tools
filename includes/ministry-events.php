<?php
/**
 * Curated upcoming events for the Ministries page.
 *
 * @package SurfsideTools
 */

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_ministry_event_occurrences($limit = 6) {
    $today = current_time('Y-m-d');
    $range_end = date('Y-m-d', strtotime($today . ' +2 years'));
    $events = array();

    foreach (surfside_tools_calendar_get_all_events() as $event) {
        if (empty($event['show_on_ministries'])) {
            continue;
        }
        if (function_exists('surfside_tools_event_is_featured_ministry') && !surfside_tools_event_is_featured_ministry($event['id'] ?? 0)) {
            continue;
        }
        $events = array_merge($events, surfside_tools_calendar_event_occurrences($event, $today, $range_end));
    }

    usort($events, function ($first, $second) {
        $first_key = ($first['date'] ?? '') . ' ' . (($first['start_time'] ?? '') ?: '00:00');
        $second_key = ($second['date'] ?? '') . ' ' . (($second['start_time'] ?? '') ?: '00:00');
        return strcmp($first_key, $second_key);
    });

    return array_slice($events, 0, max(1, absint($limit)));
}

function surfside_tools_ministry_events_url() {
    if (function_exists('surfside_tools_get_site_information')) {
        $information = surfside_tools_get_site_information();
        foreach ((array) ($information['navigation'] ?? array()) as $link) {
            if (strtolower(trim((string) ($link['label'] ?? ''))) === 'events') {
                $url = surfside_tools_site_information_navigation_url($link);
                if ($url !== '') {
                    return $url;
                }
            }
        }
    }
    return home_url('/events/');
}

function surfside_tools_ministry_events_shortcode($attributes = array()) {
    $attributes = shortcode_atts(array(
        'title' => 'Featured Ministries',
        'intro' => 'Connect with one of these featured Surfside ministries.',
        'limit' => 6,
    ), $attributes, 'surfside_featured_ministries');

    $events = surfside_tools_ministry_event_occurrences($attributes['limit']);
    if (empty($events)) {
        return '';
    }

    surfside_tools_calendar_enqueue_styles();
    $heading_id = wp_unique_id('surfside-ministry-events-heading-');
    ob_start();
    ?>
    <section class="surfside-ministry-events" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
        <div class="surfside-ministry-events__inner">
            <div class="surfside-ministry-events__intro">
                <h2 id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($attributes['title']); ?></h2>
                <?php if (trim((string) $attributes['intro']) !== '') : ?><p><?php echo esc_html($attributes['intro']); ?></p><?php endif; ?>
            </div>
            <div class="surfside-ministry-events__grid surfside-staggered-cards">
                <?php foreach ($events as $index => $event) :
                    $timestamp = strtotime(($event['date'] ?? '') . ' 12:00:00');
                    $detail_id = 'surfside-ministry-event-' . absint($event['id']) . '-' . str_replace('-', '', $event['date']) . '-' . absint($index);
                    ?>
                    <article class="surfside-ministry-events__card">
                        <div class="surfside-ministry-events__date" aria-hidden="true"><span><?php echo esc_html($timestamp ? date_i18n('D · M', $timestamp) : ''); ?></span><strong><?php echo esc_html($timestamp ? date_i18n('j', $timestamp) : ''); ?></strong></div>
                        <button type="button" class="surfside-ministry-events__body surfside-event-detail-button" aria-haspopup="dialog" aria-controls="<?php echo esc_attr($detail_id); ?>">
                            <h3><?php echo esc_html($event['title'] ?? ''); ?></h3>
                            <p><?php echo esc_html($timestamp ? date_i18n('l, F j', $timestamp) : surfside_tools_calendar_format_date($event['date'])); ?> · <?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></p>
                            <?php if (!empty($event['location'])) : ?><p class="surfside-ministry-events__location">📍 <?php echo esc_html($event['location']); ?></p><?php endif; ?>
                        </button>
                        <?php echo surfside_tools_calendar_render_event_modal($event, $detail_id); ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="surfside-ministry-events__actions"><a class="surfside-button" href="<?php echo esc_url(surfside_tools_ministry_events_url()); ?>">View All Events</a></div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_featured_ministries', 'surfside_tools_ministry_events_shortcode');
// Temporary compatibility alias for pages that still use the previous shortcode name.
add_shortcode('surfside_ministry_events', 'surfside_tools_ministry_events_shortcode');
