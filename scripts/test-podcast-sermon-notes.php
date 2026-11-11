<?php
/**
 * Regression: match permanent WordPress sermon notes to podcast descriptions.
 * No WordPress bootstrap, Cloudflare credentials, or external calls.
 */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
require dirname(__DIR__) . '/includes/sermon-archive.php';
require dirname(__DIR__) . '/includes/podcast-publishing.php';

function podcast_note_check($condition, $reason) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$reason}\n");
        exit(1);
    }
}
function get_option($key, $default = false) {
    return $key === SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_OPTION
        ? $GLOBALS['podcast_archive_ready'] : $default;
}
function surfside_tools_notes_without_main_heading($notes, $heading) {
    return trim($notes) === trim($heading) ? '' : $notes;
}
class PodcastNotesTestDB {
    public $prefix = 'wp_';
    public $records = array();
    public $queries = 0;
    private $dates = array();

    public function prepare($sql, $params) {
        $this->dates = $params;
        podcast_note_check(strpos($sql, 'message_date IN') !== false,
            'Archive query filters exact sermon dates');
        podcast_note_check(strpos($sql, 'LIMIT 30') !== false,
            'Staff lookup has a small result limit');
        return $sql;
    }
    public function get_results($sql, $mode) {
        $this->queries++;
        return array_values(array_filter($this->records, function ($row) {
            return in_array($row['message_date'], $this->dates, true);
        }));
    }
}
$GLOBALS['wpdb'] = new PodcastNotesTestDB();
$GLOBALS['podcast_archive_ready'] = 1;
$db = $GLOBALS['wpdb'];
$db->records = array(
    array(
        'title' => 'How Can We Gain God’s Perspective?',
        'message_date' => 'Nov 7/8, 2026',
        'main_heading' => 'Seeing things from God’s perspective',
        'notes' => "1. Seek wisdom in Scripture.\n2. Trust God’s timing.",
        'prayer' => 'Lord, help us see clearly.',
    ),
    array(
        'title' => 'A Different Sermon',
        'message_date' => 'November 8, 2026',
        'main_heading' => 'Not for the podcast',
        'notes' => 'Wrong notes must never appear.',
        'prayer' => '',
    ),
    array(
        'title' => 'Another Weekend Sermon',
        'message_date' => 'Nov 14/15, 2026',
        'main_heading' => 'A different weekend',
        'notes' => 'These are not November 8 sermon notes.',
        'prayer' => '',
    ),
    array(
        'title' => 'Cross Month Sermon',
        'message_date' => 'Oct 31/Nov 1, 2026',
        'main_heading' => 'Cross-month notes',
        'notes' => 'Notes for November 1 only.',
        'prayer' => '',
    ),
    array(
        'title' => 'Single Day Sermon',
        'message_date' => 'November 8, 2026',
        'main_heading' => 'Single-day notes',
        'notes' => 'Traditional date formatting still works.',
        'prayer' => '',
    ),
    array(
        'title' => 'Duplicate Sermon',
        'message_date' => 'November 15, 2026',
        'main_heading' => '',
        'notes' => 'Candidate one',
        'prayer' => '',
    ),
    array(
        'title' => 'Duplicate Sermon',
        'message_date' => 'November 15, 2026',
        'main_heading' => '',
        'notes' => 'Candidate two',
        'prayer' => '',
    ),
    array(
        'title' => 'Long Sermon',
        'message_date' => 'November 22, 2026',
        'main_heading' => 'A long heading',
        'notes' => str_repeat("A very long note about God’s love. ", 420),
        'prayer' => 'Amen.',
    ),
);
$id = 'e0NnCrrshM4';
$sermon = "How Can We Gain God's Perspective - 08 November 2026";
$notes = surfside_tools_podcast_episode_with_notes($sermon, '2026-11-08', $id);
podcast_note_check($notes['notes_status'] === 'added', 'Real Saturday/Sunday notes date matches canonical Sunday sermon');
podcast_note_check(strpos($notes['description'], "Sermon Notes\n\nSeeing things") !== false,
    'Main heading is embedded as plain text');
podcast_note_check(strpos($notes['description'], 'Seek wisdom in Scripture.') !== false &&
    strpos($notes['description'], "Closing Prayer\nLord, help us see clearly.") !== false,
    'Archived notes and closing prayer are embedded');
podcast_note_check(strpos($notes['description'], 'Wrong notes') === false,
    'Another title on the same weekend cannot leak into the episode');
podcast_note_check(strpos($notes['description'], 'Sermon Notes') <
    strpos($notes['description'], 'Watch on YouTube:'),
    'Written notes appear before the unchanged episode links');

$other = surfside_tools_podcast_episode_with_notes(
    'No Notes - 08 November 2026', '2026-11-08', $id);
podcast_note_check($other['notes_status'] === 'no_match' &&
    strpos($other['description'], 'Sermon Notes') === false,
    'No same-day fallback when title does not match');
podcast_note_check($db->queries === 1, 'Preview reuses per-date lookup within a request');
$single = surfside_tools_podcast_episode_with_notes(
    'Single Day Sermon - 08 November 2026', '2026-11-08', $id);
podcast_note_check($single['notes_status'] === 'added' &&
    strpos($single['description'], 'Traditional date formatting still works.') !== false,
    'Existing single-day notes continue matching');
$cross = surfside_tools_podcast_episode_with_notes(
    'Cross Month Sermon - 01 November 2026', '2026-11-01', $id);
podcast_note_check($cross['notes_status'] === 'added' &&
    strpos($cross['description'], 'Cross-month notes') !== false,
    'Saturday/Sunday dates match across a month boundary');
$wrong_week = surfside_tools_podcast_episode_with_notes(
    'How Can We Gain God\'s Perspective - 15 November 2026', '2026-11-15', $id);
podcast_note_check($wrong_week['notes_status'] === 'no_match',
    'The same title cannot match a different weekend');
$conflicting = surfside_tools_podcast_episode_with_notes(
    'Duplicate Sermon - 15 November 2026', '2026-11-15', $id);
podcast_note_check($conflicting['notes_status'] === 'ambiguous' &&
    strpos($conflicting['description'], 'Candidate one') === false,
    'Multiple matches do not attach arbitrary notes');

$long = surfside_tools_podcast_episode_with_notes(
    'Long Sermon - 22 November 2026', '2026-11-22', $id);
podcast_note_check($long['notes_status'] === 'truncated', 'Oversized notes explicitly marked');
podcast_note_check(strlen($long['description']) <= 8000 &&
    strpos($long['description'], '[Notes shortened') !== false &&
    strpos($long['description'], 'Watch on YouTube:') !== false &&
    preg_match('//u', $long['description']) === 1,
    'Length cap preserves valid UTF-8 and YouTube links');

$unstructured = surfside_tools_podcast_episode_with_notes(
    'Bad - Unrecognized Series - 08 November 2026', '2026-11-08', $id);
podcast_note_check($unstructured['notes_status'] === 'metadata_review',
    'Ambiguous video metadata does not attempt archive matching');
$GLOBALS['podcast_archive_ready'] = 0;
$unavailable = surfside_tools_podcast_episode_with_notes($sermon, '2026-11-08', $id);
podcast_note_check($unavailable['notes_status'] === 'archive_unavailable' &&
    strpos($unavailable['description'], 'Sermon Notes') === false,
    'Unavailable archive does not interrupt the existing description');
$GLOBALS['podcast_archive_ready'] = 1;

// The same merged description must be used for the signed Spreaker job,
// not only for the read-only browser preview.
define('SURFSIDE_SPREAKER_DRAFTS_ENABLED', true);
function surfside_tools_youtube_review_master_id() { return 'master'; }
function surfside_tools_youtube_review_video($master, $video_id) {
    return array('title' => "How Can We Gain God's Perspective - 08 November 2026",
        'effective_date' => '2026-11-08', 'disposition' => 'include');
}
$spreaker = surfside_tools_podcast_spreaker_metadata($id);
podcast_note_check(is_array($spreaker) &&
    $spreaker['description'] === $notes['description'],
    'Spreaker draft and staff preview use identical archived notes');
echo "PASS: podcast descriptions match single-day and Saturday/Sunday archive dates\n";
