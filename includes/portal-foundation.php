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
            'url' => '#',
            'dialog' => 'message-notes',
        ),
        array(
            'key' => 'announcements',
            'title' => 'Announcements',
            'description' => 'See what’s happening this week.',
            'icon' => '📅',
            'url' => '#',
            'dialog' => 'announcements',
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
        .surfside-portal-dialog{width:min(760px,calc(100vw - 32px));max-width:760px;max-height:min(86vh,900px);padding:0;border:0;border-radius:20px;background:#fff;color:#071b3a;box-shadow:0 24px 70px rgba(7,27,58,.28);overflow:hidden}
        .surfside-portal-dialog::backdrop{background:rgba(7,27,58,.72);backdrop-filter:blur(3px)}
        .surfside-portal-dialog-header{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px 22px;border-bottom:1px solid rgba(7,27,58,.12);background:#fff}
        .surfside-portal-dialog-header h2{margin:0;color:#071b3a;font-size:clamp(24px,4vw,32px);line-height:1.15}
        .surfside-portal-dialog-close{display:inline-flex;align-items:center;justify-content:center;min-width:44px;min-height:44px;padding:8px 14px;border:0;border-radius:10px;background:#071b3a;color:#fff;font:inherit;font-weight:800;cursor:pointer}
        .surfside-portal-dialog-close:hover,.surfside-portal-dialog-close:focus-visible{background:#0b4f9c}
        .surfside-portal-dialog-close:focus-visible{outline:3px solid rgba(11,79,156,.3);outline-offset:3px}
        .surfside-portal-dialog-body{max-height:calc(86vh - 81px);padding:24px;overflow:auto;overscroll-behavior:contain}
        .surfside-portal-dialog-body>:first-child{margin-top:0}.surfside-portal-dialog-body>:last-child{margin-bottom:0}
        body.surfside-portal-dialog-open{overflow:hidden}
        @media(max-width:600px){.surfside-portal-grid{grid-template-columns:1fr}.surfside-portal-card{min-height:175px}.surfside-portal-card h3{font-size:28px}.surfside-portal-card p{font-size:17px}.surfside-portal-dialog{width:100vw;max-width:none;height:100dvh;max-height:none;margin:0;border-radius:0}.surfside-portal-dialog-header{padding:14px 16px}.surfside-portal-dialog-body{max-height:calc(100dvh - 73px);padding:20px 16px}}
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
            'prayer_url' => surfside_tools_portal_url('contact/#Contact'),
            'ministries_url' => surfside_tools_portal_url('ministries/'),
            'give_url' => surfside_tools_portal_url('give/'),
            'explore_url' => home_url('/'),
        ),
        $atts,
        'surfside_portal'
    );

    surfside_tools_portal_assets();
    $cards = surfside_tools_portal_cards($atts);
    $portal_id = wp_unique_id('surfside-portal-');

    ob_start();
    ?>
    <nav class="surfside-portal-grid" aria-label="Surfside portal" data-portal-dialogs>
            <?php foreach ($cards as $card) :
                $dialog = trim((string) ($card['dialog'] ?? ''));
                $dialog_id = $dialog !== '' ? $portal_id . '-' . $dialog : '';
                $url = $dialog_id !== '' ? '#' . $dialog_id : trim((string) ($card['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $classes = 'surfside-portal-card';
                if (!empty($card['featured'])) {
                    $classes .= ' featured';
                }
                ?>
                <a class="<?php echo esc_attr($classes); ?>" href="<?php echo esc_url($url); ?>" data-portal-card="<?php echo esc_attr($card['key'] ?? ''); ?>"<?php if ($dialog_id !== '') : ?> data-portal-dialog="<?php echo esc_attr($dialog_id); ?>" aria-haspopup="dialog"<?php endif; ?>>
                    <div class="portal-icon" aria-hidden="true"><?php echo esc_html($card['icon'] ?? ''); ?></div>
                    <h3><?php echo esc_html($card['title'] ?? ''); ?></h3>
                    <?php if (!empty($card['description'])) : ?>
                        <p><?php echo esc_html($card['description']); ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
    </nav>

    <dialog id="<?php echo esc_attr($portal_id . '-message-notes'); ?>" class="surfside-portal-dialog" aria-labelledby="<?php echo esc_attr($portal_id . '-message-notes-title'); ?>">
        <div class="surfside-portal-dialog-header">
            <h2 id="<?php echo esc_attr($portal_id . '-message-notes-title'); ?>">Message Notes</h2>
            <button type="button" class="surfside-portal-dialog-close" data-dialog-close>Close</button>
        </div>
        <div class="surfside-portal-dialog-body"><?php echo do_shortcode('[surfside_tools_message]'); ?></div>
    </dialog>

    <dialog id="<?php echo esc_attr($portal_id . '-announcements'); ?>" class="surfside-portal-dialog" aria-labelledby="<?php echo esc_attr($portal_id . '-announcements-title'); ?>">
        <div class="surfside-portal-dialog-header">
            <h2 id="<?php echo esc_attr($portal_id . '-announcements-title'); ?>">Announcements</h2>
            <button type="button" class="surfside-portal-dialog-close" data-dialog-close>Close</button>
        </div>
        <div class="surfside-portal-dialog-body"><?php echo do_shortcode('[surfside_tools_announcements]'); ?></div>
    </dialog>

    <script>
    (function(){
        var root=document.querySelector('[data-portal-dialogs]:not([data-dialogs-ready])');
        if(!root)return;
        root.setAttribute('data-dialogs-ready','true');
        var returnFocus=null;
        root.addEventListener('click',function(event){
            var trigger=event.target.closest('[data-portal-dialog]');
            if(!trigger)return;
            var dialog=document.getElementById(trigger.getAttribute('data-portal-dialog'));
            if(!dialog||typeof dialog.showModal!=='function')return;
            event.preventDefault();
            returnFocus=trigger;
            dialog.showModal();
            document.body.classList.add('surfside-portal-dialog-open');
        });
        document.querySelectorAll('.surfside-portal-dialog').forEach(function(dialog){
            dialog.addEventListener('click',function(event){if(event.target===dialog)dialog.close();});
            dialog.addEventListener('close',function(){
                document.body.classList.remove('surfside-portal-dialog-open');
                if(returnFocus){returnFocus.focus();returnFocus=null;}
            });
            dialog.querySelectorAll('[data-dialog-close]').forEach(function(button){
                button.addEventListener('click',function(){dialog.close();});
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('surfside_portal', 'surfside_tools_portal_shortcode');
