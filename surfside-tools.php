<?php
/**
 * Plugin Name: Surfside Tools
 * Description: Custom Surfside website tools for weekly announcements and sermon notes publishing.
 * Version: 1.2.0-dev24
 * Author: Surfside Community Fellowship
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SURFSIDE_TOOLS_VERSION', '1.2.0-dev23');
define('SURFSIDE_TOOLS_PATH', plugin_dir_path(__FILE__));
define('SURFSIDE_TOOLS_URL', plugin_dir_url(__FILE__));

require_once SURFSIDE_TOOLS_PATH . 'includes/core-weekly-tools.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/staff-dashboard.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/calendar-manager.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/location-clarity.php';
require_once SURFSIDE_TOOLS_PATH . 'includes/admin.php';
