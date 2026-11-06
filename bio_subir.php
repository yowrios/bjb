<?php
/**
 * Recibe un PDF de bioimpedancia ya leído en el navegador.
 * Cliente: siempre se guarda en su propia cuenta. Bryan: elige el cliente o crea uno nuevo.
 */
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/bio.php';
iniciar_sesion();
cabeceras_seguridad();
$u = usuario();
if (!$u) json_out(['ok' => false, 'error' => 'Inicia sesión para cargar tu bioimpedancia.'], 401);
if (!es_post()) json_out(['ok' => false], 405);
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) json_out(['ok' => false, 'error' => 'La sesión expiró. Recarga la página.'], 419);
try {
    $raw = json_decode((string)($_POST['datos'] ?? ''), true);
    if (!is_array($raw)) throw new RuntimeException('No llegaron los datos del reporte.');
    $d = bio_validar($raw);
    $archivo = subir_archivo($_FILES['pdf'] ?? ['error' => UPLOAD_ERR_NO_FILE], TIPOS_PDF, true, 'bio', 8);
    $creado = null;
    if ($u['rol'] === 'admin') {
        $dest = (string)($_POST['usuario_id'] ?? '');
        if ($dest === 'nuevo') {
            if ($d['nombre_reporte'] === '') throw new RuntimeException('El reporte no trae nombre: elige un cliente existente.');
            if (empty($_POST['consentimiento'])) throw new RuntimeException('Confirma que tienes autorización para guardar sus datos de salud.');
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = 'cliente' . time() . random_int(10, 99) . '@sin-correo.local';
            if (valor('SELECT COUNT(*) FROM usuarios WHERE LOWER(email) = ?', [$email])) throw new RuntimeException('Ya existe un cliente con ese correo.');
            $nombre = mb_convert_case(mb_strtolower($d['nombre_reporte']), MB_CASE_TITLE);
            $clave = substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
            $uid = insertar('usuarios', ['rol' => 'cliente', 'nombre' => $nombre, 'email' => $email, 'telefono' => mb_substr(trim((string)($_POST['telefono'] ?? '')), 0, 30),
                'clave_hash' => password_hash($clave, PASSWORD_DEFAULT), 'activo' => 1, 'debe_cambiar' => 1, 'sexo' => $d['sexo'], 'estatura_cm' => $d['estatura_cm'],
                'origen' => 'Bioimpedancia Smart Fit', 'consentimiento' => ahora(), 'creado' => ahora()]);
            $creado = ['id' => $uid, 'nombre' => $nombre, 'clave' => $clave];
        } else {
            $uid = (int)$dest;
            if (!$uid || !valor("SELECT COUNT(*) FROM usuarios WHERE id = ? AND rol = 'cliente'", [$uid])) throw new RuntimeException('Elige el cliente.');
        }
        $por = 'admin';
    } else { $uid = (int)$u['id']; $por = 'cliente'; }
    $r = bio_guardar($uid, $d, $archivo, $por);
    evento('bioimpedancia', $r['estado'], $d['fecha']);
    $c = fila('SELECT id, nombre FROM usuarios WHERE id = ?', [$uid]);
    json_out(['ok' => true, 'estado' => $r['estado'], 'historial' => $r['historial'], 'cliente' => $c, 'creado' => $creado, 'fecha' => fecha_corta($d['fecha'])]);
} catch (RuntimeException $ex) {
    json_out(['ok' => false, 'error' => $ex->getMessage()]);
}
