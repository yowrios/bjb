<?php
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
iniciar_sesion();

/** Solo permite volver a páginas de este mismo sitio. */
function destino_seguro(string $r, array $u): string {
    $r = ltrim($r, '/');
    if ($r !== '' && !preg_match('~^(https?:)?//~i', $r) && !str_contains($r, '\\') && preg_match('~^[\w\-./?=&%]+$~', $r)) return url($r);
    return url($u['rol'] === 'admin' ? 'admin/' : 'cliente/');
}

$r = (string)($_GET['r'] ?? $_POST['r'] ?? '');
$tab = $_GET['tab'] ?? 'ingresar';
$error = '';
if (usuario() && !es_post()) redirigir(destino_seguro($r, usuario()));

if (es_post()) {
    csrf_verificar();
    $acc = $_POST['acc'] ?? '';
    if ($acc === 'ingresar') {
        if (intentos_superados('login')) $error = 'Demasiados intentos. Espera 15 minutos e intenta de nuevo.';
        else {
            $id = trim((string)($_POST['usuario'] ?? ''));
            $clave = (string)($_POST['clave'] ?? '');
            $u = str_contains($id, '@') ? fila('SELECT * FROM usuarios WHERE LOWER(email) = LOWER(?) AND activo = 1', [$id]) : null;
            if (!$u && strlen(solo_digitos($id)) >= 7) {
                foreach (filas('SELECT * FROM usuarios WHERE activo = 1 AND telefono IS NOT NULL') as $c)
                    if (substr(solo_digitos($c['telefono']), -10) === substr(solo_digitos($id), -10)) { $u = $c; break; }
            }
            if ($u && password_verify($clave, $u['clave_hash'])) {
                login_usuario($u);
                if (!empty($_POST['recordar'])) recordar_crear((int)$u['id']);
                if ($u['debe_cambiar']) redirigir(url(($u['rol'] === 'admin' ? 'admin/?p=ajustes' : 'cliente/?p=perfil') . '&cambiar=1'));
                redirigir(destino_seguro($r, $u));
            }
            registrar_intento('login');
            $error = 'Correo, celular o contraseña incorrectos.';
        }
        $tab = 'ingresar';
    } elseif ($acc === 'registro') {
        $tab = 'registro';
        $nombre = trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 120));
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $tel = trim(mb_substr((string)($_POST['telefono'] ?? ''), 0, 30));
        $clave = (string)($_POST['clave'] ?? '');
        if (intentos_superados('registro')) $error = 'Demasiados intentos. Espera unos minutos.';
        elseif ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Escribe tu nombre y un correo válido.';
        elseif (strlen(solo_digitos($tel)) < 7) $error = 'Escribe tu número de WhatsApp.';
        elseif (mb_strlen($clave) < 8) $error = 'La contraseña debe tener al menos 8 caracteres.';
        elseif (empty($_POST['acepto'])) $error = 'Debes autorizar el tratamiento de datos para crear tu cuenta.';
        elseif (valor('SELECT COUNT(*) FROM usuarios WHERE LOWER(email) = ?', [$email])) $error = 'Ya existe una cuenta con ese correo. Ingresa o pide a Bryan restablecer tu contraseña.';
        else {
            registrar_intento('registro');
            $id = insertar('usuarios', ['rol' => 'cliente', 'nombre' => $nombre, 'email' => $email, 'telefono' => $tel, 'clave_hash' => password_hash($clave, PASSWORD_DEFAULT),
                'activo' => 1, 'debe_cambiar' => 0, 'origen' => 'registro web', 'consentimiento' => ahora(), 'creado' => ahora()]);
            q('UPDATE leads SET usuario_id = ?, estado = CASE WHEN estado = ? THEN ? ELSE estado END WHERE telefono = ? AND usuario_id IS NULL', [$id, 'nuevo', 'contactado', $tel]);
            evento('registro', 'cuenta', $nombre);
            login_usuario(fila('SELECT * FROM usuarios WHERE id = ?', [$id]));
            flash('¡Bienvenido, ' . explode(' ', $nombre)[0] . '! Tu cuenta quedó creada.');
            redirigir(destino_seguro($r, ['rol' => 'cliente']));
        }
    }
}
cabecera('Ingresar', ['body' => 'pagina']);
?>
<section class="cabeza-pag" style="min-height:100vh;padding-bottom:80px">
  <div class="envoltura panel-centro">
    <div class="centro" style="margin-bottom:24px"><img src="<?= imagen_marca('logo') ?>" alt="" width="120" style="margin:0 auto"></div>
    <div class="caja">
      <div class="pestanas" role="tablist">
        <button type="button" class="<?= $tab !== 'registro' ? 'on' : '' ?>" data-t="ingresar">Ingresar</button>
        <button type="button" class="<?= $tab === 'registro' ? 'on' : '' ?>" data-t="registro">Crear cuenta</button>
      </div>
      <?php if ($error): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" data-f="ingresar" class="<?= $tab === 'registro' ? 'oculto' : '' ?>">
        <?= csrf_campo() ?><input type="hidden" name="acc" value="ingresar"><input type="hidden" name="r" value="<?= e($r) ?>">
        <div class="campos">
          <div class="campo ancho"><label for="iU">Correo o celular</label><input id="iU" name="usuario" required autocomplete="username" value="<?= e($_POST['usuario'] ?? '') ?>"></div>
          <div class="campo ancho"><label for="iC">Contraseña</label><input id="iC" name="clave" type="password" required autocomplete="current-password"></div>
          <label class="check ancho"><input type="checkbox" name="recordar" value="1" checked> <span>Recordarme 30 días en este equipo <small style="display:block;color:var(--txt-3)">No la marques en computadores públicos o prestados.</small></span></label>
        </div>
        <button class="btn btn-rojo btn-bloque mt2" type="submit">Ingresar</button>
        <p class="centro mt2" style="font-size:14px;color:var(--txt-2)">¿Olvidaste tu contraseña? <a style="color:var(--rojo)" target="_blank" rel="noopener" href="<?= e(wa_link(ajuste('whatsapp'), 'Hola Bryan, olvidé la contraseña de mi portal. Mi correo es: ')) ?>">Pídele a Bryan una nueva por WhatsApp</a></p>
      </form>
      <form method="post" data-f="registro" class="<?= $tab !== 'registro' ? 'oculto' : '' ?>">
        <?= csrf_campo() ?><input type="hidden" name="acc" value="registro"><input type="hidden" name="r" value="<?= e($r) ?>">
        <div class="campos">
          <div class="campo ancho"><label for="rN">Nombre completo</label><input id="rN" name="nombre" required maxlength="120" autocomplete="name" value="<?= e($_POST['nombre'] ?? '') ?>"></div>
          <div class="campo"><label for="rE">Correo</label><input id="rE" name="email" type="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></div>
          <div class="campo"><label for="rT">WhatsApp</label><input id="rT" name="telefono" type="tel" required autocomplete="tel" value="<?= e($_POST['telefono'] ?? '') ?>"></div>
          <div class="campo ancho"><label for="rC">Contraseña</label><input id="rC" name="clave" type="password" required minlength="8" autocomplete="new-password"><span class="ayuda">Mínimo 8 caracteres.</span></div>
          <label class="check ancho"><input type="checkbox" name="acepto" value="1" required> <span>Autorizo el tratamiento de mis datos, incluidos datos de salud para mi plan, según la <a href="<?= url('privacidad.php') ?>" target="_blank" style="color:var(--rojo)">política de datos</a>.</span></label>
        </div>
        <button class="btn btn-rojo btn-bloque mt2" type="submit">Crear mi cuenta</button>
      </form>
    </div>
  </div>
</section>
<script>
document.querySelectorAll('.pestanas button').forEach(function (b) {
  b.addEventListener('click', function () {
    document.querySelectorAll('.pestanas button').forEach(function (x) { x.classList.toggle('on', x === b); });
    document.querySelectorAll('form[data-f]').forEach(function (f) { f.classList.toggle('oculto', f.dataset.f !== b.dataset.t); });
  });
});
</script>
<?php pie(); ?>
