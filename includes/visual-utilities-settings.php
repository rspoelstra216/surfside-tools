<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optional CSS overrides for reveal and countdown utilities.
 */
function surfside_tools_visual_custom_css_option() {
    return 'surfside_tools_visual_custom_css';
}

function surfside_tools_visual_default_css_reference() {
    return <<<'CSS'
/* Reveal on scroll */
body:not(.wp-admin) .surfside-reveal {
    opacity: 0;
    transform: translateY(16px);
    transition: opacity 700ms ease, transform 700ms ease;
}

body:not(.wp-admin) .surfside-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
}

body:not(.wp-admin) .surfside-reveal.surfside-delay-1 { transition-delay: 0.1s; }
body:not(.wp-admin) .surfside-reveal.surfside-delay-2 { transition-delay: 0.5s; }
body:not(.wp-admin) .surfside-reveal.surfside-delay-3 { transition-delay: 0.75s; }
body:not(.wp-admin) .surfside-reveal.surfside-delay-4 { transition-delay: 1s; }
body:not(.wp-admin) .surfside-reveal.surfside-delay-5 { transition-delay: 1.25s; }
body:not(.wp-admin) .surfside-reveal.surfside-delay-6 { transition-delay: 1.5s; }
body:not(.wp-admin) .surfside-reveal.surfside-delay-7 { transition-delay: 1.75s; }

.wp-admin .surfside-reveal,
.editor-styles-wrapper .surfside-reveal,
.block-editor-page .surfside-reveal,
.interface-interface-skeleton .surfside-reveal {
    opacity: 1 !important;
    transform: none !important;
    transition: none !important;
}

/* Full countdown */
.surfside-countdown {
    text-align: center;
    padding: 28px 20px;
    border-radius: 18px;
    background: #f5f5f8;
    max-width: 760px;
    margin: 24px auto;
}

.surfside-countdown-label {
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
    opacity: 0.75;
    margin-bottom: 6px;
}

.surfside-countdown-service {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 18px;
}

.surfside-countdown-timer {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.surfside-countdown-timer span {
    background: #fff;
    border-radius: 14px;
    padding: 16px 8px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
}

.surfside-countdown-timer strong {
    display: block;
    font-size: clamp(1.6rem, 5vw, 2.6rem);
    line-height: 1;
}

.surfside-countdown-timer small {
    display: block;
    margin-top: 6px;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    opacity: 0.7;
}

.surfside-live-now {
    background: #f2f2fa;
    color: #fff !important;
}

.surfside-is-live,
.surfside-is-live a,
.surfside-is-live span,
.surfside-is-live div {
    color: #fff !important;
}

/* Compact countdown */
.surfside-compact-countdown {
    margin-top: 18px;
    text-align: center;
    color: #fff;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.45);
}

.surfside-next-service-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    opacity: 0.85;
    margin-bottom: 2px;
}

.surfside-next-service {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 4px;
}

.surfside-compact-time {
    font-size: 0.95rem;
    font-weight: 500;
}

/* Sunday countdown */
.surfside-sunday-countdown {
    margin: 18px 0 24px;
    text-align: left;
    color: inherit;
}

.surfside-sunday-countdown .surfside-next-service-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    opacity: 0.75;
    margin-bottom: 4px;
}

.surfside-sunday-countdown .surfside-next-service {
    font-size: 1.35rem;
    font-weight: 700;
    margin-bottom: 6px;
}

.surfside-sunday-countdown .surfside-compact-time {
    font-size: 1rem;
    font-weight: 500;
}

@media (max-width: 600px) {
    .surfside-countdown-timer {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (prefers-reduced-motion: reduce) {
    body:not(.wp-admin) .surfside-reveal {
        opacity: 1;
        transform: none;
        transition: none;
    }
}
CSS;
}

function surfside_tools_sanitize_custom_css($css) {
    $css = wp_unslash((string) $css);
    $css = str_replace(array('</style', '<style'), '', $css);
    $css = preg_replace('/@import\s+[^;]+;?/i', '', $css);
    $css = preg_replace('/(?:javascript\s*:|expression\s*\()/i', '', $css);
    return trim($css);
}

function surfside_tools_handle_visual_css_settings() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['surfside_visual_css_action'])) {
        return;
    }

    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return;
    }

    $action = sanitize_key(wp_unslash($_POST['surfside_visual_css_action']));
    if ($action !== 'save') {
        return;
    }

    $nonce = isset($_POST['surfside_visual_css_nonce']) ? sanitize_text_field(wp_unslash($_POST['surfside_visual_css_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'surfside_visual_css_settings')) {
        return;
    }

    $css = isset($_POST['surfside_visual_custom_css']) ? surfside_tools_sanitize_custom_css($_POST['surfside_visual_custom_css']) : '';
    update_option(surfside_tools_visual_custom_css_option(), $css, false);

    if (function_exists('surfside_tools_purge_cache')) {
        surfside_tools_purge_cache();
    }

    $redirect = wp_get_referer() ?: home_url('/dashboard/settings/');
    wp_safe_redirect(add_query_arg('visual_css_saved', '1', $redirect));
    exit;
}
add_action('template_redirect', 'surfside_tools_handle_visual_css_settings', 5);

function surfside_tools_enqueue_visual_custom_css() {
    $css = (string) get_option(surfside_tools_visual_custom_css_option(), '');
    if ($css === '') {
        return;
    }

    if (!wp_style_is('surfside-tools-visual-utilities', 'enqueued')) {
        wp_register_style('surfside-tools-visual-utilities', false, array(), SURFSIDE_TOOLS_VERSION);
        wp_enqueue_style('surfside-tools-visual-utilities');
    }

    wp_add_inline_style('surfside-tools-visual-utilities', $css);
}
add_action('wp_enqueue_scripts', 'surfside_tools_enqueue_visual_custom_css', 30);

function surfside_tools_visual_css_settings_panel() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return '';
    }

    $css = (string) get_option(surfside_tools_visual_custom_css_option(), '');
    $default_css = surfside_tools_visual_default_css_reference();
    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-visual-css-settings-shell">
        <?php if (isset($_GET['visual_css_saved'])) : ?>
            <div class="surfside-front-settings-notice surfside-front-settings-success">Visual CSS saved.</div>
        <?php endif; ?>
        <section class="surfside-front-settings-card surfside-visual-css-settings-card">
            <h2>Reveal &amp; Countdown Styling</h2>
            <p class="surfside-staff-muted">Add only the rules you want to change. These overrides load after the built-in Surfside Tools styles. Leave this blank to use the defaults.</p>
            <form method="post">
                <?php wp_nonce_field('surfside_visual_css_settings', 'surfside_visual_css_nonce'); ?>
                <input type="hidden" name="surfside_visual_css_action" value="save">
                <label for="surfside-visual-custom-css"><strong>Custom CSS overrides</strong></label>
                <textarea id="surfside-visual-custom-css" name="surfside_visual_custom_css" rows="16" spellcheck="false" placeholder="/* Change the full countdown background */&#10;.surfside-countdown {&#10;    background: #f5f5f8;&#10;}&#10;&#10;/* Speed up reveal animations */&#10;.surfside-reveal {&#10;    transition-duration: 500ms !important;&#10;}"><?php echo esc_textarea($css); ?></textarea>
                <details class="surfside-visual-css-reference">
                    <summary><strong>Common selectors</strong></summary>
                    <p><code>.surfside-reveal</code>, <code>.surfside-delay-1</code> through <code>.surfside-delay-7</code>, <code>.surfside-countdown</code>, <code>.surfside-countdown-label</code>, <code>.surfside-countdown-service</code>, <code>.surfside-countdown-timer</code>, <code>.surfside-compact-countdown</code>, and <code>.surfside-sunday-countdown</code>.</p>
                </details>
                <details class="surfside-visual-css-reference surfside-visual-default-css">
                    <summary><strong>View current built-in CSS</strong></summary>
                    <p class="surfside-front-description">This is read-only. Copy the rule you want to change into the override box above, then edit only that copy.</p>
                    <pre><code><?php echo esc_html($default_css); ?></code></pre>
                </details>
                <p><button type="submit" class="surfside-front-primary-button">Save Visual CSS</button></p>
            </form>
        </section>
    </div>
    <style>
        .surfside-visual-css-settings-shell{margin-top:0}.surfside-visual-css-settings-card textarea{display:block;width:100%;min-height:300px;margin:10px 0 14px;padding:14px;border:1px solid #9aa9b8;border-radius:9px;background:#101827;color:#e6edf7;font:14px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;tab-size:4}.surfside-visual-css-reference{margin:12px 0 18px}.surfside-visual-css-reference code{display:inline-block;margin:4px 2px;padding:2px 5px;border-radius:4px;background:#edf3f8}.surfside-visual-default-css pre{max-height:520px;overflow:auto;margin:12px 0 0;padding:16px;border-radius:9px;background:#101827;color:#e6edf7;white-space:pre;font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}.surfside-visual-default-css pre code{display:block;margin:0;padding:0;background:transparent;color:inherit}
    </style>
    <?php
    return ob_get_clean();
}

add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'surfside_staff_settings') {
        return $output;
    }

    return $output . surfside_tools_visual_css_settings_panel();
}, 20, 2);
