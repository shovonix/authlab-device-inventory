<?php
if (!defined('ABSPATH')) exit;

class ADI_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('admin_init', [__CLASS__, 'redirect_old_intake_slug']);
        // Hide the "Device Detail" sidebar entry (it needs an ?id= to mean anything).
        // Done in admin_head, after WordPress's page-access check, so the page
        // itself stays reachable via links from the device list.
        add_action('admin_head', [__CLASS__, 'hide_detail_menu_item']);
        add_action('admin_head', [__CLASS__, 'early_theme_script']);
        add_filter('submenu_file', [__CLASS__, 'highlight_all_devices_on_detail']);
        add_filter('admin_body_class', [__CLASS__, 'body_class']);
    }

    public static function is_plugin_screen() {
        return isset($_GET['page']) && strpos(sanitize_key($_GET['page']), 'adi-') === 0;
    }

    public static function body_class($classes) {
        if (self::is_plugin_screen()) $classes .= ' adi-page';
        return $classes;
    }

    // Applies the saved light/dark choice before the page paints, so there is no
    // flash of the wrong theme. Light is the default.
    public static function early_theme_script() {
        if (!self::is_plugin_screen()) return;
        echo "<script>(function(){var t='light';try{var s=localStorage.getItem('adi_theme');if(s==='light'||s==='dark')t=s;if(localStorage.getItem('adi_fullscreen')==='1')document.documentElement.setAttribute('data-adi-fs','1');}catch(e){}document.documentElement.setAttribute('data-adi-theme',t);})();</script>\n";
    }

    public static function hide_detail_menu_item() {
        remove_submenu_page('adi-devices', 'adi-device-detail');
    }

    public static function highlight_all_devices_on_detail($submenu_file) {
        if (isset($_GET['page']) && $_GET['page'] === 'adi-device-detail') return 'adi-devices';
        return $submenu_file;
    }

    // The plugin's logo as the icon in WordPress's left menu. It is the white SVG in assets/img, given to
    // WordPress as a base64 SVG so it can recolour it to the admin colour scheme (light grey, white when
    // active, dark on light schemes). Falls back to the old laptop icon if the file is missing.
    public static function menu_icon() {
        $file = ADI_PATH . 'assets/img/authlab-logo-white.svg';
        $svg = is_readable($file) ? (string) file_get_contents($file) : '';
        return $svg !== '' ? 'data:image/svg+xml;base64,' . base64_encode($svg) : 'dashicons-laptop';
    }

    public static function register_menu() {
        add_menu_page(
            'AuthLab Devices', 'AuthLab Devices', 'read', 'adi-devices',
            [__CLASS__, 'render_list_page'], self::menu_icon(), 26
        );
        add_submenu_page('adi-devices', 'All Devices', 'All Devices', 'read', 'adi-devices', [__CLASS__, 'render_list_page']);
        // One "Add Device" page: the form, plus the direct link people can share.
        add_submenu_page('adi-devices', 'Add Device', 'Add Device', 'read', 'adi-add-device', [__CLASS__, 'render_add_page']);
        add_submenu_page('adi-devices', 'Device Detail', 'Device Detail', 'read', 'adi-device-detail', [__CLASS__, 'render_detail_page']);
    }

    public static function enqueue($hook) {
        if (strpos($hook, 'adi-') === false) return;

        wp_enqueue_style('adi-admin-style', ADI_URL . 'assets/css/style.css', [], ADI_VERSION);
        wp_enqueue_script('adi-theme-js', ADI_URL . 'assets/js/theme.js', [], ADI_VERSION, true);
        wp_enqueue_script('adi-qrcode', ADI_URL . 'assets/js/qrcode.min.js', [], ADI_VERSION, true);
        wp_enqueue_script('adi-qr', ADI_URL . 'assets/js/qr.js', ['adi-qrcode'], ADI_VERSION, true);
        wp_enqueue_script('adi-history', ADI_URL . 'assets/js/history.js', [], ADI_VERSION, true);
        wp_enqueue_script('adi-admin-js', ADI_URL . 'assets/js/admin.js', ['adi-qr', 'adi-history'], ADI_VERSION, true);
        if (strpos($hook, 'adi-add-device') !== false) {
            // the intake form is shown right on this page too
            wp_enqueue_script('adi-intake-js', ADI_URL . 'assets/js/intake.js', ['adi-admin-js'], ADI_VERSION, true);
        }

        wp_localize_script('adi-admin-js', 'ADI', [
            'root' => esc_url_raw(rest_url('authlab/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'isManager' => adi_current_user_is_manager(),
            'userId' => get_current_user_id(),
            'siteUrl' => site_url(),
            'viewUrl' => ADI_Settings::get_view_url(),
            'detailUrl' => admin_url('admin.php?page=adi-device-detail'),
            'paginate' => ADI_Settings::pagination_enabled(),
        ]);
    }

    /**
     * The shared header for every plugin admin page: title on the left,
     * optional action buttons + the light/dark toggle on the right.
     * $actions_html must already be escaped by the caller.
     */
    public static function topbar($title, $actions_html = '', $is_home = false) {
        $sun = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10" cy="10" r="3.5"/><path d="M10 2v2M10 16v2M2 10h2M16 10h2M4.3 4.3l1.4 1.4M14.3 14.3l1.4 1.4M4.3 15.7l1.4-1.4M14.3 5.7l1.4-1.4"/></svg>';
        $moon = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.5 11.5A6.5 6.5 0 0 1 8.5 3.5a6.5 6.5 0 1 0 8 8z"/></svg>';

        $expand = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8V3h5M17 8V3h-5M3 12v5h5M17 12v5h-5"/></svg>';
        $shrink = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3v5H3M12 3v5h5M8 17v-5H3M12 17v-5h5"/></svg>';
        $fs_button = '<button type="button" class="adi-btn secondary adi-fs-toggle" aria-label="Full screen" title="Full screen">'
            . '<span class="adi-fs-in">' . $expand . '</span>'
            . '<span class="adi-fs-out">' . $shrink . '</span></button>';

        $html  = '<div class="adi-topbar"><div class="adi-topbar-title">';
        if (!$is_home) $html .= '<div class="adi-eyebrow">AuthLab Device Inventory</div>';
        $html .= '<h1 class="adi-title">' . esc_html($title) . '</h1></div>';
        $html .= '<div class="adi-topbar-right">' . $actions_html;
        $html .= '<div class="adi-theme-toggle" role="group" aria-label="Colour theme">';
        $html .= '<button type="button" data-theme="light" aria-label="Light mode">' . $sun . '<span>Light</span></button>';
        $html .= '<button type="button" data-theme="dark" aria-label="Dark mode">' . $moon . '<span>Dark</span></button>';
        $html .= '</div>' . $fs_button . '</div></div>';
        // WordPress moves admin notices to just after this marker.
        $html .= '<hr class="wp-header-end" style="display:none;">';
        return $html;
    }

    public static function render_list_page() {
        $actions = '';
        if (adi_current_user_is_manager()) {
            $actions .= '<a href="' . esc_url(admin_url('admin.php?page=adi-add-device')) . '" class="adi-btn">+ Add device</a>';
        }
        if (current_user_can('manage_options')) {
            $actions .= '<a href="' . esc_url(admin_url('admin.php?page=adi-stickers')) . '" class="adi-btn secondary">Print stickers</a>';
        }
        echo '<div class="wrap adi-wrap adi-wrap-wide"><div class="adi-app">';
        echo self::topbar('AuthLab Device Inventory', $actions, true);
        echo '<div id="adi-device-list">Loading...</div></div></div>';
    }

    public static function render_detail_page() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $back = '<a href="' . esc_url(admin_url('admin.php?page=adi-devices')) . '" class="adi-btn secondary">&larr; All devices</a>';
        echo '<div class="wrap adi-wrap adi-wrap-wide"><div class="adi-app">';
        echo self::topbar('Device detail', $back);
        echo '<div id="adi-device-detail" data-id="' . esc_attr($id) . '"><p class="adi-muted">Loading...</p></div></div></div>';
    }

    // Add Device: the form (left) and the direct link to share + a small preview of the example picture (right).
    public static function render_add_page() {
        $url = ADI_Public::intake_url();
        echo '<div class="wrap adi-wrap adi-wrap-wide"><div class="adi-app">';
        echo self::topbar('Add Device');
        echo '<div class="adi-intake-layout">';
        // left: the live form
        echo '<div class="adi-intake-stage"><div id="adi-intake-root">Loading form&hellip;</div></div>';
        // right: the link to share
        echo '<div class="adi-intake-side"><aside class="adi-card adi-linkcard">'
            . '<h3>Direct link</h3>'
            . '<p class="adi-muted">Anyone with this link can fill the form &mdash; no login needed. Submissions appear in All Devices as <strong>Pending Review</strong>.</p>'
            . '<input type="text" id="adi-link-url" class="adi-ie" readonly value="' . esc_attr($url) . '" aria-label="Direct link to the intake form">'
            . '<button type="button" class="adi-btn" id="adi-link-copy">Copy link</button>'
            . '<a class="adi-btn secondary" href="' . esc_url($url) . '" target="_blank" rel="noopener">Open &#8599;</a>'
            . '<details class="adi-more"><summary>Other ways to use it</summary>'
            . '<p>Prefer your own page? Paste <code>[device_intake_form]</code> into any page or post.</p>'
            . '<p>To let people scan a QR code and view a device (they must be logged in to WordPress), put <code>[device_view]</code> on a page whose slug matches your <a href="' . esc_url(admin_url('admin.php?page=adi-settings')) . '">Settings</a> value &mdash; the QR codes link straight to that page.</p>'
            . '<p>Want your own screenshot beside the public form? Save it as <code>about-this-mac-example.png</code> in the plugin\'s <code>assets/img</code> folder (blur the serial number first).</p>'
            . '</details>'
            . '</aside>'
            . '<aside class="adi-card adi-refcard"><p class="adi-ref-cap">This is the window we mean:<br><strong>Apple menu → About This Mac</strong></p>'
            . '<img src="' . esc_url(ADI_Public::reference_image_url()) . '" width="558" height="1052" alt="Example of the About This Mac window">'
            . '</aside></div>';
        echo '</div></div></div>';
    }

    // Bookmarks to the old "Intake Form Link" page land on Add Device.
    public static function redirect_old_intake_slug() {
        if (isset($_GET['page']) && $_GET['page'] === 'adi-intake-info') {
            wp_safe_redirect(admin_url('admin.php?page=adi-add-device'));
            exit;
        }
    }
}
