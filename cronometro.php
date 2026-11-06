<?php
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
$u = usuario();
$rutinas = !$u ? [] : (es_admin()
    ? filas('SELECT id, nombre FROM rutinas WHERE activa = 1 ORDER BY plantilla, id DESC') // Bryan puede probar cualquier rutina
    : filas('SELECT id, nombre FROM rutinas WHERE usuario_id = ? AND activa = 1 ORDER BY id DESC', [$u['id']]));
$cfg = ['pausa' => (int)ajuste('pausa_aviso_seg', '45'), 'logueado' => (bool)$u, 'rutinas' => $rutinas, 'nombre' => $u ? explode(' ', $u['nombre'])[0] : ''];
$mini = !empty($_GET['mini']);
cabecera('Cronómetro de entrenamiento', ['sin_nav' => true, 'body' => 'crono' . ($mini ? ' mini' : ''), 'sin_visita' => $mini, 'desc' => 'Cronómetro de gimnasio gratis: series, descansos, intervalos HIIT y Tabata con aviso de sonido, voz y vibración. Sin descargar nada.',
    'head' => '<link rel="stylesheet" href="' . asset('css/crono.css') . '">']);
?>
<div class="cr-app">
  <div class="cr-top">
    <a class="cr-ib" href="<?= url('') ?>" data-volver aria-label="Volver" title="Volver a donde estabas">←</a>
    <a class="marca" href="<?= url('') ?>"><img src="<?= imagen_marca('logo') ?>" alt=""><span><?= e(ajuste('marca')) ?></span></a>
    <div class="acc">
      <button class="cr-ib" id="bSonido" type="button" aria-label="Sonido" title="Sonido"><?= icono('volume') ?></button>
      <button class="cr-ib" id="bEnfoque" type="button" aria-label="Modo enfoque" title="Solo el reloj"><?= icono('expand') ?></button>
      <a class="cr-ib" href="<?= url($u ? ($u['rol'] === 'admin' ? 'admin/' : 'cliente/') : 'ingresar.php') ?>" aria-label="Mi portal" title="Mi portal"><?= icono('user') ?></a>
    </div>
  </div>

  <section class="cr-pantalla" id="pantalla" data-fase="listo" aria-live="polite">
    <div class="cr-fase" id="fase">Listo</div>
    <div class="cr-ejercicio" id="ejercicio"></div>
    <div class="cr-carga" id="carga"></div>
    <div class="cr-anillo">
      <svg viewBox="0 0 220 220" aria-hidden="true"><circle class="f" cx="110" cy="110" r="96"/><circle class="b" id="arco" cx="110" cy="110" r="96" stroke-dasharray="603.19" stroke-dashoffset="0"/></svg>
      <div class="cr-tiempo"><b id="tiempo">0:00</b><span id="tiempoSub">Presiona play</span></div>
    </div>
    <div class="cr-contador" id="contador"></div>
    <div class="cr-pausa-aviso" id="avisoPausa">⏸ En pausa · <span id="pausaSeg">0</span> s. Cuando estés listo, presiona play.</div>
    <div class="cr-controles">
      <button class="cr-btn" id="bReiniciar" type="button" aria-label="Reiniciar"><?= icono('refresh') ?></button>
      <button class="cr-btn grande" id="bPlay" type="button" aria-label="Iniciar"><?= icono('play') ?></button>
      <button class="cr-btn" id="bSaltar" type="button" aria-label="Saltar fase"><?= icono('skip') ?></button>
    </div>
    <div class="cr-hecho"><button class="btn btn-volt oculto" id="bHecho" type="button"><?= icono('check') ?> Terminé la serie</button><button class="btn btn-borde oculto" id="bVuelta" type="button">Vuelta</button></div>
    <div class="cr-registro" id="registro"><label for="kgUsado" style="font-size:13px;color:var(--txt-2);white-space:nowrap">Kg que usaste</label><input id="kgUsado" type="number" inputmode="decimal" step="0.5"><button class="btn btn-sm btn-borde" id="bKg" type="button">Guardar</button></div>
    <div class="cr-siguiente" id="siguiente"></div>
    <div class="cr-vueltas" id="vueltas"></div>
  </section>

  <aside class="cr-panel" id="panel">
    <div class="cr-modos" role="tablist">
      <button type="button" data-modo="series" class="on"><?= icono('dumbbell') ?>Series</button>
      <button type="button" data-modo="intervalos"><?= icono('flame') ?>Intervalos</button>
      <button type="button" data-modo="rutina" <?= $u ? '' : 'hidden' ?>><?= icono('clipboard') ?>Rutina</button>
      <button type="button" data-modo="libre"><?= icono('timer') ?>Libre</button>
      <button type="button" data-modo="cuenta"><?= icono('bell') ?>Cuenta</button>
    </div>

    <div data-cfg="series">
      <p class="etq" style="margin:0 0 8px">Tiempos sugeridos según tu objetivo</p>
      <div class="cr-sugerencias" id="sugSeries"></div>
      <div class="cr-fila"><label>Series<small>Cuántas veces repites el ejercicio</small></label><div class="cr-num" data-n="series" data-min="1" data-max="20" data-paso="1"></div></div>
      <div class="cr-fila"><label>Descanso entre series<small>En segundos</small></label><div class="cr-num" data-n="descanso" data-min="5" data-max="600" data-paso="15"></div></div>
      <div class="cr-fila"><label>Serie con tiempo<small>0 = tú marcas «Terminé la serie»</small></label><div class="cr-num" data-n="trabajoSerie" data-min="0" data-max="300" data-paso="5"></div></div>
      <div class="cr-fila"><label for="nombreEj">Ejercicio<small>Opcional</small></label><input id="nombreEj" type="text" maxlength="60" placeholder="Ej: Prensa 80 kg" style="max-width:200px"></div>
      <p class="etq" style="margin:14px 0 0">Solo necesitas un descanso rápido:</p>
      <div class="cr-rapidos" id="rapidos"><button type="button" data-s="30">30 s</button><button type="button" data-s="60">1 min</button><button type="button" data-s="90">1:30</button><button type="button" data-s="120">2 min</button><button type="button" data-s="180">3 min</button></div>
    </div>

    <div data-cfg="intervalos" class="oculto">
      <p class="etq" style="margin:0 0 8px">Rutinas de intervalos</p>
      <div class="cr-sugerencias" id="sugIntervalos"></div>
      <div class="cr-fila"><label>Trabajo<small>Segundos de esfuerzo</small></label><div class="cr-num" data-n="trabajo" data-min="5" data-max="600" data-paso="5"></div></div>
      <div class="cr-fila"><label>Descanso<small>Segundos de recuperación</small></label><div class="cr-num" data-n="descansoInt" data-min="0" data-max="600" data-paso="5"></div></div>
      <div class="cr-fila"><label>Rondas<small>Trabajo + descanso</small></label><div class="cr-num" data-n="rondas" data-min="1" data-max="50" data-paso="1"></div></div>
      <div class="cr-fila"><label>Bloques<small>Cuántas veces repites todo</small></label><div class="cr-num" data-n="bloques" data-min="1" data-max="10" data-paso="1"></div></div>
      <div class="cr-fila"><label>Descanso entre bloques<small>Segundos</small></label><div class="cr-num" data-n="descansoBloque" data-min="0" data-max="600" data-paso="15"></div></div>
    </div>

    <div data-cfg="rutina" class="oculto">
      <?php if ($rutinas): ?>
        <div class="campo"><label for="selRutina">Tu rutina</label><select id="selRutina"><?php foreach ($rutinas as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['nombre']) ?></option><?php endforeach; ?></select></div>
        <div class="campo mt1"><label for="selDia">Día</label><select id="selDia"></select></div>
        <div class="cr-rutina-lista mt1" id="listaRutina"></div>
      <?php else: ?>
        <p class="sub" style="font-size:15px">Aún no tienes rutinas asignadas. Cuando Bryan te asigne una, aparecerá aquí con tus series, descansos y cargas.</p>
      <?php endif; ?>
    </div>

    <div data-cfg="libre" class="oculto"><p class="sub" style="font-size:15px">Cronómetro que cuenta hacia arriba. Usa «Vuelta» para marcar tiempos (por ejemplo, cada vuelta a la pista o cada ejercicio de un circuito).</p></div>

    <div data-cfg="cuenta" class="oculto">
      <div class="cr-fila"><label>Minutos</label><div class="cr-num" data-n="cuentaMin" data-min="0" data-max="180" data-paso="1"></div></div>
      <div class="cr-fila"><label>Segundos</label><div class="cr-num" data-n="cuentaSeg" data-min="0" data-max="59" data-paso="5"></div></div>
      <div class="cr-rapidos"><button type="button" data-cuenta="300">5 min</button><button type="button" data-cuenta="600">10 min</button><button type="button" data-cuenta="1200">20 min</button><button type="button" data-cuenta="1800">30 min</button></div>
    </div>

    <div class="cr-resumen-cfg" id="resumenCfg"></div>

    <details class="cr-ajustes">
      <summary>Avisos y pantalla</summary>
      <div class="cr-fila"><label>Pitidos<small>Al cambiar de fase y 3-2-1</small></label><label class="switch"><input type="checkbox" data-op="sonido" checked><i></i></label></div>
      <div class="cr-fila"><label>Voz<small>«Trabajo», «Descanso», «Última serie»</small></label><label class="switch"><input type="checkbox" data-op="voz" checked><i></i></label></div>
      <div class="cr-fila"><label>Vibración<small>En celulares compatibles</small></label><label class="switch"><input type="checkbox" data-op="vibrar" checked><i></i></label></div>
      <div class="cr-fila"><label>Pantalla siempre encendida<small>Mientras el cronómetro corre</small></label><label class="switch"><input type="checkbox" data-op="despierto" checked><i></i></label></div>
      <div class="cr-fila"><label>Preparación<small>Segundos antes de empezar</small></label><div class="cr-num" data-n="prep" data-min="0" data-max="30" data-paso="5"></div></div>
      <div class="cr-fila"><label>Avisar si me quedo en pausa<small>Segundos (0 = no avisar)</small></label><div class="cr-num" data-n="pausaAviso" data-min="0" data-max="600" data-paso="15"></div></div>
      <div class="cr-fila"><label>Avisos aunque cambie de app<small>Notificación al terminar el descanso</small></label><button class="btn btn-xs btn-borde" id="bNotif" type="button">Activar</button></div>
    </details>
    <div class="cr-instalar" id="instalar"><button class="btn btn-rojo btn-bloque" id="bInstalar" type="button"><?= icono('download') ?> Instalar como app</button></div>
    <p class="aviso-salud">Consejo: agrega esta página a la pantalla de inicio de tu celular y ábrela como una app, incluso sin internet.</p>
  </aside>
</div>

<div class="cr-fin" id="fin" role="dialog" aria-modal="true">
  <div class="caja">
    <h2>¡Terminaste!</h2>
    <p id="finTexto" class="sub" style="margin-inline:auto"></p>
    <div id="finGuardar" class="oculto">
      <p class="etq">¿Cómo te sentiste?</p>
      <div class="cr-caras" id="caras"><button type="button" data-v="1">😫</button><button type="button" data-v="2">😕</button><button type="button" data-v="3">🙂</button><button type="button" data-v="4">💪</button><button type="button" data-v="5">🔥</button></div>
      <input id="finNota" type="text" maxlength="200" placeholder="Nota para Bryan (opcional)">
      <button class="btn btn-volt btn-bloque mt1" id="bGuardar" type="button">Guardar en mi historial</button>
    </div>
    <button class="btn btn-borde btn-bloque mt1" id="bCerrarFin" type="button">Cerrar</button>
  </div>
</div>
<script>window.CRONO = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php pie(['sin_pie' => true, 'sin_wa' => true, 'sin_crono' => true, 'scripts' => '<script src="' . asset('js/cronometro.js') . '" defer></script>']); ?>
