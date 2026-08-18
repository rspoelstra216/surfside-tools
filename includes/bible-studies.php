<?php
/**
 * Bible Study event classification and mobile API support.
 *
 * Bible Study and Ministries are intentionally independent classifications:
 * an event may be either, both, or neither.
 */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_bible_study_audience_choices() {
    return array(
        'kids' => 'Kids',
        'youth' => 'Youth',
        'adults' => 'Adults',
        'all-ages' => 'All Ages',
    );
}

function surfside_tools_bible_study_audience($event_id) {
    $value = sanitize_key((string) get_post_meta(absint($event_id), '_surfside_event_bible_study_audience', true));
    return isset(surfside_tools_bible_study_audience_choices()[$value]) ? $value : '';
}

function surfside_tools_event_is_bible_study($event_id) {
    return (bool) get_post_meta(absint($event_id), '_surfside_event_is_bible_study', true);
}

/** Save the extra classification fields alongside the existing Calendar Manager save. */
function surfside_tools_bible_study_save_event_meta($post_id, $post, $update) {
    if (!$post || $post->post_type !== 'surfside_event') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (empty($_POST['surfside_calendar_action']) || sanitize_key(wp_unslash($_POST['surfside_calendar_action'])) !== 'save') return;
    if (empty($_POST['surfside_calendar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_calendar_nonce'])), 'surfside_calendar_manager')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $is_bible_study = !empty($_POST['event_is_bible_study']) ? 1 : 0;
    $audience = isset($_POST['event_bible_study_audience']) ? sanitize_key(wp_unslash($_POST['event_bible_study_audience'])) : '';
    if (!$is_bible_study || !isset(surfside_tools_bible_study_audience_choices()[$audience])) {
        $audience = '';
    }

    update_post_meta($post_id, '_surfside_event_is_bible_study', $is_bible_study);
    update_post_meta($post_id, '_surfside_event_bible_study_audience', $audience);
}
add_action('save_post_surfside_event', 'surfside_tools_bible_study_save_event_meta', 20, 3);

/**
 * Add Bible Study controls to the existing Calendar Manager without replacing its
 * event form. This keeps the existing Ministries checkbox independent.
 */
function surfside_tools_bible_study_manager_fields() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) return;

    $edit_id = isset($_GET['edit_event']) ? absint($_GET['edit_event']) : 0;
    $is_bible_study = $edit_id ? surfside_tools_event_is_bible_study($edit_id) : false;
    $audience = $edit_id ? surfside_tools_bible_study_audience($edit_id) : '';
    $study_ids = get_posts(array(
        'post_type' => 'surfside_event',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_key' => '_surfside_event_is_bible_study',
        'meta_value' => '1',
        'no_found_rows' => true,
    ));
    ?>
    <template id="surfside-bible-study-fields-template">
        <div class="surfside-bible-study-classification">
            <label class="surfside-calendar-checkbox surfside-calendar-featured-check">
                <input type="checkbox" name="event_is_bible_study" value="1" data-surfside-bible-study-toggle <?php checked($is_bible_study); ?>>
                <span>List as Bible Study</span>
            </label>
            <label class="surfside-bible-study-audience" data-surfside-bible-study-audience>
                <span>Bible Study audience <em>(optional)</em></span>
                <select name="event_bible_study_audience">
                    <option value="">Not specified</option>
                    <?php foreach (surfside_tools_bible_study_audience_choices() as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($audience, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <p class="surfside-bible-study-help">Bible Study is separate from Ministries. An event can be listed in both places.</p>
        </div>
    </template>
    <style>
        .surfside-bible-study-classification{margin:0 0 16px;padding:12px;border:1px solid rgba(11,79,156,.18);border-radius:12px;background:#f7fbff}
        .surfside-bible-study-classification .surfside-calendar-checkbox{margin:0}
        .surfside-bible-study-audience{display:block;margin:12px 0 0!important}
        .surfside-bible-study-audience>span{display:block;font-weight:700;margin-bottom:7px;color:#071b3a}
        .surfside-bible-study-audience em{font-weight:400;color:#60708a}
        .surfside-bible-study-audience select{width:100%;border:1px solid rgba(7,27,58,.2);border-radius:10px;padding:11px 12px;font:inherit;background:#fff}
        .surfside-bible-study-help{margin:8px 0 0;color:#60708a;font-size:.88rem}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.surfside-calendar-form');
        var template = document.getElementById('surfside-bible-study-fields-template');
        if (form && template && !form.querySelector('[name="event_is_bible_study"]')) {
            var ministryCheckbox = form.querySelector('input[name="event_show_on_ministries"]');
            var anchor = ministryCheckbox ? ministryCheckbox.closest('label') : form.querySelector('.surfside-calendar-form-actions');
            var fragment = template.content.cloneNode(true);
            var wrapper = fragment.querySelector('.surfside-bible-study-classification');
            if (anchor && anchor.parentNode) {
                anchor.parentNode.insertBefore(wrapper, anchor.nextSibling);
            } else {
                form.appendChild(wrapper);
            }

            var toggle = form.querySelector('[data-surfside-bible-study-toggle]');
            var audience = form.querySelector('[data-surfside-bible-study-audience]');
            function updateAudience() {
                if (!toggle || !audience) return;
                audience.hidden = !toggle.checked;
                var select = audience.querySelector('select');
                if (select) select.disabled = !toggle.checked;
            }
            if (toggle) toggle.addEventListener('change', updateAudience);
            updateAudience();
        }

        var studyIds = <?php echo wp_json_encode(array_values(array_map('absint', $study_ids))); ?>;
        document.querySelectorAll('.surfside-calendar-event').forEach(function (card) {
            var link = card.querySelector('a[href*="edit_event="]');
            var heading = card.querySelector('h3');
            if (!link || !heading) return;
            try {
                var id = parseInt(new URL(link.href, window.location.href).searchParams.get('edit_event') || '0', 10);
                if (studyIds.indexOf(id) === -1 || heading.querySelector('[data-bible-study-badge]')) return;
                var badge = document.createElement('span');
                badge.className = 'surfside-calendar-featured-badge';
                badge.setAttribute('data-bible-study-badge', '1');
                badge.textContent = 'Bible Study';
                heading.appendChild(document.createTextNode(' '));
                heading.appendChild(badge);
            } catch (e) {}
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'surfside_tools_bible_study_manager_fields', 98);
add_action('admin_footer', 'surfside_tools_bible_study_manager_fields', 98);

/** Add both independent classifications to the existing mobile Events API. */
function surfside_tools_bible_study_enrich_events_response($result, $server, $request) {
    if (!$request || $request->get_route() !== '/surfside/v1/events') return $result;
    $response = rest_ensure_response($result);
    $data = $response->get_data();
    if (empty($data['events']) || !is_array($data['events'])) return $response;

    foreach ($data['events'] as &$event) {
        $event_id = absint($event['id'] ?? 0);
        if (!$event_id) continue;
        $event['is_ministry'] = (bool) get_post_meta($event_id, '_surfside_event_show_on_ministries', true);
        $event['is_bible_study'] = surfside_tools_event_is_bible_study($event_id);
        $event['bible_study_audience'] = surfside_tools_bible_study_audience($event_id);
    }
    unset($event);
    $response->set_data($data);
    return $response;
}
add_filter('rest_post_dispatch', 'surfside_tools_bible_study_enrich_events_response', 10, 3);

/** Register a series-level endpoint for the app's dedicated Current Bible Studies section. */
function surfside_tools_bible_study_register_mobile_route() {
    register_rest_route('surfside/v1', '/bible-studies', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'surfside_tools_mobile_api_bible_studies',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'surfside_tools_bible_study_register_mobile_route');

function surfside_tools_mobile_api_bible_studies() {
    if (!function_exists('surfside_tools_calendar_get_all_events') || !function_exists('surfside_tools_calendar_event_occurrences')) {
        return new WP_Error('surfside_bible_studies_unavailable', 'Bible Studies are temporarily unavailable.', array('status' => 503));
    }

    $today = current_time('Y-m-d');
    $range_end = wp_date('Y-m-d', strtotime($today . ' +2 years'));
    $studies = array();

    foreach (surfside_tools_calendar_get_all_events() as $event) {
        $event_id = absint($event['id'] ?? 0);
        if (!$event_id || !surfside_tools_event_is_bible_study($event_id)) continue;

        $occurrences = surfside_tools_calendar_event_occurrences($event, $today, $range_end);
        if (empty($occurrences)) continue;

        $next = $occurrences[0];
        $item = function_exists('surfside_tools_mobile_api_event')
            ? surfside_tools_mobile_api_event($next)
            : array(
                'id' => $event_id,
                'title' => (string) ($event['title'] ?? ''),
                'description' => wp_strip_all_tags((string) ($event['description'] ?? ''), true),
                'date' => (string) ($next['date'] ?? ''),
                'start_time' => (string) ($event['start_time'] ?? ''),
                'end_time' => (string) ($event['end_time'] ?? ''),
            );

        $item['is_ministry'] = !empty($event['show_on_ministries']);
        $item['is_bible_study'] = true;
        $item['bible_study_audience'] = surfside_tools_bible_study_audience($event_id);
        $item['next_occurrence'] = (string) ($next['date'] ?? '');
        $item['recurrence_label'] = function_exists('surfside_tools_calendar_recurrence_label') ? surfside_tools_calendar_recurrence_label($event) : '';
        $item['upcoming_dates'] = array_values(array_slice(array_map(function ($occurrence) {
            return (string) ($occurrence['date'] ?? '');
        }, $occurrences), 0, 12));
        $studies[] = $item;
    }

    usort($studies, function ($a, $b) {
        $a_key = ($a['next_occurrence'] ?? '') . ' ' . (($a['start_time'] ?? '') ?: '00:00');
        $b_key = ($b['next_occurrence'] ?? '') . ' ' . (($b['start_time'] ?? '') ?: '00:00');
        return strcmp($a_key, $b_key);
    });

    return rest_ensure_response(array(
        'api_version' => 1,
        'generated_at' => current_datetime()->format(DATE_ATOM),
        'count' => count($studies),
        'studies' => array_values($studies),
    ));
}
