<?php
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
$v = fila('SELECT * FROM videos WHERE slug = ? AND visible = 1', [(string)($_GET['s'] ?? '')]);
if (!$v) { http_response_code(404); cabecera('No encontrada', ['body' => 'pagina']); echo '<section class="cabeza-pag"><div class="envoltura"><h1 class="titulo">Máquina no encontrada</h1><a class="btn btn-rojo" href="' . url('maquinas.php') . '">Ver biblioteca</a></div></section>'; pie(); exit; }
$u = usuario();
$ok = puede_ver_video($u, $v);
if ($ok) { q('UPDATE videos SET vistas = vistas + 1 WHERE id = ?', [$v['id']]); evento('video', $v['slug'], $v['titulo']); }
$pendiente = $u ? fila("SELECT * FROM pagos WHERE usuario_id = ? AND concepto_tipo = 'video' AND concepto_id = ? AND estado = 'pendiente'", [$u['id'], $v['id']]) : null;
$embed = video_embed($v['video_url']);
$peso = $u ? (float)valor('SELECT peso FROM medidas WHERE usuario_id = ? AND peso IS NOT NULL ORDER BY fecha DESC, id DESC LIMIT 1', [$u['id']]) : 0;
$edad = ($u && $u['fecha_nacimiento']) ? (int)date_diff(date_create($u['fecha_nacimiento']), date_create('today'))->y : '';
$bib = fila("SELECT * FROM planes WHERE tipo = 'videos' AND visible = 1 ORDER BY orden LIMIT 1");
$enPaquete = fila('SELECT p.* FROM paquetes p JOIN paquete_videos pv ON pv.paquete_id = p.id WHERE pv.video_id = ? AND p.visible = 1 ORDER BY p.precio LIMIT 1', [$v['id']]);
$otros = filas('SELECT * FROM videos WHERE visible = 1 AND id <> ? AND categoria = ? ORDER BY orden LIMIT 4', [$v['id'], $v['categoria']]);
$musc = array_filter(explode(',', (string)$v['musculos']));
cabecera($v['titulo'], ['activo' => 'maquinas', 'body' => 'pagina', 'desc' => mb_substr((string)$v['descripcion'], 0, 160)]);
?>
<section class="cabeza-pag" style="padding-bottom:30px">
  <div class="envoltura">
    <div class="migas"><a href="<?= url('') ?>">Inicio</a> / <a href="<?= url('maquinas.php') ?>">Máquinas</a> / <?= e($v['titulo']) ?></div>
    <h1 class="titulo" style="font-size:clamp(40px,6vw,74px)"><?= e($v['titulo']) ?></h1>
    <div class="plan-meta"><span class="chip"><?= icono('target') ?> <?= e($v['categoria']) ?></span><span class="chip"><?= icono('bolt') ?> Nivel <?= e($v['nivel']) ?></span>
      <?php foreach ($musc as $m): ?><span class="chip chip-rojo"><?= e(nombre_musculo($m)) ?></span><?php endforeach; ?></div>
  </div>
</section>
<section class="seccion" style="padding-top:20px">
  <div class="envoltura detalle">
    <div>
      <div class="reproductor">
        <?php if ($ok && $embed): ?>
          <iframe src="<?= e($embed) ?>" title="<?= e($v['titulo']) ?>" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
        <?php elseif ($ok && $v['video_archivo']): ?>
          <video controls playsinline preload="metadata" controlslist="nodownload" oncontextmenu="return false" <?= $v['imagen'] ? 'poster="' . e(img_publica($v['imagen'])) . '"' : '' ?>><source src="<?= e(media_url('video', (int)$v['id'])) ?>"></video>
        <?php else: ?>
          <div class="bloqueo-fondo"><?= portada_video($v) ?></div>
          <div class="bloqueo">
            <?php if ($ok): ?>
              <div class="candado" style="background:var(--volt);color:#0a0a0c"><?= icono('video') ?></div>
              <h3>Video en producción</h3><p class="sub" style="font-size:15px">Bryan está grabando este video. Mientras tanto, sigue los pasos y la carga sugerida.</p>
            <?php elseif ($pendiente): ?>
              <div class="candado" style="background:var(--alerta)"><?= icono('timer') ?></div>
              <h3>Pago en revisión</h3><p class="sub" style="font-size:15px">Recibimos tu comprobante. Bryan lo revisa y te habilita el video muy pronto.</p>
            <?php else: ?>
              <div class="candado"><?= icono('lock') ?></div>
              <h3>Video premium</h3>
              <p class="sub" style="font-size:15px;margin-bottom:20px">Aprende la técnica correcta paso a paso con Bryan.</p>
              <div class="hero-ctas" style="justify-content:center;margin:0">
                <?php if ((float)$v['precio'] > 0): ?><a class="btn btn-rojo" href="<?= url('pagar.php?video=' . urlencode($v['slug'])) ?>" data-ev="pago:video-<?= e($v['slug']) ?>"><?= icono('unlock') ?> Desbloquear · <?= pesos($v['precio']) ?></a><?php endif; ?>
                <?php if ($enPaquete): ?><a class="btn btn-borde" href="<?= url('maquinas.php#paquete-' . $enPaquete['slug']) ?>">Paquete <?= e($enPaquete['nombre']) ?> · <?= pesos($enPaquete['precio']) ?></a>
                <?php elseif ($bib && (float)$bib['precio'] > 0): ?><a class="btn btn-borde" href="<?= url('pagar.php?plan=' . urlencode($bib['slug'])) ?>">Todos por <?= pesos($bib['precio']) ?></a>
                <?php else: ?><a class="btn btn-borde" href="<?= url('#planes') ?>">Incluido en planes</a><?php endif; ?>
              </div>
              <?php if (!$u): ?><p class="mt2" style="font-size:14px;color:var(--txt-2)">¿Ya lo compraste? <a href="<?= url('ingresar.php?r=' . urlencode('maquina.php?s=' . $v['slug'])) ?>" style="color:var(--rojo)">Ingresa a tu cuenta</a></p><?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="tarjeta mt2">
        <p style="font-size:17px;color:#dcdce0"><?= e($v['descripcion']) ?></p>
        <div class="dos-col mt2" style="grid-template-columns:1fr 1.2fr;align-items:center">
          <div style="background:var(--negro);border-radius:16px;padding:14px"><?= mapa_musculos((string)$v['musculos']) ?></div>
          <div>
            <h3 style="font-size:20px">Cómo se hace</h3>
            <ol class="pasos-lista"><?php foreach (lineas($v['pasos']) as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ol>
          </div>
        </div>
      </div>
      <div class="dos-col mt2">
        <?php if (lineas($v['errores'])): ?><div class="tarjeta"><h3 style="font-size:20px">Errores comunes</h3><ul class="lista-x"><?php foreach (lineas($v['errores']) as $p): ?><li><?= icono('x') ?><?= e($p) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if (lineas($v['tips'])): ?><div class="tarjeta"><h3 style="font-size:20px">Consejos de Bryan</h3><ul class="lista-check"><?php foreach (lineas($v['tips']) as $p): ?><li><?= icono('check') ?><?= e($p) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      </div>
    </div>

    <aside class="carga-caja" id="cargaCaja">
      <span class="antetitulo">Tu carga ideal</span>
      <h2 style="font-family:var(--display);font-weight:400;font-size:34px;text-transform:uppercase">¿Con cuánto peso empiezo?</h2>
      <?php if ((float)$v['coef_hombre'] > 0): ?>
        <div class="campos mt1">
          <div class="campo"><label for="kPeso">Tu peso (kg)</label><input id="kPeso" type="number" inputmode="decimal" min="25" max="300" step="0.1" value="<?= $peso ?: '' ?>" placeholder="Ej: 70"></div>
          <div class="campo"><label for="kEdad">Edad</label><input id="kEdad" type="number" inputmode="numeric" min="12" max="95" value="<?= e($edad) ?>" placeholder="30"></div>
          <div class="campo ancho"><div class="opciones"><label><input type="radio" name="kSexo" value="M" <?= ($u['sexo'] ?? 'M') !== 'F' ? 'checked' : '' ?>><span>Hombre</span></label><label><input type="radio" name="kSexo" value="F" <?= ($u['sexo'] ?? '') === 'F' ? 'checked' : '' ?>><span>Mujer</span></label></div></div>
          <div class="campo"><label for="kNivel">Nivel</label><select id="kNivel"><?php foreach (['principiante' => 'Principiante', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'] as $k => $t): ?><option value="<?= $k ?>" <?= ($u['nivel'] ?? '') === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
          <div class="campo"><label for="kMeta">Objetivo</label><select id="kMeta"><option value="hipertrofia">Ganar músculo</option><option value="fuerza">Fuerza</option><option value="grasa">Quemar grasa</option><option value="resistencia">Resistencia</option></select></div>
        </div>
        <div class="carga-res" id="kRes" aria-live="polite"></div>
        <p class="aviso-salud"><?= e($v['nota_carga']) ?>. Es un punto de partida: si te sobran más de 3 repeticiones con buena técnica, sube; si no completas la serie, baja. Si tienes una lesión, consulta primero.</p>
        <a class="btn btn-borde btn-bloque mt1" id="kCrono" href="<?= url('cronometro.php') ?>"><?= icono('timer') ?> Abrir cronómetro con este descanso</a>
        <details class="mt2" style="font-size:14px;color:var(--txt-2)"><summary style="cursor:pointer;font-weight:700">¿Ya sabes cuánto levantas? Calcula tu máximo</summary>
          <div class="campos mt1"><div class="campo"><label for="rmKg">Peso que moviste</label><input id="rmKg" type="number" inputmode="decimal"></div><div class="campo"><label for="rmReps">Repeticiones</label><input id="rmReps" type="number" inputmode="numeric" min="1" max="20"></div></div>
          <div id="rmRes" class="mt1"></div>
        </details>
      <?php else: ?>
        <p class="sub" style="font-size:15px"><?= e($v['nota_carga'] ?: 'Este ejercicio usa tu peso corporal.') ?></p>
        <a class="btn btn-rojo btn-bloque" href="<?= url('cronometro.php') ?>"><?= icono('timer') ?> Abrir cronómetro</a>
      <?php endif; ?>
    </aside>
  </div>
</section>
<?php if ($otros): ?>
<section class="seccion seccion-oscura" style="padding:70px 0">
  <div class="envoltura">
    <h2 class="titulo" style="font-size:44px">Más de <?= e($v['categoria']) ?></h2>
    <div class="maquinas mt2">
      <?php foreach ($otros as $m): ?>
        <a class="maq" href="<?= url('maquina.php?s=' . urlencode($m['slug'])) ?>"><div class="maq-img"><?= portada_video($m) ?></div><div class="maq-cuerpo"><h3><?= e($m['titulo']) ?></h3><div class="maq-pie"><span><?= $m['gratis'] ? 'Gratis' : pesos($m['precio']) ?></span><?= icono('arrow') ?></div></div></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<script>
(function () {
  var V = <?= json_encode(['coef_hombre' => $v['coef_hombre'], 'coef_mujer' => $v['coef_mujer'], 'incremento' => $v['incremento'], 'nombre' => $v['titulo']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  var base = <?= json_encode(url('cronometro.php')) ?>;
  function g(id) { return document.getElementById(id); }
  function calc() {
    if (!g('kRes') || !window.bjCarga) return;
    var peso = parseFloat(g('kPeso').value), sexo = (document.querySelector('input[name=kSexo]:checked') || {}).value || 'M';
    var c = window.bjCarga(V, peso, sexo, g('kNivel').value, g('kMeta').value, parseInt(g('kEdad').value, 10) || 30);
    if (!c) { g('kRes').innerHTML = '<p class="sub" style="font-size:15px">Escribe tu peso para calcular.</p>'; return; }
    g('kRes').innerHTML = '<div class="res-grande">' + c.kg + '<small>kg</small></div><div class="res-lista"><div><b>' + c.series + ' × ' + c.reps + '</b><span>Series × reps</span></div><div><b>' + c.descanso + ' s</b><span>Descanso</span></div></div><span class="chip">Rango: ' + c.min + ' a ' + c.max + ' kg</span>';
    g('kCrono').href = base + '?modo=series&series=' + c.series + '&descanso=' + c.descanso + '&nombre=' + encodeURIComponent(V.nombre + ' · ' + c.kg + ' kg');
  }
  function rm() {
    var kg = parseFloat(g('rmKg').value), r = parseInt(g('rmReps').value, 10);
    if (!(kg > 0 && r > 0)) { g('rmRes').innerHTML = ''; return; }
    var m = r === 1 ? kg : kg * (1 + r / 30);
    g('rmRes').innerHTML = '<b style="font-size:22px;color:var(--txt)">Máximo estimado: ' + m.toFixed(1) + ' kg</b><br>Fuerza (85%): ' + (m * .85).toFixed(1) + ' kg · Músculo (72%): ' + (m * .72).toFixed(1) + ' kg · Resistencia (60%): ' + (m * .6).toFixed(1) + ' kg';
  }
  ['kPeso', 'kEdad', 'kNivel', 'kMeta'].forEach(function (id) { var el = g(id); if (el) { el.addEventListener('input', calc); el.addEventListener('change', calc); } });
  document.querySelectorAll('input[name=kSexo]').forEach(function (el) { el.addEventListener('change', calc); });
  ['rmKg', 'rmReps'].forEach(function (id) { var el = g(id); if (el) el.addEventListener('input', rm); });
  document.addEventListener('DOMContentLoaded', calc);
  if (document.readyState !== 'loading') setTimeout(calc, 0);
})();
</script>
<?php pie(); ?>
