<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue footer styles before WordPress prints the document head.
 * Site Editor template shortcodes render too late for conditional enqueueing.
 */
function surfside_tools_footer_assets() {
    wp_enqueue_style(
        'surfside-tools-footer',
        SURFSIDE_TOOLS_URL . 'assets/css/footer.css',
        array('surfside-tools-design-system'),
        defined('SURFSIDE_TOOLS_VERSION') ? SURFSIDE_TOOLS_VERSION : '2.3.1'
    );
}
add_action('wp_enqueue_scripts', 'surfside_tools_footer_assets', 6);

function surfside_tools_footer_social_icon($network) {
    $icons = array(
        'facebook' => '<path d="M14 8.5h3V5h-3c-3.3 0-5.5 2.1-5.5 5.8V13H5v4h3.5v8H13v-8h3.4l.6-4H13v-2c0-1.7.5-2.5 1-2.5Z"/>',
        'youtube' => '<path d="M27.4 8.1a3.5 3.5 0 0 0-2.5-2.5C22.7 5 16 5 16 5s-6.7 0-8.9.6a3.5 3.5 0 0 0-2.5 2.5C4 10.3 4 15 4 15s0 4.7.6 6.9a3.5 3.5 0 0 0 2.5 2.5c2.2.6 8.9.6 8.9.6s6.7 0 8.9-.6a3.5 3.5 0 0 0 2.5-2.5c.6-2.2.6-6.9.6-6.9s0-4.7-.6-6.9ZM13 19.3v-8.6l7.5 4.3-7.5 4.3Z"/>',
        'instagram' => '<path d="M21.5 4h-11A6.5 6.5 0 0 0 4 10.5v11a6.5 6.5 0 0 0 6.5 6.5h11a6.5 6.5 0 0 0 6.5-6.5v-11A6.5 6.5 0 0 0 21.5 4Zm3.9 17.5a3.9 3.9 0 0 1-3.9 3.9h-11a3.9 3.9 0 0 1-3.9-3.9v-11a3.9 3.9 0 0 1 3.9-3.9h11a3.9 3.9 0 0 1 3.9 3.9v11ZM16 10a6 6 0 1 0 0 12 6 6 0 0 0 0-12Zm0 9.4a3.4 3.4 0 1 1 0-6.8 3.4 3.4 0 0 1 0 6.8Zm7.5-9.7a1.4 1.4 0 1 1-2.8 0 1.4 1.4 0 0 1 2.8 0Z"/>',
    );

    if (!isset($icons[$network])) {
        return '';
    }

    return '<svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">' . $icons[$network] . '</svg>';
}

function surfside_tools_footer_shortcode() {
    $information = surfside_tools_get_site_information();
    $identity = $information['identity'] ?? array();
    $location = $information['location'] ?? array();
    $navigation = $information['navigation'] ?? array();
    $social = $information['social'] ?? array();
    $services = surfside_tools_site_information_services();
    $maps_url = surfside_tools_site_information_maps_url($information);
    $address = surfside_tools_site_information_address($information);
    $contact_url = surfside_tools_site_information_url($identity['contact_url'] ?? '');
    $phone = trim((string) ($identity['phone'] ?? ''));
    $phone_href = preg_replace('/[^0-9+]/', '', $phone);
    $logo_url = surfside_tools_site_information_logo_url($information);

    ob_start();
    ?>
    <footer class="surfside-site-footer surfside-section" aria-label="Site footer">
        <div class="surfside-site-footer__accent" aria-hidden="true"></div>
        <div class="surfside-site-footer__inner">
            <div class="surfside-site-footer__brand">
                <a class="surfside-site-footer__logo-link" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(($identity['name'] ?? 'Surfside Community Fellowship') . ' home'); ?>">
                    <img class="surfside-site-footer__logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($identity['name'] ?? 'Surfside Community Fellowship'); ?>">
                </a>
                <?php if (!empty($identity['tagline'])) : ?>
                    <p class="surfside-site-footer__tagline"><?php echo esc_html($identity['tagline']); ?></p>
                <?php endif; ?>
                <?php if (!empty($social)) : ?>
                    <ul class="surfside-site-footer__social" aria-label="Follow Surfside">
                        <?php foreach ($social as $network => $link) :
                            $url = surfside_tools_site_information_url($link['url'] ?? '');
                            if ($url === '') {
                                continue;
                            }
                            $label = $link['label'] ?? ucfirst($network);
                            ?>
                            <li><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($label); ?>"><?php echo surfside_tools_footer_social_icon($network); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php echo esc_html($label); ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <nav class="surfside-site-footer__section" aria-label="Footer navigation">
                <h2>Explore</h2>
                <ul class="surfside-site-footer__links">
                    <?php foreach ($navigation as $link) :
                        $url = surfside_tools_site_information_url($link['url'] ?? '');
                        if ($url === '') {
                            continue;
                        }
                        ?>
                        <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($link['label'] ?? ''); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <section class="surfside-site-footer__section">
                <h2>Service Times</h2>
                <?php if (!empty($services)) : ?>
                    <ul class="surfside-site-footer__services">
                        <?php foreach ($services as $service) : ?>
                            <li><span><?php echo esc_html($service['day']); ?></span><strong><?php echo esc_html($service['time']); ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="surfside-site-footer__section surfside-site-footer__connect">
                <h2>Visit &amp; Connect</h2>
                <?php if (!empty($location['venue'])) : ?><p><strong><?php echo esc_html($location['venue']); ?></strong></p><?php endif; ?>
                <?php if ($address !== '') : ?>
                    <p><a href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($address); ?><span aria-hidden="true"> ↗</span></a></p>
                <?php endif; ?>
                <?php if ($phone !== '') : ?><p><a href="tel:<?php echo esc_attr($phone_href); ?>"><?php echo esc_html($phone); ?></a></p><?php endif; ?>
                <?php if ($contact_url !== '') : ?><p><a class="surfside-site-footer__contact" href="<?php echo esc_url($contact_url); ?>">Contact Us</a></p><?php endif; ?>
            </section>
        </div>
        <div class="surfside-site-footer__legal">
            <p>&copy; <?php echo esc_html(wp_date('Y')); ?> <?php echo esc_html($identity['name'] ?? 'Surfside Community Fellowship'); ?>.</p>
        </div>
    </footer>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_footer', 'surfside_tools_footer_shortcode');
