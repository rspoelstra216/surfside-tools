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
        .surfside-portal{--surfside-portal-navy:#071b3a;--surfside-portal-blue:#0b4f9c;max-width:900px;margin:0 auto;padding:clamp(16px,3vw,28px) 0;color:var(--surfside-portal-navy)}
        .surfside-portal-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
        .surfside-portal-card{display:flex;min-width:0;min-height:230px;color:inherit!important;text-decoration:none!important;border:1px solid rgba(7,27,58,.06);border-radius:20px;background:#fff;box-shadow:0 10px 28px rgba(7,27,58,.09);transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease}
        .surfside-portal-card.is-featured{grid-column:1/-1;min-height:230px}
        .surfside-portal-card:hover{transform:translateY(-3px);border-color:rgba(11,79,156,.22);box-shadow:0 16px 34px rgba(7,27,58,.13)}
        .surfside-portal-card:focus-visible{outline:4px solid rgba(11,79,156,.28);outline-offset:4px}
        .surfside-portal-card-content{display:flex;flex:1;flex-direction:column;align-items:center;justify-content:center;padding:clamp(28px,5vw,48px) clamp(22px,4vw,42px);text-align:center}
        .surfside-portal-icon{display:block;margin-bottom:16px;font-size:30px;line-height:1}
        .surfside-portal-card h2{margin:0;color:var(--surfside-portal-navy);font-size:clamp(28px,3vw,36px);line-height:1.12;letter-spacing:-.035em}
        .surfside-portal-card p{max-width:720px;margin:10px auto 0;color:#111827;font-size:clamp(17px,2vw,20px);line-height:1.45}
        @media(max-width:700px){.surfside-portal{padding-inline:4px}.surfside-portal-grid{grid-template-columns:1fr;gap:14px}.surfside-portal-card,.surfside-portal-card.is-featured{grid-column:auto;min-height:190px}.surfside-portal-card-content{padding:30px 22px}.surfside-portal-card h2{font-size:28px}.surfside-portal-card p{font-size:17px}}
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
    <nav class="surfside-portal" aria-label="Surfside portal">
        <div class="surfside-portal-grid">
            <?php foreach ($cards as $card) :
                $url = trim((string) ($card['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $classes = 'surfside-portal-card';
                if (!empty($card['featured'])) {
                    $classes .= ' is-featured';
                }
                ?>
                <a class="<?php echo esc_attr($classes); ?>" href="<?php echo esc_url($url); ?>" data-portal-card="<?php echo esc_attr($card['key'] ?? ''); ?>">
                    <span class="surfside-portal-card-content">
                        <span class="surfside-portal-icon" aria-hidden="true"><?php echo esc_html($card['icon'] ?? ''); ?></span>
                        <h2><?php echo esc_html($card['title'] ?? ''); ?></h2>
                        <?php if (!empty($card['description'])) : ?>
                            <p><?php echo esc_html($card['description']); ?></p>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_portal', 'surfside_tools_portal_shortcode');
