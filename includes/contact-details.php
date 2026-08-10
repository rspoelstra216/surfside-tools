<?php
/**
 * Centralized contact and visit details for the Contact page.
 *
 * @package SurfsideTools
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render contact, service, location, and map information.
 *
 * @return string
 */
function surfside_tools_contact_details_shortcode() {
    $information = surfside_tools_get_site_information();
    $identity = (array) ($information['identity'] ?? array());
    $location = (array) ($information['location'] ?? array());
    $services = surfside_tools_site_information_services();

    $name = trim((string) ($identity['name'] ?? ''));
    $phone = trim((string) ($identity['phone'] ?? ''));
    $email = sanitize_email($identity['email'] ?? '');
    $venue = trim((string) ($location['venue'] ?? ''));
    $address = surfside_tools_site_information_address($information);
    $maps_url = surfside_tools_site_information_maps_url($information);
    $map_embed_url = $address !== ''
        ? 'https://www.google.com/maps?q=' . rawurlencode($address) . '&output=embed'
        : '';
    $phone_url = $phone !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $phone) : '';

    ob_start();
    ?>
    <section class="surfside-contact-details" aria-label="Contact and visit information">
        <div class="surfside-contact-details__cards">
            <article class="surfside-contact-details__card">
                <h2>Visit Us</h2>
                <?php if ($name !== '') : ?><p><strong><?php echo esc_html($name); ?></strong></p><?php endif; ?>
                <?php if ($venue !== '') : ?><p>Meeting at <?php echo esc_html($venue); ?></p><?php endif; ?>
                <?php if ($address !== '') : ?><address><?php echo esc_html($address); ?></address><?php endif; ?>
                <?php if ($maps_url !== '') : ?><p class="surfside-contact-details__action"><a class="surfside-button" href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer">Get Directions</a></p><?php endif; ?>
            </article>

            <article class="surfside-contact-details__card">
                <h2>Service Times</h2>
                <div class="surfside-contact-details__services">
                    <?php foreach ($services as $service) : ?>
                        <p class="surfside-contact-details__service">
                            <strong><?php echo esc_html($service['day'] ?? ''); ?></strong>
                            <?php echo esc_html($service['time'] ?? ''); ?>
                            <?php if (!empty($service['label'])) : ?><br><span><?php echo esc_html($service['label']); ?></span><?php endif; ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="surfside-contact-details__card">
                <h2>Contact Us</h2>
                <?php if ($phone !== '') : ?>
                    <p><strong>Phone</strong><br><a href="<?php echo esc_url($phone_url); ?>"><?php echo esc_html($phone); ?></a></p>
                <?php endif; ?>
                <?php if ($email !== '') : ?>
                    <p><strong>Email</strong><br><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p>
                <?php endif; ?>
            </article>
        </div>

        <?php if ($map_embed_url !== '') : ?>
            <div class="surfside-contact-details__map">
                <iframe
                    src="<?php echo esc_url($map_embed_url); ?>"
                    title="Map showing <?php echo esc_attr($name !== '' ? $name : 'Surfside Community Fellowship'); ?>"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                ></iframe>
            </div>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_contact_details', 'surfside_tools_contact_details_shortcode');
