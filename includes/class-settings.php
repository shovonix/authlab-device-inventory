<?php
if (!defined('ABSPATH')) exit;

class ADI_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function register_menu() {
        add_submenu_page('adi-devices', 'Settings', 'Settings', 'manage_options', 'adi-settings', [__CLASS__, 'render']);
    }

    public static function register_settings() {
        register_setting('adi_settings_group', 'adi_device_view_slug', [
            'sanitize_callback' => 'sanitize_title',
            'default' => 'device-record',
        ]);
        register_setting('adi_settings_group', 'adi_list_pagination', [
            'sanitize_callback' => function ($v) { return $v === '1' ? '1' : '0'; },
            'default' => '0',
        ]);
    }

    /** All Devices list: split into pages of 10 (true) or show every device on one page (false, the default). */
    public static function pagination_enabled() {
        return get_option('adi_list_pagination', '0') === '1';
    }

    public static function get_view_slug() {
        return get_option('adi_device_view_slug', 'device-record');
    }

    public static function get_view_url() {
        return home_url('/' . self::get_view_slug() . '/');
    }

    /** Left-menu sections: slug => [label, short hint]. */
    private static function tabs() {
        return [
            'general' => ['General', 'Record page, list'],
            'roles'   => ['Access & Roles', 'Admin, Manager, User'],
            'export'  => ['Export (CSV)', 'Download the list'],
            'sheets'  => ['Google Sheets', 'Live link'],
            'demo'    => ['Demo data', 'Try with sample devices'],
        ];
    }

    /** URL of one section; every action on Settings sends the person back to its own section. */
    public static function tab_url($tab, $extra = []) {
        return add_query_arg(array_merge(['page' => 'adi-settings', 'tab' => $tab], $extra), admin_url('admin.php'));
    }

    private static function render_general() {
        ?>
        <div class="adi-card">
            <h3>General</h3>
            <form method="post" action="options.php">
                <?php settings_fields('adi_settings_group'); ?>
                <div class="adi-field">
                    <label for="adi-set-slug">Device record page slug</label>
                    <input type="text" id="adi-set-slug" name="adi_device_view_slug" value="<?php echo esc_attr(self::get_view_slug()); ?>">
                    <p class="adi-muted" style="margin-top:6px;">
                        Must match the slug of the WordPress page that contains the
                        <code>[device_view]</code> shortcode. QR codes will link to
                        <code><?php echo esc_html(self::get_view_url()); ?>?id=X</code>.
                    </p>
                </div>
                <div class="adi-field">
                    <label>All Devices list</label>
                    <label class="adi-check-row">
                        <input type="checkbox" name="adi_list_pagination" value="1" <?php checked(self::pagination_enabled()); ?>>
                        <span>Split the list into pages (10 devices per page)</span>
                    </label>
                    <p class="adi-muted" style="margin-top:6px;">
                        Off: every device is shown on one page. On: 10 per page with Previous / Next.
                    </p>
                </div>
                <button type="submit" class="adi-btn">Save settings</button>
            </form>
        </div>
        <?php
    }

    public static function render() {
        if (!current_user_can('manage_options')) return;
        $tabs = self::tabs();
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        if (!isset($tabs[$tab])) $tab = 'general';
        $badge = [
            'sheets' => ADI_Sheets::enabled() ? ['On', 'on'] : ['Off', 'off'],
        ];
        $demo_n = ADI_Demo::count();
        if ($demo_n > 0) $badge['demo'] = [(string) $demo_n, 'on'];
        ?>
        <div class="wrap adi-wrap adi-wrap-wide">
            <div class="adi-app">
                <?php echo ADI_Admin::topbar('Settings'); ?>
                <?php settings_errors(); ?>
                <div class="adi-settings-layout">
                    <nav class="adi-set-nav" aria-label="Settings sections">
                        <?php foreach ($tabs as $slug => $t) : ?>
                            <a href="<?php echo esc_url(self::tab_url($slug)); ?>" class="adi-set-link<?php echo $slug === $tab ? ' is-active' : ''; ?>"<?php echo $slug === $tab ? ' aria-current="page"' : ''; ?>>
                                <span class="adi-set-label"><?php echo esc_html($t[0]); ?></span>
                                <span class="adi-set-hint"><?php echo esc_html($t[1]); ?></span>
                                <?php if (isset($badge[$slug])) : ?><span class="adi-set-badge is-<?php echo esc_attr($badge[$slug][1]); ?>"><?php echo esc_html($badge[$slug][0]); ?></span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <div class="adi-set-body">
                        <?php
                        if ($tab === 'general') self::render_general();
                        elseif ($tab === 'roles') ADI_Roles::render_card();
                        elseif ($tab === 'export') ADI_Export::render_card();
                        elseif ($tab === 'sheets') ADI_Sheets::render_card();
                        else ADI_Demo::render_card();
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
