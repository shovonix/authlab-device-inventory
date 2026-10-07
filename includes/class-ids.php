<?php
if (!defined('ABSPATH')) exit;

/**
 * Device ID generator.
 *
 * Codes run ALD-A001 ... ALD-A999, then ALD-B001 ... ALD-B999, and so on up
 * to ALD-Z999 (25,974 devices). A code is assigned once, when the device is
 * created, and is never changed or reused (a deleted device leaves a gap).
 *
 * The counter lives in its own table and is incremented with MySQL's
 * LAST_INSERT_ID(expr) trick, which is atomic — two people submitting the
 * form at the same moment can't get the same code.
 */
class ADI_IDs {

    const PREFIX = 'ALD-';
    const PER_LETTER = 999;
    const MAX_SEQ = 25974; // 26 letters * 999
    const COUNTER_NAME = 'device_seq';

    // 1 => ALD-A001, 999 => ALD-A999, 1000 => ALD-B001 ... 25974 => ALD-Z999
    public static function format_code($seq) {
        $seq = (int) $seq;
        if ($seq < 1 || $seq > self::MAX_SEQ) return null;
        $letter = chr(65 + intdiv($seq - 1, self::PER_LETTER));
        $num = (($seq - 1) % self::PER_LETTER) + 1;
        return self::PREFIX . $letter . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
    }

    // Inverse of format_code(). Returns null for anything that isn't an auto code.
    public static function parse_code($code) {
        if (!preg_match('/^ALD-([A-Z])(\d{3})$/', (string) $code, $m)) return null;
        $num = (int) $m[2];
        if ($num < 1) return null;
        return (ord($m[1]) - 65) * self::PER_LETTER + $num;
    }

    // Reserves and returns the next code, or a WP_Error if the ID space is used up.
    public static function next_code() {
        global $wpdb;
        $table = ADI_DB::counters_table();

        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET value = LAST_INSERT_ID(value + 1) WHERE name = %s",
            self::COUNTER_NAME
        ));
        if (!$affected) {
            // Counter row missing (shouldn't happen after install) — create it and retry once.
            self::init_counter();
            $affected = $wpdb->query($wpdb->prepare(
                "UPDATE $table SET value = LAST_INSERT_ID(value + 1) WHERE name = %s",
                self::COUNTER_NAME
            ));
            if (!$affected) {
                return new WP_Error('id_counter_failed', 'Could not generate a device ID', ['status' => 500]);
            }
        }

        $seq = (int) $wpdb->get_var('SELECT LAST_INSERT_ID()');
        $code = self::format_code($seq);
        if ($code === null) {
            return new WP_Error('id_space_exhausted', 'No more device IDs available (ALD-Z999 reached)', ['status' => 500]);
        }
        return $code;
    }

    // Creates the counter row if missing, starting after the highest existing
    // auto code so a manually-assigned ALD-A001 is never duplicated.
    public static function init_counter() {
        global $wpdb;
        $counters = ADI_DB::counters_table();
        $devices = ADI_DB::devices_table();

        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $counters WHERE name = %s", self::COUNTER_NAME));
        if ($exists) return;

        $max = 0;
        $codes = $wpdb->get_col("SELECT sticker_code FROM $devices");
        foreach ($codes as $code) {
            $seq = self::parse_code($code);
            if ($seq !== null && $seq > $max) $max = $seq;
        }
        $wpdb->insert($counters, ['name' => self::COUNTER_NAME, 'value' => $max]);
    }

    // One-time cleanup: earlier versions gave form submissions a temporary
    // PENDING-xxxx code until an admin assigned a real one. Give those their
    // real auto code now, oldest first.
    public static function migrate_pending_codes() {
        global $wpdb;
        $devices = ADI_DB::devices_table();
        $rows = $wpdb->get_results(
            "SELECT id FROM $devices WHERE sticker_code LIKE 'PENDING-%' ORDER BY created_at ASC, id ASC",
            ARRAY_A
        );
        foreach ($rows as $row) {
            $code = self::next_code();
            if (is_wp_error($code)) return;
            $wpdb->update($devices, ['sticker_code' => $code], ['id' => (int) $row['id']]);
        }
    }
}
