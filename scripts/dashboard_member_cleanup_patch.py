from pathlib import Path
import re


def replace_once(text, old, new, label):
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 occurrence, found {count}")
    return text.replace(old, new, 1)


def regex_once(text, pattern, replacement, label, flags=0):
    updated, count = re.subn(pattern, replacement, text, count=1, flags=flags)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 regex match, found {count}")
    return updated


# Retire the obsolete first-generation Dashboard renderer while retaining the
# shared staff styles/helpers and Weekly Update/Calendar wrappers in this file.
path = Path('includes/staff-dashboard.php')
text = path.read_text()
text = regex_once(
    text,
    r"\nfunction surfside_tools_staff_dashboard_shortcode\(\) \{.*?\n\}\nadd_shortcode\('surfside_staff_dashboard', 'surfside_tools_staff_dashboard_shortcode'\);\n",
    "\n",
    'obsolete dashboard renderer',
    re.S,
)
path.write_text(text)


# The current dashboard is authoritative; register it directly instead of
# removing/replacing an earlier shortcode generation at init priority 60.
path = Path('includes/dashboard-overview.php')
text = path.read_text()
text = replace_once(
    text,
    """add_action('init', function () {
    remove_shortcode('surfside_staff_dashboard');
    add_shortcode('surfside_staff_dashboard', 'surfside_tools_dashboard_overview_shortcode');
}, 60);
""",
    """add_shortcode('surfside_staff_dashboard', 'surfside_tools_dashboard_overview_shortcode');
""",
    'dashboard shortcode replacement hook',
)
path.write_text(text)


# Remove the Settings post-render rewrite that only existed to fix the old
# dashboard renderer's wp-admin Settings link.
path = Path('includes/frontend-settings.php')
text = path.read_text()
old_filter = """
add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'surfside_staff_dashboard') {
        return $output;
    }
    $admin_url = admin_url('admin.php?page=surfside-tools-settings');
    $front_url = function_exists('surfside_tools_staff_page_url') ? surfside_tools_staff_page_url('settings') : home_url('/dashboard/settings/');
    return str_replace(esc_url($admin_url), esc_url($front_url), $output);
}, 10, 2);"""
text = replace_once(text, old_filter, '', 'dashboard Settings post-render filter')
path.write_text(text)


# Volunteer Needs belongs to Member Engagement; stop loading it as a side
# effect of the Mobile App hub.
path = Path('includes/mobile-app-dashboard.php')
text = path.read_text()
text = replace_once(
    text,
    "require_once SURFSIDE_TOOLS_PATH . 'includes/volunteer-needs.php';\n",
    '',
    'mobile app volunteer-needs include',
)
path.write_text(text)


# Make the Volunteer Needs manager render its final Member Engagement identity
# itself, while retaining the public mobile API and existing persistence.
path = Path('includes/volunteer-needs.php')
text = path.read_text()
text = replace_once(
    text,
    '/** Current volunteer needs for the Surfside mobile app. */',
    '/** Current volunteer needs for Surfside member engagement and the mobile app. */',
    'volunteer module description',
)
text = replace_once(
    text,
    "$back_url = remove_query_arg('view', surfside_tools_staff_page_url('mobile-app'));",
    "$back_url = function_exists('surfside_tools_member_engagement_url') ? surfside_tools_member_engagement_url() : surfside_tools_staff_page_url('');",
    'volunteer back URL',
)
text = replace_once(text, '← Back to Manage Mobile App', '← Back to Member Engagement', 'volunteer back label')
text = replace_once(
    text,
    '<p class="surfside-staff-eyebrow">Mobile App</p>',
    '<p class="surfside-staff-eyebrow">Member Engagement</p>',
    'volunteer eyebrow',
)
text = replace_once(
    text,
    'Publish timely serving opportunities in the Surfside app. Keep only current needs active; members will use the app’s existing Connect form to respond.',
    'Publish timely serving opportunities for the Surfside church community. Active needs appear in the mobile app, where members can use the existing Connect form to respond.',
    'volunteer description',
)
path.write_text(text)


# Member Engagement no longer needs to mutate Volunteer Needs HTML after render.
path = Path('includes/member-engagement.php')
text = path.read_text()
old = """    if ($tool === 'volunteer-needs' && function_exists('surfside_tools_staff_volunteer_needs_view')) {
        $html = surfside_tools_staff_volunteer_needs_view();
        $back = '<div class=\"surfside-staff-back\"><a href=\"' . esc_url(surfside_tools_member_engagement_url()) . '\">← Back to Member Engagement</a></div>';
        $html = preg_replace('/<div class=\"surfside-staff-back\"><a href=\"[^\"]+\">← Back to Manage Mobile App<\\/a><\\/div>/', $back, $html, 1);
        $html = str_replace('<p class=\"surfside-staff-eyebrow\">Mobile App</p>', '<p class=\"surfside-staff-eyebrow\">Member Engagement</p>', $html);
        $html = str_replace('Publish timely serving opportunities in the Surfside app.', 'Publish timely serving opportunities for the Surfside church community.', $html);
        return $html;
    }
"""
new = """    if ($tool === 'volunteer-needs' && function_exists('surfside_tools_staff_volunteer_needs_view')) {
        return surfside_tools_staff_volunteer_needs_view();
    }
"""
text = replace_once(text, old, new, 'Member Engagement volunteer rewrite')
path.write_text(text)


# Load Volunteer Needs explicitly from the plugin bootstrap near Member
# Engagement, instead of through the unrelated Mobile App hub.
path = Path('surfside-tools.php')
text = path.read_text()
needle = "require_once SURFSIDE_TOOLS_PATH . 'includes/member-engagement.php';\n"
replacement = "require_once SURFSIDE_TOOLS_PATH . 'includes/volunteer-needs.php';\n" + needle
text = replace_once(text, needle, replacement, 'bootstrap volunteer-needs ownership')
path.write_text(text)
