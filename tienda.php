<?php
/** Tienda: suplementos y accesorios. Se activa o desactiva desde el panel (Tienda). */
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
iniciar_sesion();
$u = usuario();
if (!tienda_activa() && !es_admin()) {
    cabecera('Tienda', ['body' => 'pagina']);
    echo '<section class="cabeza-pag" style="min-height:70vh"><div class="envoltura"><span class="antetitulo">Tienda</span><h1 class="titulo">Muy <span class="marca">pronto</span></h1><p class="sub">Estamos preparando la tienda de suplementos y accesorios. Mientras tanto, pregúntale a Bryan por WhatsApp.</p>'
        . '<a class="btn btn-wa mt2" target="_blank" rel="noopener" href="' . e(wa_link(ajuste('whatsapp'), 'Hola Bryan, quiero información sobre suplementos.')) . '">' . icono('whatsapp') . ' Preguntar</a></div></section>';
    pie(); exit;
}
$checkout = isset($_GET['pedido']);
$error = ''; $hecho = null;
$entregas = lineas(ajuste('tienda_entrega'));

if (es_post()) {
    csrf_verificar();
    $items = json_decode((string)($_POST['carrito'] ?? '[]'), true);
    $lineas = []; $total = 0;
    foreach (is_array($items) ? $items : [] as $it) {
        $p = fila('SELECT * FROM productos WHERE id = ? AND visible = 1', [(int)($it['id'] ?? 0)]);
        $c = max(1, min(50, (int)($it['cant'] ?? 1)));
        if (!$p) continue;
        $lineas[] = ['id' => (int)$p['id'], 'nombre' => $p['nombre'], 'precio' => (float)$p['precio'], 'cant' => $c]; // el precio siempre sale de la base de datos
        $total += (float)$p['precio'] * $c;
    }
    $nombre = trim(mb_substr((string)($_POST['nombre'] ?? ($u['nombre'] ?? '')), 0, 120));
    $tel = trim(mb_substr((string)($_POST['telefono'] ?? ($u['telefono'] ?? '')), 0, 30));
    $entrega = mb_substr(trim((string)($_POST['entrega'] ?? '') . ' ' . trim((string)($_POST['direccion'] ?? ''))), 0, 250);
    if (!$lineas) $error = 'Tu carrito está vacío.';
    elseif ($nombre === '' || strlen(solo_digitos($tel)) < 7) $error = 'Escribe tu nombre y tu WhatsApp.';
    elseif (empty($_POST['acepto'])) $error = 'Debes autorizar el tratamiento de datos.';
    elseif (intentos_superados('pedido')) $error = 'Demasiados pedidos seguidos. Intenta más tarde.';
    else {
        try {
            $comp = subir_archivo($_FILES['comprobante'] ?? ['error' => UPLOAD_ERR_NO_FILE], TIPOS_IMAGEN + TIPOS_PDF, true, 'comprobantes', 12);
            if (!$comp && ($_POST['pago_contra'] ?? '') !== '1') $error = 'Sube el comprobante de pago o elige «Pago al recibir».';
        } catch (RuntimeException $ex) { $error = $ex->getMessage(); }
        if (!$error) {
            registrar_intento('pedido');
            $concepto = 'Pedido tienda: ' . implode(', ', array_map(fn($l) => $l['cant'] . '× ' . $l['nombre'], $lineas));
            $hecho = insertar('pagos', ['usuario_id' => $u['id'] ?? null, 'nombre' => $nombre, 'telefono' => $tel, 'email' => mb_substr((string)($_POST['email'] ?? ($u['email'] ?? '')), 0, 160),
                'concepto_tipo' => 'pedido', 'concepto' => mb_substr($concepto, 0, 190), 'monto' => $total, 'metodo' => ($_POST['pago_contra'] ?? '') === '1' ? 'Pago al recibir' : mb_substr((string)($_POST['metodo'] ?? ''), 0, 40),
                'comprobante' => $comp ?: '', 'estado' => 'pendiente', 'nota' => mb_substr((string)($_POST['nota'] ?? ''), 0, 500), 'detalle' => json_encode($lineas, JSON_UNESCAPED_UNICODE),
                'entrega' => $entrega, 'estado_entrega' => 'por preparar', 'creado' => ahora()]);
            evento('pago', 'pedido', $concepto);
        }
    }
    $checkout = true;
}
$prods = filas('SELECT * FROM productos WHERE visible = 1 ORDER BY destacado DESC, orden, id');
$cats = array_values(array_unique(array_filter(array_column($prods, 'categoria'))));
$wa = ajuste('whatsapp');
cabecera('Tienda', ['activo' => 'tienda', 'body' => 'pagina', 'desc' => ajuste('tienda_texto')]);
?>
<section class="cabeza-pag" style="padding-bottom:30px">
  <div class="envoltura">
    <div class="migas"><a href="<?= url('') ?>">Inicio</a> / <?= $checkout ? '<a href="' . url('tienda.php') . '">Tienda</a> / Pedido' : 'Tienda' ?></div>
    <?php if (!tienda_activa()): ?><div class="msg msg-error">La tienda está DESACTIVADA: solo tú la ves. Actívala en el panel › Tienda.</div><?php endif; ?>
    <h1 class="titulo"><?= $hecho ? '¡Pedido recibido!' : ($checkout ? 'Finalizar <span class="marca">pedido</span>' : e(ajuste('tienda_titulo'))) ?></h1>
    <?php if (!$checkout): ?><p class="sub"><?= e(ajuste('tienda_texto')) ?></p><?php endif; ?>
  </div>
</section>
<section class="seccion" style="padding-top:20px">
  <div class="envoltura">
  <?php if ($hecho): ?>
    <div class="caja centro" style="max-width:640px;margin:0 auto">
      <p class="sub" style="margin-inline:auto">Bryan revisa tu pedido y te confirma por WhatsApp la entrega.</p>
      <a class="btn btn-wa" target="_blank" rel="noopener" href="<?= e(wa_link($wa, 'Hola Bryan, soy ' . ($_POST['nombre'] ?? '') . '. Hice el pedido #' . $hecho . ' en la tienda de la web.')) ?>"><?= icono('whatsapp') ?> Avisarle a Bryan</a>
    </div>
    <script>try { localStorage.removeItem('bj_carrito'); } catch (e) {}</script>
  <?php elseif ($checkout): ?>
    <?php if ($error): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="dos-col" id="formPedido" action="<?= url('tienda.php?pedido=1') ?>">
      <?= csrf_campo() ?><input type="hidden" name="carrito" id="carritoCampo">
      <div class="caja">
        <h3>Tu pedido</h3>
        <div id="resumenPedido"><p class="sub">Cargando...</p></div>
        <a href="<?= url('tienda.php') ?>" class="btn btn-borde btn-sm mt1">Seguir comprando</a>
        <h3 class="mt3">Medios de pago</h3>
        <div class="medios">
          <?php if (ajuste('pago_nequi')): ?><div class="medio"><span class="logo-m" style="background:#200020;color:#ff3fa4">N</span><div><small>Nequi</small><b><?= e(ajuste('pago_nequi')) ?></b></div><button class="btn btn-xs btn-borde" type="button" data-copiar="<?= e(solo_digitos(ajuste('pago_nequi'))) ?>">Copiar</button></div><?php endif; ?>
          <?php if (ajuste('pago_daviplata')): ?><div class="medio"><span class="logo-m" style="background:#e30613;color:#fff">D</span><div><small>Daviplata</small><b><?= e(ajuste('pago_daviplata')) ?></b></div><button class="btn btn-xs btn-borde" type="button" data-copiar="<?= e(solo_digitos(ajuste('pago_daviplata'))) ?>">Copiar</button></div><?php endif; ?>
          <?php if (ajuste('pago_cuenta')): ?><div class="medio"><span class="logo-m" style="background:#fff;color:#e30613">DV</span><div><small><?= e(ajuste('pago_banco')) ?></small><b><?= e(ajuste('pago_cuenta')) ?></b><small><?= e(ajuste('pago_titular')) ?></small></div><button class="btn btn-xs btn-borde" type="button" data-copiar="<?= e(solo_digitos(ajuste('pago_cuenta'))) ?>">Copiar</button></div><?php endif; ?>
        </div>
      </div>
      <div class="caja">
        <h3>Tus datos y entrega</h3>
        <div class="campos">
          <div class="campo ancho"><label for="tN">Nombre</label><input id="tN" name="nombre" required value="<?= e($_POST['nombre'] ?? ($u['nombre'] ?? '')) ?>"></div>
          <div class="campo"><label for="tT">WhatsApp</label><input id="tT" name="telefono" type="tel" required value="<?= e($_POST['telefono'] ?? ($u['telefono'] ?? '')) ?>"></div>
          <div class="campo"><label for="tE">Correo (opcional)</label><input id="tE" name="email" type="email" value="<?= e($_POST['email'] ?? ($u['email'] ?? '')) ?>"></div>
          <div class="campo ancho"><label for="tEn">Entrega</label><select id="tEn" name="entrega"><?php foreach ($entregas as $en): ?><option><?= e($en) ?></option><?php endforeach; ?></select></div>
          <div class="campo ancho"><label for="tD">Dirección o indicaciones (si es envío)</label><input id="tD" name="direccion" value="<?= e($_POST['direccion'] ?? '') ?>"></div>
        </div>
        <h3 class="mt2">Comprobante</h3>
        <label class="zona-archivo"><?= icono('upload') ?><b>Sube la foto o PDF del pago</b><br><span class="nombre-archivo" style="color:var(--volt);font-size:14px"></span><input type="file" name="comprobante" accept="image/*,application/pdf"></label>
        <label class="check mt1"><input type="checkbox" name="pago_contra" value="1"> <span>Prefiero pagar al recibir / en el gimnasio</span></label>
        <div class="campo mt1"><label for="tNo">Nota (sabor, talla...)</label><input id="tNo" name="nota" maxlength="500"></div>
        <label class="check mt1"><input type="checkbox" name="acepto" value="1" required> <span>Autorizo el tratamiento de mis datos según la <a href="<?= url('privacidad.php') ?>" target="_blank" style="color:var(--rojo)">política de datos</a>.</span></label>
        <button class="btn btn-rojo btn-bloque mt2" type="submit"><?= icono('send') ?> Enviar pedido</button>
      </div>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      var c = window.bjCarrito ? window.bjCarrito.leer() : [], r = document.getElementById('resumenPedido');
      document.getElementById('carritoCampo').value = JSON.stringify(c);
      if (!c.length) { r.innerHTML = '<p class="sub">Tu carrito está vacío.</p>'; return; }
      var t = 0;
      r.innerHTML = c.map(function (x) { t += x.precio * x.cant; return '<div class="carrito-fila"><span>' + x.cant + ' × ' + x.nombre.replace(/</g, '&lt;') + '</span><span></span><b>$' + Math.round(x.precio * x.cant).toLocaleString('es-CO') + '</b></div>'; }).join('') +
        '<div class="carrito-total"><span>Total</span><span>$' + Math.round(t).toLocaleString('es-CO') + '</span></div>';
    });
    </script>
  <?php else: ?>
    <?php if ($cats): ?><div class="filtros" id="filtrosTienda"><button type="button" class="on" data-cat="">Todo</button><?php foreach ($cats as $c): ?><button type="button" data-cat="<?= e($c) ?>"><?= e($c) ?></button><?php endforeach; ?></div><?php endif; ?>
    <div class="productos" id="listaProductos"><?php foreach ($prods as $p) echo tarjeta_producto($p); ?></div>
    <?php if (!$prods): ?><p class="sub centro">Pronto tendremos productos aquí.</p><?php endif; ?>
    <p class="aviso-salud centro mt3">Los suplementos no reemplazan una alimentación balanceada. Si tienes alguna condición médica, consulta antes con tu médico.</p>
    <script>
    document.querySelectorAll('#filtrosTienda button').forEach(function (b) {
      b.addEventListener('click', function () {
        document.querySelectorAll('#filtrosTienda button').forEach(function (x) { x.classList.toggle('on', x === b); });
        document.querySelectorAll('#listaProductos .producto').forEach(function (p) { p.style.display = (!b.dataset.cat || p.dataset.cat === b.dataset.cat) ? '' : 'none'; });
      });
    });
    </script>
  <?php endif; ?>
  </div>
</section>
<?php pie(); ?>
