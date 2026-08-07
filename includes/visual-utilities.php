<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Front-end visual utilities migrated from Code Snippets.
 * Existing shortcode names and CSS classes are preserved.
 */
function surfside_tools_visual_utilities_prevent_countdown_cache() {
    if (is_admin() || !is_singular()) {
        return;
    }

    $post = get_queried_object();
    if (!($post instanceof WP_Post)) {
        return;
    }

    $countdown_shortcodes = array(
        'surfside_service_countdown',
        'surfside_service_countdown_compact',
        'surfside_sunday_countdown',
    );

    foreach ($countdown_shortcodes as $shortcode) {
        if (has_shortcode($post->post_content, $shortcode)) {
            if (function_exists('surfside_tools_prevent_cache')) {
                surfside_tools_prevent_cache();
            }
            return;
        }
    }
}
add_action('template_redirect', 'surfside_tools_visual_utilities_prevent_countdown_cache', 1);

function surfside_tools_visual_utilities_styles() {
    wp_register_style('surfside-tools-visual-utilities', false, array(), SURFSIDE_TOOLS_VERSION);
    wp_enqueue_style('surfside-tools-visual-utilities');
    wp_add_inline_style('surfside-tools-visual-utilities', '
        body:not(.wp-admin) :is(.surfside-reveal, .surfside-section-white, .surfside-section-sand, .surfside-section-soft, .surfside-prayer-cta){opacity:0;transform:translateY(16px);transition:opacity 700ms ease,transform 700ms ease}
        body:not(.wp-admin) :is(.surfside-reveal, .surfside-section-white, .surfside-section-sand, .surfside-section-soft, .surfside-prayer-cta).is-visible{opacity:1;transform:translateY(0)}
        body:not(.wp-admin) .surfside-staggered-cards > *{opacity:0;transform:translateY(16px);transition:opacity 700ms ease,transform 700ms ease}
        body:not(.wp-admin) .surfside-staggered-cards.is-visible > *{opacity:1;transform:translateY(0)}
        body:not(.wp-admin) :is(.surfside-section-white, .surfside-section-sand, .surfside-section-soft):has(.surfside-staggered-cards){opacity:1;transform:none;transition:none}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-1{transition-delay:.1s}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-2{transition-delay:.5s}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-3{transition-delay:.75s}
        body:not(.wp-admin) .surfside-staggered-cards > :is(:nth-child(1),:nth-child(4)){transition-delay:.1s}
        body:not(.wp-admin) .surfside-staggered-cards > :is(:nth-child(2),:nth-child(5)){transition-delay:.3s}
        body:not(.wp-admin) .surfside-staggered-cards > :is(:nth-child(3),:nth-child(6)){transition-delay:.5s}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-4{transition-delay:1s}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-5{transition-delay:1.25s}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-6{transition-delay:1.5s}
        body:not(.wp-admin) .surfside-reveal.surfside-delay-7{transition-delay:1.75s}
        .wp-block-group:has(> .surfside-visit-expectations){margin-block-start:0!important;padding-block:0!important}
        .surfside-visit-expectations{box-sizing:border-box;max-width:none!important;width:100%;background:#fff;padding:56px 16px}
        .surfside-visit-expectations__inner{max-width:80rem;margin:0 auto}
        .surfside-visit-expectations h2{color:#061b33;font-size:clamp(2rem,4vw,3rem);font-weight:700;line-height:1.12;text-align:center;margin:0 0 32px}
        .surfside-visit-expectations__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
        .surfside-visit-expectations__card{box-sizing:border-box;background:#fff;border:1px solid #d8e1e9;border-radius:16px;box-shadow:0 2px 8px rgba(6,27,51,.08);color:#10243a;min-height:184px;padding:28px}
        .surfside-visit-expectations__card h3{color:#061b33;font-size:1.35rem;font-weight:700;line-height:1.25;margin:0 0 12px}
        .surfside-visit-expectations__card p{font-size:1rem;line-height:1.6;margin:0}
        body:not(.wp-admin) .surfside-visit-expectations__grid > :nth-child(1){transition-delay:.1s}
        body:not(.wp-admin) .surfside-visit-expectations__grid > :nth-child(2){transition-delay:.35s}
        body:not(.wp-admin) .surfside-visit-expectations__grid > :nth-child(3){transition-delay:.6s}
        body:not(.wp-admin) .surfside-visit-expectations__grid > :nth-child(4){transition-delay:.85s}
        body:not(.wp-admin) .surfside-visit-expectations__grid > :nth-child(5){transition-delay:1.1s}
        body:not(.wp-admin) .surfside-visit-expectations__grid > :nth-child(6){transition-delay:1.35s}
        @media(max-width:900px){.surfside-visit-expectations__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:600px){.surfside-visit-expectations{padding:40px 16px}.surfside-visit-expectations h2{margin-bottom:24px}.surfside-visit-expectations__grid{grid-template-columns:1fr;gap:16px}.surfside-visit-expectations__card{min-height:0;padding:24px}body:not(.wp-admin) .surfside-visit-expectations__grid > :nth-child(n){transition-delay:calc((var(--surfside-card-index) - 1) * .18s + .1s)}}
        .wp-admin :is(.surfside-reveal, .surfside-section-white, .surfside-section-sand, .surfside-section-soft, .surfside-prayer-cta, .surfside-staggered-cards > *),.editor-styles-wrapper :is(.surfside-reveal, .surfside-section-white, .surfside-section-sand, .surfside-section-soft, .surfside-prayer-cta, .surfside-staggered-cards > *),.block-editor-page :is(.surfside-reveal, .surfside-section-white, .surfside-section-sand, .surfside-section-soft, .surfside-prayer-cta, .surfside-staggered-cards > *),.interface-interface-skeleton :is(.surfside-reveal, .surfside-section-white, .surfside-section-sand, .surfside-section-soft, .surfside-prayer-cta, .surfside-staggered-cards > *){opacity:1!important;transform:none!important;transition:none!important}
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
        @media(prefers-reduced-motion:reduce){body:not(.wp-admin) :is(.surfside-reveal, .surfside-section-white, .surfside-section-sand, .surfside-section-soft, .surfside-prayer-cta, .surfside-staggered-cards > *){opacity:1;transform:none;transition:none}}
    ');
}
add_action('wp_enqueue_scripts', 'surfside_tools_visual_utilities_styles');

function surfside_tools_visual_utilities_scripts() {
    if (is_admin()) return;
    ?>
    <script>
    (function(){
        function initReveal(){
            var items=document.querySelectorAll('.surfside-reveal, .surfside-section-white, .surfside-section-sand, .surfside-section-soft, .surfside-prayer-cta, .surfside-staggered-cards');
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
                var livestream=box.getAttribute('data-surfside-countdown-livestream')==='1';
                function update(){
                    var distance=target-Date.now();
                    if(distance<=0){
                        if(!livestream){window.location.reload();return;}
                        box.innerHTML='<a class="wp-block-button__link wp-element-button" href="/watch-live/">🔴 We’re Live Now</a>';
                        box.classList.add('surfside-is-live');
                        box.setAttribute('data-surfside-live-until',String(target+3600000));
                        if(interval)clearInterval(interval);
                        initLiveWindows();
                        return;
                    }
                    if(box.classList.contains('surfside-countdown')){
                        var values={days:Math.floor(distance/86400000),hours:Math.floor(distance/3600000)%24,minutes:Math.floor(distance/60000)%60,seconds:Math.floor(distance/1000)%60};
                        Object.keys(values).forEach(function(key){var el=box.querySelector('.'+key);if(el)el.textContent=values[key];});
                    }else{var el=box.querySelector('.surfside-compact-time');if(el)el.textContent=compact(distance);}
                }
                update();interval=setInterval(update,1000);
            });
        }
        function initLiveWindows(){
            document.querySelectorAll('[data-surfside-live-until]').forEach(function(box){
                if(box.dataset.surfsideLiveWindowReady)return;
                box.dataset.surfsideLiveWindowReady='1';
                var liveUntil=parseInt(box.getAttribute('data-surfside-live-until'),10);
                var interval=setInterval(function(){
                    if(Date.now()>liveUntil){clearInterval(interval);window.location.reload();}
                },1000);
            });
        }
        function init(){initReveal();initCountdowns();initLiveWindows();}
        if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
    })();
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_visual_utilities_scripts', 30);

function surfside_tools_visit_expectations_shortcode() {
    $cards = array(
        array('icon' => '😊', 'title' => 'Friendly & Relaxed', 'text' => 'Come as you are. Whether you prefer jeans and a t-shirt or Sunday best, you’ll fit right in.'),
        array('icon' => '☕', 'title' => 'When I Arrive', 'text' => 'Grab a coffee, say hello, and find a seat. We want you to feel comfortable from the moment you arrive.'),
        array('icon' => '📖', 'title' => 'Biblical Teaching', 'text' => 'Pastor Erick teaches God’s Word in a way that is understandable, applicable, and relevant to everyday life.'),
        array('icon' => '🎵', 'title' => 'Worship', 'text' => 'Our services include worship through music, prayer, and biblical teaching centered on Christ.'),
        array('icon' => '⏱', 'title' => 'About an Hour', 'text' => 'We start on time, focus on what matters most, and strive to make every minute meaningful.'),
        array('icon' => '🤝', 'title' => 'No Pressure', 'text' => 'You won’t be asked to stand up, introduce yourself, or participate in anything you’re uncomfortable with.'),
    );

    $html = '<section class="surfside-visit-expectations" aria-labelledby="surfside-visit-expectations-heading">';
    $html .= '<div class="surfside-visit-expectations__inner">';
    $html .= '<h2 id="surfside-visit-expectations-heading">What Should I Expect?</h2>';
    $html .= '<div class="surfside-visit-expectations__grid surfside-staggered-cards">';

    foreach ($cards as $index => $card) {
        $html .= '<article class="surfside-visit-expectations__card" style="--surfside-card-index:' . esc_attr($index + 1) . '">';
        $html .= '<h3><span aria-hidden="true">' . esc_html($card['icon']) . '</span> ' . esc_html($card['title']) . '</h3>';
        $html .= '<p>' . esc_html($card['text']) . '</p>';
        $html .= '</article>';
    }

    $html .= '</div></div></section>';
    return $html;
}

function surfside_tools_service_schedule() {
    if (function_exists('surfside_tools_site_information_services')) {
        $shared_services = surfside_tools_site_information_services();
        $schedule = array();

        foreach ($shared_services as $service) {
            $weekday = absint($service['weekday'] ?? 0);
            $day = trim((string) ($service['day'] ?? ''));
            $time = trim((string) ($service['time_24'] ?? ''));
            $display_time = trim((string) ($service['time'] ?? ''));
            if ($weekday < 1 || $weekday > 7 || $day === '' || $time === '') {
                continue;
            }

            $schedule[] = array(
                'weekday' => $weekday,
                'day' => $day,
                'time' => $time,
                'label' => (string) ($service['label'] ?? 'Worship Service'),
                'compact' => trim($day . ' at ' . $display_time),
                'livestream' => !empty($service['livestream']),
            );
        }

        if ($schedule) {
            return $schedule;
        }
    }

    return array(
        array('weekday'=>6,'day'=>'Saturday','time'=>'18:00','label'=>'Saturday Worship','compact'=>'Saturday at 6:00 PM','livestream'=>false),
        array('weekday'=>7,'day'=>'Sunday','time'=>'09:45','label'=>'Sunday Worship','compact'=>'Sunday at 9:45 AM','livestream'=>true),
    );
}

function surfside_tools_next_service($livestream_only = false) {
    $timezone = wp_timezone();
    $now = new DateTimeImmutable('now', $timezone);
    $services = surfside_tools_service_schedule();

    if ($livestream_only) {
        $services = array_values(array_filter($services, function ($service) {
            return !empty($service['livestream']);
        }));
    }

    $next = null;
    $live = null;
    $live_end = null;

    foreach ($services as $service) {
        $today = new DateTimeImmutable('this ' . $service['day'] . ' ' . $service['time'], $timezone);
        $end = $today->modify('+60 minutes');

        if (!empty($service['livestream']) && $now >= $today && $now < $end) {
            $live = $service;
            $live_end = $end;
        }

        $candidate = $today < $now ? $today->modify('+1 week') : $today;
        if ($next === null || $candidate < $next['datetime']) {
            $next = array('datetime'=>$candidate,'service'=>$service);
        }
    }

    return array('live'=>$live,'live_end'=>$live_end,'next'=>$next);
}

function surfside_tools_live_window_attribute($state) {
    if (empty($state['live_end']) || !($state['live_end'] instanceof DateTimeInterface)) {
        return '';
    }

    return ' data-surfside-live-until="' . esc_attr($state['live_end']->getTimestamp() * 1000) . '"';
}

function surfside_tools_countdown_livestream_attribute($service) {
    return ' data-surfside-countdown-livestream="' . (!empty($service['livestream']) ? '1' : '0') . '"';
}

function surfside_tools_service_countdown_shortcode() {
    $state = surfside_tools_next_service();
    if ($state['live']) {
        return '<div class="surfside-countdown surfside-live-now surfside-is-live"' . surfside_tools_live_window_attribute($state) . '><div class="surfside-countdown-label">We’re Live Now</div><div class="surfside-countdown-service">' . esc_html($state['live']['label']) . '</div><a class="wp-block-button__link wp-element-button" href="/watch-live/">Watch Live</a></div>';
    }
    if (empty($state['next'])) return '';
    $timestamp = $state['next']['datetime']->getTimestamp() * 1000;
    return '<div class="surfside-countdown" data-surfside-countdown-time="' . esc_attr($timestamp) . '"' . surfside_tools_countdown_livestream_attribute($state['next']['service']) . '><div class="surfside-countdown-label">Next Service</div><div class="surfside-countdown-service">' . esc_html($state['next']['service']['label']) . '</div><div class="surfside-countdown-timer"><span><strong class="days">0</strong><small>Days</small></span><span><strong class="hours">0</strong><small>Hours</small></span><span><strong class="minutes">0</strong><small>Minutes</small></span><span><strong class="seconds">0</strong><small>Seconds</small></span></div></div>';
}

function surfside_tools_compact_countdown_shortcode() {
    $state = surfside_tools_next_service();
    $id = wp_unique_id('surfside-compact-countdown-');
    if ($state['live']) return '<div id="' . esc_attr($id) . '" class="surfside-compact-countdown surfside-is-live"' . surfside_tools_live_window_attribute($state) . '><a href="/watch-live/">🔴 We’re Live Now</a></div>';
    if (empty($state['next'])) return '';
    $timestamp = $state['next']['datetime']->getTimestamp() * 1000;
    return '<div id="' . esc_attr($id) . '" class="surfside-compact-countdown" data-surfside-countdown-time="' . esc_attr($timestamp) . '"' . surfside_tools_countdown_livestream_attribute($state['next']['service']) . '><div class="surfside-next-service-label">Next Service</div><div class="surfside-next-service">' . esc_html($state['next']['service']['compact']) . '</div><div class="surfside-compact-time">loading...</div></div>';
}

function surfside_tools_sunday_countdown_shortcode() {
    $state = surfside_tools_next_service(true);
    $id = wp_unique_id('surfside-sunday-countdown-');
    if ($state['live']) return '<div id="' . esc_attr($id) . '" class="surfside-sunday-countdown surfside-is-live"' . surfside_tools_live_window_attribute($state) . '><a href="/watch-live/">🔴 We’re Live Now</a></div>';
    if (empty($state['next'])) return '';
    $timestamp = $state['next']['datetime']->getTimestamp() * 1000;
    return '<div id="' . esc_attr($id) . '" class="surfside-sunday-countdown" data-surfside-countdown-time="' . esc_attr($timestamp) . '" data-surfside-countdown-livestream="1"><div class="surfside-next-service-label">Next Livestream</div><div class="surfside-next-service">' . esc_html($state['next']['service']['compact']) . '</div><div class="surfside-compact-time">loading...</div></div>';
}

add_action('init', function () {
    remove_shortcode('surfside_service_countdown');
    remove_shortcode('surfside_service_countdown_compact');
    remove_shortcode('surfside_sunday_countdown');
    add_shortcode('surfside_service_countdown', 'surfside_tools_service_countdown_shortcode');
    add_shortcode('surfside_service_countdown_compact', 'surfside_tools_compact_countdown_shortcode');
    add_shortcode('surfside_sunday_countdown', 'surfside_tools_sunday_countdown_shortcode');
    add_shortcode('surfside_visit_expectations', 'surfside_tools_visit_expectations_shortcode');
}, 999);
