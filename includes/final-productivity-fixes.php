<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Remaining UI compatibility fixes for the Productivity milestone.
 */
function surfside_tools_final_productivity_fix_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }
    ?>
    <style>
        .pac-container {
            z-index: 2147483647 !important;
        }

        .surfside-staff-grid .surfside-staff-button-secondary {
            background: #0b4f9c;
            color: #fff !important;
            border-color: #0b4f9c;
            box-shadow: 0 10px 18px rgba(11, 79, 156, .22);
        }
        .surfside-staff-grid .surfside-staff-button-secondary:hover,
        .surfside-staff-grid .surfside-staff-button-secondary:focus {
            background: #083f7d;
            color: #fff !important;
            transform: translateY(-1px);
        }
    </style>
    <script>
    (function () {
        function repairPage() {
            document.querySelectorAll('.surfside-staff-grid a.surfside-staff-button-secondary').forEach(function (button) {
                button.classList.remove('surfside-staff-button-secondary');
                button.classList.add('surfside-staff-button');
            });
        }

        document.addEventListener('DOMContentLoaded', repairPage);
        repairPage();
        new MutationObserver(repairPage).observe(document.documentElement, { childList: true, subtree: true });
    })();
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_final_productivity_fix_assets', 120);
