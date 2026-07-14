<?php
/**
 * Plugin Name: Surfside Tools
 * Description: Custom Surfside website tools for weekly announcements and sermon notes publishing.
 * Version: 1.3.0
 * Author: Surfside Community Fellowship
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SURFSIDE_TOOLS_VERSION', '1.3.0');
define('SURFSIDE_TOOLS_PATH', plugin_dir_path(__FILE__));
define('SURFSIDE_TOOLS_URL', plugin_dir_url(__FILE__));

require_once SURFSIDE_TOOLS_PATH . 'includes/core-weekly-tools.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/staff-dashboard.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-manager.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-suggestions.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-suggestion-duplicates.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-suggestion-completion.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-suggestion-one-click.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-suggestion-locations.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-suggestion-location-search-fix.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-manager-refinements.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/saved-places-settings.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/productivity-finish.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/productivity-modal-tracking.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/frontend-settings.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/google-places-regression-fix.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/final-productivity-fixes.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/weekly-update-native-google-places.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/location-clarity.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/homepage-manager.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/admin.php';
