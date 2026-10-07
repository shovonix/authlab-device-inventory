(function () {
  function api(path, opts) {
    opts = opts || {};
    opts.headers = Object.assign({ 'Content-Type': 'application/json', 'X-WP-Nonce': ADI.nonce }, opts.headers || {});
    return fetch(ADI.root + path, opts).then(async (r) => {
      const data = await r.json();
      if (!r.ok) throw new Error(data.message || data.error || 'Request failed');
      return data;
    });
  }

  function statusClass(status) {
    if (status === 'In Use') return 'adi-badge in-use';
    if (status === 'In Stock') return 'adi-badge in-stock';
    if (status === 'In Repair' || status === 'Need repair') return 'adi-badge repair';
    if (status === 'Dead') return 'adi-badge dead';
    if (status === 'Pending Review') return 'adi-badge pending';
    return 'adi-badge';
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  // ---- Device list page ----
  const listEl = document.getElementById('adi-device-list');
  if (listEl) {
    const STATUSES = ['Pending Review', 'In Use', 'In Stock', 'Need repair', 'In Repair', 'Dead'];
    const PAGINATE = !!ADI.paginate;   // Settings > "Split the list into pages" (off by default)
    const PER_PAGE = 10;
    let all = [];
    let filter = 'All';
    let query = '';
    let page = 1;

    // ---- the Filters dropdown: Department, Brand, Size, Year, Chip, Memory ----
    // Values are tidied so "16GB" and "16 GB" are one choice, "14 inch" and "14-inch" are one choice,
    // and "Nov 2023" / "Jan 2023" / "2023" are all the year 2023.
    const clean = (v) => String(v == null ? '' : v).replace(/\s+/g, ' ').trim();
    const numOf = (s) => { const m = String(s).match(/\d+(?:\.\d+)?/); const n = m ? parseFloat(m[0]) : 0; return /TB$/.test(s) ? n * 1024 : n; };
    const FACETS = [
      { key: 'department', label: 'Department', sort: 'text', get: (d) => clean(d.department) },
      { key: 'brand', label: 'Brand', sort: 'text', get: (d) => clean(d.brand) },
      { key: 'size', label: 'Size', sort: 'num', get: (d) => { const m = clean(d.screen_size).match(/\d+(?:\.\d+)?/); return m ? m[0] + '-inch' : clean(d.screen_size); } },
      { key: 'year', label: 'Year', sort: 'numdesc', get: (d) => { const m = clean(d.model_year).match(/\b(?:19|20)\d{2}\b/); return m ? m[0] : ''; } },
      { key: 'chip', label: 'Chip', sort: 'text', get: (d) => clean(d.chip) },
      { key: 'memory', label: 'Memory', sort: 'num', get: (d) => { const s = clean(d.memory); const m = s.match(/(\d+(?:\.\d+)?)\s*(tb|gb|t|g)?/i); return m ? m[1] + ' ' + ((m[2] || 'g').toLowerCase().charAt(0) === 't' ? 'TB' : 'GB') : s; } }
    ];
    const sel = {};                       // facet key -> Set of the chosen values (lower-case)
    FACETS.forEach((f) => { sel[f.key] = new Set(); });
    let facetOptions = [];                // [{ f, list: [{ key, label, n }] }]
    let openGroup = 'department';
    let popOpen = false;

    function buildOptions() {
      facetOptions = FACETS.map((f) => {
        const map = new Map();
        all.forEach((d) => {
          const v = f.get(d); if (!v) return;
          const k = v.toLowerCase(); const e = map.get(k) || { key: k, label: v, n: 0 };
          e.n++; map.set(k, e);
        });
        const list = Array.from(map.values());
        list.sort(f.sort === 'text' ? (x, y) => x.label.localeCompare(y.label, undefined, { sensitivity: 'base' })
          : f.sort === 'numdesc' ? (x, y) => numOf(y.label) - numOf(x.label) : (x, y) => numOf(x.label) - numOf(y.label));
        return { f, list };
      });
    }
    const labelOf = (fkey, k) => { const g = facetOptions.find((o) => o.f.key === fkey); const o = g && g.list.find((x) => x.key === k); return o ? o.label : k; };
    const activeCount = () => FACETS.reduce((n, f) => n + sel[f.key].size, 0);

    const CHEV = '<svg class="adi-fchev" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8l5 5 5-5"/></svg>';
    function filterGroupsHtml() {
      return facetOptions.map(({ f, list }) => {
        const n = sel[f.key].size, open = openGroup === f.key;
        return `<section class="adi-fgroup${open ? ' open' : ''}" data-facet="${f.key}">
          <button type="button" class="adi-fgroup-head" aria-expanded="${open}"><span>${f.label}</span><span class="adi-fgroup-n"${n ? '' : ' hidden'}>${n}</span>${CHEV}</button>
          <div class="adi-fgroup-body"${open ? '' : ' hidden'}>${list.length
            ? list.map((o) => `<label class="adi-fopt"><input type="checkbox" data-fkey="${f.key}" value="${escapeHtml(o.key)}"${sel[f.key].has(o.key) ? ' checked' : ''}><span class="adi-fopt-l">${escapeHtml(o.label)}</span><em>${o.n}</em></label>`).join('')
            : '<p class="adi-muted adi-fnone">Nothing to choose from yet.</p>'}</div>
        </section>`;
      }).join('');
    }

    // the button badge, the per-group counts, and the row of chosen filters under the toolbar
    function updateFilterUi() {
      const n = activeCount();
      const btn = listEl.querySelector('.adi-filter-btn');
      btn.classList.toggle('active', n > 0);
      const badge = btn.querySelector('.adi-filter-n'); badge.textContent = n; badge.hidden = n === 0;
      listEl.querySelectorAll('.adi-fgroup').forEach((g) => {
        const c = sel[g.dataset.facet].size, b = g.querySelector('.adi-fgroup-n'); b.textContent = c; b.hidden = c === 0;
      });
      const row = document.getElementById('adi-active-filters');
      const tags = [];
      FACETS.forEach((f) => sel[f.key].forEach((k) => tags.push({ f, k, label: labelOf(f.key, k) })));
      row.hidden = tags.length === 0;
      row.innerHTML = tags.map((t) => `<button type="button" class="adi-tag" data-rm-facet="${t.f.key}" data-rm-val="${escapeHtml(t.k)}" aria-label="Remove filter ${escapeHtml(t.f.label)}: ${escapeHtml(t.label)}">${t.f.label}: <strong>${escapeHtml(t.label)}</strong> <span aria-hidden="true">&times;</span></button>`).join('')
        + (tags.length ? '<button type="button" class="adi-tag-clear">Clear all</button>' : '');
    }
    function clearFilters() { FACETS.forEach((f) => sel[f.key].clear()); page = 1; refreshPanel(); updateFilterUi(); renderResults(); }
    function refreshPanel() { const g = listEl.querySelector('.adi-filter-groups'); if (g) g.innerHTML = filterGroupsHtml(); }

    listEl.innerHTML = '<p class="adi-muted">Loading devices...</p>';

    api('devices').then(({ devices }) => {
      // Pending submissions first (they need action), then newest first.
      all = devices.slice().sort((a, b) => {
        const pa = a.status === 'Pending Review' ? 0 : 1;
        const pb = b.status === 'Pending Review' ? 0 : 1;
        if (pa !== pb) return pa - pb;
        return String(b.created_at).localeCompare(String(a.created_at));
      });
      buildOptions();
      renderShell();
      renderResults();
    }).catch((e) => { listEl.innerHTML = `<p class="adi-error">${escapeHtml(e.message)}</p>`; });

    function count(status) { return all.filter((d) => d.status === status).length; }

    function renderShell() {
      if (all.length === 0) {
        listEl.innerHTML = `<div class="adi-empty"><p><strong>No devices yet.</strong></p>
          <p class="adi-muted">Devices appear here when someone submits the intake form${ADI.isManager ? ', or when you add one yourself' : ''}.</p></div>`;
        return;
      }
      const stats = [
        ['Total', all.length, 'All'],
        ['In use', count('In Use'), 'In Use'],
        ['In stock', count('In Stock'), 'In Stock'],
        ['Repair', count('In Repair') + count('Need repair'), 'REPAIR'],
        ['Pending review', count('Pending Review'), 'Pending Review']
      ];
      const chips = ['All'].concat(STATUSES);
      const FUNNEL = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h14l-5.2 6.3v4.4l-3.6 1.8v-6.2z"/></svg>';

      listEl.innerHTML = `
        <div class="adi-stats">
          ${stats.map((s) => `<button type="button" class="adi-stat ${s[0] === 'Pending review' && s[1] > 0 ? 'attention' : ''}" data-filter="${s[2]}">
            <span class="adi-stat-num">${s[1]}</span><span class="adi-stat-label">${s[0]}</span></button>`).join('')}
        </div>
        <div class="adi-toolbar">
          <div class="adi-chips">
            ${chips.map((s) => `<button type="button" class="adi-chip" data-filter="${s}">${s}${s === 'All' ? '' : ' <span class="adi-chip-count">' + count(s) + '</span>'}</button>`).join('')}
          </div>
          <div class="adi-filter">
            <button type="button" class="adi-chip adi-filter-btn" aria-haspopup="true" aria-expanded="false" aria-controls="adi-filter-pop">${FUNNEL}<span>Filters</span><span class="adi-filter-n" hidden>0</span></button>
            <div class="adi-filter-pop" id="adi-filter-pop" role="group" aria-label="Filter devices" hidden>
              <div class="adi-filter-head"><strong>Filter by</strong><button type="button" class="adi-link-btn adi-filter-clear">Clear all</button></div>
              <div class="adi-filter-groups">${filterGroupsHtml()}</div>
            </div>
          </div>
          <label class="adi-search-wrap">
            <svg class="adi-search-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="9" r="6"/><path d="M14 14l4 4"/></svg>
            <input type="search" id="adi-search" class="adi-search" placeholder="Search devices…" aria-label="Search devices">
          </label>
        </div>
        <div class="adi-active-filters" id="adi-active-filters" hidden></div>
        <div id="adi-results"></div>`;

      document.getElementById('adi-search').addEventListener('input', (e) => { query = e.target.value.trim().toLowerCase(); page = 1; renderResults(); });
      listEl.querySelectorAll('[data-filter]').forEach((el) => el.addEventListener('click', () => {
        filter = el.dataset.filter; page = 1; renderResults();
      }));

      // the dropdown
      const btn = listEl.querySelector('.adi-filter-btn'), pop = document.getElementById('adi-filter-pop'), groups = pop.querySelector('.adi-filter-groups');
      const setOpen = (v) => { popOpen = v; pop.hidden = !v; btn.setAttribute('aria-expanded', v ? 'true' : 'false'); };
      btn.addEventListener('click', () => setOpen(!popOpen));
      // (composedPath, because the clicked element may already have been replaced by the time this runs)
      document.addEventListener('click', (e) => { if (popOpen && !e.composedPath().some((n) => n.classList && n.classList.contains('adi-filter'))) setOpen(false); });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && popOpen) { setOpen(false); btn.focus(); } });
      groups.addEventListener('click', (e) => {          // open one group at a time (only classes change, nothing is rebuilt)
        const h = e.target.closest('.adi-fgroup-head'); if (!h) return;
        const key = h.parentElement.dataset.facet; openGroup = openGroup === key ? null : key;
        groups.querySelectorAll('.adi-fgroup').forEach((g) => {
          const o = g.dataset.facet === openGroup;
          g.classList.toggle('open', o);
          g.querySelector('.adi-fgroup-head').setAttribute('aria-expanded', o ? 'true' : 'false');
          g.querySelector('.adi-fgroup-body').hidden = !o;
        });
      });
      groups.addEventListener('change', (e) => {
        const c = e.target.closest('input[data-fkey]'); if (!c) return;
        const s = sel[c.dataset.fkey]; if (c.checked) s.add(c.value); else s.delete(c.value);
        page = 1; updateFilterUi(); renderResults();
      });
      pop.querySelector('.adi-filter-clear').addEventListener('click', clearFilters);
      const row = document.getElementById('adi-active-filters');
      row.addEventListener('click', (e) => {
        if (e.target.closest('.adi-tag-clear')) return clearFilters();
        const t = e.target.closest('.adi-tag'); if (!t) return;
        sel[t.dataset.rmFacet].delete(t.dataset.rmVal); page = 1; refreshPanel(); updateFilterUi(); renderResults();
      });
      updateFilterUi();
    }

    function matches(d) {
      if (filter === 'REPAIR') { if (d.status !== 'In Repair' && d.status !== 'Need repair') return false; }
      else if (filter !== 'All' && d.status !== filter) return false;
      for (const f of FACETS) {                          // inside one filter: any of the ticked values; between filters: all
        const s = sel[f.key];
        if (s.size && !s.has(f.get(d).toLowerCase())) return false;
      }
      if (!query) return true;
      const hay = [d.sticker_code, d.device_name, d.brand, d.current_owner, d.owner_email, d.serial_number, d.department, d.chip]
        .join(' ').toLowerCase();
      return hay.indexOf(query) !== -1;
    }

    // 1 2 3 … 9 10 : always the first, the last and the neighbours of the current page
    function pagerHtml(cur, total) {
      const keep = new Set([1, total, cur, cur - 1, cur + 1]);
      if (cur <= 3) { keep.add(2); keep.add(3); keep.add(4); }
      if (cur >= total - 2) { keep.add(total - 1); keep.add(total - 2); keep.add(total - 3); }
      const nums = Array.from(keep).filter((n) => n >= 1 && n <= total).sort((a, b) => a - b);
      let items = '', prev = 0;
      nums.forEach((n) => {
        if (prev && n - prev > 1) items += '<span class="adi-pg-gap" aria-hidden="true">&hellip;</span>';
        items += `<button type="button" class="adi-pg" data-page="${n}"${n === cur ? ' aria-current="page"' : ''} aria-label="Page ${n}">${n}</button>`;
        prev = n;
      });
      return `<nav class="adi-pager" aria-label="Pages">
        <button type="button" class="adi-pg" data-page="${cur - 1}" aria-label="Previous page"${cur === 1 ? ' disabled' : ''}>&lsaquo;</button>${items}
        <button type="button" class="adi-pg" data-page="${cur + 1}" aria-label="Next page"${cur === total ? ' disabled' : ''}>&rsaquo;</button></nav>`;
    }

    function renderResults() {
      const out = document.getElementById('adi-results');
      if (!out) return;
      listEl.querySelectorAll('[data-filter]').forEach((el) => el.classList.toggle('active', el.dataset.filter === filter));

      const rows = all.filter(matches);
      if (rows.length === 0) {
        out.innerHTML = `<div class="adi-empty"><p><strong>No devices match.</strong></p><p class="adi-muted">Try a different search or filter.</p></div>`;
        return;
      }
      const size = PAGINATE ? PER_PAGE : rows.length;   // pagination off: everything on one page
      const pages = Math.ceil(rows.length / size);
      if (page > pages) page = pages;
      if (page < 1) page = 1;
      const from = (page - 1) * size;
      const shown = rows.slice(from, from + size);
      out.innerHTML = `<div class="adi-table-wrap"><table class="adi-table adi-list-table">
        <thead><tr><th>ID</th><th>Device</th><th>Owner</th><th>Serial</th><th>Status</th></tr></thead>
        <tbody>${shown.map((d) => {
          const href = `${ADI.detailUrl}&id=${d.id}`;
          const sub = [d.chip, d.memory, d.screen_size].filter(Boolean).join(' · ');
          return `<tr class="adi-row" data-href="${href}">
            <td class="c-id"><a class="adi-id" href="${href}">${escapeHtml(d.sticker_code)}</a></td>
            <td class="c-device">${escapeHtml(d.device_name)}${sub ? `<div class="adi-sub">${escapeHtml(sub)}</div>` : ''}</td>
            <td class="c-owner">${escapeHtml(d.current_owner || '—')}${d.department ? `<div class="adi-sub">${escapeHtml(d.department)}</div>` : ''}</td>
            <td class="c-serial adi-mono">${escapeHtml(d.serial_number || '—')}</td>
            <td class="c-status"><span class="${statusClass(d.status)}">${escapeHtml(d.status)}</span></td>
          </tr>`;
        }).join('')}</tbody></table></div>
        <div class="adi-list-foot">
          <p class="adi-muted adi-count">Showing ${from + 1}&ndash;${from + shown.length} of ${rows.length} device${rows.length === 1 ? '' : 's'}${rows.length !== all.length ? ` (filtered from ${all.length})` : ''}</p>
          ${pages > 1 ? pagerHtml(page, pages) : ''}
        </div>`;

      out.querySelectorAll('.adi-row').forEach((tr) => tr.addEventListener('click', (e) => {
        if (e.target.closest('a')) return;
        window.location.href = tr.dataset.href;
      }));
      out.querySelectorAll('.adi-pg[data-page]').forEach((b) => b.addEventListener('click', () => {
        page = parseInt(b.dataset.page, 10); renderResults();
        listEl.scrollIntoView({ block: 'start' });
      }));
    }
  }

  // ---- Device detail page ----
  // Left (70%): Device Status (owner, department, status + quick actions), then Device History.
  // Right (30%): Device details (permanent once reviewed), then the QR with its print options.
  const detailEl = document.getElementById('adi-device-detail');
  if (detailEl) {
    const id = detailEl.dataset.id;
    const LS = { size: 'adi_qr_print_mm', old: 'adi_qr_print_w' };   // size is shared with the Print Stickers page
    const STATUS_CHOICES = ['In Stock', 'In Use', 'Need repair', 'In Repair', 'Dead'];
    const BRANDS = ['Apple', 'Dell', 'HP', 'Lenovo', 'Asus', 'Other'];
    const DEPARTMENTS = ['Dev', 'Marketing', 'Support', 'MGD', 'HR'];
    const LOCK = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="9" width="11" height="8" rx="2"/><path d="M7 9V6.5a3 3 0 0 1 6 0V9"/></svg>';
    const isLocked = (d) => Number(d.specs_locked) === 1;
    const isPending = (d) => d.status === 'Pending Review';
    loadDetail();

    function loadDetail() {
      api('devices/' + id).then(({ device }) => renderDetail(device)).catch((e) => {
        detailEl.innerHTML = `<p class="adi-error">${escapeHtml(e.message)}</p>`;
      });
    }

    function put(body) { return api(`devices/${id}`, { method: 'PUT', body: JSON.stringify(body) }); }
    function showError(scope, msg) { const el = scope.querySelector('.adi-error'); if (el) { el.textContent = msg; el.hidden = false; el.style.display = 'block'; } }
    function deptList() { return `<datalist id="adi-dept-list">${DEPARTMENTS.map((o) => `<option value="${o}">`).join('')}</datalist>`; }

    // ---------- Device details (the screenshot data): permanent once reviewed ----------
    const SPEC_ROWS = [
      ['Brand', 'brand'], ['Device name', 'device_name'], ['Screen size', 'screen_size'], ['Model year', 'model_year'],
      ['Chip', 'chip'], ['Memory', 'memory'], ['Serial number', 'serial_number']
    ];

    function photoRow(device) {
      return device.photo_url
        ? `<tr><td class="adi-muted">Photo</td><td><a href="${device.photo_url}" target="_blank" title="Verification photo — open full size"><img src="${device.photo_url}" class="adi-thumb" alt="Verification photo"></a></td></tr>`
        : '';
    }

    function detailsCardHtml(device) {
      const locked = isLocked(device);
      const head = locked
        ? `<span class="adi-lock" title="Recorded at review. Permanent, like the device ID.">${LOCK} Locked</span>`
        : (ADI.isManager ? '<button type="button" class="adi-btn secondary adi-sm" id="adi-edit-btn">Edit</button>' : '');
      return `
        <div class="adi-card-head"><h3>Device details</h3>${head}</div>
        <table class="adi-table"><tbody>
          ${SPEC_ROWS.map((r) => `<tr><td class="adi-muted">${r[0]}</td><td>${escapeHtml(device[r[1]])}</td></tr>`).join('')}
          ${photoRow(device)}
        </tbody></table>
        <p class="adi-muted adi-lock-note">${locked ? 'Recorded at review — permanent and cannot be edited.' : 'You can still correct these until the submission is approved. After that they are locked for good.'}</p>`;
    }

    // pending submissions only: correct a typo before approving
    function startSpecEdit(device) {
      const card = document.getElementById('adi-details-card');
      const row = (name, label, list) => `<tr><td class="adi-muted"><label for="ie-${name}">${label}</label></td><td><input class="adi-ie" id="ie-${name}" name="${name}" value="${escapeHtml(device[name] || '')}" ${list ? `list="ie-list-${name}"` : ''} autocomplete="off">${list ? `<datalist id="ie-list-${name}">${list.map((o) => `<option value="${o}">`).join('')}</datalist>` : ''}</td></tr>`;
      card.innerHTML = `
        <form id="adi-edit-form" novalidate>
          <div class="adi-card-head"><h3>Edit details</h3>
            <div class="adi-card-actions">
              <button type="submit" class="adi-btn adi-sm" id="adi-save-edit">Save</button>
              <button type="button" class="adi-btn secondary adi-sm" id="adi-cancel-edit">Cancel</button>
            </div></div>
          <table class="adi-table adi-ie-table"><tbody>
            ${row('brand', 'Brand', BRANDS)}${row('device_name', 'Device name')}${row('screen_size', 'Screen size')}${row('model_year', 'Model year')}
            ${row('chip', 'Chip')}${row('memory', 'Memory')}${row('serial_number', 'Serial number')}
            ${photoRow(device)}
          </tbody></table>
          <div class="adi-error" hidden></div>
        </form>`;
      const first = card.querySelector('#ie-device_name'); if (first) first.focus();
      card.querySelector('#adi-edit-form').addEventListener('submit', function (e) {
        e.preventDefault();
        const body = {};
        SPEC_ROWS.forEach((r) => { body[r[1]] = e.target.querySelector(`[name="${r[1]}"]`).value; });
        const save = document.getElementById('adi-save-edit'); save.disabled = true;
        put(body).then(() => loadDetail()).catch((err) => { save.disabled = false; showError(card, err.message); });
      });
      card.querySelector('#adi-cancel-edit').addEventListener('click', () => loadDetail());
      card.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.stopPropagation(); loadDetail(); } });
    }

    // ---------- Device Status: owner, department, status + quick actions ----------
    function actionsHtml(device) {
      if (isPending(device)) {
        return '<p class="adi-muted adi-action-hint">Approve this submission first — then you can hand it over, send it for repair or return it to stock.</p>';
      }
      const dead = device.status === 'Dead';
      const btn = (key, label, off, why) => `<button type="button" class="adi-btn secondary adi-sm" data-action="${key}" ${off ? `disabled title="${why}"` : ''}>${label}</button>`;
      return `<div class="adi-actions-row" role="group" aria-label="Quick actions">
        ${btn('handover', 'Hand over', dead, 'This device is marked Dead')}
        ${btn('repair', 'Send for repair', dead || device.status === 'In Repair', dead ? 'This device is marked Dead' : 'Already in repair')}
        ${btn('stock', 'Return to stock', dead || device.status === 'In Stock', dead ? 'This device is marked Dead' : 'Already in stock')}
      </div>`;
    }

    function statusCardHtml(device) {
      const mgr = ADI.isManager;
      return `
        <div class="adi-card-head"><h3>Device Status</h3>${mgr ? '<button type="button" class="adi-btn secondary adi-sm" id="adi-status-edit">Edit</button>' : ''}</div>
        <div class="adi-status-grid">
          <div><span class="adi-muted">Current owner</span><strong>${escapeHtml(device.current_owner || '—')}</strong></div>
          <div><span class="adi-muted">Department</span><strong>${escapeHtml(device.department || '—')}</strong></div>
          <div><span class="adi-muted">Status</span><span class="${statusClass(device.status)}">${escapeHtml(device.status)}</span></div>
        </div>
        ${mgr ? actionsHtml(device) : ''}
        <div id="adi-action-panel" hidden></div>`;
    }

    const ACTIONS = {
      handover: { title: 'Hand over', intro: 'Sets the new owner and department, and the status to In Use.', confirm: 'Confirm hand over',
                  hint: 'e.g. battery 100%, charger and cable included, no damage',
                  body: (v) => ({ current_owner: v.owner, department: v.department, status: 'In Use' }) },
      repair:   { title: 'Send for repair', intro: 'Sets the status to In Repair. The owner stays the same.', confirm: 'Send for repair',
                  hint: 'e.g. screen flickering — sent to the service centre',
                  body: () => ({ status: 'In Repair' }) },
      stock:    { title: 'Return to stock', intro: 'Clears the owner and department, and sets the status to In Stock.', confirm: 'Return to stock',
                  hint: 'e.g. condition checked, no damage, charger returned',
                  body: () => ({ status: 'In Stock', current_owner: '', department: '' }) }
    };

    function openAction(device, key) {
      const a = ACTIONS[key];
      const panel = document.getElementById('adi-action-panel');
      const row = document.querySelector('#adi-status-card .adi-actions-row');
      panel.innerHTML = `
        <form class="adi-action-form" novalidate>
          <h4>${a.title}</h4>
          <p class="adi-muted">${a.intro}</p>
          ${key === 'handover' ? `
            <div class="adi-form-row">
              <label><span class="adi-muted">Hand over to</span><input class="adi-ie" name="owner" autocomplete="off" required></label>
              <label><span class="adi-muted">Department</span><input class="adi-ie" name="department" list="adi-dept-list" value="${escapeHtml(device.department || '')}" autocomplete="off"></label>
            </div>${deptList()}` : ''}
          <label><span class="adi-muted">Note (optional) — saved in Device History</span><input class="adi-ie" name="note" placeholder="${a.hint}" autocomplete="off"></label>
          <div class="adi-form-actions">
            <button type="submit" class="adi-btn adi-sm">${a.confirm}</button>
            <button type="button" class="adi-btn secondary adi-sm" data-cancel>Cancel</button>
          </div>
          <div class="adi-error" hidden></div>
        </form>`;
      panel.hidden = false;
      if (row) row.hidden = true;
      const form = panel.querySelector('form');
      const first = form.querySelector('input'); if (first) first.focus();
      const close = () => { panel.hidden = true; panel.innerHTML = ''; if (row) row.hidden = false; };
      form.querySelector('[data-cancel]').addEventListener('click', close);
      form.addEventListener('keydown', (e) => { if (e.key === 'Escape') { e.stopPropagation(); close(); } });
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        const f = e.target;
        const v = {
          owner: f.owner ? f.owner.value.trim() : '',
          department: f.department ? f.department.value.trim() : '',
          note: f.note.value.trim()
        };
        if (key === 'handover' && !v.owner) { showError(panel, 'Enter who the device is being handed over to.'); f.owner.focus(); return; }
        const body = Object.assign({}, a.body(v));
        if (v.note) body.note = v.note;
        const go = f.querySelector('button[type="submit"]'); go.disabled = true;
        put(body).then(() => loadDetail()).catch((err) => { go.disabled = false; showError(panel, err.message); });
      });
    }

    function opts(list, current) {
      const all = list.indexOf(current) === -1 && current ? list.concat([current]) : list;
      return all.map((o) => `<option ${o === current ? 'selected' : ''}>${escapeHtml(o)}</option>`).join('');
    }

    function startStatusEdit(device) {
      const card = document.getElementById('adi-status-card');
      const pending = isPending(device);
      card.innerHTML = `
        <form id="adi-status-form" novalidate>
          <div class="adi-card-head"><h3>Device Status</h3>
            <div class="adi-card-actions">
              <button type="submit" class="adi-btn adi-sm" id="adi-status-save">Save</button>
              <button type="button" class="adi-btn secondary adi-sm" id="adi-status-cancel">Cancel</button>
            </div></div>
          <div class="adi-status-grid">
            <label><span class="adi-muted">Current owner</span><input class="adi-ie" name="current_owner" value="${escapeHtml(device.current_owner || '')}" autocomplete="off"></label>
            <label><span class="adi-muted">Department</span><input class="adi-ie" name="department" list="adi-dept-list" value="${escapeHtml(device.department || '')}" autocomplete="off"></label>
            <label><span class="adi-muted">Status</span><select class="adi-ie" name="status" ${pending ? 'disabled' : ''}>${pending ? '<option selected>Pending Review</option>' : opts(STATUS_CHOICES, device.status)}</select></label>
          </div>${deptList()}
          ${pending ? '<p class="adi-muted">The status changes when you approve the submission.</p>' : ''}
          <div class="adi-error" hidden></div>
        </form>`;
      const first = card.querySelector('[name="current_owner"]'); if (first) first.focus();
      card.querySelector('#adi-status-form').addEventListener('submit', function (e) {
        e.preventDefault();
        const f = e.target;
        const body = { current_owner: f.current_owner.value.trim(), department: f.department.value.trim() };
        if (!pending) body.status = f.status.value;
        const save = document.getElementById('adi-status-save'); save.disabled = true;
        put(body).then(() => loadDetail()).catch((err) => { save.disabled = false; showError(card, err.message); });
      });
      card.querySelector('#adi-status-cancel').addEventListener('click', () => loadDetail());
      card.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.stopPropagation(); loadDetail(); } });
    }

    // ---------- the page ----------
    function readMm(key, fallback) {
      try {
        const v = parseInt(localStorage.getItem(key), 10) || parseInt(localStorage.getItem(LS.old), 10);
        return v || fallback;
      } catch (e) { return fallback; }
    }

    function renderDetail(device) {
      // The page title becomes the device ID, with its status next to it.
      const title = document.querySelector('.adi-app h1.adi-title');
      if (title) title.innerHTML = `${escapeHtml(device.sticker_code)} <span class="${statusClass(device.status)} adi-title-badge">${escapeHtml(device.status)}</span>`;

      const banner = (isPending(device) && ADI.isManager) ? `
        <div class="adi-card adi-detail-banner" style="border-color:var(--adi-warn);">
          <h3>Review this submission</h3>
          <p class="adi-muted">Submitted by ${escapeHtml(device.current_owner)} (${escapeHtml(device.owner_email)}). This device already has its permanent ID: <strong>${escapeHtml(device.sticker_code)}</strong>. Check the details (use Edit to correct a typo), then approve. <strong>Approving locks the details permanently.</strong></p>
          <button class="adi-btn" id="adi-approve-btn">Approve</button>
          <div id="adi-approve-error" class="adi-error" style="display:none;"></div>
        </div>` : '';

      detailEl.innerHTML = `
        <div class="adi-detail">
          ${banner}
          <div class="adi-detail-main">
            <div class="adi-card" id="adi-status-card">${statusCardHtml(device)}</div>
            <div class="adi-card">
              <h3>Device History</h3>
              <div id="adi-history"></div>
            </div>
          </div>
          <div class="adi-detail-side">
            <div class="adi-card" id="adi-details-card">${detailsCardHtml(device)}</div>
            <div class="adi-card adi-qr-card">
              <div id="adi-qr"></div>
              <p class="adi-muted adi-qr-caption">Scan to open this record</p>
              <div class="adi-qr-print">
                <div class="adi-qr-dim"><label for="adi-qr-size">Size</label><div class="adi-qr-box"><input class="adi-ie" type="number" id="adi-qr-size" min="15" max="200" step="1" value="${readMm(LS.size, 40)}"><em>mm</em></div></div>
                <button type="button" class="adi-btn secondary adi-sm" id="adi-qr-print">Print QR</button>
              </div>
              <p class="adi-muted adi-qr-hint" id="adi-qr-hint"></p>
            </div>
          </div>
        </div>`;

      const qrHolder = document.getElementById('adi-qr');
      const qrSize = Math.max(140, Math.min(200, Math.floor(qrHolder.clientWidth) || 200));
      ADIQR.render(qrHolder, ADI.viewUrl + '?id=' + device.id, device.sticker_code, qrSize);

      ADIHistory.mount(document.getElementById('adi-history'), id);

      // ---- print size: one number, the QR is always square ----
      const sizeIn = document.getElementById('adi-qr-size'), hint = document.getElementById('adi-qr-hint');
      const clampMm = (v) => Math.max(15, Math.min(200, parseInt(v, 10) || 40));
      function updateHint() { const s = clampMm(sizeIn.value); hint.textContent = `Prints as a ${s} × ${s} mm square.`; }
      sizeIn.addEventListener('input', updateHint); updateHint();
      document.getElementById('adi-qr-print').addEventListener('click', function () {
        const s = clampMm(sizeIn.value);
        sizeIn.value = s; updateHint();
        try { localStorage.setItem(LS.size, String(s)); } catch (e) { /* ignore */ }
        printSingleQR(device, s);
      });

      // ---- approve / edit / quick actions ----
      const approveBtn = document.getElementById('adi-approve-btn');
      if (approveBtn) {
        approveBtn.addEventListener('click', function () {
          approveBtn.disabled = true;
          put({ status: 'In Use' }).then(() => loadDetail()).catch((e) => {
            approveBtn.disabled = false;
            const el = document.getElementById('adi-approve-error'); el.textContent = e.message; el.style.display = 'block';
          });
        });
      }
      const specEdit = document.getElementById('adi-edit-btn');
      if (specEdit) specEdit.addEventListener('click', () => startSpecEdit(device));
      const statusEdit = document.getElementById('adi-status-edit');
      if (statusEdit) statusEdit.addEventListener('click', () => startStatusEdit(device));
      detailEl.querySelectorAll('[data-action]').forEach((b) => b.addEventListener('click', () => openAction(device, b.getAttribute('data-action'))));
    }

    // Prints just this device's QR on its own, as an s x s mm square.
    function printSingleQR(device, s) {
      const holder = document.createElement('div');
      const canvas = ADIQR.render(holder, ADI.viewUrl + '?id=' + device.id, device.sticker_code, s * 96 / 25.4, { pixelRatio: 7 });
      if (!canvas) { alert('Could not build the QR code for printing.'); return; }
      const dataUrl = canvas.toDataURL('image/png');

      const frame = document.createElement('iframe');
      frame.setAttribute('aria-hidden', 'true');
      frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
      frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><title>' + escapeHtml(device.sticker_code) + '</title>' +
        '<style>@page{margin:8mm}html,body{margin:0;background:#fff}' +
        '.box{width:' + s + 'mm;height:' + s + 'mm}' +
        'img{display:block;width:' + s + 'mm;height:' + s + 'mm;image-rendering:pixelated;image-rendering:crisp-edges}</style></head>' +
        '<body><div class="box"><img alt="" src="' + dataUrl + '"></div></body></html>';
      frame.onload = function () {
        try {
          frame.contentWindow.onafterprint = function () { frame.remove(); };
          frame.contentWindow.focus();
          frame.contentWindow.print();
        } catch (e) { frame.remove(); }
        setTimeout(function () { if (frame.parentNode) frame.remove(); }, 120000);
      };
      document.body.appendChild(frame);
    }
  }

  function field(name, label, type, required, value) {
    return `<div class="adi-field"><label>${label}${required ? ' *' : ''}</label>
      <input type="${type}" name="${name}" value="${escapeHtml(value || '')}" ${required ? 'required' : ''}></div>`;
  }
  function select(name, label, options, selected) {
    return `<div class="adi-field"><label>${label}</label><select name="${name}">
      ${options.map((o) => `<option ${o === selected ? 'selected' : ''}>${o}</option>`).join('')}
    </select></div>`;
  }
})();
