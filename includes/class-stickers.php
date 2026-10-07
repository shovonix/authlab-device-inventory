<?php
if (!defined('ABSPATH')) exit;

class ADI_Stickers {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    public static function register_menu() {
        add_submenu_page('adi-devices', 'Print Stickers', 'Print Stickers', 'manage_options', 'adi-stickers', [__CLASS__, 'render']);
    }

    public static function enqueue($hook) {
        if (strpos($hook, 'adi-stickers') === false) return;
        wp_enqueue_style('adi-admin-style', ADI_URL . 'assets/css/style.css', [], ADI_VERSION);
        wp_enqueue_script('adi-qrcode', ADI_URL . 'assets/js/qrcode.min.js', [], ADI_VERSION, true);
        wp_enqueue_script('adi-qr', ADI_URL . 'assets/js/qr.js', ['adi-qrcode'], ADI_VERSION, true);
        wp_enqueue_script('adi-stickers-js', ADI_URL . 'assets/js/stickers.js', ['adi-qr'], ADI_VERSION, true);
        wp_localize_script('adi-stickers-js', 'ADI', [
            'root' => esc_url_raw(rest_url('authlab/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'viewUrl' => ADI_Settings::get_view_url(),
        ]);
    }

    public static function render() {
        if (!current_user_can('manage_options')) { echo '<div class="wrap"><p>Admins only.</p></div>'; return; }
        ?>
        <div class="wrap adi-wrap adi-wrap-wide">
            <div class="adi-app">
                <div class="adi-no-print"><?php echo ADI_Admin::topbar('Print QR stickers'); ?></div>
                <div class="adi-card adi-no-print" id="adi-sticker-controls">
                    <p class="adi-muted">Select the devices you want stickers for, set the sticker size, then print. Stickers are square QR codes with the device ID in the middle.</p>
                    <div id="adi-sticker-device-list">Loading devices...</div>
                    <div class="adi-qr-print adi-sticker-opts">
                        <div class="adi-qr-dim"><label for="adi-sticker-size">Size</label><div class="adi-qr-box"><input class="adi-ie" type="number" id="adi-sticker-size" min="15" max="200" step="1" value="40"><em>mm</em></div></div>
                        <div class="adi-qr-dim"><label for="adi-sticker-cols">Per row</label><div class="adi-qr-box"><input class="adi-ie adi-no-unit" type="number" id="adi-sticker-cols" min="1" max="12" step="1" value="4"></div></div>
                    </div>
                    <p class="adi-muted adi-qr-hint" id="adi-sticker-hint"></p>
                    <button class="adi-btn" id="adi-sticker-generate" style="margin-top:12px;">Generate sheet</button>
                    <button class="adi-btn secondary" id="adi-sticker-print" style="margin-top:12px;" disabled>Print sheet</button>
                </div>
                <div id="adi-sticker-sheet" class="adi-card" style="display:none;background:#fff;color:#1c1c1a;">
                    <div id="adi-sticker-grid" style="display:grid;gap:6mm;"></div>
                </div>
            </div>
        </div>
        <style>
          @media print {
            .adi-no-print, #adminmenumain, #adminmenuback, #adminmenuwrap, #wpadminbar, #wpfooter, .notice { display: none !important; }
            #wpcontent, #wpbody-content { margin: 0 !important; padding: 0 !important; }
            #adi-sticker-sheet { border: 0 !important; padding: 0 !important; margin: 0 !important; }
            .sticker { outline: 1px dashed #999 !important; }
            @page { size: A4; margin: 10mm; }
          }
        </style>
        <?php
    }
}
