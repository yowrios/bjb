<?php
/** Estadísticas de visitas: quién, de dónde, cuándo, con qué y qué hizo. */
$d = in_array((int)($_GET['d'] ?? 30), [1, 7, 30, 90, 365], true) ? (int)($_GET['d'] ?? 30) : 30;
$desde = date('Y-m-d', strtotime('-' . ($d - 1) . ' days'));
$desdeT = $desde . ' 00:00:00';
$vis = filas('SELECT * FROM visitas WHERE dia >= ? ORDER BY id DESC', [$desde]);
if (isset($_GET['exportar'])) csv_descarga('visitas', ['Fecha', 'Página', 'Origen', 'Campaña', 'Dispositivo', 'Navegador', 'Sistema', 'País', 'Ciudad', 'Segundos', 'Visitante', 'Cliente'],
    array_map(fn($v) => [$v['fecha'], $v['ruta'], $v['ref'], $v['utm_campaign'], $v['dispositivo'], $v['navegador'], $v['so'], $v['pais'], $v['ciudad'], $v['duracion'], $v['visitante'], $v['usuario_id']], $vis));

function fuente(array $v): string {
    if ($v['utm_source']) return ucfirst(strtolower($v['utm_source']));
    $h = strtolower((string)parse_url((string)$v['ref'], PHP_URL_HOST));
    foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'fb.' => 'Facebook', 'tiktok' => 'TikTok', 'google' => 'Google', 'bing' => 'Bing', 'whatsapp' => 'WhatsApp', 'wa.me' => 'WhatsApp', 'youtube' => 'YouTube', 't.co' => 'X / Twitter', 'linktr' => 'Linktree'] as $k => $n)
        if ($h && str_contains($h, $k)) return $n;
    if (in_array($v['navegador'], ['Instagram', 'Facebook', 'TikTok'], true)) return $v['navegador'];
    return $h ? $h : 'Directo / WhatsApp';
}
$unicos = []; $porDia = []; $fuentes = []; $disp = []; $nav = []; $so = []; $ciud = []; $pags = []; $mapa = array_fill(0, 7, array_fill(0, 24, 0)); $dur = []; $primeraFuente = [];
for ($i = $d - 1; $i >= 0; $i--) $porDia[date('Y-m-d', strtotime("-$i days"))] = ['v' => 0, 'u' => []];
foreach (array_reverse($vis) as $v) {
    $unicos[$v['visitante']] = true;
    if (isset($porDia[$v['dia']])) { $porDia[$v['dia']]['v']++; $porDia[$v['dia']]['u'][$v['visitante']] = 1; }
    if (!isset($primeraFuente[$v['visitante']])) { $primeraFuente[$v['visitante']] = fuente($v); $f = fuente($v); $fuentes[$f] = ($fuentes[$f] ?? 0) + 1;
        $disp[$v['dispositivo']] = ($disp[$v['dispositivo']] ?? 0) + 1; $nav[$v['navegador']] = ($nav[$v['navegador']] ?? 0) + 1; $so[$v['so']] = ($so[$v['so']] ?? 0) + 1;
        $c = $v['ciudad'] ?: ($v['pais'] ?: 'Sin dato'); $ciud[$c] = ($ciud[$c] ?? 0) + 1; }
    $r = preg_replace('/\?.*$/', '', (string)$v['ruta']); $pags[$r] = ($pags[$r] ?? 0) + 1;
    $mapa[(int)date('N', strtotime($v['fecha'])) - 1][(int)$v['hora']]++;
    if ($v['duracion'] > 0) $dur[] = (int)$v['duracion'];
}
foreach ([&$fuentes, &$disp, &$nav, &$so, &$ciud, &$pags] as &$arr) arsort($arr);
unset($arr);
$nU = count($unicos); $nV = count($vis);
$ev = filas('SELECT tipo, valor, COUNT(*) AS n FROM eventos WHERE fecha >= ? GROUP BY tipo, valor ORDER BY n DESC', [$desdeT]);
$evT = []; foreach ($ev as $e) $evT[$e['tipo']] = ($evT[$e['tipo']] ?? 0) + $e['n'];
$leadsN = (int)valor('SELECT COUNT(*) FROM leads WHERE creado >= ?', [$desdeT]);
$clientesN = (int)valor('SELECT COUNT(DISTINCT usuario_id) FROM membresias WHERE creado >= ?', [$desdeT]);
$vieronPlanes = (int)valor("SELECT COUNT(DISTINCT visitante) FROM eventos WHERE fecha >= ? AND tipo IN ('plan','pago')", [$desdeT]) + (int)valor("SELECT COUNT(DISTINCT visitante) FROM visitas WHERE dia >= ? AND ruta LIKE '%pagar.php%'", [$desde]);
$clicWa = (int)valor("SELECT COUNT(DISTINCT visitante) FROM eventos WHERE fecha >= ? AND tipo = 'whatsapp'", [$desdeT]);
// Visitantes identificados
$ident = filas("SELECT v.visitante, MAX(v.fecha) AS ultima, COUNT(*) AS n, MAX(v.ciudad) AS ciudad, MAX(v.dispositivo) AS disp, MAX(v.usuario_id) AS uid
    FROM visitas v WHERE v.dia >= ? AND (v.usuario_id IS NOT NULL OR v.visitante IN (SELECT visitante FROM leads WHERE visitante <> '')) GROUP BY v.visitante ORDER BY ultima DESC LIMIT 40", [$desde]);
$nombres = [];
foreach (filas("SELECT visitante, nombre FROM leads WHERE visitante <> ''") as $l) $nombres[$l['visitante']] = $l['nombre'];
foreach (filas("SELECT id, nombre FROM usuarios") as $u2) $nombresU[$u2['id']] = $u2['nombre'];
$quien = function (array $v) use ($nombres, $nombresU) {
    if (!empty($v['usuario_id']) || !empty($v['uid'])) { $id = $v['usuario_id'] ?? $v['uid']; return ['<a href="' . url('admin/?p=cliente&id=' . $id) . '">' . e($nombresU[$id] ?? 'Cliente') . '</a>', 'Cliente']; }
    if (isset($nombres[$v['visitante']])) return [e($nombres[$v['visitante']]), 'Interesado'];
    return ['Visitante ' . e(strtoupper(substr((string)$v['visitante'], 0, 4))), ''];
};
admin_cabecera('estadisticas', 'Visitas a tu web');
$barras = function (array $a, int $max = 8) use ($nU) {
    $tot = max(1, array_sum($a)); $h = '<div class="lista-barras">';
    foreach (array_slice($a, 0, $max, true) as $k => $n) $h .= '<div class="f"><span title="' . e($k) . '">' . e($k) . '</span><div class="barra-h"><i style="width:' . round($n / $tot * 100) . '%"></i></div><b>' . $n . '</b></div>';
    return ($a ? $h : '<p class="ad-vacio">Sin datos aún.</p>') . '</div>';
};
$nomPag = fn($r) => ['/' => 'Portada', '/index.php' => 'Portada'][$r] ?? str_replace(['.php', '/'], ['', ' '], (string)$r);
?>
<div class="ad-barra">
  <nav class="ad-pestanas" style="margin:0;border:0"><?php foreach ([1 => 'Hoy', 7 => '7 días', 30 => '30 días', 90 => '90 días', 365 => '1 año'] as $k => $tt): ?><a class="<?= $d === $k ? 'on' : '' ?>" href="<?= url('admin/?p=estadisticas&d=' . $k) ?>"><?= $tt ?></a><?php endforeach; ?></nav>
  <div class="der"><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=estadisticas&d=' . $d . '&exportar=1') ?>"><?= icono('download') ?> Excel</a></div>
</div>
<div class="ad-kpis">
  <div class="ad-kpi"><?= icono('users') ?><span>Visitantes únicos</span><b><?= $nU ?></b><small><?= $nV ?> páginas vistas · <?= $nU ? round($nV / $nU, 1) : 0 ?> por visitante</small></div>
  <div class="ad-kpi"><?= icono('timer') ?><span>Tiempo promedio por página</span><b><?= $dur ? gmdate('i:s', (int)round(array_sum($dur) / count($dur))) : '—' ?></b><small>minutos:segundos</small></div>
  <div class="ad-kpi"><?= icono('whatsapp') ?><span>Clics a WhatsApp</span><b><?= (int)($evT['whatsapp'] ?? 0) ?></b><small><?= $clicWa ?> personas distintas</small></div>
  <div class="ad-kpi"><?= icono('bell') ?><span>Interesados (dejaron datos)</span><b><?= $leadsN ?></b><small>Conversión: <?= $nU ? round($leadsN / $nU * 100, 1) : 0 ?>% de los visitantes</small></div>
  <div class="ad-kpi"><?= icono('timer') ?><span>Usos del cronómetro</span><b><?= (int)valor("SELECT COUNT(*) FROM visitas WHERE dia >= ? AND ruta LIKE '%cronometro%'", [$desde]) ?></b><small>Atrae gente al gimnasio y a tu marca</small></div>
</div>
<div class="ad-rejilla">
  <div class="ad-caja"><h2><?= icono('chart') ?> Visitantes por día</h2><div style="height:240px"><canvas id="gDia"></canvas></div></div>
  <div class="ad-caja"><h2><?= icono('target') ?> Embudo: de visitante a cliente</h2>
    <?php $emb = ['Visitaron la web' => $nU, 'Miraron planes o pagos' => $vieronPlanes, 'Hicieron clic en WhatsApp' => $clicWa, 'Dejaron sus datos' => $leadsN, 'Compraron un plan' => $clientesN]; $mx = max(1, $nU); ?>
    <div class="lista-barras"><?php foreach ($emb as $k => $n): ?><div class="f"><span><?= e($k) ?></span><div class="barra-h"><i style="width:<?= max(2, round($n / $mx * 100)) ?>%"></i></div><b><?= $n ?></b></div><?php endforeach; ?></div>
    <p class="sec mt1">Si muchos miran planes pero pocos escriben, prueba un precio de lanzamiento o un video gratis. Si pocos llegan, publica más en redes con el enlace de tu web.</p></div>
</div>
<div class="ad-rejilla-3 mt2">
  <div class="ad-caja"><h2><?= icono('link') ?> ¿De dónde llegan?</h2><?= $barras($fuentes) ?></div>
  <div class="ad-caja"><h2><?= icono('globe') ?> ¿Desde dónde?</h2><?= $barras($ciud) ?><?php if (ajuste('geo_activo', '1') !== '1'): ?><p class="sec">La ubicación está desactivada en Ajustes.</p><?php endif; ?></div>
  <div class="ad-caja"><h2><?= icono('eye') ?> ¿Qué miran?</h2><?= $barras(array_combine(array_map($nomPag, array_keys($pags)), array_values($pags)) ?: []) ?></div>
  <div class="ad-caja"><h2><?= icono('user') ?> Dispositivo</h2><?= $barras($disp) ?><h2 class="mt2">Navegador</h2><?= $barras($nav, 5) ?></div>
  <div class="ad-caja"><h2><?= icono('bolt') ?> ¿Qué hicieron?</h2>
    <?php $nomEv = ['whatsapp' => 'WhatsApp', 'plan' => 'Clic en plan', 'red' => 'Abrió redes', 'descarga' => 'Descargó', 'video' => 'Vio video', 'lead' => 'Dejó datos', 'pago' => 'Subió pago', 'propuesta' => 'Propuesta', 'registro' => 'Creó cuenta', 'cronometro' => 'Cronómetro'];
    $evL = []; foreach ($ev as $e) { $k = ($nomEv[$e['tipo']] ?? $e['tipo']) . ($e['valor'] ? ' · ' . $e['valor'] : ''); $evL[$k] = ($evL[$k] ?? 0) + $e['n']; } arsort($evL); ?>
    <?= $barras($evL, 10) ?></div>
  <div class="ad-caja"><h2><?= icono('calendar') ?> ¿Cuándo entran?</h2>
    <div style="display:grid;grid-template-columns:18px repeat(24,minmax(0,1fr));gap:2px;font-size:9px;color:var(--txt-3)">
      <span></span><?php for ($h = 0; $h < 24; $h++): ?><span style="text-align:center"><?= $h % 6 === 0 ? $h : '' ?></span><?php endfor; ?>
      <?php $mxm = max(1, max(array_map('max', $mapa))); foreach (['L', 'M', 'X', 'J', 'V', 'S', 'D'] as $i => $dn): ?><span><?= $dn ?></span><?php for ($h = 0; $h < 24; $h++): $n = $mapa[$i][$h]; ?><i title="<?= $dn ?> <?= $h ?>h: <?= $n ?>" style="display:block;aspect-ratio:1;border-radius:3px;background:<?= $n ? 'color-mix(in srgb, var(--rojo) ' . (15 + round($n / $mxm * 85)) . '%, transparent)' : 'var(--gris-2)' ?>"></i><?php endfor; endforeach; ?>
    </div><p class="sec mt1">Publica en redes un poco antes de las horas más rojas.</p></div>
</div>
<div class="ad-caja mt2 ad-tabla-envoltura"><h2><?= icono('users') ?> ¿Quién? Clientes e interesados que visitaron</h2>
  <table class="ad-tabla"><thead><tr><th>Persona</th><th>Tipo</th><th class="num">Páginas vistas</th><th>Última visita</th><th>Ciudad</th><th>Dispositivo</th></tr></thead><tbody>
  <?php foreach ($ident as $v): [$n, $tp] = $quien($v); ?><tr><td><b><?= $n ?></b></td><td><?= $tp ? '<span class="chip">' . $tp . '</span>' : '' ?></td><td class="num"><?= (int)$v['n'] ?></td><td><?= hace($v['ultima']) ?></td><td><?= e($v['ciudad'] ?: '—') ?></td><td><?= e($v['disp']) ?></td></tr><?php endforeach; ?>
  <?php if (!$ident): ?><tr><td colspan="6" class="ad-vacio">Cuando un visitante deje sus datos o inicie sesión, verás aquí su nombre y todas sus visitas.</td></tr><?php endif; ?>
  </tbody></table></div>
<div class="ad-caja mt2 ad-tabla-envoltura"><h2><?= icono('eye') ?> Últimas visitas</h2>
  <table class="ad-tabla"><thead><tr><th>Cuándo</th><th>Quién</th><th>Página</th><th>Llegó desde</th><th>Dispositivo</th><th>Ciudad</th><th class="num">Tiempo</th></tr></thead><tbody>
  <?php foreach (array_slice($vis, 0, 60) as $v): [$n] = $quien($v); ?><tr><td class="sec"><?= date('j/n H:i', strtotime($v['fecha'])) ?></td><td><?= $n ?></td><td><?= e($nomPag(preg_replace('/\?.*$/', '', (string)$v['ruta']))) ?></td><td class="sec"><?= e(fuente($v)) ?><?= $v['utm_campaign'] ? ' · ' . e($v['utm_campaign']) : '' ?></td><td class="sec"><?= e($v['dispositivo'] . ' · ' . $v['navegador']) ?></td><td class="sec"><?= e($v['ciudad'] ?: $v['pais'] ?: '—') ?></td><td class="num"><?= $v['duracion'] ? gmdate('i:s', (int)$v['duracion']) : '—' ?></td></tr><?php endforeach; ?>
  <?php if (!$vis): ?><tr><td colspan="7" class="ad-vacio">Sin visitas en este periodo. Tus propias visitas (con sesión de administrador) no se cuentan.</td></tr><?php endif; ?>
  </tbody></table></div>
<div class="ad-nota mt2"><b>Truco para saber qué publicación funciona:</b> en el enlace de tu biografía o de cada historia agrega al final <code>?utm_source=instagram&amp;utm_campaign=historia-prensa</code>. Aquí verás cuántas personas llegaron por esa campaña.</div>
<?php
$lab = array_map(fn($x) => date('j/n', strtotime($x)), array_keys($porDia));
admin_pie('<script>document.addEventListener("DOMContentLoaded",function(){if(!window.Chart)return;var r=getComputedStyle(document.documentElement).getPropertyValue("--rojo").trim(),g={color:"rgba(255,255,255,.06)"},t={color:"#a8a8b3"};'
    . 'new Chart(document.getElementById("gDia"),{type:"line",data:{labels:' . json_encode($lab) . ',datasets:[{label:"Visitantes",data:' . json_encode(array_map(fn($x) => count($x['u']), array_values($porDia))) . ',borderColor:r,backgroundColor:r+"33",fill:true,tension:.35},{label:"Páginas vistas",data:' . json_encode(array_column($porDia, 'v')) . ',borderColor:"#d4ff3a",tension:.35,borderDash:[4,4]}]},options:{maintainAspectRatio:false,plugins:{legend:{labels:{color:"#d4d4d8"}}},scales:{x:{grid:g,ticks:t},y:{grid:g,ticks:t,beginAtZero:true,precision:0}}}});});</script>');
