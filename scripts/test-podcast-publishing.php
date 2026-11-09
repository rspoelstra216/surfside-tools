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
$standalone = surfside_tools_podcast_episode_preview(
    "How Can We Gain God's Perspective - 08 November 2026",
    '2026-11-08', 'e0NnCrrshM4');
verify_podcast($standalone['title'] === "How Can We Gain God's Perspective",
    'dated standalone sermon title excludes date');
verify_podcast(!$standalone['needs_review'], 'standalone sermon is eligible for Spreaker');
verify_podcast(strpos($standalone['description'], 'Series:') === false &&
    strpos($standalone['description'], 'Week:') === false,
    'standalone sermon does not invent a series or week');
verify_podcast(strpos($standalone['description'], 'Sermon date: November 8, 2026') !== false,
    'standalone sermon preserves date');
$unstructured = surfside_tools_podcast_episode_preview(
    'Living Well - Series But No Week - 08 November 2026',
    '2026-11-08', $video);
verify_podcast($unstructured['needs_review'], 'malformed multi-part title still requires review');
$undated = surfside_tools_podcast_episode_preview(
    'Standalone Sermon', '2026-11-08', $video);
verify_podcast($undated['needs_review'], 'standalone requires a dated YouTube title');
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
function wp_remote_retrieve_response_code($response) { return $response['status'] ?? 0; }
function is_wp_error($result) { return $result instanceof WP_Error; }
function sanitize_text_field($value) { return trim((string) $value); }
class WP_Error {
    public function __construct($code, $message, $data = array()) {}
}
verify_podcast(surfside_tools_podcast_queue_verified('invalid')['status'] === 'invalid_video',
    'invalid YouTube ID cannot be sent to processor');
verify_podcast(surfside_tools_podcast_queue_verified('OTt1dfYhlDY')['status'] === 'not_configured',
    'Cloudflare integration stays off without production credentials');

// Enable production config only after the fail-closed test above.
define('SURFSIDE_PODCAST_WORKER_URL', 'https://podcast.example.workers.dev');
define('SURFSIDE_PODCAST_SHARED_SECRET', str_repeat('s', 64));
define('SURFSIDE_SPREAKER_DRAFTS_ENABLED', true);
function surfside_tools_youtube_review_master_id() { return 'master'; }
function surfside_tools_youtube_review_video($master, $id) {
    if ($master !== 'master' || $id !== 'e0NnCrrshM4') return null;
    return array('title' => "How Can We Gain God's Perspective - 08 November 2026",
        'effective_date' => '2026-11-08', 'disposition' => 'include');
}
$standalone_meta = surfside_tools_podcast_spreaker_metadata('e0NnCrrshM4');
verify_podcast($standalone_meta['title'] === "How Can We Gain God's Perspective",
    'canonical YouTube standalone metadata is usable for Spreaker');

$recovery = surfside_tools_podcast_prepare_existing_draft('e0NnCrrshM4');
verify_podcast($recovery['status'] === 'queued', 'recovery accepted');
$posted = $GLOBALS['podcast_last_request'];
verify_podcast($posted['url'] === 'https://podcast.example.workers.dev/recover-spreaker',
    'signed Spreaker-only recovery endpoint');
$body = $posted['options']['body'];
$data = json_decode($body, true);
verify_podcast($data['video_id'] === 'e0NnCrrshM4' &&
    $data['confirmed_no_draft'] === true &&
    $data['spreaker']['title'] === "How Can We Gain God's Perspective",
    'recovery sends only explicit verified metadata and confirmation');
verify_podcast(!isset($data['test']) && !isset($data['mp4']),
    'recovery cannot trigger isolated test or media upload');
verify_podcast(hash_hmac('sha256',
    $posted['options']['headers']['X-Surfside-Time'] . '.' . $body,
    SURFSIDE_PODCAST_SHARED_SECRET) ===
    $posted['options']['headers']['X-Surfside-Signature'],
    'recovery Worker request uses HMAC');

echo "PASS: podcast previews, standalone Spreaker drafts and recovery safety\n";
