<?php
/** Public, lightweight playlist refresh endpoint keeps website updates current. */
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
class WP_REST_Server { const READABLE = 'GET'; }
class TestResponse {
    public $data;
    public $headers = array();
    public function __construct($data) { $this->data = $data; }
    public function header($name, $value) { $this->headers[$name] = $value; }
}
$GLOBALS['routes'] = array();
$GLOBALS['actions'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['options'] = array();
function add_action($name, $callback, $priority=10) { $GLOBALS['actions'][$name][] = $callback; }
function add_filter(...$args) {}
function register_rest_route($namespace, $path, $config) { $GLOBALS['routes'][$namespace . $path] = $config; }
function rest_ensure_response($data) { return new TestResponse($data); }
function __return_true() { return true; }
function absint($v) { return abs((int)$v); }
function get_option($key, $default=array()) { return $GLOBALS['options'][$key] ?? $default; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function get_post_type($id) { return $id === 5 ? 'attachment' : false; }
function wp_attachment_is_image($id) { return $id === 5; }
function wp_get_attachment_image_url($id, $size) { return $id === 5 ? 'https://example.org/image.jpg' : false; }
function esc_url_raw($v) { return $v; }
function must($ok, $message) { if (!$ok) throw new RuntimeException($message); }

require dirname(__DIR__) . '/includes/offline-announcement-slideshow.php';
must(isset($GLOBALS['actions']['rest_api_init']), 'Slideshow refresh route is registered on REST init');
foreach ($GLOBALS['actions']['rest_api_init'] as $callback) $callback();
$route = $GLOBALS['routes']['surfside/v1/announcement-slideshow'] ?? null;
must(is_array($route), 'Public refresh route exists');
must($route['methods'] === 'GET' && $route['permission_callback'] === '__return_true',
    'Route exposes published data read-only, with no authentication requirement');

$GLOBALS['options'][SURFSIDE_TOOLS_OFFLINE_SLIDESHOW_OPTION] = array(
    'updated_at' => time(),
    'default_duration_seconds' => 8,
    'slides' => array(array('id'=>5, 'enabled'=>true, 'duration_seconds'=>12),
                      array('id'=>6, 'enabled'=>false, 'duration_seconds'=>3))
);
$response = $route['callback']();
must($response instanceof TestResponse, 'Callback produces a REST response');
must(count($response->data['slides']) === 1 && $response->data['slides'][0]['id'] === 5,
    'Refresh returns only published enabled images');
must($response->data['slides'][0]['duration_seconds'] === 12, 'Duration preserved');
must(strpos($response->headers['Cache-Control'] ?? '', 'no-store') !== false,
    'Response disallows intermediary cached stale playlists');
echo "PASS: Lightweight, public, non-cacheable announcement playlist refresh API\n";
