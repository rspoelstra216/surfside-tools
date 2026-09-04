<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Homepage carousel and front-end photo management.
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

function surfside_tools_homepage_get_images() {
    return surfside_tools_homepage_normalize_images(get_option(surfside_tools_homepage_image_option(), array()));
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
        .surfside-scroll-carousel{width:calc(100vw - 32px);max-width:none;margin:0 auto 40px;margin-left:50%;transform:translateX(-50%);overflow:hidden}
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
