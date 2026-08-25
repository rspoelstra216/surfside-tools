<?php
/** Tighten the Integrations stack and place Streaming with technical settings. */
if (!defined('ABSPATH')) { exit; }

add_filter('do_shortcode_tag', function ($output, $tag) {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return $output;
    }

    if ($tag === 'surfside_staff_site_settings') {
        return preg_replace(
            '~<article class="surfside-staff-card">(?:(?!</article>).)*?<h2>Streaming</h2>(?:(?!</article>).)*?</article>~s',
            '',
            $output,
            1
        );
    }

    if ($tag !== 'surfside_staff_settings') {
        return $output;
    }

    $streaming_url = function_exists('surfside_tools_staff_page_url')
        ? surfside_tools_staff_page_url('site-streaming')
        : home_url('/dashboard/site-streaming/');

    $streaming = '<details class="surfside-front-settings-card surfside-integration-card surfside-streaming-integration-card">'
        . '<summary><span>Streaming</span></summary>'
        . '<div class="surfside-integration-body"><p class="surfside-front-description">Livestream channel, offline announcement media, and shared streaming destinations.</p>'
        . '<p><a class="surfside-front-secondary-button surfside-streaming-settings-link" href="' . esc_url($streaming_url) . '">Open Streaming Settings <span aria-hidden="true">›</span></a></p></div>'
        . '</details>';

    $visual_marker = '<div class="surfside-staff-shell surfside-visual-css-settings-shell">';
    if (strpos($output, $visual_marker) !== false) {
        $output = str_replace($visual_marker, $streaming . $visual_marker, $output);
    } else {
        $output .= $streaming;
    }

    $css = '<style>
        .surfside-integration-card{margin:0 0 10px!important;box-sizing:border-box}
        .surfside-integration-card>summary{display:grid!important;grid-template-columns:minmax(0,1fr) 24px;align-items:center;column-gap:14px;min-height:56px;box-sizing:border-box;padding:14px 18px!important}
        .surfside-integration-card>summary>span:first-child{min-width:0}
        .surfside-integration-summary-action,.surfside-integration-status,.surfside-youversion-status{display:none!important}
        .surfside-integration-card>summary:after{margin:0!important;justify-self:end}
        .surfside-streaming-integration-card{max-width:1200px;margin-left:auto!important;margin-right:auto!important}
        .surfside-streaming-settings-link{display:inline-flex;align-items:center;gap:8px;width:auto!important;text-decoration:none!important}
        .surfside-visual-css-settings-shell{margin:0 auto!important;padding:0!important}
        .surfside-visual-css-settings-shell:empty{display:none!important}
        .surfside-youversion-settings-card{margin-top:0!important}
        .surfside-integrations-save-shell{margin-top:0!important;padding-top:6px!important}
        @media(max-width:720px){.surfside-integration-card>summary{grid-template-columns:minmax(0,1fr) 20px;column-gap:9px;padding:13px 14px!important}}
    </style>';

    $script = '<script>
    document.addEventListener("DOMContentLoaded",function(){
        const stack=document.querySelector(".surfside-front-settings");
        if(!stack)return;
        const streaming=document.querySelector(".surfside-streaming-integration-card");
        const visual=document.querySelector(".surfside-visual-css-settings-card");
        const youversion=document.querySelector(".surfside-youversion-settings-card");
        [streaming,visual,youversion].forEach(function(card){if(card)stack.appendChild(card);});
        const visualShell=document.querySelector(".surfside-visual-css-settings-shell");
        if(visualShell && !visualShell.querySelector(".surfside-integration-card")) visualShell.style.display="none";
    });
    </script>';

    return $output . $css . $script;
}, 80, 2);
