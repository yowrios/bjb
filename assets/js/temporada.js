/* BRYAN JFIT — Decoración de temporadas (Navidad, Halloween, Día de la madre...)
   Todo es decorativo: no bloquea clics, se pausa cuando la pestaña no está visible
   y respeta a quien tiene activado «reducir movimiento». */
(function () {
  'use strict';
  var T = window.BJ_TEMP || {}, K = T.clave;
  if (!K) return;
  var movil = window.innerWidth < 700;
  var quieto = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var rnd = function (a, b) { return a + Math.random() * (b - a); };
  var elegir = function (l) { return l[Math.floor(Math.random() * l.length)]; };

  /* ---------- Tarjeta de saludo: aparece, y a los segundos se recoge en un botoncito ---------- */
  var sal = document.getElementById('saludo'), pill = document.getElementById('saludoPill'), CLAVE = 'bj_saludo_' + K, tRecoger = null;
  var ICONO = { navidad: '🎄', ano_nuevo: '🥂', san_valentin: '💘', mujer: '🌷', actividad: '⚡', nino: '🎈', madre: '💐', padre: '⭐', independencia: '<svg viewBox="0 0 30 20" width="30" height="20" style="border-radius:3px;vertical-align:-3px"><rect width="30" height="10" fill="#fcd116"/><rect y="10" width="30" height="5" fill="#003893"/><rect y="15" width="30" height="5" fill="#ce1126"/></svg>', amistad: '💛', octubre_rosa: '🎗️', halloween: '🎃', black_friday: '🏷️' };
  var ORN = {
    navidad: '<span style="left:18px;top:-14px">🎄</span><span style="right:56px;top:-16px">⭐</span><span style="left:46%;top:-18px;font-size:24px">🎁</span>',
    halloween: '<span style="left:16px;top:-18px">🎃</span><span style="right:60px;top:-18px">🦇</span>',
    mujer: '<span style="left:14px;top:-16px">🌸</span><span style="right:58px;top:-16px">🌷</span>',
    madre: '<span style="left:14px;top:-16px">💐</span><span style="right:58px;top:-16px">🌸</span>',
    san_valentin: '<span style="left:16px;top:-16px">💘</span><span style="right:58px;top:-16px">💕</span>',
    amistad: '<span style="left:16px;top:-16px">🎈</span><span style="right:58px;top:-16px">💛</span>',
    octubre_rosa: '<span style="left:16px;top:-16px">🎗️</span>', padre: '<span style="left:16px;top:-16px">🏆</span>',
    ano_nuevo: '<span style="left:16px;top:-16px">🥂</span><span style="right:58px;top:-16px">🎆</span>', independencia: '<span style="left:16px;top:-12px"><svg viewBox="0 0 30 20" width="30" height="20" style="border-radius:3px;vertical-align:-3px"><rect width="30" height="10" fill="#fcd116"/><rect y="10" width="30" height="5" fill="#003893"/><rect y="15" width="30" height="5" fill="#ce1126"/></svg></span><span style="right:58px;top:-12px"><svg viewBox="0 0 30 20" width="30" height="20" style="border-radius:3px;vertical-align:-3px"><rect width="30" height="10" fill="#fcd116"/><rect y="10" width="30" height="5" fill="#003893"/><rect y="15" width="30" height="5" fill="#ce1126"/></svg></span>',
    nino: '<span style="left:16px;top:-16px">🎈</span><span style="right:58px;top:-16px">🎉</span>', actividad: '<span style="left:16px;top:-16px">🏃</span>', black_friday: '<span style="left:16px;top:-16px">🏷️</span>'
  };
  if (sal) {
    var orn = sal.querySelector('.saludo-orn');
    if (orn && ORN[K]) { orn.innerHTML = ORN[K]; Array.prototype.forEach.call(orn.children, function (s) { s.style.position = 'absolute'; s.style.fontSize = s.style.fontSize || '28px'; }); }
    if (pill) pill.insertAdjacentHTML('afterbegin', (ICONO[K] || '✨') + ' ');
    var mostrar = function () {
      pill.hidden = true; pill.classList.remove('on'); sal.hidden = false;
      requestAnimationFrame(function () { requestAnimationFrame(function () { sal.classList.add('on'); }); });
      clearTimeout(tRecoger); tRecoger = setTimeout(recoger, 10000);
    };
    var recoger = function () {
      clearTimeout(tRecoger); sal.classList.remove('on');
      try { sessionStorage.setItem(CLAVE, '1'); } catch (e) {}
      setTimeout(function () { sal.hidden = true; pill.hidden = false; requestAnimationFrame(function () { pill.classList.add('on'); }); }, 650);
    };
    sal.querySelector('.saludo-cerrar').addEventListener('click', recoger);
    pill.addEventListener('click', mostrar);
    var visto = false; try { visto = sessionStorage.getItem(CLAVE) === '1'; } catch (e) {}
    if (visto) { sal.hidden = true; pill.hidden = false; pill.classList.add('on'); }
    else setTimeout(mostrar, 700);
  }
  if (T.efectos === false) return;

  /* ---------- Capa de decoración ---------- */
  var capa = document.createElement('div'); capa.className = 'tp-capa'; capa.setAttribute('aria-hidden', 'true');
  document.body.appendChild(capa);
  var poner = function (html, cls, estilo) { var d = document.createElement('div'); d.className = cls; if (estilo) d.style.cssText = estilo; d.innerHTML = html || ''; capa.appendChild(d); return d; };
  var esquina = function (html, cls) { document.body.classList.add('tp-con-esquina'); return poner(html, 'tp-esquina ' + cls); };

  /* Partículas */
  var activas = 0;
  var particula = function (tipo, colores, extra) {
    if (document.hidden || activas >= (movil ? 16 : 34)) return;
    var el = document.createElement(tipo === 'corazon' || tipo === 'estrella' ? 'span' : 'div'), dur, tam;
    el.className = 'tp-' + tipo;
    el.style.left = rnd(0, 100) + 'vw';
    el.style.setProperty('--dx', rnd(-90, 90) + 'px');
    el.style.setProperty('--rot', rnd(-720, 720) + 'deg');
    var c = elegir(colores || ['#fff']);
    if (tipo === 'nieve') { tam = rnd(3, 9); dur = rnd(9, 18); el.style.width = el.style.height = tam + 'px'; el.style.opacity = rnd(.45, .95); if (tam > 7) el.style.filter = 'blur(1px)'; }
    else if (tipo === 'petalo') { tam = rnd(10, 18); dur = rnd(9, 16); el.style.width = tam + 'px'; el.style.height = tam * .8 + 'px'; el.style.background = 'linear-gradient(135deg,' + c + ', rgba(255,255,255,.75))'; el.style.opacity = rnd(.7, .95); }
    else if (tipo === 'confeti') { dur = rnd(6, 11); el.style.width = rnd(6, 9) + 'px'; el.style.height = rnd(10, 15) + 'px'; el.style.background = c; el.style.opacity = rnd(.75, 1); }
    else if (tipo === 'estrella') { dur = rnd(10, 18); el.textContent = elegir(['✦', '✧', '⋆']); el.style.fontSize = rnd(10, 20) + 'px'; el.style.color = c; }
    else if (tipo === 'corazon') { tam = rnd(14, 30); dur = rnd(9, 15); el.style.width = el.style.height = tam + 'px'; el.style.setProperty('--op', rnd(.55, .9));
      el.innerHTML = '<svg viewBox="0 0 24 24"><path fill="' + c + '" d="M12 21s-7.5-4.6-9.8-9.2A5.6 5.6 0 0 1 12 5.9a5.6 5.6 0 0 1 9.8 5.9C19.5 16.4 12 21 12 21z"/></svg>'; }
    else if (tipo === 'globo') { dur = rnd(13, 20); el.style.background = c; el.style.borderBottomColor = c; el.style.setProperty('--op', .92); var s = rnd(.8, 1.25); el.style.width = 34 * s + 'px'; el.style.height = 42 * s + 'px'; }
    el.style.animationDuration = dur + 's';
    if (extra) extra(el);
    capa.appendChild(el); activas++;
    setTimeout(function () { el.remove(); activas--; }, dur * 1000 + 200);
  };
  var lluvia = function (tipo, colores, cadaMs, inicial) {
    if (quieto) return;
    for (var i = 0; i < (inicial || 0); i++) setTimeout(function () { particula(tipo, colores); }, i * 220);
    setInterval(function () { particula(tipo, colores); }, movil ? cadaMs * 1.7 : cadaMs);
  };

  /* Voladores que cruzan la pantalla */
  var volador = function (html, cls, opc) {
    if (quieto || document.hidden) return;
    opc = opc || {};
    var izq = opc.dir ? opc.dir === 'izq' : Math.random() < .5, dur = opc.dur || rnd(9, 14);
    var v = poner('<span class="vuelo">' + html + '</span>', 'tp-volador ' + cls, 'top:' + (opc.top || rnd(14, 44)) + 'vh;animation-name:tp-cruza-' + (izq ? 'izq' : 'der') + ';animation-duration:' + dur + 's');
    if (!izq) v.querySelector('.vuelo').style.transform = 'scaleX(-1)';
    if (opc.chispas) {
      var t = setInterval(function () {
        var r = v.getBoundingClientRect();
        if (r.right < -50 || r.left > window.innerWidth + 50) return;
        var c = document.createElement('span'); c.className = 'tp-chispa'; c.textContent = elegir(['✦', '✧', '·', '✨']);
        c.style.cssText = 'position:absolute;left:' + (izq ? r.right - 10 : r.left + 10) + 'px;top:' + (r.top + r.height * .6) + 'px;font-size:' + rnd(10, 18) + 'px';
        capa.appendChild(c); setTimeout(function () { c.remove(); }, 1600);
      }, 110);
      setTimeout(function () { clearInterval(t); }, dur * 1000);
    }
    setTimeout(function () { v.remove(); }, dur * 1000 + 300);
  };
  var cada = function (fn, primeroMs, cadaMs) { if (quieto) return; setTimeout(fn, primeroMs); setInterval(fn, cadaMs); };

  /* Guirnaldas bajo el menú (luces navideñas, banderines) */
  var guirnalda = function (tipo) {
    var g = poner('', 'tp-guirnalda');
    var dibujar = function () {
      var w = window.innerWidth, paso = movil ? 46 : 62, n = Math.ceil(w / paso) + 1, h = '', y0 = 4, col = tipo === 'luces' ? ['#ff3b3b', '#ffd34d', '#3bd16f', '#3ba7ff', '#ff7ad9'] : ['#fcd116', '#2b59c3', '#ce1126'];
      var cable = 'M0 ' + y0;
      for (var i = 0; i < n; i++) { var x1 = i * paso, x2 = x1 + paso; cable += ' Q' + (x1 + paso / 2) + ' ' + (y0 + 12) + ' ' + x2 + ' ' + y0; }
      h += '<path d="' + cable + '" fill="none" stroke="' + (tipo === 'luces' ? '#2c3a2c' : 'rgba(255,255,255,.6)') + '" stroke-width="1.6"/>';
      for (i = 0; i < n; i++) {
        var cx = i * paso + paso / 2, cy = y0 + 6, c = col[i % col.length];
        if (tipo === 'luces') h += '<rect x="' + (cx - 2.5) + '" y="' + (cy - 1) + '" width="5" height="4" fill="#555"/><ellipse class="foco" style="animation-delay:' + (-(i % 5) * .25) + 's" cx="' + cx + '" cy="' + (cy + 8) + '" rx="4.6" ry="7" fill="' + c + '" filter="drop-shadow(0 0 5px ' + c + ')"/>';
        else h += '<polygon points="' + (cx - 14) + ',' + (cy - 1) + ' ' + (cx + 14) + ',' + (cy - 1) + ' ' + cx + ',' + (cy + 20) + '" fill="' + c + '"/>';
      }
      g.innerHTML = '<svg viewBox="0 0 ' + w + ' 30" preserveAspectRatio="none">' + h + '</svg>';
    };
    dibujar(); var tr; window.addEventListener('resize', function () { clearTimeout(tr); tr = setTimeout(dibujar, 200); });
  };

  /* Fuegos artificiales (Año nuevo) */
  var fuegos = function () {
    if (quieto) return;
    var cv = document.createElement('canvas'); cv.className = 'tp-fuegos'; capa.appendChild(cv);
    var ctx = cv.getContext('2d'), P = [], dpr = Math.min(2, window.devicePixelRatio || 1);
    var ajustar = function () { cv.width = innerWidth * dpr; cv.height = innerHeight * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0); };
    ajustar(); window.addEventListener('resize', ajustar);
    var estallar = function () {
      if (document.hidden) return;
      var x = rnd(.1, .9) * innerWidth, y = rnd(.12, .45) * innerHeight, c = elegir(['#f5c542', '#ffffff', '#ff4d6d', '#7dd3fc', '#c084fc', '#4ade80']), n = movil ? 34 : 60;
      for (var i = 0; i < n; i++) { var a = Math.PI * 2 * i / n, v = rnd(1.4, 4.2); P.push({ x: x, y: y, vx: Math.cos(a) * v, vy: Math.sin(a) * v, vida: 1, c: c }); }
    };
    setInterval(estallar, movil ? 2200 : 1400); setTimeout(estallar, 600);
    (function cuadro() {
      ctx.clearRect(0, 0, innerWidth, innerHeight);
      for (var i = P.length - 1; i >= 0; i--) {
        var p = P[i]; p.x += p.vx; p.y += p.vy; p.vy += .045; p.vx *= .985; p.vida -= .012;
        if (p.vida <= 0) { P.splice(i, 1); continue; }
        ctx.globalAlpha = p.vida; ctx.fillStyle = p.c; ctx.beginPath(); ctx.arc(p.x, p.y, 2.1, 0, 7); ctx.fill();
      }
      ctx.globalAlpha = 1; requestAnimationFrame(cuadro);
    })();
  };

  /* Piezas dibujadas */
  var SVG = {
    arbol: '<svg viewBox="0 0 100 150"><defs><linearGradient id="tpPino" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#2f9a5f"/><stop offset="1" stop-color="#0b4d2c"/></linearGradient></defs>' +
      '<rect x="43" y="128" width="14" height="20" rx="2" fill="#6b3f1f"/><polygon points="50,16 78,62 22,62" fill="url(#tpPino)"/><polygon points="50,40 86,98 14,98" fill="url(#tpPino)"/><polygon points="50,68 95,134 5,134" fill="url(#tpPino)"/>' +
      '<path d="M30 56 Q50 68 72 54 M22 92 Q50 106 80 88 M13 128 Q50 142 88 122" stroke="#f4c95d" stroke-width="2" fill="none" opacity=".85"/>' +
      [[38, 50, '#ff3b3b'], [60, 48, '#3ba7ff'], [50, 62, '#ffd34d'], [30, 84, '#3bd16f'], [52, 90, '#ff7ad9'], [72, 82, '#ff3b3b'], [22, 118, '#ffd34d'], [42, 124, '#3ba7ff'], [64, 122, '#ff3b3b'], [82, 114, '#3bd16f']]
        .map(function (l, i) { return '<circle class="luz" style="animation-delay:' + (-i * .3) + 's" cx="' + l[0] + '" cy="' + l[1] + '" r="3.6" fill="' + l[2] + '" filter="drop-shadow(0 0 3px ' + l[2] + ')"/>'; }).join('') +
      '<polygon class="estrella-arbol" points="50,1 54,11 65,11 56,17 59,27 50,21 41,27 44,17 35,11 46,11" fill="#ffd34d"/></svg>',
    telarana: (function () {
      var o = [150, 0], rad = [], h = '<svg viewBox="0 0 150 150" fill="none" stroke="rgba(255,255,255,.75)" stroke-width=".8">';
      for (var i = 0; i <= 6; i++) { var a = Math.PI / 2 + i * (Math.PI / 2) / 6; rad.push(a); h += '<line x1="150" y1="0" x2="' + (150 + Math.cos(a) * 150) + '" y2="' + (Math.sin(a) * 150) + '"/>'; }
      [26, 52, 80, 110, 140].forEach(function (r) {
        var d = '';
        rad.forEach(function (a, j) { var x = 150 + Math.cos(a) * r, y = Math.sin(a) * r; if (!j) d = 'M' + x + ' ' + y; else { var am = (a + rad[j - 1]) / 2, rm = r * .86; d += ' Q' + (150 + Math.cos(am) * rm) + ' ' + (Math.sin(am) * rm) + ' ' + x + ' ' + y; } });
        h += '<path d="' + d + '"/>';
      });
      return h + '</svg>';
    })(),
    flores: function (col) {
      var f = function (cx, cy, r, c, d) {
        var p = '<g class="flor" style="animation-delay:' + d + 's">';
        for (var i = 0; i < 6; i++) p += '<ellipse cx="' + cx + '" cy="' + (cy - r) + '" rx="' + r * .55 + '" ry="' + r + '" fill="' + c + '" opacity=".92" transform="rotate(' + (i * 60) + ' ' + cx + ' ' + cy + ')"/>';
        return p + '<circle cx="' + cx + '" cy="' + cy + '" r="' + r * .45 + '" fill="#ffd34d"/></g>';
      };
      return '<svg viewBox="0 0 170 130"><path d="M40 130 Q42 90 50 70 M90 130 Q88 95 96 60 M128 130 Q130 104 140 88" stroke="#3f8f5a" stroke-width="3" fill="none"/>' +
        '<ellipse cx="34" cy="104" rx="16" ry="6" fill="#4caf72" transform="rotate(-30 34 104)"/><ellipse cx="104" cy="100" rx="16" ry="6" fill="#4caf72" transform="rotate(25 104 100)"/>' +
        f(50, 62, 15, col[0], 0) + f(96, 50, 19, col[1], -1.3) + f(140, 82, 13, col[2] || col[0], -2.4) + '</svg>';
    },
    lazo: '<svg viewBox="0 0 100 110"><path d="M28 104 L54 56 C64 36 62 10 50 10 C38 10 36 36 46 56 L72 104" stroke="#ff5fa2" stroke-width="15" stroke-linecap="round" stroke-linejoin="round" fill="none"/><path d="M47 30 C48 22 52 22 53 30" stroke="rgba(255,255,255,.6)" stroke-width="3" fill="none"/></svg>',
    corazon: '<svg viewBox="0 0 24 24"><path fill="#ff3d6e" d="M12 21s-7.5-4.6-9.8-9.2A5.6 5.6 0 0 1 12 5.9a5.6 5.6 0 0 1 9.8 5.9C19.5 16.4 12 21 12 21z"/></svg>'
  };

  /* ---------- Cada temporada ---------- */
  var santa = '<span>🦌</span><span>🦌</span><i class="rienda"></i><span class="trineo">🛷<b>🎅</b></span>';
  var escenas = {
    navidad: function () {
      lluvia('nieve', ['#fff'], 260, 14);
      esquina(SVG.arbol, 'tp-arbol');
      guirnalda('luces');
      cada(function () { volador(santa, 'tp-santa', { dir: 'izq', top: rnd(12, 26), dur: 13, chispas: true }); }, 3500, 26000);
    },
    halloween: function () {
      esquina('<span>🎃</span><span>🎃</span>', 'tp-calabazas');
      poner(SVG.telarana, 'tp-telarana');
      poner('<span>🕷️</span>', 'tp-arana', 'right:' + (movil ? 30 : 18) + '%');
      poner('', 'tp-niebla');
      cada(function () { var n = movil ? 2 : 3; for (var i = 0; i < n; i++) setTimeout(function () { volador('<span style="font-size:' + rnd(22, 38) + 'px">🦇</span>', 'tp-murcielago', { dur: rnd(7, 11) }); }, i * 700); }, 1500, 7000);
      lluvia('estrella', ['#c4a7ff', '#ffb46b'], 1400, 3);
    },
    mujer: function () { lluvia('petalo', ['#f5d0fe', '#e9d5ff', '#fbcfe8', '#ddd6fe'], 520, 8); esquina(SVG.flores(['#e879f9', '#c084fc', '#f9a8d4']), 'tp-flores'); },
    madre: function () { lluvia('petalo', ['#fbcfe8', '#fde2e4', '#fecdd3'], 560, 8); lluvia('corazon', ['#f472b6', '#fda4af'], 2600, 1); esquina('💐', 'tp-ramo'); },
    san_valentin: function () { lluvia('corazon', ['#ff3d6e', '#ff8fab', '#ffc2d1', '#e11d48'], 700, 6); esquina(SVG.corazon, 'tp-corazon-fijo'); cada(function () { volador('<span style="font-size:34px">💘</span>', 'tp-cupido', { top: rnd(16, 34) }); }, 5000, 20000); },
    amistad: function () { lluvia('corazon', ['#ff4d6d', '#ffd166', '#ff8fab'], 1100, 3); lluvia('globo', ['#ff4d6d', '#ffd166', '#4cc9f0', '#80ed99', '#c77dff'], 1700, 3); },
    octubre_rosa: function () { lluvia('petalo', ['#ff9cc6', '#ffd1e3', '#ff5fa2'], 650, 6); esquina(SVG.lazo, 'tp-lazo'); },
    padre: function () { lluvia('estrella', ['#e5c07b', '#ffe39a', '#93c5fd'], 700, 6); lluvia('confeti', ['#1e40af', '#3b82f6', '#e5c07b', '#cbd5e1'], 900, 4); },
    nino: function () { lluvia('globo', ['#ff595e', '#ffca3a', '#8ac926', '#1982c4', '#6a4c93'], 1300, 4); lluvia('confeti', ['#ff595e', '#ffca3a', '#8ac926', '#1982c4', '#6a4c93'], 450, 8); },
    actividad: function () { lluvia('confeti', ['#22c55e', '#facc15', '#ffffff'], 500, 8); cada(function () { volador('<span style="font-size:34px">🏃</span>', 'tp-corredor', { dir: 'izq', top: rnd(60, 80), dur: 7 }); }, 2500, 15000); },
    independencia: function () { guirnalda('banderines'); lluvia('confeti', ['#fcd116', '#fcd116', '#2b59c3', '#ce1126'], 420, 10); },
    ano_nuevo: function () { fuegos(); lluvia('confeti', ['#f5c542', '#fff4d6', '#c0c0c0'], 600, 6); },
    black_friday: function () {
      var tags = movil ? [12, 72] : [8, 26, 74, 90], txt = ['%', '-30%', 'OFERTA', '2x1'];
      tags.forEach(function (x, i) { var e = poner('<span>' + txt[i % txt.length] + '</span>', 'tp-etiqueta', 'left:' + x + '%;animation-delay:' + (-i * .7) + 's'); });
      lluvia('confeti', ['#ff2a2a', '#ffffff', '#111111'], 900, 4);
    }
  };
  if (escenas[K]) escenas[K]();
})();
