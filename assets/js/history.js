// The History panel for one device (used on the wp-admin device page and the public QR page).
//
//  - One box to write in. The paperclip icon inside it attaches a photo (you can also
//    paste a screenshot straight into the box). A note can be text, a photo, or both.
//  - Each entry shows its text with a small thumbnail; click the thumbnail for a lightbox.
//  - Edits made to the device's details show up here automatically ("Status: In Use →
//    Need repair") together with who did it and when.
//  - Notes written by a person have Edit and Delete (the author, or an admin). Automatic
//    entries (changes, system messages) are the audit trail and can't be changed.
//
// Usage: ADIHistory.mount(containerElement, deviceId)   (needs the global ADI = { root, nonce })
(function () {
  var CLIP = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.5 9.5l-6.4 6.4a4 4 0 0 1-5.7-5.7l7-7a2.7 2.7 0 0 1 3.8 3.8l-7 7a1.3 1.3 0 0 1-1.9-1.9l6.3-6.3"/></svg>';

  var PENCIL = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 3.5l3 3L7 16l-4 1 1-4 9.5-9.5z"/></svg>';
  var TRASH = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h12M8 6V4h4v2M6 6l1 10h6l1-10M9 9v4M11 9v4"/></svg>';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // "2026-10-06 14:32:10" (site time, as stored) -> "6 Oct 2026, 14:32". No timezone shifting.
  function when(dt) {
    var m = /^(\d{4})-(\d\d)-(\d\d)[ T](\d\d):(\d\d)/.exec(String(dt || ''));
    if (!m) return esc(dt);
    var mon = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][parseInt(m[2], 10) - 1];
    return parseInt(m[3], 10) + ' ' + mon + ' ' + m[1] + ', ' + m[4] + ':' + m[5];
  }

  function api(path) {
    return fetch(ADI.root + path, { headers: { 'X-WP-Nonce': ADI.nonce } }).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) throw new Error(data.message || data.error || 'Request failed');
        return data;
      });
    });
  }

  function request(method, path, body) {
    var opts = { method: method, headers: { 'X-WP-Nonce': ADI.nonce } };
    if (body !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    return fetch(ADI.root + path, opts).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) throw new Error(data.message || data.error || 'Request failed');
        return data;
      });
    });
  }

  // An admin can change any note; anyone else only their own (the server checks this too).
  function canChange(l) {
    if ((l.kind || 'note') !== 'note') return false;
    if (ADI.isManager) return true;
    return Number(l.user_id) > 0 && Number(l.user_id) === Number(ADI.userId);
  }

  // ---------- lightbox (one shared instance) ----------
  var lb = null;
  function lightbox() {
    if (lb) return lb;
    var el = document.createElement('div');
    el.className = 'adi-lightbox';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-label', 'Photo');
    el.hidden = true;
    el.innerHTML =
      '<button type="button" class="adi-lb-close" aria-label="Close">&times;</button>' +
      '<button type="button" class="adi-lb-nav adi-lb-prev" aria-label="Previous photo">&#8249;</button>' +
      '<figure class="adi-lb-fig"><img alt=""><figcaption></figcaption></figure>' +
      '<button type="button" class="adi-lb-nav adi-lb-next" aria-label="Next photo">&#8250;</button>';
    document.body.appendChild(el);

    var items = [], idx = 0, opener = null;
    var img = el.querySelector('img'), cap = el.querySelector('figcaption');
    var prev = el.querySelector('.adi-lb-prev'), next = el.querySelector('.adi-lb-next');

    function show() {
      var it = items[idx];
      img.src = it.url;
      cap.innerHTML = (it.text ? '<span class="adi-lb-text">' + esc(it.text) + '</span>' : '') + '<span>' + esc(it.meta) + '</span>';
      var many = items.length > 1;
      prev.hidden = next.hidden = !many;
    }
    function open(list, i, trigger) {
      items = list; idx = i; opener = trigger || null;
      show();
      el.hidden = false;
      document.documentElement.classList.add('adi-lb-open');
      el.querySelector('.adi-lb-close').focus();
    }
    function close() {
      el.hidden = true;
      img.removeAttribute('src');
      document.documentElement.classList.remove('adi-lb-open');
      if (opener && opener.focus) opener.focus();
    }
    function step(d) { if (items.length > 1) { idx = (idx + d + items.length) % items.length; show(); } }

    el.addEventListener('click', function (e) {
      if (e.target === el || e.target.classList.contains('adi-lb-fig')) close();
    });
    el.querySelector('.adi-lb-close').addEventListener('click', close);
    prev.addEventListener('click', function () { step(-1); });
    next.addEventListener('click', function () { step(1); });
    document.addEventListener('keydown', function (e) {
      if (el.hidden) return;
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
      else if (e.key === 'ArrowLeft') step(-1);
      else if (e.key === 'ArrowRight') step(1);
    }, true);

    lb = { open: open, close: close, el: el };
    return lb;
  }

  // ---------- one log entry ----------
  function entryHtml(l, photoIndex) {
    var kind = l.kind || 'note';
    var body = '';
    if (kind === 'change') {
      var lines = String(l.note || '').split('\n').filter(Boolean).map(function (line) {
        var m = /^([^:]+): (.*) → (.*)$/.exec(line);
        if (!m) return '<li>' + esc(line) + '</li>';
        return '<li><strong>' + esc(m[1]) + '</strong> <span class="adi-from">' + esc(m[2]) + '</span> <span class="adi-arrow">→</span> <span class="adi-to">' + esc(m[3]) + '</span></li>';
      }).join('');
      body = '<span class="adi-log-tag change">Change</span><ul class="adi-changes">' + lines + '</ul>';
    } else if (kind === 'system') {
      body = '<span class="adi-log-tag">System</span>' + (l.note ? '<p class="adi-log-text">' + esc(l.note) + '</p>' : '');
    } else {
      body = l.note ? '<p class="adi-log-text">' + esc(l.note) + '</p>' : '';
    }
    var thumb = l.photo_url
      ? '<button type="button" class="adi-thumb-btn" data-photo="' + photoIndex + '" aria-label="View photo"><img src="' + esc(l.photo_url) + '" alt=""></button>'
      : '';
    var edited = l.edited_at ? ' · <span class="adi-edited" title="Edited ' + esc(when(l.edited_at)) + '">edited</span>' : '';
    var actions = canChange(l)
      ? '<span class="adi-log-actions">' +
          '<button type="button" class="adi-icon-btn adi-log-edit" aria-label="Edit note" title="Edit">' + PENCIL + '</button>' +
          '<button type="button" class="adi-icon-btn adi-log-del" aria-label="Delete note" title="Delete">' + TRASH + '</button>' +
        '</span>'
      : '';
    return '<div class="adi-log-entry adi-log-' + esc(kind) + '" data-log="' + esc(l.id) + '">' +
      '<div class="adi-log-main">' + body +
        '<div class="adi-log-foot"><p class="adi-log-meta">' + esc(l.created_by || '—') + ' · ' + when(l.created_at) + edited + '</p>' + actions + '</div>' +
      '</div>' + thumb + '</div>';
  }

  // ---------- the panel ----------
  function mount(el, deviceId) {
    el.innerHTML =
      '<div class="adi-composer">' +
        '<textarea id="adi-note" rows="3" placeholder="Write a note…  (attach or paste a photo with the paperclip)" aria-label="Note"></textarea>' +
        '<div class="adi-composer-bar">' +
          '<label class="adi-attach" for="adi-note-photo" title="Attach a photo">' + CLIP + '</label>' +
          '<input type="file" id="adi-note-photo" accept="image/*" hidden>' +
          '<div class="adi-attach-chip" hidden><img alt=""><span></span><button type="button" aria-label="Remove photo">&times;</button></div>' +
          '<span class="adi-composer-spacer"></span>' +
          '<button type="button" class="adi-btn adi-sm" id="adi-add-note-btn">Add note</button>' +
        '</div>' +
      '</div>' +
      '<div class="adi-error" id="adi-note-error" hidden></div>' +
      '<div id="adi-logs"></div>';

    var ta = el.querySelector('#adi-note');
    var fileInput = el.querySelector('#adi-note-photo');
    var chip = el.querySelector('.adi-attach-chip');
    var chipImg = chip.querySelector('img'), chipName = chip.querySelector('span');
    var addBtn = el.querySelector('#adi-add-note-btn');
    var errEl = el.querySelector('#adi-note-error');
    var logsEl = el.querySelector('#adi-logs');
    var previewUrl = null, photoItems = [], logsById = {};

    function clearPreview() {
      if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
      chip.hidden = true;
      chipImg.removeAttribute('src');
    }
    function showPreview() {
      var f = fileInput.files[0];
      clearPreview();
      if (!f) return;
      previewUrl = URL.createObjectURL(f);
      chipImg.src = previewUrl;
      chipName.textContent = f.name || 'Pasted image';
      chip.hidden = false;
    }
    function setError(msg) { errEl.textContent = msg || ''; errEl.hidden = !msg; }

    fileInput.addEventListener('change', showPreview);
    chip.querySelector('button').addEventListener('click', function () { fileInput.value = ''; clearPreview(); });

    // paste a screenshot straight into the box
    ta.addEventListener('paste', function (e) {
      var files = e.clipboardData && e.clipboardData.files;
      if (files && files.length && /^image\//.test(files[0].type)) {
        try {
          var dt = new DataTransfer();
          dt.items.add(files[0]);
          fileInput.files = dt.files;
          showPreview();
          e.preventDefault();
        } catch (err) { /* older browser: fall back to the paperclip */ }
      }
    });

    ta.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); send(); }
    });
    addBtn.addEventListener('click', send);

    function send() {
      var text = ta.value.trim();
      var file = fileInput.files[0];
      if (!text && !file) { setError('Write a note or attach a photo first.'); return; }
      setError('');
      var fd = new FormData();
      fd.append('note', text);
      if (file) fd.append('photo', file);
      addBtn.disabled = true;
      fetch(ADI.root + 'devices/' + deviceId + '/logs', { method: 'POST', headers: { 'X-WP-Nonce': ADI.nonce }, body: fd })
        .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.message || 'Could not save the note'); return d; }); })
        .then(function () { ta.value = ''; fileInput.value = ''; clearPreview(); loadLogs(); })
        .catch(function (e) { setError(e.message); })
        .then(function () { addBtn.disabled = false; });
    }

    logsEl.addEventListener('click', function (e) {
      var b = e.target.closest('.adi-thumb-btn');
      if (b) { lightbox().open(photoItems, parseInt(b.getAttribute('data-photo'), 10), b); return; }

      var entry = e.target.closest('.adi-log-entry');
      if (!entry) return;
      var log = logsById[entry.getAttribute('data-log')];
      if (!log) return;

      if (e.target.closest('.adi-log-edit')) startEdit(entry, log);
      else if (e.target.closest('.adi-log-del')) askDelete(entry, log);
      else if (e.target.closest('.adi-edit-cancel') || e.target.closest('.adi-del-cancel')) loadLogs();
      else if (e.target.closest('.adi-edit-save')) saveEdit(entry, log);
      else if (e.target.closest('.adi-del-confirm')) doDelete(entry, log);
    });

    // ----- edit a note in place -----
    function startEdit(entry, log) {
      var main = entry.querySelector('.adi-log-main');
      main.innerHTML =
        '<div class="adi-log-editor">' +
          '<textarea rows="3" aria-label="Edit note">' + esc(log.note) + '</textarea>' +
          (log.photo_url ? '<label class="adi-check"><input type="checkbox" class="adi-rm-photo"> Remove the photo</label>' : '') +
          '<div class="adi-log-editor-actions">' +
            '<button type="button" class="adi-btn adi-sm adi-edit-save">Save</button>' +
            '<button type="button" class="adi-btn secondary adi-sm adi-edit-cancel">Cancel</button>' +
          '</div>' +
          '<div class="adi-error" hidden></div>' +
        '</div>';
      var ta = main.querySelector('textarea');
      ta.focus();
      ta.setSelectionRange(ta.value.length, ta.value.length);
      ta.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); loadLogs(); }
        else if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); saveEdit(entry, log); }
      });
    }

    function saveEdit(entry, log) {
      var ta = entry.querySelector('.adi-log-editor textarea');
      var rm = entry.querySelector('.adi-rm-photo');
      var err = entry.querySelector('.adi-log-editor .adi-error');
      var save = entry.querySelector('.adi-edit-save');
      var removePhoto = !!(rm && rm.checked);
      var text = ta.value.trim();
      if (!text && (!log.photo_url || removePhoto)) { err.textContent = 'A note needs some text or a photo.'; err.hidden = false; return; }
      save.disabled = true;
      request('PUT', 'devices/' + deviceId + '/logs/' + log.id, { note: text, remove_photo: removePhoto })
        .then(function () { return loadLogs(); })
        .catch(function (e) { save.disabled = false; err.textContent = e.message; err.hidden = false; });
    }

    // ----- delete (asks first, right where the note is) -----
    function askDelete(entry, log) {
      var foot = entry.querySelector('.adi-log-foot');
      foot.innerHTML =
        '<span class="adi-del-ask">Delete this note' + (log.photo_url ? ' and its photo' : '') + '?</span>' +
        '<span class="adi-log-actions adi-log-actions-on">' +
          '<button type="button" class="adi-btn adi-sm adi-danger adi-del-confirm">Delete</button>' +
          '<button type="button" class="adi-btn secondary adi-sm adi-del-cancel">Cancel</button>' +
        '</span>';
      foot.querySelector('.adi-del-cancel').focus();
    }

    function doDelete(entry, log) {
      var btn = entry.querySelector('.adi-del-confirm');
      btn.disabled = true;
      request('DELETE', 'devices/' + deviceId + '/logs/' + log.id)
        .then(function () { return loadLogs(); })
        .catch(function (e) { btn.disabled = false; entry.querySelector('.adi-del-ask').textContent = e.message; });
    }

    function loadLogs() {
      return api('devices/' + deviceId + '/logs').then(function (data) {
        var logs = data.logs || [];
        photoItems = [];
        logsById = {};
        logs.forEach(function (l) { logsById[String(l.id)] = l; });
        if (!logs.length) { logsEl.innerHTML = '<p class="adi-muted">No history yet.</p>'; return; }
        logsEl.innerHTML = logs.map(function (l) {
          var pi = -1;
          if (l.photo_url) {
            pi = photoItems.length;
            photoItems.push({ url: l.photo_url, text: l.note && (l.kind || 'note') === 'note' ? l.note : '', meta: (l.created_by || '—') + ' · ' + when(l.created_at) });
          }
          return entryHtml(l, pi);
        }).join('');
      }).catch(function (e) { logsEl.innerHTML = '<p class="adi-error">' + esc(e.message) + '</p>'; });
    }

    return loadLogs();
  }

  window.ADIHistory = { mount: mount };
})();
