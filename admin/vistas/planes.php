<?php
$id = (int)($_GET['id'] ?? 0);
if (es_post()) {
    csrf_verificar();
    try {
        if (post('acc') === 'guardar') {
            $act = $id ? fila('SELECT * FROM planes WHERE id = ?', [$id]) : null;
            if (post('nombre') === '') throw new RuntimeException('Escribe el nombre del plan.');
            $d = ['nombre' => mb_strtoupper(post('nombre')), 'slug' => $act['slug'] ?? slugify(post('nombre')), 'tipo' => in_array(post('tipo'), ['entrenamiento', 'virtual', 'videos'], true) ? post('tipo') : 'entrenamiento',
                'etiqueta' => post('etiqueta'), 'subtitulo' => post('subtitulo'), 'descripcion' => post('descripcion'), 'incluye' => post('incluye'), 'sesiones' => post_int('sesiones'),
                'frecuencia' => post('frecuencia'), 'duracion_dias' => max(1, post_int('duracion_dias')), 'precio' => post_num('precio'), 'precio_antes' => post_num('precio_antes'),
                'modalidad' => post('modalidad'), 'incluye_videos' => post('incluye_videos') ? 1 : 0, 'destacado' => post('destacado') ? 1 : 0, 'visible' => post('visible') ? 1 : 0,
                'orden' => post_int('orden'), 'link_pago' => filter_var(post('link_pago'), FILTER_VALIDATE_URL) ? post('link_pago') : ''];
            if ($d['destacado']) q('UPDATE planes SET destacado = 0');
            if ($act) actualizar('planes', $id, $d);
            else { if (valor('SELECT COUNT(*) FROM planes WHERE slug = ?', [$d['slug']])) $d['slug'] .= '-' . substr(token(2), 0, 4); $id = insertar('planes', $d); }
            flash('Plan guardado. Ya se ve así en tu web.');
            redirigir(url('admin/?p=planes'));
        } elseif (post('acc') === 'precio') {
            foreach ((array)($_POST['precio_rapido'] ?? []) as $pid => $v) q('UPDATE planes SET precio = ? WHERE id = ?', [(float)preg_replace('/\D/', '', (string)$v), (int)$pid]);
            flash('Precios actualizados.');
        } elseif (post('acc') === 'borrar') {
            if (valor('SELECT COUNT(*) FROM membresias WHERE plan_id = ?', [$id])) { q('UPDATE planes SET visible = 0 WHERE id = ?', [$id]); flash('El plan tiene clientes, así que se ocultó en vez de borrarse.', 'info'); }
            else { q('DELETE FROM planes WHERE id = ?', [$id]); flash('Plan borrado.'); }
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'error'); }
    redirigir(url('admin/?p=planes'));
}
if ($id || isset($_GET['nuevo'])) {
    $pl = $id ? fila('SELECT * FROM planes WHERE id = ?', [$id]) : ['nombre' => '', 'tipo' => 'entrenamiento', 'etiqueta' => '', 'subtitulo' => '', 'descripcion' => '', 'incluye' => '', 'sesiones' => 8, 'frecuencia' => '',
        'duracion_dias' => 30, 'precio' => 0, 'precio_antes' => 0, 'modalidad' => 'presencial', 'incluye_videos' => 0, 'destacado' => 0, 'visible' => 1, 'orden' => 10, 'link_pago' => ''];
    admin_cabecera('planes', $id ? 'Editar ' . $pl['nombre'] : 'Nuevo plan');
    ?>
    <form method="post" class="ad-caja" style="max-width:980px"><?= csrf_campo() ?><input type="hidden" name="acc" value="guardar">
      <div class="campos">
        <?= campo('nombre', 'Nombre del plan *', $pl['nombre'], 'text', ['required' => true]) ?>
        <?= campo('tipo', 'Tipo', $pl['tipo'], 'select', ['opciones' => ['entrenamiento' => 'Entrenamiento con sesiones', 'virtual' => 'Asesoría virtual', 'videos' => 'Acceso a la biblioteca de videos'], 'ayuda' => '«Biblioteca» da acceso a todos los videos por los días de duración.']) ?>
        <?= campo('precio', 'Precio (COP) *', (int)$pl['precio'], 'text', ['inputmode' => 'numeric', 'ayuda' => 'Escribe 0 para mostrar «Consúltalo».']) ?>
        <?= campo('precio_antes', 'Precio antes (opcional, para mostrar descuento)', (int)$pl['precio_antes'] ?: '', 'text', ['inputmode' => 'numeric']) ?>
        <?= campo('etiqueta', 'Etiqueta', $pl['etiqueta'], 'text', ['placeholder' => 'El más elegido']) ?>
        <?= campo('subtitulo', 'Frase corta', $pl['subtitulo'], 'text', ['placeholder' => 'Supera tus límites']) ?>
        <?= campo('sesiones', 'Número de sesiones', $pl['sesiones'], 'number', ['min' => 0, 'ayuda' => '0 si no aplica (virtual o videos).']) ?>
        <?= campo('frecuencia', 'Frecuencia', $pl['frecuencia'], 'text', ['placeholder' => '3 veces por semana']) ?>
        <?= campo('duracion_dias', 'Vigencia (días)', $pl['duracion_dias'], 'number', ['min' => 1, 'ayuda' => 'Cuántos días tiene el cliente para usar el plan.']) ?>
        <?= campo('modalidad', 'Modalidad', $pl['modalidad'], 'select', ['opciones' => ['presencial' => 'Presencial', 'virtual' => 'Virtual', 'mixta' => 'Presencial o virtual']]) ?>
        <?= campo('descripcion', 'Descripción', $pl['descripcion'], 'textarea', ['ancho' => true]) ?>
        <?= campo('incluye', 'Qué incluye (un beneficio por línea)', $pl['incluye'], 'textarea', ['ancho' => true, 'filas' => 7]) ?>
        <?= campo('link_pago', 'Enlace de pago con tarjeta / PSE (opcional)', $pl['link_pago'], 'url', ['ancho' => true, 'placeholder' => 'https://checkout.wompi.co/l/...', 'ayuda' => 'Si creas un enlace de pago en Wompi, Bold o Mercado Pago, pégalo aquí y aparece un botón en la página de pago.']) ?>
        <?= campo('orden', 'Orden en la web', $pl['orden'], 'number') ?>
        <div></div>
        <?= campo('incluye_videos', 'Incluye acceso a todos los videos mientras esté activo', $pl['incluye_videos'], 'checkbox') ?>
        <?= campo('destacado', 'Destacarlo en la web (borde rojo)', $pl['destacado'], 'checkbox') ?>
        <?= campo('visible', 'Mostrar en la web', $pl['visible'], 'checkbox') ?>
      </div>
      <div class="ad-acciones mt2" style="justify-content:flex-start"><button class="btn btn-rojo" type="submit">Guardar plan</button><a class="btn btn-borde" href="<?= url('admin/?p=planes') ?>">Cancelar</a></div>
    </form>
    <?php if ($id): ?><form method="post" action="<?= url('admin/?p=planes&id=' . $id) ?>" data-confirmar="¿Borrar este plan?" class="mt2"><?= csrf_campo() ?><input type="hidden" name="acc" value="borrar"><button class="btn btn-sm btn-borde" type="submit"><?= icono('trash') ?> Borrar plan</button></form><?php endif; ?>
    <?php admin_pie(); return;
}
$planes = filas('SELECT p.*, (SELECT COUNT(*) FROM membresias m WHERE m.plan_id = p.id AND m.estado = \'activa\') AS activos FROM planes p ORDER BY p.orden, p.id');
admin_cabecera('planes', 'Planes y precios');
?>
<div class="ad-nota">Cambia el precio aquí y presiona <b>Guardar precios</b>: se actualiza al instante en la web, el catálogo y la página de pago. Para cambiar textos o beneficios, entra a <b>Editar</b>.</div>
<form method="post"><?= csrf_campo() ?><input type="hidden" name="acc" value="precio">
<div class="ad-caja ad-tabla-envoltura"><table class="ad-tabla">
  <thead><tr><th>Plan</th><th>Tipo</th><th>Sesiones</th><th class="num">Clientes activos</th><th>En la web</th><th style="width:180px">Precio</th><th></th></tr></thead><tbody>
  <?php foreach ($planes as $p): ?>
    <tr><td><b><?= e($p['nombre']) ?></b> <?= $p['destacado'] ? '<span class="chip chip-rojo">Destacado</span>' : '' ?><br><span class="sec"><?= e($p['subtitulo']) ?></span></td>
      <td class="sec"><?= ['entrenamiento' => 'Entrenamiento', 'virtual' => 'Virtual', 'videos' => 'Biblioteca'][$p['tipo']] ?? e($p['tipo']) ?></td>
      <td><?= $p['sesiones'] ? (int)$p['sesiones'] . ' · ' . e($p['frecuencia']) : e($p['frecuencia']) ?></td>
      <td class="num"><?= (int)$p['activos'] ?></td>
      <td><?= $p['visible'] ? '<span class="chip chip-ok">Visible</span>' : '<span class="chip">Oculto</span>' ?></td>
      <td><input name="precio_rapido[<?= (int)$p['id'] ?>]" value="<?= (int)$p['precio'] ?>" inputmode="numeric" style="padding:9px 12px;font-weight:800"></td>
      <td><div class="ad-acciones"><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=planes&id=' . $p['id']) ?>"><?= icono('edit') ?> Editar</a><a class="btn-icono" target="_blank" href="<?= url('#plan-' . $p['slug']) ?>" title="Ver en la web"><?= icono('eye') ?></a></div></td></tr>
  <?php endforeach; ?></tbody></table>
  <div class="ad-acciones mt2" style="justify-content:space-between"><a class="btn btn-borde btn-sm" href="<?= url('admin/?p=planes&nuevo=1') ?>"><?= icono('plus') ?> Nuevo plan</a><button class="btn btn-rojo" type="submit">Guardar precios</button></div>
</div></form>
<div class="ad-nota mt2">Los planes <b>TU EVOLUCIÓN ONLINE</b> y <b>BIBLIOTECA DE MÁQUINAS</b> están creados pero ocultos: ponles precio, revisa el texto y marca «Mostrar en la web» cuando quieras ofrecerlos.</div>
<?php admin_pie();
