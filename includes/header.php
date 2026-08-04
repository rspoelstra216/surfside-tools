<?php
/**
 * Plugin-owned site header.
 *
 * @package SurfsideTools
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load header assets before WordPress prints the document head.
 */
function surfside_tools_header_assets() {
    wp_enqueue_style(
        'surfside-tools-header',
        SURFSIDE_TOOLS_URL . 'assets/css/header.css',
        array('surfside-tools-design-system'),
        SURFSIDE_TOOLS_VERSION
    );

    wp_enqueue_script(
        'surfside-tools-header',
        SURFSIDE_TOOLS_URL . 'assets/js/header.js',
        array(),
        SURFSIDE_TOOLS_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'surfside_tools_header_assets', 6);

function surfside_tools_header_link_role($link) {
    $label = strtolower(trim((string) ($link['label'] ?? '')));
    $url = strtolower(surfside_tools_site_information_navigation_url($link));

    if ($label === 'plan your visit' || strpos($url, '/plan-your-visit') !== false) {
        return 'visit';
    }
    if ($label === 'watch live' || strpos($url, '/watch-live') !== false) {
        return 'watch';
    }

    return '';
}

function surfside_tools_header_link_is_current($url) {
    $link_host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    $site_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    if ($link_host !== '' && $site_host !== '' && $link_host !== $site_host) {
        return false;
    }

    $current_path = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $link_path = (string) wp_parse_url($url, PHP_URL_PATH);
    $normalize = static function ($path) {
        $path = '/' . ltrim(rawurldecode((string) $path), '/');
        return $path === '/' ? '/' : untrailingslashit($path);
    };

    return $normalize($current_path) === $normalize($link_path);
}

function surfside_tools_header_shortcode() {
    $information = surfside_tools_get_site_information();
    $identity = $information['identity'] ?? array();
    $navigation = $information['navigation'] ?? array();
    $logo_url = surfside_tools_site_information_logo_url($information);
    $menu_id = wp_unique_id('surfside-site-header-menu-');

    $livestream_state = function_exists('surfside_tools_next_service')
        ? surfside_tools_next_service(true)
        : array('live' => null, 'live_end' => null);
    $is_live = !empty($livestream_state['live']);
    $live_until = '';
    if ($is_live && !empty($livestream_state['live_end']) && $livestream_state['live_end'] instanceof DateTimeInterface) {
        $live_until = (string) ($livestream_state['live_end']->getTimestamp() * 1000);
    }

    ob_start();
    ?>
    <header class="surfside-site-header surfside-section<?php echo $is_live ? ' surfside-site-header--live' : ''; ?>" data-surfside-header<?php echo $live_until !== '' ? ' data-surfside-live-until="' . esc_attr($live_until) . '"' : ''; ?>>
        <div class="surfside-site-header__accent" aria-hidden="true"></div>
        <div class="surfside-site-header__inner">
            <a class="surfside-site-header__brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(($identity['name'] ?? 'Surfside Community Fellowship') . ' home'); ?>">
                <img class="surfside-site-header__logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($identity['name'] ?? 'Surfside Community Fellowship'); ?>">
            </a>

            <button class="surfside-site-header__toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr($menu_id); ?>" data-surfside-menu-toggle>
                <span class="surfside-site-header__toggle-icon" aria-hidden="true"><span></span><span></span><span></span></span>
                <span class="screen-reader-text">Open navigation menu</span>
            </button>

            <nav id="<?php echo esc_attr($menu_id); ?>" class="surfside-site-header__nav" aria-label="Primary navigation" data-surfside-menu>
                <ul>
                    <?php foreach ($navigation as $link) :
                        $url = surfside_tools_site_information_navigation_url($link);
                        $label = trim((string) ($link['label'] ?? ''));
                        if ($url === '' || $label === '') {
                            continue;
                        }

                        $role = surfside_tools_header_link_role($link);
                        $is_current = surfside_tools_header_link_is_current($url);
                        $new_tab = ($link['type'] ?? '') === 'custom' && !empty($link['new_tab']);
                        $classes = array('surfside-site-header__link');
                        if ($is_current || ($is_live && $role === 'watch')) {
                            $classes[] = 'surfside-site-header__link--primary';
                        }
                        if ($is_live && $role === 'watch') {
                            $classes[] = 'surfside-site-header__link--live';
                            $label = 'Live Now';
                        }
                        ?>
                        <li data-surfside-nav-role="<?php echo esc_attr($role); ?>">
                            <a class="<?php echo esc_attr(implode(' ', $classes)); ?>" href="<?php echo esc_url($url); ?>"<?php echo $is_current ? ' aria-current="page"' : ''; ?><?php echo $new_tab ? ' target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php if ($is_live && $role === 'watch') : ?><span class="surfside-site-header__live-dot" aria-hidden="true"></span><?php endif; ?><span data-surfside-link-label data-default-label="<?php echo esc_attr($link['label'] ?? ''); ?>"><?php echo esc_html($label); ?></span></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </header>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_header', 'surfside_tools_header_shortcode');
