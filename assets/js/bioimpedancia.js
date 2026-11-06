/* Lector del PDF de bioimpedancia (reporte de Smart Fit). Se lee en el navegador con pdf.js
   y devuelve los datos ya ordenados. window.bjLeerBio(archivo) -> Promise<{ datos, texto }> */
(function () {
  'use strict';
  var PDFJS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';
  var cargando = null;
  function pdfjs() {
    if (window.pdfjsLib) return Promise.resolve(window.pdfjsLib);
    if (cargando) return cargando;
    cargando = new Promise(function (ok, mal) {
      var s = document.createElement('script'); s.src = PDFJS + 'pdf.min.js';
      s.onload = function () { window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS + 'pdf.worker.min.js'; ok(window.pdfjsLib); };
      s.onerror = function () { mal(new Error('No se pudo cargar el lector de PDF. Revisa tu conexión a internet.')); };
      document.head.appendChild(s);
    });
    return cargando;
  }
  function texto(archivo) {
    return pdfjs().then(function (lib) { return archivo.arrayBuffer().then(function (buf) { return lib.getDocument({ data: buf }).promise; }); })
      .then(function (pdf) {
        var paginas = [];
        for (var i = 1; i <= pdf.numPages; i++) paginas.push(pdf.getPage(i).then(function (p) { return p.getTextContent(); }));
        return Promise.all(paginas);
      })
      .then(function (cont) {
        return cont.map(function (c) { return c.items.map(function (it) { return it.str; }).filter(function (s) { return s.trim() !== ''; }).join('\n'); }).join('\n');
      });
  }
  var NUM = /-?\d+(?:[.,]\d+)?/g;
  var n = function (s) { if (s == null) return null; var v = parseFloat(String(s).replace(',', '.')); return isNaN(v) ? null : v; };
  function entre(t, desde, hasta) {
    var i = t.search(desde); if (i < 0) return '';
    var resto = t.slice(i), j = hasta ? resto.slice(1).search(hasta) : -1;
    return j < 0 ? resto : resto.slice(0, j + 1);
  }
  function ultimoNumero(s) { var m = s.match(NUM); return m ? n(m[m.length - 1]) : null; }

  function interpretar(t) {
    t = t.replace(/ /g, ' ').replace(/[ \t]+/g, ' ');
    var d = {}, m;
    if (!/bioimpedancia|Impedancia|Masa libre de grasa/i.test(t)) throw new Error('Este PDF no parece un resultado de bioimpedancia.');
    if ((m = t.match(/Nombre\s*\n\s*([^\n]+)/))) d.nombre = m[1].trim();
    if ((m = t.match(/Altura\s*\n\s*(\d+(?:[.,]\d+)?)\s*cm/i))) d.estatura_cm = n(m[1]);
    if ((m = t.match(/Edad\s*\n\s*(\d+)/))) d.edad = n(m[1]);
    if ((m = t.match(/Sexo\s*\n\s*(Masculino|Femenino|Hombre|Mujer)/i))) d.sexo = /^(m(asc)?|h)/i.test(m[1]) && !/^muj/i.test(m[1]) ? 'M' : 'F';
    if ((m = t.match(/Fecha\s*\n\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/))) d.fecha = m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2);
    // Análisis global: el valor de la persona es el último número de cada fila (los demás son la escala)
    var glob = entre(t, /An[áa]lisis Global/i, /Historial de la Composici/i);
    var filas = [['peso', /Peso real/], ['grasa_kg', /Masa de grasa/], ['masa_libre', /Masa libre de grasa/], ['agua', /Agua corporal/], ['imc', /\nIMC/]];
    filas.forEach(function (f, i) {
      var sig = filas[i + 1] ? filas[i + 1][1] : null, trozo = entre(glob, f[1], sig);
      if (trozo) d[f[0]] = ultimoNumero(trozo.replace(/\(kg\/m.?\)|\(kg\)|\(L\)/g, ''));
    });
    // Historial (meses anteriores que trae el mismo reporte)
    var hist = entre(t, /Historial de la Composici/i, /Control de Peso/i), fechas = hist.match(/\d{2}\.\d{2}\.\d{4}/g) || [];
    if (fechas.length) {
      var filaH = function (re) { var x = entre(hist, re, /\n(Peso|Masa libre de grasa|Masa de grasa|Masa muscular)\s*\n/); var v = (x.replace(re, '').replace(/\(kg\)/g, '').match(NUM) || []).slice(0, fechas.length); return v.map(n); };
      var hp = filaH(/\nPeso\s*\n/), hl = filaH(/Masa libre de grasa/), hg = filaH(/\nMasa de grasa/), hm = filaH(/Masa muscular esquel[ée]tica/);
      d.historial = fechas.map(function (f, i) { var p = f.split('.'); return { fecha: p[2] + '-' + p[1] + '-' + p[0], peso: hp[i], masa_libre: hl[i], grasa_kg: hg[i], musculo_esq: hm[i] }; });
      var ult = d.historial[d.historial.length - 1];
      if (ult && ult.musculo_esq != null && (!d.fecha || ult.fecha === d.fecha)) d.musculo_esq = ult.musculo_esq;
    }
    var dato = function (re) { var x = t.match(re); return x ? n(x[1]) : null; };
    d.peso_ideal = dato(/Peso ideal\s*\(kg\)\s*(-?[\d.,]+)/i);
    d.control_peso = dato(/Control de peso\s*\(kg\)\s*(-?[\d.,]+)/i);
    d.control_grasa = dato(/Control de grasa\s*\(kg\)\s*(-?[\d.,]+)/i);
    d.control_magra = dato(/Control de masa magra\s*\(kg\)\s*(-?[\d.,]+)/i);
    d.tmb = dato(/Tasa metab[óo]lica basal\s*\(kcal\)\s*([\d.,]+)/i);
    d.rangos = {};
    if ((m = t.match(/[ÍI]ndice apendicular\s*\([^)]*\)\s*([\d.,]+)\s*\(([^)]*)\)/i))) { d.indice_apendicular = n(m[1]); d.rangos.indice_apendicular = m[2]; }
    if ((m = t.match(/grasa visceral\s*([\d.,]+)\s*\(([^)]*)\)/i))) { d.visceral = n(m[1]); d.rangos.visceral = m[2]; }
    if ((m = t.match(/Porcentaje de grasa\s*\(%\)\s*([\d.,]+)\s*\(([^)]*)\)/i))) { d.grasa_pct = n(m[1]); d.rangos.grasa_pct = m[2]; }
    // Segmentos: masa magra y grasa por brazo, tronco y pierna
    var seg = function (bloque) {
      var r = {}, partes = [['bd', /Brazo derecho/], ['bi', /Brazo izquierdo/], ['tr', /Tronco/], ['pd', /Pierna derecha/], ['pi', /Pierna izquierda/]];
      partes.forEach(function (p) { var x = bloque.match(new RegExp(p[1].source + '\\s*([\\d.,]+)\\s*kg', 'i')); if (x) r[p[0]] = n(x[1]); });
      return r;
    };
    d.segmentos = { magra: seg(entre(t, /Masa Magra Segmentar/i, /Grasa Segmentar/i)), grasa: seg(entre(t, /Grasa Segmentar/i, null)) };
    // Impedancia (dato técnico, se guarda como viene)
    var imp = entre(t, /\nImpedancia/, /An[áa]lisis Global/i), filasImp = {};
    ['5 kHz', '50 kHz', '250 kHz'].forEach(function (k) { var x = imp.match(new RegExp(k + '\\s*((?:\\s*-?\\d+){5})')); if (x) filasImp[k] = (x[1].match(NUM) || []).map(n); });
    d.impedancia = filasImp;
    if (d.grasa_pct == null && d.grasa_kg != null && d.peso) d.grasa_pct = Math.round(d.grasa_kg / d.peso * 1000) / 10;
    if (!d.fecha || !d.peso) throw new Error('No encontré la fecha o el peso en el PDF. ¿Es el reporte de bioimpedancia completo?');
    return d;
  }
  window.bjLeerBio = function (archivo) {
    if (!archivo || !/pdf$/i.test(archivo.type || archivo.name)) return Promise.reject(new Error('El archivo debe ser un PDF.'));
    return texto(archivo).then(function (t) { return { datos: interpretar(t), texto: t }; });
  };
  window.bjNormaNombre = function (s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z ]/g, ' ').replace(/\s+/g, ' ').trim(); };
})();

/* Carga de un solo PDF (ficha del cliente y portal) y gráfica del comparativo */
(function () {
  'use strict';
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  document.querySelectorAll('input[data-bio-subir]').forEach(function (inp) {
    var est = document.querySelector(inp.getAttribute('data-estado'));
    inp.addEventListener('change', function () {
      var f = inp.files[0]; if (!f) return;
      est.innerHTML = '<span class="sec">Leyendo el reporte…</span>';
      window.bjLeerBio(f).then(function (r) {
        var d = r.datos, mio = inp.getAttribute('data-nombre') || '';
        if (mio) {
          var a = window.bjNormaNombre(mio).split(' '), b = window.bjNormaNombre(d.nombre || '').split(' '), com = a.filter(function (x) { return b.indexOf(x) >= 0; }).length;
          if (d.nombre && com < 2 && !confirm('El reporte es de «' + d.nombre + '» y no coincide con «' + mio + '». ¿Guardarlo de todas formas?')) { est.innerHTML = ''; inp.value = ''; return; }
        }
        est.innerHTML = '<div class="msg msg-ok" style="margin:10px 0">Encontré: <b>' + esc(d.nombre || '') + '</b> · ' + esc(d.fecha) + ' · peso ' + esc(d.peso) + ' kg · grasa ' + esc(d.grasa_pct) + ' % · músculo ' + esc(d.musculo_esq == null ? '—' : d.musculo_esq) + ' kg. Guardando…</div>';
        var fd = new FormData(); fd.append('_csrf', BJ.csrf); fd.append('pdf', f); fd.append('datos', JSON.stringify(d));
        if (inp.getAttribute('data-usuario')) fd.append('usuario_id', inp.getAttribute('data-usuario'));
        return fetch(BJ.base + '/bio_subir.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (x) { return x.json(); }).then(function (j) {
          if (!j.ok) { est.innerHTML = '<div class="msg msg-error" style="margin:10px 0">' + esc(j.error) + '</div>'; return; }
          est.innerHTML = '<div class="msg msg-ok" style="margin:10px 0">' + (j.estado === 'nuevo' ? '¡Listo! Bioimpedancia del ' + esc(j.fecha) + ' guardada.' : 'Esa fecha ya estaba: se actualizó.') + (j.historial ? ' También agregué ' + j.historial + ' meses del historial.' : '') + ' Actualizando el comparativo…</div>';
          setTimeout(function () { location.reload(); }, 1200);
        });
      }).catch(function (e) { est.innerHTML = '<div class="msg msg-error" style="margin:10px 0">' + esc(e.message || 'No se pudo leer el PDF.') + '</div>'; inp.value = ''; });
    });
  });
  var graficar = function () {
    if (!window.Chart) return;
    document.querySelectorAll('canvas[data-bio]').forEach(function (c) {
      var g = JSON.parse(c.getAttribute('data-bio')), rojo = getComputedStyle(document.documentElement).getPropertyValue('--rojo').trim() || '#ff2a2a', volt = getComputedStyle(document.documentElement).getPropertyValue('--volt').trim() || '#d4ff3a';
      new Chart(c, { type: 'line', data: { labels: g.fechas, datasets: [
        { label: 'Peso (kg)', data: g.peso, borderColor: '#e5e7eb', backgroundColor: '#e5e7eb', tension: .3, spanGaps: true },
        { label: 'Músculo esquelético (kg)', data: g.musculo, borderColor: volt, backgroundColor: volt, tension: .3, spanGaps: true },
        { label: 'Masa de grasa (kg)', data: g.grasa, borderColor: rojo, backgroundColor: rojo, tension: .3, spanGaps: true },
        { label: 'Masa libre de grasa (kg)', data: g.libre, borderColor: '#38bdf8', backgroundColor: '#38bdf8', tension: .3, spanGaps: true, hidden: true }] },
        options: { maintainAspectRatio: false, plugins: { legend: { labels: { color: '#d4d4d8', boxWidth: 12 } } }, scales: { x: { ticks: { color: '#a8a8b3' }, grid: { color: 'rgba(255,255,255,.06)' } }, y: { ticks: { color: '#a8a8b3' }, grid: { color: 'rgba(255,255,255,.06)' } } } } });
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', graficar); else setTimeout(graficar, 0);
})();
