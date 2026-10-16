<?php
/**
 * Homepage sermon teaser backed by the public Messages archive.
 *
 * Unlike Watch Live, this is a still-image preview and a link to the sermon:
 * visitors do not download/play a video on the homepage.
 * No YouTube or Cloudflare API requests occur on visitor page views.
 */
if (!defined('ABSPATH')) { exit; }

/** Prefer the published WordPress page's canonical URL. */
function surfside_tools_home_latest_sermon_archive_url() {
    $page = get_page_by_path('messages', OBJECT, 'page');
    if ($page instanceof WP_Post && $page->post_status === 'publish') {
        $link = get_permalink($page);
        if (is_string($link) && $link !== '') return $link;
    }
    return home_url('/messages/');
}

/** Only a valid, publicly visible, dated sermon can be featured. */
function surfside_tools_home_latest_sermon_data() {
    if (!function_exists('surfside_tools_public_messages_master_id')
        || !function_exists('surfside_tools_public_messages_latest')) {
        return null;
    }
    $master = surfside_tools_public_messages_master_id();
    if ($master === '') return null;

    // The shared archive query honors the staff Exclude disposition and
    // sermon date overrides, and never selects by YouTube upload date.
    $latest = surfside_tools_public_messages_latest($master);
    if (!is_array($latest)
        || !preg_match('/^[A-Za-z0-9_-]{11}$/', (string) ($latest['video_id'] ?? ''))
        || empty($latest['title']) || empty($latest['message_date'])) {
        return null;
    }
    return $latest;
}

function surfside_tools_home_latest_sermon_shortcode() {
    $archive_url = surfside_tools_home_latest_sermon_archive_url();
    $video = surfside_tools_home_latest_sermon_data();

    $watch_url = $video
        ? add_query_arg(array('sm_video' => $video['video_id']), $archive_url)
        : $archive_url;
    $heading = $video && function_exists('surfside_tools_public_messages_featured_heading')
        ? surfside_tools_public_messages_featured_heading($video['title'])
        : array('heading' => $video['title'] ?? '', 'context' => '');
    $date_label = $video && function_exists('surfside_tools_public_messages_date_label')
        ? surfside_tools_public_messages_date_label($video['message_date'], $video['date_source'] ?? 'sermon')
        : '';
    ob_start();
    ?>
    <section class="surfside-home-sermon" aria-labelledby="surfside-home-sermon-heading">
        <div class="surfside-home-sermon__inner">
            <header class="surfside-home-sermon__intro">
                <p class="surfside-home-sermon__eyebrow">Worship &amp; Teaching</p>
                <h2 id="surfside-home-sermon-heading">Watch a Recent Sermon</h2>
                <p>Get a feel for the biblical teaching at Surfside, catch up on a message, or explore an entire sermon series.</p>
            </header>

            <?php if ($video) : ?>
                <div class="surfside-home-sermon__feature">
                    <a class="surfside-home-sermon__art" href="<?php echo esc_url($watch_url); ?>"
                        aria-label="<?php echo esc_attr('Watch sermon: ' . $video['title']); ?>">
                        <?php if (!empty($video['thumbnail_url'])) : ?>
                            <img loading="lazy" decoding="async" alt=""
                                src="<?php echo esc_url($video['thumbnail_url']); ?>">
                        <?php else : ?>
                            <span class="surfside-home-sermon__placeholder" aria-hidden="true">Surfside Messages</span>
                        <?php endif; ?>
                        <span class="surfside-home-sermon__play" aria-hidden="true">▶</span>
                    </a>
                    <div class="surfside-home-sermon__copy">
                        <p class="surfside-home-sermon__eyebrow">Latest Sermon</p>
                        <h3><?php echo esc_html($heading['heading']); ?></h3>
                        <?php if (!empty($heading['context'])) : ?>
                            <p class="surfside-home-sermon__context"><?php echo esc_html($heading['context']); ?></p>
                        <?php endif; ?>
                        <p class="surfside-home-sermon__date"><?php echo esc_html($date_label); ?></p>
                        <div class="surfside-home-sermon__buttons">
                            <a class="surfside-home-sermon__button surfside-home-sermon__button--primary"
                                href="<?php echo esc_url($watch_url); ?>">Watch Sermon <span aria-hidden="true">→</span></a>
                            <a class="surfside-home-sermon__button surfside-home-sermon__button--outline"
                                href="<?php echo esc_url($archive_url); ?>">Browse All Messages</a>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                <div class="surfside-home-sermon__empty">
                    <p>Our messages are available to browse in the sermon archive.</p>
                    <a class="surfside-home-sermon__button surfside-home-sermon__button--primary"
                        href="<?php echo esc_url($archive_url); ?>">Browse All Messages <span aria-hidden="true">→</span></a>
                </div>
            <?php endif; ?>

            <p class="surfside-home-sermon__service-link">Looking for the full worship experience?
                <a href="<?php echo esc_url(home_url('/watch-live/')); ?>">Watch Live or catch up on a service →</a>
            </p>
        </div>
    </section>
    <style id="surfside-home-sermon-styles">
        .surfside-home-sermon{--shs-ink:#0b2546;--shs-blue:#0d568e;--shs-muted:#56677b;--shs-border:#dce5ee;color:var(--shs-ink);box-sizing:border-box;padding:clamp(28px,5vw,64px) clamp(16px,3vw,32px);background:#f4f7fa;font-family:inherit}
        .surfside-home-sermon *{box-sizing:border-box}
        .surfside-home-sermon__inner{max-width:1080px;margin:0 auto}
        .surfside-home-sermon__intro{text-align:center;max-width:760px;margin:0 auto 28px}
        .surfside-home-sermon__eyebrow{color:#176ca1!important;letter-spacing:.11em;text-transform:uppercase;font-size:12px!important;font-weight:800!important;line-height:1.4;margin:0 0 10px!important}
        .surfside-home-sermon__intro h2{font-size:clamp(30px,4vw,44px)!important;line-height:1.12!important;margin:0 0 12px!important;color:var(--shs-ink)!important;letter-spacing:-.025em}
        .surfside-home-sermon__intro>p:last-child{margin:0;color:var(--shs-muted);font-size:clamp(15px,1.6vw,18px);line-height:1.55}
        .surfside-home-sermon__feature{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);align-items:center;gap:clamp(22px,4vw,44px);background:#fff;border:1px solid var(--shs-border);border-radius:18px;padding:clamp(14px,2vw,24px);box-shadow:0 8px 24px rgba(14,43,75,.045)}
        .surfside-home-sermon__art{position:relative;display:block;width:100%;aspect-ratio:16/9;border-radius:11px;overflow:hidden;background:#102841;text-decoration:none}
        .surfside-home-sermon__art img{display:block;width:100%;height:100%;object-fit:contain}
        .surfside-home-sermon__placeholder{height:100%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:21px;font-weight:700}
        .surfside-home-sermon__play{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:60px;height:60px;border-radius:50%;background:rgba(5,26,54,.8);border:1px solid rgba(255,255,255,.6);color:#fff;display:grid;place-items:center;font-size:24px;padding-left:3px}
        .surfside-home-sermon__art:hover .surfside-home-sermon__play,.surfside-home-sermon__art:focus-visible .surfside-home-sermon__play{background:#0d568e}
        .surfside-home-sermon__copy{min-width:0}
        .surfside-home-sermon__copy h3{margin:0 0 12px!important;color:var(--shs-ink)!important;letter-spacing:-.02em;font-size:clamp(23px,2.8vw,33px)!important;line-height:1.17!important;overflow-wrap:anywhere}
        .surfside-home-sermon__context{color:#465c73;font-size:14px;line-height:1.4;margin:0 0 8px!important}
        .surfside-home-sermon__date{color:var(--shs-muted);font-size:14px;line-height:1.4;margin:0 0 24px!important}
        .surfside-home-sermon__buttons{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .surfside-home-sermon__button{display:inline-flex;align-items:center;justify-content:center;gap:7px;text-decoration:none!important;font-size:15px;font-weight:750;line-height:1.3;padding:12px 17px;border-radius:9px;border:1px solid var(--shs-blue);transition:background .15s ease}
        .surfside-home-sermon__button--primary{background:var(--shs-blue);color:#fff!important}
        .surfside-home-sermon__button--primary:hover,.surfside-home-sermon__button--primary:focus-visible{background:#0a416b;color:#fff!important}
        .surfside-home-sermon__button--outline{background:#fff;color:var(--shs-blue)!important}
        .surfside-home-sermon__button--outline:hover,.surfside-home-sermon__button--outline:focus-visible{background:#edf5fa}
        .surfside-home-sermon__service-link{margin:20px 0 0!important;text-align:center;font-size:14px;color:var(--shs-muted);line-height:1.55}
        .surfside-home-sermon__service-link a{color:var(--shs-blue)!important;font-weight:750;text-decoration:none}
        .surfside-home-sermon__service-link a:hover{text-decoration:underline}
        .surfside-home-sermon__empty{text-align:center;background:#fff;border:1px solid var(--shs-border);border-radius:16px;padding:30px}
        .surfside-home-sermon__empty p{margin:0 0 18px}
        @media(max-width:780px){.surfside-home-sermon__feature{grid-template-columns:1fr;gap:23px}.surfside-home-sermon__copy{padding:0 6px 8px}.surfside-home-sermon__intro{margin-bottom:22px}}
        @media(max-width:480px){.surfside-home-sermon__feature{padding:12px}.surfside-home-sermon__copy h3{font-size:clamp(23px,7vw,29px)!important}.surfside-home-sermon__buttons{align-items:stretch;flex-direction:column}.surfside-home-sermon__button{width:100%}.surfside-home-sermon__play{width:50px;height:50px}}
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_home_latest_sermon', 'surfside_tools_home_latest_sermon_shortcode');

/**
 * Full-page caching could otherwise keep yesterday's featured sermon on the
 * homepage after the importer finishes. Purge only when a new successful sync
 * is committed, not on visitor requests or each background import batch.
 */
function surfside_tools_home_latest_sermon_on_sync($old_value, $new_value, $option_name = '') {
    if (!is_array($new_value) || ($new_value['status'] ?? '') !== 'complete') return;

    $previous_success = is_array($old_value) ? (int) ($old_value['last_success'] ?? 0) : 0;
    $new_success = (int) ($new_value['last_success'] ?? 0);
    if (!$new_success || $new_success === $previous_success) return;

    if (function_exists('surfside_tools_purge_homepage_carousel_cache')) {
        surfside_tools_purge_homepage_carousel_cache();
    }
}
add_action('update_option_' . SURFSIDE_YT_STATE_OPTION, 'surfside_tools_home_latest_sermon_on_sync', 10, 3);
