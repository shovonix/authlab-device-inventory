(function () {
  const root = document.getElementById('adi-device-view-root');
  if (!root) return;
  const id = root.dataset.id;

  function api(path, opts) {
    opts = opts || {};
    opts.headers = Object.assign({ 'Content-Type': 'application/json', 'X-WP-Nonce': ADI.nonce }, opts.headers || {});
    return fetch(ADI.root + path, opts).then(async (r) => {
      const data = await r.json();
      if (!r.ok) throw new Error(data.message || data.error || 'Request failed');
      return data;
    });
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  api('devices/' + id).then(({ device }) => {
    root.innerHTML = `
      <div class="adi-card" style="text-align:center;"><div id="adi-qr"></div></div>
      ${device.photo_url ? `<div class="adi-card"><h3>Verification photo</h3><a href="${device.photo_url}" target="_blank"><img src="${device.photo_url}" style="max-width:260px;border-radius:8px;display:block;"></a></div>` : ''}
      <div class="adi-card">
        <h2 style="margin-top:0;">${escapeHtml(device.sticker_code)}</h2>
        <table class="adi-table"><tbody>
          <tr><td class="adi-muted">Brand</td><td>${escapeHtml(device.brand)}</td></tr>
          <tr><td class="adi-muted">Device name</td><td>${escapeHtml(device.device_name)}</td></tr>
          <tr><td class="adi-muted">Screen size</td><td>${escapeHtml(device.screen_size)}</td></tr>
          <tr><td class="adi-muted">Model year</td><td>${escapeHtml(device.model_year)}</td></tr>
          <tr><td class="adi-muted">Chip</td><td>${escapeHtml(device.chip)}</td></tr>
          <tr><td class="adi-muted">Memory</td><td>${escapeHtml(device.memory)}</td></tr>
          <tr><td class="adi-muted">Serial number</td><td>${escapeHtml(device.serial_number)}</td></tr>
          <tr><td class="adi-muted">Current owner</td><td>${escapeHtml(device.current_owner || '—')}</td></tr>
          <tr><td class="adi-muted">Department</td><td>${escapeHtml(device.department || '—')}</td></tr>
          <tr><td class="adi-muted">Status</td><td>${escapeHtml(device.status)}</td></tr>
        </tbody></table>
      </div>
      <div class="adi-card">
        <h3>Device History</h3>
        <div id="adi-history"></div>
      </div>`;

    ADIQR.render(document.getElementById('adi-qr'), window.location.href, device.sticker_code, 180);

    ADIHistory.mount(document.getElementById('adi-history'), id);
  }).catch((e) => { root.innerHTML = `<p class="adi-error">${e.message}</p>`; });
})();
