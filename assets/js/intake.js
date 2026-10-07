// The self-service intake form. The layout copies macOS "About This Mac" so people can
// read the window and type what they see, field by field:
//
//      Your name  [..........................]
//      Your email [..........................]
//      Department [..........................]
//   ------------------------------------------------
//                 Open the Apple menu → About This Mac, then copy what you see.
//          Brand  [..........................]
//    Device name  [MacBook Pro               ]
//   Size and year [16-inch ] , [Nov 2020    ]
//           Chip  [Apple M3 Pro              ]
//         Memory  [18 GB                     ]
//   Serial number [C02XXXXXXXXX              ]
//   [Attach screenshot (required)]                [Submit]
//    Please attach a screenshot of your About This Mac window. PNG, JPG or SVG, up to 2 MB.
//   (every label sits in one column, every box in the next, so it reads like a table)
//
// Used on the public direct-link page, inside a WordPress page via the shortcode, and
// (as a live preview) on the admin "Add Device" page.
(function () {
  const root = document.getElementById('adi-intake-root');
  if (!root) return;

  const BRANDS = ['Apple', 'Dell', 'HP', 'Lenovo', 'Asus', 'Other'];
  const DEPARTMENTS = ['Dev', 'Marketing', 'Support', 'MGD', 'HR'];
  const CLIP = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.5 9.5l-6.4 6.4a4 4 0 0 1-5.7-5.7l7-7a2.7 2.7 0 0 1 3.8 3.8l-7 7a1.3 1.3 0 0 1-1.9-1.9l6.3-6.3"/></svg>';

  // what the person typed for themselves, kept so "Add another device" doesn't make them retype it
  let me = { name: '', email: '', department: '' };

  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const opts = (list, first, selected) => `<option value="">${first}</option>` + list.map((o) => `<option${o === selected ? ' selected' : ''}>${o}</option>`).join('');

  // the About This Mac screenshot is required: PNG, JPG or SVG, at most 2 MB
  const SHOT_MAX = 2 * 1024 * 1024;
  const SHOT_HELP = 'Please attach a screenshot of your About This Mac window.';
  function shotProblem(f) {
    const ext = (f.name.split('.').pop() || '').toLowerCase();
    if (['png', 'jpg', 'jpeg', 'svg'].indexOf(ext) === -1 || (f.type && ['image/png', 'image/jpeg', 'image/svg+xml'].indexOf(f.type) === -1)) return 'Only PNG, JPG or SVG files are allowed.';
    if (f.size === 0) return 'That file is empty.';
    if (f.size > SHOT_MAX) return 'That file is larger than 2 MB. Please attach a smaller screenshot.';
    return '';
  }

  // one line of the form: a label on the left, its box(es) on the right
  const row = (label, forId, body) => `<div class="adi-frow"><label for="${forId}">${label}</label>${body}</div>`;

  function renderForm() {
    root.innerHTML = `
      <div class="adi-card adi-intake-card">
        <div class="adi-intake-head">
          <h2>AuthLab Device Intake Form</h2>
          <p class="adi-muted">One entry per device &mdash; submit again for each extra one.</p>
        </div>
        <form id="adi-intake-form">
          <div class="adi-intake-who">
            ${row('Your name', 'adi-i-name', `<input class="adi-ie" id="adi-i-name" name="name" required autocomplete="name" placeholder="Full name" value="${esc(me.name)}">`)}
            ${row('Your email', 'adi-i-email', `<input class="adi-ie" id="adi-i-email" type="email" name="email" required autocomplete="email" placeholder="you@company.com" value="${esc(me.email)}">`)}
            ${row('Department', 'adi-i-dept', `<select class="adi-ie" id="adi-i-dept" name="department" required>${opts(DEPARTMENTS, 'Select…', me.department)}</select>`)}
          </div>

          <div class="adi-mac" role="group" aria-label="Your device — copy what About This Mac shows">
            <p class="adi-mac-note">Open the <strong>Apple menu</strong> (top-left corner) &rarr; <strong>About This Mac</strong>, then copy what you see.</p>
            <div class="adi-mac-rows">
              ${row('Brand', 'adi-i-brand', `<select class="adi-ie" id="adi-i-brand" name="brand" required>${opts(BRANDS, 'Select…')}</select>`)}
              ${row('Device name', 'adi-i-device', `<input class="adi-ie" id="adi-i-device" name="deviceName" required autocomplete="off" placeholder="MacBook Pro">`)}
              ${row('Size and year', 'adi-i-size', `<div class="adi-pair"><input class="adi-ie" id="adi-i-size" name="screenSize" aria-label="Screen size" required autocomplete="off" placeholder="16-inch"><span class="adi-pair-comma" aria-hidden="true">,</span><input class="adi-ie" name="modelYear" aria-label="Model year" required autocomplete="off" placeholder="Nov 2020"></div>`)}
              ${row('Chip', 'adi-i-chip', `<input class="adi-ie" id="adi-i-chip" name="chip" required autocomplete="off" placeholder="Apple M3 Pro">`)}
              ${row('Memory', 'adi-i-memory', `<input class="adi-ie" id="adi-i-memory" name="memory" required autocomplete="off" placeholder="18 GB">`)}
              ${row('Serial number', 'adi-i-serial', `<input class="adi-ie" id="adi-i-serial" name="serialNumber" required autocomplete="off" placeholder="C02XXXXXXXXX">`)}
            </div>
          </div>

          <div class="adi-hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>

          <div class="adi-intake-foot">
            <div class="adi-foot-attach">
              <label class="adi-file-btn">${CLIP}<span>Attach screenshot <em>(required)</em></span><input type="file" id="adi-i-photo" name="photo" accept=".png,.jpg,.jpeg,.svg,image/png,image/jpeg,image/svg+xml" required></label>
              <small class="adi-foot-note">Please attach a screenshot of your <strong>About This Mac</strong> window. PNG, JPG or SVG, up to 2 MB.</small>
              <small class="adi-file-error" id="adi-file-error" role="alert" hidden></small>
            </div>
            <span class="adi-attach-chip" hidden><img alt=""><span></span><button type="button" aria-label="Remove screenshot">&times;</button></span>
            <span class="adi-composer-spacer"></span>
            <button type="submit" class="adi-btn">Submit</button>
          </div>
          <div id="adi-intake-status" class="adi-error" hidden></div>
        </form>
      </div>`;

    const form = document.getElementById('adi-intake-form');
    const file = form.querySelector('[name="photo"]');
    const chip = form.querySelector('.adi-attach-chip');
    const pick = form.querySelector('.adi-file-btn');
    const errEl = document.getElementById('adi-file-error');
    let previewUrl = null;
    const clear = () => { if (previewUrl) URL.revokeObjectURL(previewUrl); previewUrl = null; chip.hidden = true; };
    const showErr = (msg) => { errEl.textContent = msg; errEl.hidden = !msg; pick.classList.toggle('is-invalid', !!msg); };
    file.setCustomValidity(SHOT_HELP); // the browser's "required" bubble says our sentence
    file.addEventListener('invalid', function () { showErr(file.files[0] ? errEl.textContent : SHOT_HELP); });
    file.addEventListener('change', function () {
      clear();
      const f = file.files[0];
      const problem = f ? shotProblem(f) : '';
      if (!f || problem) { file.value = ''; file.setCustomValidity(SHOT_HELP); showErr(problem); return; }
      showErr('');
      file.setCustomValidity('');
      previewUrl = URL.createObjectURL(f);
      chip.querySelector('img').src = previewUrl;
      chip.querySelector('span').textContent = f.name;
      chip.hidden = false;
    });
    chip.querySelector('button').addEventListener('click', function () { file.value = ''; clear(); file.setCustomValidity(SHOT_HELP); });
    form.addEventListener('submit', handleSubmit);
  }

  function renderConfirmation() {
    root.innerHTML = `
      <div class="adi-card adi-intake-card adi-intake-done">
        <h2>Submitted &mdash; thank you</h2>
        <p class="adi-muted">HR will review it shortly.</p>
        <button type="button" class="adi-btn" id="adi-add-another">Add another device</button>
      </div>`;
    document.getElementById('adi-add-another').addEventListener('click', function () {
      renderForm();
      const first = root.querySelector('[name="deviceName"]');
      if (first) first.focus();
    });
  }

  function handleSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const statusEl = document.getElementById('adi-intake-status');
    statusEl.hidden = true;

    // belt and braces: the screenshot must be there, and be an allowed type and size
    const shot = form.querySelector('[name="photo"]').files[0];
    const shotErr = shot ? shotProblem(shot) : SHOT_HELP;
    if (shotErr) { const el = document.getElementById('adi-file-error'); el.textContent = shotErr; el.hidden = false; form.querySelector('.adi-file-btn').classList.add('is-invalid'); return; }

    const fd = new FormData();
    ['name', 'email', 'department', 'brand', 'deviceName', 'screenSize', 'modelYear', 'chip', 'memory', 'serialNumber', 'website']
      .forEach((k) => fd.append(k, form.querySelector(`[name="${k}"]`).value));
    const fileInput = form.querySelector('[name="photo"]');
    if (fileInput && fileInput.files[0]) fd.append('photo', fileInput.files[0]);

    me = { name: form.name.value.trim(), email: form.email.value.trim(), department: form.department.value };

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true; btn.textContent = 'Submitting…';

    fetch(ADI.root + 'submit', { method: 'POST', headers: { 'X-WP-Nonce': ADI.nonce }, body: fd })
      .then(async (r) => {
        const result = await r.json();
        if (!r.ok) throw new Error(result.message || 'Something went wrong.');
        renderConfirmation();
      })
      .catch((err) => {
        statusEl.textContent = err.message || 'Something went wrong.';
        statusEl.hidden = false;
        btn.disabled = false; btn.textContent = 'Submit';
      });
  }

  // ---- admin "Add Device" page: copy the direct link ----
  const copyBtn = document.getElementById('adi-link-copy');
  const urlEl = document.getElementById('adi-link-url');
  if (copyBtn && urlEl) {
    urlEl.addEventListener('focus', () => urlEl.select());
    copyBtn.addEventListener('click', async function () {
      let ok = false;
      try {
        if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(urlEl.value); ok = true; }
      } catch (err) { /* fall through to the older way */ }
      if (!ok) {
        urlEl.focus(); urlEl.select();
        try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
      }
      copyBtn.textContent = ok ? 'Copied ✓' : 'Press Ctrl/⌘ + C';
      setTimeout(() => { copyBtn.textContent = 'Copy link'; }, 2200);
    });
  }

  renderForm();
})();
