<?php
/** Final presentation pass for the front-end Integrations screen. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_integrations_visual_card($output) {
    return preg_replace_callback(
        '~<section class="surfside-front-settings-card surfside-visual-css-settings-card">\s*<h2>Reveal &amp; Countdown Styling</h2>(.*?)</section>~s',
        function ($matches) {
            return '<details class="surfside-front-settings-card surfside-integration-card surfside-visual-css-settings-card"><summary><span>Reveal &amp; Countdown Styling</span><span class="surfside-integration-summary-action">Configure</span></summary><div class="surfside-integration-body">' . $matches[1] . '</div></details>';
        },
        $output,
        1
    );
}

function surfside_tools_integrations_youversion_card($output) {
    return preg_replace_callback(
        '~<section id="surfside-youversion" class="surfside-front-settings-card surfside-youversion-settings-card">\s*<div class="surfside-youversion-heading"><div><h2>YouVersion</h2><p class="surfside-front-description">(.*?)</p></div><span class="surfside-youversion-status ([^"]*)">(.*?)</span></div>(.*?)</section>~s',
        function ($matches) {
            $status_class = trim($matches[2]);
            return '<details id="surfside-youversion" class="surfside-front-settings-card surfside-integration-card surfside-youversion-settings-card"><summary><span>YouVersion</span><span class="surfside-youversion-status surfside-integration-status ' . esc_attr($status_class) . '">' . wp_kses_post($matches[3]) . '</span></summary><div class="surfside-integration-body"><p class="surfside-front-description">' . wp_kses_post($matches[1]) . '</p>' . $matches[4] . '</div></details>';
        },
        $output,
        1
    );
}

add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'surfside_staff_settings' || !is_user_logged_in() || !current_user_can('manage_options')) {
        return $output;
    }

    $output = surfside_tools_integrations_visual_card($output);
    $output = surfside_tools_integrations_youversion_card($output);

    $saved_notice = isset($_GET['integrations_saved'])
        ? '<div class="surfside-front-settings-notice surfside-front-settings-success surfside-integrations-save-notice">Integration settings saved.</div>'
        : '';

    $save_all = $saved_notice . '<div class="surfside-staff-shell surfside-integrations-save-shell"><button type="button" class="surfside-front-primary-button surfside-integrations-save-all" id="surfside-integrations-save-all">Save Integrations</button><div class="surfside-front-description surfside-integrations-save-status" id="surfside-integrations-save-status" aria-live="polite"></div></div>';

    $css = '<style>
        .surfside-front-settings-form>p,.surfside-shared-integrations-save,.surfside-visual-css-settings-card form>p{display:none!important}
        .surfside-youversion-row button[name="surfside_youversion_settings_action"][value="save"]{display:none!important}
        .surfside-visual-css-settings-shell{padding-top:0!important;padding-bottom:0!important}
        .surfside-visual-css-settings-shell .surfside-integration-card,.surfside-youversion-settings-card{margin-bottom:12px!important}
        .surfside-visual-css-settings-card textarea{min-height:260px}
        .surfside-youversion-settings-card{padding:0!important}
        .surfside-youversion-settings-card .surfside-integration-body{padding-top:14px}
        .surfside-youversion-form{margin-top:14px}
        .surfside-integrations-save-shell{padding-top:8px;padding-bottom:28px}
        .surfside-integrations-save-all{width:auto!important;min-width:190px}
        .surfside-integrations-save-status{display:inline-block;margin-left:12px}
        .surfside-integrations-save-notice{max-width:1200px;margin:8px auto 0!important}
        @media(max-width:720px){.surfside-integrations-save-all{width:100%!important}.surfside-integrations-save-status{display:block;margin:8px 0 0}}
    </style>';

    $script = '<script>
    document.addEventListener("DOMContentLoaded",function(){
        const button=document.getElementById("surfside-integrations-save-all");
        const status=document.getElementById("surfside-integrations-save-status");
        if(!button)return;
        button.addEventListener("click",async function(){
            const forms=[
                document.querySelector(".surfside-front-settings-form"),
                document.querySelector(".surfside-shared-integrations-form"),
                document.querySelector(".surfside-visual-css-settings-card form"),
                document.querySelector(".surfside-youversion-form")
            ].filter(Boolean);
            button.disabled=true;
            button.textContent="Saving…";
            if(status)status.textContent="Saving all integration settings.";
            try{
                for(const form of forms){
                    const data=new FormData(form);
                    if(form.classList.contains("surfside-youversion-form")){
                        data.set("surfside_youversion_settings_action","save");
                    }
                    const response=await fetch(window.location.href,{method:"POST",body:data,credentials:"same-origin",redirect:"follow"});
                    if(!response.ok)throw new Error("Save failed");
                }
                if(status)status.textContent="Saved. Refreshing…";
                const url=new URL(window.location.href);
                url.searchParams.set("integrations_saved","1");
                url.hash="";
                window.location.href=url.toString();
            }catch(error){
                button.disabled=false;
                button.textContent="Save Integrations";
                if(status)status.textContent="One or more settings could not be saved. Please try again.";
            }
        });
    });
    </script>';

    return $output . $save_all . $css . $script;
}, 60, 2);
