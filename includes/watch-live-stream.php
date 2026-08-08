<?php
/**
 * Dynamic Watch Live streaming block.
 *
 * @package SurfsideTools
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Find the next configured livestream service.
 *
 * @return array|null
 */
function surfside_tools_watch_live_next_service() {
    $now = current_datetime();
    $next = null;

    foreach (surfside_tools_site_information_services() as $service) {
        if (empty($service['livestream']) || empty($service['time_24'])) {
            continue;
        }

        $weekday = (int) ($service['weekday'] ?? 0);
        if ($weekday < 1 || $weekday > 7) {
            continue;
        }

        list($hour, $minute) = array_map('intval', explode(':', $service['time_24']));
        $days_ahead = ($weekday - (int) $now->format('N') + 7) % 7;
        $candidate = $now->modify('+' . $days_ahead . ' days')->setTime($hour, $minute);

        if ($candidate <= $now) {
            $candidate = $candidate->modify('+7 days');
        }

        if ($next === null || $candidate < $next['date']) {
            $next = array(
                'date' => $candidate,
                'service' => $service,
            );
        }
    }

    return $next;
}

/**
 * Load the streaming block assets only when the shortcode is rendered.
 */
function surfside_tools_watch_live_enqueue_assets() {
    $style_path = SURFSIDE_TOOLS_PATH . 'assets/css/watch-live-stream.css';
    $script_path = SURFSIDE_TOOLS_PATH . 'assets/js/watch-live-stream.js';

    wp_enqueue_style(
        'surfside-tools-watch-live',
        SURFSIDE_TOOLS_URL . 'assets/css/watch-live-stream.css',
        array('surfside-tools-design-system'),
        file_exists($style_path) ? (string) filemtime($style_path) : SURFSIDE_TOOLS_VERSION
    );
    wp_enqueue_script(
        'surfside-tools-watch-live',
        SURFSIDE_TOOLS_URL . 'assets/js/watch-live-stream.js',
        array(),
        file_exists($script_path) ? (string) filemtime($script_path) : SURFSIDE_TOOLS_VERSION,
        true
    );
}

/**
 * Render the live Twitch player with an offline local-video fallback.
 *
 * @param array $attributes Shortcode attributes.
 * @return string
 */
function surfside_tools_watch_live_shortcode($attributes = array()) {
    $attributes = shortcode_atts(
        array(
            'title' => 'Watch Live',
            'intro' => 'Join us live each Sunday from wherever you are.',
        ),
        $attributes,
        'surfside_watch_live'
    );

    surfside_tools_watch_live_enqueue_assets();

    $information = surfside_tools_get_site_information();
    $streaming = isset($information['streaming']) && is_array($information['streaming'])
        ? $information['streaming']
        : array();
    $channel = sanitize_key($streaming['twitch_channel'] ?? 'surfsidecf');
    $video_id = absint($streaming['announcement_video_id'] ?? 0);
    $video_url = $video_id ? wp_get_attachment_url($video_id) : '';
    $youtube_url = surfside_tools_site_information_url($streaming['youtube_url'] ?? 'https://www.youtube.com/@addpastor');
    $facebook_url = surfside_tools_site_information_url($streaming['facebook_url'] ?? 'https://www.facebook.com/SurfsideCommunityFellowship');
    $twitch_url = 'https://www.twitch.tv/' . $channel;
    $next = surfside_tools_watch_live_next_service();
    $heading_id = wp_unique_id('surfside-watch-live-heading-');
    $player_id = wp_unique_id('surfside-twitch-player-');
    $next_timestamp = $next ? $next['date']->format(DATE_ATOM) : '';
    $next_label = $next
        ? $next['date']->format('l') . ' at ' . $next['date']->format('g:i A')
        : '';

    ob_start();
    ?>
    <section
        class="surfside-watch-live surfside-section"
        aria-labelledby="<?php echo esc_attr($heading_id); ?>"
        data-surfside-watch-live
        data-channel="<?php echo esc_attr($channel); ?>"
        data-player-id="<?php echo esc_attr($player_id); ?>"
        data-next-service="<?php echo esc_attr($next_timestamp); ?>"
    >
        <div class="surfside-watch-live__inner">
            <header class="surfside-watch-live__intro">
                <h2 id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($attributes['title']); ?></h2>
                <?php if (trim((string) $attributes['intro']) !== '') : ?>
                    <p><?php echo esc_html($attributes['intro']); ?></p>
                <?php endif; ?>
            </header>

            <div class="surfside-watch-live__stage">
                <div class="surfside-watch-live__loading" data-stream-state="loading" role="status">
                    <span class="surfside-watch-live__spinner" aria-hidden="true"></span>
                    <p>Checking the livestream…</p>
                </div>

                <div class="surfside-watch-live__player" data-stream-state="live" hidden>
                    <div id="<?php echo esc_attr($player_id); ?>"></div>
                    <span class="surfside-watch-live__badge">Live now</span>
                </div>

                <div class="surfside-watch-live__offline" data-stream-state="offline" hidden>
                    <?php if ($video_url) : ?>
                        <video
                            class="surfside-watch-live__video"
                            src="<?php echo esc_url($video_url); ?>"
                            muted
                            autoplay
                            loop
                            playsinline
                            preload="metadata"
                            aria-label="Surfside announcements"
                        ></video>
                        <span class="surfside-watch-live__badge surfside-watch-live__badge--announcements">Surfside announcements</span>
                    <?php else : ?>
                        <div class="surfside-watch-live__offline-message">
                            <p class="surfside-watch-live__eyebrow">Next livestream</p>
                            <?php if ($next_label) : ?>
                                <h3><?php echo esc_html($next_label); ?></h3>
                            <?php endif; ?>
                            <p>We are offline right now. Join us for our next live service.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="surfside-watch-live__status" aria-live="polite">
                <div>
                    <p class="surfside-watch-live__eyebrow" data-stream-label>Next livestream</p>
                    <?php if ($next_label) : ?>
                        <p class="surfside-watch-live__next"><?php echo esc_html($next_label); ?></p>
                    <?php endif; ?>
                    <?php if ($next_timestamp) : ?>
                        <p class="surfside-watch-live__countdown" data-stream-countdown>Calculating…</p>
                    <?php endif; ?>
                </div>
                <nav class="surfside-watch-live__links" aria-label="Livestream platforms">
                    <?php if ($youtube_url) : ?><a class="surfside-button surfside-button--secondary" href="<?php echo esc_url($youtube_url); ?>" target="_blank" rel="noopener noreferrer">YouTube</a><?php endif; ?>
                    <?php if ($facebook_url) : ?><a class="surfside-button surfside-button--secondary" href="<?php echo esc_url($facebook_url); ?>" target="_blank" rel="noopener noreferrer">Facebook</a><?php endif; ?>
                    <a class="surfside-button surfside-button--secondary" href="<?php echo esc_url($twitch_url); ?>" target="_blank" rel="noopener noreferrer">Twitch</a>
                </nav>
            </div>
            <p class="surfside-watch-live__note">Twitch provides our most reliable live viewing experience.</p>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_watch_live', 'surfside_tools_watch_live_shortcode');
