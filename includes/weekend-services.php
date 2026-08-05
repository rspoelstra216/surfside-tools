<?php
/**
 * Plugin-rendered homepage weekend service section.
 *
 * @package SurfsideTools
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the centralized weekly service schedule for the homepage.
 *
 * @param array $attributes Shortcode attributes.
 * @return string
 */
function surfside_tools_weekend_services_shortcode($attributes = array()) {
    $attributes = shortcode_atts(
        array(
            'title' => 'Join Us This Weekend',
            'intro' => 'Come as you are and worship with us.',
        ),
        $attributes,
        'surfside_weekend_services'
    );

    $information = surfside_tools_get_site_information();
    $services = surfside_tools_site_information_services();
    $location = $information['location'] ?? array();
    $venue = trim((string) ($location['venue'] ?? ''));
    $address = surfside_tools_site_information_address($information);
    $maps_url = surfside_tools_site_information_maps_url($information);
    $heading_id = wp_unique_id('surfside-weekend-heading-');

    if (empty($services)) {
        return '';
    }

    ob_start();
    ?>
    <section class="surfside-weekend surfside-section" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
        <div class="surfside-weekend__intro">
            <h2 class="surfside-weekend__heading" id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($attributes['title']); ?></h2>
            <?php if (trim((string) $attributes['intro']) !== '') : ?>
                <p><?php echo esc_html($attributes['intro']); ?></p>
            <?php endif; ?>
        </div>

        <div class="surfside-weekend__services">
            <?php foreach ($services as $service) : ?>
                <article class="surfside-weekend__service">
                    <p class="surfside-weekend__day"><?php echo esc_html($service['day'] ?? ''); ?></p>
                    <p class="surfside-weekend__time"><?php echo esc_html($service['time'] ?? ''); ?></p>
                    <?php if (!empty($service['label'])) : ?>
                        <p class="surfside-weekend__detail"><?php echo esc_html($service['label']); ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($venue !== '' || $address !== '') : ?>
            <div class="surfside-weekend__location">
                <?php if ($venue !== '') : ?>
                    <strong><?php echo esc_html($venue); ?></strong>
                <?php endif; ?>
                <?php if ($address !== '') : ?>
                    <?php if ($maps_url !== '') : ?>
                        <a href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($address); ?></a>
                    <?php else : ?>
                        <span><?php echo esc_html($address); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_weekend_services', 'surfside_tools_weekend_services_shortcode');
