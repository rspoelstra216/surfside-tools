<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repair native drag-and-drop behavior for the compact homepage gallery.
 */
function surfside_tools_homepage_drag_fix_script() {
    $page = get_queried_object();

    if (!$page || empty($page->post_content) || strpos($page->post_content, '[surfside_staff_homepage]') === false) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var grid = document.querySelector('[data-homepage-sortable]');
        if (!grid || grid.dataset.surfsideDragFixed === '1') {
            return;
        }

        grid.dataset.surfsideDragFixed = '1';
        var dragged = null;

        grid.querySelectorAll('.surfside-homepage-photo').forEach(function (card) {
            card.setAttribute('draggable', 'false');

            var image = card.querySelector('img');
            if (image) {
                image.setAttribute('draggable', 'false');
            }

            var handle = card.querySelector('.surfside-homepage-drag');
            if (handle) {
                handle.setAttribute('draggable', 'true');
                handle.setAttribute('role', 'button');
                handle.setAttribute('aria-label', 'Drag to reorder this photo');
                handle.setAttribute('title', 'Drag to reorder');
            }
        });

        grid.addEventListener('dragstart', function (event) {
            var handle = event.target.closest('.surfside-homepage-drag');
            if (!handle) {
                event.preventDefault();
                return;
            }

            var card = handle.closest('.surfside-homepage-photo');
            if (!card) {
                event.preventDefault();
                return;
            }

            dragged = card;
            card.classList.add('dragging');

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', card.getAttribute('data-image-id') || 'homepage-photo');
            }
        }, true);

        grid.addEventListener('dragover', function (event) {
            if (!dragged) {
                return;
            }

            event.preventDefault();

            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }

            var target = event.target.closest('.surfside-homepage-photo');
            if (!target || target === dragged) {
                return;
            }

            var box = target.getBoundingClientRect();
            var after = event.clientY > box.top + (box.height / 2);
            grid.insertBefore(dragged, after ? target.nextSibling : target);
        });

        grid.addEventListener('drop', function (event) {
            if (!dragged) {
                return;
            }

            event.preventDefault();
            finishDrag();
        });

        grid.addEventListener('dragend', finishDrag, true);

        function finishDrag() {
            if (dragged) {
                dragged.classList.remove('dragging');
            }

            dragged = null;
            renumber();
        }

        function renumber() {
            grid.querySelectorAll('.surfside-homepage-photo').forEach(function (card, index) {
                var number = card.querySelector('.surfside-homepage-number');
                if (number) {
                    number.textContent = 'Photo ' + (index + 1);
                }
            });
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_homepage_drag_fix_script', 100);

function surfside_tools_homepage_drag_fix_styles() {
    wp_register_style('surfside-tools-homepage-drag-fix', false, array(), SURFSIDE_TOOLS_VERSION);
    wp_enqueue_style('surfside-tools-homepage-drag-fix');
    wp_add_inline_style('surfside-tools-homepage-drag-fix', '
        .surfside-homepage-photo{cursor:default}
        .surfside-homepage-drag{cursor:grab;user-select:none;-webkit-user-select:none}
        .surfside-homepage-drag:active{cursor:grabbing}
        .surfside-homepage-photo.dragging{opacity:.45;outline:2px dashed #0b4f9c;outline-offset:2px}
    ');
}
add_action('wp_enqueue_scripts', 'surfside_tools_homepage_drag_fix_styles', 30);
