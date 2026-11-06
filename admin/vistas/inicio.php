<?php
if (es_post() && post('acc') === 'regalo') {
    csrf_verificar();
    evento('regalo_cumple', 'entregado', date('Y'));
    q('UPDATE eventos SET usuario_id = ? WHERE id = ?', [post_int('uid'), (int)db()->lastInsertId()]);
    flash('Regalo de cumpleaños marcado como entregado.');
    redirigir(url('admin/'));
}
$mes = date('Y-m');
$mesAnt = date('Y-m', strtotime('first day of last month'));
$ingMes = (float)valor("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE estado = 'aprobado' AND " . sql_mes('creado') . " = ?", [$mes]);
$ingAnt = (float)valor("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE estado = 'aprobado' AND " . sql_mes('creado') . " = ?", [$mesAnt]);
$activos = (int)valor("SELECT COUNT(DISTINCT usuario_id) FROM membresias WHERE estado = 'activa' AND (fin IS NULL OR fin >= ?)", [hoy()]);
$pend = filas("SELECT * FROM pagos WHERE estado = 'pendiente' ORDER BY id LIMIT 6");
$leadsN = (int)valor("SELECT COUNT(*) FROM leads WHERE estado = 'nuevo'");
$visHoy = (int)valor('SELECT COUNT(DISTINCT visitante) FROM visitas WHERE dia = ?', [hoy()]);
$vis7 = (int)valor('SELECT COUNT(DISTINCT visitante) FROM visitas WHERE dia >= ?', [date('Y-m-d', strtotime('-6 days'))]);
$wa7 = (int)valor("SELECT COUNT(*) FROM eventos WHERE tipo = 'whatsapp' AND fecha >= ?", [date('Y-m-d', strtotime('-6 days'))]);
$chk = (int)valor("SELECT COUNT(*) FROM checkins WHERE respuesta IS NULL OR respuesta = ''");
$porVencer = filas("SELECT m.*, u.nombre, u.telefono FROM membresias m JOIN usuarios u ON u.id = m.usuario_id WHERE m.estado = 'activa' AND m.fin BETWEEN ? AND ? ORDER BY m.fin", [hoy(), date('Y-m-d', strtotime('+7 days'))]);
$pocasSes = filas("SELECT m.*, u.nombre, u.telefono FROM membresias m JOIN usuarios u ON u.id = m.usuario_id WHERE m.estado = 'activa' AND m.sesiones_total > 0 AND m.sesiones_total - m.sesiones_usadas <= 2 ORDER BY m.fin");
$ultLeads = filas('SELECT * FROM leads ORDER BY id DESC LIMIT 6');
// Cumpleaños: desde hace 3 días hasta dentro de 7
$cumples = [];
foreach (filas("SELECT id, nombre, telefono, fecha_nacimiento FROM usuarios WHERE rol = 'cliente' AND activo = 1 AND fecha_nacimiento IS NOT NULL AND fecha_nacimiento <> ''") as $c) {
    if (!preg_match('/^\d{4}-(\d{2}-\d{2})$/', (string)$c['fecha_nacimiento'], $mm)) continue;
    for ($i = -3; $i <= 7; $i++) if (date('m-d', strtotime("$i days")) === $mm[1]) { $c['en'] = $i; $c['dado'] = (bool)valor("SELECT COUNT(*) FROM eventos WHERE tipo = 'regalo_cumple' AND usuario_id = ? AND valor = ?", [$c['id'], date('Y')]); $cumples[] = $c; break; }
}
usort($cumples, fn($a, $b) => $a['en'] <=> $b['en']);
$forms = filas('SELECT * FROM formularios WHERE revisado = 0 ORDER BY id DESC LIMIT 5');
// Gráficas
$meses = []; for ($i = 5; $i >= 0; $i--) $meses[date('Y-m', strtotime("first day of -$i month"))] = 0;
foreach (filas("SELECT " . sql_mes('creado') . " AS m, SUM(monto) AS t FROM pagos WHERE estado = 'aprobado' AND creado >= ? GROUP BY m", [array_key_first($meses) . '-01']) as $r) if (isset($meses[$r['m']])) $meses[$r['m']] = (float)$r['t'];
$dias = []; for ($i = 13; $i >= 0; $i--) $dias[date('Y-m-d', strtotime("-$i days"))] = 0;
foreach (filas('SELECT dia, COUNT(DISTINCT visitante) AS n FROM visitas WHERE dia >= ? GROUP BY dia', [array_key_first($dias)]) as $r) if (isset($dias[$r['dia']])) $dias[$r['dia']] = (int)$r['n'];
$nombresMes = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];

admin_cabecera('inicio', 'Hola, ' . explode(' ', usuario()['nombre'])[0]);
$var = $ingAnt > 0 ? round(($ingMes - $ingAnt) / $ingAnt * 100) : null;
?>
<div class="ad-kpis">
  <a class="ad-kpi" href="<?= url('admin/?p=reportes') ?>"><?= icono('money') ?><span>Ingresos de este mes</span><b><?= pesos($ingMes) ?></b><small class="<?= $var !== null ? ($var >= 0 ? 'sube' : 'baja') : '' ?>"><?= $var !== null ? ($var >= 0 ? '▲ ' : '▼ ') . abs($var) . '% vs. mes anterior' : 'Mes anterior: ' . pesos($ingAnt) ?></small></a>
  <a class="ad-kpi" href="<?= url('admin/?p=clientes&f=activos') ?>"><?= icono('users') ?><span>Clientes con plan activo</span><b><?= $activos ?></b><small><?= (int)valor("SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente'") ?> cuentas en total</small></a>
  <a class="ad-kpi <?= $pend ? 'alerta' : '' ?>" href="<?= url('admin/?p=pagos') ?>"><?= icono('clipboard') ?><span>Pagos por revisar</span><b><?= count($pend) ?></b><small>Apruébalos para activar accesos</small></a>
  <a class="ad-kpi <?= $leadsN ? 'alerta' : '' ?>" href="<?= url('admin/?p=leads') ?>"><?= icono('bell') ?><span>Interesados nuevos</span><b><?= $leadsN ?></b><small><?= count($forms) ?> formularios sin revisar</small></a>
  <a class="ad-kpi" href="<?= url('admin/?p=estadisticas') ?>"><?= icono('eye') ?><span>Visitantes (7 días)</span><b><?= $vis7 ?></b><small>Hoy: <?= $visHoy ?> · Clics a WhatsApp: <?= $wa7 ?></small></a>
  <a class="ad-kpi <?= $chk ? 'alerta' : '' ?>" href="<?= url('admin/?p=checkins') ?>"><?= icono('heart') ?><span>Check-ins sin responder</span><b><?= $chk ?></b><small>Responder motiva a tus clientes</small></a>
</div>

<div class="ad-rejilla">
  <div class="ad-caja"><h2><?= icono('money') ?> Ingresos últimos 6 meses</h2><div style="height:230px"><canvas id="gIng"></canvas></div></div>
  <div class="ad-caja"><h2><?= icono('eye') ?> Visitantes últimos 14 días</h2><div style="height:230px"><canvas id="gVis"></canvas></div></div>
</div>

<div class="ad-rejilla mt2">
  <div class="ad-caja">
    <h2><?= icono('calendar') ?> Para llamar esta semana <span class="der"><a href="<?= url('admin/?p=reportes#vencidos') ?>">Ver vencidos</a></span></h2>
    <?php foreach ($porVencer as $m): ?>
      <div class="fila-lista"><div><a href="<?= url('admin/?p=cliente&id=' . $m['usuario_id']) ?>"><b><?= e($m['nombre']) ?></b></a><br><span><?= e($m['nombre_plan']) ?> vence el <?= fecha_corta($m['fin']) ?></span></div>
        <a class="btn-icono wa" target="_blank" rel="noopener" href="<?= e(wa_link($m['telefono'], 'Hola ' . explode(' ', $m['nombre'])[0] . ', tu plan ' . $m['nombre_plan'] . ' vence el ' . fecha_larga($m['fin']) . '. ¿Lo renovamos para seguir con tu evolución? 💪')) ?>" title="Escribir por WhatsApp"><?= icono('whatsapp') ?></a></div>
    <?php endforeach; ?>
    <?php foreach ($pocasSes as $m): if (in_array($m['id'], array_column($porVencer, 'id'))) continue; ?>
      <div class="fila-lista"><div><a href="<?= url('admin/?p=cliente&id=' . $m['usuario_id']) ?>"><b><?= e($m['nombre']) ?></b></a><br><span>Le quedan <?= max(0, $m['sesiones_total'] - $m['sesiones_usadas']) ?> sesiones de <?= e($m['nombre_plan']) ?></span></div>
        <a class="btn-icono wa" target="_blank" rel="noopener" href="<?= e(wa_link($m['telefono'], 'Hola ' . explode(' ', $m['nombre'])[0] . ', te quedan pocas sesiones de tu plan. ¿Te reservo el siguiente mes? 🔥')) ?>"><?= icono('whatsapp') ?></a></div>
    <?php endforeach; ?>
    <?php if (!$porVencer && !$pocasSes): ?><p class="ad-vacio">Ningún plan vence en los próximos 7 días.</p><?php endif; ?>
  </div>
  <div class="ad-caja">
    <h2><?= icono('money') ?> Pagos por revisar <span class="der"><a href="<?= url('admin/?p=pagos') ?>">Ver todos</a></span></h2>
    <?php foreach ($pend as $x): ?>
      <div class="fila-lista"><div><b><?= e($x['nombre']) ?></b><br><span><?= e($x['concepto']) ?> · <?= pesos($x['monto']) ?> · <?= hace($x['creado']) ?></span></div><a class="btn btn-sm btn-rojo" href="<?= url('admin/?p=pagos#pago' . $x['id']) ?>">Revisar</a></div>
    <?php endforeach; ?>
    <?php if (!$pend): ?><p class="ad-vacio">Todo al día. 👌</p><?php endif; ?>
  </div>
</div>

<?php if ($cumples): $regalo = ajuste('regalo_cumple_activo', '1') === '1'; ?>
<div class="ad-caja mt2"><h2>🎂 Cumpleaños de tus clientes <span class="der"><?= $regalo ? 'Regalo activo: ' . e(mb_strimwidth((string)ajuste('regalo_cumple_texto'), 0, 60, '…')) : '<a href="' . url('admin/?p=temporadas#beneficios') . '">Activar regalo</a>' ?></span></h2>
  <?php foreach ($cumples as $c): $n1 = explode(' ', $c['nombre'])[0]; $cuando = $c['en'] === 0 ? '¡Hoy!' : ($c['en'] > 0 ? 'en ' . $c['en'] . ' día' . ($c['en'] > 1 ? 's' : '') : 'hace ' . abs($c['en']) . ' día' . (abs($c['en']) > 1 ? 's' : '')); ?>
    <div class="fila-lista"><div><a href="<?= url('admin/?p=cliente&id=' . $c['id']) ?>"><b><?= e($c['nombre']) ?></b></a> <?= $c['en'] === 0 ? '<span class="chip chip-volt">Hoy</span>' : '' ?><?= $c['dado'] ? '<span class="chip chip-ok">Regalo entregado</span>' : '' ?><br><span>Cumple <?= $cuando ?> · <?= edad($c['fecha_nacimiento']) + ($c['en'] > 0 ? 1 : 0) ?> años</span></div>
      <div class="ad-acciones"><a class="btn-icono wa" target="_blank" rel="noopener" href="<?= e(wa_link($c['telefono'], '¡Feliz cumpleaños, ' . $n1 . '! 🎂🎉 Te deseo un año lleno de salud y logros.' . ($regalo && !$c['dado'] ? ' Tu regalo: ' . ajuste('regalo_cumple_texto') . ' ¿Cuándo la agendamos?' : ''))) ?>" title="Felicitar por WhatsApp"><?= icono('whatsapp') ?></a>
        <?php if ($regalo && !$c['dado'] && $c['en'] <= 0): ?><form method="post"><?= csrf_campo() ?><input type="hidden" name="acc" value="regalo"><input type="hidden" name="uid" value="<?= (int)$c['id'] ?>"><button class="btn btn-xs btn-borde" type="submit">Regalo entregado</button></form><?php endif; ?></div></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="ad-rejilla mt2">
  <div class="ad-caja">
    <h2><?= icono('bell') ?> Últimos interesados <span class="der"><a href="<?= url('admin/?p=leads') ?>">Ver todos</a></span></h2>
    <?php foreach ($ultLeads as $l): ?>
      <div class="fila-lista"><div><b><?= e($l['nombre']) ?></b> <?= estado_chip($l['estado']) ?><br><span><?= e(ucfirst((string)$l['origen'])) ?><?= $l['interes'] ? ' · ' . e($l['interes']) : '' ?> · <?= hace($l['creado']) ?></span></div>
        <a class="btn-icono wa" target="_blank" rel="noopener" href="<?= e(wa_link($l['telefono'], 'Hola ' . explode(' ', (string)$l['nombre'])[0] . ', soy Bryan de ' . ajuste('marca') . '. Vi que te interesa entrenar conmigo. ¿Cuál es tu objetivo?')) ?>"><?= icono('whatsapp') ?></a></div>
    <?php endforeach; ?>
    <?php if (!$ultLeads): ?><p class="ad-vacio">Cuando alguien deje sus datos en la web aparecerá aquí.</p><?php endif; ?>
  </div>
  <div class="ad-caja">
    <h2><?= icono('clipboard') ?> Formularios iniciales sin revisar</h2>
    <?php foreach ($forms as $f): ?>
      <div class="fila-lista"><div><b><?= e($f['nombre']) ?></b><br><span><?= hace($f['creado']) ?></span></div><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=leads&formulario=' . $f['id']) ?>">Abrir</a></div>
    <?php endforeach; ?>
    <?php if (!$forms): ?><p class="ad-vacio">Sin formularios pendientes.</p><?php endif; ?>
    <div class="mt1"><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=manual') ?>"><?= icono('help') ?> ¿Cómo uso mi panel?</a></div>
  </div>
</div>
<?php
$sc = '<script>document.addEventListener("DOMContentLoaded",function(){if(!window.Chart)return;var r=getComputedStyle(document.documentElement).getPropertyValue("--rojo").trim(),g={color:"rgba(255,255,255,.06)"},t={color:"#a8a8b3"};Chart.defaults.color="#a8a8b3";'
    . 'new Chart(document.getElementById("gIng"),{type:"bar",data:{labels:' . json_encode(array_map(fn($m) => $nombresMes[substr($m, 5)], array_keys($meses))) . ',datasets:[{data:' . json_encode(array_values($meses)) . ',backgroundColor:r,borderRadius:8}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return "$"+c.raw.toLocaleString("es-CO")}}}},scales:{x:{grid:g,ticks:t},y:{grid:g,ticks:{color:"#a8a8b3",callback:function(v){return "$"+(v/1000)+"k"}}}}}});'
    . 'new Chart(document.getElementById("gVis"),{type:"line",data:{labels:' . json_encode(array_map(fn($d) => date('j/n', strtotime($d)), array_keys($dias))) . ',datasets:[{data:' . json_encode(array_values($dias)) . ',borderColor:r,backgroundColor:r+"33",fill:true,tension:.35}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:g,ticks:t},y:{grid:g,ticks:t,beginAtZero:true,precision:0}}}});});</script>';
admin_pie($sc);
