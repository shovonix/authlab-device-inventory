<?php
/**
 * Plugin Name: AuthLab Device Inventory
 * Description: Internal device/laptop inventory tracking — device records, QR codes, a public self-service intake form, and a history log. Uses WordPress's own login/roles.
 * Version: 1.0.0
 * Author: AuthLab
 * Text Domain: authlab-device-inventory
 */

if (!defined('ABSPATH')) exit;

define('ADI_PATH', plugin_dir_path(__FILE__));
define('ADI_URL', plugin_dir_url(__FILE__));
define('ADI_VERSION', '1.0.0');

require_once ADI_PATH . 'includes/class-db.php';
require_once ADI_PATH . 'includes/class-ids.php';
require_once ADI_PATH . 'includes/class-rest-api.php';
require_once ADI_PATH . 'includes/class-admin.php';
require_once ADI_PATH . 'includes/class-shortcodes.php';
require_once ADI_PATH . 'includes/class-public.php';
require_once ADI_PATH . 'includes/class-settings.php';
require_once ADI_PATH . 'includes/class-demo.php';
require_once ADI_PATH . 'includes/class-export.php';
require_once ADI_PATH . 'includes/class-sheets.php';
require_once ADI_PATH . 'includes/class-roles.php';
require_once ADI_PATH . 'includes/class-stickers.php';

register_activation_hook(__FILE__, ['ADI_DB', 'install']);

add_action('init', ['ADI_DB', 'maybe_upgrade']);

add_action('plugins_loaded', function () {
    ADI_REST_API::init();
    ADI_Admin::init();
    ADI_Shortcodes::init();
    ADI_Public::init();
    ADI_Settings::init();
    ADI_Demo::init();
    ADI_Export::init();
    ADI_Sheets::init();
    ADI_Roles::init();
    ADI_Stickers::init();
});

/**
 * Any logged-in WordPress user can view/scan/add history notes.
 * Only users who can 'manage_options' (Administrators by default) can
 * add/edit devices, approve pending submissions, and change specs.
 * To let a non-admin role (e.g. an "HR" role you create separately) manage
 * devices without full site admin access, grant that role the
 * 'manage_device_inventory' capability — this plugin checks for either
 * 'manage_options' or 'manage_device_inventory'.
 */
function adi_current_user_is_manager() {
    return current_user_can('manage_options') || current_user_can('manage_device_inventory');
}
