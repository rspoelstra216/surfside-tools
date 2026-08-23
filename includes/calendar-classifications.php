<?php
/** Calendar Manager Ministry and Bible Study classification support. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_event_is_bible_study($event_id) {
    return (bool) get_post_meta(absint($event_id), '_surfside_event_is_bible_study', true);
}

/**
 * Save Bible Study classification and queue one-time Ministry Manager seeding.
 * This runs only when a Surfside Event is explicitly saved in Calendar Manager.
 */
function surfside_tools_calendar_classification_save($post_id, $post, $update) {
    if (!$post || $post->post_type !== 'surfside_event') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (empty($_POST['surfside_calendar_action']) || sanitize_key(wp_unslash($_POST['surfside_calendar_action'])) !== 'save') return;
    if (empty($_POST['surfside_calendar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['surfside_calendar_nonce'])), 'surfside_calendar_manager')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    update_post_meta($post_id, '_surfside_event_is_bible_study', !empty($_POST['event_is_bible_study']) ? 1 : 0);

    if (empty($_POST['event_show_on_ministries'])) return;

    $existing_key = sanitize_key((string) get_post_meta($post_id, '_surfside_ministry_manager_key', true));
    if ($existing_key !== '') {
        foreach ((array) surfside_tools_get_ministries() as $ministry) {
            if (($ministry['key'] ?? '') === $existing_key) return;
        }
    }

    $GLOBALS['surfside_tools_ministry_seed_event_id'] = absint($post_id);
    if (empty($GLOBALS['surfside_tools_ministry_seed_shutdown_registered'])) {
        $GLOBALS['surfside_tools_ministry_seed_shutdown_registered'] = true;
        add_action('shutdown', 'surfside_tools_seed_ministry_from_saved_event', 1);
    }
}
add_action('save_post_surfside_event', 'surfside_tools_calendar_classification_save', 30, 3);

/** Create the Ministry Manager record after Calendar Manager has finished saving event meta. */
function surfside_tools_seed_ministry_from_saved_event() {
    $event_id = absint($GLOBALS['surfside_tools_ministry_seed_event_id'] ?? 0);
    if (!$event_id || !function_exists('surfside_tools_calendar_get_event') || !function_exists('surfside_tools_update_ministries')) return;

    $event = surfside_tools_calendar_get_event($event_id);
    if (!$event || empty($event['show_on_ministries'])) return;

    $ministries = array_values((array) surfside_tools_get_ministries());
    $title = trim((string) ($event['title'] ?? ''));
    if ($title === '') return;

    // Reuse an existing ministry with the same name rather than creating a duplicate.
    foreach ($ministries as $ministry) {
        if (strcasecmp(trim((string) ($ministry['name'] ?? '')), $title) === 0) {
            if (!empty($ministry['key'])) update_post_meta($event_id, '_surfside_ministry_manager_key', sanitize_key($ministry['key']));
            return;
        }
    }

    $key = 'calendar-' . $event_id;
    $schedule = function_exists('surfside_tools_calendar_recurrence_label') ? surfside_tools_calendar_recurrence_label($event) : '';
    if ($schedule === '' && !empty($event['date']) && function_exists('surfside_tools_calendar_format_date')) {
        $schedule = surfside_tools_calendar_format_date($event['date']);
    }

    $ministries[] = array(
        'key' => $key,
        'icon' => '',
        'name' => $title,
        'schedule' => $schedule,
        'location' => (string) ($event['location_name'] ?? $event['location'] ?? ''),
        'description' => wp_strip_all_tags((string) ($event['description'] ?? ''), true),
        'audiences' => array('adults'),
        'featured' => false,
        'contact_name' => '',
        'contact_email' => '',
        'contact_phone' => '',
    );

    surfside_tools_update_ministries($ministries);
    update_post_meta($event_id, '_surfside_ministry_manager_key', $key);
}

/** Add the two independent classification controls to Calendar Manager only. */
function surfside_tools_calendar_classification_manager_ui($output, $tag) {
    if ($tag !== 'surfside_tools_calendar_manager' || !is_user_logged_in() || !current_user_can('upload_files')) return $output;

    $edit_id = isset($_GET['edit_event']) ? absint($_GET['edit_event']) : 0;
    $bible_checked = $edit_id ? surfside_tools_event_is_bible_study($edit_id) : false;
    $study_ids = get_posts(array(
        'post_type' => 'surfside_event',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_key' => '_surfside_event_is_bible_study',
        'meta_value' => '1',
        'no_found_rows' => true,
    ));

    $checked = $bible_checked ? ' checked' : '';
    $study_ids_json = wp_json_encode(array_values(array_map('absint', $study_ids)));

    $output .= '<style>.surfside-calendar-classification-row{display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin:0 0 16px;padding:12px 14px;border:1px solid rgba(11,79,156,.18);border-radius:12px;background:#f7fbff}.surfside-calendar-classification-row .surfside-calendar-checkbox{margin:0!important}.surfside-calendar-classification-note{width:100%;margin:0;color:#60708a;font-size:.86rem}</style>';
    $output .= '<template id="surfside-calendar-classification-template"><div class="surfside-calendar-classification-row"><label class="surfside-calendar-checkbox surfside-calendar-featured-check"><input type="checkbox" name="event_is_bible_study" value="1"' . $checked . '><span>Bible Study</span></label><p class="surfside-calendar-classification-note">Selecting Ministry creates a draft Ministry Manager entry if one does not already exist. Please visit the Ministry Manager to update details and publish.</p></div></template>';
    $output .= '<script>(function(){var form=document.querySelector(".surfside-calendar-form");if(!form)return;var ministry=form.querySelector("input[name=event_show_on_ministries]");if(ministry){var label=ministry.closest("label");var span=label&&label.querySelector("span");if(span)span.textContent="Ministry";var template=document.getElementById("surfside-calendar-classification-template");if(template&&label&&label.parentNode){var row=template.content.cloneNode(true).firstElementChild;label.parentNode.insertBefore(row,label);row.insertBefore(label,row.firstChild);}}var ids=' . $study_ids_json . ';document.querySelectorAll(".surfside-calendar-event").forEach(function(card){var link=card.querySelector("a[href*=\"edit_event=\"]"),heading=card.querySelector("h3");if(!link||!heading)return;try{var id=parseInt(new URL(link.href,window.location.href).searchParams.get("edit_event")||"0",10);if(ids.indexOf(id)!==-1&&!heading.querySelector("[data-bible-study-badge]")){var badge=document.createElement("span");badge.className="surfside-calendar-featured-badge";badge.setAttribute("data-bible-study-badge","1");badge.textContent="Bible Study";heading.appendChild(document.createTextNode(" "));heading.appendChild(badge);}Array.from(heading.querySelectorAll(".surfside-calendar-featured-badge")).forEach(function(b){if(b.textContent.trim()==="Ministries page")b.textContent="Ministry";});}catch(e){}});})();</script>';
    return $output;
}
add_filter('do_shortcode_tag', 'surfside_tools_calendar_classification_manager_ui', 35, 2);

/** Register the series-level Bible Studies endpoint used by the mobile app. */
function surfside_tools_bible_study_register_mobile_route() {
    register_rest_route('surfside/v1', '/bible-studies', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'surfside_tools_mobile_api_bible_studies',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'surfside_tools_bible_study_register_mobile_route');

/**
 * Return each active Bible Study series once, using its next occurrence for date/time
 * and a small list of upcoming dates for native app presentation.
 */
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
