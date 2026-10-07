<?php
if (!defined('ABSPATH')) exit;

/**
 * Google Sheets live link.
 *
 * A secret URL that returns the device list as CSV, for Google Sheets' =IMPORTDATA("...").
 * Google fetches it without logging in, so the long random key in the URL is the only lock:
 * anyone who has the link can read the list. It is OFF by default; "Make a new link"
 * replaces the key so an old, leaked link stops working at once.
 * Same data as the CSV download: real devices only (no DEMO-...), all statuses.
 * Google can only reach a site that is on the internet — not a LocalWP / localhost site.
 */
class ADI_Sheets {

    const OPT_ON  = 'adi_sheets_enabled';
    const OPT_KEY = 'adi_sheets_key';

    public static function init() {
        // The feed: Google is not logged in, so both the logged-in and logged-out hooks serve it.
        add_action('admin_post_nopriv_adi_sheets_feed', [__CLASS__, 'serve_feed']);
        add_action('admin_post_adi_sheets_feed', [__CLASS__, 'serve_feed']);
        // Settings buttons (admins only).
        add_action('admin_post_adi_sheets_save', [__CLASS__, 'handle_save']);
    }

    public static function enabled() { return get_option(self::OPT_ON, '0') === '1'; }

    private static function key() {
        $k = (string) get_option(self::OPT_KEY, '');
        if (strlen($k) < 32) { $k = wp_generate_password(40, false, false); update_option(self::OPT_KEY, $k, false); }
        return $k;
    }

    public static function feed_url() {
        return add_query_arg(['action' => 'adi_sheets_feed', 'key' => self::key()], admin_url('admin-post.php'));
    }

    private static function is_local_site() {
        $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        return $host === 'localhost' || $host === '127.0.0.1' || substr($host, -6) === '.local' || substr($host, -5) === '.test';
    }

    public static function serve_feed() {
        $given = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        if (!self::enabled() || $given === '' || !hash_equals(self::key(), $given)) {
            status_header(403);
            nocache_headers();
            header('Content-Type: text/plain; charset=utf-8');
            echo 'This link is turned off or no longer valid.';
            exit;
        }
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');
        $fh = fopen('php://output', 'w');
        ADI_Export::write_csv($fh, ADI_Export::get_rows());   // no BOM: Google reads UTF-8 as is
        fclose($fh);
        exit;
    }

    public static function handle_save() {
        if (!current_user_can('manage_options')) wp_die('You are not allowed to do that.', '', ['response' => 403]);
        check_admin_referer('adi_sheets_save');
        $do = isset($_POST['do']) ? sanitize_key($_POST['do']) : '';
        $msg = '';
        if ($do === 'on')  { self::key(); update_option(self::OPT_ON, '1', false); $msg = 'on'; }
        if ($do === 'off') { update_option(self::OPT_ON, '0', false); $msg = 'off'; }
        if ($do === 'new') { update_option(self::OPT_KEY, wp_generate_password(40, false, false), false); $msg = 'new'; }
        wp_safe_redirect(ADI_Settings::tab_url('sheets', ['adi_sheets' => $msg]));
        exit;
    }

    private static function button($do, $label, $class = 'adi-btn secondary adi-sm', $confirm = '') {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin:0 8px 8px 0;"<?php if ($confirm) echo ' onsubmit="return confirm(\'' . esc_js($confirm) . '\');"'; ?>>
            <input type="hidden" name="action" value="adi_sheets_save">
            <input type="hidden" name="do" value="<?php echo esc_attr($do); ?>">
            <?php wp_nonce_field('adi_sheets_save'); ?>
            <button type="submit" class="<?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></button>
        </form>
        <?php
    }

    public static function render_card() {
        $on = self::enabled();
        $what = isset($_GET['adi_sheets']) ? sanitize_key($_GET['adi_sheets']) : '';
        $msgs = ['on' => 'Google Sheets link turned on.', 'off' => 'Google Sheets link turned off. The old link now returns an error.', 'new' => 'New link made. The old link no longer works — update the formula in your sheet.'];
        ?>
        <div class="adi-card" id="adi-sheets-card">
            <h3 style="margin-top:0;">Google Sheets live link</h3>
            <?php if (isset($msgs[$what])) : ?><p><strong><?php echo esc_html($msgs[$what]); ?></strong></p><?php endif; ?>
            <p class="adi-muted">
                Put the device list into a Google Sheet that keeps itself up to date. Same columns as the CSV download, real devices only.
                Google refreshes it by itself (roughly every hour); reopen the sheet or edit the formula to refresh sooner.
            </p>
            <?php if (self::is_local_site()) : ?>
                <p class="adi-error" style="display:block;">This site (<?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?>) is on this computer only, so Google cannot reach it. The link will work once the plugin runs on the live site.</p>
            <?php endif; ?>

            <?php if ($on) : $url = self::feed_url(); $formula = '=IMPORTDATA("' . $url . '")'; ?>
                <div class="adi-field">
                    <label for="adi-sheets-formula">Paste this into cell A1 of a Google Sheet</label>
                    <div class="adi-copy-row">
                        <input type="text" id="adi-sheets-formula" readonly value="<?php echo esc_attr($formula); ?>" onclick="this.select();">
                        <button type="button" class="adi-btn adi-sm" onclick="var i=document.getElementById('adi-sheets-formula');i.select();(navigator.clipboard?navigator.clipboard.writeText(i.value):Promise.reject()).then(()=>{this.textContent='Copied';},()=>{document.execCommand('copy');this.textContent='Copied';});">Copy</button>
                    </div>
                    <p class="adi-muted" style="margin-top:6px;">
                        Anyone with this link can read every device, including names and emails. Share the <em>sheet</em> with people, not the link.
                        If the link leaks, click "Make a new link".
                    </p>
                </div>
                <?php self::button('new', 'Make a new link', 'adi-btn secondary adi-sm', 'Make a new link? The old link stops working and the formula in your sheet must be replaced.'); ?>
                <?php self::button('off', 'Turn off', 'adi-btn adi-danger adi-sm'); ?>
            <?php else : ?>
                <p class="adi-muted">Off. Nothing can be read from outside until you turn it on.</p>
                <?php self::button('on', 'Turn on', 'adi-btn adi-sm'); ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
