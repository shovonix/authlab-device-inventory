<?php
if (!defined('ABSPATH')) exit;

// Serves the self-service intake form at its own address, so there is no need to create a
// WordPress page or paste a shortcode: just share the link. (The [device_intake_form]
// shortcode still works for anyone who prefers to embed it in a page.)
class ADI_Public {

    const SLUG = 'device-intake';

    public static function init() {
        add_action('init', [__CLASS__, 'register_rewrite']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'maybe_render']);
    }

    // The example picture shown beside the form. A real screenshot saved in assets/img/
    // as about-this-mac-example.png (or .webp / .jpg) is used if it exists; otherwise
    // the built-in drawing of the About This Mac window (about-this-mac-example.svg).
    public static function reference_image_url() {
        foreach (['png', 'webp', 'jpg', 'jpeg'] as $ext) {
            $file = ADI_PATH . 'assets/img/about-this-mac-example.' . $ext;
            if (file_exists($file)) return ADI_URL . 'assets/img/about-this-mac-example.' . $ext . '?ver=' . filemtime($file);
        }
        return ADI_URL . 'assets/img/about-this-mac-example.svg?ver=' . ADI_VERSION;
    }

    public static function query_vars($vars) {
        $vars[] = 'adi_intake';
        return $vars;
    }

    public static function register_rewrite() {
        add_rewrite_rule('^' . self::SLUG . '/?$', 'index.php?adi_intake=1', 'top');
        // WordPress has to be told about a new address once; after that this is skipped.
        if (get_option('adi_rewrite_version') !== '1') {
            flush_rewrite_rules(false);
            update_option('adi_rewrite_version', '1');
        }
    }

    // The link to share. With "pretty" permalinks it is /device-intake/, otherwise ?adi_intake=1.
    public static function intake_url() {
        if (get_option('permalink_structure')) {
            return home_url('/' . self::SLUG . '/');
        }
        return add_query_arg('adi_intake', '1', home_url('/'));
    }

    public static function maybe_render() {
        if (!get_query_var('adi_intake')) return;
        nocache_headers();
        status_header(200);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');
        echo self::render_page();
        exit;
    }

    // A small, self-contained page: no theme header/footer, just the form.
    public static function render_page() {
        $ver = ADI_VERSION;
        $cfg = [
            'root' => esc_url_raw(rest_url('authlab/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
        ];
        ob_start(); ?>
<!doctype html>
<html lang="en" data-adi-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>AuthLab Device Intake Form — <?php echo esc_html(get_bloginfo('name')); ?></title>
<script>(function(){var t='light';try{var s=localStorage.getItem('adi_theme');if(s==='light'||s==='dark'){t=s;}else if(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches){t='dark';}}catch(e){}document.documentElement.setAttribute('data-adi-theme',t);})();</script>
<link rel="stylesheet" href="<?php echo esc_url(ADI_URL . 'assets/css/style.css?ver=' . $ver); ?>">
</head>
<body class="adi-public-page">
<main class="adi-app adi-public"><div class="adi-public-grid">
<div id="adi-intake-root">Loading form…</div>
<div class="adi-public-tools"><div class="adi-theme-toggle" role="group" aria-label="Colour theme"><button type="button" data-theme="light" aria-label="Light mode"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10" cy="10" r="3.5"/><path d="M10 2v2M10 16v2M2 10h2M16 10h2M4.3 4.3l1.4 1.4M14.3 14.3l1.4 1.4M4.3 15.7l1.4-1.4M14.3 5.7l1.4-1.4"/></svg><span>Light</span></button><button type="button" data-theme="dark" aria-label="Dark mode"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.5 11.5A6.5 6.5 0 0 1 8.5 3.5a6.5 6.5 0 1 0 8 8z"/></svg><span>Dark</span></button></div></div>
<aside class="adi-ref" aria-label="Example of the About This Mac window">
<img src="<?php echo esc_url(self::reference_image_url()); ?>" width="558" height="1052" alt="Example of the About This Mac window on a MacBook Pro, showing the Chip, Memory and Serial number lines">
<p class="adi-ref-cap">This is the window we mean:<br><strong>Apple menu → About This Mac</strong></p>
</aside>
</div></main>
<script>window.ADI = <?php echo wp_json_encode($cfg); ?>;</script>
<script src="<?php echo esc_url(ADI_URL . 'assets/js/theme.js?ver=' . $ver); ?>"></script>
<script src="<?php echo esc_url(ADI_URL . 'assets/js/intake.js?ver=' . $ver); ?>"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
