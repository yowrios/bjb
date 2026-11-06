<?php
/** Temporadas (Navidad, Halloween, Día de la madre...) y beneficios (regalo de cumpleaños, referidos...). */
$lista = temporadas_lista();
if (es_post()) {
    csrf_verificar();
    if (post('acc') === 'temporadas') {
        guardar_ajuste('temporadas_auto', post('temporadas_auto') ? '1' : '0');
        $conf = [];
        foreach ($lista as $k => $t) {
            $d = (array)($_POST['t'][$k] ?? []);
            $modo = in_array($d['modo'] ?? 'auto', ['auto', 'on', 'off'], true) ? $d['modo'] : 'auto';
            $conf[$k] = ['modo' => $modo, 'titulo' => mb_substr(trim((string)($d['titulo'] ?? '')), 0, 60), 'mensaje' => mb_substr(trim((string)($d['mensaje'] ?? '')), 0, 240), 'enlace' => filter_var($d['enlace'] ?? '', FILTER_VALIDATE_URL) || str_starts_with((string)($d['enlace'] ?? ''), '#') ? (string)$d['enlace'] : '',
                'sin_colores' => !empty($d['sin_colores']) ? 1 : 0, 'sin_adornos' => !empty($d['sin_adornos']) ? 1 : 0];
        }
        $encendidas = array_keys(array_filter($conf, fn($c) => $c['modo'] === 'on'));
        if (count($encendidas) > 1) { foreach (array_slice($encendidas, 1) as $k) $conf[$k]['modo'] = 'auto'; flash('Solo puede haber una temporada encendida a mano: dejé ' . $lista[$encendidas[0]][0] . '.', 'info'); }
        guardar_ajuste('temporadas', json_encode($conf, JSON_UNESCAPED_UNICODE));
        flash('Temporadas guardadas.');
    } elseif (post('acc') === 'beneficios') {
        foreach (['regalo_cumple_activo', 'referidos_activo', 'valoracion_gratis_activo', 'beneficio_extra_activo'] as $k) guardar_ajuste($k, post($k) ? '1' : '0');
        foreach (['regalo_cumple_texto', 'referidos_texto', 'valoracion_gratis_texto', 'beneficio_extra_titulo', 'beneficio_extra_texto'] as $k) guardar_ajuste($k, mb_substr(post($k), 0, 400));
        guardar_ajuste('regalo_cumple_dias', (string)max(0, min(30, post_int('regalo_cumple_dias'))));
        flash('Beneficios guardados.');
    }
    redirigir(url('admin/?p=temporadas'));
}
$conf = ajuste_json('temporadas');
$activa = temporada_activa();
$h = hoy();
admin_cabecera('temporadas', 'Temporadas y beneficios');
?>
<div class="ad-caja" style="margin-bottom:20px;border-color:<?= $activa ? 'var(--volt)' : 'var(--linea)' ?>">
  <h2>🎉 Hoy <?= $activa ? 'se muestra la temporada <span style="color:var(--volt)">' . e($activa['nombre']) . '</span>' : 'no hay ninguna temporada activa' ?></h2>
  <p class="sec" style="margin:0">Cada temporada decora la web: cambia los colores, muestra una tarjeta de saludo con letra especial (aparece unos segundos y se recoge sola en un botoncito) y pone decoración animada que no estorba los botones. Al terminar la fecha, la web vuelve sola a tus colores.</p>
</div>
<form method="post" class="ad-caja"><?= csrf_campo() ?><input type="hidden" name="acc" value="temporadas">
  <h2><?= icono('calendar') ?> Temporadas <span class="der"><label class="check" style="margin:0"><input type="hidden" name="temporadas_auto" value="0"><input type="checkbox" name="temporadas_auto" value="1" <?= ajuste('temporadas_auto', '1') === '1' ? 'checked' : '' ?>> <span>Activar solas al llegar la fecha</span></label></span></h2>
  <p class="sec">Modo por temporada: <b>Automática</b> = se enciende sola en sus fechas · <b>Encendida</b> = se muestra ya, sin importar la fecha (solo una a la vez) · <b>Apagada</b> = nunca se muestra.</p>
  <div class="ad-tabla-envoltura"><table class="ad-tabla">
    <thead><tr><th>Temporada y decoración</th><th>Fechas <?= date('Y') ?></th><th>Modo</th><th>Saludo y mensaje (vacío = el sugerido)</th><th>Opciones</th><th></th></tr></thead><tbody>
    <?php $deco = ['navidad' => 'Nieve, Santa en su trineo cruzando la pantalla, árbol con luces y luces de colores bajo el menú', 'ano_nuevo' => 'Fuegos artificiales y confeti dorado',
      'san_valentin' => 'Corazones que suben, corazón que late y cupido volando', 'mujer' => 'Pétalos lilas cayendo y flores en la esquina', 'actividad' => 'Confeti verde y amarillo y un corredor que cruza',
      'nino' => 'Globos de colores que suben y confeti', 'madre' => 'Pétalos rosados, corazones y un ramo de flores', 'padre' => 'Estrellas doradas y confeti azul',
      'independencia' => 'Banderines tricolor bajo el menú y confeti amarillo, azul y rojo', 'amistad' => 'Corazones y globos que suben', 'octubre_rosa' => 'Pétalos rosados y el lazo rosa',
      'halloween' => 'Murciélagos volando, calabazas encendidas, telaraña, araña colgando y neblina', 'black_friday' => 'Etiquetas de descuento colgando y confeti'];
    foreach ($lista as $k => [$n, $col, $ad, $msg, $ini, $fin, $tit]): $c = $conf[$k] ?? []; $hoyEs = $h >= $ini && $h <= $fin; ?>
      <tr><td style="min-width:220px"><b><?= e($n) ?></b> <?= $hoyEs ? '<span class="chip chip-volt">Es su fecha</span>' : '' ?><br><span class="sec" style="font-size:12px"><?= e($deco[$k] ?? '') ?></span><br>
          <span style="display:inline-flex;gap:3px;vertical-align:middle;margin-left:6px"><?php foreach ($col as $cc): ?><i style="width:14px;height:14px;border-radius:4px;background:<?= e($cc) ?>;border:1px solid #444;display:inline-block"></i><?php endforeach; ?></span></td>
        <td class="sec" style="white-space:nowrap"><?= date('j/n', strtotime($ini)) ?> → <?= date('j/n', strtotime($fin)) ?></td>
        <td><select name="t[<?= $k ?>][modo]" style="padding:8px;min-width:130px"><?php foreach (['auto' => 'Automática', 'on' => 'Encendida', 'off' => 'Apagada'] as $mk => $mt): ?><option value="<?= $mk ?>" <?= ($c['modo'] ?? 'auto') === $mk ? 'selected' : '' ?>><?= $mt ?></option><?php endforeach; ?></select></td>
        <td style="min-width:260px"><input name="t[<?= $k ?>][titulo]" value="<?= e($c['titulo'] ?? '') ?>" placeholder="<?= e($tit) ?>" style="padding:8px;font-size:13px;font-weight:700;margin-bottom:4px"><input name="t[<?= $k ?>][mensaje]" value="<?= e($c['mensaje'] ?? '') ?>" placeholder="<?= e($msg) ?>" style="padding:8px;font-size:13px"><input name="t[<?= $k ?>][enlace]" value="<?= e($c['enlace'] ?? '') ?>" placeholder="Enlace opcional (ej. #planes)" style="padding:6px 8px;font-size:12px;margin-top:4px"></td>
        <td style="white-space:nowrap;font-size:13px"><label class="check"><input type="checkbox" name="t[<?= $k ?>][sin_colores]" value="1" <?= !empty($c['sin_colores']) ? 'checked' : '' ?>> <span>Mantener mis colores</span></label><label class="check"><input type="checkbox" name="t[<?= $k ?>][sin_adornos]" value="1" <?= !empty($c['sin_adornos']) ? 'checked' : '' ?>> <span>Sin decoración animada</span></label></td>
        <td><a class="btn btn-xs btn-borde" target="_blank" href="<?= url('?temporada=' . $k) ?>"><?= icono('eye') ?> Ver</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <p class="sec mt1">«Ver» te muestra cómo se vería la web con esa temporada (solo tú, con tu sesión iniciada). Las fechas del Día de la madre, del padre, del niño, Amor y amistad y Black Friday se calculan solas cada año según el calendario de Colombia.</p>
  <button class="btn btn-rojo mt1" type="submit">Guardar temporadas</button>
</form>

<form method="post" class="ad-caja mt2" id="beneficios"><?= csrf_campo() ?><input type="hidden" name="acc" value="beneficios">
  <h2><?= icono('star') ?> Beneficios para tus clientes</h2>
  <p class="sec">Los beneficios activos aparecen en la sección de planes de la portada y en el portal de cada cliente.</p>
  <div class="ad-rejilla">
    <div class="ad-caja" style="background:var(--negro)"><h2>🎂 Regalo de cumpleaños</h2>
      <?= campo('regalo_cumple_activo', 'Activo', ajuste('regalo_cumple_activo', '1'), 'checkbox') ?>
      <div class="campos mt1"><?= campo('regalo_cumple_texto', 'Regalo', ajuste('regalo_cumple_texto'), 'textarea', ['ancho' => true, 'filas' => 2]) ?><?= campo('regalo_cumple_dias', 'Días para reclamarlo después del cumpleaños', ajuste('regalo_cumple_dias', '7'), 'number') ?></div>
      <p class="sec">El día de su cumpleaños el cliente ve una felicitación en su portal con el botón «Reclamar mi regalo». En tu Resumen aparecen los cumpleaños de la semana con el saludo listo para WhatsApp. Necesita tener su fecha de nacimiento en la ficha.</p></div>
    <div class="ad-caja" style="background:var(--negro)"><h2>🤝 Trae a un amigo (referidos)</h2>
      <?= campo('referidos_activo', 'Activo', ajuste('referidos_activo', '0'), 'checkbox') ?>
      <div class="campos mt1"><?= campo('referidos_texto', 'Texto', ajuste('referidos_texto'), 'textarea', ['ancho' => true, 'filas' => 2]) ?></div></div>
    <div class="ad-caja" style="background:var(--negro)"><h2>📋 Valoración gratis</h2>
      <?= campo('valoracion_gratis_activo', 'Activo', ajuste('valoracion_gratis_activo', '0'), 'checkbox') ?>
      <div class="campos mt1"><?= campo('valoracion_gratis_texto', 'Texto', ajuste('valoracion_gratis_texto'), 'textarea', ['ancho' => true, 'filas' => 2]) ?></div></div>
    <div class="ad-caja" style="background:var(--negro)"><h2>⭐ Beneficio adicional (el que quieras)</h2>
      <?= campo('beneficio_extra_activo', 'Activo', ajuste('beneficio_extra_activo', '0'), 'checkbox') ?>
      <div class="campos mt1"><?= campo('beneficio_extra_titulo', 'Título', ajuste('beneficio_extra_titulo'), 'text', ['ancho' => true, 'placeholder' => 'Ej: Descuento para estudiantes']) ?><?= campo('beneficio_extra_texto', 'Texto', ajuste('beneficio_extra_texto'), 'textarea', ['ancho' => true, 'filas' => 2]) ?></div></div>
  </div>
  <button class="btn btn-rojo mt2" type="submit">Guardar beneficios</button>
</form>
<?php admin_pie();
