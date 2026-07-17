<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Milestone 7: a reusable public summary of what is happening at Surfside today.
 */
function surfside_tools_today_service_schedule() {
    $schedule = array(
        6 => array(
            'label' => 'Saturday Worship',
            'time' => '6:00 PM',
        ),
        7 => array(
            'label' => 'Sunday Worship',
            'time' => '9:45 AM',
        ),
    );

    return apply_filters('surfside_tools_today_service_schedule', $schedule);
}

function surfside_tools_today_event_image($event) {
    $event_id = absint($event['id'] ?? 0);
    if (!$event_id || !has_post_thumbnail($event_id)) {
        return '';
    }

    $image_id = get_post_thumbnail_id($event_id);
    $alt = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));
    if ($alt === '') {
        $alt = (string) ($event['title'] ?? 'Surfside event');
    }

    return wp_get_attachment_image(
        $image_id,
        'medium_large',
        false,
        array(
            'class' => 'surfside-today-event-image',
            'alt' => $alt,
            'loading' => 'lazy',
        )
    );
}

function surfside_tools_today_render_event($event, $show_date = false) {
    $image = surfside_tools_today_event_image($event);
    $location = trim((string) ($event['location_name'] ?? $event['location'] ?? ''));

    ob_start();
    ?>
    <article class="surfside-today-event<?php echo $image ? ' has-image' : ''; ?>">
        <?php if ($image) : ?>
            <div class="surfside-today-event-media"><?php echo $image; ?></div>
        <?php endif; ?>
        <div class="surfside-today-event-content">
            <h3><?php echo esc_html($event['title'] ?? 'Surfside Event'); ?></h3>
            <div class="surfside-today-event-meta">
                <?php if ($show_date && !empty($event['date'])) : ?>
                    <span><?php echo esc_html(surfside_tools_calendar_format_date($event['date'])); ?></span>
                <?php endif; ?>
                <span><?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></span>
                <?php if ($location !== '') : ?><span><?php echo esc_html($location); ?></span><?php endif; ?>
            </div>
            <?php if (!empty($event['description'])) : ?>
                <p><?php echo esc_html(wp_trim_words(wp_strip_all_tags($event['description']), 24, '…')); ?></p>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

function surfside_tools_today_assets() {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    wp_register_style('surfside-tools-today', false, array(), defined('SURFSIDE_TOOLS_VERSION') ? SURFSIDE_TOOLS_VERSION : '2.1.0');
    wp_enqueue_style('surfside-tools-today');
    wp_add_inline_style('surfside-tools-today', '
        .surfside-today{--surfside-today-blue:#0b4f9c;--surfside-today-navy:#071b3a;display:grid;gap:18px;padding:clamp(22px,4vw,36px);border:1px solid rgba(7,27,58,.12);border-radius:22px;background:linear-gradient(145deg,#fff 0%,#f3f8ff 100%);box-shadow:0 14px 40px rgba(7,27,58,.08);color:#34425e}
        .surfside-today-header{display:flex;align-items:flex-end;justify-content:space-between;gap:18px}.surfside-today-eyebrow{margin:0 0 5px;color:var(--surfside-today-blue);font-size:.82rem;font-weight:900;letter-spacing:.09em;text-transform:uppercase}.surfside-today h2{margin:0;color:var(--surfside-today-navy);font-size:clamp(1.75rem,4vw,2.5rem);line-height:1.08}.surfside-today-date{margin:0;color:#5b667a;font-weight:700;white-space:nowrap}
        .surfside-today-service{display:grid;grid-template-columns:auto 1fr;gap:16px;align-items:center;padding:18px;border-radius:16px;background:var(--surfside-today-navy);color:#fff}.surfside-today-service-time{display:grid;place-items:center;min-width:104px;min-height:74px;padding:10px;border-radius:13px;background:#fff;color:var(--surfside-today-blue);font-size:1.12rem;font-weight:900;text-align:center}.surfside-today-service h3{margin:0 0 4px;color:#fff;font-size:1.25rem}.surfside-today-service p{margin:0;color:rgba(255,255,255,.82)}.surfside-today-sermon{margin-top:8px!important;color:#fff!important;font-weight:800}
        .surfside-today-section-title{margin:0;color:var(--surfside-today-navy);font-size:1.05rem}.surfside-today-events{display:grid;gap:12px}.surfside-today-event{display:grid;grid-template-columns:minmax(0,1fr);overflow:hidden;border:1px solid rgba(7,27,58,.11);border-radius:15px;background:#fff}.surfside-today-event.has-image{grid-template-columns:minmax(150px,30%) minmax(0,1fr)}.surfside-today-event-media{min-height:150px;background:#eef2f7}.surfside-today-event-image{display:block;width:100%;height:100%;min-height:150px;object-fit:cover}.surfside-today-event-content{padding:17px}.surfside-today-event h3{margin:0 0 7px;color:var(--surfside-today-navy);font-size:1.14rem}.surfside-today-event-meta{display:flex;flex-wrap:wrap;gap:6px 12px;color:#46536a;font-size:.92rem;font-weight:700}.surfside-today-event-meta span+span:before{content:"•";margin-right:12px;color:#9aa5b5}.surfside-today-event p{margin:10px 0 0;line-height:1.5}
        .surfside-today-empty{margin:0;padding:16px;border-radius:14px;background:#fff;color:#5b667a}.surfside-today-link{justify-self:start;display:inline-flex;align-items:center;min-height:42px;padding:9px 14px;border-radius:9px;background:var(--surfside-today-blue);color:#fff!important;font-weight:800;text-decoration:none!important}.surfside-today-link:hover,.surfside-today-link:focus-visible{background:var(--surfside-today-navy)}.surfside-today-link:focus-visible{outline:3px solid rgba(11,79,156,.25);outline-offset:3px}
        @media(max-width:700px){.surfside-today-header{display:block}.surfside-today-date{margin-top:8px;white-space:normal}.surfside-today-service{grid-template-columns:1fr}.surfside-today-service-time{min-width:0;min-height:0;justify-self:start}.surfside-today-event.has-image{grid-template-columns:1fr}.surfside-today-event-media,.surfside-today-event-image{min-height:190px;max-height:240px}.surfside-today-event-meta{display:grid;gap:4px}.surfside-today-event-meta span+span:before{content:none;margin:0}}
    ');
}

function surfside_tools_today_shortcode($atts = array()) {
    if (!function_exists('surfside_tools_calendar_get_occurrences')) {
        return '';
    }

    surfside_tools_today_assets();

    $atts = shortcode_atts(array(
        'title' => 'Today at Surfside',
        'events_url' => '/events/',
        'show_link' => 'yes',
    ), $atts, 'surfside_today');

    $timezone = wp_timezone();
    $now = new DateTimeImmutable('now', $timezone);
    $today = $now->format('Y-m-d');
    $weekday = (int) $now->format('N');
    $schedule = surfside_tools_today_service_schedule();
    $service = isset($schedule[$weekday]) && is_array($schedule[$weekday]) ? $schedule[$weekday] : null;
    $today_events = surfside_tools_calendar_get_occurrences($today, $today);
    $next_events = array();

    if (!$service && empty($today_events)) {
        $tomorrow = $now->modify('+1 day')->format('Y-m-d');
        $range_end = $now->modify('+2 years')->format('Y-m-d');
        $next_events = surfside_tools_calendar_get_occurrences($tomorrow, $range_end, 1);
    }

    $message = function_exists('surfside_tools_get_message_data') ? surfside_tools_get_message_data() : array();
    $sermon_title = trim((string) ($message['title'] ?? ''));
    $events_url = trim((string) $atts['events_url']);
    if ($events_url !== '' && strpos($events_url, 'http') !== 0) {
        $events_url = home_url('/' . ltrim($events_url, '/'));
    }

    ob_start();
    ?>
    <section class="surfside-today" aria-labelledby="surfside-today-title">
        <header class="surfside-today-header">
            <div>
                <p class="surfside-today-eyebrow">What is happening</p>
                <h2 id="surfside-today-title"><?php echo esc_html($atts['title']); ?></h2>
            </div>
            <p class="surfside-today-date"><?php echo esc_html(wp_date('l, F j', $now->getTimestamp(), $timezone)); ?></p>
        </header>

        <?php if ($service) : ?>
            <div class="surfside-today-service">
                <div class="surfside-today-service-time"><?php echo esc_html($service['time'] ?? ''); ?></div>
                <div>
                    <h3><?php echo esc_html($service['label'] ?? 'Worship Service'); ?></h3>
                    <p>Join us for worship at Surfside Community Fellowship.</p>
                    <?php if ($sermon_title !== '') : ?><p class="surfside-today-sermon">Today’s message: <?php echo esc_html($sermon_title); ?></p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($today_events)) : ?>
            <h3 class="surfside-today-section-title"><?php echo $service ? 'Also happening today' : 'Happening today'; ?></h3>
            <div class="surfside-today-events">
                <?php foreach ($today_events as $event) : echo surfside_tools_today_render_event($event, false); endforeach; ?>
            </div>
        <?php elseif (!empty($next_events)) : ?>
            <h3 class="surfside-today-section-title">Coming up next</h3>
            <div class="surfside-today-events"><?php echo surfside_tools_today_render_event($next_events[0], true); ?></div>
        <?php elseif (!$service) : ?>
            <p class="surfside-today-empty">There are no events scheduled for today. Check the full calendar for upcoming opportunities to worship, connect, and serve.</p>
        <?php endif; ?>

        <?php if (strtolower((string) $atts['show_link']) !== 'no' && $events_url !== '') : ?>
            <a class="surfside-today-link" href="<?php echo esc_url($events_url); ?>">View the full calendar →</a>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_today', 'surfside_tools_today_shortcode');
