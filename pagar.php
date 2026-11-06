<?php
/**
 * Pago de planes, videos o cuotas: muestra los medios de pago y recibe el comprobante.
 * Bryan lo aprueba en el panel y el sistema activa el plan o el acceso al video.
 */
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
iniciar_sesion();
$u = usuario();
$tipo = 'otro'; $item = null; $concepto = ''; $monto = 0;
if (!empty($_REQUEST['plan'])) {
    $item = fila('SELECT * FROM planes WHERE slug = ? AND visible = 1', [(string)$_REQUEST['plan']]);
    if ($item) { $tipo = $item['tipo'] === 'videos' ? 'biblioteca' : 'plan'; $concepto = $item['nombre']; $monto = (float)$item['precio']; }
} elseif (!empty($_REQUEST['paquete'])) {
    $item = fila('SELECT * FROM paquetes WHERE slug = ? AND visible = 1', [(string)$_REQUEST['paquete']]);
    if ($item) { $tipo = 'paquete'; $concepto = 'Paquete: ' . $item['nombre']; $monto = (float)$item['precio']; }
} elseif (!empty($_REQUEST['video'])) {
    $item = fila('SELECT * FROM videos WHERE slug = ? AND visible = 1', [(string)$_REQUEST['video']]);
    if ($item) { $tipo = 'video'; $concepto = 'Video: ' . $item['titulo']; $monto = (float)$item['precio']; }
}
$qs = $item ? (['video' => 'video=', 'paquete' => 'paquete='][$tipo] ?? 'plan=') . urlencode($item['slug']) : '';
$error = ''; $hecho = null;

if (es_post()) {
    csrf_verificar();
    // Cuenta nueva en el mismo paso, si no ha iniciado sesión
    if (!$u) {
        $nombre = trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 120));
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $tel = trim(mb_substr((string)($_POST['telefono'] ?? ''), 0, 30));
        $clave = (string)($_POST['clave'] ?? '');
        if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen(solo_digitos($tel)) < 7) $error = 'Escribe tu nombre, correo y WhatsApp.';
        elseif (mb_strlen($clave) < 8) $error = 'La contraseña debe tener al menos 8 caracteres.';
        elseif (empty($_POST['acepto'])) $error = 'Debes autorizar el tratamiento de datos.';
        elseif (valor('SELECT COUNT(*) FROM usuarios WHERE LOWER(email) = ?', [$email])) $error = 'Ya tienes una cuenta con ese correo: ingresa primero.';
        else {
            $uid = insertar('usuarios', ['rol' => 'cliente', 'nombre' => $nombre, 'email' => $email, 'telefono' => $tel, 'clave_hash' => password_hash($clave, PASSWORD_DEFAULT),
                'activo' => 1, 'debe_cambiar' => 0, 'origen' => 'pago web', 'consentimiento' => ahora(), 'creado' => ahora()]);
            login_usuario(fila('SELECT * FROM usuarios WHERE id = ?', [$uid]));
            $u = fila('SELECT * FROM usuarios WHERE id = ?', [$uid]);
        }
    }
    if (!$error && $u) {
        if (!$item) {
            $concepto = trim(mb_substr((string)($_POST['concepto'] ?? ''), 0, 190)) ?: 'Pago';
            $monto = (float)solo_digitos((string)($_POST['monto'] ?? '0'));
        }
        try {
            $comp = subir_archivo($_FILES['comprobante'] ?? ['error' => UPLOAD_ERR_NO_FILE], TIPOS_IMAGEN + TIPOS_PDF, true, 'comprobantes', 12);
            if (!$comp) $error = 'Sube la foto o el PDF de tu comprobante.';
        } catch (RuntimeException $ex) { $error = $ex->getMessage(); }
        if (!$error) {
            $pid = insertar('pagos', ['usuario_id' => $u['id'], 'nombre' => $u['nombre'], 'telefono' => $u['telefono'], 'email' => $u['email'],
                'concepto_tipo' => $tipo, 'concepto_id' => $item['id'] ?? null, 'concepto' => $concepto, 'monto' => $monto,
                'metodo' => mb_substr((string)($_POST['metodo'] ?? ''), 0, 40), 'referencia' => mb_substr((string)($_POST['referencia'] ?? ''), 0, 120),
                'comprobante' => $comp, 'estado' => 'pendiente', 'nota' => mb_substr((string)($_POST['nota'] ?? ''), 0, 1000), 'creado' => ahora()]);
            evento('pago', $tipo, $concepto);
            $hecho = $pid;
        }
    }
}
$pendientes = $u ? filas("SELECT * FROM pagos WHERE usuario_id = ? AND estado = 'pendiente' ORDER BY id DESC", [$u['id']]) : [];
$wa = ajuste('whatsapp');
cabecera('Pagar', ['body' => 'pagina']);
?>
<section class="cabeza-pag" style="padding-bottom:30px">
  <div class="envoltura" style="max-width:760px">
    <div class="migas"><a href="<?= url('') ?>">Inicio</a> / Pago</div>
    <h1 class="titulo"><?= $hecho ? '¡Recibido!' : 'Completa tu <span class="marca">pago</span>' ?></h1>
  </div>
</section>
<section class="seccion" style="padding-top:10px">
  <div class="envoltura" style="max-width:760px">
  <?php if ($hecho): ?>
    <div class="caja centro">
      <p class="sub" style="margin-inline:auto">Tu comprobante de <b><?= e($concepto) ?></b> quedó registrado. Bryan lo revisa y activa tu <?= ['video' => 'acceso al video', 'paquete' => 'paquete de videos y tu rutina'][$tipo] ?? 'plan' ?>. Te avisamos por WhatsApp.</p>
      <div class="hero-ctas" style="justify-content:center">
        <a class="btn btn-wa" target="_blank" rel="noopener" href="<?= e(wa_link($wa, 'Hola Bryan, soy ' . $u['nombre'] . '. Acabo de pagar ' . $concepto . ($monto ? ' (' . pesos($monto) . ')' : '') . ' y subí el comprobante en la página.')) ?>" data-ev="whatsapp:pago"><?= icono('whatsapp') ?> Avisarle a Bryan</a>
        <a class="btn btn-rojo" href="<?= url('cliente/') ?>">Ir a mi portal</a>
      </div>
    </div>
  <?php elseif ($item && $monto <= 0): ?>
    <div class="caja centro">
      <h2 class="titulo" style="font-size:40px"><?= e($concepto) ?></h2>
      <p class="sub" style="margin-inline:auto">El valor de este programa se define según tu caso. Escríbele a Bryan y te arma la propuesta.</p>
      <a class="btn btn-wa" target="_blank" rel="noopener" href="<?= e(wa_link($wa, 'Hola Bryan, quiero información y el valor de ' . $concepto . '.')) ?>" data-ev="whatsapp:consultar"><?= icono('whatsapp') ?> Consultar por WhatsApp</a>
    </div>
  <?php else: ?>
    <?php if ($error): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="caja" action="<?= url('pagar.php' . ($qs ? '?' . $qs : '')) ?>">
      <?= csrf_campo() ?>
      <?php if ($item): ?>
        <div class="resumen-compra"><div><span class="res-etiqueta">Vas a pagar</span><div style="font-weight:800;font-size:18px"><?= e($concepto) ?></div>
          <?php if ($tipo === 'video'): ?><span style="font-size:13px;color:var(--txt-2)">Acceso por <?= (int)ajuste('dias_acceso_video', '90') ?> días</span>
          <?php elseif ($tipo === 'paquete'): ?><span style="font-size:13px;color:var(--txt-2)"><?= (int)valor('SELECT COUNT(*) FROM paquete_videos WHERE paquete_id = ?', [$item['id']]) ?> videos + rutina en tu portal · <?= (int)$item['dias_acceso'] ?> días</span><?php elseif (!empty($item['frecuencia'])): ?><span style="font-size:13px;color:var(--txt-2)"><?= e($item['frecuencia']) ?></span><?php endif; ?></div>
          <b><?= pesos($monto) ?></b></div>
      <?php else: ?>
        <p class="sub" style="font-size:15px">¿Vas a renovar tu plan o pagar una cuota? Escribe el concepto y el valor.</p>
        <div class="campos"><div class="campo"><label for="pC">Concepto</label><input id="pC" name="concepto" required placeholder="Ej: Renovación Hard Gold" value="<?= e($_POST['concepto'] ?? '') ?>"></div>
          <div class="campo"><label for="pM">Valor pagado</label><input id="pM" name="monto" inputmode="numeric" required placeholder="480000" value="<?= e($_POST['monto'] ?? '') ?>"></div></div>
      <?php endif; ?>

      <h3 class="mt2">1. Paga por cualquiera de estos medios</h3>
      <div class="medios">
        <?php if (ajuste('pago_nequi')): ?><div class="medio"><span class="logo-m" style="background:#200020;color:#ff3fa4">N</span><div><small>Nequi</small><b><?= e(ajuste('pago_nequi')) ?></b></div><button class="btn btn-xs btn-borde" type="button" data-copiar="<?= e(solo_digitos(ajuste('pago_nequi'))) ?>">Copiar</button></div><?php endif; ?>
        <?php if (ajuste('pago_daviplata')): ?><div class="medio"><span class="logo-m" style="background:#e30613;color:#fff">D</span><div><small>Daviplata</small><b><?= e(ajuste('pago_daviplata')) ?></b></div><button class="btn btn-xs btn-borde" type="button" data-copiar="<?= e(solo_digitos(ajuste('pago_daviplata'))) ?>">Copiar</button></div><?php endif; ?>
        <?php if (ajuste('pago_cuenta')): ?><div class="medio"><span class="logo-m" style="background:#fff;color:#e30613">DV</span><div><small><?= e(ajuste('pago_banco')) ?> · <?= e(ajuste('pago_cuenta_tipo')) ?></small><b><?= e(ajuste('pago_cuenta')) ?></b><small><?= e(ajuste('pago_titular')) ?><?= ajuste('pago_cedula') ? ' · C.C. ' . e(ajuste('pago_cedula')) : '' ?></small></div><button class="btn btn-xs btn-borde" type="button" data-copiar="<?= e(solo_digitos(ajuste('pago_cuenta'))) ?>">Copiar</button></div><?php endif; ?>
        <?php $link = (($item['link_pago'] ?? '') ?: ajuste('pago_link')); if ($link): ?><a class="btn btn-volt btn-bloque" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= icono('money') ?> Pagar con tarjeta o PSE</a><?php endif; ?>
      </div>

      <h3 class="mt3">2. Sube tu comprobante</h3>
      <label class="zona-archivo"><?= icono('upload') ?><b>Toca para subir la foto o el PDF</b><br><span class="nombre-archivo" style="color:var(--volt);font-size:14px"></span><input type="file" name="comprobante" accept="image/*,application/pdf" required></label>
      <div class="campos mt1">
        <div class="campo"><label for="pMe">Medio usado</label><select id="pMe" name="metodo"><option>Nequi</option><option>Daviplata</option><option><?= e(ajuste('pago_banco', 'Banco')) ?></option><option>Tarjeta / PSE</option><option>Efectivo</option></select></div>
        <div class="campo"><label for="pR">Referencia (opcional)</label><input id="pR" name="referencia" maxlength="120"></div>
      </div>

      <?php if (!$u): ?>
        <h3 class="mt3">3. Crea tu cuenta para ver tu <?= $tipo === 'video' ? 'video' : 'plan' ?></h3>
        <p style="font-size:14px;color:var(--txt-2)">¿Ya tienes cuenta? <a style="color:var(--rojo)" href="<?= url('ingresar.php?r=' . urlencode('pagar.php' . ($qs ? '?' . $qs : ''))) ?>">Ingresa aquí</a>.</p>
        <div class="campos">
          <div class="campo ancho"><label for="pN">Nombre completo</label><input id="pN" name="nombre" required autocomplete="name" value="<?= e($_POST['nombre'] ?? '') ?>"></div>
          <div class="campo"><label for="pE">Correo</label><input id="pE" name="email" type="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></div>
          <div class="campo"><label for="pT">WhatsApp</label><input id="pT" name="telefono" type="tel" required autocomplete="tel" value="<?= e($_POST['telefono'] ?? '') ?>"></div>
          <div class="campo ancho"><label for="pK">Contraseña</label><input id="pK" name="clave" type="password" required minlength="8" autocomplete="new-password"></div>
          <label class="check ancho"><input type="checkbox" name="acepto" value="1" required> <span>Autorizo el tratamiento de mis datos según la <a href="<?= url('privacidad.php') ?>" target="_blank" style="color:var(--rojo)">política de datos</a>.</span></label>
        </div>
      <?php else: ?>
        <p class="mt2" style="font-size:14px;color:var(--txt-2)">Pagando como <b style="color:var(--txt)"><?= e($u['nombre']) ?></b> (<?= e($u['email']) ?>).</p>
      <?php endif; ?>
      <div class="campo mt1"><label for="pNo">Mensaje para Bryan (opcional)</label><input id="pNo" name="nota" maxlength="500"></div>
      <button class="btn btn-rojo btn-bloque mt2" type="submit"><?= icono('send') ?> Enviar comprobante</button>
      <p class="aviso-salud centro"><?= e(ajuste('pago_nota')) ?></p>
    </form>
    <?php if ($pendientes): ?>
      <div class="tarjeta mt2"><h3 style="font-size:18px">Tus pagos en revisión</h3>
        <?php foreach ($pendientes as $p): ?><p style="margin:0 0 6px;color:var(--txt-2)"><?= e($p['concepto']) ?> · <?= pesos($p['monto']) ?> · <?= hace($p['creado']) ?></p><?php endforeach; ?></div>
    <?php endif; ?>
  <?php endif; ?>
  </div>
</section>
<?php pie(); ?>
