<?php
/** Panel de Bryan. Cada sección está en admin/vistas/<seccion>.php */
require dirname(__DIR__) . '/inc/app.php';
require dirname(__DIR__) . '/inc/layout.php';
require __DIR__ . '/inc.php';
iniciar_sesion();
cabeceras_seguridad();
header('X-Robots-Tag: noindex, nofollow');

/* Primer uso: crear la cuenta de Bryan (solo si todavía no existe ningún administrador). */
if (!(int)valor("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")) {
    $error = '';
    if (es_post()) {
        csrf_verificar();
        $n = trim((string)($_POST['nombre'] ?? '')); $em = mb_strtolower(trim((string)($_POST['email'] ?? ''))); $c = (string)($_POST['clave'] ?? '');
        if ($n === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) $error = 'Escribe nombre y correo válidos.';
        elseif (mb_strlen($c) < 10) $error = 'La contraseña del panel debe tener al menos 10 caracteres.';
        elseif ($c !== ($_POST['repite'] ?? '')) $error = 'Las contraseñas no coinciden.';
        else {
            $ex = fila('SELECT id FROM usuarios WHERE LOWER(email) = ?', [$em]);
            if ($ex) q("UPDATE usuarios SET rol = 'admin', nombre = ?, clave_hash = ?, activo = 1 WHERE id = ?", [$n, password_hash($c, PASSWORD_DEFAULT), $ex['id']]);
            else insertar('usuarios', ['rol' => 'admin', 'nombre' => $n, 'email' => $em, 'telefono' => ajuste('whatsapp'), 'clave_hash' => password_hash($c, PASSWORD_DEFAULT), 'activo' => 1, 'debe_cambiar' => 0, 'creado' => ahora()]);
            login_usuario(fila('SELECT * FROM usuarios WHERE LOWER(email) = ?', [$em]));
            recordar_crear((int)valor('SELECT id FROM usuarios WHERE LOWER(email) = ?', [$em]));
            flash('¡Listo! Tu panel quedó configurado. Empieza por el Manual.');
            redirigir(url('admin/?p=manual'));
        }
    }
    cabecera('Configurar panel', ['body' => 'pagina', 'sin_visita' => true]);
    ?>
    <section class="cabeza-pag" style="min-height:100vh"><div class="envoltura panel-centro"><div class="caja">
      <span class="antetitulo">Primer uso</span><h1 class="titulo" style="font-size:44px">Crea tu acceso al panel</h1>
      <p class="sub" style="font-size:15px">Esta pantalla solo aparece una vez. Con este correo y contraseña entrarás a administrar tu web.</p>
      <?php if ($error): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post"><?= csrf_campo() ?><div class="campos">
        <div class="campo ancho"><label>Tu nombre</label><input name="nombre" required value="<?= e($_POST['nombre'] ?? ajuste('nombre_entrenador')) ?>"></div>
        <div class="campo ancho"><label>Correo</label><input name="email" type="email" required value="<?= e($_POST['email'] ?? ajuste('email')) ?>"></div>
        <div class="campo"><label>Contraseña (mín. 10)</label><input name="clave" type="password" required minlength="10" autocomplete="new-password"></div>
        <div class="campo"><label>Repítela</label><input name="repite" type="password" required minlength="10" autocomplete="new-password"></div>
      </div><button class="btn btn-rojo btn-bloque mt2" type="submit">Crear mi panel</button></form>
    </div></div></section>
    <?php pie(['sin_wa' => true]);
    exit;
}

if (!es_admin()) redirigir(url('ingresar.php?r=' . urlencode('admin/' . (isset($_GET['p']) ? '?p=' . $_GET['p'] : ''))));

$p = preg_replace('/[^a-z]/', '', (string)($_GET['p'] ?? 'inicio')) ?: 'inicio';
if ($p === 'salir') {
    if (es_post()) { csrf_verificar(); logout_usuario(); }
    redirigir(url(''));
}
$archivo = __DIR__ . '/vistas/' . $p . '.php';
if (!is_file($archivo)) $archivo = __DIR__ . '/vistas/inicio.php';
require $archivo;
