<?php
/**
 * Staff workspace navigation, shared search index and breadcrumb shell.
 *
 * Phase 1 is navigation-only: no changes to existing staff shortcode routes,
 * persisted options, upload handlers, roles, or the WordPress page tree.
 */
if (!defined('ABSPATH')) { exit; }

/** A single capability-filtered index powers desktop nav, mobile nav and search. */
function surfside_tools_workspace_catalog() {
    $home = surfside_tools_staff_page_url('');
    $settings = surfside_tools_staff_page_url('settings');
    $messages = function_exists('surfside_tools_youtube_manager_url')
        ? surfside_tools_youtube_manager_url()
        : add_query_arg('view', 'message-archive', surfside_tools_staff_page_url('site-management'));
    $roku = function_exists('surfside_tools_r2_manager_url')
        ? surfside_tools_r2_manager_url()
        : add_query_arg('view', 'roku-media', surfside_tools_staff_page_url('site-management'));
    $engagement = add_query_arg('view', 'member-engagement', $home);
    $archive = add_query_arg('view', 'sermon-archive', $home);

    $groups = array(
        'content' => array('title' => 'Content & Publishing', 'items' => array(
            array('label' => 'Weekly Update', 'url' => surfside_tools_staff_page_url('weekly-update'), 'search' => 'announcements sermon notes publish documents'),
            array('label' => 'Archived Sermon Notes', 'url' => $archive, 'search' => 'past notes search weekly message'),
            array('label' => 'Featured Announcement', 'url' => surfside_tools_staff_page_url('mobile-app/featured-announcement'), 'search' => 'app home feature scheduled banner'),
            array('label' => 'Ministries', 'url' => surfside_tools_staff_page_url('site-ministries'), 'search' => 'ministry descriptions staff contact groups', 'cap' => 'manage_options'),
        )),
        'media' => array('title' => 'Messages & Media', 'items' => array(
            array('label' => 'Message Archive', 'url' => $messages, 'search' => 'YouTube sermons metadata videos review'),
            array('label' => 'Sermon Series', 'url' => add_query_arg('tab', 'series', $messages), 'search' => 'YouTube playlists grouping'),
            array('label' => 'Roku MP4 Uploads', 'url' => $roku, 'search' => 'R2 cloudflare video copies find this week message'),
            array('label' => 'Streaming', 'url' => surfside_tools_staff_page_url('site-settings/streaming'), 'search' => 'Cloudflare live recording exclusions livestream notifications Twitch Facebook YouTube Watch Live announcement video connection settings', 'cap' => 'manage_options'),
            array('label' => 'Live Slides', 'url' => surfside_tools_staff_page_url('mobile-app/live-slides'), 'search' => 'ProPresenter slides viewer colors fonts'),
        )),
        'events' => array('title' => 'Events & Serving', 'items' => array(
            array('label' => 'Calendar Manager', 'url' => surfside_tools_staff_page_url('calendar'), 'search' => 'events dates recurrence activities'),
            array('label' => 'Volunteer Needs', 'url' => add_query_arg('tool', 'volunteer-needs', $engagement), 'search' => 'serving opportunities help wanted'),
            array('label' => 'Calendar Defaults', 'url' => add_query_arg('view', 'defaults', surfside_tools_staff_page_url('calendar')), 'search' => 'calendar week mode current sunday saturday next seven days duration event defaults', 'cap' => 'manage_options'),
            array('label' => 'Saved Places', 'url' => add_query_arg('view', 'places', surfside_tools_staff_page_url('calendar')), 'search' => 'calendar location saved places addresses remove restore previously used', 'cap' => 'manage_options'),
        )),
        'community' => array('title' => 'Community & Communications', 'items' => array(
            array('label' => 'Prayer Requests', 'url' => add_query_arg('tool', 'prayer-requests', $engagement), 'search' => 'prayers praises pending review prayer list'),
            array('label' => 'Push Notifications', 'url' => surfside_tools_staff_page_url('mobile-app/push-notifications'), 'search' => 'app announcements alerts message audience send'),
            array('label' => 'Contact Routing', 'url' => surfside_tools_staff_page_url('contact-routing'), 'search' => 'email recipient website contact form prayer', 'cap' => 'manage_options'),
        )),
        'appearance' => array('title' => 'Appearance', 'items' => array(
            array('label' => 'Website Navigation', 'url' => surfside_tools_staff_page_url('site-navigation'), 'search' => 'header footer menus links', 'cap' => 'manage_options'),
            array('label' => 'Homepage Photos', 'url' => surfside_tools_staff_page_url('homepage'), 'search' => 'website carousel pictures gallery'),
            array('label' => 'App Home Experience', 'url' => surfside_tools_staff_page_url('mobile-app/home-experience'), 'search' => 'mobile hero image crop zoom'),
            array('label' => 'Reveal & Countdown Styling', 'url' => $settings . '#surfside-focus-reveal-countdown-styling', 'search' => 'visual CSS selectors design', 'cap' => 'manage_options'),
        )),
        'settings' => array('title' => 'Settings & Access', 'items' => array(
            array('label' => 'Surfside Information', 'url' => surfside_tools_staff_page_url('surfside-information'), 'search' => 'church identity service times location social links', 'cap' => 'manage_options'),
            array('label' => 'Integrations', 'url' => $settings, 'search' => 'connections configuration APIs', 'cap' => 'manage_options'),
            array('label' => 'Google Maps', 'url' => $settings . '#surfside-focus-google-maps', 'search' => 'places api key', 'cap' => 'manage_options'),
            array('label' => 'YouTube Integration', 'url' => $settings . '#surfside-focus-youtube-sermon-archive', 'search' => 'data API full sync playlist key', 'cap' => 'manage_options'),
            array('label' => 'YouVersion Integration', 'url' => $settings . '#surfside-focus-youversion', 'search' => 'Bible scripture passage API', 'cap' => 'manage_options'),
            array('label' => 'Giving', 'url' => $settings . '#surfside-focus-giving', 'search' => 'tithes donations giving form', 'cap' => 'manage_options'),
            array('label' => 'Cloudflare Turnstile', 'url' => $settings . '#surfside-focus-cloudflare-turnstile', 'search' => 'captcha spam protection', 'cap' => 'manage_options'),
            array('label' => 'Staff Access', 'url' => surfside_tools_staff_page_url('access'), 'search' => 'users permissions roles login Google account', 'cap' => 'tools_admin'),
        )),
    );

    foreach ($groups as $key => &$group) {
        $group['key'] = $key;
        $group['items'] = array_values(array_filter($group['items'], function ($item) {
            if (($item['cap'] ?? '') === 'tools_admin') {
                return function_exists('surfside_tools_current_user_is_tools_admin')
                    && surfside_tools_current_user_is_tools_admin();
            }
            return empty($item['cap']) || current_user_can($item['cap']);
        }));
    }
    unset($group);
    return array_filter($groups, function ($group) { return !empty($group['items']); });
}

/** Restrict the workspace shell to authenticated staff dashboard pages. */
function surfside_tools_workspace_is_staff_page() {
    if (is_admin() || !is_page() || !is_user_logged_in() || !current_user_can('upload_files')) {
        return false;
    }
    $id = (int) get_queried_object_id();
    if (!$id) return false;
    $path = trim((string) get_page_uri($id), '/');
    return $path === 'dashboard' || strpos($path, 'dashboard/') === 0;
}

function surfside_tools_workspace_current($groups) {
    $current_path = (string) wp_parse_url((string) get_permalink(get_queried_object_id()), PHP_URL_PATH);
    $current_view = isset($_GET['view']) && is_string($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
    $current_tool = isset($_GET['tool']) && is_string($_GET['tool']) ? sanitize_key(wp_unslash($_GET['tool'])) : '';
    $current_tab = isset($_GET['tab']) && is_string($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    // URL fragments never reach PHP; Integrations focus is handled by workspace.js.
    $best = null;
    $score = -1;
    foreach ($groups as $group) {
        foreach ($group['items'] as $item) {
            $parts = wp_parse_url($item['url']);
            if (!is_array($parts) || (string) ($parts['path'] ?? '') !== $current_path) continue;
            $query = array();
            parse_str((string) ($parts['query'] ?? ''), $query);
            if (isset($query['view']) && $query['view'] !== $current_view) continue;
            if (isset($query['tool']) && $query['tool'] !== $current_tool) continue;
            if (isset($query['tab']) && $query['tab'] !== $current_tab) continue;
            if (!isset($query['view']) && $current_view !== '') continue;
            // A fragment-specific settings item is only selected in JS.
            if (!empty($parts['fragment'])) continue;
            $candidate = count($query);
            if ($candidate > $score) {
                $score = $candidate;
                $best = array('group' => $group['title'], 'label' => $item['label'], 'url' => $item['url']);
            }
        }
    }
    if ($best) return $best;
    $uri = trim((string) get_page_uri(get_queried_object_id()), '/');
    if ($uri === 'dashboard') {
        return array('group' => '', 'label' => 'Dashboard', 'url' => surfside_tools_staff_page_url(''));
    }
    $known = array(
        'dashboard/site-management' => array('group'=>'Appearance','label'=>'Website'),
        'dashboard/mobile-app' => array('group'=>'Appearance','label'=>'Mobile App'),
        'dashboard/site-settings' => array('group'=>'Settings & Access','label'=>'Church Settings'),
    );
    if (isset($known[$uri])) return $known[$uri] + array('url' => get_permalink());
    return array('group' => '', 'label' => get_the_title(get_queried_object_id()), 'url' => get_permalink());
}

function surfside_tools_workspace_navigation($groups, $active) {
    ob_start(); ?>
    <aside class="surfside-workspace-sidebar" id="surfside-workspace-sidebar" aria-label="Surfside Tools navigation">
      <div class="surfside-workspace-brand"><a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">Surfside <strong>Tools</strong></a><button type="button" class="surfside-workspace-close" data-ss-close aria-label="Close navigation">×</button></div>
      <label class="surfside-workspace-search-label" for="surfside-workspace-search">Find a tool</label>
      <input id="surfside-workspace-search" type="search" placeholder="Search tools (Ctrl+K)" autocomplete="off" data-ss-search aria-controls="surfside-workspace-links">
      <nav id="surfside-workspace-links" aria-label="Management tools">
        <a class="surfside-workspace-home<?php echo $active['label'] === 'Dashboard' ? ' is-active' : ''; ?>" href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>" <?php if ($active['label'] === 'Dashboard') echo 'aria-current="page"'; ?>>Dashboard</a>
        <?php foreach ($groups as $group) : ?>
          <div class="surfside-workspace-group" data-ss-group>
            <h2><?php echo esc_html($group['title']); ?></h2>
            <ul>
              <?php foreach ($group['items'] as $item) :
                $selected = $active['label'] === $item['label'] && $active['group'] === $group['title']; ?>
                <li><a data-ss-link data-keywords="<?php echo esc_attr($item['search'] ?? ''); ?>" class="<?php echo $selected ? 'is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>" <?php if ($selected) echo 'aria-current="page"'; ?>><?php echo esc_html($item['label']); ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </nav>
      <p class="surfside-workspace-no-results" data-ss-empty role="status" hidden>No matching tools. Try another term.</p>
    </aside>
    <?php return ob_get_clean();
}

function surfside_tools_workspace_wrap_content($content) {
    if (!surfside_tools_workspace_is_staff_page() || !is_main_query() || !in_the_loop()
        || (int) get_the_ID() !== (int) get_queried_object_id()) {
        return $content;
    }
    $groups = surfside_tools_workspace_catalog();
    $active = surfside_tools_workspace_current($groups);
    ob_start(); ?>
    <div class="surfside-workspace" data-surfside-workspace>
      <div class="surfside-workspace-scrim" data-ss-close hidden></div>
      <?php echo surfside_tools_workspace_navigation($groups, $active); // Escaped inside renderer. ?>
      <div class="surfside-workspace-main">
        <div class="surfside-workspace-toolbar">
          <button type="button" class="surfside-workspace-menu" data-ss-open aria-controls="surfside-workspace-sidebar" aria-expanded="false"><span aria-hidden="true">☰</span> Tools & Search</button>
          <nav class="surfside-workspace-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo esc_url(surfside_tools_staff_page_url('')); ?>">Dashboard</a>
            <?php if ($active['group'] !== '') : ?><span aria-hidden="true">›</span><span><?php echo esc_html($active['group']); ?></span><?php endif; ?>
            <?php if ($active['label'] !== 'Dashboard') : ?><span aria-hidden="true">›</span><span aria-current="page"><?php echo esc_html($active['label']); ?></span><?php endif; ?>
          </nav>
        </div>
        <div class="surfside-workspace-content"><?php echo $content; // Existing shortcode output, already rendered by WordPress. ?></div>
      </div>
    </div>
    <?php return ob_get_clean();
}
add_filter('the_content', 'surfside_tools_workspace_wrap_content', 99);

/**
 * Asset URL version follows the file's actual content, not the plugin-wide
 * version (which can remain 3.3.0 across several GitHub deployments).
 * Only computed on staff dashboard requests; nothing affects public assets.
 */
function surfside_tools_workspace_asset_version($relative_path) {
    if (!defined('SURFSIDE_TOOLS_PATH')) return SURFSIDE_TOOLS_VERSION;
    $file = SURFSIDE_TOOLS_PATH . ltrim($relative_path, '/');
    if (!is_file($file) || !is_readable($file)) return SURFSIDE_TOOLS_VERSION;
    $digest = hash_file('sha256', $file);
    return $digest ? substr($digest, 0, 12) : SURFSIDE_TOOLS_VERSION;
}

function surfside_tools_workspace_enqueue_assets() {
    if (!surfside_tools_workspace_is_staff_page()) return;
    $css = 'assets/css/staff-workspace.css';
    $js = 'assets/js/staff-workspace.js';
    wp_enqueue_style('surfside-workspace', SURFSIDE_TOOLS_URL . $css, array(), surfside_tools_workspace_asset_version($css));
    wp_enqueue_script('surfside-workspace', SURFSIDE_TOOLS_URL . $js, array(), surfside_tools_workspace_asset_version($js), true);

    // One shared presentation layer for staff care screens; do not load it on
    // the public website, other staff management pages, or anonymous visits.
    $uri = trim((string) get_page_uri(get_queried_object_id()), '/');
    $view = isset($_GET['view']) && is_string($_GET['view'])
        ? sanitize_key(wp_unslash($_GET['view'])) : '';
    if (($uri === 'dashboard' && $view === 'member-engagement')
        || $uri === 'dashboard/contact-routing') {
        $care_css = 'assets/css/member-care-workspace.css';
        wp_enqueue_style('surfside-member-care-workspace', SURFSIDE_TOOLS_URL . $care_css,
            array('surfside-workspace'), surfside_tools_workspace_asset_version($care_css));
    }

    // Publishing/media styling is loaded only for the three related staff
    // management screens, never on a public sermon or announcement page.
    if (($uri === 'dashboard' && $view === 'sermon-archive')
        || ($uri === 'dashboard/site-management' && $view === 'message-archive')
        || $uri === 'dashboard/mobile-app/featured-announcement') {
        $content_css = 'assets/css/content-media-workspace.css';
        wp_enqueue_style('surfside-content-media-workspace', SURFSIDE_TOOLS_URL . $content_css,
            array('surfside-workspace'), surfside_tools_workspace_asset_version($content_css));
    }
}
add_action('wp_enqueue_scripts', 'surfside_tools_workspace_enqueue_assets');
