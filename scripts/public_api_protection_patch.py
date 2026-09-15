from pathlib import Path
import re


def replace_once(text, old, new, label):
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 match, found {count}")
    return text.replace(old, new, 1)


def sub_once(text, pattern, repl, label, flags=0):
    updated, count = re.subn(pattern, repl, text, count=1, flags=flags)
    if count != 1:
        raise SystemExit(f"{label}: expected 1 match, found {count}")
    return updated

# Bootstrap shared protection before public API modules consume it.
path = Path('surfside-tools.php')
text = path.read_text()
text = replace_once(
    text,
    "require_once SURFSIDE_TOOLS_PATH . 'includes/youversion-settings.php';\nrequire_once SURFSIDE_TOOLS_PATH . 'includes/youversion-mobile-api.php';",
    "require_once SURFSIDE_TOOLS_PATH . 'includes/youversion-settings.php';\nrequire_once SURFSIDE_TOOLS_PATH . 'includes/public-api-protection.php';\nrequire_once SURFSIDE_TOOLS_PATH . 'includes/youversion-mobile-api.php';",
    'public API protection bootstrap'
)
path.write_text(text)

# Push registration: throttle only brand-new registrations and never evict an
# existing device merely because a scripted client filled the registry.
path = Path('includes/push-notifications.php')
text = path.read_text()
pattern = r"function surfside_tools_push_register_token\(WP_REST_Request \$request\)\{.*?\n\}\nadd_action\('rest_api_init'"
replacement = """function surfside_tools_push_register_token(WP_REST_Request $request){
    $params=(array)$request->get_json_params();
    $token=sanitize_text_field($params['token']??'');
    if(!surfside_tools_push_valid_token($token)) return new WP_Error('surfside_push_invalid_token','A valid Expo push token is required.',array('status'=>400));

    $devices=surfside_tools_push_devices();
    $key=hash('sha256',$token);
    $is_existing=isset($devices[$key]);
    $existing=$is_existing?$devices[$key]:array();

    if(!$is_existing){
        $rate=surfside_tools_public_api_rate_limit('push_register',120,10*MINUTE_IN_SECONDS);
        if(is_wp_error($rate)) return $rate;
        if(count($devices)>=5000){
            return new WP_Error(
                'surfside_push_registry_capacity',
                'Push registration is temporarily unavailable. Please try again later.',
                array('status'=>503)
            );
        }
    }

    $preferences=array_key_exists('preferences',$params)?surfside_tools_push_sanitize_preferences($params['preferences']):surfside_tools_push_sanitize_preferences($existing['preferences']??array());
    $devices[$key]=array('token'=>$token,'preferences'=>$preferences,'platform'=>sanitize_key($params['platform']??($existing['platform']??'')),'updated_at'=>time());
    update_option('surfside_tools_push_devices',$devices,false);
    return rest_ensure_response(array('success'=>true,'preferences'=>$preferences));
}
add_action('rest_api_init'"""
text = sub_once(text, pattern, replacement, 'push registration protection', re.S)
if 'array_slice($devices,-5000' in text:
    raise SystemExit('Push registry still evicts old devices at capacity')
path.write_text(text)

# YouVersion passage requests: cache upstream results and rate-limit only cache
# misses so repeated legitimate reads never consume additional upstream quota.
path = Path('includes/youversion-mobile-api.php')
text = path.read_text()
old_passage = """    $passage = surfside_tools_youversion_request('bibles/' . $version_id . '/passages/' . rawurlencode($reference));
    if (is_wp_error($passage)) {
        return surfside_tools_youversion_mobile_api_public_error($passage, 'The requested Bible passage is unavailable.');
    }
"""
new_passage = """    $passage_cache_key = 'surfside_yv_passage_' . $version_id . '_' . md5($reference);
    $passage = get_transient($passage_cache_key);
    if (!is_array($passage)) {
        $rate = surfside_tools_public_api_rate_limit('youversion_passage', 120, 5 * MINUTE_IN_SECONDS);
        if (is_wp_error($rate)) {
            return $rate;
        }

        $passage = surfside_tools_youversion_request('bibles/' . $version_id . '/passages/' . rawurlencode($reference));
        if (is_wp_error($passage)) {
            return surfside_tools_youversion_mobile_api_public_error($passage, 'The requested Bible passage is unavailable.');
        }
        if (is_array($passage)) {
            set_transient($passage_cache_key, $passage, 6 * HOUR_IN_SECONDS);
        }
    }
"""
text = replace_once(text, old_passage, new_passage, 'YouVersion passage cache')

resolve_pattern = r"function surfside_tools_youversion_mobile_api_resolve_version\(\$requested\) \{.*?\n\}\n\nfunction surfside_tools_youversion_mobile_api_get_versions"
resolve_replacement = """function surfside_tools_youversion_mobile_api_resolve_version($requested) {
    $requested = trim((string)$requested);
    if ($requested === '') {
        $requested = 'NIV';
    }

    $cache_key = 'surfside_yv_version_' . md5(strtoupper($requested));
    $cached = get_transient($cache_key);
    if (is_array($cached) && !empty($cached['id'])) {
        return $cached;
    }

    if (ctype_digit($requested)) {
        $rate = surfside_tools_public_api_rate_limit('youversion_version', 120, 5 * MINUTE_IN_SECONDS);
        if (is_wp_error($rate)) {
            return $rate;
        }
        $id = absint($requested);
        $version = surfside_tools_youversion_request('bibles/' . $id);
        if (is_wp_error($version)) {
            return surfside_tools_youversion_mobile_api_public_error($version, 'The selected Bible version is unavailable.');
        }
        $version = is_array($version) ? $version : array();
        if (!empty($version['id'])) {
            set_transient($cache_key, $version, 12 * HOUR_IN_SECONDS);
        }
        return $version;
    }

    // YouVersion's documented NIV version ID is 111. Resolve the default
    // directly so passage lookup does not depend on collection pagination.
    if (strtoupper($requested) === 'NIV') {
        $rate = surfside_tools_public_api_rate_limit('youversion_version', 120, 5 * MINUTE_IN_SECONDS);
        if (is_wp_error($rate)) {
            return $rate;
        }
        $version = surfside_tools_youversion_request('bibles/111');
        if (is_wp_error($version)) {
            return surfside_tools_youversion_mobile_api_public_error($version, 'NIV is not available for this Surfside YouVersion integration.');
        }
        $version = is_array($version) ? $version : array();
        if (!empty($version['id'])) {
            set_transient($cache_key, $version, 12 * HOUR_IN_SECONDS);
        }
        return $version;
    }

    $versions = surfside_tools_youversion_mobile_api_get_versions();
    if (is_wp_error($versions)) {
        return $versions;
    }

    $needle = strtoupper($requested);
    foreach ($versions as $version) {
        $abbreviation = strtoupper(trim((string)($version['abbreviation'] ?? '')));
        $localized = strtoupper(trim((string)($version['localized_abbreviation'] ?? '')));
        if ($needle === $abbreviation || $needle === $localized) {
            set_transient($cache_key, $version, 12 * HOUR_IN_SECONDS);
            return $version;
        }
    }

    return new WP_Error('surfside_bible_version_not_found', 'The selected Bible version is unavailable.', array('status' => 404));
}

function surfside_tools_youversion_mobile_api_get_versions"""
text = sub_once(text, resolve_pattern, resolve_replacement, 'YouVersion version caching', re.S)

text = replace_once(
    text,
    "    // Surfside intentionally exposes English first, plus the additional\n    // languages selected for the mobile app experience.\n    $language_ranges = array('en', 'es', 'pt', 'vi', 'fr', 'de');",
    "    $rate = surfside_tools_public_api_rate_limit('youversion_versions', 12, HOUR_IN_SECONDS);\n    if (is_wp_error($rate)) {\n        return $rate;\n    }\n\n    // Surfside intentionally exposes English first, plus the additional\n    // languages selected for the mobile app experience.\n    $language_ranges = array('en', 'es', 'pt', 'vi', 'fr', 'de');",
    'YouVersion versions cold-cache limiter'
)
text = replace_once(
    text,
    "        set_transient('surfside_youversion_mobile_supported_versions', $versions, HOUR_IN_SECONDS);",
    "        set_transient('surfside_youversion_mobile_supported_versions', $versions, 6 * HOUR_IN_SECONDS);",
    'YouVersion versions cache duration'
)

if 'surfside_yv_passage_' not in text or "surfside_tools_public_api_rate_limit('youversion_passage'" not in text:
    raise SystemExit('YouVersion passage protection was not applied')
path.write_text(text)
