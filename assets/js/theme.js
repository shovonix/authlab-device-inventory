// Light/dark toggle and full-screen toggle for the plugin's wp-admin pages.
// The saved choices are applied before the page paints by a tiny inline script in
// <head> (see ADI_Admin::early_theme_script); this file handles the clicks and
// remembers the choices in this browser.
(function () {
  var root = document.documentElement;

  function setFullscreen(on) {
    if (on) root.setAttribute('data-adi-fs', '1'); else root.removeAttribute('data-adi-fs');
    try { localStorage.setItem('adi_fullscreen', on ? '1' : '0'); } catch (err) { /* private mode etc. */ }
    syncPressed();
  }

  function syncPressed() {
    var on = root.hasAttribute('data-adi-fs');
    document.querySelectorAll('.adi-fs-toggle').forEach(function (b) {
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      var label = on ? 'Exit full screen (Esc)' : 'Full screen';
      b.setAttribute('title', label);
      b.setAttribute('aria-label', label);
    });
  }

  document.addEventListener('click', function (e) {
    var themeBtn = e.target.closest('.adi-theme-toggle [data-theme]');
    if (themeBtn) {
      var theme = themeBtn.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
      root.setAttribute('data-adi-theme', theme);
      try { localStorage.setItem('adi_theme', theme); } catch (err) { /* private mode etc. */ }
      return;
    }
    if (e.target.closest('.adi-fs-toggle')) {
      setFullscreen(!root.hasAttribute('data-adi-fs'));
    }
  });

  // Esc leaves full screen (but not while typing in a field, e.g. clearing the search box).
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' || !root.hasAttribute('data-adi-fs')) return;
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    setFullscreen(false);
  });

  syncPressed();
})();
