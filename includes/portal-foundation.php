<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Milestone 8: plugin-owned foundation for the public church portal.
 *
 * The existing Portal page is not changed automatically. Add
 * [surfside_portal] to a preview page while the migration is verified.
 */

function surfside_tools_portal_url($path = '') {
    return home_url('/' . ltrim((string) $path, '/'));
}

function surfside_tools_portal_cards($atts = array()) {
    $cards = array(
        array(
            'key' => 'live_slides',
            'title' => 'Live Slides',
            'description' => 'Having trouble seeing the screens? View today’s slides on your phone with adjustable font sizes and light or dark mode options.',
            'icon' => '📺',
            'url' => (string) ($atts['live_slides_url'] ?? 'http://surfside.local/'),
            'featured' => true,
        ),
        array(
            'key' => 'first_time',
            'title' => 'First Time Here',
            'description' => 'Learn what to expect, service times, children’s ministry information, and how to get connected.',
            'icon' => '🏠',
            'url' => (string) ($atts['first_time_url'] ?? surfside_tools_portal_url('plan-your-visit/')),
        ),
        array(
            'key' => 'message_notes',
            'title' => 'Message Notes',
            'description' => 'Follow along with today’s sermon.',
            'icon' => '📖',
            'url' => (string) ($atts['message_notes_url'] ?? surfside_tools_portal_url('portal/message-notes/')),
        ),
        array(
            'key' => 'announcements',
            'title' => 'Announcements',
            'description' => 'See what’s happening this week.',
            'icon' => '📅',
            'url' => (string) ($atts['announcements_url'] ?? surfside_tools_portal_url('portal/announcements/')),
        ),
        array(
            'key' => 'events',
            'title' => 'This Week’s Events',
            'description' => 'See what’s happening at Surfside over the next seven days.',
            'icon' => '📅',
            'url' => (string) ($atts['events_url'] ?? surfside_tools_portal_url('events/')),
        ),
        array(
            'key' => 'prayer',
            'title' => 'Prayer Request',
            'description' => 'Let us know how we can pray for you.',
            'icon' => '🙏',
            'url' => (string) ($atts['prayer_url'] ?? surfside_tools_portal_url('contact/')),
        ),
        array(
            'key' => 'ministries',
            'title' => 'Ministry Opportunities',
            'description' => 'Discover ways to serve and get involved.',
            'icon' => '🤝',
            'url' => (string) ($atts['ministries_url'] ?? surfside_tools_portal_url('ministries/')),
        ),
        array(
            'key' => 'give',
            'title' => 'Give Online',
            'description' => 'Support the ministry of Surfside.',
            'icon' => '💳',
            'url' => (string) ($atts['give_url'] ?? surfside_tools_portal_url('give/')),
        ),
        array(
            'key' => 'explore',
            'title' => 'Explore Surfside',
            'description' => 'Visit the full Surfside website.',
            'icon' => '🌐',
            'url' => (string) ($atts['explore_url'] ?? home_url('/')),
        ),
    );

    return apply_filters('surfside_tools_portal_cards', $cards, $atts);
}

function surfside_tools_portal_assets() {
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;
    wp_register_style('surfside-tools-portal', false, array(), SURFSIDE_TOOLS_VERSION);
    wp_enqueue_style('surfside-tools-portal');
    wp_add_inline_style('surfside-tools-portal', '
        .surfside-portal-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;width:100%!important;max-width:900px!important;margin:0 auto!important;padding:clamp(16px,3vw,28px) 0;color:#071b3a}
        .surfside-portal-card{display:block;min-width:0;min-height:175px;padding:28px 22px;border-radius:18px;background:#fff;color:inherit!important;text-align:center;text-decoration:none!important;box-shadow:0 4px 18px rgba(0,0,0,.08);transition:transform .18s ease,box-shadow .18s ease}
        .surfside-portal-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.12)}
        .surfside-portal-card:focus-visible{outline:4px solid rgba(11,79,156,.28);outline-offset:4px}
        .surfside-portal-card.featured{grid-column:1/-1;background:#fff;text-align:center}
        .surfside-portal-card h3{margin:8px 0 6px;color:#071b3a;font-size:clamp(28px,3vw,36px);line-height:1.12;letter-spacing:-.035em}
        .surfside-portal-card p{margin:0;color:#111827;font-size:clamp(17px,2vw,20px);line-height:1.45}
        .portal-icon{font-size:2rem;line-height:1}
        @media(max-width:600px){.surfside-portal-grid{grid-template-columns:1fr}.surfside-portal-card{min-height:175px}.surfside-portal-card h3{font-size:28px}.surfside-portal-card p{font-size:17px}}
        @media(prefers-reduced-motion:reduce){.surfside-portal-card{transition:none}.surfside-portal-card:hover{transform:none}}
    ');
}

function surfside_tools_portal_shortcode($atts = array()) {
    $atts = shortcode_atts(
        array(
            'live_slides_url' => 'http://surfside.local/',
            'first_time_url' => surfside_tools_portal_url('plan-your-visit/'),
            'message_notes_url' => surfside_tools_portal_url('portal/message-notes/'),
            'announcements_url' => surfside_tools_portal_url('portal/announcements/'),
            'events_url' => surfside_tools_portal_url('events/'),
            'prayer_url' => surfside_tools_portal_url('contact/'),
            'ministries_url' => surfside_tools_portal_url('ministries/'),
            'give_url' => surfside_tools_portal_url('give/'),
            'explore_url' => home_url('/'),
        ),
        $atts,
        'surfside_portal'
    );

    surfside_tools_portal_assets();
    $cards = surfside_tools_portal_cards($atts);

    ob_start();
    ?>
    <nav class="surfside-portal-grid" aria-label="Surfside portal">
            <?php foreach ($cards as $card) :
                $url = trim((string) ($card['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $classes = 'surfside-portal-card';
                if (!empty($card['featured'])) {
                    $classes .= ' featured';
                }
                ?>
                <a class="<?php echo esc_attr($classes); ?>" href="<?php echo esc_url($url); ?>" data-portal-card="<?php echo esc_attr($card['key'] ?? ''); ?>">
                    <div class="portal-icon" aria-hidden="true"><?php echo esc_html($card['icon'] ?? ''); ?></div>
                    <h3><?php echo esc_html($card['title'] ?? ''); ?></h3>
                    <?php if (!empty($card['description'])) : ?>
                        <p><?php echo esc_html($card['description']); ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_portal', 'surfside_tools_portal_shortcode');
