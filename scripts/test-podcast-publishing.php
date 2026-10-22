<?php
/** Regression coverage for deterministic weekly podcast metadata (no WordPress). */
define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/includes/podcast-publishing.php';
function verify_podcast($pass, $label) {
    if (!$pass) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
}
$video = 'OTt1dfYhlDY';
$one = surfside_tools_podcast_episode_preview(
    'Freedom and Faith - Finish Well - Week 1 - 26 July 2026',
    '2026-07-26', $video);
verify_podcast($one['title'] === 'Freedom and Faith | Finish Well (Week 1)', 'series and week');
verify_podcast(strpos($one['description'], 'Sermon date: July 26, 2026') !== false, 'date');
verify_podcast(strpos($one['description'], 'watch?v=' . $video) !== false, 'source link');
verify_podcast(!$one['needs_review'], 'structured title accepted');
$two = surfside_tools_podcast_episode_preview(
    'Living Christ Above All - Christ Above All - Colossians - Week 5 - 19 July 2026',
    '2026-07-19', $video);
verify_podcast($two['title'] === 'Living Christ Above All | Christ Above All: Colossians (Week 5)', 'multi-part series');
$unknown = surfside_tools_podcast_episode_preview('Special Service - 19 July 2026', null, $video);
verify_podcast($unknown['needs_review'] && $unknown['title'] === 'Special Service - 19 July 2026', 'unknown format not guessed');
$missing_id = surfside_tools_podcast_episode_preview('Talk - Series - Week 2 - 26 July 2026', '2026-07-26', 'bad');
verify_podcast($missing_id['needs_review'] && strpos($missing_id['description'], 'watch?v=') === false, 'invalid id not linked');

/* New cloud processor trigger tests: R2 readiness never depends on optional queue. */
function wp_json_encode($v) { return json_encode($v); }
function untrailingslashit($v) { return rtrim($v, '/'); }
function wp_remote_post($url, $options) {
    $GLOBALS['podcast_last_request'] = array('url' => $url, 'options' => $options);
    return $GLOBALS['podcast_response'] ?? array(
        'status' => 202, 'body' => '{"video_id":"OTt1dfYhlDY","status":"queued"}'
    );
}
function wp_remote_retrieve_body($response) { return $response['body'] ?? ''; }
verify_podcast(surfside_tools_podcast_queue_verified('invalid')['status'] === 'invalid_video',
    'invalid YouTube ID cannot be sent to processor');
verify_podcast(surfside_tools_podcast_queue_verified('OTt1dfYhlDY')['status'] === 'not_configured',
    'Cloudflare integration stays off without production credentials');

echo "PASS: podcast preview and opt-in Cloudflare queue safety\n";
