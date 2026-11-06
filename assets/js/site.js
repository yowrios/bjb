/* BRYAN JFIT — comportamiento común de la web */
(function () {
  'use strict';
  var BJ = window.BJ || { base: '' };
  var api = BJ.base + '/api.php';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---- Menú ---- */
  var nav = $('#nav'), burger = $('#navBurger'), links = $('#navLinks');
  if (burger) burger.addEventListener('click', function () {
    var ab = links.classList.toggle('abierto');
    burger.setAttribute('aria-expanded', ab ? 'true' : 'false');
  });
  if (links) $$('a', links).forEach(function (a) { a.addEventListener('click', function () { links.classList.remove('abierto'); }); });
  function navScroll() { if (nav) nav.classList.toggle('solida', window.scrollY > 40); }
  window.addEventListener('scroll', navScroll, { passive: true }); navScroll();

  /* ---- Aparecer al hacer scroll y contadores ---- */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (ents) {
      ents.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.classList.add('visto');
        if (en.target.hasAttribute('data-contar')) contar(en.target);
        io.unobserve(en.target);
      });
    }, { threshold: .15 });
    $$('.aparece, [data-contar]').forEach(function (el) { io.observe(el); });
  } else { $$('.aparece').forEach(function (el) { el.classList.add('visto'); }); }
  function contar(el) {
    var txt = el.getAttribute('data-contar'), m = txt.match(/^(\D*)(\d+)(.*)$/);
    if (!m) return;
    var fin = parseInt(m[2], 10), t0 = performance.now(), dur = 1400;
    (function paso(t) {
      var p = Math.min(1, (t - t0) / dur), v = Math.round(fin * (1 - Math.pow(1 - p, 3)));
      el.innerHTML = m[1] + v + '<em>' + m[3] + '</em>';
      if (p < 1) requestAnimationFrame(paso);
    })(t0);
  }

  /* ---- Estadísticas: clics importantes y tiempo en la página ---- */
  function enviar(datos) {
    datos.csrf = BJ.csrf;
    var body = new Blob([JSON.stringify(datos)], { type: 'application/json' });
    if (navigator.sendBeacon) navigator.sendBeacon(api + '?a=ev', body);
    else fetch(api + '?a=ev', { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' });
  }
  window.bjEvento = function (tipo, ref, valor) { enviar({ tipo: tipo, ref: ref || location.pathname, valor: valor || '' }); };
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-ev]');
    if (!a) return;
    var p = a.getAttribute('data-ev').split(':');
    window.bjEvento(p[0], location.pathname, p.slice(1).join(':'));
  });
  var inicio = Date.now(), enviado = false;
  function salir() {
    if (enviado || !BJ.visita) return;
    enviado = true;
    enviar({ tipo: '_duracion', visita: BJ.visita, seg: Math.round((Date.now() - inicio) / 1000) });
  }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') salir(); });
  window.addEventListener('pagehide', salir);
  if (BJ.visita) setTimeout(function () { fetch(api + '?a=geo', { credentials: 'same-origin' }).catch(function () {}); }, 2500);

  /* ---- Embebidos de Instagram y TikTok (se cargan solo al acercarse) ---- */
  var redes = $('#redesGrid');
  if (redes) {
    var cargadas = false;
    var cargar = function () {
      if (cargadas) return; cargadas = true;
      if ($('.instagram-media', redes)) { var s = document.createElement('script'); s.async = true; s.src = 'https://www.instagram.com/embed.js'; document.body.appendChild(s); }
      if ($('.tiktok-embed', redes)) { var t = document.createElement('script'); t.async = true; t.src = 'https://www.tiktok.com/embed.js'; document.body.appendChild(t); }
    };
    if ('IntersectionObserver' in window) {
      var ior = new IntersectionObserver(function (en) { if (en[0].isIntersecting) { cargar(); ior.disconnect(); } }, { rootMargin: '600px' });
      ior.observe(redes);
    } else cargar();
  }

  /* ---- Antes y después ---- */
  $$('.antes-despues').forEach(function (c) {
    var r = $('input', c), d = $('.despues', c), div = $('.divisor', c);
    if (!r) return;
    r.addEventListener('input', function () { d.style.clipPath = 'inset(0 0 0 ' + r.value + '%)'; div.style.left = r.value + '%'; });
  });

  /* ---- Copiar al portapapeles ---- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copiar]');
    if (!b) return;
    var v = b.getAttribute('data-copiar');
    (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () {
      var o = b.textContent; b.textContent = '¡Copiado!'; setTimeout(function () { b.textContent = o; }, 1500);
    }).catch(function () { window.prompt('Copia este dato:', v); });
  });

  /* ---- Vista previa de archivos ---- */
  $$('input[type=file][data-previa]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var dest = document.getElementById(inp.getAttribute('data-previa'));
      if (!dest) return;
      dest.innerHTML = '';
      Array.prototype.slice.call(inp.files || []).forEach(function (f) {
        if (/^image\//.test(f.type)) { var im = document.createElement('img'); im.src = URL.createObjectURL(f); dest.appendChild(im); }
        else { var p = document.createElement('p'); p.textContent = '📄 ' + f.name; dest.appendChild(p); }
      });
    });
  });
  $$('.foto-slot input').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var slot = inp.closest('.foto-slot'), f = inp.files && inp.files[0];
      var im = $('img', slot); if (im) im.remove();
      if (f && /^image\//.test(f.type)) { im = document.createElement('img'); im.src = URL.createObjectURL(f); slot.appendChild(im); }
    });
  });
  $$('.zona-archivo').forEach(function (z) {
    ['dragenter', 'dragover'].forEach(function (ev) { z.addEventListener(ev, function () { z.classList.add('sobre'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { z.addEventListener(ev, function () { z.classList.remove('sobre'); }); });
    var inp = $('input', z), et = $('.nombre-archivo', z);
    if (inp && et) inp.addEventListener('change', function () { et.textContent = inp.files.length ? inp.files[0].name : ''; });
  });

  /* ---- Modales ---- */
  window.bjModal = function (id, abrir) { var m = document.getElementById(id); if (m) m.classList.toggle('on', abrir !== false); };
  document.addEventListener('click', function (e) {
    var ab = e.target.closest('[data-modal]');
    if (ab) { e.preventDefault(); window.bjModal(ab.getAttribute('data-modal')); var f = ab.getAttribute('data-modal-valor'); if (f) { var c = document.getElementById(ab.getAttribute('data-modal') + 'Valor'); if (c) c.value = f; } return; }
    if (e.target.classList.contains('modal') || e.target.closest('.modal-cerrar')) { var m = e.target.closest('.modal'); if (m) m.classList.remove('on'); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') $$('.modal.on').forEach(function (m) { m.classList.remove('on'); }); });

  /* ---- Calculadoras ---- */
  var calc = $('#calculadoras');
  if (calc) {
    $$('.pestanas button', calc).forEach(function (b) {
      b.addEventListener('click', function () {
        $$('.pestanas button', calc).forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-selected', x === b); });
        $$('[data-panel]', calc).forEach(function (p) { p.classList.toggle('oculto', p.getAttribute('data-panel') !== b.getAttribute('data-tab')); });
        calcular();
      });
    });
    $$('input, select', calc).forEach(function (i) { i.addEventListener('input', calcular); i.addEventListener('change', calcular); });
    var maquinas = [];
    try { maquinas = JSON.parse(($('#datosMaquinas') || {}).textContent || '[]'); } catch (e) {}
    calcular();
  }
  function val(id) { var el = document.getElementById(id); return el ? parseFloat(String(el.value).replace(',', '.')) : NaN; }
  function sel(n) { var r = $('input[name="' + n + '"]:checked') || document.getElementById(n); return r ? r.value : ''; }
  function fmt(n) { return Math.round(n).toLocaleString('es-CO'); }
  function calcular() {
    if (!calc) return;
    var tab = ($('.pestanas button.on', calc) || {}).getAttribute ? $('.pestanas button.on', calc).getAttribute('data-tab') : 'imc';
    var peso = val('cPeso'), alt = val('cAltura'), edad = val('cEdad'), sexo = sel('cSexo') || 'M';
    var res = $('#calcRes');
    if (!(peso > 20 && peso < 350)) { res.innerHTML = '<p class="res-etiqueta">Ingresa tus datos</p><p class="sub">Escribe tu peso para ver el resultado al instante.</p>'; return; }
    if (tab === 'imc') {
      if (!(alt > 100 && alt < 250)) { res.innerHTML = '<p class="res-etiqueta">Falta tu estatura</p>'; return; }
      var imc = peso / Math.pow(alt / 100, 2), cat, pos = Math.min(100, Math.max(0, (imc - 15) / 25 * 100));
      cat = imc < 18.5 ? 'Bajo peso' : imc < 25 ? 'Peso saludable' : imc < 30 ? 'Sobrepeso' : 'Obesidad';
      var min = 18.5 * Math.pow(alt / 100, 2), max = 24.9 * Math.pow(alt / 100, 2);
      res.innerHTML = '<span class="res-etiqueta">Tu índice de masa corporal</span><div class="res-grande">' + imc.toFixed(1) + '<small>' + cat + '</small></div>' +
        '<div class="barra-imc"><i style="left:' + pos + '%"></i></div><div class="leyenda-imc"><span>15</span><span>18,5</span><span>25</span><span>30</span><span>40</span></div>' +
        '<div class="res-lista"><div><b>' + min.toFixed(0) + '–' + max.toFixed(0) + ' kg</b><span>Rango saludable</span></div><div><b>' + (peso - max > 0 ? '−' + (peso - max).toFixed(1) : '✓') + '</b><span>' + (peso - max > 0 ? 'kg sobre el rango' : 'Dentro del rango') + '</span></div></div>' +
        '<p class="aviso-salud">El IMC no distingue músculo de grasa. En la valoración medimos tu composición corporal real.</p>' + ctaCalc('IMC ' + imc.toFixed(1));
    } else if (tab === 'calorias') {
      if (!(alt > 100) || !(edad > 10)) { res.innerHTML = '<p class="res-etiqueta">Completa estatura y edad</p>'; return; }
      var tmb = 10 * peso + 6.25 * alt - 5 * edad + (sexo === 'F' ? -161 : 5);
      var act = parseFloat(sel('cActividad') || '1.55'), mant = tmb * act, obj = sel('cObjetivo') || 'mantener';
      var meta = obj === 'perder' ? mant * 0.82 : obj === 'ganar' ? mant * 1.1 : mant;
      var prot = peso * (obj === 'perder' ? 2.0 : 1.8), grasa = meta * 0.27 / 9, carb = Math.max(0, (meta - prot * 4 - grasa * 9) / 4);
      res.innerHTML = '<span class="res-etiqueta">Calorías diarias para ' + (obj === 'perder' ? 'perder grasa' : obj === 'ganar' ? 'ganar músculo' : 'mantenerte') + '</span>' +
        '<div class="res-grande">' + fmt(meta) + '<small>kcal/día</small></div>' +
        '<div class="res-lista"><div><b>' + fmt(prot) + ' g</b><span>Proteína</span></div><div><b>' + fmt(carb) + ' g</b><span>Carbohidratos</span></div><div><b>' + fmt(grasa) + ' g</b><span>Grasas</span></div><div><b>' + fmt(mant) + '</b><span>Mantenimiento</span></div></div>' +
        '<p class="aviso-salud">Estimación con la fórmula Mifflin-St Jeor. Es una guía general, no reemplaza una valoración profesional.</p>' + ctaCalc(fmt(meta) + ' kcal');
    } else {
      var id = sel('cMaquina'), m = maquinas.filter(function (x) { return String(x.id) === String(id); })[0];
      if (!m) { res.innerHTML = '<p class="res-etiqueta">Elige una máquina</p>'; return; }
      var c = window.bjCarga(m, peso, sexo, sel('cNivel') || 'principiante', sel('cMeta') || 'hipertrofia', edad || 30);
      if (!c) { res.innerHTML = '<p class="res-etiqueta">' + m.titulo + '</p><p>Este ejercicio se hace con tu peso corporal.</p>'; return; }
      res.innerHTML = '<span class="res-etiqueta">Carga sugerida · ' + m.titulo + '</span><div class="res-grande">' + c.kg + '<small>kg</small></div>' +
        '<div class="res-lista"><div><b>' + c.series + ' × ' + c.reps + '</b><span>Series × reps</span></div><div><b>' + c.descanso + ' s</b><span>Descanso</span></div><div><b>' + c.min + '–' + c.max + ' kg</b><span>Rango para ajustar</span></div><div><b>' + (m.nota || 'kg') + '</b><span>Qué se cuenta</span></div></div>' +
        '<p class="aviso-salud">Punto de partida. Si te sobran más de 3 repeticiones con buena técnica, sube; si no completas la serie, baja.</p>' +
        '<a class="btn btn-rojo btn-bloque" href="' + BJ.base + '/maquina.php?s=' + encodeURIComponent(m.slug) + '">Ver cómo se usa</a>';
    }
  }
  function ctaCalc(dato) {
    return '<button class="btn btn-rojo btn-bloque" type="button" data-modal="modalLead" data-modal-valor="Calculadora: ' + dato + '">Quiero un plan con estos datos</button>';
  }
  /* Misma fórmula que el servidor (inc/app.php: carga_sugerida) */
  window.bjCarga = function (v, peso, sexo, nivel, obj, edad) {
    var coef = sexo === 'F' ? (parseFloat(v.coef_mujer) || parseFloat(v.coef_hombre) * 0.7) : parseFloat(v.coef_hombre);
    if (!(coef > 0) || !(peso > 0)) return null;
    var fn = { principiante: .6, intermedio: 1, avanzado: 1.3 }[nivel] || .6;
    var o = { fuerza: [1.18, '5 a 6', 150], resistencia: [.75, '15 a 20', 45], grasa: [.82, '12 a 15', 45] }[obj] || [1, '10 a 12', 75];
    var fe = edad >= 60 ? .75 : edad >= 50 ? .85 : edad < 16 ? .7 : 1;
    var inc = Math.max(.5, parseFloat(v.incremento) || 2.5), kg = peso * coef * fn * o[0] * fe;
    var r = function (x) { return Math.max(inc, Math.round(x / inc) * inc); };
    return { kg: r(kg), min: r(kg * .85), max: r(kg * 1.1), reps: o[1], descanso: o[2], series: nivel === 'principiante' ? 3 : 4 };
  };

  /* ---- Formulario de contacto rápido (modal) ---- */
  $$('form[data-lead]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var b = $('button[type=submit]', f), fd = new FormData(f);
      fd.append('_csrf', BJ.csrf);
      b.disabled = true;
      fetch(api + '?a=lead', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        b.disabled = false;
        if (!j.ok) { alert(j.error || 'No se pudo enviar.'); return; }
        f.innerHTML = '<div class="msg msg-ok">' + (j.mensaje || '¡Listo! Bryan te escribirá muy pronto.') + '</div>' + (j.wa ? '<a class="btn btn-wa btn-bloque" target="_blank" rel="noopener" href="' + j.wa + '">Escribir ya por WhatsApp</a>' : '') + (j.descarga ? '<a class="btn btn-rojo btn-bloque mt1" href="' + j.descarga + '">Descargar ahora</a>' : '');
        if (j.descarga) location.href = j.descarga;
      }).catch(function () { b.disabled = false; alert('Sin conexión. Intenta de nuevo.'); });
    });
  });

  /* ---- Carrito de la tienda (se guarda en este celular) ---- */
  window.bjCarrito = {
    leer: function () { try { var c = JSON.parse(localStorage.getItem('bj_carrito') || '[]'); return Array.isArray(c) ? c : []; } catch (e) { return []; } },
    guardar: function (c) { try { localStorage.setItem('bj_carrito', JSON.stringify(c)); } catch (e) {} pintarCarrito(); }
  };
  function pintarCarrito() {
    var c = window.bjCarrito.leer(), n = c.reduce(function (s, x) { return s + x.cant; }, 0), b = $('#carritoBoton'), l = $('#carritoLista');
    if (!b) return;
    b.classList.toggle('on', n > 0); $('#carritoN').textContent = n;
    if (!l) return;
    var t = 0;
    l.innerHTML = c.map(function (x, i) {
      t += x.precio * x.cant;
      return '<div class="carrito-fila"><span>' + String(x.nombre).replace(/</g, '&lt;') + '</span><span class="cant"><button type="button" data-cant="' + i + ':-1">−</button>' + x.cant + '<button type="button" data-cant="' + i + ':1">+</button></span><b>$' + Math.round(x.precio * x.cant).toLocaleString('es-CO') + '</b></div>';
    }).join('') + '<div class="carrito-total"><span>Total</span><span>$' + Math.round(t).toLocaleString('es-CO') + '</span></div>';
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-agregar-carrito]');
    if (a) {
      var p = JSON.parse(a.getAttribute('data-agregar-carrito')), c = window.bjCarrito.leer(), ya = c.filter(function (x) { return x.id === p.id; })[0];
      if (ya) ya.cant++; else c.push({ id: p.id, nombre: p.nombre, precio: p.precio, cant: 1 });
      window.bjCarrito.guardar(c);
      var o = a.innerHTML; a.textContent = '✓ Agregado'; setTimeout(function () { a.innerHTML = o; }, 1200);
      return;
    }
    var q = e.target.closest('[data-cant]');
    if (q) {
      var pr = q.getAttribute('data-cant').split(':'), cc = window.bjCarrito.leer(), it = cc[+pr[0]];
      if (it) { it.cant += +pr[1]; if (it.cant <= 0) cc.splice(+pr[0], 1); }
      window.bjCarrito.guardar(cc);
    }
  });
  pintarCarrito();

  /* ---- Volver sin perder el lugar ----
     Guarda la posición de cada página y la recupera al regresar. */
  var claveScroll = 'bj_scroll:' + location.pathname + location.search;
  try { if ('scrollRestoration' in history) history.scrollRestoration = 'manual'; } catch (e) {}
  function guardarScroll() { try { sessionStorage.setItem(claveScroll, String(window.scrollY)); } catch (e) {} }
  window.addEventListener('pagehide', guardarScroll);
  document.addEventListener('click', function (e) { if (e.target.closest('a[href]')) guardarScroll(); }, true);
  (function restaurar() {
    var nav = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || {};
    var y = null; try { y = sessionStorage.getItem(claveScroll); } catch (e) {}
    if (y === null || location.hash || (nav.type && nav.type !== 'back_forward' && nav.type !== 'reload')) return;
    var html = document.documentElement, prev = html.style.scrollBehavior;
    var ir = function () { html.style.scrollBehavior = 'auto'; window.scrollTo(0, +y); html.style.scrollBehavior = prev; };
    ir(); window.addEventListener('load', ir); setTimeout(ir, 350);
  })();
  window.addEventListener('pageshow', function (e) { if (e.persisted) { /* volvió desde la memoria del navegador: ya está en su lugar */ } });
  // Botón «Volver» en las páginas internas cuando se llegó desde otra página de la web
  var vieneDeAqui = false;
  try { vieneDeAqui = document.referrer && new URL(document.referrer).origin === location.origin && document.referrer !== location.href; } catch (e) {}
  var migas = $('.migas');
  if (migas && vieneDeAqui && history.length > 1) {
    var vb = document.createElement('button'); vb.type = 'button'; vb.className = 'volver'; vb.innerHTML = '← Volver';
    vb.addEventListener('click', function () { history.back(); });
    migas.insertBefore(vb, migas.firstChild);
  }
  $$('[data-volver]').forEach(function (b) {
    b.addEventListener('click', function (e) { if (vieneDeAqui && history.length > 1) { e.preventDefault(); history.back(); } });
  });

  /* ---- Cronómetro flotante: se abre en una ventanita sin salir de la página ---- */
  var cf = $('#cronoFlotante');
  if (cf) {
    var panel = null, ifr = null, pill = $('#cronoPill'), tmr = null;
    var crear = function () {
      panel = document.createElement('div'); panel.className = 'crono-panel'; panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', 'Cronómetro');
      panel.innerHTML = '<div class="crono-panel-cab"><b>⏱ Cronómetro</b>' +
        '<a href="' + cf.getAttribute('data-completo') + '" title="Abrir en pantalla completa" aria-label="Pantalla completa"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg></a>' +
        '<button type="button" data-min title="Minimizar (sigue corriendo)" aria-label="Minimizar"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/></svg></button>' +
        '<button type="button" data-cerrar title="Cerrar" aria-label="Cerrar"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>';
      ifr = document.createElement('iframe'); ifr.src = cf.getAttribute('data-url'); ifr.title = 'Cronómetro'; ifr.allow = 'screen-wake-lock; fullscreen';
      panel.appendChild(ifr); document.body.appendChild(panel);
      panel.querySelector('[data-min]').addEventListener('click', function () { panel.hidden = true; });
      panel.querySelector('[data-cerrar]').addEventListener('click', function () {
        var corre = leer().corre;
        if (corre && !confirm('El cronómetro está corriendo. ¿Cerrarlo?')) return;
        panel.remove(); panel = null; ifr = null; pill.textContent = ''; cf.classList.remove('corriendo'); clearInterval(tmr);
      });
      tmr = setInterval(function () {
        var d = leer();
        cf.classList.toggle('corriendo', d.corre);
        pill.textContent = (panel && panel.hidden && d.iniciado) ? d.tiempo : '';
      }, 500);
    };
    var leer = function () {
      try { var doc = ifr.contentDocument, p = doc.getElementById('pantalla'); var f = p.dataset.fase;
        return { corre: f !== 'listo' && f !== 'fin' && !p.classList.contains('pausado'), iniciado: f !== 'listo' && f !== 'fin', tiempo: doc.getElementById('tiempo').textContent };
      } catch (e) { return { corre: false, iniciado: false, tiempo: '' }; }
    };
    cf.addEventListener('click', function () {
      if (!panel) { crear(); bjEvento('cronometro', location.pathname, 'flotante'); return; }
      panel.hidden = !panel.hidden;
    });
  }

  /* ---- App instalable (cronómetro sin conexión) ---- */
  if ('serviceWorker' in navigator && location.protocol !== 'file:') {
    window.addEventListener('load', function () { navigator.serviceWorker.register(BJ.base + '/sw.js').catch(function () {}); });
  }
})();
