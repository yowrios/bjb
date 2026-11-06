<?php
/**
 * Entrega de archivos privados (fuera de la web), con permisos:
 *  t=foto        foto de evolución: la dueña o Bryan
 *  t=comprobante comprobante de pago: quien lo subió o Bryan
 *  t=video       video protegido: quien tenga acceso (soporta adelantar/atrasar)
 *  t=formulario  fotos del formulario inicial: solo Bryan
 */
require __DIR__ . '/inc/app.php';
iniciar_sesion();
$u = usuario();
$t = $_GET['t'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$archivo = null;
$prohibido = fn() => (http_response_code(403) && exit('Sin permiso.'));

switch ($t) {
    case 'foto':
        $f = fila('SELECT * FROM fotos WHERE id = ?', [$id]);
        if (!$f || !$u || ($u['rol'] !== 'admin' && (int)$f['usuario_id'] !== (int)$u['id'])) $prohibido();
        $archivo = $f['archivo'];
        break;
    case 'comprobante':
        $p = fila('SELECT * FROM pagos WHERE id = ?', [$id]);
        if (!$p || !$u || ($u['rol'] !== 'admin' && (int)$p['usuario_id'] !== (int)$u['id'])) $prohibido();
        $archivo = $p['comprobante'];
        break;
    case 'formulario':
        $f = fila('SELECT * FROM formularios WHERE id = ?', [$id]);
        if (!$f || !es_admin()) $prohibido();
        $lista = json_decode((string)$f['fotos'], true) ?: [];
        $archivo = $lista[(int)($_GET['n'] ?? 0)] ?? null;
        break;
    case 'bio':
        $b = fila('SELECT * FROM bioimpedancias WHERE id = ?', [$id]);
        if (!$b || !$u || ($u['rol'] !== 'admin' && (int)$b['usuario_id'] !== (int)$u['id'])) $prohibido();
        $archivo = $b['archivo'];
        break;
    case 'video':
        $v = fila('SELECT * FROM videos WHERE id = ?', [$id]);
        if (!$v || !puede_ver_video($u, $v)) $prohibido();
        $archivo = $v['video_archivo'];
        break;
    default:
        http_response_code(404); exit;
}
if (!$archivo) { http_response_code(404); exit('Sin archivo.'); }
$ruta = realpath(PRIV_DIR . '/archivos/' . $archivo);
if (!$ruta || !str_starts_with(str_replace('\\', '/', $ruta), PRIV_DIR . '/archivos/') || !is_file($ruta)) { http_response_code(404); exit('No encontrado.'); }

session_write_close();
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($ruta) ?: 'application/octet-stream';
$tam = filesize($ruta);
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');
header('Content-Disposition: inline');
// Rangos (para poder adelantar el video sin descargarlo todo)
$ini = 0; $fin = $tam - 1;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') $ini = (int)$m[1];
    if ($m[2] !== '') $fin = min((int)$m[2], $tam - 1);
    if ($m[1] === '' && $m[2] !== '') { $ini = max(0, $tam - (int)$m[2]); $fin = $tam - 1; }
    if ($ini > $fin || $ini >= $tam) { http_response_code(416); header("Content-Range: bytes */$tam"); exit; }
    http_response_code(206);
    header("Content-Range: bytes $ini-$fin/$tam");
}
header('Content-Length: ' . ($fin - $ini + 1));
$fp = fopen($ruta, 'rb');
fseek($fp, $ini);
$quedan = $fin - $ini + 1;
while ($quedan > 0 && !feof($fp)) {
    $b = fread($fp, (int)min(1048576, $quedan));
    echo $b; flush();
    $quedan -= strlen($b);
}
fclose($fp);
