<?php
/** Tienda: productos, pedidos y encendido/apagado. */
$id = (int)($_GET['id'] ?? 0);
if (es_post()) {
    csrf_verificar();
    try {
        switch (post('acc')) {
            case 'activar':
                guardar_ajuste('tienda_activa', post('activa') === '1' ? '1' : '0');
                flash(post('activa') === '1' ? 'Tienda ENCENDIDA: ya aparece en tu web.' : 'Tienda apagada: no aparece en la web.'); break;
            case 'textos':
                foreach (['tienda_titulo', 'tienda_texto', 'tienda_entrega'] as $k) guardar_ajuste($k, post($k));
                flash('Textos guardados.'); break;
            case 'guardar':
                if (post('nombre') === '') throw new RuntimeException('Escribe el nombre del producto.');
                $act = $id ? fila('SELECT * FROM productos WHERE id = ?', [$id]) : null;
                $d = ['nombre' => post('nombre'), 'categoria' => post('categoria'), 'marca' => post('marca'), 'presentacion' => post('presentacion'), 'descripcion' => post('descripcion'),
                    'precio' => post_num('precio'), 'precio_antes' => post_num('precio_antes'), 'stock' => post('stock') === '' ? null : post_int('stock'),
                    'destacado' => post('destacado') ? 1 : 0, 'visible' => post('visible') ? 1 : 0, 'orden' => post_int('orden')];
                $d['imagen'] = imagen_post('imagen', $act['imagen'] ?? null, 'productos');
                if ($act) actualizar('productos', $id, $d); else { $d['creado'] = ahora(); $id = insertar('productos', $d); }
                flash('Producto guardado.'); redirigir(url('admin/?p=tienda'));
            case 'borrar':
                q('DELETE FROM productos WHERE id = ?', [$id]); flash('Producto borrado.'); redirigir(url('admin/?p=tienda'));
            case 'entrega':
                q('UPDATE pagos SET estado_entrega = ? WHERE id = ? AND concepto_tipo = ?', [in_array(post('estado'), ['por preparar', 'por entregar', 'entregado', 'cancelado'], true) ? post('estado') : 'por preparar', post_int('pedido_id'), 'pedido']);
                flash('Pedido actualizado.'); break;
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'error'); }
    redirigir(url('admin/?p=tienda' . ($id && post('acc') === 'guardar' ? '&id=' . $id : '')));
}
if ($id || isset($_GET['nuevo'])) {
    $pr = $id ? fila('SELECT * FROM productos WHERE id = ?', [$id]) : ['nombre' => '', 'categoria' => 'Proteínas', 'marca' => '', 'presentacion' => '', 'descripcion' => '', 'precio' => 0, 'precio_antes' => 0, 'stock' => null, 'destacado' => 0, 'visible' => 1, 'orden' => 10, 'imagen' => ''];
    $cats = array_column(filas('SELECT DISTINCT categoria FROM productos WHERE categoria <> \'\' ORDER BY categoria'), 'categoria') ?: ['Proteínas', 'Creatina', 'Pre-entreno', 'Vitaminas', 'Accesorios', 'Ropa'];
    admin_cabecera('tienda', $id ? $pr['nombre'] : 'Nuevo producto');
    ?>
    <form method="post" enctype="multipart/form-data" class="ad-caja" style="max-width:900px"><?= csrf_campo() ?><input type="hidden" name="acc" value="guardar">
      <div class="campos">
        <?= campo('nombre', 'Nombre *', $pr['nombre'], 'text', ['required' => true, 'ancho' => true, 'placeholder' => 'Proteína whey 2 lb']) ?>
        <div class="campo"><label for="c_cat">Categoría</label><input id="c_cat" name="categoria" list="cats" value="<?= e($pr['categoria']) ?>"><datalist id="cats"><?php foreach (array_unique(array_merge($cats, ['Proteínas', 'Creatina', 'Pre-entreno', 'Vitaminas', 'Accesorios', 'Ropa'])) as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
        <?= campo('marca', 'Marca', $pr['marca']) ?>
        <?= campo('presentacion', 'Presentación', $pr['presentacion'], 'text', ['placeholder' => '2 lb · chocolate / vainilla']) ?>
        <?= campo('stock', 'Unidades disponibles (vacío = no controlar)', $pr['stock'], 'number') ?>
        <?= campo('precio', 'Precio (COP) *', (int)$pr['precio'], 'text', ['inputmode' => 'numeric']) ?>
        <?= campo('precio_antes', 'Precio antes (oferta)', (int)$pr['precio_antes'] ?: '', 'text', ['inputmode' => 'numeric']) ?>
        <?= campo('descripcion', 'Descripción', $pr['descripcion'], 'textarea', ['ancho' => true]) ?>
        <?= campo_imagen('imagen', 'Foto del producto (cuadrada, fondo blanco, mín. 800 × 800 px)', $pr['imagen'] ?: null) ?>
        <?= campo('orden', 'Orden', $pr['orden'], 'number') ?>
        <?= campo('destacado', 'Recomendado (sale primero y en la portada)', $pr['destacado'], 'checkbox') ?>
        <?= campo('visible', 'Mostrar en la tienda', $pr['visible'], 'checkbox') ?>
      </div>
      <div class="ad-acciones mt2" style="justify-content:flex-start"><button class="btn btn-rojo" type="submit">Guardar</button><a class="btn btn-borde" href="<?= url('admin/?p=tienda') ?>">Volver</a></div>
    </form>
    <?php if ($id): ?><form method="post" class="mt2" data-confirmar="¿Borrar este producto?"><?= csrf_campo() ?><input type="hidden" name="acc" value="borrar"><button class="btn btn-sm btn-borde" type="submit"><?= icono('trash') ?> Borrar</button></form><?php endif; ?>
    <?php admin_pie(); return;
}
$prods = filas('SELECT * FROM productos ORDER BY destacado DESC, orden, id');
$pedidos = filas("SELECT * FROM pagos WHERE concepto_tipo = 'pedido' ORDER BY id DESC LIMIT 100");
$on = tienda_activa();
admin_cabecera('tienda', 'Tienda');
?>
<div class="ad-caja" style="margin-bottom:20px;border-color:<?= $on ? '#22c55e' : 'var(--alerta)' ?>">
  <h2><?= icono('money') ?> La tienda está <?= $on ? '<span style="color:#4ade80">ENCENDIDA</span>' : '<span style="color:#fbbf24">APAGADA</span>' ?></h2>
  <p class="sec"><?= $on ? 'Aparece en el menú, en la portada (productos recomendados) y en /tienda.php.' : 'No aparece en la web. Puedes ir cargando productos y revisarla en ' ?><?= $on ? '' : '<a href="' . url('tienda.php') . '" target="_blank" style="color:var(--rojo)">la vista previa</a> (solo tú la ves). Enciéndela cuando consigas el patrocinio.' ?></p>
  <form method="post"><?= csrf_campo() ?><input type="hidden" name="acc" value="activar"><input type="hidden" name="activa" value="<?= $on ? '0' : '1' ?>"><button class="btn <?= $on ? 'btn-borde' : 'btn-rojo' ?>" type="submit"><?= $on ? 'Apagar la tienda' : 'Encender la tienda' ?></button></form>
</div>
<div class="ad-rejilla">
  <div class="ad-caja ad-tabla-envoltura"><h2><?= icono('flame') ?> Productos <span class="der"><a href="<?= url('admin/?p=tienda&nuevo=1') ?>">+ Agregar producto</a></span></h2>
    <table class="ad-tabla"><thead><tr><th></th><th>Producto</th><th class="num">Precio</th><th class="num">Stock</th><th></th></tr></thead><tbody>
    <?php foreach ($prods as $p): ?><tr><td style="width:60px"><?php if ($p['imagen']): ?><img src="<?= e(img_publica($p['imagen'])) ?>" alt="" style="width:50px;height:50px;object-fit:contain;background:#fff;border-radius:8px"><?php endif; ?></td>
      <td><b><?= e($p['nombre']) ?></b> <?= $p['visible'] ? '' : '<span class="chip">Oculto</span>' ?><br><span class="sec"><?= e($p['categoria']) ?> · <?= e($p['marca']) ?></span></td><td class="num"><?= pesos($p['precio']) ?></td><td class="num"><?= $p['stock'] === null ? '—' : (int)$p['stock'] ?></td>
      <td><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=tienda&id=' . $p['id']) ?>"><?= icono('edit') ?></a></td></tr><?php endforeach; ?>
    <?php if (!$prods): ?><tr><td colspan="5" class="ad-vacio">Agrega tus primeros productos: proteínas, creatina, shakers, guantes...</td></tr><?php endif; ?></tbody></table></div>
  <form method="post" class="ad-caja"><?= csrf_campo() ?><input type="hidden" name="acc" value="textos"><h2><?= icono('edit') ?> Textos de la tienda</h2>
    <div class="campos"><?= campo('tienda_titulo', 'Título', ajuste('tienda_titulo'), 'text', ['ancho' => true]) ?><?= campo('tienda_texto', 'Texto', ajuste('tienda_texto'), 'textarea', ['ancho' => true, 'filas' => 3]) ?>
      <?= campo('tienda_entrega', 'Opciones de entrega (una por línea)', ajuste('tienda_entrega'), 'textarea', ['ancho' => true, 'filas' => 3]) ?></div>
    <button class="btn btn-rojo btn-sm mt1" type="submit">Guardar</button></form>
</div>
<div class="ad-caja mt2 ad-tabla-envoltura"><h2><?= icono('send') ?> Pedidos</h2>
  <table class="ad-tabla"><thead><tr><th>Fecha</th><th>Cliente</th><th>Productos</th><th class="num">Total</th><th>Pago</th><th>Entrega</th></tr></thead><tbody>
  <?php foreach ($pedidos as $x): ?><tr><td><?= fecha_corta($x['creado']) ?></td><td><b><?= e($x['nombre']) ?></b><br><a class="sec" href="<?= e(wa_link($x['telefono'])) ?>" target="_blank" rel="noopener"><?= e($x['telefono']) ?></a></td>
    <td class="sec"><?php foreach (json_decode((string)$x['detalle'], true) ?: [] as $l): ?><?= (int)$l['cant'] ?> × <?= e($l['nombre']) ?><br><?php endforeach; ?><?= e($x['entrega']) ?></td><td class="num"><?= pesos($x['monto']) ?></td>
    <td><?= estado_chip($x['estado']) ?><br><span class="sec"><?= e($x['metodo']) ?></span><?= $x['estado'] === 'pendiente' ? '<br><a href="' . url('admin/?p=pagos#pago' . $x['id']) . '" style="color:var(--rojo);font-size:13px">Revisar pago</a>' : '' ?></td>
    <td><form method="post" style="display:flex;gap:6px"><?= csrf_campo() ?><input type="hidden" name="acc" value="entrega"><input type="hidden" name="pedido_id" value="<?= (int)$x['id'] ?>"><select name="estado" style="padding:8px"><?php foreach (['por preparar', 'por entregar', 'entregado', 'cancelado'] as $es): ?><option <?= $x['estado_entrega'] === $es ? 'selected' : '' ?>><?= $es ?></option><?php endforeach; ?></select><button class="btn-icono" type="submit"><?= icono('check') ?></button></form></td></tr><?php endforeach; ?>
  <?php if (!$pedidos): ?><tr><td colspan="6" class="ad-vacio">Sin pedidos todavía.</td></tr><?php endif; ?></tbody></table></div>
<?php admin_pie();
