<?php
/**
 * Plugin-rendered Ready to Visit homepage section.
 *
 * @package SurfsideTools
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render a visit call-to-action from canonical site information.
 *
 * @param array $attributes Shortcode attributes.
 * @return string
 */
function surfside_tools_ready_to_visit_shortcode($attributes = array()) {
    $attributes = shortcode_atts(
        array(
            'title' => 'Ready to Visit?',
            'intro' => 'We’d love to meet you this weekend.',
        ),
        $attributes,
        'surfside_ready_to_visit'
    );

    $information = surfside_tools_get_site_information();
    $services = surfside_tools_site_information_services();
    $location = $information['location'] ?? array();
    $venue = trim((string) ($location['venue'] ?? ''));
    $address = surfside_tools_site_information_address($information);
    $maps_url = surfside_tools_site_information_maps_url($information);
    $plan_url = home_url('/plan-your-visit/');

    foreach ((array) ($information['navigation'] ?? array()) as $link) {
        $key = sanitize_key($link['key'] ?? '');
        $label = strtolower(trim((string) ($link['label'] ?? '')));
        if ($key === 'plan-visit' || $label === 'plan your visit') {
            $candidate = surfside_tools_site_information_navigation_url($link);
            if ($candidate !== '') {
                $plan_url = $candidate;
            }
            break;
        }
    }

    if (empty($services) && $venue === '' && $address === '') {
        return '';
    }

    $heading_id = wp_unique_id('surfside-ready-visit-heading-');

    ob_start();
    ?>
    <section class="surfside-ready-visit surfside-section surfside-reveal" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
        <div class="surfside-ready-visit__inner">
            <div class="surfside-ready-visit__intro">
                <p class="surfside-ready-visit__eyebrow">Your First Weekend</p>
                <h2 class="surfside-ready-visit__heading" id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($attributes['title']); ?></h2>
                <?php if (trim((string) $attributes['intro']) !== '') : ?>
                    <p class="surfside-ready-visit__lede"><?php echo esc_html($attributes['intro']); ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($services)) : ?>
                <ul class="surfside-ready-visit__services" aria-label="Weekly service times">
                    <?php foreach ($services as $service) : ?>
                        <li>
                            <span><?php echo esc_html($service['day'] ?? ''); ?></span>
                            <strong><?php echo esc_html($service['time'] ?? ''); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($venue !== '' || $address !== '') : ?>
                <div class="surfside-ready-visit__location">
                    <span>Currently Meeting At</span>
                    <?php if ($venue !== '') : ?>
                        <strong><?php echo esc_html($venue); ?></strong>
                    <?php endif; ?>
                    <?php if ($address !== '') : ?>
                        <?php if ($maps_url !== '') : ?>
                            <a href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($address); ?></a>
                        <?php else : ?>
                            <p><?php echo esc_html($address); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="surfside-ready-visit__actions">
                <a class="surfside-ready-visit__button surfside-ready-visit__button--primary" href="<?php echo esc_url($plan_url); ?>">Plan Your Visit</a>
                <?php if ($maps_url !== '') : ?>
                    <a class="surfside-ready-visit__button surfside-ready-visit__button--secondary" href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer">Get Directions</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_ready_to_visit', 'surfside_tools_ready_to_visit_shortcode');
