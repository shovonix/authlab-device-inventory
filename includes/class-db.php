<?php
if (!defined('ABSPATH')) exit;

class ADI_DB {

    const DB_VERSION = '5';

    public static function devices_table() {
        global $wpdb;
        return $wpdb->prefix . 'adi_devices';
    }

    public static function logs_table() {
        global $wpdb;
        return $wpdb->prefix . 'adi_device_logs';
    }

    public static function counters_table() {
        global $wpdb;
        return $wpdb->prefix . 'adi_counters';
    }

    public static function install() {
        global $wpdb;
        $prev_version = get_option('adi_db_version'); // false on a brand-new install
        $charset_collate = $wpdb->get_charset_collate();

        $devices_table = self::devices_table();
        $logs_table = self::logs_table();
        $counters_table = self::counters_table();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql1 = "CREATE TABLE $devices_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sticker_code VARCHAR(64) NOT NULL,
            brand VARCHAR(64) DEFAULT '',
            device_name VARCHAR(191) DEFAULT '',
            screen_size VARCHAR(64) DEFAULT '',
            model_year VARCHAR(16) DEFAULT '',
            chip VARCHAR(128) DEFAULT '',
            memory VARCHAR(64) DEFAULT '',
            serial_number VARCHAR(191) DEFAULT '',
            current_owner VARCHAR(191) DEFAULT '',
            owner_email VARCHAR(191) DEFAULT '',
            department VARCHAR(64) DEFAULT '',
            status VARCHAR(32) NOT NULL DEFAULT 'In Stock',
            photo_url VARCHAR(500) DEFAULT '',
            specs_locked TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sticker_code (sticker_code)
        ) $charset_collate;";

        $sql2 = "CREATE TABLE $logs_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            device_id BIGINT UNSIGNED NOT NULL,
            note TEXT NOT NULL,
            kind VARCHAR(16) NOT NULL DEFAULT 'note',
            photo_url VARCHAR(500) DEFAULT '',
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_by VARCHAR(191) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            edited_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY device_id (device_id)
        ) $charset_collate;";

        // Note the two spaces after PRIMARY KEY — dbDelta is picky about that.
        $sql3 = "CREATE TABLE $counters_table (
            name VARCHAR(64) NOT NULL,
            value BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (name)
        ) $charset_collate;";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);

        // Auto-ID setup (both steps are safe to run repeatedly).
        ADI_IDs::init_counter();
        ADI_IDs::migrate_pending_codes();

        // Upgrading from before details could be locked: every device that has already been
        // reviewed (anything other than Pending Review) becomes locked now.
        if ($prev_version !== false && version_compare((string) $prev_version, '5', '<')) {
            $wpdb->query("UPDATE $devices_table SET specs_locked = 1 WHERE status <> 'Pending Review'");
        }

        update_option('adi_db_version', self::DB_VERSION);
    }

    // Runs on every admin load; upgrades the schema in place if the plugin
    // was updated without a deactivate/reactivate cycle.
    public static function maybe_upgrade() {
        if (get_option('adi_db_version') !== self::DB_VERSION) {
            self::install();
        }
    }
}

