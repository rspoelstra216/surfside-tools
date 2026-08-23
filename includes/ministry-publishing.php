<?php
/** Ministry draft/published workflow. */
if (!defined('ABSPATH')) { exit; }

function surfside_tools_ministry_is_published($ministry) {
    return !array_key_exists('published', (array) $ministry) || !empty($ministry['published']);
}

/** Calendar-created ministry records begin as drafts. Runs only in the same explicit save request. */
function surfside_tools_mark_calendar_seeded_ministry_draft() {
    $event_id = absint($GLOBALS['surfside_tools_ministry_seed_event_id'] ?? 0);
    if (!$event_id || !function_exists('surfside_tools_get_ministries') || !function_exists('surfside_tools_update_ministries')) return;
    $expected_key = 'calendar-' . $event_id;
    $linked_key = sanitize_key((string) get_post_meta($event_id, '_surfside_ministry_manager_key', true));
    if ($linked_key !== $expected_key) return; // Existing same-name ministries are never unpublished.

    $ministries = array_values((array) surfside_tools_get_ministries());
    $changed = false;
    foreach ($ministries as &$ministry) {
        if (($ministry['key'] ?? '') !== $expected_key) continue;
        if (!array_key_exists('published', $ministry) || !empty($ministry['published'])) {
            $ministry['published'] = false;
            $ministry['featured'] = false;
            $changed = true;
        }
        break;
    }
    unset($ministry);
    if ($changed) surfside_tools_update_ministries($ministries);
}
add_action('shutdown', 'surfside_tools_mark_calendar_seeded_ministry_draft', 2);

/** Add a Published switch to every Ministry Manager card without changing its save handler. */
function surfside_tools_ministry_publishing_manager_ui($output, $tag) {
    if ($tag !== 'surfside_staff_ministries_manager' || !is_user_logged_in() || !current_user_can('manage_options')) return $output;
    $states = array();
    foreach ((array) surfside_tools_get_ministries() as $ministry) {
        $states[(string) ($ministry['key'] ?? '')] = surfside_tools_ministry_is_published($ministry);
    }
    $json = wp_json_encode($states);
    $output .= '<style>.surfside-ministry-publish-row{grid-column:1/-1;display:flex;align-items:center;gap:10px;padding:8px 0 2px}.surfside-ministry-publish-row label{display:inline-flex;align-items:center;gap:8px;font-weight:800;color:#26323d}.surfside-ministry-status{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:.04em}.surfside-ministry-status--published{background:#eaf7ef;color:#126b36}.surfside-ministry-status--draft{background:#fff4d6;color:#7a5200}</style>';
    $output .= '<script>(function(){var list=document.querySelector("[data-surfside-ministries]");if(!list)return;var states=' . $json . ';function decorate(){Array.from(list.querySelectorAll(".surfside-information-ministry")).forEach(function(card){if(card.querySelector("[data-ministry-publish-row]"))return;var keyInput=card.querySelector("input[name$=\"[key]\"]"),key=keyInput?keyInput.value:"",published=Object.prototype.hasOwnProperty.call(states,key)?!!states[key]:false;var anchor=card.querySelector(".surfside-ministry-featured")||card.querySelector(".surfside-information-ministry-description");var row=document.createElement("div");row.className="surfside-ministry-publish-row";row.setAttribute("data-ministry-publish-row","1");var name=(keyInput&&keyInput.name?keyInput.name.replace(/\[key\]$/,""):"ministries[0]")+"[published]";row.innerHTML="<input type=\"hidden\" name=\""+name+"\" value=\"0\"><label><input type=\"checkbox\" name=\""+name+"\" value=\"1\" "+(published?"checked":"")+"><span>Published</span></label><span class=\"surfside-ministry-status "+(published?"surfside-ministry-status--published":"surfside-ministry-status--draft")+"\">"+(published?"Published":"Draft")+"</span>";if(anchor&&anchor.parentNode)anchor.parentNode.insertBefore(row,anchor);else card.appendChild(row);var check=row.querySelector("input[type=checkbox]"),status=row.querySelector(".surfside-ministry-status");check.addEventListener("change",function(){status.textContent=check.checked?"Published":"Draft";status.className="surfside-ministry-status "+(check.checked?"surfside-ministry-status--published":"surfside-ministry-status--draft");});});}new MutationObserver(function(){setTimeout(decorate,0);}).observe(list,{childList:true});decorate();})();</script>';
    return $output;
}
add_filter('do_shortcode_tag', 'surfside_tools_ministry_publishing_manager_ui', 50, 2);

/** Remove draft ministries from public shortcode output. The mobile API filters them server-side in ministries-model.php. */
function surfside_tools_hide_draft_ministries_public($output, $tag) {
    if (!in_array($tag, array('surfside_ministries','surfside_adult_ministries','surfside_featured_ministries','surfside_all_ministries'), true)) return $output;
    $drafts = array();
    foreach ((array) surfside_tools_get_ministries() as $ministry) {
        if (!surfside_tools_ministry_is_published($ministry)) $drafts[] = (string) ($ministry['name'] ?? '');
    }
    $drafts = array_values(array_filter($drafts));
    if (!$drafts) return $output;
    $json = wp_json_encode($drafts);
    $output .= '<script>(function(){var drafts=' . $json . ';document.querySelectorAll("[data-ministry-directory-item]").forEach(function(el){if(drafts.indexOf(el.getAttribute("data-name")||"")!==-1)el.remove();});document.querySelectorAll(".surfside-adult-ministries__card").forEach(function(card){var h=card.querySelector("h3"),t=h?h.textContent:"";if(drafts.some(function(name){return t.indexOf(name)!==-1;}))card.remove();});})();</script>';
    return $output;
}
add_filter('do_shortcode_tag', 'surfside_tools_hide_draft_ministries_public', 60, 2);
