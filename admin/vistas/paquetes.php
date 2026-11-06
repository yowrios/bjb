<?php
/** Paquetes de videos: varios videos (y una rutina) por un solo precio. */
$id = (int)($_GET['id'] ?? 0);
if (es_post()) {
    csrf_verificar();
    try {
        if (post('acc') === 'guardar') {
            if (post('nombre') === '') throw new RuntimeException('Escribe el nombre del paquete.');
            $vids = array_values(array_unique(array_map('intval', (array)($_POST['videos'] ?? []))));
            if (!$vids) throw new RuntimeException('Elige al menos un video.');
            $act = $id ? fila('SELECT * FROM paquetes WHERE id = ?', [$id]) : null;
            $d = ['nombre' => post('nombre'), 'descripcion' => post('descripcion'), 'precio' => post_num('precio'), 'precio_antes' => post_num('precio_antes'),
                'rutina_id' => post_int('rutina_id') ?: null, 'dias_acceso' => max(1, post_int('dias_acceso')), 'visible' => post('visible') ? 1 : 0, 'orden' => post_int('orden')];
            $d['imagen'] = imagen_post('imagen', $act['imagen'] ?? null, 'paquetes');
            if ($act) actualizar('paquetes', $id, $d);
            else { $d['slug'] = slugify(post('nombre')); if (valor('SELECT COUNT(*) FROM paquetes WHERE slug = ?', [$d['slug']])) $d['slug'] .= '-' . substr(token(2), 0, 4); $d['creado'] = ahora(); $id = insertar('paquetes', $d); }
            q('DELETE FROM paquete_videos WHERE paquete_id = ?', [$id]);
            foreach ($vids as $o => $v) insertar('paquete_videos', ['paquete_id' => $id, 'video_id' => $v, 'orden' => $o + 1]);
            if (post('crear_rutina') && !$d['rutina_id']) {
                $rid = insertar('rutinas', ['nombre' => post('nombre'), 'descripcion' => post('descripcion'), 'objetivo' => 'hipertrofia', 'nivel' => 'principiante', 'plantilla' => 1, 'usuario_id' => null, 'activa' => 1, 'creado' => ahora()]);
                foreach ($vids as $o => $v) insertar('rutina_ejercicios', ['rutina_id' => $rid, 'dia' => 'Rutina', 'orden' => $o + 1, 'video_id' => $v, 'nombre' => '', 'series' => 3, 'reps' => '12', 'descanso_seg' => 75, 'trabajo_seg' => 0, 'carga' => '', 'nota' => '']);
                q('UPDATE paquetes SET rutina_id = ? WHERE id = ?', [$rid, $id]);
                flash('Paquete guardado y rutina creada. Ajusta series y descansos en Rutinas.');
                redirigir(url('admin/?p=rutinas&id=' . $rid));
            }
            flash('Paquete guardado.');
        } elseif (post('acc') === 'borrar' && $id) {
            q('DELETE FROM paquete_videos WHERE paquete_id = ?', [$id]); q('DELETE FROM paquetes WHERE id = ?', [$id]);
            flash('Paquete borrado. Quienes ya lo compraron conservan sus videos.'); redirigir(url('admin/?p=paquetes'));
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'error'); }
    redirigir(url('admin/?p=paquetes' . ($id ? '&id=' . $id : '&nuevo=1')));
}
if ($id || isset($_GET['nuevo'])) {
    $pq = $id ? fila('SELECT * FROM paquetes WHERE id = ?', [$id]) : ['nombre' => '', 'descripcion' => '', 'precio' => 30000, 'precio_antes' => 0, 'rutina_id' => null, 'dias_acceso' => 90, 'visible' => 1, 'orden' => 10, 'imagen' => '', 'slug' => ''];
    $sel = $id ? array_column(filas('SELECT video_id FROM paquete_videos WHERE paquete_id = ? ORDER BY orden', [$id]), 'video_id') : [];
    $vids = filas('SELECT id, titulo, categoria, precio FROM videos ORDER BY categoria, orden');
    $rut = filas('SELECT id, nombre FROM rutinas WHERE plantilla = 1 AND activa = 1 ORDER BY nombre');
    admin_cabecera('paquetes', $id ? $pq['nombre'] : 'Nuevo paquete');
    ?>
    <form method="post" enctype="multipart/form-data" class="ad-rejilla"><?= csrf_campo() ?><input type="hidden" name="acc" value="guardar">
      <div class="ad-caja"><h2><?= icono('book') ?> Paquete</h2>
        <div class="campos">
          <?= campo('nombre', 'Nombre *', $pq['nombre'], 'text', ['required' => true, 'ancho' => true, 'placeholder' => 'Tren inferior · Piernas']) ?>
          <?= campo('descripcion', 'Descripción', $pq['descripcion'], 'textarea', ['ancho' => true, 'filas' => 3]) ?>
          <?= campo('precio', 'Precio (COP)', (int)$pq['precio'], 'text', ['inputmode' => 'numeric']) ?>
          <?= campo('precio_antes', 'Precio antes (para mostrar ahorro)', (int)$pq['precio_antes'] ?: '', 'text', ['inputmode' => 'numeric', 'ayuda' => 'Ej: la suma de los videos sueltos.']) ?>
          <?= campo('dias_acceso', 'Días de acceso', $pq['dias_acceso'], 'number') ?>
          <?= campo('orden', 'Orden', $pq['orden'], 'number') ?>
          <?= campo('rutina_id', 'Rutina que recibe al comprar', $pq['rutina_id'], 'select', ['opciones' => ['' => '— Ninguna —'] + array_column($rut, 'nombre', 'id'), 'ancho' => true, 'ayuda' => 'Al aprobar el pago, se le copia esta rutina a su portal.']) ?>
          <?php if (!$pq['rutina_id']): ?><?= campo('crear_rutina', 'Crear automáticamente una rutina con estos videos', 0, 'checkbox', ['ancho' => true]) ?><?php endif; ?>
          <?= campo_imagen('imagen', 'Imagen (opcional)', $pq['imagen'] ?: null) ?>
          <?= campo('visible', 'Mostrar en la web', $pq['visible'], 'checkbox') ?>
        </div>
      </div>
      <div class="ad-caja"><h2><?= icono('video') ?> Videos incluidos</h2>
        <p class="sec">Marca los videos del paquete. Suma de los precios sueltos: <b id="sumaSueltos">—</b></p>
        <?php $g = ''; foreach ($vids as $v): if ($v['categoria'] !== $g): $g = $v['categoria']; ?><h3 style="font-size:13px;color:var(--rojo);text-transform:uppercase;letter-spacing:.08em;margin:14px 0 6px"><?= e($g) ?></h3><?php endif; ?>
          <label class="check" style="padding:6px 0"><input type="checkbox" name="videos[]" value="<?= (int)$v['id'] ?>" data-precio="<?= (int)$v['precio'] ?>" <?= in_array($v['id'], $sel) ? 'checked' : '' ?>> <span><?= e($v['titulo']) ?> <span class="sec">· <?= pesos($v['precio']) ?></span></span></label>
        <?php endforeach; ?>
        <p class="sec mt1">¿Falta una máquina? Agrégala en <a href="<?= url('admin/?p=videos&nuevo=1') ?>" style="color:var(--rojo)">Videos y máquinas</a>.</p>
      </div>
      <div class="ad-acciones" style="justify-content:flex-start;grid-column:1/-1"><button class="btn btn-rojo" type="submit">Guardar paquete</button><a class="btn btn-borde" href="<?= url('admin/?p=paquetes') ?>">Volver</a><?php if ($id): ?><a class="btn btn-borde" target="_blank" href="<?= url('maquinas.php#paquete-' . $pq['slug']) ?>"><?= icono('eye') ?> Ver en la web</a><?php endif; ?></div>
    </form>
    <?php if ($id): ?><form method="post" class="mt2" data-confirmar="¿Borrar este paquete?"><?= csrf_campo() ?><input type="hidden" name="acc" value="borrar"><button class="btn btn-sm btn-borde" type="submit"><?= icono('trash') ?> Borrar</button></form><?php endif; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      var f = function () { var s = 0; document.querySelectorAll('input[name="videos[]"]:checked').forEach(function (c) { s += +c.dataset.precio; }); document.getElementById('sumaSueltos').textContent = '$' + s.toLocaleString('es-CO'); };
      document.querySelectorAll('input[name="videos[]"]').forEach(function (c) { c.addEventListener('change', f); }); f();
    });
    </script>
    <?php admin_pie(); return;
}
$lista = filas("SELECT p.*, (SELECT COUNT(*) FROM paquete_videos pv WHERE pv.paquete_id = p.id) AS n, (SELECT COUNT(*) FROM pagos x WHERE x.concepto_tipo = 'paquete' AND x.concepto_id = p.id AND x.estado = 'aprobado') AS ventas FROM paquetes p ORDER BY p.orden, p.id");
admin_cabecera('paquetes', 'Paquetes de videos');
?>
<div class="ad-barra"><div class="ad-nota" style="margin:0;flex:1">Arma paquetes con los videos de una rutina (por ejemplo Tren inferior: hack, prensa, extensión, aductores y pantorrilla) y véndelos por un solo precio. Al aprobar el pago, el cliente recibe los videos y la rutina en su portal.</div>
  <div class="der"><a class="btn btn-sm btn-rojo" href="<?= url('admin/?p=paquetes&nuevo=1') ?>"><?= icono('plus') ?> Nuevo paquete</a></div></div>
<div class="ad-caja ad-tabla-envoltura"><table class="ad-tabla"><thead><tr><th>Paquete</th><th class="num">Videos</th><th class="num">Precio</th><th class="num">Ventas</th><th>En la web</th><th></th></tr></thead><tbody>
  <?php foreach ($lista as $p): ?><tr><td><b><?= e($p['nombre']) ?></b><br><span class="sec"><?= e(mb_strimwidth((string)$p['descripcion'], 0, 100, '…')) ?></span></td><td class="num"><?= (int)$p['n'] ?></td><td class="num"><?= pesos($p['precio']) ?></td><td class="num"><?= (int)$p['ventas'] ?></td>
    <td><?= $p['visible'] ? '<span class="chip chip-ok">Sí</span>' : '<span class="chip">No</span>' ?></td><td><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=paquetes&id=' . $p['id']) ?>"><?= icono('edit') ?> Editar</a></td></tr><?php endforeach; ?>
  <?php if (!$lista): ?><tr><td colspan="6" class="ad-vacio">Sin paquetes.</td></tr><?php endif; ?></tbody></table></div>
<?php admin_pie();
