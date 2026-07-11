<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Surfside Tools - Front-End Staff Dashboard v1.1.10
 *
 * Public staff pages are intentionally split into focused tools:
 * /dashboard
 * /dashboard/weekly-update
 * /dashboard/calendar
 *
 * Shortcodes:
 * [surfside_staff_dashboard]
 * [surfside_staff_weekly_update]
 * [surfside_staff_calendar]
 */

if (!defined('SURFSIDE_TOOLS_VERSION')) {
    define('SURFSIDE_TOOLS_VERSION', '1.1.10');
}

function surfside_tools_staff_login_box($message = 'Please log in to access Surfside staff tools.') {
    $login_url = wp_login_url(get_permalink());

    return '<div class="surfside-staff-login"><h2>Staff Login Required</h2><p>' . esc_html($message) . '</p><a class="wp-block-button__link wp-element-button" href="' . esc_url($login_url) . '">Log In to Continue</a></div>';
}

function surfside_tools_staff_can_access() {
    return is_user_logged_in() && current_user_can('upload_files');
}

function surfside_tools_staff_enqueue_styles() {
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;

    wp_register_style('surfside-tools-staff-dashboard', false, array(), SURFSIDE_TOOLS_VERSION);
    wp_enqueue_style('surfside-tools-staff-dashboard');

    wp_add_inline_style('surfside-tools-staff-dashboard', '
        .surfside-staff-shell,
        .entry-content .surfside-staff-shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: clamp(24px, 5vw, 54px) 18px;
            color: #071b3a;
            font-family: inherit;
        }
        .surfside-staff-shell * { box-sizing: border-box; }
        .surfside-staff-dashboard-hero {
            margin: -10px calc(50% - 50vw) 34px;
            padding: clamp(34px, 6vw, 64px) max(18px, calc((100vw - 1120px) / 2));
            background:
                radial-gradient(circle at 82% 50%, rgba(197, 221, 249, .56), transparent 34%),
                linear-gradient(135deg, #f8fbff 0%, #edf5ff 54%, #f8fbff 100%);
            border-bottom: 1px solid rgba(7, 27, 58, .06);
        }
        .surfside-staff-dashboard-hero h1 {
            margin: 0 0 10px;
            font-size: clamp(42px, 6vw, 64px);
            line-height: .98;
            letter-spacing: -.045em;
            color: #071b3a;
        }
        .surfside-staff-dashboard-hero p {
            margin: 0;
            max-width: 620px;
            font-size: clamp(17px, 2vw, 22px);
            color: #4b5872;
        }
        .surfside-staff-panel,
        .surfside-staff-card,
        .surfside-staff-login {
            background: rgba(255,255,255,.96);
            border: 1px solid rgba(7, 27, 58, .12);
            border-radius: 18px;
            box-shadow: 0 12px 32px rgba(7, 27, 58, .07);
        }
        .surfside-staff-panel { padding: clamp(22px, 4vw, 34px); margin-bottom: 26px; }
        .surfside-staff-panel-head {
            display: flex;
            gap: 18px;
            align-items: center;
            padding-bottom: 26px;
            border-bottom: 1px solid rgba(7, 27, 58, .12);
        }
        .surfside-staff-icon {
            flex: 0 0 auto;
            width: 66px;
            height: 66px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eaf3ff;
            color: #0b4f9c;
        }
        .surfside-staff-icon svg { width: 34px; height: 34px; stroke: currentColor; stroke-width: 1.8; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .surfside-staff-panel h2,
        .surfside-staff-card h2,
        .surfside-staff-card h3 { margin: 0; color: #071b3a; letter-spacing: -.025em; }
        .surfside-staff-panel h2 { font-size: clamp(24px, 3vw, 30px); }
        .surfside-staff-card h2 { font-size: clamp(25px, 3vw, 32px); }
        .surfside-staff-muted { color: #4b5872; margin: 6px 0 0; }
        .surfside-staff-status {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            margin-top: 28px;
        }
        .surfside-staff-status-item {
            display: grid;
            grid-template-columns: 96px 1fr;
            gap: 20px;
            align-items: center;
            min-height: 116px;
            padding: 8px 32px;
        }
        .surfside-staff-status-item + .surfside-staff-status-item { border-left: 1px solid rgba(7, 27, 58, .14); }
        .surfside-staff-status-item strong { display: block; font-size: 19px; margin-bottom: 6px; color: #071b3a; }
        .surfside-staff-status-text { color: #28344e; font-size: 16px; line-height: 1.45; }
        .surfside-staff-published { display: inline-flex; align-items: center; gap: 7px; margin-top: 8px; color: #148944; font-weight: 700; }
        .surfside-staff-published svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2; fill: none; }
        .surfside-staff-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
        .surfside-staff-card {
            min-height: 240px;
            padding: 28px 26px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            text-align: center;
        }
        .surfside-staff-card .surfside-staff-icon { width: 62px; height: 62px; margin-bottom: 16px; }
        .surfside-staff-card .surfside-staff-icon svg { width: 30px; height: 30px; }
        .surfside-staff-card h2 { font-size: clamp(23px, 2.2vw, 29px); line-height: 1.08; }
        .surfside-staff-card p { color: #3f4a62; line-height: 1.45; margin: 12px auto 20px; max-width: 250px; }
        .surfside-staff-actions { margin-top: auto; width: 100%; }
        .surfside-staff-button,
        .surfside-staff-button-secondary,
        .surfside-staff-button-disabled {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            min-height: 50px;
            border-radius: 8px;
            padding: 11px 18px;
            font-weight: 700;
            text-decoration: none;
            transition: transform .12s ease, box-shadow .12s ease, background .12s ease;
        }
        .surfside-staff-button { background: #0b4f9c; color: #fff; box-shadow: 0 10px 18px rgba(11, 79, 156, .22); }
        .surfside-staff-button:hover,
        .surfside-staff-button:focus { color: #fff; background: #083f7d; transform: translateY(-1px); }
        .surfside-staff-button-secondary { background: #fff; color: #0b4f9c; border: 1px solid #0b4f9c; }
        .surfside-staff-button-secondary:hover,
        .surfside-staff-button-secondary:focus { background: #f3f8ff; color: #083f7d; }
        .surfside-staff-button-disabled { background: #f1f5f9; color: #637086; border: 1px solid #dbe3ec; cursor: not-allowed; }
        .surfside-staff-arrow { font-size: 24px; line-height: 1; }
        .surfside-staff-login { max-width: 720px; margin: 36px auto; padding: 26px; }
        .surfside-staff-back { margin-bottom: 18px; }
        .surfside-staff-back a { text-decoration: none; font-weight: 700; color: #0b4f9c; }
        .surfside-staff-hero { padding: clamp(22px, 4vw, 34px); margin-bottom: 24px; }
        .surfside-staff-eyebrow { text-transform: uppercase; letter-spacing: .08em; font-size: 13px; font-weight: 800; color: #51627a; margin: 0 0 8px; }
        .surfside-staff-hero h1 { margin: 0 0 8px; line-height: 1.05; color: #071b3a; }
        .surfside-staff-weekly .surfside-weekly-update-tool { max-width: 100%; }
        .surfside-staff-weekly .surfside-weekly-update-tool h2:first-child { display: none; }
        @media (max-width: 900px) {
            .surfside-staff-grid { grid-template-columns: 1fr; }
            .surfside-staff-card { min-height: auto; }
            .surfside-staff-status { grid-template-columns: 1fr; }
            .surfside-staff-status-item { grid-template-columns: 72px 1fr; padding: 18px 0; }
            .surfside-staff-status-item + .surfside-staff-status-item { border-left: 0; border-top: 1px solid rgba(7, 27, 58, .14); }
        }
        @media (max-width: 600px) {
            .surfside-staff-dashboard-hero { margin-top: 0; }
            .surfside-staff-panel-head { align-items: flex-start; }
            .surfside-staff-status-item { grid-template-columns: 1fr; text-align: left; }
            .surfside-staff-status-item .surfside-staff-icon { display: none; }
            .surfside-staff-card { grid-template-columns: 1fr; text-align: center; justify-items: center; }
            .surfside-staff-card .surfside-staff-icon { grid-row: auto; margin-bottom: 10px; }
        }
    ');
}

function surfside_tools_staff_page_url($path = '') {
    $page = get_page_by_path(trim('dashboard/' . trim($path, '/'), '/'));

    if ($page) {
        return get_permalink($page);
    }

    return home_url('/dashboard/' . trim($path, '/'));
}

function surfside_tools_staff_icon($name) {
    $icons = array(
        'document' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h4"/></svg>',
        'megaphone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11v2a2 2 0 0 0 2 2h3l5 4V5L8 9H5a2 2 0 0 0-2 2z"/><path d="M16 9.5a3.5 3.5 0 0 1 0 5"/><path d="M8 15l1 5"/></svg>',
        'book' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H7a3 3 0 0 0-3 3z"/><path d="M4 5.5V22"/><path d="M8 7h8"/></svg>',
        'upload' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M20 16.5A4.5 4.5 0 0 0 15.5 12h-.6A6 6 0 1 0 6 18h13a3 3 0 0 0 1-5.8"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4"/><path d="M16 3v4"/><path d="M4 10h16"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 8 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 8a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.1A1.7 1.7 0 0 0 16 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.18.37.48.7.9.9.27.13.56.2.86.2H21a2 2 0 1 1 0 4h-.1A1.7 1.7 0 0 0 19.4 15z"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12l2.5 2.5L16 9"/></svg>',
    );

    return isset($icons[$name]) ? $icons[$name] : '';
}

function surfside_tools_staff_dashboard_shortcode() {
    surfside_tools_prevent_cache();
    surfside_tools_staff_enqueue_styles();

    if (!is_user_logged_in()) {
        return surfside_tools_staff_login_box();
    }

    if (!current_user_can('upload_files')) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to access Surfside staff tools.</p></div>';
    }

    $announcements = function_exists('surfside_tools_get_announcements_data') ? surfside_tools_get_announcements_data() : array();
    $message = function_exists('surfside_tools_get_message_data') ? surfside_tools_get_message_data() : array();

    $announcements_date = !empty($announcements['announcement_date']) ? $announcements['announcement_date'] : 'Not set';
    $message_title = !empty($message['title']) ? $message['title'] : 'Not set';
    $message_date = !empty($message['date']) ? $message['date'] : '';

    ob_start();
    ?>
    <section class="surfside-staff-dashboard-hero">
        <h1>Staff Dashboard</h1>
        <p>Tools to keep our website content up to date.</p>
    </section>

    <div class="surfside-staff-shell">
        <section class="surfside-staff-panel">
            <div class="surfside-staff-panel-head">
                <span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('document'); ?></span>
                <div>
                    <h2>At a Glance</h2>
                    <p class="surfside-staff-muted">Here’s what was last published.</p>
                </div>
            </div>

            <div class="surfside-staff-status">
                <div class="surfside-staff-status-item">
                    <span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('megaphone'); ?></span>
                    <div class="surfside-staff-status-text">
                        <strong>Current Announcements</strong>
                        <?php echo esc_html($announcements_date); ?>
                        <?php if ($announcements_date !== 'Not set') : ?>
                            <span class="surfside-staff-published"><?php echo surfside_tools_staff_icon('check'); ?> Published</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="surfside-staff-status-item">
                    <span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('book'); ?></span>
                    <div class="surfside-staff-status-text">
                        <strong>Current Sermon Notes</strong>
                        <?php echo esc_html($message_title); ?><?php echo $message_date ? '<br>' . esc_html($message_date) : ''; ?>
                        <?php if ($message_title !== 'Not set') : ?>
                            <span class="surfside-staff-published"><?php echo surfside_tools_staff_icon('check'); ?> Published</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <div class="surfside-staff-grid">
            <article class="surfside-staff-card">
                <span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('upload'); ?></span>
                <h2>Weekly Update</h2>
                <p>Upload DOCX files, review, and publish.</p>
                <div class="surfside-staff-actions">
                    <a class="surfside-staff-button" href="<?php echo esc_url(surfside_tools_staff_page_url('weekly-update')); ?>">Go to Weekly Update <span class="surfside-staff-arrow">›</span></a>
                </div>
            </article>

            <article class="surfside-staff-card surfside-staff-coming-soon">
                <span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('calendar'); ?></span>
                <h2>Calendar</h2>
                <p>Manage church calendar events.</p>
                <div class="surfside-staff-actions">
                    <a class="surfside-staff-button-secondary" href="<?php echo esc_url(surfside_tools_staff_page_url('calendar')); ?>">Go to Calendar <span class="surfside-staff-arrow">›</span></a>
                </div>
            </article>

            <article class="surfside-staff-card">
                <span class="surfside-staff-icon"><?php echo surfside_tools_staff_icon('settings'); ?></span>
                <h2>Settings</h2>
                <p>Manage Google Maps and calendar preferences.</p>
                <div class="surfside-staff-actions">
                    <a class="surfside-staff-button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=surfside-tools-settings')); ?>">Go to Settings <span class="surfside-staff-arrow">›</span></a>
                </div>
            </article>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_staff_dashboard', 'surfside_tools_staff_dashboard_shortcode');

function surfside_tools_staff_weekly_update_shortcode() {
    surfside_tools_prevent_cache();
    surfside_tools_staff_enqueue_styles();

    if (!is_user_logged_in()) {
        return surfside_tools_staff_login_box('Please log in to publish weekly updates.');
    }

    if (!current_user_can('upload_files')) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to upload weekly update documents.</p></div>';
    }

    ob_start();
    ?>
    <div class="surfside-staff-shell surfside-staff-weekly">
        <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">← Back to Dashboard</a></div>
        <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Weekly Update</p>
            <h1>Upload This Week&apos;s Documents</h1>
            <p class="surfside-staff-muted">Use this page for announcements and sermon notes only. You can upload one document or both documents, review the results, then publish.</p>
        </section>
        <?php echo surfside_tools_weekly_update_shortcode(); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_staff_weekly_update', 'surfside_tools_staff_weekly_update_shortcode');

function surfside_tools_staff_calendar_shortcode() {
    surfside_tools_prevent_cache();
    surfside_tools_staff_enqueue_styles();

    if (!is_user_logged_in()) {
        return surfside_tools_staff_login_box('Please log in to access the calendar manager.');
    }

    if (!current_user_can('upload_files')) {
        return '<div class="surfside-staff-shell"><p>You do not have permission to access the calendar manager.</p></div>';
    }

    ob_start();
    ?>
    <div class="surfside-staff-shell">
        <div class="surfside-staff-back"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">← Back to Dashboard</a></div>
        <section class="surfside-staff-hero">
            <p class="surfside-staff-eyebrow">Calendar Manager</p>
            <h1>Manage Events</h1>
            <p class="surfside-staff-muted">Add and edit upcoming church events from a focused staff page.</p>
        </section>
        <?php echo do_shortcode('[surfside_tools_calendar_manager]'); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_staff_calendar', 'surfside_tools_staff_calendar_shortcode');

function surfside_tools_ensure_staff_page($title, $slug, $content, $parent_id = 0) {
    $path = $parent_id ? get_post_field('post_name', $parent_id) . '/' . $slug : $slug;
    $existing = get_page_by_path($path);

    if ($existing) {
        $current_content = (string) $existing->post_content;
        $known_shortcodes = array('surfside_staff_dashboard', 'surfside_staff_weekly_update', 'surfside_staff_calendar');
        $has_known_shortcode = false;

        foreach ($known_shortcodes as $shortcode) {
            if (strpos($current_content, '[' . $shortcode . ']') !== false) {
                $has_known_shortcode = true;
                break;
            }
        }

        if (trim($current_content) === '' || $has_known_shortcode) {
            wp_update_post(array(
                'ID' => $existing->ID,
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => 'publish',
                'post_parent' => $parent_id,
            ));
        }

        return (int) $existing->ID;
    }

    return (int) wp_insert_post(array(
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $parent_id,
    ));
}

function surfside_tools_ensure_staff_dashboard_pages() {
    if (!is_admin()) {
        return;
    }

    $installed_version = get_option('surfside_tools_version');

    if ($installed_version === SURFSIDE_TOOLS_VERSION) {
        return;
    }

    $dashboard_id = surfside_tools_ensure_staff_page('Staff Dashboard', 'dashboard', '[surfside_staff_dashboard]');

    if ($dashboard_id) {
        surfside_tools_ensure_staff_page('Weekly Update', 'weekly-update', '[surfside_staff_weekly_update]', $dashboard_id);
        surfside_tools_ensure_staff_page('Calendar', 'calendar', '[surfside_staff_calendar]', $dashboard_id);
    }

    update_option('surfside_tools_version', SURFSIDE_TOOLS_VERSION);
    flush_rewrite_rules(false);
}
add_action('admin_init', 'surfside_tools_ensure_staff_dashboard_pages');


