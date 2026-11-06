<?php
/** Reportes del negocio y reporte de evolución imprimible por cliente. */
if (!empty($_GET['cliente'])) {
    $c = fila("SELECT * FROM usuarios WHERE id = ? AND rol = 'cliente'", [(int)$_GET['cliente']]);
    if (!$c) redirigir(url('admin/?p=clientes'));
    $med = filas('SELECT * FROM medidas WHERE usuario_id = ? ORDER BY fecha, id', [$c['id']]);
    $fotos = filas('SELECT * FROM fotos WHERE usuario_id = ? ORDER BY fecha, id', [$c['id']]);
    $m = membresia_activa((int)$c['id']);
    $pri = $med[0] ?? null; $ult = $med ? end($med) : null;
    $desde = $c['cliente_desde'] ?: (valor('SELECT MIN(inicio) FROM membresias WHERE usuario_id = ?', [$c['id']]) ?: substr((string)$c['creado'], 0, 10));
    $ses = (int)valor('SELECT COUNT(*) FROM sesiones WHERE usuario_id = ?', [$c['id']]);
    $ent = (int)valor('SELECT COUNT(*) FROM entrenos WHERE usuario_id = ?', [$c['id']]);
    $porTipo = []; foreach ($fotos as $f) $porTipo[$f['tipo']][] = $f;
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reporte de evolución · <?= e($c['nombre']) ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;600;800&display=swap" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;600;800&display=swap"></noscript><link rel="stylesheet" href="<?= asset('css/site.css') ?>"><style><?= tema_css() ?>
    body{background:#fff;color:#111}.hoja{max-width:900px;margin:0 auto;padding:30px}.cab{display:flex;justify-content:space-between;align-items:center;background:#0a0a0c;color:#fff;padding:26px 30px;border-radius:18px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .cab h1{font-family:var(--display);font-weight:400;font-size:44px;margin:0;text-transform:uppercase}.k{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:20px 0}.k div{border:1px solid #ddd;border-radius:14px;padding:14px}.k b{display:block;font-size:26px}.k span{font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.08em}
    table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:8px;border-bottom:1px solid #e5e5e5;text-align:right}th:first-child,td:first-child{text-align:left}th{font-size:11px;text-transform:uppercase;color:#666}
    .fotos{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.fotos figure{margin:0}.fotos img{width:100%;aspect-ratio:3/4;object-fit:cover;border-radius:12px}.fotos figcaption{font-size:12px;color:#666;text-align:center;margin-top:4px}
    h2{font-size:18px;margin:28px 0 10px;color:#111}.diff{color:#16a34a;font-weight:800}.mas{color:#d97706;font-weight:800}@media print{.no-imprimir{display:none}.hoja{padding:0}}</style></head><body>
    <div class="hoja">
      <p class="no-imprimir" style="text-align:right"><button class="btn btn-rojo btn-sm" onclick="print()">Imprimir / guardar PDF</button></p>
      <div class="cab"><div><span style="color:var(--rojo);font-weight:800;letter-spacing:.2em;font-size:12px">REPORTE DE EVOLUCIÓN</span><h1><?= e($c['nombre']) ?></h1><span style="color:#aaa">Cliente desde el <?= fecha_larga($desde) ?> · <?= fecha_larga(hoy()) ?></span></div><img src="<?= imagen_marca('logo') ?>" alt="" width="100"></div>
      <div class="k">
        <div><span>Peso</span><b><?= $ult && $ult['peso'] ? (float)$ult['peso'] . ' kg' : '—' ?></b><?php if ($pri && $ult && $pri['peso'] && $ult['peso'] && $pri['id'] !== $ult['id']): $x = round($ult['peso'] - $pri['peso'], 1); ?><span class="<?= $x <= 0 ? 'diff' : 'mas' ?>"><?= $x > 0 ? '+' : '' ?><?= $x ?> kg</span><?php endif; ?></div>
        <div><span>% grasa</span><b><?= $ult && $ult['grasa'] ? (float)$ult['grasa'] . '%' : '—' ?></b><?php if ($pri && $ult && $pri['grasa'] && $ult['grasa'] && $pri['id'] !== $ult['id']): $x = round($ult['grasa'] - $pri['grasa'], 1); ?><span class="<?= $x <= 0 ? 'diff' : 'mas' ?>"><?= $x > 0 ? '+' : '' ?><?= $x ?> puntos</span><?php endif; ?></div>
        <div><span>Cintura</span><b><?= $ult && $ult['cintura'] ? (float)$ult['cintura'] . ' cm' : '—' ?></b><?php if ($pri && $ult && $pri['cintura'] && $ult['cintura'] && $pri['id'] !== $ult['id']): $x = round($ult['cintura'] - $pri['cintura'], 1); ?><span class="<?= $x <= 0 ? 'diff' : 'mas' ?>"><?= $x > 0 ? '+' : '' ?><?= $x ?> cm</span><?php endif; ?></div>
        <div><span>Sesiones · entrenos</span><b><?= $ses ?> · <?= $ent ?></b><span><?= $m ? e($m['nombre_plan']) : '' ?></span></div>
      </div>
      <?php if (count($med) > 1): ?><div style="height:260px"><canvas id="g"></canvas></div><?php endif; ?>
      <h2>Mediciones</h2>
      <table><thead><tr><th>Fecha</th><th>Peso</th><th>% grasa</th><th>% músculo</th><th>Cintura</th><th>Cadera</th><th>Abdomen</th><th>Pecho</th><th>Brazo</th><th>Pierna</th></tr></thead><tbody>
      <?php foreach ($med as $x): ?><tr><td><?= fecha_corta($x['fecha']) ?></td><?php foreach (['peso', 'grasa', 'musculo', 'cintura', 'cadera', 'abdomen', 'pecho', 'brazo', 'pierna'] as $k): ?><td><?= $x[$k] !== null ? (float)$x[$k] : '—' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
      </tbody></table>
      <?php foreach ($porTipo as $tipo => $lista): if (count($lista) < 1) continue; $a = $lista[0]; $b = end($lista); ?>
        <h2>Fotos · <?= e(ucfirst($tipo)) ?></h2>
        <div class="fotos"><figure><img src="<?= e(media_url('foto', (int)$a['id'])) ?>" alt=""><figcaption>Antes · <?= fecha_corta($a['fecha']) ?></figcaption></figure><?php if ($b['id'] !== $a['id']): ?><figure><img src="<?= e(media_url('foto', (int)$b['id'])) ?>" alt=""><figcaption>Ahora · <?= fecha_corta($b['fecha']) ?></figcaption></figure><?php endif; ?></div>
      <?php endforeach; ?>
      <p style="margin-top:30px;color:#666;font-size:13px"><?= e(ajuste('nombre_entrenador')) ?> · <?= e(ajuste('marca')) ?> · WhatsApp <?= e(ajuste('whatsapp')) ?></p>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>var el = document.getElementById('g'); if (el && window.Chart) new Chart(el, { type: 'line', data: { labels: <?= json_encode(array_map(fn($x) => fecha_corta($x['fecha']), $med)) ?>, datasets: [
      { label: 'Peso (kg)', data: <?= json_encode(array_map(fn($x) => $x['peso'] !== null ? (float)$x['peso'] : null, $med)) ?>, borderColor: '#e50914', tension: .3, spanGaps: true, yAxisID: 'y' },
      { label: 'Cintura (cm)', data: <?= json_encode(array_map(fn($x) => $x['cintura'] !== null ? (float)$x['cintura'] : null, $med)) ?>, borderColor: '#111', borderDash: [4, 4], tension: .3, spanGaps: true, yAxisID: 'y2' }] },
      options: { maintainAspectRatio: false, animation: false, scales: { y: { position: 'left' }, y2: { position: 'right', grid: { display: false } } } } });</script>
    </body></html><?php
    return;
}

$anio = (int)($_GET['anio'] ?? date('Y'));
$meses = []; for ($i = 1; $i <= 12; $i++) $meses[sprintf('%d-%02d', $anio, $i)] = ['ing' => 0, 'n' => 0, 'nuevos' => 0, 'ses' => 0];
foreach (filas("SELECT " . sql_mes('creado') . " AS m, SUM(monto) AS t, COUNT(*) AS n FROM pagos WHERE estado = 'aprobado' AND creado >= ? AND creado < ? GROUP BY m", ["$anio-01-01", ($anio + 1) . '-01-01']) as $r) if (isset($meses[$r['m']])) { $meses[$r['m']]['ing'] = (float)$r['t']; $meses[$r['m']]['n'] = (int)$r['n']; }
foreach (filas("SELECT " . sql_mes('primera') . " AS m, COUNT(*) AS n FROM (SELECT usuario_id, MIN(inicio) AS primera FROM membresias GROUP BY usuario_id) x WHERE primera >= ? AND primera < ? GROUP BY m", ["$anio-01-01", ($anio + 1) . '-01-01']) as $r) if (isset($meses[$r['m']])) $meses[$r['m']]['nuevos'] = (int)$r['n'];
foreach (filas("SELECT " . sql_mes('fecha') . " AS m, COUNT(*) AS n FROM sesiones WHERE fecha >= ? AND fecha < ? GROUP BY m", ["$anio-01-01", ($anio + 1) . '-01-01']) as $r) if (isset($meses[$r['m']])) $meses[$r['m']]['ses'] = (int)$r['n'];
$porConcepto = filas("SELECT concepto, COUNT(*) AS n, SUM(monto) AS t FROM pagos WHERE estado = 'aprobado' AND creado >= ? AND creado < ? GROUP BY concepto ORDER BY t DESC", ["$anio-01-01", ($anio + 1) . '-01-01']);
$porMetodo = filas("SELECT metodo, COUNT(*) AS n, SUM(monto) AS t FROM pagos WHERE estado = 'aprobado' AND creado >= ? AND creado < ? GROUP BY metodo ORDER BY t DESC", ["$anio-01-01", ($anio + 1) . '-01-01']);
$top = filas("SELECT u.id, u.nombre, SUM(p.monto) AS t, COUNT(*) AS n FROM pagos p JOIN usuarios u ON u.id = p.usuario_id WHERE p.estado = 'aprobado' GROUP BY u.id, u.nombre ORDER BY t DESC LIMIT 10");
$vencidos = filas("SELECT m.*, u.nombre, u.telefono FROM membresias m JOIN usuarios u ON u.id = m.usuario_id WHERE m.fin < ? AND m.fin >= ? AND NOT EXISTS (SELECT 1 FROM membresias m2 WHERE m2.usuario_id = m.usuario_id AND m2.inicio >= m.fin) AND NOT EXISTS (SELECT 1 FROM membresias m3 WHERE m3.usuario_id = m.usuario_id AND m3.estado = 'activa' AND m3.id <> m.id) ORDER BY m.fin DESC", [hoy(), date('Y-m-d', strtotime('-90 days'))]);
$totAnio = array_sum(array_column($meses, 'ing'));
$activos = (int)valor("SELECT COUNT(DISTINCT usuario_id) FROM membresias WHERE estado = 'activa'");
$ticket = ($n = array_sum(array_column($meses, 'n'))) ? $totAnio / $n : 0;
$exp = $_GET['exportar'] ?? '';
if ($exp === 'medidas') csv_descarga('medidas', ['Cliente', 'Fecha', 'Peso', '% grasa', '% músculo', 'Cintura', 'Cadera', 'Abdomen', 'Pecho', 'Brazo', 'Pierna', 'Registró'], array_map(fn($x) => [$x['nombre'], $x['fecha'], $x['peso'], $x['grasa'], $x['musculo'], $x['cintura'], $x['cadera'], $x['abdomen'], $x['pecho'], $x['brazo'], $x['pierna'], $x['por']], filas('SELECT m.*, u.nombre FROM medidas m JOIN usuarios u ON u.id = m.usuario_id ORDER BY u.nombre, m.fecha')));
if ($exp === 'mensual') csv_descarga('resumen-' . $anio, ['Mes', 'Ingresos', 'Pagos', 'Clientes nuevos', 'Sesiones'], array_map(fn($k, $v) => [$k, $v['ing'], $v['n'], $v['nuevos'], $v['ses']], array_keys($meses), $meses));
if ($exp === 'sesiones') csv_descarga('sesiones', ['Cliente', 'Fecha', 'Nota'], array_map(fn($x) => [$x['nombre'], $x['fecha'], $x['nota']], filas('SELECT s.*, u.nombre FROM sesiones s JOIN usuarios u ON u.id = s.usuario_id ORDER BY s.fecha DESC')));
admin_cabecera('reportes', 'Reportes ' . $anio);
$nm = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
?>
<div class="ad-barra">
  <select onchange="location.href='<?= url('admin/?p=reportes&anio=') ?>'+this.value"><?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 4; $y--): ?><option <?= $y === $anio ? 'selected' : '' ?>><?= $y ?></option><?php endfor; ?></select>
  <div class="der">
    <a class="btn btn-sm btn-borde" href="<?= url('admin/?p=reportes&anio=' . $anio . '&exportar=mensual') ?>"><?= icono('download') ?> Resumen mensual</a>
    <a class="btn btn-sm btn-borde" href="<?= url('admin/?p=pagos&e=aprobado&exportar=1') ?>"><?= icono('download') ?> Pagos</a>
    <a class="btn btn-sm btn-borde" href="<?= url('admin/?p=clientes&exportar=1') ?>"><?= icono('download') ?> Clientes</a>
    <a class="btn btn-sm btn-borde" href="<?= url('admin/?p=reportes&exportar=medidas') ?>"><?= icono('download') ?> Medidas</a>
    <a class="btn btn-sm btn-borde" href="<?= url('admin/?p=reportes&exportar=sesiones') ?>"><?= icono('download') ?> Sesiones</a>
    <button class="btn btn-sm btn-rojo" onclick="print()"><?= icono('print') ?> Imprimir</button>
  </div>
</div>
<div class="ad-kpis">
  <div class="ad-kpi"><?= icono('money') ?><span>Ingresos <?= $anio ?></span><b><?= pesos($totAnio) ?></b><small>Promedio mensual: <?= pesos($totAnio / max(1, $anio == date('Y') ? (int)date('n') : 12)) ?></small></div>
  <div class="ad-kpi"><?= icono('users') ?><span>Clientes nuevos <?= $anio ?></span><b><?= array_sum(array_column($meses, 'nuevos')) ?></b><small><?= $activos ?> con plan activo hoy</small></div>
  <div class="ad-kpi"><?= icono('dumbbell') ?><span>Sesiones dictadas <?= $anio ?></span><b><?= array_sum(array_column($meses, 'ses')) ?></b></div>
  <div class="ad-kpi"><?= icono('target') ?><span>Pago promedio</span><b><?= pesos($ticket) ?></b></div>
  <div class="ad-kpi <?= $vencidos ? 'alerta' : '' ?>"><?= icono('bell') ?><span>Vencieron sin renovar (90 días)</span><b><?= count($vencidos) ?></b><small>Escríbeles: son tus clientes más fáciles</small></div>
</div>
<div class="ad-caja"><h2><?= icono('chart') ?> Ingresos y clientes nuevos por mes</h2><div style="height:280px"><canvas id="gRep"></canvas></div></div>
<div class="ad-caja mt2 ad-tabla-envoltura"><table class="ad-tabla"><thead><tr><th>Mes</th><th class="num">Ingresos</th><th class="num">Pagos</th><th class="num">Clientes nuevos</th><th class="num">Sesiones</th></tr></thead><tbody>
  <?php foreach ($meses as $k => $v): ?><tr><td><?= $nm[(int)substr($k, 5) - 1] ?></td><td class="num"><?= pesos($v['ing']) ?></td><td class="num"><?= $v['n'] ?></td><td class="num"><?= $v['nuevos'] ?></td><td class="num"><?= $v['ses'] ?></td></tr><?php endforeach; ?>
  <tr><td><b>Total</b></td><td class="num"><b><?= pesos($totAnio) ?></b></td><td class="num"><b><?= array_sum(array_column($meses, 'n')) ?></b></td><td class="num"><b><?= array_sum(array_column($meses, 'nuevos')) ?></b></td><td class="num"><b><?= array_sum(array_column($meses, 'ses')) ?></b></td></tr></tbody></table></div>
<div class="ad-rejilla-3 mt2">
  <div class="ad-caja"><h2><?= icono('flame') ?> Por concepto</h2><?php foreach ($porConcepto as $r): ?><div class="ad-dato"><span><?= e($r['concepto']) ?> (<?= $r['n'] ?>)</span><span><?= pesos($r['t']) ?></span></div><?php endforeach; ?><?php if (!$porConcepto): ?><p class="ad-vacio">Sin pagos.</p><?php endif; ?></div>
  <div class="ad-caja"><h2><?= icono('money') ?> Por medio de pago</h2><?php foreach ($porMetodo as $r): ?><div class="ad-dato"><span><?= e($r['metodo'] ?: 'Sin dato') ?> (<?= $r['n'] ?>)</span><span><?= pesos($r['t']) ?></span></div><?php endforeach; ?><?php if (!$porMetodo): ?><p class="ad-vacio">Sin pagos.</p><?php endif; ?></div>
  <div class="ad-caja"><h2><?= icono('star') ?> Mejores clientes (histórico)</h2><?php foreach ($top as $r): ?><div class="ad-dato"><span><a href="<?= url('admin/?p=cliente&id=' . $r['id']) ?>"><?= e($r['nombre']) ?></a></span><span><?= pesos($r['t']) ?></span></div><?php endforeach; ?><?php if (!$top): ?><p class="ad-vacio">Sin datos.</p><?php endif; ?></div>
</div>
<div class="ad-caja mt2" id="vencidos"><h2><?= icono('bell') ?> Vencieron y no han renovado (últimos 90 días)</h2>
  <?php foreach ($vencidos as $v): ?><div class="fila-lista"><div><a href="<?= url('admin/?p=cliente&id=' . $v['usuario_id']) ?>"><b><?= e($v['nombre']) ?></b></a><br><span><?= e($v['nombre_plan']) ?> · venció <?= hace($v['fin'] . ' 23:59:59') ?></span></div>
    <a class="btn btn-wa btn-sm" target="_blank" rel="noopener" href="<?= e(wa_link($v['telefono'], 'Hola ' . explode(' ', $v['nombre'])[0] . ', ¡te extraño en los entrenos! 💪 ¿Retomamos tu evolución esta semana? Tengo un espacio para ti.')) ?>"><?= icono('whatsapp') ?> Invitar a volver</a></div><?php endforeach; ?>
  <?php if (!$vencidos): ?><p class="ad-vacio">Nadie pendiente por renovar. 💪</p><?php endif; ?></div>
<?php admin_pie('<script>document.addEventListener("DOMContentLoaded",function(){if(!window.Chart)return;var r=getComputedStyle(document.documentElement).getPropertyValue("--rojo").trim(),g={color:"rgba(255,255,255,.06)"},t={color:"#a8a8b3"};'
    . 'new Chart(document.getElementById("gRep"),{data:{labels:' . json_encode($nm) . ',datasets:[{type:"bar",label:"Ingresos",data:' . json_encode(array_column($meses, 'ing')) . ',backgroundColor:r,borderRadius:6,yAxisID:"y"},{type:"line",label:"Clientes nuevos",data:' . json_encode(array_column($meses, 'nuevos')) . ',borderColor:"#d4ff3a",backgroundColor:"#d4ff3a",yAxisID:"y2",tension:.3}]},options:{maintainAspectRatio:false,plugins:{legend:{labels:{color:"#d4d4d8"}}},scales:{x:{grid:g,ticks:t},y:{grid:g,ticks:{color:"#a8a8b3",callback:function(v){return "$"+(v/1000)+"k"}}},y2:{position:"right",grid:{display:false},ticks:{color:"#a8a8b3",precision:0},beginAtZero:true}}}});});</script>');
