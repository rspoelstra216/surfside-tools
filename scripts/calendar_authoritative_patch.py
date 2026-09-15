from pathlib import Path
import re


def replace_once(text, old, new, label):
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 match, found {count}")
    return text.replace(old, new, 1)


def sub_once(text, pattern, repl, label, flags=0):
    updated, count = re.subn(pattern, repl, text, count=1, flags=flags)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 match, found {count}")
    return updated

# Core Calendar Manager: make active-event state and range labels authoritative.
path = Path('includes/calendar-manager.php')
text = path.read_text()
old = """        $occurrences = surfside_tools_calendar_event_occurrences($event, $today, $range_end);\n        $event['next_occurrence_date'] = !empty($occurrences[0]['date']) ? $occurrences[0]['date'] : '';\n        $rows[] = $event;\n"""
new = """        $occurrences = surfside_tools_calendar_event_occurrences($event, $today, $range_end);\n        if (empty($occurrences)) {\n            continue;\n        }\n\n        $event['next_occurrence_date'] = $occurrences[0]['date'];\n        $event['manage_range_label'] = '';\n        if (\n            !empty($event['recurrence_end_date']) &&\n            ($event['recurrence_type'] ?? 'none') !== 'none'\n        ) {\n            $start_ts = strtotime(($event['date'] ?? '') . ' 12:00:00');\n            $end_ts = strtotime($event['recurrence_end_date'] . ' 12:00:00');\n            if ($start_ts && $end_ts) {\n                if (date('Y', $start_ts) !== date('Y', $end_ts)) {\n                    $event['manage_range_label'] = date_i18n('F j, Y', $start_ts) . '–' . date_i18n('F j, Y', $end_ts);\n                } elseif (date('m', $start_ts) !== date('m', $end_ts)) {\n                    $event['manage_range_label'] = date_i18n('F j', $start_ts) . '–' . date_i18n('F j, Y', $end_ts);\n                } else {\n                    $event['manage_range_label'] = date_i18n('F j', $start_ts) . '–' . date_i18n('j, Y', $end_ts);\n                }\n            }\n        }\n        $rows[] = $event;\n"""
text = replace_once(text, old, new, 'manage events active filtering')

text = replace_once(
    text,
    "<div class=\"surfside-calendar-status-pill\"><?php echo esc_html($managed_events['total']); ?> event<?php echo $managed_events['total'] === 1 ? '' : 's'; ?></div>",
    "<div class=\"surfside-calendar-status-pill\"><?php echo esc_html($managed_events['total']); ?> active event<?php echo $managed_events['total'] === 1 ? '' : 's'; ?></div>",
    'manager active count label'
)

old_render = """                                    <?php $manage_date = !empty($event['next_occurrence_date']) ? $event['next_occurrence_date'] : $event['date']; ?>\n                                    <p><strong><?php echo esc_html(surfside_tools_calendar_format_date($manage_date)); ?></strong> · <?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></p>\n                                    <?php if (empty($event['next_occurrence_date'])) : ?><p class=\"surfside-calendar-recurrence-label\">No future occurrences</p><?php endif; ?>\n"""
new_render = """                                    <?php $manage_date_label = !empty($event['manage_range_label']) ? $event['manage_range_label'] : surfside_tools_calendar_format_date($event['next_occurrence_date']); ?>\n                                    <p><strong><?php echo esc_html($manage_date_label); ?></strong> · <?php echo esc_html(surfside_tools_calendar_format_time_range($event)); ?></p>\n"""
text = replace_once(text, old_render, new_render, 'manager event date rendering')

# Month shortcode owns its final navigation hooks and anchored fallbacks.
text = replace_once(
    text,
    "function surfside_tools_calendar_month_shortcode($atts = array()) {\n    surfside_tools_calendar_enqueue_styles();",
    "function surfside_tools_calendar_month_shortcode($atts = array()) {\n    surfside_tools_calendar_enqueue_styles();\n    if (function_exists('surfside_tools_month_calendar_navigation_assets')) {\n        surfside_tools_month_calendar_navigation_assets();\n    }",
    'month navigation asset ownership'
)
text = replace_once(text, "$prev_url = esc_url(add_query_arg('surfside_month', $prev_month));", "$prev_url = esc_url(add_query_arg('surfside_month', $prev_month) . '#surfside-month-calendar');", 'previous month anchored URL')
text = replace_once(text, "$next_url = esc_url(add_query_arg('surfside_month', $next_month));", "$next_url = esc_url(add_query_arg('surfside_month', $next_month) . '#surfside-month-calendar');", 'next month anchored URL')
text = replace_once(text, "$today_url = esc_url(remove_query_arg('surfside_month'));", "$today_url = esc_url(remove_query_arg('surfside_month') . '#surfside-month-calendar');", 'today anchored URL')
text = replace_once(
    text,
    "    <div class=\"surfside-month-calendar\" data-month=\"<?php echo esc_attr($month_value); ?>\">",
    "    <div id=\"surfside-month-calendar\" class=\"surfside-month-calendar\" data-month=\"<?php echo esc_attr($month_value); ?>\" data-surfside-month-navigation><span class=\"screen-reader-text\" data-surfside-month-status aria-live=\"polite\"></span>",
    'month navigation wrapper hooks'
)
text = text.replace('class="surfside-month-calendar-nav-button" href="<?php echo $prev_url; ?>"', 'class="surfside-month-calendar-nav-button" href="<?php echo $prev_url; ?>" data-surfside-month-link', 1)
text = text.replace('class="surfside-month-calendar-today" href="<?php echo $today_url; ?>"', 'class="surfside-month-calendar-today" href="<?php echo $today_url; ?>" data-surfside-month-link', 1)
text = text.replace('class="surfside-month-calendar-nav-button" href="<?php echo $next_url; ?>"', 'class="surfside-month-calendar-nav-button" href="<?php echo $next_url; ?>" data-surfside-month-link', 1)
if text.count('data-surfside-month-link') != 3:
    raise SystemExit(f'month navigation links: expected 3 hooks, found {text.count("data-surfside-month-link")}')
path.write_text(text)

# Navigation module: assets only; no finished-shortcode mutation.
path = Path('includes/calendar-month-navigation.php')
text = path.read_text()
text = sub_once(
    text,
    r"\n/\*\*\n \* Add enhancement hooks and a no-JavaScript anchor fallback to the shortcode\.\n \*/\nfunction surfside_tools_month_calendar_navigation_markup\(\$output, \$tag\) \{.*?\nadd_filter\('do_shortcode_tag', 'surfside_tools_month_calendar_navigation_markup', 30, 2\);\n?",
    '\n',
    'month navigation post-render filter',
    re.S,
)
if 'do_shortcode_tag' in text or 'surfside_tools_month_calendar_navigation_markup' in text:
    raise SystemExit('Month navigation post-render filter remains')
path.write_text(text)

# Calendar refinement module: keep only the unrelated suggestion-location copy refinement.
Path('includes/calendar-manager-refinements.php').write_text("""<?php\n\nif (!defined('ABSPATH')) {\n    exit;\n}\n\n/**\n * Small copy refinement for calendar suggestions that still need a venue.\n * Event-list state is rendered authoritatively by Calendar Manager itself.\n */\nfunction surfside_tools_calendar_manager_refinement_assets() {\n    if (!is_user_logged_in() || !current_user_can('upload_files')) {\n        return;\n    }\n\n    global $post;\n    if (!$post instanceof WP_Post || !has_shortcode((string) $post->post_content, 'surfside_tools_calendar_manager')) {\n        return;\n    }\n    ?>\n    <script>\n    document.addEventListener('DOMContentLoaded', function () {\n        document.querySelectorAll('.surfside-calendar-location-required').forEach(function (box) {\n            const label = box.querySelector('label');\n            if (label) {\n                Array.from(label.childNodes).forEach(function (node) {\n                    if (node.nodeType === Node.TEXT_NODE && node.nodeValue.trim()) {\n                        node.nodeValue = 'Where is this event being held? ';\n                    }\n                });\n            }\n            const help = box.querySelector('small');\n            const card = box.closest('.surfside-calendar-suggestion');\n            const meeting = card ? (card.dataset.surfsideMeetingLocation || '') : '';\n            if (help) {\n                help.textContent = meeting\n                    ? 'We found ' + meeting + ', but still need the church, campus, or venue.'\n                    : 'Enter the church, campus, or venue before saving.';\n            }\n        });\n    });\n    </script>\n    <?php\n}\nadd_action('wp_footer', 'surfside_tools_calendar_manager_refinement_assets', 50);\n""")
