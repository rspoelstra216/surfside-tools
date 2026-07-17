<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Milestone 7: optional event images for staff management and public details.
 */
function surfside_tools_calendar_event_images_support() {
    add_post_type_support('surfside_event', 'thumbnail');
}
add_action('init', 'surfside_tools_calendar_event_images_support', 20);

function surfside_tools_calendar_event_images_save($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!isset($_POST['surfside_calendar_action']) || sanitize_key(wp_unslash($_POST['surfside_calendar_action'])) !== 'save') {
        return;
    }

    if (!isset($_POST['surfside_calendar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_calendar_nonce'])), 'surfside_calendar_manager')) {
        return;
    }

    if (!current_user_can('upload_files') || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $image_id = isset($_POST['event_image_id']) ? absint($_POST['event_image_id']) : 0;
    if ($image_id && wp_attachment_is_image($image_id)) {
        set_post_thumbnail($post_id, $image_id);
    } else {
        delete_post_thumbnail($post_id);
    }
}
add_action('save_post_surfside_event', 'surfside_tools_calendar_event_images_save', 20);

function surfside_tools_calendar_event_images_enqueue_media() {
    if (is_user_logged_in() && current_user_can('upload_files')) {
        wp_enqueue_media();
    }
}
add_action('wp_enqueue_scripts', 'surfside_tools_calendar_event_images_enqueue_media');

function surfside_tools_calendar_event_images_public_map() {
    $query = new WP_Query(array(
        'post_type' => 'surfside_event',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => array(array('key' => '_thumbnail_id', 'compare' => 'EXISTS')),
    ));

    $images = array();
    foreach ($query->posts as $event_id) {
        $image_id = get_post_thumbnail_id($event_id);
        $url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
        if (!$url) {
            continue;
        }

        $alt = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));
        $images[(string) $event_id] = array(
            'url' => $url,
            'alt' => $alt !== '' ? $alt : get_the_title($event_id),
        );
    }
    wp_reset_postdata();

    return $images;
}

function surfside_tools_calendar_event_images_assets() {
    $edit_id = isset($_GET['edit_event']) ? absint($_GET['edit_event']) : 0;
    $edit_image_id = $edit_id ? get_post_thumbnail_id($edit_id) : 0;
    $edit_image_url = $edit_image_id ? wp_get_attachment_image_url($edit_image_id, 'medium') : '';
    $public_images = surfside_tools_calendar_event_images_public_map();
    ?>
    <style id="surfside-calendar-event-images-styles">
        .surfside-event-image-field{display:grid;gap:10px;padding:14px;border:1px solid rgba(7,27,58,.12);border-radius:12px;background:#f8fbff}
        .surfside-event-image-field>span{font-weight:800;color:#071b3a}.surfside-event-image-help{margin:0;color:#5b667a;font-size:.9rem}
        .surfside-event-image-preview[hidden]{display:none!important}.surfside-event-image-preview{max-width:280px;overflow:hidden;border-radius:10px;background:#fff}
        .surfside-event-image-preview img{display:block;width:100%;height:auto;max-height:190px;object-fit:cover}
        .surfside-event-image-actions{display:flex;flex-wrap:wrap;gap:9px}.surfside-event-image-actions button{min-height:40px;padding:8px 12px;border:1px solid rgba(11,79,156,.28);border-radius:8px;background:#fff;color:#0b4f9c;font:inherit;font-weight:800;cursor:pointer}
        .surfside-event-image-actions button:hover,.surfside-event-image-actions button:focus-visible{border-color:#0b4f9c;background:#eef6ff}.surfside-event-image-remove[hidden]{display:none!important}
        .surfside-event-modal-image{margin:0 0 20px;overflow:hidden;border-radius:14px;background:#eef2f7}.surfside-event-modal-image img{display:block;width:100%;max-height:340px;object-fit:cover}
        @media(max-width:600px){.surfside-event-modal-image{margin-left:-4px;margin-right:-4px}.surfside-event-modal-image img{max-height:240px}}
    </style>
    <script id="surfside-calendar-event-images-script">
    (function(){
        'use strict';
        var managerImage=<?php echo wp_json_encode(array('id' => $edit_image_id, 'url' => $edit_image_url)); ?>;
        var publicImages=<?php echo wp_json_encode($public_images); ?>;

        function addManagerField(){
            var form=document.querySelector('.surfside-calendar-form');
            if(!form||form.querySelector('[data-surfside-event-image]'))return;
            var description=form.querySelector('textarea[name="event_description"]');
            var descriptionLabel=description?description.closest('label'):null;
            if(!descriptionLabel)return;

            var field=document.createElement('div');
            field.className='surfside-event-image-field';
            field.setAttribute('data-surfside-event-image','');
            field.innerHTML='<span>Event Image <small>(optional)</small></span>'+
                '<p class="surfside-event-image-help">Shown in event details, but never inside the compact monthly calendar grid.</p>'+
                '<input type="hidden" name="event_image_id" value="'+(managerImage.id||0)+'">'+
                '<div class="surfside-event-image-preview"'+(managerImage.url?'':' hidden')+'>'+(managerImage.url?'<img src="'+managerImage.url+'" alt="Selected event image">':'')+'</div>'+
                '<div class="surfside-event-image-actions"><button type="button" class="surfside-event-image-choose">'+(managerImage.url?'Replace Image':'Choose Image')+'</button><button type="button" class="surfside-event-image-remove"'+(managerImage.url?'':' hidden')+'>Remove Image</button></div>';
            descriptionLabel.insertAdjacentElement('afterend',field);

            var input=field.querySelector('input');
            var preview=field.querySelector('.surfside-event-image-preview');
            var choose=field.querySelector('.surfside-event-image-choose');
            var remove=field.querySelector('.surfside-event-image-remove');
            var frame=null;

            choose.addEventListener('click',function(){
                if(!window.wp||!wp.media)return;
                if(!frame){
                    frame=wp.media({title:'Choose an event image',button:{text:'Use this image'},library:{type:'image'},multiple:false});
                    frame.on('select',function(){
                        var image=frame.state().get('selection').first().toJSON();
                        var source=(image.sizes&&image.sizes.medium)?image.sizes.medium.url:image.url;
                        input.value=image.id;
                        preview.innerHTML='<img src="'+source+'" alt="Selected event image">';
                        preview.hidden=false;
                        remove.hidden=false;
                        choose.textContent='Replace Image';
                    });
                }
                frame.open();
            });

            remove.addEventListener('click',function(){
                input.value='0';
                preview.innerHTML='';
                preview.hidden=true;
                remove.hidden=true;
                choose.textContent='Choose Image';
            });
        }

        function addPublicImages(){
            document.querySelectorAll('.surfside-event-modal[id^="surfside-event-detail-"]').forEach(function(modal){
                if(modal.querySelector('.surfside-event-modal-image'))return;
                var match=modal.id.match(/^surfside-event-detail-(\d+)-/);
                if(!match||!publicImages[match[1]])return;
                var card=modal.querySelector('.surfside-event-modal-card');
                var close=card?card.querySelector('.surfside-event-modal-close'):null;
                if(!card)return;
                var figure=document.createElement('figure');
                figure.className='surfside-event-modal-image';
                var img=document.createElement('img');
                img.src=publicImages[match[1]].url;
                img.alt=publicImages[match[1]].alt||'';
                figure.appendChild(img);
                if(close&&close.nextSibling){card.insertBefore(figure,close.nextSibling);}else{card.insertBefore(figure,card.firstChild);}
            });
        }

        function initialize(){addManagerField();addPublicImages();}
        if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',initialize);}else{initialize();}
    })();
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_calendar_event_images_assets', 92);
