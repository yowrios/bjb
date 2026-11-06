<?php
/** Cargar uno o varios PDF de bioimpedancia de Smart Fit (crea clientes nuevos o actualiza su progreso). */
require_once APP_ROOT . '/inc/bio.php';
$clientes = filas("SELECT id, nombre FROM usuarios WHERE rol = 'cliente' AND activo = 1 ORDER BY nombre");
$recientes = filas("SELECT b.*, u.nombre FROM bioimpedancias b JOIN usuarios u ON u.id = b.usuario_id WHERE b.origen = 'reporte' ORDER BY b.actualizado DESC LIMIT 15");
admin_cabecera('bio', 'Bioimpedancia Smart Fit');
?>
<div class="ad-nota">Arrastra aquí los PDF de bioimpedancia que entrega Smart Fit (puedes cargar varios a la vez). La web lee cada reporte, busca al cliente por su nombre y te muestra lo que encontró antes de guardar.
  Si el cliente no existe, lo crea. Si ya existe, agrega la nueva medición a su progreso y arma el comparativo. Tus medidas manuales no se tocan.</div>
<div class="ad-caja">
  <label class="zona-archivo" id="zonaBio"><?= icono('upload') ?><b>Toca o arrastra los PDF de bioimpedancia</b><br><span class="sec">Puedes elegir varios</span><input type="file" id="pdfsBio" accept="application/pdf" multiple></label>
  <label class="check mt2"><input type="checkbox" id="consBio" checked> <span>Tengo la autorización de estas personas para guardar sus datos de salud (necesario para crear clientes nuevos).</span></label>
  <div id="listaBio" class="mt2"></div>
  <div class="ad-acciones mt2 oculto" id="accBio" style="justify-content:flex-start"><button class="btn btn-rojo" type="button" id="guardarBio"><?= icono('check') ?> Guardar todo</button><span class="sec" id="estadoBio"></span></div>
</div>
<?php if ($recientes): ?>
<div class="ad-caja mt2 ad-tabla-envoltura"><h2><?= icono('scale') ?> Últimas cargadas</h2>
  <table class="ad-tabla"><thead><tr><th>Cliente</th><th>Fecha del reporte</th><th class="num">Peso</th><th class="num">% grasa</th><th class="num">Músculo</th><th>Cargó</th><th></th></tr></thead><tbody>
  <?php foreach ($recientes as $b): ?><tr><td><a href="<?= url('admin/?p=cliente&id=' . $b['usuario_id'] . '&t=bio') ?>"><b><?= e($b['nombre']) ?></b></a></td><td><?= fecha_corta($b['fecha']) ?></td><td class="num"><?= (float)$b['peso'] ?> kg</td><td class="num"><?= $b['grasa_pct'] !== null ? (float)$b['grasa_pct'] . ' %' : '—' ?></td><td class="num"><?= $b['musculo_esq'] !== null ? (float)$b['musculo_esq'] . ' kg' : '—' ?></td><td class="sec"><?= $b['por'] === 'cliente' ? 'El cliente' : 'Tú' ?> · <?= hace($b['actualizado']) ?></td><td><a class="btn btn-xs btn-borde" href="<?= url('admin/?p=cliente&id=' . $b['usuario_id'] . '&t=bio') ?>">Comparativo</a></td></tr><?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
<style>
.bio-item { display: grid; grid-template-columns: 1.3fr 1fr 1.5fr; gap: 14px; align-items: start; padding: 14px; border: 1px solid var(--linea); border-radius: 14px; margin-bottom: 10px; background: var(--negro); }
.bio-item .sec { font-size: 13px; }
.bio-item select, .bio-item input { padding: 9px 10px; font-size: 14px; }
.bio-res { margin-top: 6px; font-size: 13px; }
@media (max-width: 860px) { .bio-item { grid-template-columns: 1fr; } }
</style>
<?php
$sc = '<script src="' . asset('js/bioimpedancia.js') . '"></script><script>
(function () {
  var CLIENTES = ' . json_encode(array_map(fn($c) => ['id' => (int)$c['id'], 'nombre' => $c['nombre']], $clientes), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';
  var URL_SUBIR = ' . json_encode(url('bio_subir.php')) . ', URL_FICHA = ' . json_encode(url('admin/?p=cliente&id=')) . ';
  var items = [], lista = document.getElementById("listaBio");
  var esc = function (s) { return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;" }[c]; }); };
  var coincidir = function (nombre) {
    var n = bjNormaNombre(nombre), p = n.split(" "), ex = [], par = [];
    CLIENTES.forEach(function (c) { var cn = bjNormaNombre(c.nombre); if (cn === n) ex.push(c); else { var cp = cn.split(" "), com = p.filter(function (x) { return cp.indexOf(x) >= 0; }).length; if (com >= 2 && com >= Math.min(p.length, cp.length)) par.push(c); } });
    return ex.length ? ex : par;
  };
  var pintar = function (it) {
    var d = it.datos, sug = it.sugeridos || [];
    var ops = "<option value=\"\">— Elige el cliente —</option>" + (d ? "<option value=\"nuevo\"" + (!sug.length ? " selected" : "") + ">➕ Crear cliente nuevo: " + esc(d.nombre || "") + "</option>" : "");
    if (sug.length) ops += "<optgroup label=\"Coincide con el nombre\">" + sug.map(function (c, i) { return "<option value=\"" + c.id + "\"" + (i === 0 ? " selected" : "") + ">" + esc(c.nombre) + "</option>"; }).join("") + "</optgroup>";
    ops += "<optgroup label=\"Todos los clientes\">" + CLIENTES.map(function (c) { return "<option value=\"" + c.id + "\">" + esc(c.nombre) + "</option>"; }).join("") + "</optgroup>";
    it.el.innerHTML = it.error ? "<div><b>" + esc(it.archivo.name) + "</b><div class=\"msg msg-error\" style=\"margin:6px 0 0\">" + esc(it.error) + "</div></div>" :
      "<div><b>" + esc(d.nombre || "(sin nombre)") + "</b><br><span class=\"sec\">" + esc(it.archivo.name) + "</span></div>" +
      "<div class=\"sec\">Fecha: <b style=\"color:var(--txt)\">" + esc(d.fecha) + "</b><br>Peso " + esc(d.peso) + " kg · Grasa " + esc(d.grasa_pct) + " %<br>Músculo " + esc(d.musculo_esq == null ? "—" : d.musculo_esq) + " kg · " + (d.historial ? d.historial.length : 0) + " meses de historial</div>" +
      "<div><select data-dest>" + ops + "</select><div data-nuevo class=\"" + (sug.length ? "oculto" : "") + "\" style=\"display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px\"><input data-tel placeholder=\"WhatsApp (opcional)\"><input data-mail placeholder=\"Correo (opcional)\"></div><div class=\"bio-res\" data-res></div></div>";
    var s = it.el.querySelector("[data-dest]");
    if (s) s.addEventListener("change", function () { it.el.querySelector("[data-nuevo]").classList.toggle("oculto", s.value !== "nuevo"); });
  };
  document.getElementById("pdfsBio").addEventListener("change", function (e) {
    Array.prototype.forEach.call(e.target.files, function (f) {
      var it = { archivo: f, el: document.createElement("div") }; it.el.className = "bio-item"; it.el.innerHTML = "<span class=\"sec\">Leyendo " + esc(f.name) + "…</span>";
      lista.appendChild(it.el); items.push(it);
      bjLeerBio(f).then(function (r) { it.datos = r.datos; it.sugeridos = coincidir(r.datos.nombre || ""); pintar(it); }, function (er) { it.error = er.message; pintar(it); })
        .then(function () { document.getElementById("accBio").classList.toggle("oculto", !items.some(function (x) { return x.datos && !x.hecho; })); });
    });
    e.target.value = "";
  });
  document.getElementById("guardarBio").addEventListener("click", function () {
    var b = this, pend = items.filter(function (x) { return x.datos && !x.hecho; }), i = 0;
    b.disabled = true;
    var sig = function () {
      if (i >= pend.length) { b.disabled = false; document.getElementById("estadoBio").textContent = "Listo."; return; }
      var it = pend[i++], dest = it.el.querySelector("[data-dest]").value, res = it.el.querySelector("[data-res]");
      if (!dest) { res.innerHTML = "<span style=\"color:#fca5a5\">Elige el cliente.</span>"; return sig(); }
      var fd = new FormData(); fd.append("_csrf", BJ.csrf); fd.append("pdf", it.archivo); fd.append("datos", JSON.stringify(it.datos)); fd.append("usuario_id", dest);
      if (dest === "nuevo") { fd.append("telefono", it.el.querySelector("[data-tel]").value); fd.append("email", it.el.querySelector("[data-mail]").value); if (document.getElementById("consBio").checked) fd.append("consentimiento", "1"); }
      res.textContent = "Guardando…";
      fetch(URL_SUBIR, { method: "POST", body: fd, credentials: "same-origin" }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) { res.innerHTML = "<span style=\"color:#fca5a5\">" + esc(j.error) + "</span>"; return; }
        it.hecho = true;
        var txt = (j.estado === "nuevo" ? "✓ Guardada" : "✓ Actualizada (ya existía ese día)") + (j.historial ? " + " + j.historial + " meses de historial" : "") + " · <a href=\"" + URL_FICHA + j.cliente.id + "&t=bio\" style=\"color:var(--volt)\">Ver comparativo de " + esc(j.cliente.nombre) + "</a>";
        if (j.creado) txt += "<br><span style=\"color:var(--volt)\">Cliente nuevo creado. Contraseña temporal: <b>" + esc(j.creado.clave) + "</b> (también puedes cambiarla en su ficha).</span>";
        res.innerHTML = txt; it.el.querySelector("[data-dest]").disabled = true;
      }).catch(function () { res.innerHTML = "<span style=\"color:#fca5a5\">Sin conexión.</span>"; }).then(sig);
    };
    sig();
  });
})();
</script>';
admin_pie($sc);
