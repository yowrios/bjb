/* BRYAN JFIT — Cronómetro de entrenamiento (funciona sin conexión) */
(function () {
  'use strict';
  var C = window.CRONO || {}, BJ = window.BJ || { base: '' };
  var $ = function (id) { return document.getElementById(id); };
  var qa = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------- Configuración (se recuerda en este celular) ---------- */
  var cfg = { modo: 'series', series: 4, descanso: 90, trabajoSerie: 0, trabajo: 40, descansoInt: 20, rondas: 8, bloques: 1, descansoBloque: 60,
    cuentaMin: 5, cuentaSeg: 0, prep: 5, pausaAviso: C.pausa || 45, sonido: true, voz: true, vibrar: true, despierto: true };
  try { var g = JSON.parse(localStorage.getItem('bj_crono') || '{}'); for (var k in g) if (k in cfg) cfg[k] = g[k]; } catch (e) {}
  var P = new URLSearchParams(location.search);
  ['series', 'descanso', 'trabajo', 'rondas', 'bloques', 'descansoInt', 'trabajoSerie'].forEach(function (k) { if (P.has(k)) cfg[k] = parseInt(P.get(k), 10) || cfg[k]; });
  if (P.has('modo')) cfg.modo = P.get('modo');
  if (cfg.modo === 'rutina' && !C.logueado) cfg.modo = 'series';
  function guardar() { try { localStorage.setItem('bj_crono', JSON.stringify(cfg)); } catch (e) {} }

  var SUG_SERIES = [
    { t: 'Ganar músculo', d: '4 series · 90 s', series: 4, descanso: 90 },
    { t: 'Fuerza', d: '5 series · 3 min', series: 5, descanso: 180 },
    { t: 'Resistencia', d: '3 series · 45 s', series: 3, descanso: 45 },
    { t: 'Quemar grasa', d: '4 series · 40 s', series: 4, descanso: 40 },
    { t: 'Principiante', d: '3 series · 75 s', series: 3, descanso: 75 }
  ];
  var SUG_INT = [
    { t: 'Tabata', d: '20 s / 10 s × 8', trabajo: 20, descansoInt: 10, rondas: 8, bloques: 1, descansoBloque: 60 },
    { t: 'HIIT quema grasa', d: '40/20 × 8 × 3', trabajo: 40, descansoInt: 20, rondas: 8, bloques: 3, descansoBloque: 60 },
    { t: 'EMOM 10', d: '1 min × 10', trabajo: 60, descansoInt: 0, rondas: 10, bloques: 1, descansoBloque: 0 },
    { t: 'Principiante', d: '30/30 × 10', trabajo: 30, descansoInt: 30, rondas: 10, bloques: 1, descansoBloque: 0 },
    { t: 'Cardio largo', d: '3 min / 1 min × 5', trabajo: 180, descansoInt: 60, rondas: 5, bloques: 1, descansoBloque: 0 }
  ];

  /* ---------- Controles numéricos − valor + ---------- */
  qa('.cr-num').forEach(function (box) {
    var n = box.dataset.n, min = +box.dataset.min, max = +box.dataset.max, paso = +box.dataset.paso;
    box.innerHTML = '<button type="button" aria-label="Menos">−</button><input type="number" inputmode="numeric" aria-label="' + n + '"><button type="button" aria-label="Más">+</button>';
    var inp = box.querySelector('input'), b = box.querySelectorAll('button');
    inp.min = min; inp.max = max; inp.value = cfg[n];
    function fijar(v) { v = Math.max(min, Math.min(max, Math.round(v))); if (isNaN(v)) v = min; cfg[n] = v; inp.value = v; guardar(); cambioCfg(); }
    b[0].addEventListener('click', function () { fijar(cfg[n] - paso); });
    b[1].addEventListener('click', function () { fijar(cfg[n] + paso); });
    inp.addEventListener('change', function () { fijar(parseInt(inp.value, 10)); });
    box._fijar = function () { inp.value = cfg[n]; };
  });
  function refrescarNums() { qa('.cr-num').forEach(function (b) { b._fijar && b._fijar(); }); }
  qa('[data-op]').forEach(function (c) {
    c.checked = !!cfg[c.dataset.op];
    c.addEventListener('change', function () { cfg[c.dataset.op] = c.checked; guardar(); pintarSonido(); if (c.dataset.op === 'despierto') despertar(estado.corriendo); });
  });
  if (P.get('nombre')) $('nombreEj').value = P.get('nombre');

  function sugerencias(cont, lista) {
    cont.innerHTML = '';
    lista.forEach(function (s) {
      var b = document.createElement('button'); b.type = 'button'; b.innerHTML = s.t + '<small>' + s.d + '</small>';
      b.addEventListener('click', function () { for (var k in s) if (k in cfg) cfg[k] = s[k]; guardar(); refrescarNums(); qa('button', cont).forEach(function (x) { x.classList.toggle('on', x === b); }); cambioCfg(); });
      cont.appendChild(b);
    });
  }
  sugerencias($('sugSeries'), SUG_SERIES);
  sugerencias($('sugIntervalos'), SUG_INT);

  /* ---------- Modos ---------- */
  qa('.cr-modos button').forEach(function (b) { b.addEventListener('click', function () { if (estado.iniciado && !confirm('¿Cambiar de modo? Se reinicia el cronómetro.')) return; ponerModo(b.dataset.modo); }); });
  function ponerModo(m) {
    cfg.modo = m; guardar();
    qa('.cr-modos button').forEach(function (b) { b.classList.toggle('on', b.dataset.modo === m); });
    qa('[data-cfg]').forEach(function (d) { d.classList.toggle('oculto', d.dataset.cfg !== m); });
    if (m === 'rutina') cargarRutina(); else reiniciar();
  }

  /* ---------- Rutina asignada por Bryan ---------- */
  var rutina = null, rutinaEj = [];
  function cargarRutina() {
    var sel = $('selRutina');
    if (!sel) { reiniciar(); return; }
    if (P.get('rutina')) { sel.value = P.get('rutina'); }
    fetch(BJ.base + '/api.php?a=rutina&id=' + encodeURIComponent(sel.value), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) { $('listaRutina').innerHTML = '<p>' + (j.error || 'No se pudo cargar') + '</p>'; return; }
        rutina = j.rutina; rutinaEj = j.ejercicios;
        var dias = []; rutinaEj.forEach(function (e) { if (dias.indexOf(e.dia) < 0) dias.push(e.dia); });
        var sd = $('selDia'); sd.innerHTML = dias.map(function (d) { return '<option>' + esc(d || 'Rutina') + '</option>'; }).join('');
        if (P.get('dia') && dias.indexOf(P.get('dia')) >= 0) sd.value = P.get('dia');
        reiniciar();
      }).catch(function () { $('listaRutina').innerHTML = '<p>Sin conexión para cargar la rutina.</p>'; });
  }
  if ($('selRutina')) {
    $('selRutina').addEventListener('change', function () { P.delete('rutina'); cargarRutina(); });
    $('selDia').addEventListener('change', reiniciar);
  }
  function ejerciciosDelDia() { var d = $('selDia') ? $('selDia').value : ''; return rutinaEj.filter(function (e) { return (e.dia || 'Rutina') === d; }); }
  function pintarListaRutina() {
    var l = $('listaRutina'); if (!l) return;
    var ej = ejerciciosDelDia(), paso = plan[estado.idx] || {};
    l.innerHTML = ej.map(function (e, i) {
      var cls = (paso.ej === i && estado.iniciado) ? 'actual' : (estado.iniciado && paso.ej > i ? 'hecho' : '');
      return '<div class="' + cls + '"><span><b>' + esc(e.nombre) + '</b><br><small>' + e.series + ' × ' + esc(e.reps || '') + ' · desc. ' + fmt(e.descanso) + (e.carga ? ' · ' + esc(e.carga) : '') + '</small></span>' +
        (e.slug ? '<a href="' + BJ.base + '/maquina.php?s=' + encodeURIComponent(e.slug) + '" target="_blank" style="color:var(--rojo);font-size:12px">video</a>' : '') + '</div>';
    }).join('') || '<p>Este día no tiene ejercicios.</p>';
  }

  /* ---------- Plan de fases ---------- */
  var plan = [];
  function construir() {
    plan = [];
    var m = cfg.modo, prep = cfg.prep > 0 ? [{ tipo: 'prep', dur: cfg.prep, txt: 'Prepárate' }] : [];
    if (m === 'series') {
      plan = prep.slice();
      var nom = $('nombreEj').value.trim();
      for (var s = 1; s <= cfg.series; s++) {
        plan.push({ tipo: cfg.trabajoSerie > 0 ? 'trabajo' : 'serie', dur: cfg.trabajoSerie || null, txt: 'Serie ' + s, serie: s, totSeries: cfg.series, nombre: nom });
        if (s < cfg.series) plan.push({ tipo: 'descanso_serie', dur: cfg.descanso, txt: 'Descanso', serie: s, totSeries: cfg.series, nombre: nom, sig: 'Serie ' + (s + 1) + ' de ' + cfg.series });
      }
    } else if (m === 'intervalos') {
      plan = prep.slice();
      for (var b = 1; b <= cfg.bloques; b++) for (var r = 1; r <= cfg.rondas; r++) {
        plan.push({ tipo: 'trabajo', dur: cfg.trabajo, txt: 'Trabajo', ronda: r, totRondas: cfg.rondas, bloque: b, totBloques: cfg.bloques });
        var ultimaR = r === cfg.rondas, ultimoB = b === cfg.bloques;
        if (ultimaR && !ultimoB && cfg.descansoBloque > 0) plan.push({ tipo: 'descanso_serie', dur: cfg.descansoBloque, txt: 'Descanso largo', ronda: r, totRondas: cfg.rondas, bloque: b, totBloques: cfg.bloques, sig: 'Bloque ' + (b + 1) });
        else if (cfg.descansoInt > 0 && !(ultimaR && ultimoB)) plan.push({ tipo: 'descanso', dur: cfg.descansoInt, txt: 'Descanso', ronda: r, totRondas: cfg.rondas, bloque: b, totBloques: cfg.bloques, sig: 'Ronda ' + (ultimaR ? 1 : r + 1) });
      }
    } else if (m === 'rutina') {
      plan = prep.slice();
      var ej = ejerciciosDelDia();
      ej.forEach(function (e, i) {
        for (var s = 1; s <= Math.max(1, e.series); s++) {
          plan.push({ tipo: e.trabajo > 0 ? 'trabajo' : 'serie', dur: e.trabajo || null, txt: 'Serie ' + s, serie: s, totSeries: e.series, nombre: e.nombre, carga: e.carga, reps: e.reps, ej: i });
          var ultimo = (i === ej.length - 1 && s === e.series);
          if (!ultimo) {
            var sig = s < e.series ? (e.nombre + ' · serie ' + (s + 1)) : (ej[i + 1].nombre + ' · serie 1' + (ej[i + 1].carga ? ' · ' + ej[i + 1].carga : ''));
            plan.push({ tipo: 'descanso_serie', dur: e.descanso || 60, txt: 'Descanso', serie: s, totSeries: e.series, nombre: e.nombre, ej: i, sig: sig, registra: true });
          }
        }
      });
    } else if (m === 'libre') {
      plan = [{ tipo: 'libre', dur: null, txt: 'Cronómetro' }];
    } else if (m === 'cuenta') {
      plan = [{ tipo: 'trabajo', dur: Math.max(1, cfg.cuentaMin * 60 + cfg.cuentaSeg), txt: 'Cuenta regresiva' }];
    }
  }
  function resumenCfg() {
    var t = 0, txt = '';
    if (cfg.modo === 'series') { t = cfg.series * (cfg.trabajoSerie || 40) + (cfg.series - 1) * cfg.descanso; txt = cfg.series + ' series · descanso ' + fmt(cfg.descanso); }
    else if (cfg.modo === 'intervalos') { t = cfg.bloques * cfg.rondas * (cfg.trabajo + cfg.descansoInt) + (cfg.bloques - 1) * cfg.descansoBloque; txt = cfg.rondas + ' rondas de ' + fmt(cfg.trabajo) + ' / ' + fmt(cfg.descansoInt) + (cfg.bloques > 1 ? ' × ' + cfg.bloques + ' bloques' : ''); }
    else if (cfg.modo === 'rutina') { var ej = ejerciciosDelDia(); ej.forEach(function (e) { t += e.series * (e.trabajo || 40) + e.series * (e.descanso || 60); }); txt = ej.length + ' ejercicios' + (rutina ? ' · ' + rutina.nombre : ''); }
    else if (cfg.modo === 'cuenta') { t = cfg.cuentaMin * 60 + cfg.cuentaSeg; txt = 'Cuenta regresiva'; }
    else txt = 'Cuenta hacia arriba sin límite';
    $('resumenCfg').innerHTML = '<b style="color:var(--txt)">' + esc(txt) + '</b>' + (t ? '<br>Tiempo estimado: ' + Math.max(1, Math.round(t / 60)) + ' min' : '');
  }
  function cambioCfg() { if (!estado.iniciado) reiniciar(); else resumenCfg(); }

  /* ---------- Sonido, voz, vibración, notificaciones ---------- */
  var actx = null;
  function audio() { try { if (!actx) actx = new (window.AudioContext || window.webkitAudioContext)(); if (actx.state === 'suspended') actx.resume(); } catch (e) {} return actx; }
  function beep(f, d, v, tipo, retraso) {
    if (!cfg.sonido) return; var c = audio(); if (!c) return;
    var t = c.currentTime + (retraso || 0), o = c.createOscillator(), gn = c.createGain();
    o.type = tipo || 'sine'; o.frequency.value = f; gn.gain.setValueAtTime(0.0001, t);
    gn.gain.exponentialRampToValueAtTime(v || 0.35, t + 0.01); gn.gain.exponentialRampToValueAtTime(0.0001, t + d);
    o.connect(gn); gn.connect(c.destination); o.start(t); o.stop(t + d + 0.05);
  }
  var SON = {
    tic: function () { beep(880, 0.12, 0.3, 'square'); },
    trabajo: function () { beep(1320, 0.18, 0.4, 'square'); beep(1760, 0.35, 0.4, 'square', 0.2); },
    descanso: function () { beep(660, 0.5, 0.35, 'sine'); },
    pausa: function () { beep(520, 0.2, 0.4, 'triangle'); beep(520, 0.2, 0.4, 'triangle', 0.3); beep(390, 0.4, 0.4, 'triangle', 0.6); },
    fin: function () { [784, 988, 1175, 1568].forEach(function (f, i) { beep(f, 0.25, 0.4, 'square', i * 0.18); }); }
  };
  var voces = [];
  function cargarVoces() { voces = (window.speechSynthesis && speechSynthesis.getVoices()) || []; }
  if (window.speechSynthesis) { cargarVoces(); speechSynthesis.onvoiceschanged = cargarVoces; }
  function decir(t) {
    if (!cfg.voz || !window.speechSynthesis || !t) return;
    try {
      speechSynthesis.cancel();
      var u = new SpeechSynthesisUtterance(t); u.lang = 'es-CO'; u.rate = 1.05;
      var v = voces.filter(function (x) { return /^es(-|_)(CO|MX|US|419)/i.test(x.lang); })[0] || voces.filter(function (x) { return /^es/i.test(x.lang); })[0];
      if (v) u.voice = v;
      speechSynthesis.speak(u);
    } catch (e) {}
  }
  function vibrar(p) { if (cfg.vibrar && navigator.vibrate) try { navigator.vibrate(p); } catch (e) {} }
  function notificar(titulo, cuerpo) {
    if (!document.hidden || !('Notification' in window) || Notification.permission !== 'granted') return;
    var op = { body: cuerpo, tag: 'bj-crono', renotify: true, icon: BJ.base + '/assets/img/icono-192.png', vibrate: [300, 100, 300] };
    if (navigator.serviceWorker && navigator.serviceWorker.ready) navigator.serviceWorker.ready.then(function (r) { r.showNotification(titulo, op); }).catch(function () { try { new Notification(titulo, op); } catch (e) {} });
    else try { new Notification(titulo, op); } catch (e) {}
  }
  $('bNotif').addEventListener('click', function () {
    if (!('Notification' in window)) { alert('Este navegador no permite notificaciones.'); return; }
    Notification.requestPermission().then(function (p) { $('bNotif').textContent = p === 'granted' ? 'Activadas ✓' : 'Bloqueadas'; });
  });
  if ('Notification' in window && Notification.permission === 'granted') $('bNotif').textContent = 'Activadas ✓';
  function pintarSonido() { $('bSonido').classList.toggle('off', !cfg.sonido); $('bSonido').innerHTML = cfg.sonido ? ICO.vol : ICO.mute; }
  var ICO = { vol: $('bSonido').innerHTML, mute: '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h4l5-4v14l-5-4H4zM17 9l5 6M22 9l-5 6"/></svg>',
    play: $('bPlay').innerHTML, pause: '<svg class="ico" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4h4v16H7zM14 4h4v16h-4z"/></svg>' };
  $('bSonido').addEventListener('click', function () { cfg.sonido = !cfg.sonido; guardar(); pintarSonido(); var c = document.querySelector('[data-op=sonido]'); if (c) c.checked = cfg.sonido; if (cfg.sonido) SON.tic(); });
  pintarSonido();

  /* Pantalla encendida */
  var wl = null;
  function despertar(si) {
    if (!('wakeLock' in navigator)) return;
    if (si && cfg.despierto && !wl) navigator.wakeLock.request('screen').then(function (w) { wl = w; w.addEventListener('release', function () { wl = null; }); }).catch(function () {});
    if ((!si || !cfg.despierto) && wl) { wl.release().catch(function () {}); wl = null; }
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden && estado.corriendo) despertar(true); tick(); });

  /* ---------- Motor ---------- */
  var estado = {};
  function reiniciar() {
    estado = { idx: 0, iniciado: false, corriendo: false, faseIni: 0, acum: 0, pausaIni: 0, proxAviso: 0, avisosPausa: 0, activoIni: 0, activoAcum: 0, series: 0, vueltas: [], tics: {}, detalle: {}, sensacion: null, ultimaVuelta: 0 };
    construir(); despertar(false);
    $('pantalla').classList.remove('pausado');
    $('vueltas').innerHTML = '';
    $('registro').classList.remove('on');
    resumenCfg(); pintarListaRutina(); pintar();
  }
  function ahora() { return Date.now(); }
  function transcurrido() { return estado.acum + (estado.corriendo ? ahora() - estado.faseIni : 0); }
  function activo() { return estado.activoAcum + (estado.corriendo ? ahora() - estado.activoIni : 0); }
  function play() {
    audio();
    if (!plan.length) construir();
    if (!estado.iniciado) {
      if (cfg.modo === 'rutina' && !ejerciciosDelDia().length) { alert('Elige una rutina con ejercicios.'); return; }
      estado.iniciado = true; estado.corriendo = true; estado.idx = 0; estado.activoIni = ahora();
      entrar(true);
    } else if (estado.corriendo) {
      estado.acum = transcurrido(); estado.activoAcum = activo(); estado.corriendo = false; estado.pausaIni = ahora();
      estado.proxAviso = cfg.pausaAviso > 0 ? cfg.pausaAviso : Infinity; estado.avisosPausa = 0;
      $('pantalla').classList.add('pausado'); despertar(false); decir('Pausa');
    } else {
      estado.corriendo = true; estado.faseIni = ahora(); estado.activoIni = ahora();
      $('pantalla').classList.remove('pausado'); despertar(true);
      SON.tic(); decir('Continuamos');
    }
    pintar();
  }
  function entrar(primera) {
    var p = plan[estado.idx];
    estado.faseIni = ahora(); estado.acum = 0; estado.tics = {};
    if (!p) { terminar(); return; }
    despertar(true);
    var pant = $('pantalla'); pant.classList.remove('destello'); void pant.offsetWidth; pant.classList.add('destello');
    var voz = '', notif = '';
    if (p.tipo === 'trabajo') {
      SON.trabajo(); vibrar([200, 80, 200]);
      voz = p.ronda ? (p.ronda === p.totRondas && (!p.totBloques || p.bloque === p.totBloques) ? 'Última ronda' : 'Trabajo') : (p.serie ? (p.serie === p.totSeries ? 'Última serie' : 'Serie ' + p.serie) : 'Vamos');
      notif = '¡A trabajar!';
    } else if (p.tipo === 'serie') {
      SON.trabajo(); vibrar([200, 80, 200]);
      voz = (p.serie === p.totSeries ? 'Última serie' : 'Serie ' + p.serie) + (p.nombre && cfg.modo === 'rutina' && p.serie === 1 ? ', ' + p.nombre : '');
      notif = 'Descanso terminado: ' + (p.nombre ? p.nombre + ' · ' : '') + 'serie ' + p.serie;
    } else if (p.tipo === 'descanso' || p.tipo === 'descanso_serie') {
      SON.descanso(); vibrar(400);
      voz = 'Descanso'; notif = 'Descanso de ' + fmt(p.dur);
      if (p.registra) { $('registro').classList.add('on'); $('kgUsado').value = (estado.detalle[p.ej] && estado.detalle[p.ej].ultimo) || ''; }
    } else if (p.tipo === 'prep') { SON.tic(); voz = 'Prepárate'; }
    else if (p.tipo === 'libre') { SON.trabajo(); voz = 'Tiempo corriendo'; }
    if (p.tipo !== 'descanso_serie') $('registro').classList.remove('on');
    decir(voz);
    if (!primera && notif) notificar(notif, p.sig ? 'Siguiente: ' + p.sig : '');
    pintarListaRutina(); pintar();
  }
  function siguiente() {
    if (!estado.iniciado) return;
    var p = plan[estado.idx];
    if (p && (p.tipo === 'serie' || p.tipo === 'trabajo')) estado.series++;
    estado.idx++;
    if (estado.idx >= plan.length) { terminar(); return; }
    if (!estado.corriendo) { estado.corriendo = true; estado.activoIni = ahora(); $('pantalla').classList.remove('pausado'); }
    entrar(false);
  }
  function terminar() {
    estado.activoAcum = activo(); estado.corriendo = false; estado.iniciado = false; despertar(false);
    $('pantalla').dataset.fase = 'fin'; $('fase').textContent = '¡Terminaste!';
    $('tiempo').textContent = fmt(Math.round(estado.activoAcum / 1000)); $('tiempoSub').textContent = 'Tiempo total';
    $('arco').style.strokeDashoffset = 0; $('bPlay').innerHTML = ICO.play; $('registro').classList.remove('on');
    $('bHecho').classList.add('oculto'); $('bVuelta').classList.add('oculto');
    SON.fin(); vibrar([300, 100, 300, 100, 600]);
    decir('¡Terminaste! Excelente trabajo' + (C.nombre ? ', ' + C.nombre : ''));
    notificar('¡Terminaste tu entrenamiento!', 'Tiempo total ' + fmt(Math.round(estado.activoAcum / 1000)));
    var txt = 'Tiempo activo: <b>' + fmt(Math.round(estado.activoAcum / 1000)) + '</b>';
    if (estado.series) txt += ' · ' + (cfg.modo === 'intervalos' ? 'Rondas' : 'Series') + ': <b>' + estado.series + '</b>';
    $('finTexto').innerHTML = txt;
    $('finGuardar').classList.toggle('oculto', !C.logueado || cfg.modo === 'cuenta');
    $('fin').classList.add('on');
  }
  function tick() {
    if (estado.iniciado && estado.corriendo) {
      var p = plan[estado.idx];
      if (p && p.dur) {
        var resta = p.dur * 1000 - transcurrido();
        [3, 2, 1].forEach(function (s) { if (resta <= s * 1000 && resta > (s - 1) * 1000 && !estado.tics[s] && p.dur > 4) { estado.tics[s] = 1; SON.tic(); vibrar(60); } });
        if (resta <= 0) { siguiente(); return; }
      }
    } else if (estado.iniciado && !estado.corriendo) {
      var ps = Math.floor((ahora() - estado.pausaIni) / 1000);
      $('pausaSeg').textContent = ps;
      if (ps >= estado.proxAviso && estado.avisosPausa < 10) {
        estado.avisosPausa++; estado.proxAviso += cfg.pausaAviso;
        SON.pausa(); vibrar([500, 200, 500]);
        decir('Tu entrenamiento está en pausa. ¿Continuamos?');
        notificar('Tu entrenamiento está en pausa', 'Llevas ' + fmt(ps) + ' en pausa. Toca para continuar.');
      }
    }
    pintar();
  }
  function pintar() {
    var p = plan[estado.idx] || plan[0] || {};
    var pant = $('pantalla');
    if (!estado.iniciado && pant.dataset.fase === 'fin' && $('fin').classList.contains('on')) return;
    pant.dataset.fase = estado.iniciado ? p.tipo : 'listo';
    var el = transcurrido(), seg;
    if (p.dur) { var resta = Math.max(0, p.dur * 1000 - el); seg = Math.ceil(resta / 1000); $('arco').style.strokeDashoffset = (603.19 * (1 - resta / (p.dur * 1000))).toFixed(1); }
    else { seg = Math.floor(el / 1000); $('arco').style.strokeDashoffset = (603.19 * (1 - (el % 60000) / 60000)).toFixed(1); }
    if (!estado.iniciado) {
      var pri = plan.filter(function (x) { return x.tipo !== 'prep'; })[0] || p;
      seg = pri.dur ? pri.dur : 0; $('arco').style.strokeDashoffset = 0;
    }
    $('tiempo').textContent = fmt(seg);
    var nombres = { prep: 'Prepárate', trabajo: p.txt === 'Trabajo' ? 'Trabajo' : (p.txt || 'Trabajo'), serie: p.txt || 'Serie', descanso: 'Descanso', descanso_serie: p.txt || 'Descanso', libre: 'Cronómetro' };
    $('fase').textContent = estado.iniciado ? (nombres[p.tipo] || '') : 'Listo';
    $('tiempoSub').textContent = !estado.iniciado ? 'Presiona play' : (!estado.corriendo ? 'En pausa' : (p.dur ? 'Quedan' : (p.tipo === 'serie' ? 'En tu serie' : 'Transcurrido')));
    var ej = p.nombre || (cfg.modo === 'series' ? $('nombreEj').value.trim() : '');
    $('ejercicio').textContent = ej;
    $('carga').textContent = [p.reps ? p.reps + ' repeticiones' : '', p.carga || ''].filter(Boolean).join(' · ');
    var chips = [];
    if (p.totSeries) chips.push('Serie <b>' + p.serie + '/' + p.totSeries + '</b>');
    if (p.totRondas) chips.push('Ronda <b>' + p.ronda + '/' + p.totRondas + '</b>');
    if (p.totBloques > 1) chips.push('Bloque <b>' + p.bloque + '/' + p.totBloques + '</b>');
    if (estado.iniciado) chips.push('Total <b>' + fmt(Math.floor(activo() / 1000)) + '</b>');
    $('contador').innerHTML = chips.map(function (c) { return '<span class="chip">' + c + '</span>'; }).join('');
    $('siguiente').innerHTML = estado.iniciado && p.sig ? 'Siguiente: <b style="color:var(--txt)">' + esc(p.sig) + '</b>' : '';
    $('bPlay').innerHTML = estado.corriendo ? ICO.pause : ICO.play;
    $('bPlay').setAttribute('aria-label', estado.corriendo ? 'Pausar' : (estado.iniciado ? 'Continuar' : 'Iniciar'));
    $('bHecho').classList.toggle('oculto', !(estado.iniciado && p.tipo === 'serie'));
    $('bVuelta').classList.toggle('oculto', !(estado.iniciado && p.tipo === 'libre'));
  }
  function fmt(s) { s = Math.max(0, Math.round(s)); var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60; return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(x).padStart(2, '0'); }
  function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /* ---------- Botones ---------- */
  $('bPlay').addEventListener('click', play);
  $('bSaltar').addEventListener('click', function () { if (estado.iniciado) siguiente(); });
  $('bReiniciar').addEventListener('click', function () { if (estado.iniciado && !confirm('¿Reiniciar el cronómetro?')) return; reiniciar(); });
  $('bHecho').addEventListener('click', siguiente);
  $('bVuelta').addEventListener('click', function () {
    var t = transcurrido(), n = estado.vueltas.length + 1, parcial = t - estado.ultimaVuelta;
    estado.ultimaVuelta = t; estado.vueltas.push(t); SON.tic();
    var d = document.createElement('div'); d.innerHTML = '<span>Vuelta ' + n + '</span><span>' + fmt(parcial / 1000) + '</span><b>' + fmt(t / 1000) + '</b>';
    $('vueltas').prepend(d);
  });
  $('bKg').addEventListener('click', function () {
    var p = plan[estado.idx]; if (!p || p.ej == null) return;
    var kg = parseFloat($('kgUsado').value); if (!(kg >= 0)) return;
    var d = estado.detalle[p.ej] || (estado.detalle[p.ej] = { nombre: p.nombre, kg: [] });
    d.kg.push(kg); d.ultimo = kg; $('bKg').textContent = '✓';
    setTimeout(function () { $('bKg').textContent = 'Guardar'; }, 1200);
  });
  qa('#rapidos button').forEach(function (b) {
    b.addEventListener('click', function () {
      if (estado.iniciado && !confirm('¿Empezar un descanso rápido? Se reinicia lo que llevas.')) return;
      reiniciar(); plan = [{ tipo: 'descanso', dur: +b.dataset.s, txt: 'Descanso', sig: 'tu siguiente serie' }];
      play();
    });
  });
  qa('[data-cuenta]').forEach(function (b) { b.addEventListener('click', function () { var s = +b.dataset.cuenta; cfg.cuentaMin = Math.floor(s / 60); cfg.cuentaSeg = s % 60; guardar(); refrescarNums(); reiniciar(); }); });
  $('nombreEj').addEventListener('input', function () { if (!estado.iniciado) reiniciar(); });
  $('bEnfoque').addEventListener('click', function () {
    var on = document.body.classList.toggle('enfoque');
    try { if (on && document.documentElement.requestFullscreen) document.documentElement.requestFullscreen(); else if (!on && document.fullscreenElement) document.exitFullscreen(); } catch (e) {}
  });
  document.addEventListener('keydown', function (e) {
    if (e.target.matches('input, textarea, select')) return;
    if (e.code === 'Space') { e.preventDefault(); play(); }
    if (e.key === 'ArrowRight') siguiente();
  });
  qa('#caras button').forEach(function (b) { b.addEventListener('click', function () { estado.sensacion = +b.dataset.v; qa('#caras button').forEach(function (x) { x.classList.toggle('on', x === b); }); }); });
  $('bCerrarFin').addEventListener('click', function () { $('fin').classList.remove('on'); reiniciar(); });
  $('bGuardar').addEventListener('click', function () {
    var b = $('bGuardar'); b.disabled = true;
    var det = []; for (var k in estado.detalle) det.push(estado.detalle[k]);
    fetch(BJ.base + '/api.php?a=entreno', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: BJ.csrf, rutina_id: cfg.modo === 'rutina' && rutina ? rutina.id : 0, dia: cfg.modo === 'rutina' && $('selDia') ? $('selDia').value : cfg.modo,
        duracion: Math.round(estado.activoAcum / 1000), series: estado.series, sensacion: estado.sensacion, nota: $('finNota').value, detalle: det }) })
      .then(function (r) { return r.json(); }).then(function (j) { b.textContent = j.ok ? '¡Guardado en tu historial! ✓' : 'No se pudo guardar'; })
      .catch(function () { b.disabled = false; b.textContent = 'Sin conexión: intenta de nuevo'; });
  });

  /* Instalar como app */
  var instEv = null;
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); instEv = e; $('instalar').classList.add('on'); });
  $('bInstalar').addEventListener('click', function () { if (instEv) { instEv.prompt(); instEv = null; $('instalar').classList.remove('on'); } });

  /* Arranque */
  setInterval(tick, 200);
  ponerModo(cfg.modo);
})();
