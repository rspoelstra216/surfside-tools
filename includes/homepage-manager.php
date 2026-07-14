<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Homepage carousel and front-end photo management.
 *
 * The existing [surfside_photo_carousel] shortcode is preserved so the
 * homepage does not need to be rebuilt during migration from ACF.
 */

function surfside_tools_homepage_image_option() {
    return 'surfside_tools_homepage_carousel_images';
}

function surfside_tools_homepage_normalize_images($images) {
    $normalized = array();

    foreach ((array) $images as $image) {
        $id = is_array($image) ? absint($image['id'] ?? 0) : absint($image);
        if (!$id || !wp_attachment_is_image($id)) {
            continue;
        }

        $normalized[] = array(
            'id' => $id,
            'updated' => is_array($image) ? absint($image['updated'] ?? 0) : 0,
        );
    }

    return array_slice($normalized, 0, 30);
}

function surfside_tools_homepage_acf_image_id($value) {
    if (is_array($value)) {
        return absint($value['ID'] ?? $value['id'] ?? 0);
    }

    if (is_numeric($value)) {
        return absint($value);
    }

    if (is_string($value) && $value !== '') {
        return absint(attachment_url_to_postid($value));
    }

    return 0;
}

function surfside_tools_homepage_maybe_import_acf() {
    $option_name = surfside_tools_homepage_image_option();
    $existing = get_option($option_name, null);

    if ($existing !== null) {
        return surfside_tools_homepage_normalize_images($existing);
    }

    $imported = array();

    if (function_exists('get_field')) {
        $page_id = 552;
        $fields = array('carousel_image');
        for ($i = 2; $i <= 30; $i++) {
            $fields[] = 'carousel_image_' . $i;
        }

        foreach ($fields as $field) {
            $id = surfside_tools_homepage_acf_image_id(get_field($field, $page_id));
            if ($id && wp_attachment_is_image($id)) {
                $imported[] = array(
                    'id' => $id,
                    'updated' => absint(get_post_meta($page_id, '_surfside_updated_' . $field, true)),
                );
            }
        }
    }

    update_option($option_name, $imported, false);
    update_option('surfside_tools_homepage_acf_import_complete', current_time('mysql'), false);

    return $imported;
}

function surfside_tools_homepage_get_images() {
    return surfside_tools_homepage_maybe_import_acf();
}

function surfside_tools_homepage_enqueue_carousel_styles() {
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $loaded = true;
    wp_register_style('surfside-tools-homepage-carousel', false, array(), SURFSIDE_TOOLS_VERSION);
    wp_enqueue_style('surfside-tools-homepage-carousel');
    wp_add_inline_style('surfside-tools-homepage-carousel', '
        .surfside-scroll-carousel{width:100%;max-width:1100px;margin:0 auto 40px;overflow:hidden}
        .surfside-scroll-track{display:flex;gap:18px;width:max-content;will-change:transform}
        .surfside-scroll-slide{flex:0 0 420px;height:280px;border-radius:18px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,.12);background:#f5f5f8}
        .surfside-scroll-slide img{width:100%;height:100%;object-fit:cover;object-position:center;display:block}
        @media(max-width:768px){.surfside-scroll-slide{flex-basis:82vw;height:240px}}
        @media(prefers-reduced-motion:reduce){.surfside-scroll-track{transform:none!important}}
    ');
}

function surfside_tools_photo_carousel_shortcode() {
    $images = surfside_tools_homepage_get_images();
    if (!$images) {
        return '';
    }

    surfside_tools_homepage_enqueue_carousel_styles();
    $carousel_id = 'surfside-scroll-carousel-' . wp_unique_id();
    $slides = '';

    foreach ($images as $image) {
        $id = absint($image['id']);
        $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
        if ($alt === '') {
            $alt = 'Surfside Community Fellowship photo';
        }
        $html = wp_get_attachment_image($id, 'large', false, array('alt' => $alt, 'loading' => 'lazy'));
        if ($html) {
            $slides .= '<div class="surfside-scroll-slide">' . $html . '</div>';
        }
    }

    if ($slides === '') {
        return '';
    }

    $output = '<div id="' . esc_attr($carousel_id) . '" class="surfside-scroll-carousel" aria-label="Church photo carousel">';
    $output .= '<div class="surfside-scroll-track">' . $slides . $slides . '</div></div>';
    $output .= '<script>(function(){function start(){var c=document.getElementById(' . wp_json_encode($carousel_id) . ');if(!c||c.dataset.surfsideStarted)return;c.dataset.surfsideStarted="1";var t=c.querySelector(".surfside-scroll-track");if(!t)return;if(window.matchMedia&&window.matchMedia("(prefers-reduced-motion: reduce)").matches)return;var p=0,s=.4;function a(){p-=s;if(Math.abs(p)>=t.scrollWidth/2)p=0;t.style.transform="translateX("+p+"px)";window.requestAnimationFrame(a)}a()}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",start);else start();})();</script>';

    return $output;
}

add_action('init', function () {
    remove_shortcode('surfside_photo_carousel');
    add_shortcode('surfside_photo_carousel', 'surfside_tools_photo_carousel_shortcode');
}, 30);

function surfside_tools_homepage_upload_file($field_name, $parent_id = 0) {
    if (empty($_FILES[$field_name]['name'])) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $id = media_handle_upload($field_name, $parent_id);
    return is_wp_error($id) ? $id : absint($id);
}

function surfside_tools_homepage_upload_multiple($field_name, $parent_id = 0) {
    if (empty($_FILES[$field_name]['name']) || !is_array($_FILES[$field_name]['name'])) {
        return array();
    }

    $uploaded = array();
    $files = $_FILES[$field_name];
    $count = count($files['name']);

    for ($i = 0; $i < $count; $i++) {
        if (empty($files['name'][$i])) {
            continue;
        }

        $_FILES['surfside_homepage_single_upload'] = array(
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        );

        $result = surfside_tools_homepage_upload_file('surfside_homepage_single_upload', $parent_id);
        if (!is_wp_error($result) && $result) {
            $uploaded[] = $result;
        }
    }

    unset($_FILES['surfside_homepage_single_upload']);
    return $uploaded;
}

function surfside_tools_homepage_handle_post($images) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['surfside_homepage_action'])) {
        return array($images, '');
    }

    if (!current_user_can('upload_files')) {
        return array($images, '<div class="surfside-homepage-notice error">You do not have permission to update homepage photos.</div>');
    }

    $nonce = isset($_POST['surfside_homepage_nonce']) ? sanitize_text_field(wp_unslash($_POST['surfside_homepage_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'surfside_homepage_update')) {
        return array($images, '<div class="surfside-homepage-notice error">Security check failed. Please refresh and try again.</div>');
    }

    $by_id = array();
    foreach ($images as $image) {
        $by_id[absint($image['id'])] = $image;
    }

    $remove = array_map('absint', (array) ($_POST['remove_images'] ?? array()));
    $order = array_map('absint', (array) ($_POST['image_order'] ?? array()));
    $updated = array();

    foreach ($order as $id) {
        if (!$id || isset($remove[$id]) || in_array($id, $remove, true) || !isset($by_id[$id])) {
            continue;
        }

        $replacement_field = 'replace_image_' . $id;
        $replacement = surfside_tools_homepage_upload_file($replacement_field);
        if (is_wp_error($replacement)) {
            continue;
        }

        if ($replacement) {
            $updated[] = array('id' => $replacement, 'updated' => current_time('timestamp'));
        } else {
            $updated[] = $by_id[$id];
        }
    }

    foreach (surfside_tools_homepage_upload_multiple('new_images') as $id) {
        if (count($updated) >= 30) {
            break;
        }
        $updated[] = array('id' => $id, 'updated' => current_time('timestamp'));
    }

    $updated = surfside_tools_homepage_normalize_images($updated);
    update_option(surfside_tools_homepage_image_option(), $updated, false);

    return array($updated, '<div class="surfside-homepage-notice success">Homepage photos updated successfully.</div>');
}

function surfside_tools_homepage_manager_styles() {
    wp_register_style('surfside-tools-homepage-manager', false, array(), SURFSIDE_TOOLS_VERSION);
    wp_enqueue_style('surfside-tools-homepage-manager');
    wp_add_inline_style('surfside-tools-homepage-manager', '
        .surfside-homepage-manager{max-width:100%}.surfside-homepage-notice{padding:14px 16px;border-radius:10px;margin:0 0 20px;font-weight:700}.surfside-homepage-notice.success{background:#edf9f0;border:1px solid #9bd2a6;color:#17682e}.surfside-homepage-notice.error{background:#fff0f0;border:1px solid #e7aaaa;color:#9b2020}
        .surfside-homepage-intro{margin-bottom:22px;color:#4b5872}.surfside-homepage-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin:20px 0}.surfside-homepage-photo{border:1px solid rgba(7,27,58,.14);border-radius:16px;padding:14px;background:#fff;box-shadow:0 6px 18px rgba(7,27,58,.05);cursor:grab}.surfside-homepage-photo.dragging{opacity:.5}.surfside-homepage-photo img{display:block;width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:11px;background:#eef2f6}.surfside-homepage-photo-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:10px}.surfside-homepage-photo-head strong{font-size:16px}.surfside-homepage-photo-controls{display:grid;gap:10px;margin-top:12px}.surfside-homepage-photo input[type=file]{max-width:100%}.surfside-homepage-remove{display:flex;gap:8px;align-items:center;color:#9b2020;font-weight:700}.surfside-homepage-add{border:1px dashed #9fb3c9;border-radius:14px;padding:20px;background:#f8fbff;margin:18px 0}.surfside-homepage-add label{display:block;font-weight:800;margin-bottom:8px}.surfside-homepage-actions{display:flex;gap:14px;align-items:center;flex-wrap:wrap}.surfside-homepage-save{border:0;border-radius:8px;background:#0b4f9c;color:#fff;font-weight:800;padding:13px 22px;cursor:pointer}.surfside-homepage-hint{font-size:13px;color:#5d687d}.surfside-homepage-drag{font-size:13px;color:#5d687d}
        @media(max-width:760px){.surfside-homepage-grid{grid-template-columns:1fr}}
    ');
}

function surfside_tools_staff_homepage_shortcode() {
    if (function_exists('surfside_tools_prevent_cache')) {
        surfside_tools_prevent_cache();
    }
    if (function_exists('surfside_tools_staff_enqueue_styles')) {
        surfside_tools_staff_enqueue_styles();
    }

    if (!is_user_logged_in()) {
        return function_exists('surfside_tools_staff_login_box') ? surfside_tools_staff_login_box('Please log in to manage homepage photos.') : '<p>Please log in.</p>';
    }
    if (!current_user_can('upload_files')) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to manage homepage photos.</p></div>';
    }

    surfside_tools_homepage_manager_styles();
    $images = surfside_tools_homepage_get_images();
    list($images, $notice) = surfside_tools_homepage_handle_post($images);

    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-homepage-manager">
        <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">← Back to Dashboard</a></div>
        <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Manage Homepage</p>
            <h1>Homepage Photos</h1>
            <p class="surfside-staff-muted">Upload, replace, remove, and reorder the photos shown in the homepage carousel.</p>
        </section>
        <?php echo $notice; ?>
        <form method="post" enctype="multipart/form-data" class="surfside-homepage-form">
            <?php wp_nonce_field('surfside_homepage_update', 'surfside_homepage_nonce'); ?>
            <input type="hidden" name="surfside_homepage_action" value="save">
            <p class="surfside-homepage-intro">Drag photos into the preferred order. Recommended image size: 1920 × 1080. Up to 30 photos may be used.</p>
            <div class="surfside-homepage-grid" data-homepage-sortable>
                <?php foreach ($images as $index => $image) : $id = absint($image['id']); ?>
                    <article class="surfside-homepage-photo" draggable="true" data-image-id="<?php echo esc_attr($id); ?>">
                        <input type="hidden" name="image_order[]" value="<?php echo esc_attr($id); ?>">
                        <div class="surfside-homepage-photo-head"><strong>Photo <?php echo esc_html($index + 1); ?></strong><span class="surfside-homepage-drag">Drag to reorder</span></div>
                        <?php echo wp_get_attachment_image($id, 'medium_large', false, array('alt' => '')); ?>
                        <div class="surfside-homepage-photo-controls">
                            <label><strong>Replace this photo</strong><br><input type="file" name="replace_image_<?php echo esc_attr($id); ?>" accept="image/*"></label>
                            <label class="surfside-homepage-remove"><input type="checkbox" name="remove_images[]" value="<?php echo esc_attr($id); ?>"> Remove this photo</label>
                            <?php if (!empty($image['updated'])) : ?><span class="surfside-homepage-hint">Last updated <?php echo esc_html(date_i18n('F j, Y g:i A', $image['updated'])); ?></span><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="surfside-homepage-add">
                <label for="surfside-new-homepage-images">Add homepage photos</label>
                <input id="surfside-new-homepage-images" type="file" name="new_images[]" accept="image/*" multiple>
                <p class="surfside-homepage-hint">New photos are added to the end and can be reordered after saving.</p>
            </div>
            <div class="surfside-homepage-actions"><button class="surfside-homepage-save" type="submit">Save Homepage Photos</button><span class="surfside-homepage-hint"><?php echo esc_html(count($images)); ?> of 30 photo slots currently used.</span></div>
        </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded',function(){var grid=document.querySelector('[data-homepage-sortable]');if(!grid)return;var dragged=null;grid.addEventListener('dragstart',function(e){var card=e.target.closest('.surfside-homepage-photo');if(!card)return;dragged=card;card.classList.add('dragging');});grid.addEventListener('dragend',function(){if(dragged)dragged.classList.remove('dragging');dragged=null;});grid.addEventListener('dragover',function(e){e.preventDefault();if(!dragged)return;var cards=[].slice.call(grid.querySelectorAll('.surfside-homepage-photo:not(.dragging)'));var next=cards.find(function(card){return e.clientY<=card.getBoundingClientRect().top+card.offsetHeight/2;});if(next)grid.insertBefore(dragged,next);else grid.appendChild(dragged);});});
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_staff_homepage', 'surfside_tools_staff_homepage_shortcode');

function surfside_tools_homepage_dashboard_card($html) {
    if (strpos($html, '<h1>Staff Dashboard</h1>') === false || strpos($html, '<h2>Settings</h2>') === false) {
        return $html;
    }

    $settings_heading = strpos($html, '<h2>Settings</h2>');
    $insert_at = strrpos(substr($html, 0, $settings_heading), '<article');
    if ($insert_at === false) {
        return $html;
    }

    $card = '<article class="surfside-staff-card"><span class="surfside-staff-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="M21 15l-5-5L5 20"/></svg></span><h2>Manage Homepage</h2><p>Update and reorder homepage carousel photos.</p><div class="surfside-staff-actions"><a class="surfside-staff-button" href="' . esc_url(surfside_tools_staff_page_url('homepage')) . '">Manage Homepage <span class="surfside-staff-arrow">›</span></a></div></article>';

    return substr($html, 0, $insert_at) . $card . substr($html, $insert_at);
}

add_action('init', function () {
    if (!function_exists('surfside_tools_staff_dashboard_shortcode')) {
        return;
    }

    remove_shortcode('surfside_staff_dashboard');
    add_shortcode('surfside_staff_dashboard', function () {
        return surfside_tools_homepage_dashboard_card(surfside_tools_staff_dashboard_shortcode());
    });
}, 40);

function surfside_tools_ensure_homepage_staff_page() {
    if (!is_admin() || !function_exists('surfside_tools_ensure_staff_page')) {
        return;
    }

    $dashboard = get_page_by_path('dashboard');
    if (!$dashboard) {
        return;
    }

    surfside_tools_ensure_staff_page('Manage Homepage', 'homepage', '[surfside_staff_homepage]', $dashboard->ID);
}
add_action('admin_init', 'surfside_tools_ensure_homepage_staff_page', 30);
