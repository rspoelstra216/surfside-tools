<?php
/** Regression checks for human-approved local size differences. */
define('ABSPATH', __DIR__ . '/');
function add_action() {}
function get_option($key, $default = false) { return $GLOBALS['test_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) {
    $GLOBALS['test_options'][$key] = $value;
    return true;
}
function assert_match($ok, $label) {
    if (!$ok) throw new RuntimeException($label);
}
require_once __DIR__ . '/../includes/youtube-owner-manual-matching.php';

$id = '_pZDK7gttZU';
$path = '2026/May/capture - 10 May 2026 - 09-41-35 AM.mp4';
$local = '1788090392';
$youtube = '1790044326';
$automatic = array('status' => 'no_filename_match', 'source_path' => '');
$index = array(basename($path) => array(array('source_path' => $path, 'size_bytes' => $local)));
$detail = array('file_name' => 'capture - 10 May 2026 - 09-41-35 AM (online-video-cutter.com).mp4', 'size_bytes' => $youtube);
$GLOBALS['test_options'] = array(
    SURFSIDE_YT_OWNER_MANUAL_OPTION => array($id => $path),
);
$result = surfside_yt_owner_manual_apply($id, $automatic, $index, $detail);
assert_match($result['status'] === 'manual_size_mismatch' && $result['source_path'] === '',
    'Must reject unapproved byte-size mismatches');

$overrides = array($id => array(
    'path' => $path, 'local_size_bytes' => $local, 'youtube_size_bytes' => $youtube,
));
update_option(SURFSIDE_YT_OWNER_SIZE_OVERRIDE_OPTION, $overrides, false);
$result = surfside_yt_owner_manual_apply($id, $automatic, $index, $detail);
assert_match($result['status'] === 'manually_matched_size_override' && $result['source_path'] === $path,
    'Approved mismatch should export verified source path with a distinct status');

$changed_index = array(basename($path) => array(array('source_path' => $path, 'size_bytes' => '1788090393')));
$result = surfside_yt_owner_manual_apply($id, $automatic, $changed_index, $detail);
assert_match($result['status'] === 'manual_size_mismatch' && $result['source_path'] === '',
    'Changed NAS file size must revoke the previously saved override');

$changed_detail = array('file_name' => $detail['file_name'], 'size_bytes' => '1790044327');
$result = surfside_yt_owner_manual_apply($id, $automatic, $index, $changed_detail);
assert_match($result['status'] === 'manual_size_mismatch' && $result['source_path'] === '',
    'Changed YouTube owner file size must revoke the previously saved override');

$other_path = '2026/May/another.mp4';
$other_index = array(basename($other_path) => array(array('source_path' => $other_path, 'size_bytes' => $local)));
update_option(SURFSIDE_YT_OWNER_MANUAL_OPTION, array($id => $other_path), false);
$result = surfside_yt_owner_manual_apply($id, $automatic, $other_index, $detail);
assert_match($result['status'] === 'manual_size_mismatch' && $result['source_path'] === '',
    'Override must not apply to a different NAS path');

update_option(SURFSIDE_YT_OWNER_MANUAL_OPTION, array($id => $path), false);
$result = surfside_yt_owner_manual_apply($id, $automatic, array(), $detail);
assert_match($result['status'] === 'manual_path_missing' && $result['source_path'] === '',
    'Missing path must remain blank even when size override was approved');

$result = surfside_yt_owner_manual_apply($id, array('status' => 'matched', 'source_path' => $path),
    $index, $detail);
assert_match($result['status'] === 'matched', 'Original filename exact match must take priority');

$equal_size_detail = array('size_bytes' => $local);
$result = surfside_yt_owner_manual_apply($id, $automatic, $index, $equal_size_detail);
assert_match($result['status'] === 'manually_matched' && $result['source_path'] === $path,
    'Exact-size manual match should remain unchanged');

$no_owner_size = array('file_name' => '');
$result = surfside_yt_owner_manual_apply($id, $automatic, $index, $no_owner_size);
assert_match($result['status'] === 'manual_unverified_youtube_size' && $result['source_path'] === $path,
    'Missing owner size retains existing explicit manual-review status');

echo "Manual NAS size override regression checks passed.\n";
