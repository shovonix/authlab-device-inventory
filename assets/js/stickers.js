(function () {
  function api(path) {
    return fetch(ADI.root + path, { headers: { 'X-WP-Nonce': ADI.nonce } }).then((r) => r.json());
  }

  const listEl = document.getElementById('adi-sticker-device-list');
  if (!listEl) return;

  let devices = [];

  api('devices').then((data) => {
    devices = data.devices || [];
    if (devices.length === 0) { listEl.innerHTML = '<p class="adi-muted">No devices yet.</p>'; return; }
    listEl.innerHTML = '<div style="max-height:50vh;overflow:auto;border:1px solid var(--adi-border);border-radius:6px;padding:10px;">' +
      devices.map((d) => `
        <label style="display:flex;align-items:center;gap:8px;padding:4px 0;">
          <input type="checkbox" class="adi-sticker-check" value="${d.id}" checked>
          ${escapeHtml(d.sticker_code)} — ${escapeHtml(d.device_name)}
        </label>`).join('') + '</div>' +
      `<div style="margin-top:8px;">
        <a href="#" id="adi-select-all">Select all</a> · <a href="#" id="adi-select-none">Select none</a>
      </div>`;

    document.getElementById('adi-select-all').addEventListener('click', (e) => { e.preventDefault(); toggleAll(true); });
    document.getElementById('adi-select-none').addEventListener('click', (e) => { e.preventDefault(); toggleAll(false); });
  });

  function toggleAll(checked) {
    document.querySelectorAll('.adi-sticker-check').forEach((el) => { el.checked = checked; });
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  // ---- size + per row (size is shared with the device page's "Print QR") ----
  const LS_SIZE = 'adi_qr_print_mm', LS_COLS = 'adi_sticker_cols';
  const sizeIn = document.getElementById('adi-sticker-size'), colsIn = document.getElementById('adi-sticker-cols');
  const hint = document.getElementById('adi-sticker-hint');
  const GAP = 6;            // mm between stickers
  const A4_WIDTH = 190;     // printable width of A4 with 10 mm margins
  const clampSize = (v) => Math.max(15, Math.min(200, parseInt(v, 10) || 40));
  const clampCols = (v) => Math.max(1, Math.min(12, parseInt(v, 10) || 4));
  try {
    const s = parseInt(localStorage.getItem(LS_SIZE), 10), c = parseInt(localStorage.getItem(LS_COLS), 10);
    if (s) sizeIn.value = clampSize(s);
    if (c) colsIn.value = clampCols(c);
  } catch (e) { /* ignore */ }

  function updateHint() {
    const s = clampSize(sizeIn.value), c = clampCols(colsIn.value);
    const row = c * s + (c - 1) * GAP;
    const fit = Math.max(1, Math.floor((A4_WIDTH + GAP) / (s + GAP)));
    hint.textContent = row <= A4_WIDTH
      ? `${s} × ${s} mm squares, ${c} per row (${row} mm wide, fits A4).`
      : `${c} × ${s} mm is ${row} mm wide, more than A4 (${A4_WIDTH} mm). At this size, at most ${fit} fit per row.`;
    hint.classList.toggle('adi-error', row > A4_WIDTH);
  }
  sizeIn.addEventListener('input', updateHint); colsIn.addEventListener('input', updateHint); updateHint();

  document.getElementById('adi-sticker-generate').addEventListener('click', function () {
    const selectedIds = Array.from(document.querySelectorAll('.adi-sticker-check:checked')).map((el) => el.value);
    const selected = devices.filter((d) => selectedIds.includes(String(d.id)));
    if (selected.length === 0) { alert('Select at least one device.'); return; }

    const size = clampSize(sizeIn.value), cols = clampCols(colsIn.value);
    sizeIn.value = size; colsIn.value = cols; updateHint();
    try { localStorage.setItem(LS_SIZE, String(size)); localStorage.setItem(LS_COLS, String(cols)); } catch (e) { /* ignore */ }

    const grid = document.getElementById('adi-sticker-grid');
    grid.innerHTML = '';
    grid.style.gap = GAP + 'mm';
    grid.style.gridTemplateColumns = `repeat(${cols}, ${size}mm)`;

    selected.forEach((d) => {
      const sticker = document.createElement('div');
      sticker.className = 'sticker';
      sticker.style.cssText = `width:${size}mm;height:${size}mm;box-sizing:border-box;outline:1px dashed #b7b4aa;outline-offset:0;overflow:hidden;background:#fff;`;
      // Square QR (the device ID is drawn in its centre), rendered sharp and sized in mm for print.
      const holder = document.createElement('div');
      const canvas = ADIQR.render(holder, `${ADI.viewUrl}?id=${d.id}`, d.sticker_code, size * 96 / 25.4, { pixelRatio: 7 });
      if (canvas) {
        const img = document.createElement('img');
        img.alt = d.sticker_code;
        img.src = canvas.toDataURL('image/png');
        img.style.cssText = `display:block;width:${size}mm;height:${size}mm;image-rendering:pixelated;`;
        sticker.appendChild(img);
      } else {
        sticker.appendChild(holder);   // fallback: plain QR from the library
      }
      grid.appendChild(sticker);
    });

    document.getElementById('adi-sticker-sheet').style.display = 'block';
    document.getElementById('adi-sticker-print').disabled = false;
  });

  document.getElementById('adi-sticker-print').addEventListener('click', function () { window.print(); });
})();
