// QR code with the device ID printed in the middle (e.g. "ALD-A001").
//
// The bundled qrcode.min.js library works out which squares are dark; this file
// draws them itself on a <canvas> so we can put a white label box over the centre.
// The label is painted INTO the canvas (not an HTML overlay), so it still prints
// correctly even when the browser's "print background graphics" option is off.
//
// Error-correction level Q (25%) is used and the label covers under ~9% of the
// code, which leaves plenty of margin for phone cameras to read it.
//
// Usage: ADIQR.render(containerElement, textToEncode, 'ALD-A001', sizeInCssPixels)
(function () {
  var QUIET = 3; // white border around the code, in modules

  function splitLabel(label) {
    // "ALD-A001" -> ["ALD-", "A001"] so each line can use bigger letters
    var i = label.indexOf('-');
    if (i > 0 && i < label.length - 1) return [label.slice(0, i + 1), label.slice(i + 1)];
    return [label];
  }

  function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  function drawLabel(ctx, label, n, cell, total, twoLine) {
    var lines = twoLine ? splitLabel(label) : [label];
    twoLine = lines.length === 2;

    var wMod = Math.round(n * (twoLine ? 0.36 : 0.50));
    var hMod = Math.round(n * (twoLine ? 0.25 : 0.16));
    var w = wMod * cell, h = hMod * cell;
    var x = Math.round((total * cell - w) / 2), y = Math.round((total * cell - h) / 2);

    ctx.fillStyle = '#ffffff';
    roundRect(ctx, x, y, w, h, cell * 1.5);
    ctx.fill();

    var fam = 'ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif';
    var fs = h * (twoLine ? 0.36 : 0.62);
    ctx.font = '700 ' + fs + 'px ' + fam;
    var widest = 0;
    lines.forEach(function (l) { widest = Math.max(widest, ctx.measureText(l).width); });
    var maxW = w * 0.88;
    if (widest > maxW) {
      fs = fs * (maxW / widest);
      ctx.font = '700 ' + fs + 'px ' + fam;
    }

    ctx.fillStyle = '#111111';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    var cx = x + w / 2, cy = y + h / 2;
    if (twoLine) {
      ctx.fillText(lines[0], cx, cy - fs * 0.55);
      ctx.fillText(lines[1], cx, cy + fs * 0.55);
    } else {
      ctx.fillText(lines[0], cx, cy);
    }
  }

  function render(container, text, label, sizePx, opts) {
    opts = opts || {};
    var model;
    try {
      // The library builds the module matrix; we only read it.
      var scratch = document.createElement('div');
      var lib = new QRCode(scratch, { text: text, width: 16, height: 16, correctLevel: QRCode.CorrectLevel.Q });
      model = lib._oQRCode;
      if (!model || typeof model.isDark !== 'function') throw new Error('no module matrix');
    } catch (e) {
      // Fallback: plain QR without the centre label.
      container.innerHTML = '';
      new QRCode(container, { text: text, width: sizePx, height: sizePx, correctLevel: QRCode.CorrectLevel.M });
      return null;
    }

    var n = model.getModuleCount();
    var total = n + QUIET * 2;
    var ratio = opts.pixelRatio || Math.max(window.devicePixelRatio || 1, 4); // sharp when printed
    var cell = Math.max(2, Math.floor((sizePx * ratio) / total));
    var px = cell * total;

    var canvas = document.createElement('canvas');
    canvas.width = px;
    canvas.height = px;
    canvas.style.width = (px / ratio) + 'px';
    canvas.style.height = (px / ratio) + 'px';
    canvas.style.display = 'block';
    canvas.style.margin = '0 auto';
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', label ? ('QR code for ' + label) : 'QR code');

    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, px, px);
    ctx.fillStyle = '#000000';
    for (var r = 0; r < n; r++) {
      for (var c = 0; c < n; c++) {
        if (model.isDark(r, c)) ctx.fillRect((c + QUIET) * cell, (r + QUIET) * cell, cell, cell);
      }
    }

    if (label) drawLabel(ctx, String(label), n, cell, total, sizePx < 150);

    container.innerHTML = '';
    container.appendChild(canvas);
    return canvas;
  }

  window.ADIQR = { render: render };
})();
