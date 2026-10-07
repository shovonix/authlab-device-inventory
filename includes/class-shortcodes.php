<?php
if (!defined('ABSPATH')) exit;

class ADI_Shortcodes {

    public static function init() {
        add_shortcode('device_intake_form', [__CLASS__, 'render_intake_form']);
        add_shortcode('device_view', [__CLASS__, 'render_device_view']);
        add_shortcode('device_scanner', [__CLASS__, 'render_scanner']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue']);
    }

    public static function maybe_enqueue() {
        global $post;
        if (!is_a($post, 'WP_Post')) return;

        wp_register_style('adi-front-style', ADI_URL . 'assets/css/style.css', [], ADI_VERSION);
        wp_register_script('adi-qrcode', ADI_URL . 'assets/js/qrcode.min.js', [], ADI_VERSION, true);
        wp_register_script('adi-html5-qrcode', ADI_URL . 'assets/js/html5-qrcode.min.js', [], ADI_VERSION, true);
        wp_register_script('adi-intake-js', ADI_URL . 'assets/js/intake.js', [], ADI_VERSION, true);
        wp_register_script('adi-qr', ADI_URL . 'assets/js/qr.js', ['adi-qrcode'], ADI_VERSION, true);
        wp_register_script('adi-history', ADI_URL . 'assets/js/history.js', [], ADI_VERSION, true);
        wp_register_script('adi-view-js', ADI_URL . 'assets/js/device-view.js', ['adi-qr', 'adi-history'], ADI_VERSION, true);
        wp_register_script('adi-scan-js', ADI_URL . 'assets/js/scan.js', ['adi-html5-qrcode'], ADI_VERSION, true);

        $localize = [
            'root' => esc_url_raw(rest_url('authlab/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'loggedIn' => is_user_logged_in(),
            'isManager' => adi_current_user_is_manager(),
            'userId' => get_current_user_id(),
            'siteUrl' => site_url(),
            'viewUrl' => ADI_Settings::get_view_url(),
            'refImage' => ADI_URL . 'assets/img/about-this-mac-reference.png',
        ];

        if (has_shortcode($post->post_content, 'device_intake_form')) {
            wp_enqueue_style('adi-front-style');
            wp_enqueue_script('adi-intake-js');
            wp_localize_script('adi-intake-js', 'ADI', $localize);
        }
        if (has_shortcode($post->post_content, 'device_view')) {
            wp_enqueue_style('adi-front-style');
            wp_enqueue_script('adi-view-js');
            wp_localize_script('adi-view-js', 'ADI', $localize);
        }
        if (has_shortcode($post->post_content, 'device_scanner')) {
            wp_enqueue_style('adi-front-style');
            wp_enqueue_script('adi-scan-js');
            wp_localize_script('adi-scan-js', 'ADI', $localize);
        }
    }

    public static function render_intake_form() {
        ob_start(); ?>
        <div class="adi-dark-shell">
            <div id="adi-intake-root">Loading form...</div>
        </div>
        <?php return ob_get_clean();
    }

    public static function render_device_view() {
        if (!is_user_logged_in()) {
            ob_start(); ?>
            <div class="adi-dark-shell"><div class="adi-card">
                <p>Please <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">log in</a> to view this device record.</p>
            </div></div>
            <?php return ob_get_clean();
        }
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        ob_start(); ?>
        <div class="adi-dark-shell">
            <div id="adi-device-view-root" data-id="<?php echo esc_attr($id); ?>">Loading...</div>
        </div>
        <?php return ob_get_clean();
    }

    public static function render_scanner() {
        if (!is_user_logged_in()) {
            ob_start(); ?>
            <div class="adi-dark-shell"><div class="adi-card">
                <p>Please <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">log in</a> to scan a device.</p>
            </div></div>
            <?php return ob_get_clean();
        }
        ob_start(); ?>
        <div class="adi-dark-shell">
            <div class="adi-card">
                <div id="adi-qr-reader" style="width:100%;"></div>
                <div id="adi-scan-error" class="adi-error" style="display:none;"></div>
            </div>
        </div>
        <?php return ob_get_clean();
    }
}
