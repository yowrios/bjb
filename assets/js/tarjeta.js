/* Tarjeta de presentación y QR: todo se genera en el navegador (jsPDF + qrcodejs). */
(function () {
  'use strict';
  var T = window.TJ;
  if (!T || !window.QRCode) { console.error('No cargó el generador de QR'); return; }
  var NIVEL = QRCode.CorrectLevel.H;
  new QRCode(document.getElementById('qrVista'), { text: T.enlace, width: 200, height: 200, correctLevel: NIVEL });
  new QRCode(document.getElementById('qrTarjeta'), { text: T.enlace, width: 160, height: 160, correctLevel: NIVEL });

  /* QR en alta resolución con margen blanco (para imprimir o compartir) */
  function qrGrande(px) {
    var div = document.createElement('div');
    new QRCode(div, { text: T.enlace, width: px, height: px, correctLevel: NIVEL });
    var c = div.querySelector('canvas'), m = Math.round(px * .08), out = document.createElement('canvas');
    out.width = out.height = px + m * 2;
    var x = out.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, out.width, out.height); x.drawImage(c, m, m);
    return out;
  }
  function cargar(src) {
    return new Promise(function (ok, mal) {
      var im = new Image(); im.onload = function () { var c = document.createElement('canvas'); c.width = im.naturalWidth; c.height = im.naturalHeight; c.getContext('2d').drawImage(im, 0, 0); ok({ url: c.toDataURL('image/png'), w: im.naturalWidth, h: im.naturalHeight }); };
      im.onerror = mal; im.src = src;
    });
  }
  function bajar(url, nombre) { var a = document.createElement('a'); a.href = url; a.download = nombre; document.body.appendChild(a); a.click(); a.remove(); }

  document.getElementById('bQr').addEventListener('click', function () { bajar(qrGrande(1024).toDataURL('image/png'), 'qr-' + T.archivo + '.png'); });

  /* Tarjeta 85 × 55 mm, frente y reverso */
  document.getElementById('bTarjeta').addEventListener('click', function () {
    var b = this; b.disabled = true;
    cargar(T.logo).then(function (logo) {
      var doc = new jspdf.jsPDF({ orientation: 'landscape', unit: 'mm', format: [85, 55] });
      var fondo = function () { doc.setFillColor('#0a0a0c'); doc.rect(0, 0, 85, 55, 'F'); doc.setFillColor(T.color); doc.rect(0, 0, 1.8, 55, 'F'); };
      // Frente
      fondo();
      var lw = 36, lh = lw * logo.h / logo.w; if (lh > 44) { lh = 44; lw = lh * logo.w / logo.h; }
      doc.addImage(logo.url, 'PNG', 4.5, (55 - lh) / 2, lw, lh);
      var x = 44;
      doc.setTextColor('#ffffff'); doc.setFont('helvetica', 'bold'); doc.setFontSize(11.5);
      var lineas = doc.splitTextToSize(T.nombre, 38); doc.text(lineas, x, 16);
      var y = 16 + lineas.length * 4.6;
      if (T.lema) { doc.setTextColor(T.color); doc.setFontSize(5.6); doc.text(T.lema, x, y, { charSpace: .45 }); y += 4.2; }
      doc.setTextColor('#bbbbbb'); doc.setFont('helvetica', 'normal'); doc.setFontSize(6.2); doc.text(doc.splitTextToSize(T.rol, 38), x, y);
      doc.setDrawColor(T.color); doc.setLineWidth(.3); doc.line(x, 37, 80, 37);
      doc.setTextColor('#dddddd'); doc.setFontSize(6.2);
      var cy = 41.5;
      doc.text('WhatsApp  ' + T.tel, x, cy); cy += 3.6;
      if (T.instagram) { doc.text('Instagram  @' + T.instagram, x, cy); cy += 3.6; }
      doc.text(T.web, x, cy);
      // Reverso
      doc.addPage([85, 55], 'landscape'); fondo();
      var q = qrGrande(600).toDataURL('image/png');
      doc.setFillColor('#ffffff'); doc.roundedRect(6, 8, 39, 39, 2.5, 2.5, 'F'); doc.addImage(q, 'PNG', 7, 9, 37, 37);
      doc.setTextColor('#ffffff'); doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.text('ESCANÉAME', 50, 22);
      doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor('#cccccc'); doc.text('y entrena conmigo', 50, 27);
      doc.setTextColor(T.color); doc.setFont('helvetica', 'bold'); doc.setFontSize(6); doc.text(doc.splitTextToSize(T.marca + (T.lema ? ' · ' + T.lema : ''), 31), 50, 34);
      if (T.lugar) { doc.setTextColor('#999999'); doc.setFont('helvetica', 'normal'); doc.setFontSize(5.5); doc.text(doc.splitTextToSize(T.lugar, 31), 50, 41); }
      doc.save('tarjeta-' + T.archivo + '.pdf');
    }).catch(function () { alert('No se pudo cargar el logo para la tarjeta.'); }).then(function () { b.disabled = false; });
  });

  /* Afiche A5 para el gimnasio */
  document.getElementById('bAfiche').addEventListener('click', function () {
    var b = this; b.disabled = true;
    cargar(T.logo).then(function (logo) {
      var doc = new jspdf.jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a5' }), W = 148, H = 210;
      doc.setFillColor('#0a0a0c'); doc.rect(0, 0, W, H, 'F');
      doc.setFillColor(T.color); doc.rect(0, 0, W, 3, 'F'); doc.rect(0, H - 3, W, 3, 'F');
      var lw = 52, lh = lw * logo.h / logo.w; doc.addImage(logo.url, 'PNG', (W - lw) / 2, 10, lw, lh);
      var y = 14 + lh;
      doc.setTextColor('#ffffff'); doc.setFont('helvetica', 'bold'); doc.setFontSize(26); doc.text('ENTRENA CONMIGO', W / 2, y + 8, { align: 'center' });
      doc.setFont('helvetica', 'normal'); doc.setFontSize(11); doc.setTextColor('#cccccc'); doc.text('Escanea el código y conoce los planes', W / 2, y + 16, { align: 'center' });
      var qs = 68, qx = (W - qs) / 2, qy = y + 22;
      doc.setFillColor('#ffffff'); doc.roundedRect(qx - 4, qy - 4, qs + 8, qs + 8, 4, 4, 'F'); doc.addImage(qrGrande(900).toDataURL('image/png'), 'PNG', qx, qy, qs, qs);
      y = qy + qs + 14;
      doc.setTextColor('#ffffff'); doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.text(T.nombre, W / 2, y, { align: 'center' });
      doc.setFont('helvetica', 'normal'); doc.setFontSize(10); doc.setTextColor('#bbbbbb'); doc.text('WhatsApp ' + T.tel + (T.instagram ? '   ·   @' + T.instagram : ''), W / 2, y + 7, { align: 'center' });
      if (T.lema) { doc.setTextColor(T.color); doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.text(T.lema, W / 2, H - 10, { align: 'center', charSpace: 1 }); }
      doc.save('afiche-' + T.archivo + '.pdf');
    }).catch(function () { alert('No se pudo cargar el logo.'); }).then(function () { b.disabled = false; });
  });
})();
