<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compact Manage Homepage gallery.
 *
 * Keeps the carousel editor on the existing Manage Homepage page while making
 * room for additional homepage controls later.
 */
function surfside_tools_compact_homepage_manager_styles() {
    wp_register_style('surfside-tools-homepage-manager-compact', false, array(), SURFSIDE_TOOLS_VERSION);
    wp_enqueue_style('surfside-tools-homepage-manager-compact');
    wp_add_inline_style('surfside-tools-homepage-manager-compact', '
        .surfside-homepage-compact{max-width:100%}
        .surfside-homepage-toolbar{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:center;padding:18px 20px;margin:0 0 22px;border:1px solid rgba(7,27,58,.14);border-radius:16px;background:#f8fbff}
        .surfside-homepage-toolbar h2{margin:0 0 4px;font-size:22px}.surfside-homepage-toolbar p{margin:0;color:#4b5872}
        .surfside-homepage-upload-label{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 16px;border-radius:8px;background:#0b4f9c;color:#fff;font-weight:800;cursor:pointer;white-space:nowrap}
        .surfside-homepage-upload-label input{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
        .surfside-homepage-upload-files{grid-column:1/-1;font-size:13px;color:#4b5872;display:none}
        .surfside-homepage-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0 90px}
        .surfside-homepage-photo{position:relative;border:1px solid rgba(7,27,58,.14);border-radius:14px;background:#fff;box-shadow:0 5px 16px rgba(7,27,58,.05);overflow:hidden;cursor:grab}
        .surfside-homepage-photo.dragging{opacity:.45}.surfside-homepage-photo:focus-within{box-shadow:0 0 0 3px rgba(11,79,156,.14)}
        .surfside-homepage-thumb{position:relative}.surfside-homepage-thumb img{display:block;width:100%;aspect-ratio:16/10;object-fit:cover;background:#eef2f6}
        .surfside-homepage-number{position:absolute;left:9px;top:9px;padding:4px 8px;border-radius:999px;background:rgba(7,27,58,.82);color:#fff;font-size:12px;font-weight:800}
        .surfside-homepage-drag{position:absolute;right:9px;top:9px;padding:4px 8px;border-radius:999px;background:rgba(255,255,255,.9);color:#26344e;font-size:12px;font-weight:700}
        .surfside-homepage-edit summary{list-style:none;display:flex;align-items:center;justify-content:center;min-height:40px;padding:8px 12px;color:#0b4f9c;font-weight:800;cursor:pointer;border-top:1px solid rgba(7,27,58,.1)}
        .surfside-homepage-edit summary::-webkit-details-marker{display:none}.surfside-homepage-edit[open] summary{background:#f3f8ff}
        .surfside-homepage-controls{display:grid;gap:11px;padding:13px;border-top:1px solid rgba(7,27,58,.1);font-size:13px}
        .surfside-homepage-controls input[type=file]{max-width:100%;font-size:12px}.surfside-homepage-remove{display:flex;gap:7px;align-items:center;color:#9b2020;font-weight:800}
        .surfside-homepage-hint{font-size:12px;color:#5d687d}
        .surfside-homepage-empty{grid-column:1/-1;padding:34px;border:1px dashed #9fb3c9;border-radius:14px;text-align:center;color:#4b5872;background:#f8fbff}
        .surfside-homepage-savebar{position:sticky;bottom:14px;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:-70px;padding:14px 16px;border:1px solid rgba(7,27,58,.16);border-radius:14px;background:rgba(255,255,255,.96);box-shadow:0 12px 30px rgba(7,27,58,.16);backdrop-filter:blur(8px)}
        .surfside-homepage-save{border:0;border-radius:8px;background:#0b4f9c;color:#fff;font-weight:800;padding:12px 20px;cursor:pointer}.surfside-homepage-save:hover{background:#083f7d}
        .surfside-homepage-notice{padding:14px 16px;border-radius:10px;margin:0 0 20px;font-weight:700}.surfside-homepage-notice.success{background:#edf9f0;border:1px solid #9bd2a6;color:#17682e}.surfside-homepage-notice.error{background:#fff0f0;border:1px solid #e7aaaa;color:#9b2020}
        @media(max-width:980px){.surfside-homepage-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:720px){.surfside-homepage-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.surfside-homepage-toolbar{grid-template-columns:1fr}.surfside-homepage-upload-label{width:100%}.surfside-homepage-savebar{bottom:8px}}
        @media(max-width:460px){.surfside-homepage-grid{grid-template-columns:1fr}.surfside-homepage-savebar{align-items:stretch;flex-direction:column}.surfside-homepage-save{width:100%}}
    ');
}

function surfside_tools_staff_homepage_compact_shortcode() {
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

    surfside_tools_compact_homepage_manager_styles();
    $images = surfside_tools_homepage_get_images();
    list($images, $notice) = surfside_tools_homepage_handle_post($images);

    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-homepage-compact">
        <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">← Back to Dashboard</a></div>
        <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Manage Homepage</p>
            <h1>Homepage Photos</h1>
            <p class="surfside-staff-muted">Add, reorder, replace, or remove photos shown in the homepage carousel.</p>
        </section>
        <?php echo $notice; ?>
        <form method="post" enctype="multipart/form-data" class="surfside-homepage-form">
            <?php wp_nonce_field('surfside_homepage_update', 'surfside_homepage_nonce'); ?>
            <input type="hidden" name="surfside_homepage_action" value="save">

            <section class="surfside-homepage-toolbar">
                <div>
                    <h2>Carousel Photos</h2>
                    <p><?php echo esc_html(count($images)); ?> of 30 photos used. Drag thumbnails to reorder them.</p>
                </div>
                <label class="surfside-homepage-upload-label">
                    Add Photos
                    <input type="file" name="new_images[]" accept="image/*" multiple data-homepage-new-images>
                </label>
                <div class="surfside-homepage-upload-files" data-homepage-file-status aria-live="polite"></div>
            </section>

            <div class="surfside-homepage-grid" data-homepage-sortable>
                <?php if (!$images) : ?>
                    <div class="surfside-homepage-empty">No homepage photos are currently selected. Use <strong>Add Photos</strong> above to begin.</div>
                <?php endif; ?>
                <?php foreach ($images as $index => $image) : $id = absint($image['id']); ?>
                    <article class="surfside-homepage-photo" draggable="true" data-image-id="<?php echo esc_attr($id); ?>">
                        <input type="hidden" name="image_order[]" value="<?php echo esc_attr($id); ?>">
                        <div class="surfside-homepage-thumb">
                            <?php echo wp_get_attachment_image($id, 'medium', false, array('alt' => '')); ?>
                            <span class="surfside-homepage-number">Photo <?php echo esc_html($index + 1); ?></span>
                            <span class="surfside-homepage-drag">Drag</span>
                        </div>
                        <details class="surfside-homepage-edit">
                            <summary>Edit Photo</summary>
                            <div class="surfside-homepage-controls">
                                <label><strong>Replace photo</strong><br><input type="file" name="replace_image_<?php echo esc_attr($id); ?>" accept="image/*"></label>
                                <label class="surfside-homepage-remove"><input type="checkbox" name="remove_images[]" value="<?php echo esc_attr($id); ?>"> Remove from carousel</label>
                                <?php if (!empty($image['updated'])) : ?><span class="surfside-homepage-hint">Last updated <?php echo esc_html(date_i18n('F j, Y g:i A', $image['updated'])); ?></span><?php endif; ?>
                            </div>
                        </details>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="surfside-homepage-savebar">
                <span class="surfside-homepage-hint">Recommended image size: 1920 × 1080. Removing a photo does not delete it from the Media Library.</span>
                <button class="surfside-homepage-save" type="submit">Save Homepage Photos</button>
            </div>
        </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        var grid=document.querySelector('[data-homepage-sortable]');
        if(grid){
            var dragged=null;
            grid.addEventListener('dragstart',function(e){var card=e.target.closest('.surfside-homepage-photo');if(!card)return;dragged=card;card.classList.add('dragging');});
            grid.addEventListener('dragend',function(){if(dragged)dragged.classList.remove('dragging');dragged=null;renumber();});
            grid.addEventListener('dragover',function(e){e.preventDefault();if(!dragged)return;var cards=[].slice.call(grid.querySelectorAll('.surfside-homepage-photo:not(.dragging)'));var next=cards.find(function(card){var box=card.getBoundingClientRect();return e.clientY<box.top+(box.height/2);});if(next)grid.insertBefore(dragged,next);else grid.appendChild(dragged);});
            function renumber(){[].slice.call(grid.querySelectorAll('.surfside-homepage-photo')).forEach(function(card,index){var number=card.querySelector('.surfside-homepage-number');if(number)number.textContent='Photo '+(index+1);});}
        }
        var input=document.querySelector('[data-homepage-new-images]');
        var status=document.querySelector('[data-homepage-file-status]');
        if(input&&status){input.addEventListener('change',function(){var count=input.files?input.files.length:0;status.style.display=count?'block':'none';status.textContent=count===1?'1 new photo selected. Save to add it.':count+' new photos selected. Save to add them.';});}
    });
    </script>
    <?php
    return ob_get_clean();
}

add_action('init', function () {
    remove_shortcode('surfside_staff_homepage');
    add_shortcode('surfside_staff_homepage', 'surfside_tools_staff_homepage_compact_shortcode');
}, 90);
