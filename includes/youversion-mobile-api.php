<?php
/**
 * Public, app-safe YouVersion REST resources.
 */
if (!defined('ABSPATH')) { exit; }

add_action('rest_api_init', 'surfside_tools_youversion_mobile_api_register_routes');

function surfside_tools_youversion_mobile_api_register_routes() {
    register_rest_route('surfside/v1', '/bible/versions', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'surfside_tools_youversion_mobile_api_versions',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('surfside/v1', '/bible/passage', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'surfside_tools_youversion_mobile_api_passage',
        'permission_callback' => '__return_true',
        'args' => array(
            'reference' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'surfside_tools_youversion_mobile_api_validate_reference',
            ),
            'version' => array(
                'required' => false,
                'default' => 'NIV',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));
}

function surfside_tools_youversion_mobile_api_validate_reference($value) {
    $value = strtoupper(trim((string)$value));
    return $value !== ''
        && strlen($value) <= 80
        && (bool)preg_match('/^[A-Z0-9.\-]+$/', $value);
}

function surfside_tools_youversion_mobile_api_versions() {
    $versions = surfside_tools_youversion_mobile_api_get_versions();
    if (is_wp_error($versions)) {
        return $versions;
    }

    return rest_ensure_response(array(
        'api_version' => 1,
        'generated_at' => current_datetime()->format(DATE_ATOM),
        'default_version' => 'NIV',
        'default_version_id' => 111,
        'count' => count($versions),
        'versions' => array_values(array_map('surfside_tools_youversion_mobile_api_public_version', $versions)),
    ));
}

function surfside_tools_youversion_mobile_api_passage(WP_REST_Request $request) {
    $reference = strtoupper(trim((string)$request->get_param('reference')));
    $requested_version = trim((string)$request->get_param('version'));
    if ($requested_version === '') {
        $requested_version = 'NIV';
    }

    $version = surfside_tools_youversion_mobile_api_resolve_version($requested_version);
    if (is_wp_error($version)) {
        return $version;
    }

    $version_id = absint($version['id'] ?? 0);
    if (!$version_id) {
        return new WP_Error('surfside_bible_version_invalid', 'The selected Bible version is unavailable.', array('status' => 404));
    }

    $passage = surfside_tools_youversion_request('bibles/' . $version_id . '/passages/' . rawurlencode($reference));
    if (is_wp_error($passage)) {
        return surfside_tools_youversion_mobile_api_public_error($passage, 'The requested Bible passage is unavailable.');
    }

    $content = (string)($passage['content'] ?? '');
    $canonical_reference = (string)($passage['reference'] ?? $reference);
    $passage_id = (string)($passage['id'] ?? $reference);
    $copyright = trim((string)($version['copyright'] ?? ''));
    if ($copyright === '') {
        $copyright = trim((string)($version['promotional_content'] ?? ''));
    }

    return rest_ensure_response(array(
        'api_version' => 1,
        'generated_at' => current_datetime()->format(DATE_ATOM),
        'passage' => array(
            'id' => $passage_id,
            'reference' => $canonical_reference,
            'content' => wp_kses_post($content),
        ),
        'version' => surfside_tools_youversion_mobile_api_public_version($version),
        'attribution' => $copyright,
        'explore_more_url' => surfside_tools_youversion_mobile_api_passage_link($version_id, $passage_id, $version),
    ));
}

function surfside_tools_youversion_mobile_api_resolve_version($requested) {
    $requested = trim((string)$requested);
    if ($requested === '') {
        $requested = 'NIV';
    }

    if (ctype_digit($requested)) {
        $id = absint($requested);
        $version = surfside_tools_youversion_request('bibles/' . $id);
        if (is_wp_error($version)) {
            return surfside_tools_youversion_mobile_api_public_error($version, 'The selected Bible version is unavailable.');
        }
        return is_array($version) ? $version : array();
    }

    // YouVersion's documented NIV version ID is 111. Resolve the default
    // directly so passage lookup does not depend on collection pagination.
    if (strtoupper($requested) === 'NIV') {
        $version = surfside_tools_youversion_request('bibles/111');
        if (is_wp_error($version)) {
            return surfside_tools_youversion_mobile_api_public_error($version, 'NIV is not available for this Surfside YouVersion integration.');
        }
        return is_array($version) ? $version : array();
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
            return $version;
        }
    }

    return new WP_Error('surfside_bible_version_not_found', 'The selected Bible version is unavailable.', array('status' => 404));
}

function surfside_tools_youversion_mobile_api_get_versions() {
    $cached = get_transient('surfside_youversion_mobile_english_versions');
    if (is_array($cached) && !empty($cached)) {
        return $cached;
    }

    $versions = array();
    $page_token = '';
    $pages = 0;

    do {
        $query = array(
            'language_ranges[]' => 'en*',
            'page_size' => 100,
        );
        if ($page_token !== '') {
            $query['page_token'] = $page_token;
        }

        $result = surfside_tools_youversion_request('bibles', $query);
        if (is_wp_error($result)) {
            return surfside_tools_youversion_mobile_api_public_error($result, 'Bible versions are temporarily unavailable.');
        }

        foreach ((array)($result['data'] ?? array()) as $version) {
            if (is_array($version) && !empty($version['id'])) {
                $versions[] = $version;
            }
        }

        $page_token = trim((string)($result['next_page_token'] ?? ''));
        $pages++;
    } while ($page_token !== '' && $pages < 10);

    if (!empty($versions)) {
        usort($versions, function ($a, $b) {
            return strcasecmp((string)($a['localized_abbreviation'] ?? $a['abbreviation'] ?? ''), (string)($b['localized_abbreviation'] ?? $b['abbreviation'] ?? ''));
        });
        set_transient('surfside_youversion_mobile_english_versions', $versions, HOUR_IN_SECONDS);
    }

    return $versions;
}

function surfside_tools_youversion_mobile_api_public_version($version) {
    $version = (array)$version;
    return array(
        'id' => absint($version['id'] ?? 0),
        'abbreviation' => (string)($version['localized_abbreviation'] ?? $version['abbreviation'] ?? ''),
        'title' => (string)($version['localized_title'] ?? $version['title'] ?? ''),
        'language_tag' => (string)($version['language_tag'] ?? ''),
        'copyright' => (string)($version['copyright'] ?? ''),
        'publisher_url' => esc_url_raw($version['publisher_url'] ?? ''),
        'youversion_deep_link' => esc_url_raw($version['youversion_deep_link'] ?? ''),
    );
}

function surfside_tools_youversion_mobile_api_passage_link($version_id, $passage_id, $version) {
    $passage_id = trim((string)$passage_id);
    if ($passage_id !== '') {
        // bible.com universal links open in the Bible App when the platform can
        // hand them off, while remaining usable in a browser as a fallback.
        return esc_url_raw('https://www.bible.com/bible/' . absint($version_id) . '/' . rawurlencode($passage_id));
    }
    return esc_url_raw($version['youversion_deep_link'] ?? '');
}

function surfside_tools_youversion_mobile_api_public_error($error, $fallback) {
    if (!is_wp_error($error)) {
        return new WP_Error('surfside_bible_unavailable', $fallback, array('status' => 502));
    }

    $data = $error->get_error_data();
    $upstream_status = is_array($data) ? absint($data['status'] ?? 0) : 0;
    $status = $upstream_status === 404 ? 404 : ($upstream_status === 429 ? 429 : 502);

    return new WP_Error(
        'surfside_bible_unavailable',
        $fallback,
        array('status' => $status)
    );
}
