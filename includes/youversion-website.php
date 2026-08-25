<?php
/**
 * Public website Scripture viewer powered by the existing Surfside Bible REST API.
 */
if (!defined('ABSPATH')) { exit; }

add_action('wp_enqueue_scripts', 'surfside_tools_youversion_website_assets');
add_action('wp_footer', 'surfside_tools_youversion_website_dialog');

function surfside_tools_youversion_website_assets() {
    if (is_admin()) {
        return;
    }

    wp_enqueue_style(
        'surfside-youversion-website',
        SURFSIDE_TOOLS_URL . 'assets/css/youversion-website.css',
        array(),
        SURFSIDE_TOOLS_VERSION
    );

    wp_enqueue_script(
        'surfside-youversion-website',
        SURFSIDE_TOOLS_URL . 'assets/js/youversion-website.js',
        array(),
        SURFSIDE_TOOLS_VERSION,
        true
    );

    wp_enqueue_script(
        'surfside-youversion-website-version-picker',
        SURFSIDE_TOOLS_URL . 'assets/js/youversion-website-version-picker.js',
        array('surfside-youversion-website'),
        SURFSIDE_TOOLS_VERSION,
        true
    );

    wp_localize_script('surfside-youversion-website', 'surfsideBibleWebsite', array(
        'passageEndpoint' => esc_url_raw(rest_url('surfside/v1/bible/passage')),
        'versionsEndpoint' => esc_url_raw(rest_url('surfside/v1/bible/versions')),
        'defaultVersion' => 'NIV',
    ));
}

function surfside_tools_youversion_website_dialog() {
    if (is_admin()) {
        return;
    }
    ?>
    <div class="surfside-scripture-dialog" data-surfside-scripture-dialog hidden>
        <div class="surfside-scripture-dialog__backdrop" data-scripture-close></div>
        <section class="surfside-scripture-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="surfside-scripture-title" tabindex="-1">
            <header class="surfside-scripture-dialog__header">
                <div>
                    <span class="surfside-scripture-dialog__eyebrow">Scripture</span>
                    <h2 id="surfside-scripture-title" data-scripture-title>Scripture</h2>
                </div>
                <button type="button" class="surfside-scripture-dialog__close" data-scripture-close aria-label="Close Scripture">Close</button>
            </header>
            <div class="surfside-scripture-dialog__body">
                <div class="surfside-scripture-dialog__version-block">
                    <div class="surfside-scripture-dialog__version-row">
                        <label class="surfside-scripture-dialog__version-picker">
                            <span class="screen-reader-text">Bible version</span>
                            <select data-scripture-version-select disabled>
                                <option value="NIV">NIV — New International Version</option>
                            </select>
                        </label>
                        <div class="surfside-scripture-dialog__version" data-scripture-version>NIV · New International Version</div>
                    </div>
                    <div class="surfside-scripture-dialog__version-hint">Click for more translations</div>
                </div>
                <div class="surfside-scripture-dialog__status" data-scripture-status>Loading Scripture…</div>
                <div class="surfside-scripture-dialog__content" data-scripture-content hidden></div>
                <div class="surfside-scripture-dialog__attribution" data-scripture-attribution hidden></div>
                <a class="surfside-scripture-dialog__youversion" data-scripture-link href="#" target="_blank" rel="noopener" hidden>Explore More in YouVersion ↗</a>
            </div>
        </section>
    </div>
    <?php
}
