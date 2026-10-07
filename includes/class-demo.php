<?php
if (!defined('ABSPATH')) exit;

/**
 * Demo data: 14 made-up devices (owners, statuses, history) so the plugin can be seen with content.
 * Add / remove them in Settings > Demo data. They use the codes DEMO-001 ... DEMO-014, so they never use
 * up (or leave gaps in) the real ALD-A001 ... numbers, and "Remove" deletes exactly these and their history.
 */
class ADI_Demo {

    const PREFIX = 'DEMO-';

    public static function init() {
        add_action('admin_post_adi_demo_add', [__CLASS__, 'handle_add']);
        add_action('admin_post_adi_demo_remove', [__CLASS__, 'handle_remove']);
    }

    public static function count() {
        global $wpdb;
        $t = ADI_DB::devices_table();
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE sticker_code LIKE %s", $wpdb->esc_like(self::PREFIX) . '%'));
    }

    // brand, name, screen, year, chip, memory, owner, department, status, locked, intake screenshot?, age in hours, history
    // history entry: [kind, hours ago, by, note, picture: 0 none / 1 intake screenshot / 2 damage photo]
    private static function rows() {
        $hr = 'Rina Sultana'; $it = 'Karim Uddin';
        $added = function ($h, $owner = '', $dept = '') use ($hr) {
            $e = [['system', $h, $hr, 'Device added.', 0]];
            if ($owner !== '') $e[] = ['change', $h - 1, $hr, "Status: In Stock → In Use\nOwner: — → $owner\nDepartment: — → $dept", 0];
            return $e;
        };
        $intake = function ($h, $name, $n) {
            return ['system', $h, $name, sprintf('Submitted via the self-service intake form by %s (%s). Assigned ID %s%03d.', $name, self::email($name), self::PREFIX, $n), 1];
        };
        $approved = function ($h) use ($hr) { return ['change', $h, $hr, 'Status: Pending Review → In Use', 0]; };
        return [
            ['Apple', 'MacBook Pro', '14-inch', 'Nov 2023', 'Apple M3 Pro', '18 GB', 'Nadia Rahman', 'Marketing', 'In Use', 1, 1, 3600, [
                $intake(3600, 'Nadia Rahman', 1), $approved(3560), ['note', 3559, $hr, 'Checked against the screenshot. Charger, sleeve and sticker handed over.', 0], ['note', 1440, $it, 'Battery health 96% at the 3-month check. No issues.', 0]]],
            ['Apple', 'MacBook Pro', '16-inch', 'Nov 2023', 'Apple M3 Pro', '36 GB', 'Tanvir Hossain', 'Dev', 'In Use', 1, 1, 3170, [
                $intake(3170, 'Tanvir Hossain', 2), $approved(3150), ['note', 700, $it, 'Updated to the latest macOS. Dev tools installed.', 0]]],
            ['Apple', 'MacBook Air', '13-inch', '2022', 'Apple M2', '8 GB', 'Farhana Akter', 'HR', 'In Use', 1, 0, 5760, $added(5760, 'Farhana Akter', 'HR')],
            ['Lenovo', 'ThinkPad T14 Gen 3', '14-inch', '2022', 'Intel Core i7-1260P', '16 GB', 'Imran Chowdhury', 'Dev', 'In Use', 1, 1, 4800, [
                $intake(4800, 'Imran Chowdhury', 4), $approved(4780), ['note', 2900, $it, 'Docking station and external monitor assigned.', 0]]],
            ['Dell', 'Latitude 5440', '14-inch', '2023', 'Intel Core i5-1335U', '16 GB', 'Sadia Islam', 'Support', 'In Use', 1, 0, 2160, $added(2160, 'Sadia Islam', 'Support')],
            ['Apple', 'MacBook Pro', '16-inch', 'Nov 2023', 'Apple M3 Pro', '36 GB', 'Rakib Hasan', 'MGD', 'Need repair', 1, 1, 2640, [
                $intake(2640, 'Rakib Hasan', 6), $approved(2620), ['note', 52, $it, 'Rakib reports 3 keyboard keys not registering. Photo attached.', 2], ['change', 51, $it, 'Status: In Use → Need repair', 0]]],
            ['Apple', 'MacBook Air', '15-inch', '2023', 'Apple M2', '16 GB', 'Mehnaz Sultana', 'Marketing', 'In Use', 1, 0, 1800, array_merge($added(1800, 'Mehnaz Sultana', 'Marketing'), [['note', 600, $hr, 'Sleeve and USB-C hub handed over.', 0]])],
            ['HP', 'EliteBook 840 G9', '14-inch', '2022', 'Intel Core i7-1255U', '16 GB', 'Arif Khan', 'Dev', 'In Repair', 1, 1, 4320, [
                $intake(4320, 'Arif Khan', 8), $approved(4300), ['change', 30, $it, 'Status: In Use → In Repair', 0], ['note', 30, $it, 'Screen flicker under load. Sent to the service centre, expected back in about a week.', 0]]],
            ['Apple', 'MacBook Air', '13-inch', '2020', 'Apple M1', '8 GB', '', '', 'In Stock', 1, 0, 9600, array_merge($added(9600, 'Zubair Alam', 'Support'), [
                ['change', 700, $hr, "Status: In Use → In Stock\nOwner: Zubair Alam → —\nDepartment: Support → —", 0], ['note', 699, $hr, 'Returned in good condition. Wiped and reset, ready for the next person.', 0]])],
            ['Dell', 'XPS 13 9315', '13.4-inch', '2022', 'Intel Core i5-1230U', '8 GB', '', '', 'In Stock', 1, 0, 7200, array_merge($added(7200), [['note', 7199, $hr, 'Spare laptop for new joiners. Kept in the HR cabinet.', 0]])],
            ['Apple', 'MacBook Pro', '14-inch', 'Jan 2023', 'Apple M2 Pro', '16 GB', 'Shirin Ahmed', 'HR', 'Dead', 1, 1, 7680, [
                $intake(7680, 'Shirin Ahmed', 11), $approved(7660), ['note', 200, $it, 'Liquid damage. The logic board is dead and not worth repairing. Photo attached.', 2], ['change', 199, $it, 'Status: In Use → Dead', 0]]],
            ['Asus', 'Zenbook 14 OLED', '14-inch', '2024', 'Intel Core Ultra 7 155H', '16 GB', 'Omar Faruk', 'Support', 'In Use', 1, 0, 1080, $added(1080, 'Omar Faruk', 'Support')],
            ['Apple', 'MacBook Pro', '14-inch', 'Nov 2024', 'Apple M4 Pro', '24 GB', 'Lamia Noor', 'Dev', 'Pending Review', 0, 1, 26, [$intake(26, 'Lamia Noor', 13)]],
            ['Apple', 'Mac mini', '', '2023', 'Apple M2 Pro', '32 GB', 'Sabbir Rahman', 'MGD', 'Pending Review', 0, 1, 6, [$intake(6, 'Sabbir Rahman', 14)]],
        ];
    }

    private static function email($name) {
        return strtolower(trim(preg_replace('/[^a-z]+/i', '.', $name), '.')) . '@demo.authlab.test';
    }

    // Adds the demo devices. Does nothing (returns 0) if they are already there.
    public static function seed() {
        global $wpdb;
        if (self::count() > 0) return 0;
        $dev = ADI_DB::devices_table();
        $log = ADI_DB::logs_table();
        $pic = [1 => ADI_URL . 'assets/img/about-this-mac-example.svg', 2 => ADI_URL . 'assets/img/demo-photo.svg'];
        $made = 0;
        foreach (self::rows() as $i => $r) {
            list($brand, $name, $size, $year, $chip, $mem, $owner, $dept, $status, $locked, $shot, $age, $history) = $r;
            $n = $i + 1;
            $latest = $age;
            foreach ($history as $e) $latest = min($latest, $e[1]);
            $ok = $wpdb->query($wpdb->prepare(
                "INSERT INTO $dev (sticker_code, brand, device_name, screen_size, model_year, chip, memory, serial_number, current_owner, owner_email, department, status, photo_url, specs_locked, created_at, updated_at)
                 VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%d, DATE_SUB(NOW(), INTERVAL %d HOUR), DATE_SUB(NOW(), INTERVAL %d HOUR))",
                sprintf('%s%03d', self::PREFIX, $n), $brand, $name, $size, $year, $chip, $mem, 'DEMO' . strtoupper(substr(md5('adi-demo-serial-' . $n), 0, 8)),
                $owner, $owner === '' ? '' : self::email($owner), $dept, $status, $shot ? $pic[1] : '', $locked, $age, $latest
            ));
            if (!$ok) continue;
            $id = (int) $wpdb->insert_id;
            foreach ($history as $e) {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO $log (device_id, note, kind, photo_url, user_id, created_by, created_at) VALUES (%d,%s,%s,%s,0,%s, DATE_SUB(NOW(), INTERVAL %d HOUR))",
                    $id, $e[3], $e[0], $e[4] ? $pic[$e[4]] : '', $e[2], $e[1]
                ));
            }
            $made++;
        }
        return $made;
    }

    // Deletes the demo devices and their history (nothing else).
    public static function remove() {
        global $wpdb;
        $dev = ADI_DB::devices_table();
        $log = ADI_DB::logs_table();
        $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT id FROM $dev WHERE sticker_code LIKE %s", $wpdb->esc_like(self::PREFIX) . '%')));
        if (!$ids) return 0;
        $in = implode(',', $ids);
        $wpdb->query("DELETE FROM $log WHERE device_id IN ($in)");
        $wpdb->query("DELETE FROM $dev WHERE id IN ($in)");
        return count($ids);
    }

    public static function handle_add() {
        if (!current_user_can('manage_options')) wp_die('You are not allowed to do that.', '', ['response' => 403]);
        check_admin_referer('adi_demo_add');
        $n = self::seed();
        wp_safe_redirect(ADI_Settings::tab_url('demo', ['adi_demo' => $n ? 'added' : 'exists', 'n' => $n]));
        exit;
    }

    public static function handle_remove() {
        if (!current_user_can('manage_options')) wp_die('You are not allowed to do that.', '', ['response' => 403]);
        check_admin_referer('adi_demo_remove');
        $n = self::remove();
        wp_safe_redirect(ADI_Settings::tab_url('demo', ['adi_demo' => 'removed', 'n' => $n]));
        exit;
    }

    // The "Demo data" card on the Settings page.
    public static function render_card() {
        $have = self::count();
        $msg = '';
        $what = isset($_GET['adi_demo']) ? sanitize_key($_GET['adi_demo']) : '';
        $n = isset($_GET['n']) ? (int) $_GET['n'] : 0;
        if ($what === 'added') $msg = $n . ' demo devices added. Open All Devices to see them.';
        elseif ($what === 'exists') $msg = 'The demo devices are already there.';
        elseif ($what === 'removed') $msg = $n . ' demo devices and their history removed.';
        ?>
        <div class="adi-card" id="adi-demo-card">
            <h3 style="margin-top:0;">Demo data</h3>
            <?php if ($msg) : ?><p><strong><?php echo esc_html($msg); ?></strong></p><?php endif; ?>
            <p class="adi-muted">
                Fill the plugin with <?php echo count(self::rows()); ?> made-up devices (different statuses, owners and history) to see how it looks with real content.
                They use the IDs <code><?php echo esc_html(self::PREFIX); ?>001</code> to <code><?php echo esc_html(sprintf('%s%03d', self::PREFIX, count(self::rows()))); ?></code>,
                so your real <code>ALD-A001</code> numbers are not affected. Remove them any time with one click.
            </p>
            <?php if ($have > 0) : ?>
                <p class="adi-muted"><?php echo (int) $have; ?> demo devices are in the list now.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Remove the demo devices and all their history? Your real devices are not touched.');">
                    <input type="hidden" name="action" value="adi_demo_remove">
                    <?php wp_nonce_field('adi_demo_remove'); ?>
                    <button type="submit" class="adi-btn adi-danger">Remove demo data</button>
                </form>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="adi_demo_add">
                    <?php wp_nonce_field('adi_demo_add'); ?>
                    <button type="submit" class="adi-btn">Add demo data</button>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}
