<?php
/**
 * Regression test for native Follow Along Accordion injection.
 * Run: php scripts/test-watch-live-accordion.php
 */
define('ABSPATH', __DIR__);
$GLOBALS['surfside_test_watch_page'] = true;

function add_filter($name, $callback) { $GLOBALS['surfside_filters'][$name] = $callback; }
function add_shortcode($name, $callback) {}
function is_page($slug) { return $slug === 'watch-live' && $GLOBALS['surfside_test_watch_page']; }
function wp_strip_all_tags($text) { return strip_tags((string) $text); }
function esc_url($url) { return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function surfside_tools_live_slides_public_settings() {
    return array('viewer_url' => 'https://example.workers.dev/viewer',
        'websocket_url' => 'wss://example.workers.dev/api/live');
}
function parse_blocks($markup) {
    // The production site uses WordPress's real parser; the stub records
    // its serialized markup so we can verify insertion and slide content.
    if (strpos($markup, '<!-- wp:accordion-item -->') !== 0) {
        return array();
    }
    return array(array(
        'blockName' => 'core/accordion-item',
        'attrs' => array(),
        'innerBlocks' => array(),
        'innerHTML' => $markup,
        'innerContent' => array($markup),
    ));
}

require dirname(__DIR__) . '/includes/watch-live-stream.php';

function must($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function accordion_item($name) {
    return array(
        'blockName' => 'core/accordion-item',
        'attrs' => array(),
        'innerBlocks' => array(array(
            'blockName' => 'core/accordion-heading',
            'attrs' => array('title' => $name),
            'innerBlocks' => array(),
            'innerHTML' => '<h3>' . $name . '+</h3>',
        )),
        'innerContent' => array(),
    );
}

must(isset($GLOBALS['surfside_filters']['render_block_data']),
    'Correct block-data filter should be registered');

$other = array(
    'blockName' => 'core/accordion',
    'innerBlocks' => array(accordion_item('Question')),
    'innerContent' => array('<div>', null, '</div>'),
);
must(surfside_tools_watch_live_inject_slides_accordion($other) === $other,
    'Other accordions should be unchanged');

$follow_along = array(
    'blockName' => 'core/accordion',
    'innerBlocks' => array(
        accordion_item('Message Notes'),
        accordion_item('Announcements'),
        accordion_item('Upcoming Events'),
    ),
    'innerContent' => array('<div>', null, "\n", null, "\n", null, '</div>'),
);

$GLOBALS['surfside_test_watch_page'] = false;
must(surfside_tools_watch_live_inject_slides_accordion($follow_along) === $follow_along,
    'Never modify non-watch-live pages');
$GLOBALS['surfside_test_watch_page'] = true;

$result = surfside_tools_watch_live_inject_slides_accordion($follow_along);
must(count($result['innerBlocks']) === 4, 'One native accordion item should be added');
must($result['innerBlocks'][0]['blockName'] === 'core/accordion-item',
    'Slides must use native Accordion item');
must(strpos($result['innerBlocks'][0]['innerHTML'], 'Live Slides') !== false,
    'Slides are the first item');
must(strpos($result['innerBlocks'][0]['innerHTML'], 'data-slides-socket="wss://example.workers.dev/api/live"') !== false,
    'Read-only slide socket must be available for native rendering');
must(strpos($result['innerBlocks'][0]['innerHTML'], 'data-surfside-live-slides hidden') !== false,
    'Slides are hidden until live stream status is confirmed');
must(strpos($result['innerBlocks'][0]['innerHTML'], 'data-slides-text') !== false,
    'Native slide text region is provided');
must(strpos($result['innerBlocks'][0]['innerHTML'], '<iframe') === false,
    'Never embed the CSP-protected Cloudflare viewer');
must(strpos($result['innerBlocks'][0]['innerHTML'], 'https://example.workers.dev/viewer') !== false,
    'External viewer remains available as an accessible fallback');
must($result['innerBlocks'][1] === $follow_along['innerBlocks'][0],
    'Message Notes and existing children should not be altered');
must(count(array_filter($result['innerContent'], 'is_null')) === 4,
    'A matching null render marker must be inserted');
must($result['innerContent'][1] === null && $result['innerContent'][2] === "\n" &&
    $result['innerContent'][3] === null, 'Slides render directly before Message Notes');

echo "Watch Live native accordion regression checks passed.\n";
