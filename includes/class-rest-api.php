<?php
if (!defined('ABSPATH')) exit;

class ADI_REST_API {

    // The details that come from the "About This Mac" style screenshot. They can be corrected
    // while a submission is still waiting for review, and become permanent once it is approved
    // (just like the device ID). Devices added by an admin are permanent from the start.
    const SPEC_FIELDS = ['brand', 'device_name', 'screen_size', 'model_year', 'chip', 'memory', 'serial_number'];

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        $ns = 'authlab/v1';

        register_rest_route($ns, '/devices', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'list_devices'], 'permission_callback' => [__CLASS__, 'require_login']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_device'], 'permission_callback' => [__CLASS__, 'require_manager']],
        ]);

        register_rest_route($ns, '/devices/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'get_device'], 'permission_callback' => [__CLASS__, 'require_login']],
            ['methods' => 'PUT', 'callback' => [__CLASS__, 'update_device'], 'permission_callback' => [__CLASS__, 'require_manager']],
        ]);

        register_rest_route($ns, '/devices/(?P<id>\d+)/logs', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'get_logs'], 'permission_callback' => [__CLASS__, 'require_login']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'add_log'], 'permission_callback' => [__CLASS__, 'require_login']],
        ]);

        // Edit / delete one history note (only notes written by a person; checked in the callbacks).
        register_rest_route($ns, '/devices/(?P<id>\d+)/logs/(?P<log_id>\d+)', [
            ['methods' => 'PUT', 'callback' => [__CLASS__, 'update_log'], 'permission_callback' => [__CLASS__, 'require_login']],
            ['methods' => 'DELETE', 'callback' => [__CLASS__, 'delete_log'], 'permission_callback' => [__CLASS__, 'require_login']],
        ]);

        register_rest_route($ns, '/submit', [
            ['methods' => 'POST', 'callback' => [__CLASS__, 'public_submit'], 'permission_callback' => '__return_true'],
        ]);
    }

    public static function require_login() {
        return is_user_logged_in();
    }

    public static function require_manager() {
        return is_user_logged_in() && adi_current_user_is_manager();
    }

    // Handles an uploaded file (from a multipart request) under $key, using
    // WordPress's own upload machinery. Returns the file URL, or '' if no
    // file was sent, or a WP_Error if the upload failed.
    private static function handle_upload($request, $key) {
        $files = $request->get_file_params();
        if (empty($files[$key]) || empty($files[$key]['tmp_name'])) return '';

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filetype = wp_check_filetype($files[$key]['name']);
        if (empty($filetype['ext']) || !in_array(strtolower($filetype['ext']), $allowed, true)) {
            return new WP_Error('bad_file_type', 'Only image files (jpg, png, gif, webp) are allowed', ['status' => 400]);
        }

        $overrides = ['test_form' => false];
        $result = wp_handle_upload($files[$key], $overrides);

        if (isset($result['error'])) {
            return new WP_Error('upload_failed', $result['error'], ['status' => 500]);
        }
        return $result['url'];
    }

    // ---- The intake screenshot: REQUIRED, PNG / JPG / SVG only, at most 2 MB ----
    // (Notes and device updates keep using handle_upload() above, unchanged.)
    const SHOT_MAX_BYTES = 2097152; // 2 MB

    private static function handle_screenshot($request) {
        $files = $request->get_file_params();
        $f = (isset($files['photo']) && is_array($files['photo'])) ? $files['photo'] : null;
        $err = $f && isset($f['error']) ? (int) $f['error'] : 0;
        if (!$f || $err === UPLOAD_ERR_NO_FILE || ($err === 0 && empty($f['tmp_name']))) {
            return new WP_Error('screenshot_required', 'Please attach a screenshot of your About This Mac window.', ['status' => 400]);
        }
        $too_big = new WP_Error('file_too_large', 'That file is too large. The screenshot must be 2 MB or smaller.', ['status' => 400]);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return $too_big;
        if ($err !== 0 || !is_file($f['tmp_name'])) {
            return new WP_Error('upload_failed', 'The upload did not finish. Please try again.', ['status' => 400]);
        }
        $tmp = $f['tmp_name'];
        $size = (int) filesize($tmp);
        if ($size === 0) return new WP_Error('empty_file', 'That file is empty.', ['status' => 400]);
        if ($size > self::SHOT_MAX_BYTES) return $too_big;

        // The name's extension AND the real content must agree on one of the three allowed types.
        $bad_type = new WP_Error('bad_file_type', 'Only PNG, JPG or SVG files are allowed.', ['status' => 400]);
        $ext = strtolower(pathinfo(isset($f['name']) ? (string) $f['name'] : '', PATHINFO_EXTENSION));
        $by_name = ['png' => 'png', 'jpg' => 'jpg', 'jpeg' => 'jpg', 'svg' => 'svg'];
        if (!isset($by_name[$ext])) return $bad_type;
        $type = $by_name[$ext];

        $head = (string) file_get_contents($tmp, false, null, 0, 16);
        $is_png = strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0;
        $is_jpg = strncmp($head, "\xFF\xD8\xFF", 3) === 0;
        if ($type === 'png' || $type === 'jpg') {
            if (($type === 'png' && !$is_png) || ($type === 'jpg' && !$is_jpg)) return $bad_type;
            $info = @getimagesize($tmp);
            $want = $type === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
            $bad_image = new WP_Error('bad_image', 'That file is not a valid image.', ['status' => 400]);
            if (!$info || (int) $info[2] !== $want || (int) $info[0] < 1 || (int) $info[1] < 1) return $bad_image;
            // getimagesize() is lenient, so check the structure too
            $all = (string) file_get_contents($tmp);
            if ($type === 'png') {
                $dim = unpack('Nw/Nh', substr($all, 16, 8));
                if (substr($all, 12, 4) !== 'IHDR' || !$dim || $dim['w'] < 1 || $dim['h'] < 1 || $dim['w'] > 30000 || $dim['h'] > 30000 || strpos($all, 'IEND') === false) return $bad_image;
            } elseif (strrpos($all, "\xFF\xD9") === false) { // a JPEG ends with the EOI marker
                return $bad_image;
            }
        } else { // svg
            if ($is_png || $is_jpg) return $bad_type;
            $clean = self::sanitize_svg((string) file_get_contents($tmp));
            if ($clean === false) {
                return new WP_Error('bad_svg', 'That SVG cannot be used. Please attach a PNG or JPG screenshot instead.', ['status' => 400]);
            }
            file_put_contents($tmp, $clean); // keep only the cleaned copy
        }

        // Store it under a name we choose (never the visitor's file name).
        $f['name'] = 'screenshot-' . bin2hex(random_bytes(6)) . '.' . $type;
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        // test_type is off because the checks above are stricter than WordPress's (and SVG is not on its list).
        $result = wp_handle_upload($f, ['test_form' => false, 'test_type' => false]);
        if (isset($result['error'])) return new WP_Error('upload_failed', $result['error'], ['status' => 500]);
        return $result['url'];
    }

    // Cleans an uploaded SVG with a strict allow-list (anything not listed is dropped): no scripts, no
    // event handlers, no links, no animation, no <style>, no foreign content, nothing that loads from
    // outside the file. Returns the cleaned SVG text, or false if it cannot be made safe.
    private static function sanitize_svg($raw) {
        if (!class_exists('DOMDocument') || strlen($raw) > self::SHOT_MAX_BYTES) return false; // cannot clean -> refuse
        if (preg_match('/<!ENTITY/i', $raw)) return false;                                     // blocks XXE / entity bombs
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = $dom->loadXML($raw, LIBXML_NONET | LIBXML_NOBLANKS); // no DTD loading, no entity substitution
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $ok ? $dom->documentElement : null;
        $svg_ns = 'http://www.w3.org/2000/svg';
        if (!$root || strtolower($root->localName) !== 'svg') return false;
        if ($root->namespaceURI !== $svg_ns) return false;
        self::svg_clean_children($root, $svg_ns);
        self::svg_clean_attrs($root);
        $out = $dom->saveXML($root);
        return ($out && strlen($out) <= self::SHOT_MAX_BYTES) ? $out : false;
    }

    private static function svg_clean_children($el, $svg_ns) {
        static $tags = null;
        if ($tags === null) {
            $tags = array_flip(explode(' ', 'svg g defs title desc path rect circle ellipse line polyline polygon text tspan lineargradient radialgradient stop clippath mask pattern symbol use image marker filter feblend fecolormatrix fecomponenttransfer fecomposite feconvolvematrix fediffuselighting fedisplacementmap fedistantlight fedropshadow feflood fefunca fefuncb fefuncg fefuncr fegaussianblur femerge femergenode femorphology feoffset fepointlight fespecularlighting fespotlight fetile feturbulence'));
        }
        $kids = [];
        foreach ($el->childNodes as $c) $kids[] = $c;
        foreach ($kids as $c) {
            if ($c instanceof DOMElement) {
                if ($c->namespaceURI !== $svg_ns || !isset($tags[strtolower($c->localName)])) { $el->removeChild($c); continue; }
                self::svg_clean_children($c, $svg_ns);
                self::svg_clean_attrs($c);
            } elseif ($c->nodeType !== XML_TEXT_NODE && $c->nodeType !== XML_CDATA_SECTION_NODE) {
                $el->removeChild($c); // comments, processing instructions, entity references
            }
        }
    }

    private static function svg_clean_attrs($el) {
        static $names = null;
        if ($names === null) {
            $names = array_flip(explode(' ', 'id class x y x1 x2 y1 y2 cx cy r rx ry fx fy fr width height d points transform viewbox preserveaspectratio version xmlns xmlns:xlink role aria-label aria-hidden aria-labelledby xml:space style href xlink:href fill fill-opacity fill-rule stroke stroke-width stroke-linecap stroke-linejoin stroke-miterlimit stroke-dasharray stroke-dashoffset stroke-opacity opacity color display visibility overflow clip-path clip-rule mask filter offset stop-color stop-opacity gradientunits gradienttransform spreadmethod patternunits patterncontentunits patterntransform markerwidth markerheight markerunits refx refy orient font-family font-size font-weight font-style font-variant text-anchor text-decoration dominant-baseline letter-spacing word-spacing dx dy rotate textlength lengthadjust in in2 result stddeviation mode type values flood-color flood-opacity lighting-color operator k1 k2 k3 k4 radius scale xchannelselector ychannelselector basefrequency numoctaves seed stitchtiles surfacescale diffuseconstant specularconstant specularexponent tablevalues slope intercept amplitude exponent order kernelmatrix divisor bias targetx targety edgemode preservealpha limitingconeangle azimuth elevation pointsatx pointsaty pointsatz z'));
        }
        $drop = [];
        foreach ($el->attributes as $a) {
            $n = strtolower($a->nodeName);
            $v = (string) $a->nodeValue;
            $keep = isset($names[$n]) && strncmp($n, 'on', 2) !== 0;
            if ($keep && ($n === 'xmlns' || $n === 'xmlns:xlink')) {
                $keep = $v === ($n === 'xmlns' ? 'http://www.w3.org/2000/svg' : 'http://www.w3.org/1999/xlink');
            } elseif ($keep && ($n === 'href' || $n === 'xlink:href')) {
                // only a reference to something inside this file, or a small embedded PNG/JPG/GIF/WebP picture
                $keep = (bool) preg_match('/^\s*(#[\w:.\-]+|data:image\/(png|jpe?g|gif|webp);base64,[A-Za-z0-9+\/=\s]+)\s*$/i', $v);
            } elseif ($keep && $n === 'style') {
                $keep = !preg_match('/url\s*\(|expression|javascript|@import|behavior|binding|\\\\/i', $v);
            } elseif ($keep) {
                // no value may point outside the file or carry a script
                $keep = !preg_match('/javascript:|vbscript:|data:|<|url\s*\(\s*[\'"]?\s*(?!#)/i', $v);
            }
            if (!$keep) $drop[] = $a;
        }
        foreach ($drop as $a) $el->removeAttributeNode($a);
    }

    // Removes an uploaded photo from disk, but only if nothing else still points at it.
    private static function delete_upload_if_unused($url) {
        global $wpdb;
        if (!$url) return;
        $logs = ADI_DB::logs_table();
        $devs = ADI_DB::devices_table();
        $used = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT (SELECT COUNT(*) FROM $logs WHERE photo_url = %s) + (SELECT COUNT(*) FROM $devs WHERE photo_url = %s)", $url, $url
        ));
        if ($used > 0) return;

        $up = wp_upload_dir();
        if (empty($up['baseurl']) || strpos($url, $up['baseurl']) !== 0) return;
        $path = realpath($up['basedir'] . substr($url, strlen($up['baseurl'])));
        $base = realpath($up['basedir']);
        if ($path && $base && strpos($path, $base . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
            wp_delete_file($path);
        }
    }

    public static function list_devices() {
        global $wpdb;
        $table = ADI_DB::devices_table();
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A);
        return rest_ensure_response(['devices' => $rows]);
    }

    public static function get_device($request) {
        global $wpdb;
        $table = ADI_DB::devices_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $request['id']), ARRAY_A);
        if (!$row) return new WP_Error('not_found', 'Device not found', ['status' => 404]);
        return rest_ensure_response(['device' => $row]);
    }

    // $partial = true returns only the fields that were actually sent, so a request that
    // changes just the status can never blank out the other fields.
    private static function sanitize_device_fields($request, $partial = false) {
        $fields = ['brand', 'device_name', 'screen_size', 'model_year', 'chip', 'memory', 'serial_number', 'current_owner', 'department', 'status'];
        $out = [];
        foreach ($fields as $f) {
            $val = $request->get_param($f);
            if ($val === null) {
                if (!$partial) $out[$f] = '';
                continue;
            }
            $out[$f] = sanitize_text_field($val);
        }
        return $out;
    }

    public static function create_device($request) {
        global $wpdb;
        $fields = self::sanitize_device_fields($request);

        if (empty($fields['device_name'])) {
            return new WP_Error('missing_fields', 'device_name is required', ['status' => 400]);
        }
        if (empty($fields['status'])) $fields['status'] = 'In Stock';
        if ($fields['status'] === 'Pending Review') $fields['status'] = 'In Stock';

        // The ID is always auto-assigned; any sticker_code sent by the client is ignored.
        $code = ADI_IDs::next_code();
        if (is_wp_error($code)) return $code;
        $fields['sticker_code'] = $code;

        // An admin entering a device is the review: its details are permanent from the start.
        $fields['specs_locked'] = 1;

        $photo_url = self::handle_upload($request, 'photo');
        if (is_wp_error($photo_url)) return $photo_url;
        if ($photo_url) $fields['photo_url'] = $photo_url;

        $table = ADI_DB::devices_table();
        $inserted = $wpdb->insert($table, $fields);
        if ($inserted === false) {
            return new WP_Error('db_error', 'Could not save device', ['status' => 400]);
        }
        $new_id = (int) $wpdb->insert_id;
        self::log_event($new_id, 'Device added.', 'system');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $new_id), ARRAY_A);
        return rest_ensure_response(['device' => $row]);
    }

    public static function update_device($request) {
        global $wpdb;
        $id = (int) $request['id'];
        $table = ADI_DB::devices_table();

        $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        if (!$before) {
            return new WP_Error('not_found', 'Device not found', ['status' => 404]);
        }

        $fields = self::sanitize_device_fields($request, true);
        $locked = (int) $before['specs_locked'] === 1;

        // Once reviewed, the screenshot details can never be changed.
        if ($locked) {
            foreach (self::SPEC_FIELDS as $key) {
                if (array_key_exists($key, $fields) && trim($fields[$key]) !== trim((string) $before[$key])) {
                    return new WP_Error('specs_locked', 'These details are locked after review and cannot be changed.', ['status' => 403]);
                }
            }
        }

        // Status rules: a blank status is ignored; a device can't go back to "Pending Review";
        // leaving "Pending Review" is the review itself, which locks the details for good.
        if (array_key_exists('status', $fields)) {
            if ($fields['status'] === '') {
                unset($fields['status']);
            } elseif ($fields['status'] === 'Pending Review' && $before['status'] !== 'Pending Review') {
                return new WP_Error('bad_status', 'A reviewed device cannot go back to Pending Review.', ['status' => 400]);
            } elseif ($before['status'] === 'Pending Review' && $fields['status'] !== 'Pending Review') {
                $fields['specs_locked'] = 1;
            }
        }

        $photo_url = self::handle_upload($request, 'photo');
        if (is_wp_error($photo_url)) return $photo_url;
        if ($photo_url) {
            if ($locked) {
                return new WP_Error('specs_locked', 'The verification photo is locked after review.', ['status' => 403]);
            }
            $fields['photo_url'] = $photo_url;
        }

        $fields['updated_at'] = current_time('mysql');
        $updated = $wpdb->update($table, $fields, ['id' => $id]);
        if ($updated === false) {
            return new WP_Error('db_error', 'Could not update device', ['status' => 400]);
        }

        // Every change is written to the device's history: who, what, from -> to, and when.
        $labels = [
            'brand' => 'Brand', 'device_name' => 'Device name', 'screen_size' => 'Screen size',
            'model_year' => 'Model year', 'chip' => 'Chip', 'memory' => 'Memory',
            'serial_number' => 'Serial number', 'current_owner' => 'Owner',
            'department' => 'Department', 'status' => 'Status',
        ];
        $lines = [];
        foreach ($labels as $key => $label) {
            if (!array_key_exists($key, $fields)) continue;
            $old = trim((string) $before[$key]);
            $new = trim((string) $fields[$key]);
            if ($old !== $new) {
                $lines[] = sprintf('%s: %s → %s', $label, $old === '' ? '—' : $old, $new === '' ? '—' : $new);
            }
        }
        if ($photo_url) $lines[] = 'Verification photo: replaced';
        if ($lines) self::log_event($id, implode("\n", $lines), 'change');

        // Quick actions (hand over, send for repair, ...) can carry an optional note.
        $note = sanitize_textarea_field((string) $request->get_param('note'));
        if (trim($note) !== '') self::log_event($id, $note, 'note');

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        return rest_ensure_response(['device' => $row]);
    }

    // Writes one row to a device's history. $kind is 'note' (typed by a person),
    // 'change' (a detail was edited) or 'system' (device added / submitted / note deleted).
    private static function log_event($device_id, $note, $kind = 'note', $photo_url = '', $by = '') {
        global $wpdb;
        $user = wp_get_current_user();
        $uid = ($user && $user->ID) ? (int) $user->ID : 0;
        if ($by === '') {
            $by = $uid ? ($user->display_name ?: $user->user_email) : '';
        }
        return $wpdb->insert(ADI_DB::logs_table(), [
            'device_id' => (int) $device_id,
            'note' => $note,
            'kind' => $kind,
            'photo_url' => $photo_url ?: '',
            'user_id' => $uid,
            'created_by' => $by,
            'created_at' => current_time('mysql'), // site timezone, like the rest of WordPress
        ]);
    }

    private static function logs_for($device_id) {
        global $wpdb;
        $table = ADI_DB::logs_table();
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE device_id = %d ORDER BY created_at DESC, id DESC", (int) $device_id), ARRAY_A);
    }

    public static function get_logs($request) {
        return rest_ensure_response(['logs' => self::logs_for($request['id'])]);
    }

    public static function add_log($request) {
        $id = (int) $request['id'];
        $note = sanitize_textarea_field((string) $request->get_param('note'));

        $photo_url = self::handle_upload($request, 'photo');
        if (is_wp_error($photo_url)) return $photo_url;

        // A note needs text, a photo, or both.
        if (trim($note) === '' && !$photo_url) {
            return new WP_Error('empty_note', 'Write a note or attach a photo', ['status' => 400]);
        }

        self::log_event($id, $note, 'note', $photo_url);
        return rest_ensure_response(['logs' => self::logs_for($id)]);
    }

    // Loads a note and checks the caller may change it. Returns the row, or a WP_Error.
    private static function editable_note($request) {
        global $wpdb;
        $device_id = (int) $request['id'];
        $log_id = (int) $request['log_id'];
        $table = ADI_DB::logs_table();
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND device_id = %d", $log_id, $device_id), ARRAY_A);
        if (!$log) {
            return new WP_Error('not_found', 'Note not found', ['status' => 404]);
        }
        // Automatic entries (changes, system messages) are the audit trail and can't be touched.
        if ($log['kind'] !== 'note') {
            return new WP_Error('not_a_note', 'Only notes written by a person can be changed.', ['status' => 403]);
        }
        $user = wp_get_current_user();
        $mine = $user && $user->ID && (int) $log['user_id'] === (int) $user->ID;
        if (!adi_current_user_is_manager() && !$mine) {
            return new WP_Error('forbidden', 'You can only change your own notes.', ['status' => 403]);
        }
        return $log;
    }

    public static function update_log($request) {
        global $wpdb;
        $log = self::editable_note($request);
        if (is_wp_error($log)) return $log;

        $note = sanitize_textarea_field((string) $request->get_param('note'));
        $remove_photo = filter_var($request->get_param('remove_photo'), FILTER_VALIDATE_BOOLEAN);
        $photo = $remove_photo ? '' : (string) $log['photo_url'];

        if (trim($note) === '' && $photo === '') {
            return new WP_Error('empty_note', 'A note needs some text or a photo', ['status' => 400]);
        }

        if ($note !== (string) $log['note'] || $photo !== (string) $log['photo_url']) {
            $wpdb->update(ADI_DB::logs_table(), [
                'note' => $note,
                'photo_url' => $photo,
                'edited_at' => current_time('mysql'),
            ], ['id' => (int) $log['id']]);
            if ($remove_photo && $log['photo_url']) self::delete_upload_if_unused($log['photo_url']);
        }
        return rest_ensure_response(['logs' => self::logs_for((int) $request['id'])]);
    }

    public static function delete_log($request) {
        global $wpdb;
        $log = self::editable_note($request);
        if (is_wp_error($log)) return $log;

        $wpdb->delete(ADI_DB::logs_table(), ['id' => (int) $log['id']]);
        if ($log['photo_url']) self::delete_upload_if_unused($log['photo_url']);

        // The content is gone for good, but a one-line trace stays so the history is honest.
        $when = date('j M Y, H:i', strtotime($log['created_at']));
        self::log_event((int) $request['id'], sprintf('A note by %s (%s) was deleted.', $log['created_by'] !== '' ? $log['created_by'] : 'someone', $when), 'system');
        return rest_ensure_response(['logs' => self::logs_for((int) $request['id'])]);
    }

    // Public, unauthenticated intake form submission. Multipart: the form fields plus the
    // required screenshot (PNG / JPG / SVG, max 2 MB).
    public static function public_submit($request) {
        global $wpdb;

        $get = function ($key) use ($request) {
            $v = $request->get_param($key);
            return $v === null ? '' : trim($v);
        };

        // Spam trap: the form has a hidden "website" box that people never see or fill in.
        // Bots do, so a filled one is quietly ignored (they are told it worked).
        if (trim((string) $request->get_param('website')) !== '') {
            return rest_ensure_response(['ok' => true]);
        }

        $required = ['name', 'email', 'department', 'brand', 'deviceName', 'screenSize', 'modelYear', 'chip', 'memory', 'serialNumber'];
        foreach ($required as $key) {
            if ($get($key) === '') {
                return new WP_Error('missing_field', "Missing field: $key", ['status' => 400]);
            }
        }

        // the About This Mac screenshot is required (and strictly checked)
        $photo_url = self::handle_screenshot($request);
        if (is_wp_error($photo_url)) return $photo_url;

        $code = ADI_IDs::next_code();
        if (is_wp_error($code)) return $code;
        $table = ADI_DB::devices_table();

        $inserted = $wpdb->insert($table, [
            'sticker_code' => $code,
            'brand' => sanitize_text_field($get('brand')),
            'device_name' => sanitize_text_field($get('deviceName')),
            'screen_size' => sanitize_text_field($get('screenSize')),
            'model_year' => sanitize_text_field($get('modelYear')),
            'chip' => sanitize_text_field($get('chip')),
            'memory' => sanitize_text_field($get('memory')),
            'serial_number' => sanitize_text_field($get('serialNumber')),
            'current_owner' => sanitize_text_field($get('name')),
            'owner_email' => sanitize_email($get('email')),
            'department' => sanitize_text_field($get('department')),
            'status' => 'Pending Review',
            'photo_url' => $photo_url ?: '',
            'specs_locked' => 0, // editable until an admin approves the submission
        ]);

        if ($inserted === false) {
            return new WP_Error('db_error', 'Could not save submission', ['status' => 500]);
        }

        self::log_event(
            $wpdb->insert_id,
            sprintf('Submitted via the self-service intake form by %s (%s). Assigned ID %s.', sanitize_text_field($get('name')), sanitize_email($get('email')), $code),
            'system', $photo_url ?: '', sanitize_text_field($get('name'))
        );

        return rest_ensure_response(['ok' => true]);
    }
}
