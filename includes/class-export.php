<?php
if (!defined('ABSPATH')) exit;

/**
 * Settings > Export: download the device list as a CSV (opens in Excel / Google Sheets).
 * Real devices only — the demo devices (DEMO-...) are always left out. Optional status filter.
 * Admins only (manage_options), protected by a nonce.
 */
class ADI_Export {

    const STATUSES = ['Pending Review', 'In Use', 'In Stock', 'Need repair', 'In Repair', 'Dead'];

    public static function init() {
        add_action('admin_post_adi_export_devices', [__CLASS__, 'handle_export']);
    }

    /** Real (non-demo) device count per status. */
    private static function counts() {
        global $wpdb;
        $t = ADI_DB::devices_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) AS n FROM $t WHERE sticker_code NOT LIKE %s GROUP BY status",
            $wpdb->esc_like(ADI_Demo::PREFIX) . '%'
        ), ARRAY_A);
        $out = [];
        foreach ((array) $rows as $r) $out[$r['status']] = (int) $r['n'];
        return $out;
    }

    // Excel runs a cell that starts with = + - @ as a formula; a leading apostrophe makes it plain text.
    private static function cell($v) {
        $v = (string) $v;
        if ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) $v = "'" . $v;
        return $v;
    }

    /** Real (non-demo) devices, optionally only the given statuses, ordered by ID. */
    public static function get_rows($picked = []) {
        global $wpdb;
        $t = ADI_DB::devices_table();
        $where = 'sticker_code NOT LIKE %s';
        $args = [$wpdb->esc_like(ADI_Demo::PREFIX) . '%'];
        if ($picked) {                                   // nothing ticked = every status
            $where .= ' AND status IN (' . implode(',', array_fill(0, count($picked), '%s')) . ')';
            $args = array_merge($args, $picked);
        }
        return (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE $where ORDER BY sticker_code ASC", $args), ARRAY_A);
    }

    /** Writes the header row + one line per device. Used by the download and the Google Sheets link. */
    public static function write_csv($fh, $rows) {
        $cols = [
            'sticker_code' => 'Device ID', 'brand' => 'Brand', 'device_name' => 'Device Name', 'screen_size' => 'Screen Size',
            'model_year' => 'Model Year', 'chip' => 'Chip', 'memory' => 'Memory', 'serial_number' => 'Serial Number',
            'current_owner' => 'Current Owner', 'owner_email' => 'Owner Email', 'department' => 'Department',
            'status' => 'Status', 'specs_locked' => 'Details Locked', 'photo_url' => 'Photo URL',
            'created_at' => 'Added', 'updated_at' => 'Last Updated',
        ];
        fputcsv($fh, array_values($cols));
        foreach ($rows as $r) {
            $line = [];
            foreach ($cols as $key => $label) {
                $v = isset($r[$key]) ? $r[$key] : '';
                if ($key === 'specs_locked') $v = $v ? 'Yes' : 'No';
                $line[] = self::cell($v);
            }
            fputcsv($fh, $line);
        }
    }

    public static function handle_export() {
        if (!current_user_can('manage_options')) wp_die('You are not allowed to export devices.', 403);
        check_admin_referer('adi_export_devices');

        $picked = isset($_POST['statuses']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['statuses'])) : [];
        $picked = array_values(array_intersect(self::STATUSES, $picked)); // only known statuses
        $rows = self::get_rows($picked);

        $name = 'devices-' . wp_date('Y-m-d');
        if (count($picked) === 1) $name .= '-' . sanitize_title($picked[0]);
        elseif ($picked) $name .= '-filtered';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '.csv"');
        header('X-Content-Type-Options: nosniff');

        $fh = fopen('php://output', 'w');
        fwrite($fh, "\xEF\xBB\xBF");                    // BOM so Excel reads Bangla / UTF-8 correctly
        self::write_csv($fh, $rows);
        fclose($fh);
        exit;
    }

    public static function render_card() {
        $counts = self::counts();
        $total = array_sum($counts);
        ?>
        <div class="adi-card" id="adi-export-card">
            <h3 style="margin-top:0;">Export devices (CSV)</h3>
            <p class="adi-muted">
                Download the device list as a CSV file that opens in Excel or Google Sheets.
                Demo devices are never included. Tick statuses to export only those; leave all unticked to export every device
                (<?php echo (int) $total; ?> now).
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="adi_export_devices">
                <?php wp_nonce_field('adi_export_devices'); ?>
                <div class="adi-field adi-export-statuses">
                    <?php foreach (self::STATUSES as $s) : ?>
                        <label class="adi-check-row">
                            <input type="checkbox" name="statuses[]" value="<?php echo esc_attr($s); ?>">
                            <span><?php echo esc_html($s); ?> <span class="adi-muted">(<?php echo (int) ($counts[$s] ?? 0); ?>)</span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="adi-btn">Download CSV</button>
            </form>
        </div>
        <?php
    }
}
