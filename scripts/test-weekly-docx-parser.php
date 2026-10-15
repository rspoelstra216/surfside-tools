<?php
/**
 * DOCX Weekly Update parser regressions: replace-name series, text-box
 * headers, duplicated boxes, manually numbered announcements and intro text.
 * Run: php scripts/test-weekly-docx-parser.php
 */
define('ABSPATH', __DIR__ . '/');
function add_action(...$args) {}
function add_filter(...$args) {}
function add_shortcode(...$args) {}

require dirname(__DIR__) . '/includes/core-weekly-tools.php';

function weekly_check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException('Weekly DOCX parser: ' . $message);
    }
}

// The published series must never have to be added to a fixed PHP allowlist.
$header_lines = array(
    'MADE TO SERVE',
    'MADE FOR MORE SERIES-WEEK 6',
    'OCTOBER 18, 2026',
    '3 KEYS TO USING YOUR GIFTS',
    '1) SERVE IN LOVE, NOT OBLIGATION',
);
$header = surfside_tools_detect_sermon_header($header_lines);
weekly_check(surfside_tools_textbox_is_sermon_header(array_slice($header_lines, 0, 3)), 'Bordered box recognized as a header');
weekly_check($header['title'] === 'MADE TO SERVE', 'Sermon title');
weekly_check($header['series'] === 'Made for More', 'Series name excludes week suffix');
weekly_check($header['date'] === 'OCTOBER 18, 2026', 'Sermon date');
weekly_check(surfside_tools_find_sermon_main_heading($header_lines, 4, $header['title'], $header['series'], $header['date']) === '3 KEYS TO USING YOUR GIFTS', 'Main heading remains distinct');
$other_header = surfside_tools_detect_sermon_header(array(
    'MADE TO KNOW GOD', 'MADE FOR MORE SERIES-WEEK 3', 'SEPTEMBER 26/27, 2026',
    'WORSHIP: HOW I KNOW GOD MORE', '1) RECOGNIZE HIS PRESENCE WITH THANKS',
));
weekly_check($other_header['title'] === 'MADE TO KNOW GOD', 'Weekend message title');
weekly_check($other_header['date'] === 'SEPTEMBER 26/27, 2026', 'Weekend date range');
weekly_check($other_header['series'] === 'Made for More', 'Series reusable across weeks');
$legacy = surfside_tools_detect_sermon_header(array(
    'WHAT MATTERS MOST', 'CHRIST ABOVE ALL - COLOSSIANS', 'SEPTEMBER 6, 2026',
    'HOW DO I GROW?', '1) FOLLOW JESUS',
));
weekly_check($legacy['series'] === 'CHRIST ABOVE ALL - COLOSSIANS', 'Older series without week suffix stays supported');

$records = array(
    array('text' => 'Thank you to everyone that gave of their time and energy to make our Fall Festival a success again this year!'),
    array('text' => ''),
    array('text' => '1.   Don’t forget to stop by the OCC area in the lobby to pick up your'),
    array('text' => 'Shoeboxes in the lobby area.'),
    array('text' => 'Packing party is on Nov. 8th and lots of hands are needed to help.'),
    array('text' => ''),
    array('text' => '2.   Food Drive will be Oct. 24th through Nov. 21st to help the Central'),
    array('text' => 'Brevard Sharing Center help those in need during the upcoming holidays.'),
    array('text' => '3.   The Big Red Bus will have “2” buses in the parking area on Nov. 8th'),
    array('text' => 'from 9:00am-1:00pm.'),
    array('text' => 'October 18, 2026'),
);
$parsed = surfside_tools_parse_announcement_records($records);
weekly_check(count($parsed['items']) === 4, 'Intro and three announcements retained');
weekly_check(strpos($parsed['items'][0], 'Fall Festival') !== false, 'Introductory thank-you preserved');
weekly_check(strpos($parsed['items'][1], '1.') !== 0, 'Typed numbering removed before <ol>');
weekly_check(strpos($parsed['items'][1], 'Packing party') !== false, 'Wrapped lines retained');
weekly_check(strpos($parsed['items'][2], 'Food Drive') === 0, 'Second numbered item distinct');
weekly_check(strpos($parsed['items'][3], 'Big Red Bus') === 4 || strpos($parsed['items'][3], 'Big Red Bus') === 4, 'Third numbered item retained');
weekly_check(strpos(implode(' ', $parsed['items']), 'October 18, 2026') === false, 'Date excluded from announcement list');
$auto = surfside_tools_parse_announcement_records(array(
    array('text' => 'A native Word numbered item', 'numbered' => true),
    array('text' => 'Continuation'),
));
weekly_check(count($auto['items']) === 1 && $auto['items'][0] === 'A native Word numbered item Continuation', 'Native Word numbering remains supported');
weekly_check(count(surfside_tools_parse_announcement_records(array(array('text' => 'No list present')))['items']) === 0, 'Unnumbered ordinary documents do not publish as announcements');

// Verify the full OOXML extraction path using minimal DOCX specimens.
// This catches duplicate text boxes and container paragraph leakage.
if (!class_exists('ZipArchive') || !class_exists('DOMDocument')) {
    throw new RuntimeException('DOCX parser test requires PHP zip and DOM extensions');
}
function weekly_p($text) {
    return '<w:p><w:r><w:t xml:space="preserve">' .
        htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r></w:p>';
}
function weekly_box($paragraphs) {
    return '<w:p><w:r><w:pict><v:shape><v:textbox><w:txbxContent>' .
        implode('', $paragraphs) . '</w:txbxContent></v:textbox></v:shape></w:pict></w:r></w:p>';
}
function weekly_fixture($body) {
    $xml = '<?xml version="1.0" encoding="utf-8"?>' .
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" ' .
        'xmlns:v="urn:schemas-microsoft-com:vml"><w:body>' .
        $body . '</w:body></w:document>';
    $path = tempnam(sys_get_temp_dir(), 'surfside-docx-');
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create DOCX test ZIP');
    }
    $zip->addFromString('word/document.xml', $xml);
    $zip->close();
    return $path;
}
$sermon_box = array(
    weekly_p('MADE TO SERVE'),
    weekly_p('MADE FOR MORE SERIES-WEEK 6'),
    weekly_p('OCTOBER 18, 2026'),
);
$sermon_path = weekly_fixture(
    weekly_box($sermon_box) . weekly_box($sermon_box) .
    weekly_p('3 KEYS TO USING YOUR GIFTS') .
    weekly_p('1) SERVE IN LOVE, NOT OBLIGATION') .
    weekly_p('Each of you should use whatever gift you have received to serve others. 1 Peter 4:10 NIV') .
    weekly_box(array(weekly_p('Father, teach me how to love others in love. In Jesus’ name, Amen.')))
);
$sermon = surfside_tools_parse_sermon_docx($sermon_path);
unlink($sermon_path);
weekly_check(!is_wp_error($sermon), 'Synthetic DOCX sermon parses');
weekly_check($sermon['title'] === 'Made To Serve', 'DOCX title correctly displayed');
weekly_check($sermon['series'] === 'Made for More', 'DOCX series correctly displayed');
weekly_check($sermon['date'] === 'OCTOBER 18, 2026', 'DOCX date extracted');
weekly_check($sermon['main_heading'] === '3 KEYS TO USING YOUR GIFTS', 'DOCX main heading extracted');
weekly_check(strpos($sermon['notes'], 'SERVE IN LOVE') !== false, 'DOCX body retained');
weekly_check(strpos($sermon['prayer'], 'Father, teach me') !== false, 'DOCX closing prayer extracted');

$announcement_box = array();
foreach ($records as $record) {
    $announcement_box[] = weekly_p($record['text']);
}
$announcement_path = weekly_fixture(
    weekly_box($announcement_box) . weekly_box($announcement_box) .
    weekly_box(array(weekly_p('October 18, 2026')))
);
$announcements = surfside_tools_extract_numbered_announcements_from_docx($announcement_path);
unlink($announcement_path);
weekly_check(!is_wp_error($announcements), 'Synthetic announcement DOCX parses');
weekly_check(count($announcements['items']) === 4, 'Word text box yields intro and three unique items');
weekly_check($announcements['date'] === 'October 18, 2026', 'Date from separate text box');
weekly_check($announcements['report']['duplicate_paragraphs_removed'] > 0, 'Duplicate text boxes discarded');

echo "Weekly DOCX sermon and announcement parser checks passed.\n";
