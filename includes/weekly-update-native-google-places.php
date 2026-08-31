<?php

if (!defined('ABSPATH')) {
    exit;
}

function surfside_tools_weekly_update_native_google_places_assets() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        return;
    }
    ?>
    <style>.pac-container{z-index:2147483647!important}</style>
    <?php
}
add_action('wp_footer', 'surfside_tools_weekly_update_native_google_places_assets', 130);
