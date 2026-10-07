<?php
if (!defined('ABSPATH')) exit;

/**
 * Settings > Access & Roles.
 *
 * Three levels, all based on WordPress users:
 *   Admin   — WordPress Administrators (manage_options): everything, incl. Settings, Export, Print Stickers.
 *   Manager — users given the 'manage_device_inventory' capability here: add / edit / approve devices.
 *   User    — any other logged-in user: view, scan, add history notes.
 * Managers are granted per user (WP_User::add_cap), so no WordPress role is changed.
 * A capability given to a whole role by another plugin also counts; it is shown but not removable here.
 */
class ADI_Roles {

    const CAP = 'manage_device_inventory';

    public static function init() {
        add_action('admin_post_adi_role_save', [__CLASS__, 'handle_save']);
    }

    public static function handle_save() {
        if (!current_user_can('manage_options')) wp_die('You are not allowed to do that.', '', ['response' => 403]);
        check_admin_referer('adi_role_save');
        $uid = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $do  = isset($_POST['do']) ? sanitize_key($_POST['do']) : '';
        $user = $uid ? get_userdata($uid) : false;
        $msg = 'none';
        if ($user && !user_can($user, 'manage_options')) {
            if ($do === 'add')    { $user->add_cap(self::CAP);    $msg = 'added'; }
            if ($do === 'remove') { $user->remove_cap(self::CAP); $msg = 'removed'; }
        }
        wp_safe_redirect(ADI_Settings::tab_url('roles', ['adi_role' => $msg, 'u' => $uid]));
        exit;
    }

    private static function remove_button($u) {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;" onsubmit="return confirm('<?php echo esc_js('Remove Manager access from ' . $u->display_name . '?'); ?>');">
            <input type="hidden" name="action" value="adi_role_save">
            <input type="hidden" name="do" value="remove">
            <input type="hidden" name="user_id" value="<?php echo (int) $u->ID; ?>">
            <?php wp_nonce_field('adi_role_save'); ?>
            <button type="submit" class="adi-btn secondary adi-sm">Remove</button>
        </form>
        <?php
    }

    public static function render_card() {
        $admins = []; $managers = []; $others = [];
        foreach (get_users(['orderby' => 'display_name', 'fields' => 'all']) as $u) {
            if (user_can($u, 'manage_options')) $admins[] = $u;
            elseif (user_can($u, self::CAP)) $managers[] = $u;
            else $others[] = $u;
        }
        $what = isset($_GET['adi_role']) ? sanitize_key($_GET['adi_role']) : '';
        $who = isset($_GET['u']) ? get_userdata((int) $_GET['u']) : false;
        $msg = '';
        if ($what === 'added' && $who)   $msg = $who->display_name . ' is now a Manager.';
        if ($what === 'removed' && $who) $msg = $who->display_name . ' is now a User.';
        $row = function ($u, $level, $action = '') {
            echo '<tr><td>' . esc_html($u->display_name) . '<div class="adi-sub">' . esc_html($u->user_email) . '</div></td>'
                . '<td>' . esc_html($level) . '</td><td style="text-align:right;">' . $action . '</td></tr>';
        };
        ?>
        <div class="adi-card" id="adi-roles-card">
            <h3 style="margin-top:0;">Access &amp; Roles</h3>
            <?php if ($msg) : ?><p><strong><?php echo esc_html($msg); ?></strong></p><?php endif; ?>
            <div class="adi-table-wrap"><table class="adi-table adi-roles-levels">
                <thead><tr><th>Level</th><th>Who</th><th>Can do</th></tr></thead>
                <tbody>
                    <tr><td><strong>Admin</strong></td><td>WordPress Administrators (<?php echo count($admins); ?>)</td><td>Everything: devices, Settings, Export, Google Sheets link, Print Stickers, roles</td></tr>
                    <tr><td><strong>Manager</strong></td><td>Chosen below (<?php echo count($managers); ?>)</td><td>Add, edit and approve devices, change status and owner</td></tr>
                    <tr><td><strong>User</strong></td><td>Every other logged-in user (<?php echo count($others); ?>)</td><td>View devices, scan QR, add history notes</td></tr>
                </tbody>
            </table></div>

            <h4 style="margin:22px 0 8px;">Admins and Managers</h4>
            <div class="adi-table-wrap"><table class="adi-table">
                <tbody>
                <?php
                foreach ($admins as $u) $row($u, 'Admin', '<span class="adi-muted">change in Users</span>');
                foreach ($managers as $u) {
                    if (isset($u->caps[self::CAP])) { ob_start(); self::remove_button($u); $row($u, 'Manager', ob_get_clean()); }
                    else $row($u, 'Manager', '<span class="adi-muted">via their WordPress role</span>');
                }
                if (!$managers) echo '<tr><td colspan="3" class="adi-muted">No Managers yet.</td></tr>';
                ?>
                </tbody>
            </table></div>

            <?php if ($others) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="adi-copy-row" style="margin-top:16px;">
                    <input type="hidden" name="action" value="adi_role_save">
                    <input type="hidden" name="do" value="add">
                    <?php wp_nonce_field('adi_role_save'); ?>
                    <div class="adi-field" style="margin:0;flex:1 1 260px;">
                        <select name="user_id" aria-label="User to make Manager" required>
                            <option value="">Choose a user&hellip;</option>
                            <?php foreach ($others as $u) : ?>
                                <option value="<?php echo (int) $u->ID; ?>"><?php echo esc_html($u->display_name . ' — ' . $u->user_email); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="adi-btn adi-sm">Make Manager</button>
                </form>
            <?php else : ?>
                <p class="adi-muted" style="margin-top:12px;">No other WordPress users yet. Add people in Users &rarr; Add New, then make them Managers here.</p>
            <?php endif; ?>
        </div>
        <?php
    }
}
