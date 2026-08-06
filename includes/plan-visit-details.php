<?php
/**
 * Plugin-rendered visit details for the Plan Your Visit page.
 *
 * @package SurfsideTools
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the centralized weekly schedule and meeting location.
 *
 * @param array $attributes Shortcode attributes.
 * @return string
 */
function surfside_tools_plan_visit_details_shortcode($attributes = array()) {
    $attributes = shortcode_atts(
        array(
            'title' => 'When & Where We Meet',
            'intro' => 'Choose the service that works best for you. We look forward to welcoming you.',
        ),
        $attributes,
        'surfside_visit_details'
    );

    $information = surfside_tools_get_site_information();
    $services = surfside_tools_site_information_services();
    $location = $information['location'] ?? array();
    $venue = trim((string) ($location['venue'] ?? ''));
    $address = surfside_tools_site_information_address($information);
    $maps_url = surfside_tools_site_information_maps_url($information);
    $heading_id = wp_unique_id('surfside-visit-details-heading-');

    if (empty($services) && $venue === '' && $address === '') {
        return '';
    }

    ob_start();
    ?>
    <section class="surfside-visit-details surfside-reveal" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
        <div class="surfside-visit-details__inner">
            <div class="surfside-visit-details__intro">
                <h2 id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($attributes['title']); ?></h2>
                <?php if (trim((string) $attributes['intro']) !== '') : ?>
                    <p><?php echo esc_html($attributes['intro']); ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($services)) : ?>
                <div class="surfside-visit-details__services">
                    <?php foreach ($services as $service) : ?>
                        <article class="surfside-visit-details__service">
                            <p class="surfside-visit-details__day"><?php echo esc_html($service['day'] ?? ''); ?></p>
                            <p class="surfside-visit-details__time"><?php echo esc_html($service['time'] ?? ''); ?></p>
                            <?php if (!empty($service['label'])) : ?>
                                <p class="surfside-visit-details__label"><?php echo esc_html($service['label']); ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($venue !== '' || $address !== '') : ?>
                <div class="surfside-visit-details__location">
                    <p class="surfside-visit-details__eyebrow">Currently meeting at</p>
                    <?php if ($venue !== '') : ?>
                        <h3><?php echo esc_html($venue); ?></h3>
                    <?php endif; ?>
                    <?php if ($address !== '') : ?>
                        <p><?php echo esc_html($address); ?></p>
                    <?php endif; ?>
                    <?php if ($maps_url !== '') : ?>
                        <a class="surfside-visit-details__button" href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer">Get Directions</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_visit_details', 'surfside_tools_plan_visit_details_shortcode');
