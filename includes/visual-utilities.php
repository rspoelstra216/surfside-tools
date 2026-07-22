<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Front-end visual utilities migrated from Code Snippets.
 * Existing shortcode names and CSS classes are preserved.
 */
function surfside_tools_visual_utilities_styles() {
    wp_register_style('surfside-tools-visual-utilities', false, array(), SURFSIDE_TOOLS_VERSION);
    wp_enqueue_style('surfside-tools-visual-utilities');
    wp_add_inline_style('surfside-tools-visual-utilities', '
        body:not(.wp-admin) .surfside-reveal{opacity:0;transform:translateY(16px);transition:opacity 700ms ease,transform 700ms ease}
        body:not(.wp-admin) .surfside-reveal.is-visible{opacity:1;transform:translateY(0)}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-1{transition-delay:.1s}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-2{transition-delay:.5s}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-3{transition-delay:.75s}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-4{transition-delay:1s}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-5{transition-delay:1.25s}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-6{transition-delay:1.5s}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-7{transition-delay:1.75s}
        .wp-admin .surfside-reveal,.editor-styles-wrapper .surfside-reveal,.block-editor-page .surfside-reveal,.interface-interface-skeleton .surfside-reveal{opacity:1!important;transform:none!important;transition:none!important}
        .surfside-countdown{text-align:center;padding:28px 20px;border-radius:18px;background:#f5f5f8;max-width:760px;margin:24px auto}
        .surfside-countdown-label{font-size:.9rem;text-transform:uppercase;letter-spacing:.08em;font-weight:700;opacity:.75;margin-bottom:6px}
        .surfside-countdown-service{font-size:1.5rem;font-weight:700;margin-bottom:18px}
        .surfside-countdown-timer{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
        .surfside-countdown-timer span{background:#fff;border-radius:14px;padding:16px 8px;box-shadow:0 4px 14px rgba(0,0,0,.06)}
        .surfside-countdown-timer strong{display:block;font-size:clamp(1.6rem,5vw,2.6rem);line-height:1}
        .surfside-countdown-timer small{display:block;margin-top:6px;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;opacity:.7}
        .surfside-live-now{background:#f2f2fa;color:#fff!important}
        .surfside-is-live,.surfside-is-live a,.surfside-is-live span,.surfside-is-live div{color:#fff!important}
        .surfside-compact-countdown{margin-top:18px;text-align:center;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.45)}
        .surfside-next-service-label{font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;opacity:.85;margin-bottom:2px}
        .surfside-next-service{font-size:1.1rem;font-weight:700;margin-bottom:4px}
        .surfside-compact-time{font-size:.95rem;font-weight:500}
        .surfside-sunday-countdown{margin:18px 0 24px;text-align:left;color:inherit}
        .surfside-sunday-countdown .surfside-next-service-label{font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;opacity:.75;margin-bottom:4px}
        .surfside-sunday-countdown .surfside-next-service{font-size:1.35rem;font-weight:700;margin-bottom:6px}
        .surfside-sunday-countdown .surfside-compact-time{font-size:1rem;font-weight:500}
        @media(max-width:600px){.surfside-countdown-timer{grid-template-columns:repeat(2,1fr)}}
        @media(prefers-reduced-motion:reduce){body:not(.wp-admin) .surfside-reveal{opacity:1;transform:none;transition:none}}
    ');
}
add_action('wp_enqueue_scripts', 'surfside_tools_visual_utilities_styles');

function surfside_tools_visual_utilities_scripts() {
    if (is_admin()) return;
    ?>
    <script>
    (function(){
        function initReveal(){
            var items=document.querySelectorAll('.surfside-reveal');
            if(!items.length)return;
            if(!('IntersectionObserver' in window)){items.forEach(function(item){item.classList.add('is-visible');});return;}
            var observer=new IntersectionObserver(function(entries){entries.forEach(function(entry){if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}});},{root:null,rootMargin:'0px 0px -5% 0px',threshold:.05});
            items.forEach(function(item){observer.observe(item);});
            setTimeout(function(){items.forEach(function(item){var rect=item.getBoundingClientRect();if(rect.top<window.innerHeight&&rect.bottom>0)item.classList.add('is-visible');});},500);
        }
        function compact(distance){var d=Math.floor(distance/86400000),h=Math.floor(distance/3600000)%24,m=Math.floor(distance/60000)%60,s=Math.floor(distance/1000)%60;return d+'d '+h+'h '+m+'m '+s+'s';}
        function initCountdowns(){
            document.querySelectorAll('[data-surfside-countdown-time]').forEach(function(box){
                if(box.dataset.surfsideCountdownReady)return;
                box.dataset.surfsideCountdownReady='1';
                var target=parseInt(box.getAttribute('data-surfside-countdown-time'),10),interval=null;
                function update(){
                    var distance=target-Date.now();
                    if(distance<=0){box.innerHTML='<a class="wp-block-button__link wp-element-button" href="/watch-live/">🔴 We’re Live Now</a>';box.classList.add('surfside-is-live');if(interval)clearInterval(interval);return;}
                    if(box.classList.contains('surfside-countdown')){
                        var values={days:Math.floor(distance/86400000),hours:Math.floor(distance/3600000)%24,minutes:Math.floor(distance/60000)%60,seconds:Math.floor(distance/1000)%60};
                        Object.keys(values).forEach(function(key){var el=box.querySelector('.'+key);if(el)el.textContent=values[key];});
                    }else{var el=box.querySelector('.surfside-compact-time');if(el)el.textContent=compact(distance);}
                }
                update();interval=setInterval(update,1000);
            });
        }
        function init(){initReveal();initCountdowns();}
        if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
    })();
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_visual_utilities_scripts', 30);

function surfside_tools_service_schedule() {
    if (function_exists('surfside_tools_site_information_service_schedule')) {
        $shared_schedule = surfside_tools_site_information_service_schedule();
        $schedule = array();

        foreach ($shared_schedule as $weekday => $service) {
            $day = trim((string) ($service['day'] ?? ''));
            $time = trim((string) ($service['time_24'] ?? ''));
            $display_time = trim((string) ($service['time'] ?? ''));
            if ($day === '' || $time === '') {
                continue;
            }

            $schedule[] = array(
                'weekday' => absint($weekday),
                'day' => $day,
                'time' => $time,
                'label' => (string) ($service['label'] ?? 'Worship Service'),
                'compact' => trim($day . ' at ' . $display_time),
            );
        }

        if ($schedule) {
            return $schedule;
        }
    }

    return array(
        array('weekday'=>6,'day'=>'Saturday','time'=>'18:00','label'=>'Saturday Worship','compact'=>'Saturday at 6:00 PM'),
        array('weekday'=>7,'day'=>'Sunday','time'=>'09:45','label'=>'Sunday Worship','compact'=>'Sunday at 9:45 AM'),
    );
}

function surfside_tools_next_service($sunday_only = false) {
    $timezone = wp_timezone();
    $now = new DateTimeImmutable('now', $timezone);
    $schedule = surfside_tools_service_schedule();
    $services = $schedule;
    if ($sunday_only) {
        $services = array_values(array_filter($schedule, function ($service) {
            return (int) ($service['weekday'] ?? 0) === 7;
        }));
    }
    $next = null;
    $live = null;

    foreach ($services as $service) {
        $today = new DateTimeImmutable('this ' . $service['day'] . ' ' . $service['time'], $timezone);
        $end = $today->modify('+90 minutes');
        if ($now >= $today && $now <= $end) $live = $service;
        $candidate = $today < $now ? $today->modify('+1 week') : $today;
        if ($next === null || $candidate < $next['datetime']) $next = array('datetime'=>$candidate,'service'=>$service);
    }

    return array('live'=>$live,'next'=>$next);
}

function surfside_tools_service_countdown_shortcode() {
    $state = surfside_tools_next_service();
    if ($state['live']) {
        return '<div class="surfside-countdown surfside-live-now surfside-is-live"><div class="surfside-countdown-label">We’re Live Now</div><div class="surfside-countdown-service">' . esc_html($state['live']['label']) . '</div><a class="wp-block-button__link wp-element-button" href="/watch-live/">Watch Live</a></div>';
    }
    if (empty($state['next'])) return '';
    $timestamp = $state['next']['datetime']->getTimestamp() * 1000;
    return '<div class="surfside-countdown" data-surfside-countdown-time="' . esc_attr($timestamp) . '"><div class="surfside-countdown-label">Next Service</div><div class="surfside-countdown-service">' . esc_html($state['next']['service']['label']) . '</div><div class="surfside-countdown-timer"><span><strong class="days">0</strong><small>Days</small></span><span><strong class="hours">0</strong><small>Hours</small></span><span><strong class="minutes">0</strong><small>Minutes</small></span><span><strong class="seconds">0</strong><small>Seconds</small></span></div></div>';
}

function surfside_tools_compact_countdown_shortcode() {
    $state = surfside_tools_next_service();
    $id = wp_unique_id('surfside-compact-countdown-');
    if ($state['live']) return '<div id="' . esc_attr($id) . '" class="surfside-compact-countdown surfside-is-live"><a href="/watch-live/">🔴 We’re Live Now</a></div>';
    if (empty($state['next'])) return '';
    $timestamp = $state['next']['datetime']->getTimestamp() * 1000;
    return '<div id="' . esc_attr($id) . '" class="surfside-compact-countdown" data-surfside-countdown-time="' . esc_attr($timestamp) . '"><div class="surfside-next-service-label">Next Service</div><div class="surfside-next-service">' . esc_html($state['next']['service']['compact']) . '</div><div class="surfside-compact-time">loading...</div></div>';
}

function surfside_tools_sunday_countdown_shortcode() {
    $state = surfside_tools_next_service(true);
    $id = wp_unique_id('surfside-sunday-countdown-');
    if ($state['live']) return '<div id="' . esc_attr($id) . '" class="surfside-sunday-countdown surfside-is-live"><a href="/watch-live/">🔴 We’re Live Now</a></div>';
    if (empty($state['next'])) return '';
    $timestamp = $state['next']['datetime']->getTimestamp() * 1000;
    return '<div id="' . esc_attr($id) . '" class="surfside-sunday-countdown" data-surfside-countdown-time="' . esc_attr($timestamp) . '"><div class="surfside-next-service-label">Next Livestream</div><div class="surfside-next-service">' . esc_html($state['next']['service']['compact']) . '</div><div class="surfside-compact-time">loading...</div></div>';
}

add_action('init', function () {
    remove_shortcode('surfside_service_countdown');
    remove_shortcode('surfside_service_countdown_compact');
    remove_shortcode('surfside_sunday_countdown');
    add_shortcode('surfside_service_countdown', 'surfside_tools_service_countdown_shortcode');
    add_shortcode('surfside_service_countdown_compact', 'surfside_tools_compact_countdown_shortcode');
    add_shortcode('surfside_sunday_countdown', 'surfside_tools_sunday_countdown_shortcode');
}, 999);
