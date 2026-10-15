<?php
/**
 * Regression for permanent sermon-note archiving.
 * Run: php scripts/test-sermon-archive.php
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['options'] = array();
$GLOBALS['actions'] = array();

class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($hook, $callback, $priority = 10) { $GLOBALS['actions'][] = $hook; }
function add_shortcode(...$args) {}
function add_filter(...$args) {}
function has_action(...$args) { return false; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) {
    if (isset($GLOBALS['options'][$key]) && $GLOBALS['options'][$key] === $value) return false;
    $GLOBALS['options'][$key] = $value;
    return true;
}
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function current_time($format, $gmt = false) { return $format === 'mysql' ? '2026-10-15 20:00:00' : 0; }

class SermonTestDB {
    public $prefix = 'wp_';
    public $table_exists = false;
    public $fail_writes = false;
    public $records = array();
    private $next_id = 1;

    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
    public function esc_like($value) { return addcslashes($value, '_%\\'); }
    public function prepare($query, $value) {
        return str_replace('%s', "'" . str_replace("'", "''", (string) $value) . "'", $query);
    }
    public function get_var($query) {
        if (strpos($query, 'SHOW TABLES LIKE') === 0) {
            return $this->table_exists ? 'wp_surfside_sermon_archive' : null;
        }
        if (preg_match("/WHERE message_key = '([a-f0-9]{64})'/", $query, $matches)) {
            return isset($this->records[$matches[1]]) ? $this->records[$matches[1]]['id'] : null;
        }
        throw new RuntimeException('Unexpected SQL: ' . $query);
    }
    public function insert($table, $data) {
        if ($this->fail_writes) return false;
        $key = $data['message_key'];
        if (isset($this->records[$key])) return false;
        $this->records[$key] = array_merge(array('id' => $this->next_id++), $data);
        return 1;
    }
    public function update($table, $data, $where) {
        if ($this->fail_writes) return false;
        foreach ($this->records as &$row) {
            if ($row['id'] === $where['id']) {
                $row = array_merge($row, $data);
                return 1;
            }
        }
        return false;
    }
}
$GLOBALS['wpdb'] = new SermonTestDB();
function dbDelta($sql) {
    if (strpos($sql, 'CREATE TABLE wp_surfside_sermon_archive') === false) {
        throw new RuntimeException('Unexpected table declaration');
    }
    $GLOBALS['wpdb']->table_exists = true;
}

require dirname(__DIR__) . '/includes/core-weekly-tools.php';
require dirname(__DIR__) . '/includes/sermon-archive.php';

function check($result, $description) {
    if (!$result) throw new RuntimeException($description);
}
function note($date, $title, $text) {
    return array('date' => $date, 'title' => $title, 'series' => 'Series',
        'main_heading' => 'Key thought', 'notes' => $text, 'prayer' => 'Amen.');
}
$backup = note('2026-09-27', 'Older Message', 'Older message text');
$current = note('2026-10-04', 'Previous Message', 'Previous message text');
$incoming = note('2026-10-11', 'New Message', 'New message text');
$GLOBALS['options']['surfside_tools_message_backup'] = $backup;
$GLOBALS['options']['surfside_tools_message_current'] = $current;

check($GLOBALS['wpdb']->records === array(), 'No database writes at module load time');
check(surfside_tools_save_message($incoming) === true, 'Publish succeeds and archives');
check(count($GLOBALS['wpdb']->records) === 3, 'Backup, current, and incoming notes are archived');
check(get_option('surfside_tools_message_current')['title'] === 'New Message', 'Public current notes updated');
check(get_option('surfside_tools_message_backup')['title'] === 'Previous Message', 'Rotating backup behavior remains');
check(get_option(SURFSIDE_TOOLS_SERMON_ARCHIVE_SCHEMA_OPTION) === 1, 'Schema migration version recorded');

$key = surfside_tools_sermon_archive_key($incoming);
check(isset($GLOBALS['wpdb']->records[$key]), 'New message is stored under its identity');
$id = $GLOBALS['wpdb']->records[$key]['id'];
$corrected = $incoming;
$corrected['notes'] = 'Corrected message text';
check(surfside_tools_save_message($corrected) === true, 'Republishing a correction works');
check(count($GLOBALS['wpdb']->records) === 3, 'Republishing does not create duplicate archive rows');
check($GLOBALS['wpdb']->records[$key]['id'] === $id, 'Archive identity remains stable');
check($GLOBALS['wpdb']->records[$key]['notes'] === 'Corrected message text', 'Corrected notes replace archived text');

$before = get_option('surfside_tools_message_current');
$GLOBALS['wpdb']->fail_writes = true;
$failed = surfside_tools_save_message(note('2026-10-18', 'Next Message', 'Not saved'));
check(is_wp_error($failed), 'An archive storage failure is reported');
check(get_option('surfside_tools_message_current') === $before, 'Archive failure cannot overwrite current notes');

check(!in_array('init', $GLOBALS['actions'], true) || count($GLOBALS['wpdb']->records) === 3,
    'Archive does not write during public initialization');

echo "Permanent sermon-note archive regression checks passed.\n";
