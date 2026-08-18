<?php
/** Shared audience classification for Ministry and Bible Study events. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_event_audience_choices() {
    return array('kids'=>'Kids','youth'=>'Youth','adults'=>'Adults','all-ages'=>'All Ages');
}
function surfside_tools_event_audience($event_id) {
    $event_id = absint($event_id);
    $value = sanitize_key((string) get_post_meta($event_id, '_surfside_event_audience', true));
    if ($value === '') $value = sanitize_key((string) get_post_meta($event_id, '_surfside_event_bible_study_audience', true));
    return isset(surfside_tools_event_audience_choices()[$value]) ? $value : '';
}
function surfside_tools_event_audience_save($post_id, $post, $update) {
    if (!$post || $post->post_type !== 'surfside_event' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) return;
    if (empty($_POST['surfside_calendar_action']) || sanitize_key(wp_unslash($_POST['surfside_calendar_action'])) !== 'save') return;
    if (empty($_POST['surfside_calendar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_calendar_nonce'])), 'surfside_calendar_manager')) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $active = !empty($_POST['event_show_on_ministries']) || !empty($_POST['event_is_bible_study']);
    $audience = isset($_POST['event_audience']) ? sanitize_key(wp_unslash($_POST['event_audience'])) : '';
    if (!$active || !isset(surfside_tools_event_audience_choices()[$audience])) $audience = '';
    update_post_meta($post_id, '_surfside_event_audience', $audience);
    update_post_meta($post_id, '_surfside_event_bible_study_audience', $audience);
}
add_action('save_post_surfside_event', 'surfside_tools_event_audience_save', 30, 3);

function surfside_tools_event_audience_manager_fields() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) return;
    $edit_id = isset($_GET['edit_event']) ? absint($_GET['edit_event']) : 0;
    $audience = $edit_id ? surfside_tools_event_audience($edit_id) : '';
    ?>
    <template id="surfside-event-audience-template"><label class="surfside-event-audience" data-surfside-event-audience hidden><span>Audience <em>(optional)</em></span><select name="event_audience"><option value="">Not specified</option><?php foreach (surfside_tools_event_audience_choices() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($audience,$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label></template>
    <style>.surfside-event-audience{display:block;margin:0 0 16px;padding:0 20px}.surfside-event-audience[hidden]{display:none!important}.surfside-event-audience>span{display:block;font-weight:700;margin-bottom:7px;color:#071b3a}.surfside-event-audience em{font-weight:400;color:#60708a}.surfside-event-audience select{width:100%;border:1px solid rgba(7,27,58,.2);border-radius:10px;padding:11px 12px;font:inherit;background:#fff}</style>
    <script>document.addEventListener('DOMContentLoaded',function(){var f=document.querySelector('.surfside-calendar-form'),t=document.getElementById('surfside-event-audience-template');if(!f||!t||f.querySelector('[name="event_audience"]'))return;var m=f.querySelector('input[name="event_show_on_ministries"]'),b=f.querySelector('input[name="event_is_bible_study"]'),a=m?m.closest('label'):null,x=t.content.cloneNode(true).querySelector('[data-surfside-event-audience]');if(a&&a.parentNode)a.parentNode.insertBefore(x,a.nextSibling);else f.appendChild(x);function u(){var on=!!((m&&m.checked)||(b&&b.checked));x.hidden=!on;var s=x.querySelector('select');if(s)s.disabled=!on}if(m)m.addEventListener('change',u);if(b)b.addEventListener('change',u);u()});</script>
    <?php
}
add_action('wp_footer','surfside_tools_event_audience_manager_fields',99);
add_action('admin_footer','surfside_tools_event_audience_manager_fields',99);
