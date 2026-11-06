/* Panel de Bryan: confirmaciones, filtros de tablas, filas dinámicas */
(function () {
  'use strict';
  // Confirmar antes de borrar o acciones delicadas
  document.addEventListener('submit', function (e) {
    var f = e.target, m = f.getAttribute('data-confirmar');
    if (m && !confirm(m)) e.preventDefault();
  });
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-confirmar]');
    if (a && !confirm(a.getAttribute('data-confirmar'))) e.preventDefault();
  });
  // Filtro instantáneo de tablas: <input data-filtrar="#idTabla">
  document.querySelectorAll('[data-filtrar]').forEach(function (inp) {
    var t = document.querySelector(inp.getAttribute('data-filtrar'));
    if (!t) return;
    var norm = function (s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    inp.addEventListener('input', function () {
      var q = norm(inp.value);
      t.querySelectorAll('tbody tr').forEach(function (tr) { tr.style.display = norm(tr.textContent).indexOf(q) >= 0 ? '' : 'none'; });
    });
  });
  // Filas dinámicas: <div data-filas="plantillaId"> + botón [data-agregar="contenedorId"]
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-agregar]');
    if (b) {
      var cont = document.getElementById(b.getAttribute('data-agregar')), tpl = document.getElementById(cont.getAttribute('data-plantilla'));
      var n = cont.querySelectorAll('[data-fila]').length + 1;
      var div = document.createElement('div'); div.innerHTML = tpl.innerHTML.replace(/__N__/g, 'n' + Date.now() + n).trim();
      var fila = div.firstElementChild;
      // Copiar el día de la fila anterior para agilizar
      var prev = cont.querySelectorAll('[data-fila]'), ult = prev[prev.length - 1];
      if (ult) { var d1 = ult.querySelector('[data-copiar-valor]'), d2 = fila.querySelector('[data-copiar-valor]'); if (d1 && d2) d2.value = d1.value; }
      cont.appendChild(fila);
      var foco = fila.querySelector('input, select'); if (foco) foco.focus();
      recalcular();
      return;
    }
    var q = e.target.closest('[data-quitar]');
    if (q) { var f = q.closest('[data-fila]'); if (f) f.remove(); recalcular(); return; }
    var s = e.target.closest('[data-subir]');
    if (s) { var fs = s.closest('[data-fila]'), pv = fs.previousElementSibling; if (pv && pv.hasAttribute('data-fila')) fs.parentNode.insertBefore(fs, pv); }
  });
  // Total de propuestas
  function recalcular() {
    var tot = document.getElementById('totalPropuesta'); if (!tot) return;
    var s = 0; document.querySelectorAll('[data-valor]').forEach(function (i) { s += parseFloat(String(i.value).replace(/\D/g, '')) || 0; });
    tot.value = s;
  }
  document.addEventListener('input', function (e) { if (e.target.hasAttribute('data-valor')) recalcular(); });
  // Mostrar/ocultar campos según el tipo
  document.querySelectorAll('[data-mostrar-si]').forEach(function (el) {
    var p = el.getAttribute('data-mostrar-si').split('='), ctl = document.querySelector('[name="' + p[0] + '"]');
    if (!ctl) return;
    var f = function () { el.classList.toggle('oculto', p[1].split('|').indexOf(ctl.value) < 0); };
    ctl.addEventListener('change', f); f();
  });
})();
