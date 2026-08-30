<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('the_content', function ($content) {
    if (!is_page('dashboard') || !function_exists('surfside_tools_current_user_is_tools_admin') || !surfside_tools_current_user_is_tools_admin()) {
        return $content;
    }

    wp_add_inline_style('surfside-tools-staff-dashboard', '
        .surfside-staff-shell[style*="padding-top:0"] {
            padding-bottom: 18px !important;
        }
        .surfside-staff-shell[style*="padding-top:0"] > .surfside-staff-card {
            min-height: 0 !important;
            padding: 16px 20px !important;
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) auto;
            grid-template-areas: "title action" "copy action";
            column-gap: 24px;
            align-items: center;
            text-align: left !important;
            border-radius: 14px;
            box-shadow: 0 5px 14px rgba(7, 27, 58, .045);
        }
        .surfside-staff-shell[style*="padding-top:0"] > .surfside-staff-card h2 {
            grid-area: title;
            margin: 0 !important;
            font-size: 19px !important;
            line-height: 1.2;
        }
        .surfside-staff-shell[style*="padding-top:0"] > .surfside-staff-card p {
            grid-area: copy;
            margin: 3px 0 0 !important;
            max-width: none !important;
            font-size: 14px;
            line-height: 1.35;
        }
        .surfside-staff-shell[style*="padding-top:0"] > .surfside-staff-card .surfside-staff-actions {
            grid-area: action;
            width: auto !important;
            margin: 0 !important;
        }
        .surfside-staff-shell[style*="padding-top:0"] > .surfside-staff-card .surfside-staff-button-secondary {
            width: auto !important;
            min-height: 42px;
            padding: 8px 16px;
            white-space: nowrap;
        }
        @media (max-width: 640px) {
            .surfside-staff-shell[style*="padding-top:0"] > .surfside-staff-card {
                grid-template-columns: 1fr;
                grid-template-areas: "title" "copy" "action";
                row-gap: 8px;
            }
            .surfside-staff-shell[style*="padding-top:0"] > .surfside-staff-card .surfside-staff-actions {
                width: 100% !important;
                margin-top: 4px !important;
            }
            .surfside-staff-shell[style*="padding-top:0"] > .surfside-staff-card .surfside-staff-button-secondary {
                width: 100% !important;
            }
        }
    ');

    return $content;
}, 40);
