(function () {
  const readerEl = document.getElementById('adi-qr-reader');
  if (!readerEl) return;

  const errorEl = document.getElementById('adi-scan-error');

  const html5QrCode = new Html5Qrcode('adi-qr-reader');
  html5QrCode.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: 240 },
    (decodedText) => {
      html5QrCode.stop().then(() => {
        window.location.href = decodedText;
      });
    },
    () => { /* ignore per-frame failures */ }
  ).catch((err) => {
    errorEl.style.display = 'block';
    errorEl.textContent = 'Could not start camera: ' + err;
  });
})();
