<?php
/** Portal del cliente: plan, evolución, rutinas, videos, check-in, pagos y perfil. */
require dirname(__DIR__) . '/inc/app.php';
require dirname(__DIR__) . '/inc/layout.php';
require dirname(__DIR__) . '/inc/bio.php';
iniciar_sesion();
$u = usuario();
if (!$u) redirigir(url('ingresar.php?r=' . urlencode('cliente/' . (isset($_GET['p']) ? '?p=' . $_GET['p'] : ''))));
if ($u['rol'] === 'admin' && empty($_GET['ver'])) redirigir(url('admin/'));
$uid = (int)$u['id'];
$p = $_GET['p'] ?? 'inicio';

/* ---------- Acciones ---------- */
if (es_post()) {
    csrf_verificar();
    $acc = $_POST['acc'] ?? '';
    try {
        if ($acc === 'medida') {
            $peso = num($_POST['peso'] ?? null);
            if (!$peso) throw new RuntimeException('Escribe tu peso.');
            $d = ['usuario_id' => $uid, 'fecha' => substr((string)($_POST['fecha'] ?? hoy()), 0, 10) ?: hoy(), 'peso' => $peso, 'por' => 'cliente', 'creado' => ahora(), 'nota' => mb_substr((string)($_POST['nota'] ?? ''), 0, 250)];
            foreach (['cintura', 'cadera', 'pecho', 'brazo', 'pierna', 'abdomen'] as $c) if (num($_POST[$c] ?? null)) $d[$c] = num($_POST[$c]);
            insertar('medidas', $d);
            flash('¡Registrado! Sigue así.');
        } elseif ($acc === 'foto') {
            $f = subir_archivo($_FILES['foto'] ?? ['error' => UPLOAD_ERR_NO_FILE], TIPOS_IMAGEN, true, 'fotos', 15);
            if (!$f) throw new RuntimeException('Elige una foto.');
            insertar('fotos', ['usuario_id' => $uid, 'fecha' => substr((string)($_POST['fecha'] ?? hoy()), 0, 10) ?: hoy(), 'tipo' => in_array($_POST['tipo'] ?? '', ['frente', 'perfil', 'espalda', 'otra'], true) ? $_POST['tipo'] : 'otra',
                'archivo' => $f, 'nota' => mb_substr((string)($_POST['nota'] ?? ''), 0, 250), 'por' => 'cliente', 'creado' => ahora()]);
            flash('Foto guardada. Solo tú y Bryan pueden verla.');
        } elseif ($acc === 'checkin') {
            $r = fn($k) => max(1, min(5, (int)($_POST[$k] ?? 3)));
            insertar('checkins', ['usuario_id' => $uid, 'fecha' => hoy(), 'peso' => num($_POST['peso'] ?? null), 'energia' => $r('energia'), 'sueno' => $r('sueno'), 'adherencia' => $r('adherencia'), 'animo' => $r('animo'),
                'comentario' => mb_substr((string)($_POST['comentario'] ?? ''), 0, 2000), 'creado' => ahora()]);
            if (num($_POST['peso'] ?? null)) insertar('medidas', ['usuario_id' => $uid, 'fecha' => hoy(), 'peso' => num($_POST['peso']), 'nota' => 'Check-in', 'por' => 'cliente', 'creado' => ahora()]);
            flash('Check-in enviado. Bryan te responde aquí mismo.');
        } elseif ($acc === 'perfil') {
            $d = ['nombre' => trim(mb_substr((string)$_POST['nombre'], 0, 120)) ?: $u['nombre'], 'telefono' => mb_substr((string)$_POST['telefono'], 0, 30),
                'fecha_nacimiento' => substr((string)($_POST['fecha_nacimiento'] ?? ''), 0, 10) ?: null, 'sexo' => in_array($_POST['sexo'] ?? '', ['M', 'F'], true) ? $_POST['sexo'] : null,
                'estatura_cm' => num($_POST['estatura_cm'] ?? null), 'nivel' => in_array($_POST['nivel'] ?? '', ['principiante', 'intermedio', 'avanzado'], true) ? $_POST['nivel'] : null,
                'objetivo' => mb_substr((string)($_POST['objetivo'] ?? ''), 0, 1000)];
            actualizar('usuarios', $uid, $d);
            flash('Perfil actualizado.');
        } elseif ($acc === 'clave') {
            $n = (string)($_POST['nueva'] ?? '');
            if (!$u['debe_cambiar'] && !password_verify((string)($_POST['actual'] ?? ''), $u['clave_hash'])) throw new RuntimeException('La contraseña actual no es correcta.');
            if (mb_strlen($n) < 8) throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');
            if ($n !== ($_POST['repite'] ?? '')) throw new RuntimeException('Las contraseñas no coinciden.');
            q('UPDATE usuarios SET clave_hash = ?, debe_cambiar = 0 WHERE id = ?', [password_hash($n, PASSWORD_DEFAULT), $uid]);
            flash('Contraseña cambiada.');
        } elseif ($acc === 'salir') {
            logout_usuario(); redirigir(url(''));
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'error'); }
    redirigir(url('cliente/?p=' . urlencode($p)));
}

/* ---------- Datos comunes ---------- */
$m = membresia_activa($uid);
if ($m && $m['fin'] && $m['fin'] < hoy()) { q("UPDATE membresias SET estado = 'vencida' WHERE id = ?", [$m['id']]); $m = null; }
$desde = $u['cliente_desde'] ?: (valor('SELECT MIN(inicio) FROM membresias WHERE usuario_id = ?', [$uid]) ?: substr((string)$u['creado'], 0, 10));
$medidas = filas('SELECT * FROM medidas WHERE usuario_id = ? ORDER BY fecha, id', [$uid]);
$pesos = array_values(array_filter($medidas, fn($x) => $x['peso'] !== null));
$pesoIni = $pesos ? (float)$pesos[0]['peso'] : null;
$pesoAct = $pesos ? (float)end($pesos)['peso'] : null;
$entrenos = (int)valor('SELECT COUNT(*) FROM entrenos WHERE usuario_id = ?', [$uid]);
$sesiones = (int)valor('SELECT COUNT(*) FROM sesiones WHERE usuario_id = ?', [$uid]);
$nombre1 = explode(' ', $u['nombre'])[0];

$tabs = ['inicio' => ['home', 'Inicio'], 'evolucion' => ['chart', 'Mi evolución'], 'rutinas' => ['dumbbell', 'Mis rutinas'], 'videos' => ['video', 'Videos'],
    'checkin' => ['heart', 'Check-in'], 'pagos' => ['money', 'Pagos'], 'perfil' => ['user', 'Perfil']];
if (!isset($tabs[$p])) $p = 'inicio';
cabecera($tabs[$p][1], ['body' => 'pagina portal', 'sin_visita' => false, 'ruta' => '/cliente/' . $p,
    'head' => '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" defer></script>']);
?>
<section class="portal-cabeza">
  <div class="envoltura">
    <div class="portal-hola">
      <div><span class="antetitulo">Mi portal</span><h1 class="titulo" style="font-size:clamp(36px,5vw,58px)">Hola, <span class="marca"><?= e($nombre1) ?></span></h1>
        <p class="sub" style="font-size:15px;margin:0">Cliente desde el <?= fecha_larga($desde) ?></p></div>
      <div class="hero-ctas" style="margin:0">
        <a class="btn btn-rojo btn-sm" href="<?= url('cronometro.php') ?>"><?= icono('timer') ?> Cronómetro</a>
        <form method="post"><?= csrf_campo() ?><input type="hidden" name="acc" value="salir"><button class="btn btn-borde btn-sm" type="submit"><?= icono('logout') ?> Salir</button></form>
      </div>
    </div>
    <nav class="portal-nav" aria-label="Portal">
      <?php foreach ($tabs as $k => [$ic, $t]): ?><a href="<?= url('cliente/?p=' . $k) ?>" class="<?= $p === $k ? 'on' : '' ?>"><?= icono($ic) ?><span><?= e($t) ?></span></a><?php endforeach; ?>
    </nav>
  </div>
</section>
<section class="seccion" style="padding-top:30px">
<div class="envoltura">
<?php if ($u['debe_cambiar']): ?><div class="msg msg-error">Por seguridad, cambia la contraseña temporal que te dio Bryan en <a href="<?= url('cliente/?p=perfil') ?>" style="color:#fff">Perfil</a>.</div><?php endif; ?>

<?php /* ================= INICIO ================= */ if ($p === 'inicio'):
  $diasCumple = ajuste('regalo_cumple_activo', '1') === '1' ? dias_desde_cumple($u['fecha_nacimiento'], (int)ajuste('regalo_cumple_dias', '7')) : null;
  $regaloDado = $diasCumple !== null && valor("SELECT COUNT(*) FROM eventos WHERE tipo = 'regalo_cumple' AND usuario_id = ? AND valor = ?", [$uid, date('Y')]);
  if ($diasCumple !== null): ?>
  <div class="cumple-portal">
    <div style="font-size:52px">🎂🎉</div>
    <h2>¡Feliz cumpleaños, <?= e($nombre1) ?>!</h2>
    <p class="sub" style="margin-inline:auto"><?= $regaloDado ? 'Ya disfrutaste tu regalo de este año. ¡Que sigan los éxitos!' : 'Tu regalo: ' . e(ajuste('regalo_cumple_texto')) ?></p>
    <?php if (!$regaloDado): ?><a class="btn btn-volt" target="_blank" rel="noopener" href="<?= e(wa_link(ajuste('whatsapp'), 'Hola Bryan, soy ' . $u['nombre'] . '. ¡Hoy estoy de cumpleaños! 🎂 Quiero reclamar mi regalo: ' . ajuste('regalo_cumple_texto'))) ?>">🎁 Reclamar mi regalo</a>
      <p class="aviso-salud">Válido hasta el <?= fecha_larga(date('Y-m-d', strtotime('+' . ((int)ajuste('regalo_cumple_dias', '7') - $diasCumple) . ' days'))) ?>.</p><?php endif; ?>
  </div>
  <?php endif; ?>
<?php
  $diasRest = $m && $m['fin'] ? (int)floor((strtotime($m['fin']) - strtotime(hoy())) / 86400) : null;
  $pct = $m && $m['sesiones_total'] ? min(100, round($m['sesiones_usadas'] / $m['sesiones_total'] * 100)) : 0;
  $recs = filas("SELECT * FROM contenidos WHERE tipo = 'recomendacion' AND visible = 1 AND (usuario_id IS NULL OR usuario_id = ?) ORDER BY (usuario_id IS NULL), orden, id DESC LIMIT 4", [$uid]);
  $resp = fila('SELECT * FROM checkins WHERE usuario_id = ? AND respuesta IS NOT NULL AND respuesta <> \'\' ORDER BY id DESC LIMIT 1', [$uid]);
  $rutinas = filas('SELECT * FROM rutinas WHERE usuario_id = ? AND activa = 1 ORDER BY id DESC LIMIT 3', [$uid]);
  $semanas = filas('SELECT fecha FROM entrenos WHERE usuario_id = ? ORDER BY fecha DESC LIMIT 200', [$uid]);
  $racha = 0; $sem = (int)date('oW');
  $setSem = array_flip(array_map(fn($x) => (int)date('oW', strtotime($x['fecha'])), $semanas));
  for ($i = 0; $i < 52; $i++) { $w = (int)date('oW', strtotime("-$i week")); if (isset($setSem[$w])) $racha++; elseif ($i > 0) break; }
?>
  <div class="portal-grid">
    <div class="tarjeta plan-actual">
      <?php if ($m): ?>
        <span class="antetitulo">Tu plan activo</span>
        <h2 class="titulo" style="font-size:44px"><?= e($m['nombre_plan']) ?></h2>
        <div class="plan-anillo">
          <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="52" class="f"/><circle cx="60" cy="60" r="52" class="b" style="stroke-dashoffset:<?= 326.7 * (1 - $pct / 100) ?>"/></svg>
          <div><b><?= (int)$m['sesiones_usadas'] ?><small>/<?= (int)$m['sesiones_total'] ?></small></b><span>sesiones</span></div>
        </div>
        <div class="res-lista">
          <div><b><?= fecha_corta($m['inicio']) ?></b><span>Inicio</span></div>
          <div><b><?= $m['fin'] ? fecha_corta($m['fin']) : '—' ?></b><span>Vence</span></div>
          <div><b><?= max(0, (int)$m['sesiones_total'] - (int)$m['sesiones_usadas']) ?></b><span>Sesiones restantes</span></div>
          <div><b style="color:<?= $diasRest !== null && $diasRest <= 5 ? 'var(--alerta)' : 'var(--txt)' ?>"><?= $diasRest !== null ? max(0, $diasRest) : '—' ?></b><span>Días restantes</span></div>
        </div>
        <?php if ($diasRest !== null && $diasRest <= 7): ?><a class="btn btn-rojo btn-bloque" href="<?= url('pagar.php') ?>">Renovar mi plan</a><?php endif; ?>
      <?php else: ?>
        <span class="antetitulo">Sin plan activo</span>
        <h2 class="titulo" style="font-size:40px">Empieza tu evolución</h2>
        <p class="sub" style="font-size:15px">Elige un programa y sube tu comprobante. Bryan lo activa y aquí verás tus sesiones, tu rutina y tu progreso.</p>
        <a class="btn btn-rojo" href="<?= url('#planes') ?>">Ver planes</a>
      <?php endif; ?>
    </div>
    <div class="kpis">
      <div class="kpi"><?= icono('scale') ?><b><?= $pesoAct ? rtrim(rtrim(number_format($pesoAct, 1, ',', ''), '0'), ',') . ' kg' : '—' ?></b><span>Peso actual</span>
        <?php if ($pesoIni && $pesoAct && $pesoIni != $pesoAct): $dif = $pesoAct - $pesoIni; ?><em class="<?= $dif < 0 ? 'baja' : 'sube' ?>"><?= ($dif > 0 ? '+' : '') . number_format($dif, 1, ',', '') ?> kg desde el inicio</em><?php endif; ?></div>
      <div class="kpi"><?= icono('dumbbell') ?><b><?= $sesiones ?></b><span>Sesiones con Bryan</span></div>
      <div class="kpi"><?= icono('timer') ?><b><?= $entrenos ?></b><span>Entrenos registrados</span></div>
      <div class="kpi"><?= icono('flame') ?><b><?= $racha ?></b><span>Semanas seguidas entrenando</span></div>
    </div>
  </div>

  <div class="portal-grid mt2">
    <div class="tarjeta">
      <h3><?= icono('clipboard') ?> Tus rutinas</h3>
      <?php if ($rutinas): foreach ($rutinas as $r): ?>
        <div class="fila-lista"><div><b><?= e($r['nombre']) ?></b><br><span><?= e($r['descripcion']) ?></span></div><a class="btn btn-sm btn-rojo" href="<?= url('cliente/?p=rutinas#r' . $r['id']) ?>">Ver</a></div>
      <?php endforeach; else: ?><p class="sub" style="font-size:15px">Bryan te asignará tu rutina después de tu valoración.</p><?php endif; ?>
    </div>
    <div class="tarjeta">
      <h3><?= icono('bell') ?> De Bryan para ti</h3>
      <?php if ($resp): ?><div class="burbuja"><b>Respuesta a tu check-in del <?= fecha_corta($resp['fecha']) ?>:</b><br><?= nl2br(e($resp['respuesta'])) ?></div><?php endif; ?>
      <?php foreach ($recs as $r): ?><div class="fila-lista"><div><b><?= e($r['titulo']) ?></b><br><span><?= e($r['cuerpo']) ?></span></div></div><?php endforeach; ?>
      <?php if (!$resp && !$recs): ?><p class="sub" style="font-size:15px">Aquí verás las recomendaciones de Bryan.</p><?php endif; ?>
    </div>
  </div>
  <?php $bens = beneficios(); if ($bens): ?>
  <div class="beneficios"><?php foreach ($bens as $kb => [$ic, $tit, $txt]): ?><div class="beneficio"><span><?= $ic ?></span><div><b><?= e($tit) ?></b><p><?= e($txt) ?><?= $kb === 'cumple' && !$u['fecha_nacimiento'] ? ' <a href="' . url('cliente/?p=perfil') . '" style="color:var(--volt)">Agrega tu fecha de nacimiento</a> para recibirlo.' : '' ?></p></div></div><?php endforeach; ?></div>
  <?php endif; ?>
  <div class="accesos mt2">
    <a href="<?= url('cliente/?p=checkin') ?>"><?= icono('heart') ?><b>Check-in semanal</b><span>Cuéntale a Bryan cómo vas</span></a>
    <a href="<?= url('cliente/?p=evolucion#fotos') ?>"><?= icono('camera') ?><b>Subir fotos</b><span>Frente, perfil y espalda</span></a>
    <a href="<?= url('cliente/?p=evolucion') ?>"><?= icono('scale') ?><b>Registrar peso</b><span>Y tus medidas</span></a>
    <a href="<?= url('maquinas.php') ?>"><?= icono('video') ?><b>Biblioteca</b><span>Cómo usar cada máquina</span></a>
  </div>

<?php /* ================= EVOLUCIÓN ================= */ elseif ($p === 'evolucion'):
  $fotos = filas('SELECT * FROM fotos WHERE usuario_id = ? ORDER BY fecha DESC, id DESC', [$uid]);
  $series = ['peso' => 'Peso (kg)', 'grasa' => '% grasa', 'musculo' => '% músculo', 'cintura' => 'Cintura (cm)', 'cadera' => 'Cadera (cm)', 'pecho' => 'Pecho (cm)', 'brazo' => 'Brazo (cm)', 'pierna' => 'Pierna (cm)', 'abdomen' => 'Abdomen (cm)'];
  $datosG = [];
  foreach ($series as $k => $t) { $pts = array_values(array_filter(array_map(fn($x) => $x[$k] !== null ? ['x' => $x['fecha'], 'y' => (float)$x[$k]] : null, $medidas))); if ($pts) $datosG[$k] = ['t' => $t, 'p' => $pts]; }
?>
  <div class="portal-grid">
    <div class="tarjeta" style="grid-column:span 2">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><h3 style="margin:0"><?= icono('chart') ?> Tu progreso</h3>
        <div class="filtros" style="margin:0" id="selSerie"><?php $i = 0; foreach ($datosG as $k => $s): ?><button type="button" data-s="<?= $k ?>" class="<?= $i++ ? '' : 'on' ?>"><?= e($s['t']) ?></button><?php endforeach; ?></div></div>
      <?php if ($datosG): ?><div style="height:300px;margin-top:16px"><canvas id="grafica"></canvas></div>
      <?php else: ?><p class="sub mt2" style="font-size:15px">Registra tu peso para empezar a ver tu gráfica.</p><?php endif; ?>
    </div>
  </div>
  <div class="tarjeta mt2" id="bioimpedancia">
    <h3><?= icono('scale') ?> Bioimpedancia Smart Fit</h3>
    <label class="zona-archivo"><?= icono('upload') ?><b>Cargar mi PDF de bioimpedancia</b><br><span class="sec" style="color:var(--txt-2);font-size:13px">Descárgalo de la app o el correo de Smart Fit y súbelo aquí cada mes para ver tu comparativo.</span>
      <input type="file" accept="application/pdf" data-bio-subir data-nombre="<?= e($u['nombre']) ?>" data-estado="#bioEstado"></label>
    <div id="bioEstado"></div>
    <div class="mt2"><?= bio_comparativo($uid) ?></div>
    <script src="<?= asset('js/bioimpedancia.js') ?>"></script>
  </div>
  <div class="portal-grid mt2">
    <form method="post" class="tarjeta"><?= csrf_campo() ?><input type="hidden" name="acc" value="medida">
      <h3><?= icono('scale') ?> Registrar medidas</h3>
      <div class="campos">
        <div class="campo"><label>Fecha</label><input type="date" name="fecha" value="<?= hoy() ?>" max="<?= hoy() ?>"></div>
        <div class="campo"><label>Peso (kg) *</label><input type="number" name="peso" step="0.1" inputmode="decimal" required></div>
        <?php foreach (['cintura' => 'Cintura', 'cadera' => 'Cadera', 'abdomen' => 'Abdomen', 'pecho' => 'Pecho', 'brazo' => 'Brazo', 'pierna' => 'Pierna'] as $k => $t): ?><div class="campo"><label><?= $t ?> (cm)</label><input type="number" name="<?= $k ?>" step="0.1" inputmode="decimal"></div><?php endforeach; ?>
      </div>
      <button class="btn btn-rojo btn-bloque mt2" type="submit">Guardar</button>
      <p class="aviso-salud">Pésate en la mañana, en ayunas y después de ir al baño, siempre en la misma báscula.</p>
    </form>
    <div class="tarjeta" style="overflow:auto">
      <h3>Historial</h3>
      <table class="tabla" style="font-size:14px"><thead><tr><th>Fecha</th><th class="num">Peso</th><th class="num">% grasa</th><th class="num">Cintura</th><th>Por</th></tr></thead><tbody>
        <?php foreach (array_reverse($medidas) as $x): ?><tr><td><?= fecha_corta($x['fecha']) ?></td><td class="num"><?= $x['peso'] !== null ? (float)$x['peso'] : '—' ?></td><td class="num"><?= $x['grasa'] !== null ? (float)$x['grasa'] : '—' ?></td><td class="num"><?= $x['cintura'] !== null ? (float)$x['cintura'] : '—' ?></td><td><?= ['admin' => 'Bryan', 'bio' => 'Smart Fit'][$x['por']] ?? 'Tú' ?></td></tr><?php endforeach; ?>
        <?php if (!$medidas): ?><tr><td colspan="5" style="color:var(--txt-2)">Sin registros todavía.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
  <div class="tarjeta mt2" id="fotos">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center"><h3 style="margin:0"><?= icono('camera') ?> Fotos de progreso</h3><a class="btn btn-sm btn-borde" href="<?= url('articulo.php?s=fotos-de-progreso') ?>">¿Cómo tomarlas?</a></div>
    <?php if (count($fotos) >= 2): ?>
      <div class="comparador mt2">
        <div class="campos"><div class="campo"><label>Antes</label><select id="fA"><?php foreach (array_reverse($fotos) as $f): ?><option value="<?= e(media_url('foto', (int)$f['id'])) ?>"><?= fecha_corta($f['fecha']) ?> · <?= e($f['tipo']) ?></option><?php endforeach; ?></select></div>
          <div class="campo"><label>Después</label><select id="fD"><?php foreach ($fotos as $f): ?><option value="<?= e(media_url('foto', (int)$f['id'])) ?>"><?= fecha_corta($f['fecha']) ?> · <?= e($f['tipo']) ?></option><?php endforeach; ?></select></div></div>
        <div class="antes-despues mt1" style="max-width:420px;margin-inline:auto"><img id="imA" src="<?= e(media_url('foto', (int)end($fotos)['id'])) ?>" alt="Antes"><img id="imD" class="despues" src="<?= e(media_url('foto', (int)$fotos[0]['id'])) ?>" alt="Después"><span class="etq-a">ANTES</span><span class="etq-d">DESPUÉS</span><div class="divisor"></div><input type="range" min="0" max="100" value="50" aria-label="Comparar"></div>
      </div>
    <?php endif; ?>
    <div class="galeria mt2"><?php foreach ($fotos as $f): ?><a href="<?= e(media_url('foto', (int)$f['id'])) ?>" target="_blank"><img src="<?= e(media_url('foto', (int)$f['id'])) ?>" alt="Foto <?= e($f['tipo']) ?>" loading="lazy"><span><?= fecha_corta($f['fecha']) ?> · <?= e($f['tipo']) ?></span></a><?php endforeach; ?></div>
    <form method="post" enctype="multipart/form-data" class="campos mt2"><?= csrf_campo() ?><input type="hidden" name="acc" value="foto">
      <label class="zona-archivo ancho"><?= icono('upload') ?><b>Subir una foto</b><br><span class="nombre-archivo" style="color:var(--volt);font-size:14px"></span><input type="file" name="foto" accept="image/*" required></label>
      <div class="campo"><label>Tipo</label><select name="tipo"><option value="frente">Frente</option><option value="perfil">Perfil</option><option value="espalda">Espalda</option><option value="otra">Otra</option></select></div>
      <div class="campo"><label>Fecha</label><input type="date" name="fecha" value="<?= hoy() ?>" max="<?= hoy() ?>"></div>
      <button class="btn btn-rojo ancho" type="submit">Guardar foto</button>
    </form>
  </div>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var D = <?= json_encode($datosG, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>, ch = null, c = document.getElementById('grafica');
    function dib(k) {
      if (!c || !window.Chart || !D[k]) return;
      var rojo = getComputedStyle(document.documentElement).getPropertyValue('--rojo').trim() || '#ff2a2a';
      if (ch) ch.destroy();
      ch = new Chart(c, { type: 'line', data: { labels: D[k].p.map(function (x) { return x.x; }), datasets: [{ label: D[k].t, data: D[k].p.map(function (x) { return x.y; }), borderColor: rojo, backgroundColor: rojo + '33', fill: true, tension: .35, pointRadius: 5, pointBackgroundColor: rojo }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: '#a8a8b3' }, grid: { color: 'rgba(255,255,255,.06)' } }, y: { ticks: { color: '#a8a8b3' }, grid: { color: 'rgba(255,255,255,.06)' } } } } });
    }
    document.querySelectorAll('#selSerie button').forEach(function (b) { b.addEventListener('click', function () { document.querySelectorAll('#selSerie button').forEach(function (x) { x.classList.toggle('on', x === b); }); dib(b.dataset.s); }); });
    var k0 = Object.keys(D)[0]; if (k0) dib(k0);
    var fA = document.getElementById('fA'), fD = document.getElementById('fD');
    if (fA) { fA.addEventListener('change', function () { document.getElementById('imA').src = fA.value; }); fD.addEventListener('change', function () { document.getElementById('imD').src = fD.value; }); }
  });
  </script>

<?php /* ================= RUTINAS ================= */ elseif ($p === 'rutinas'):
  $rutinas = filas('SELECT * FROM rutinas WHERE usuario_id = ? AND activa = 1 ORDER BY id DESC', [$uid]);
  $peso = $pesoAct ?: 0;
  $edad = $u['fecha_nacimiento'] ? (int)date_diff(date_create($u['fecha_nacimiento']), date_create('today'))->y : 30;
  $hist = filas('SELECT e.*, r.nombre AS rnombre FROM entrenos e LEFT JOIN rutinas r ON r.id = e.rutina_id WHERE e.usuario_id = ? ORDER BY e.fecha DESC LIMIT 15', [$uid]);
?>
  <?php if (!$rutinas): ?><div class="tarjeta"><h3>Aún no tienes rutinas</h3><p class="sub" style="font-size:15px">Bryan te asigna tu rutina después de la valoración. Mientras tanto, usa el <a href="<?= url('cronometro.php') ?>" style="color:var(--rojo)">cronómetro</a> y revisa la <a href="<?= url('maquinas.php') ?>" style="color:var(--rojo)">biblioteca de máquinas</a>.</p></div><?php endif; ?>
  <?php foreach ($rutinas as $r):
    $ej = filas('SELECT e.*, v.titulo AS vtitulo, v.slug, v.coef_hombre, v.coef_mujer, v.incremento FROM rutina_ejercicios e LEFT JOIN videos v ON v.id = e.video_id WHERE e.rutina_id = ? ORDER BY e.orden, e.id', [$r['id']]);
    $dias = []; foreach ($ej as $x) $dias[$x['dia'] ?: 'Rutina'][] = $x; ?>
    <div class="tarjeta mt2" id="r<?= (int)$r['id'] ?>">
      <h2 class="titulo" style="font-size:38px"><?= e($r['nombre']) ?></h2>
      <?php if ($r['descripcion']): ?><p class="sub" style="font-size:15px"><?= e($r['descripcion']) ?></p><?php endif; ?>
      <?php foreach ($dias as $dia => $lista): ?>
        <div class="dia-rutina">
          <div class="dia-cab"><h3><?= e($dia) ?></h3><a class="btn btn-rojo btn-sm" href="<?= url('cronometro.php?modo=rutina&rutina=' . $r['id'] . '&dia=' . urlencode($dia)) ?>"><?= icono('play') ?> Entrenar con cronómetro</a></div>
          <div style="overflow-x:auto"><table class="tabla" style="font-size:14px">
            <thead><tr><th>Ejercicio</th><th>Series × reps</th><th>Descanso</th><th>Carga</th><th></th></tr></thead><tbody>
            <?php foreach ($lista as $x):
              $carga = $x['carga'];
              if ($carga === '' && $peso && (float)$x['coef_hombre'] > 0) { $c = carga_sugerida($x, (float)$peso, (string)($u['sexo'] ?: 'M'), (string)($u['nivel'] ?: 'principiante'), (string)($r['objetivo'] ?: 'hipertrofia'), $edad); if ($c) $carga = $c['kg'] . ' kg <small style="color:var(--txt-3)">sugerida</small>'; } else $carga = e($carga); ?>
              <tr><td><b><?= e($x['nombre'] ?: $x['vtitulo']) ?></b><?= $x['nota'] ? '<br><small style="color:var(--txt-2)">' . e($x['nota']) . '</small>' : '' ?></td><td><?= (int)$x['series'] ?> × <?= e($x['reps']) ?></td><td><?= (int)$x['descanso_seg'] ?> s</td><td><?= $carga ?: '—' ?></td>
                <td><?= $x['slug'] ? '<a class="btn btn-xs btn-borde" href="' . url('maquina.php?s=' . urlencode($x['slug'])) . '">' . icono('video') . ' Video</a>' : '' ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <div class="tarjeta mt2"><h3><?= icono('calendar') ?> Tus últimos entrenos</h3>
    <?php if ($hist): ?><table class="tabla" style="font-size:14px"><thead><tr><th>Fecha</th><th>Rutina</th><th>Duración</th><th>Series</th><th>Sensación</th></tr></thead><tbody>
      <?php foreach ($hist as $h): ?><tr><td><?= fecha_corta($h['fecha']) ?></td><td><?= e($h['rnombre'] ?: ucfirst((string)$h['dia'])) ?></td><td><?= gmdate($h['duracion_seg'] >= 3600 ? 'G:i:s' : 'i:s', (int)$h['duracion_seg']) ?></td><td><?= (int)$h['series_hechas'] ?></td><td><?= $h['sensacion'] ? ['', '😫', '😕', '🙂', '💪', '🔥'][(int)$h['sensacion']] : '—' ?></td></tr><?php endforeach; ?>
    </tbody></table><?php else: ?><p class="sub" style="font-size:15px">Cuando termines un entreno con el cronómetro, guárdalo y aparecerá aquí.</p><?php endif; ?></div>

<?php /* ================= VIDEOS ================= */ elseif ($p === 'videos'):
  $vids = filas('SELECT * FROM videos WHERE visible = 1 ORDER BY orden, id');
  $si = array_filter($vids, fn($v) => puede_ver_video($u, $v));
  $no = array_filter($vids, fn($v) => !puede_ver_video($u, $v)); ?>
  <h2 class="titulo" style="font-size:40px">Tus videos</h2>
  <div class="maquinas mt1"><?php foreach ($si as $v): ?><a class="maq" href="<?= url('maquina.php?s=' . urlencode($v['slug'])) ?>"><div class="maq-img"><span class="maq-estado"><span class="chip chip-volt"><?= icono('play') ?> Ver</span></span><?= portada_video($v) ?></div><div class="maq-cuerpo"><h3><?= e($v['titulo']) ?></h3></div></a><?php endforeach; ?></div>
  <?php if ($no): ?><h2 class="titulo mt3" style="font-size:40px">Desbloquea más</h2>
  <div class="maquinas mt1"><?php foreach ($no as $v): ?><a class="maq" href="<?= url('maquina.php?s=' . urlencode($v['slug'])) ?>"><div class="maq-img"><span class="maq-estado"><span class="chip chip-rojo"><?= icono('lock') ?> <?= pesos($v['precio']) ?></span></span><?= portada_video($v) ?></div><div class="maq-cuerpo"><h3><?= e($v['titulo']) ?></h3></div></a><?php endforeach; ?></div><?php endif; ?>

<?php /* ================= CHECK-IN ================= */ elseif ($p === 'checkin'):
  $hist = filas('SELECT * FROM checkins WHERE usuario_id = ? ORDER BY id DESC LIMIT 20', [$uid]);
  $escala = function (string $n, string $t, array $et) { $h = '<div class="campo ancho"><span class="etq">' . e($t) . '</span><div class="opciones escala">'; for ($i = 1; $i <= 5; $i++) $h .= '<label><input type="radio" name="' . $n . '" value="' . $i . '"' . ($i === 3 ? ' checked' : '') . '><span>' . $et[$i - 1] . '</span></label>'; return $h . '</div></div>'; }; ?>
  <div class="portal-grid">
    <form method="post" class="tarjeta"><?= csrf_campo() ?><input type="hidden" name="acc" value="checkin">
      <h3><?= icono('heart') ?> ¿Cómo te fue esta semana?</h3>
      <div class="campos">
        <div class="campo ancho"><label>Peso de hoy (kg, opcional)</label><input type="number" name="peso" step="0.1" inputmode="decimal"></div>
        <?= $escala('energia', 'Energía', ['😴', '😕', '🙂', '😃', '⚡']) ?>
        <?= $escala('sueno', 'Calidad del sueño', ['😫', '😕', '🙂', '😌', '💤']) ?>
        <?= $escala('adherencia', 'Cumplí el plan de comidas y entreno', ['0%', '25%', '50%', '75%', '100%']) ?>
        <?= $escala('animo', 'Ánimo y motivación', ['😞', '😐', '🙂', '😄', '🔥']) ?>
        <div class="campo ancho"><label>Cuéntale a Bryan (dolores, dudas, logros)</label><textarea name="comentario" maxlength="2000"></textarea></div>
      </div>
      <button class="btn btn-rojo btn-bloque mt2" type="submit"><?= icono('send') ?> Enviar check-in</button>
    </form>
    <div class="tarjeta"><h3>Tus check-ins</h3>
      <?php foreach ($hist as $h): ?>
        <div class="checkin-item"><div class="ci-cab"><b><?= fecha_corta($h['fecha']) ?></b><span>Energía <?= (int)$h['energia'] ?>/5 · Sueño <?= (int)$h['sueno'] ?>/5 · Plan <?= ((int)$h['adherencia'] - 1) * 25 ?>%</span></div>
          <?php if ($h['comentario']): ?><p><?= nl2br(e($h['comentario'])) ?></p><?php endif; ?>
          <?php if ($h['respuesta']): ?><div class="burbuja"><b>Bryan:</b> <?= nl2br(e($h['respuesta'])) ?></div><?php else: ?><span class="chip">Esperando respuesta</span><?php endif; ?></div>
      <?php endforeach; ?>
      <?php if (!$hist): ?><p class="sub" style="font-size:15px">Haz tu primer check-in. Lo ideal: uno por semana, el mismo día.</p><?php endif; ?>
    </div>
  </div>

<?php /* ================= PAGOS ================= */ elseif ($p === 'pagos'):
  $pagos = filas('SELECT * FROM pagos WHERE usuario_id = ? ORDER BY id DESC', [$uid]);
  $mems = filas('SELECT * FROM membresias WHERE usuario_id = ? ORDER BY inicio DESC, id DESC', [$uid]); ?>
  <div class="portal-grid">
    <div class="tarjeta"><div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><h3 style="margin:0"><?= icono('money') ?> Pagos</h3><a class="btn btn-rojo btn-sm" href="<?= url('pagar.php') ?>">Pagar / renovar</a></div>
      <table class="tabla mt1" style="font-size:14px"><thead><tr><th>Fecha</th><th>Concepto</th><th class="num">Valor</th><th>Estado</th></tr></thead><tbody>
        <?php foreach ($pagos as $x): ?><tr><td><?= fecha_corta($x['creado']) ?></td><td><?= e($x['concepto']) ?><?= $x['comprobante'] ? ' · <a href="' . e(media_url('comprobante', (int)$x['id'])) . '" target="_blank" style="color:var(--rojo)">comprobante</a>' : '' ?></td><td class="num"><?= pesos($x['monto']) ?></td><td><?= ['pendiente' => '<span class="chip">En revisión</span>', 'aprobado' => '<span class="chip chip-ok">Aprobado</span>', 'rechazado' => '<span class="chip chip-rojo">Rechazado</span>'][$x['estado']] ?? e($x['estado']) ?></td></tr><?php endforeach; ?>
        <?php if (!$pagos): ?><tr><td colspan="4" style="color:var(--txt-2)">Sin pagos registrados.</td></tr><?php endif; ?></tbody></table></div>
    <div class="tarjeta"><h3><?= icono('calendar') ?> Historial de planes</h3>
      <?php foreach ($mems as $x): ?><div class="fila-lista"><div><b><?= e($x['nombre_plan']) ?></b><br><span><?= fecha_corta($x['inicio']) ?> → <?= fecha_corta($x['fin']) ?> · <?= (int)$x['sesiones_usadas'] ?>/<?= (int)$x['sesiones_total'] ?> sesiones</span></div><span class="chip <?= $x['estado'] === 'activa' ? 'chip-ok' : '' ?>"><?= e(ucfirst($x['estado'])) ?></span></div><?php endforeach; ?>
      <?php if (!$mems): ?><p class="sub" style="font-size:15px">Aún no tienes planes.</p><?php endif; ?></div>
  </div>

<?php /* ================= PERFIL ================= */ elseif ($p === 'perfil'): ?>
  <div class="portal-grid">
    <form method="post" class="tarjeta"><?= csrf_campo() ?><input type="hidden" name="acc" value="perfil">
      <h3><?= icono('user') ?> Mis datos</h3>
      <div class="campos">
        <div class="campo ancho"><label>Nombre</label><input name="nombre" value="<?= e($u['nombre']) ?>" required></div>
        <div class="campo"><label>Correo</label><input value="<?= e($u['email']) ?>" disabled></div>
        <div class="campo"><label>WhatsApp</label><input name="telefono" value="<?= e($u['telefono']) ?>"></div>
        <div class="campo"><label>Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" value="<?= e($u['fecha_nacimiento']) ?>"></div>
        <div class="campo"><label>Sexo</label><select name="sexo"><option value="">—</option><option value="M" <?= $u['sexo'] === 'M' ? 'selected' : '' ?>>Hombre</option><option value="F" <?= $u['sexo'] === 'F' ? 'selected' : '' ?>>Mujer</option></select></div>
        <div class="campo"><label>Estatura (cm)</label><input type="number" name="estatura_cm" value="<?= e($u['estatura_cm']) ?>"></div>
        <div class="campo"><label>Nivel</label><select name="nivel"><?php foreach (['principiante' => 'Principiante', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'] as $k => $t): ?><option value="<?= $k ?>" <?= $u['nivel'] === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
        <div class="campo ancho"><label>Mi objetivo</label><textarea name="objetivo"><?= e($u['objetivo']) ?></textarea></div>
      </div>
      <button class="btn btn-rojo mt2" type="submit">Guardar</button>
      <p class="aviso-salud">Tu sexo, edad, nivel y peso se usan para calcular las cargas sugeridas de tus rutinas.</p>
    </form>
    <form method="post" class="tarjeta" id="clave"><?= csrf_campo() ?><input type="hidden" name="acc" value="clave">
      <h3><?= icono('lock') ?> Cambiar contraseña</h3>
      <div class="campos">
        <?php if (!$u['debe_cambiar']): ?><div class="campo ancho"><label>Contraseña actual</label><input type="password" name="actual" required autocomplete="current-password"></div><?php endif; ?>
        <div class="campo ancho"><label>Nueva contraseña</label><input type="password" name="nueva" required minlength="8" autocomplete="new-password"></div>
        <div class="campo ancho"><label>Repite la nueva</label><input type="password" name="repite" required minlength="8" autocomplete="new-password"></div>
      </div>
      <button class="btn btn-rojo mt2" type="submit">Cambiar</button>
      <div class="mt3"><a class="btn btn-borde btn-sm" href="<?= url('formulario.php') ?>"><?= icono('clipboard') ?> Actualizar mi formulario inicial</a></div>
    </form>
  </div>
<?php endif; ?>
</div>
</section>
<?php pie(); ?>
