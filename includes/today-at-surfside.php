<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep dynamic Today at Surfside output out of full-page caches.
 */
function surfside_tools_today_prevent_page_cache() {
    if (!is_singular()) {
        return;
    }

    $post = get_queried_object();
    if (!($post instanceof WP_Post)) {
        return;
    }

    $content = (string) $post->post_content;
    if (!has_shortcode($content, 'surfside_today') && !has_shortcode($content, 'surfside_today_compact')) {
        return;
    }

    if (function_exists('surfside_tools_prevent_cache')) {
        surfside_tools_prevent_cache();
        return;
    }

    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    nocache_headers();
}
add_action('template_redirect', 'surfside_tools_today_prevent_page_cache', 0);

/**
 * Milestone 7: a reusable public summary of what is happening at Surfside today.
 */
function surfside_tools_today_service_schedule() {
    if (function_exists('surfside_tools_site_information_service_schedule')) {
        return apply_filters(
            'surfside_tools_today_service_schedule',
            surfside_tools_site_information_service_schedule()
        );
    }

    return apply_filters('surfside_tools_today_service_schedule', array(
        6 => array('label' => 'Saturday Worship', 'time' => '6:00 PM'),
        7 => array('label' => 'Sunday Worship', 'time' => '9:45 AM'),
    ));
}


function surfside_tools_today_is_service_occurrence($event, $service, $weekday) {
    $title = strtolower(trim((string) ($event['title'] ?? '')));
    $start_time = trim((string) ($event['start_time'] ?? ''));
    $service_time = trim((string) ($service['time'] ?? ''));

    if ($title === '' || $start_time === '' || $service_time === '') {
        return false;
    }

    if (strpos($title, 'service') === false && strpos($title, 'worship') === false) {
        return false;
    }

    $weekday_names = array(6 => 'saturday', 7 => 'sunday');
    $weekday_name = $weekday_names[$weekday] ?? '';
    if ($weekday_name === '' || strpos($title, $weekday_name) === false) {
        return false;
    }

    $event_timestamp = strtotime($start_time);
    $service_timestamp = strtotime($service_time);
    if (!$event_timestamp || !$service_timestamp) {
        return false;
    }

    return date('H:i', $event_timestamp) === date('H:i', $service_timestamp);
}

function surfside_tools_today_remove_duplicate_service($events, $service, $weekday) {
    if (!$service) {
        return $events;
    }

    return array_values(array_filter((array) $events, function ($event) use ($service, $weekday) {
        return !surfside_tools_today_is_service_occurrence($event, $service, $weekday);
    }));
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
        .surfside-today-service{display:grid;grid-template-columns:auto 1fr;gap:16px;align-items:center;padding:18px;border-radius:16px;background:var(--surfside-today-navy);color:#fff}.surfside-today-service-time{display:grid;place-items:center;min-width:104px;min-height:74px;padding:10px;border-radius:13px;background:#fff;color:var(--surfside-today-blue);font-size:1.12rem;font-weight:900;text-align:center}.surfside-today-service h3{margin:0 0 4px;color:#fff;font-size:1.25rem}.surfside-today-service p{margin:0;color:rgba(255,255,255,.82)}.surfside-today-sermon{margin-top:8px!important;color:#fff!important;font-weight:800}.surfside-today-sermon-link{color:#fff!important;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:3px}.surfside-today-sermon-link:hover,.surfside-today-sermon-link:focus-visible{color:#fff!important;text-decoration-thickness:3px}.surfside-today-sermon-link:focus-visible{outline:3px solid rgba(255,255,255,.55);outline-offset:3px}.surfside-today-watch-live{display:inline-flex;align-items:center;justify-content:center;min-height:44px;margin-top:13px;padding:9px 14px;border-radius:9px;background:#fff;color:#9f1d20!important;font-weight:900;text-decoration:none!important;box-shadow:0 6px 16px rgba(0,0,0,.16)}.surfside-today-watch-live:hover,.surfside-today-watch-live:focus-visible{background:#fdf2f2;color:#7f1d1d!important}.surfside-today-watch-live:focus-visible{outline:3px solid rgba(255,255,255,.55);outline-offset:3px}
        .surfside-today-section-title{margin:0;color:var(--surfside-today-navy);font-size:1.05rem}.surfside-today-events{display:grid;gap:12px}.surfside-today-event{display:grid;grid-template-columns:minmax(0,1fr);overflow:hidden;border:1px solid rgba(7,27,58,.11);border-radius:15px;background:#fff}.surfside-today-event.has-image{grid-template-columns:minmax(150px,30%) minmax(0,1fr)}.surfside-today-event-media{min-height:150px;background:#eef2f7}.surfside-today-event-image{display:block;width:100%;height:100%;min-height:150px;object-fit:cover}.surfside-today-event-content{padding:17px}.surfside-today-event h3{margin:0 0 7px;color:var(--surfside-today-navy);font-size:1.14rem}.surfside-today-event-meta{display:flex;flex-wrap:wrap;gap:6px 12px;color:#46536a;font-size:.92rem;font-weight:700}.surfside-today-event-meta span+span:before{content:"•";margin-right:12px;color:#9aa5b5}.surfside-today-event p{margin:10px 0 0;line-height:1.5}
        .surfside-today-message-dialog{width:min(760px,calc(100vw - 32px));max-width:760px;max-height:min(86vh,900px);padding:0;border:0;border-radius:20px;background:#fff;color:#071b3a;box-shadow:0 24px 70px rgba(7,27,58,.28);overflow:hidden}.surfside-today-message-dialog::backdrop{background:rgba(7,27,58,.72);backdrop-filter:blur(3px)}.surfside-today-message-dialog-header{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px 22px;border-bottom:1px solid rgba(7,27,58,.12);background:#fff}.surfside-today-message-dialog-header h2{margin:0;color:#071b3a;font-size:clamp(24px,4vw,32px);line-height:1.15}.surfside-today-message-dialog-close{display:inline-flex;align-items:center;justify-content:center;min-width:44px;min-height:44px;padding:8px 14px;border:0;border-radius:10px;background:#071b3a;color:#fff;font:inherit;font-weight:800;cursor:pointer}.surfside-today-message-dialog-close:hover,.surfside-today-message-dialog-close:focus-visible{background:#0b4f9c}.surfside-today-message-dialog-close:focus-visible{outline:3px solid rgba(11,79,156,.3);outline-offset:3px}.surfside-today-message-dialog-body{max-height:calc(86vh - 81px);padding:24px;overflow:auto;overscroll-behavior:contain}body.surfside-today-dialog-open{overflow:hidden}
        .surfside-today-empty{margin:0;padding:16px;border-radius:14px;background:#fff;color:#5b667a}.surfside-today-link{justify-self:start;display:inline-flex;align-items:center;min-height:42px;padding:9px 14px;border-radius:9px;background:var(--surfside-today-blue);color:#fff!important;font-weight:800;text-decoration:none!important}.surfside-today-link:hover,.surfside-today-link:focus-visible{background:var(--surfside-today-navy)}.surfside-today-link:focus-visible{outline:3px solid rgba(11,79,156,.25);outline-offset:3px}
        .surfside-today-compact{display:grid;justify-items:center;gap:3px;width:fit-content;max-width:100%;margin-inline:auto;color:#fff;text-align:center;text-shadow:0 2px 8px rgba(0,0,0,.7)}.surfside-today-compact-link{display:grid;justify-items:center;gap:3px;color:inherit!important;text-decoration:none!important}.surfside-today-compact-link:hover .surfside-today-compact-title,.surfside-today-compact-link:focus-visible .surfside-today-compact-title{text-decoration:underline;text-underline-offset:3px}.surfside-today-compact-link:focus-visible{outline:3px solid rgba(255,255,255,.8);outline-offset:5px;border-radius:4px}.surfside-today-compact-eyebrow{font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.surfside-today-compact-title{font-size:1.08rem;font-weight:900;line-height:1.25}.surfside-today-compact-meta{font-size:.92rem;font-weight:700;line-height:1.35}
        @media(max-width:700px){.surfside-today-message-dialog{width:100vw;max-width:none;height:100dvh;max-height:none;margin:0;border-radius:0}.surfside-today-message-dialog-header{padding:14px 16px}.surfside-today-message-dialog-body{max-height:calc(100dvh - 73px);padding:20px 16px}.surfside-today-header{display:block}.surfside-today-date{margin-top:8px;white-space:normal}.surfside-today-service{grid-template-columns:1fr}.surfside-today-service-time{min-width:0;min-height:0;justify-self:start}.surfside-today-event.has-image{grid-template-columns:1fr}.surfside-today-event-media,.surfside-today-event-image{min-height:190px;max-height:240px}.surfside-today-event-meta{display:grid;gap:4px}.surfside-today-event-meta span+span:before{content:none;margin:0}}
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
        'message_url' => '',
    ), $atts, 'surfside_today');

    $timezone = wp_timezone();
    $now = new DateTimeImmutable('now', $timezone);
    $today = $now->format('Y-m-d');
    $weekday = (int) $now->format('N');
    $schedule = surfside_tools_today_service_schedule();
    $service = isset($schedule[$weekday]) && is_array($schedule[$weekday]) ? $schedule[$weekday] : null;
    $sunday_live = false;
    if ($weekday === 7 && function_exists('surfside_tools_next_service')) {
        $sunday_state = surfside_tools_next_service(true);
        $sunday_live = !empty($sunday_state['live']);
    }
    $today_events = surfside_tools_calendar_get_occurrences($today, $today);
    $today_events = surfside_tools_today_remove_duplicate_service($today_events, $service, $weekday);
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
    $message_url = trim((string) $atts['message_url']);
    if ($message_url !== '' && strpos($message_url, 'http') !== 0) {
        $message_url = home_url('/' . ltrim($message_url, '/'));
    }

    $message_dialog_id = wp_unique_id('surfside-today-message-');
    $show_message_dialog = $service && $sermon_title !== '' && $message_url === '';

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
                    <?php if ($sermon_title !== '') : ?>
                        <p class="surfside-today-sermon">
                            <?php if ($message_url !== '') : ?>
                                <a class="surfside-today-sermon-link" href="<?php echo esc_url($message_url); ?>">Today’s message: <?php echo esc_html($sermon_title); ?></a>
                            <?php else : ?>
                                <a class="surfside-today-sermon-link" href="#<?php echo esc_attr($message_dialog_id); ?>" data-today-message-dialog="<?php echo esc_attr($message_dialog_id); ?>" aria-haspopup="dialog">Today’s message: <?php echo esc_html($sermon_title); ?></a>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($sunday_live) : ?>
                        <a class="surfside-today-watch-live" href="<?php echo esc_url(home_url('/watch-live/')); ?>">🔴 We’re Live Now</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($today_events)) : ?>
            <h3 class="surfside-today-section-title"><?php echo $service ? 'Also happening today' : 'Happening today'; ?></h3>
            <div class="surfside-today-events">
                <?php foreach ($today_events as $event) : echo surfside_tools_today_render_event($event, false); endforeach; ?>
            </div>
        <?php elseif (!empty($next_events)) : ?>
            <p class="surfside-today-empty">Nothing scheduled today.</p>
            <h3 class="surfside-today-section-title">Coming up next</h3>
            <div class="surfside-today-events"><?php echo surfside_tools_today_render_event($next_events[0], true); ?></div>
        <?php elseif (!$service) : ?>
            <p class="surfside-today-empty">There are no events scheduled for today. Check the full calendar for upcoming opportunities to worship, connect, and serve.</p>
        <?php endif; ?>

        <?php if (strtolower((string) $atts['show_link']) !== 'no' && $events_url !== '') : ?>
            <a class="surfside-today-link" href="<?php echo esc_url($events_url); ?>">View the full calendar →</a>
        <?php endif; ?>
    </section>

    <?php if ($show_message_dialog) : ?>
        <dialog id="<?php echo esc_attr($message_dialog_id); ?>" class="surfside-today-message-dialog" aria-labelledby="<?php echo esc_attr($message_dialog_id . '-title'); ?>">
            <div class="surfside-today-message-dialog-header">
                <h2 id="<?php echo esc_attr($message_dialog_id . '-title'); ?>">Message Notes</h2>
                <button type="button" class="surfside-today-message-dialog-close" data-today-dialog-close>Close</button>
            </div>
            <div class="surfside-today-message-dialog-body"><?php echo do_shortcode('[surfside_tools_message]'); ?></div>
        </dialog>
        <script>
        (function(){
            var trigger=document.querySelector('[data-today-message-dialog="<?php echo esc_js($message_dialog_id); ?>"]');
            var dialog=document.getElementById(<?php echo wp_json_encode($message_dialog_id); ?>);
            if(!trigger||!dialog||typeof dialog.showModal!=='function')return;
            trigger.addEventListener('click',function(event){
                event.preventDefault();
                dialog.showModal();
                document.body.classList.add('surfside-today-dialog-open');
            });
            dialog.addEventListener('click',function(event){if(event.target===dialog)dialog.close();});
            dialog.addEventListener('close',function(){
                document.body.classList.remove('surfside-today-dialog-open');
                trigger.focus();
            });
            dialog.querySelector('[data-today-dialog-close]').addEventListener('click',function(){dialog.close();});
        })();
        </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_today', 'surfside_tools_today_shortcode');

/**
 * Render a transparent, hero-friendly summary of today's activity.
 */
function surfside_tools_today_compact_shortcode($atts = array()) {
    if (!function_exists('surfside_tools_calendar_get_occurrences')) {
        return '';
    }

    surfside_tools_today_assets();

    $atts = shortcode_atts(array(
        'events_url' => '/events/',
        'watch_url' => '/watch-live/',
    ), $atts, 'surfside_today_compact');

    $url = static function ($value) {
        $value = trim((string) $value);
        if ($value !== '' && strpos($value, 'http') !== 0) {
            $value = home_url('/' . ltrim($value, '/'));
        }
        return $value;
    };

    $events_url = $url($atts['events_url']);
    $watch_url = $url($atts['watch_url']);
    $timezone = wp_timezone();
    $now = new DateTimeImmutable('now', $timezone);
    $today = $now->format('Y-m-d');
    $weekday = (int) $now->format('N');
    $schedule = surfside_tools_today_service_schedule();
    $service = isset($schedule[$weekday]) && is_array($schedule[$weekday]) ? $schedule[$weekday] : null;
    $today_events = surfside_tools_calendar_get_occurrences($today, $today);
    $today_events = surfside_tools_today_remove_duplicate_service($today_events, $service, $weekday);

    $eyebrow = 'Today at Surfside';
    $title = '';
    $meta = '';
    $destination = $events_url;

    $sunday_live = false;
    if ($weekday === 7 && function_exists('surfside_tools_next_service')) {
        $sunday_state = surfside_tools_next_service(true);
        $sunday_live = !empty($sunday_state['live']);
    }

    if ($sunday_live) {
        $eyebrow = 'Live now';
        $title = '🔴 We’re Live Now';
        $meta = 'Watch Sunday’s service →';
        $destination = $watch_url;
    } elseif ($service) {
        $title = (string) ($service['label'] ?? 'Worship Service');
        $meta = 'Today at ' . (string) ($service['time'] ?? '');
    } elseif (!empty($today_events)) {
        $event = $today_events[0];
        $title = (string) ($event['title'] ?? 'Surfside Event');
        $event_time = surfside_tools_calendar_format_time_range($event);
        $meta = $event_time !== '' ? 'Today · ' . $event_time : 'Today';
        $additional = count($today_events) - 1;
        if ($additional > 0) {
            $meta .= ' · +' . $additional . ' more';
        }
    } else {
        $tomorrow = $now->modify('+1 day')->format('Y-m-d');
        $range_end = $now->modify('+2 years')->format('Y-m-d');
        $next_events = surfside_tools_calendar_get_occurrences($tomorrow, $range_end, 1);

        if (!empty($next_events)) {
            $event = $next_events[0];
            $eyebrow = 'Coming up';
            $title = (string) ($event['title'] ?? 'Surfside Event');
            $event_date = !empty($event['date']) ? surfside_tools_calendar_format_date($event['date']) : '';
            $event_time = surfside_tools_calendar_format_time_range($event);
            $meta = implode(' · ', array_filter(array($event_date, $event_time)));
        } else {
            $title = 'Nothing scheduled today';
            $meta = 'View the full calendar →';
        }
    }

    if ($destination === '') {
        $destination = home_url('/events/');
    }

    ob_start();
    ?>
    <div class="surfside-today-compact">
        <a class="surfside-today-compact-link" href="<?php echo esc_url($destination); ?>">
            <span class="surfside-today-compact-eyebrow"><?php echo esc_html($eyebrow); ?></span>
            <span class="surfside-today-compact-title"><?php echo esc_html($title); ?></span>
            <?php if ($meta !== '') : ?>
                <span class="surfside-today-compact-meta"><?php echo esc_html($meta); ?></span>
            <?php endif; ?>
        </a>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_today_compact', 'surfside_tools_today_compact_shortcode');

