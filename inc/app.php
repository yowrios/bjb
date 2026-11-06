<?php
/**
 * BRYAN JFIT — núcleo de la aplicación.
 * Base de datos, sesión, seguridad, ajustes, archivos y utilidades comunes.
 * Todo archivo de entrada (index.php, admin/index.php, cliente/index.php...) incluye este archivo.
 */
declare(strict_types=1);

define('APP_ROOT', str_replace('\\', '/', dirname(__DIR__)));
define('APP_VERSION', '1.0.0');

/* ---------- Carpeta privada (fuera de la web) ----------
 * Guarda la base de datos, la configuración y los archivos privados (fotos de evolución,
 * comprobantes de pago, videos protegidos). Por defecto: una carpeta AL LADO de la web,
 * nunca dentro, para que nadie pueda descargarla con un enlace.
 */
(function () {
    $dir = getenv('BJFIT_PRIVADO') ?: (dirname(APP_ROOT) . '/bryanjfit_privado');
    $dir = rtrim(str_replace('\\', '/', $dir), '/');
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    if (!is_dir($dir) || !is_writable($dir)) {
        // Último recurso: dentro de la web, con nombre imposible de adivinar y bloqueo.
        $alt = APP_ROOT . '/inc/privado_' . substr(hash('sha256', APP_ROOT . php_uname()), 0, 24);
        if (!is_dir($alt)) @mkdir($alt, 0750, true);
        @file_put_contents($alt . '/.htaccess', "Require all denied\nDeny from all\n");
        @file_put_contents($alt . '/index.html', '');
        $dir = $alt;
    }
    foreach (['archivos', 'archivos/fotos', 'archivos/comprobantes', 'archivos/videos', 'archivos/formularios', 'respaldos'] as $sub) {
        if (!is_dir("$dir/$sub")) @mkdir("$dir/$sub", 0750, true);
    }
    define('PRIV_DIR', $dir);
})();

/* Configuración opcional (para usar MySQL en lugar de SQLite):
 * crear PRIV_DIR/config.php con:  <?php return ['db'=>'mysql','host'=>'localhost','name'=>'bryanjfit','user'=>'...','pass'=>'...'];
 */
$GLOBALS['BJ_CONFIG'] = is_file(PRIV_DIR . '/config.php') ? (require PRIV_DIR . '/config.php') : [];

date_default_timezone_set($GLOBALS['BJ_CONFIG']['zona'] ?? 'America/Bogota');
mb_internal_encoding('UTF-8');

/* ---------- URL base (funciona en raíz o en subcarpeta) ---------- */
(function () {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $real = str_replace('\\', '/', (string)realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $rel = ($real && str_starts_with($real, APP_ROOT)) ? substr($real, strlen(APP_ROOT)) : '/' . basename($script);
    $base = substr($script, 0, max(0, strlen($script) - strlen($rel)));
    define('BASE', rtrim($base, '/'));
})();

function url(string $p = ''): string { return BASE . '/' . ltrim($p, '/'); }
/** Dirección completa para compartir (QR, tarjeta). Usa el dominio de Ajustes si ya existe. */
function url_publica(string $p = ''): string {
    $base = rtrim((string)ajuste('url_publica', ''), '/');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE;
    }
    return $base . '/' . ltrim($p, '/');
}
function asset(string $p): string {
    $f = APP_ROOT . '/assets/' . $p;
    return url('assets/' . $p) . (is_file($f) ? '?v=' . filemtime($f) : '');
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ahora(): string { return date('Y-m-d H:i:s'); }
function hoy(): string { return date('Y-m-d'); }
function es_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function redirigir(string $to): never { header('Location: ' . $to); exit; }
function json_out($data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function pesos($n): string {
    $n = (float)$n;
    return '$' . number_format($n, 0, ',', '.');
}
function fecha_larga(?string $f): string {
    if (!$f) return '—';
    $t = strtotime($f);
    if (!$t) return '—';
    $m = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return date('j', $t) . ' de ' . $m[(int)date('n', $t) - 1] . ' de ' . date('Y', $t);
}
function fecha_corta(?string $f): string {
    if (!$f) return '—';
    $t = strtotime($f);
    if (!$t) return '—';
    $m = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    return date('j', $t) . ' ' . $m[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
}
function hace(?string $f): string {
    if (!$f) return 'nunca';
    $d = time() - strtotime($f);
    if ($d < 60) return 'hace un momento';
    if ($d < 3600) return 'hace ' . floor($d / 60) . ' min';
    if ($d < 86400) return 'hace ' . floor($d / 3600) . ' h';
    if ($d < 86400 * 30) return 'hace ' . floor($d / 86400) . ' días';
    return fecha_corta($f);
}
function slugify(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string)$s, '-') ?: 'item';
}
function solo_digitos(?string $s): string { return preg_replace('/\D+/', '', (string)$s); }
/** Número de WhatsApp en formato internacional (Colombia por defecto). */
function wa_numero(?string $tel): string {
    $d = solo_digitos($tel);
    if (strlen($d) === 10 && $d[0] === '3') $d = '57' . $d;
    return $d;
}
function wa_link(?string $tel, string $texto = ''): string {
    $n = wa_numero($tel);
    return 'https://wa.me/' . $n . ($texto !== '' ? '?text=' . rawurlencode($texto) : '');
}
function token(int $n = 24): string { return bin2hex(random_bytes($n)); }
function lineas(?string $s): array {
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$s)), 'strlen'));
}
function num($v): ?float {
    if ($v === null || $v === '') return null;
    $v = str_replace(',', '.', (string)$v);
    return is_numeric($v) ? (float)$v : null;
}

/* ---------- Base de datos ---------- */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = $GLOBALS['BJ_CONFIG'];
    if (($c['db'] ?? 'sqlite') === 'mysql') {
        $pdo = new PDO('mysql:host=' . ($c['host'] ?? 'localhost') . ';dbname=' . $c['name'] . ';charset=utf8mb4',
            $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        define('DB_DRIVER', 'mysql');
    } else {
        $pdo = new PDO('sqlite:' . PRIV_DIR . '/bryanjfit.sqlite', null, null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        // WAL + synchronous NORMAL: muchas visitas a la vez sin bloqueos (medido: ~2.800 páginas/seg con 60 procesos, 0 errores)
        $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA synchronous = NORMAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 8000;');
        define('DB_DRIVER', 'sqlite');
    }
    require_once __DIR__ . '/schema.php';
    schema_asegurar($pdo);
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $st = db()->prepare($sql); $st->execute($p); return $st; }
function fila(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function filas(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function valor(string $sql, array $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
function insertar(string $tabla, array $d): int {
    $cols = array_keys($d);
    q('INSERT INTO ' . $tabla . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($d));
    return (int)db()->lastInsertId();
}
function actualizar(string $tabla, int $id, array $d): void {
    if (!$d) return;
    $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($d)));
    q("UPDATE $tabla SET $set WHERE id = ?", [...array_values($d), $id]);
}
/** Expresión SQL de "mes" (AAAA-MM) portable. */
function sql_mes(string $col): string { return DB_DRIVER === 'mysql' ? "DATE_FORMAT($col,'%Y-%m')" : "strftime('%Y-%m',$col)"; }
function sql_dia(string $col): string { return DB_DRIVER === 'mysql' ? "DATE($col)" : "date($col)"; }

/* ---------- Ajustes (todo lo editable sin programar) ---------- */
function ajuste(string $clave, $defecto = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (filas('SELECT clave, valor FROM ajustes') as $r) $cache[$r['clave']] = $r['valor'];
    }
    if (func_num_args() === 3) { /* no usado */ }
    return array_key_exists($clave, $cache) && $cache[$clave] !== null ? $cache[$clave] : $defecto;
}
function guardar_ajuste(string $clave, ?string $valor): void {
    if (valor('SELECT COUNT(*) FROM ajustes WHERE clave = ?', [$clave])) q('UPDATE ajustes SET valor = ? WHERE clave = ?', [$valor, $clave]);
    else q('INSERT INTO ajustes (clave, valor) VALUES (?, ?)', [$clave, $valor]);
}
function ajuste_json(string $clave, $defecto = []) {
    $v = json_decode((string)ajuste($clave, ''), true);
    return is_array($v) ? $v : $defecto;
}

/* ---------- Sesión y seguridad ---------- */
function iniciar_sesion(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('bjfit');
    session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'path' => (BASE ?: '/'), 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
    @ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 30));
    session_save_path_privado();
    session_start();
}
function session_save_path_privado(): void {
    $d = PRIV_DIR . '/sesiones';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    if (is_dir($d) && is_writable($d)) session_save_path($d);
}
function csrf(): string {
    iniciar_sesion();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = token(16);
    return $_SESSION['csrf'];
}
function csrf_campo(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">'; }
function csrf_verificar(): void {
    iniciar_sesion();
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!$t || !hash_equals($_SESSION['csrf'] ?? '', (string)$t)) {
        http_response_code(419);
        exit('La sesión expiró. Vuelva atrás y recargue la página.');
    }
}
function cabeceras_seguridad(): void {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
}
function usuario(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    iniciar_sesion();
    $id = (int)($_SESSION['uid'] ?? 0);
    if (!$id) $id = recordar_validar();
    $u = $id ? fila('SELECT * FROM usuarios WHERE id = ? AND activo = 1', [$id]) : null;
    return $u;
}

/* ---------- «Recordarme 30 días en este equipo» ---------- */
function cookie_opciones(int $expira): array {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ['expires' => $expira, 'path' => (BASE ?: '/'), 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https];
}
function recordar_crear(int $uid): void {
    $sel = bin2hex(random_bytes(12)); $val = bin2hex(random_bytes(32));
    $exp = time() + 60 * 60 * 24 * 30;
    insertar('recordar', ['usuario_id' => $uid, 'selector' => $sel, 'validador' => hash('sha256', $val), 'expira' => date('Y-m-d H:i:s', $exp), 'creado' => ahora()]);
    setcookie('bj_rec', $sel . ':' . $val, cookie_opciones($exp));
}
/** Si el navegador tiene la cookie de «recordarme» válida, inicia la sesión sola. */
function recordar_validar(): int {
    $c = (string)($_COOKIE['bj_rec'] ?? '');
    if (!preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $c, $m)) return 0;
    try {
        $r = fila('SELECT * FROM recordar WHERE selector = ?', [$m[1]]);
        if (!$r || $r['expira'] < ahora() || !hash_equals($r['validador'], hash('sha256', $m[2]))) {
            if (!headers_sent()) setcookie('bj_rec', '', cookie_opciones(time() - 3600));
            return 0;
        }
        if (!headers_sent()) session_regenerate_id(true);
        $_SESSION['uid'] = (int)$r['usuario_id'];
        q('UPDATE usuarios SET ultimo_acceso = ? WHERE id = ?', [ahora(), $r['usuario_id']]);
        return (int)$r['usuario_id'];
    } catch (Throwable $t) { return 0; }
}
function recordar_borrar(): void {
    $c = (string)($_COOKIE['bj_rec'] ?? '');
    if ($c !== '') { try { q('DELETE FROM recordar WHERE selector = ?', [substr($c, 0, 24)]); } catch (Throwable $t) {} }
    setcookie('bj_rec', '', cookie_opciones(time() - 3600));
    q('DELETE FROM recordar WHERE expira < ?', [ahora()]);
}
function es_admin(): bool { $u = usuario(); return $u && $u['rol'] === 'admin'; }
function login_usuario(array $u): void {
    iniciar_sesion();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    q('UPDATE usuarios SET ultimo_acceso = ? WHERE id = ?', [ahora(), $u['id']]);
    // Une las visitas anónimas de este navegador con la persona.
    if (!empty($_COOKIE['bj_v'])) q('UPDATE visitas SET usuario_id = ? WHERE visitante = ? AND usuario_id IS NULL', [$u['id'], $_COOKIE['bj_v']]);
}
function logout_usuario(): void {
    iniciar_sesion();
    recordar_borrar();
    $_SESSION = [];
    session_regenerate_id(true);
}
/** Limita intentos de inicio de sesión por IP (10 en 15 minutos). */
function intentos_superados(string $tipo): bool {
    $ip = ip_hash();
    $desde = date('Y-m-d H:i:s', time() - 900);
    return (int)valor('SELECT COUNT(*) FROM intentos WHERE ip = ? AND tipo = ? AND fecha > ?', [$ip, $tipo, $desde]) >= 10;
}
function registrar_intento(string $tipo): void { insertar('intentos', ['ip' => ip_hash(), 'tipo' => $tipo, 'fecha' => ahora()]); }
function ip_real(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) return trim(explode(',', $_SERVER[$k])[0]);
    }
    return '0.0.0.0';
}
function ip_hash(): string { return substr(hash('sha256', ip_real() . '|' . ajuste('sal', 'bj')), 0, 20); }

/* ---------- Mensajes flash ---------- */
function flash(string $msg, string $tipo = 'ok'): void { iniciar_sesion(); $_SESSION['flash'][] = [$tipo, $msg]; }
function flashes(): string {
    iniciar_sesion();
    $h = '';
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) $h .= '<div class="flash flash-' . e($t) . '" role="status">' . e($m) . '</div>';
    unset($_SESSION['flash']);
    return $h;
}

/* ---------- Archivos ---------- */
const TIPOS_IMAGEN = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
const TIPOS_PDF = ['application/pdf' => 'pdf'];
const TIPOS_VIDEO = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];

/**
 * Guarda un archivo subido. $privado = true lo deja fuera de la web (se sirve con media.php).
 * Devuelve el nombre relativo guardado o null. Lanza RuntimeException con el motivo si falla.
 */
function subir_archivo(array $f, array $tipos, bool $privado, string $sub = '', int $maxMb = 20): ?string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $lim = ini_get('upload_max_filesize');
        throw new RuntimeException($f['error'] === UPLOAD_ERR_INI_SIZE ? "El archivo supera el límite del servidor ($lim)." : 'No se pudo subir el archivo (código ' . $f['error'] . ').');
    }
    if ($f['size'] > $maxMb * 1024 * 1024) throw new RuntimeException("El archivo supera $maxMb MB.");
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    if (!isset($tipos[$mime])) throw new RuntimeException('Tipo de archivo no permitido (' . $mime . ').');
    $nombre = date('Ymd') . '_' . token(8) . '.' . $tipos[$mime];
    $dirBase = $privado ? PRIV_DIR . '/archivos' : APP_ROOT . '/uploads';
    $dir = $dirBase . ($sub ? '/' . trim($sub, '/') : '');
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!move_uploaded_file($f['tmp_name'], "$dir/$nombre")) throw new RuntimeException('No se pudo guardar el archivo.');
    if (isset(TIPOS_IMAGEN[$mime]) && $mime !== 'image/gif') reducir_imagen("$dir/$nombre", $privado ? 1600 : 1920);
    return ($sub ? trim($sub, '/') . '/' : '') . $nombre;
}
/** Reduce imágenes grandes (fotos de celular de 8 MB) y corrige la orientación. */
function reducir_imagen(string $ruta, int $max): void {
    if (!function_exists('imagecreatefromstring')) return;
    $info = @getimagesize($ruta);
    if (!$info) return;
    [$w, $h] = $info;
    $orient = 1;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($ruta);
        $orient = (int)($ex['Orientation'] ?? 1);
    }
    if ($w <= $max && $h <= $max && $orient === 1) return;
    $img = @imagecreatefromstring((string)file_get_contents($ruta));
    if (!$img) return;
    if ($orient === 3) $img = imagerotate($img, 180, 0);
    if ($orient === 6) $img = imagerotate($img, -90, 0);
    if ($orient === 8) $img = imagerotate($img, 90, 0);
    $w = imagesx($img); $h = imagesy($img);
    $r = min(1, $max / max($w, $h));
    $nw = (int)round($w * $r); $nh = (int)round($h * $r);
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    match ($info[2]) {
        IMAGETYPE_PNG => imagepng($dst, $ruta, 8),
        IMAGETYPE_WEBP => imagewebp($dst, $ruta, 85),
        default => imagejpeg($dst, $ruta, 85),
    };
}
function img_publica(?string $f, string $defecto = ''): string { return $f ? url('uploads/' . $f) : $defecto; }
function media_url(string $tipo, int $id): string { return url('media.php?t=' . $tipo . '&id=' . $id); }

/* ---------- Video: identificar YouTube / Vimeo ---------- */
function video_embed(?string $u): ?string {
    $u = trim((string)$u);
    if ($u === '') return null;
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|live/))([\w-]{11})~', $u, $m))
        return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&modestbranding=1';
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)(?:/(\w+))?~', $u, $m))
        return 'https://player.vimeo.com/video/' . $m[1] . (isset($m[2]) ? '?h=' . $m[2] : '');
    return null;
}

/* ---------- Accesos a videos ---------- */
function puede_ver_video(?array $u, array $v): bool {
    if ((int)$v['gratis'] === 1) return true;
    if (!$u) return false;
    if ($u['rol'] === 'admin') return true;
    $h = hoy();
    // Acceso directo a ese video o a toda la biblioteca
    if (valor('SELECT COUNT(*) FROM accesos_video WHERE usuario_id = ? AND (video_id = ? OR video_id IS NULL) AND (hasta IS NULL OR hasta >= ?)', [$u['id'], $v['id'], $h])) return true;
    // Un plan activo que incluya la biblioteca
    return (bool)valor('SELECT COUNT(*) FROM membresias m JOIN planes p ON p.id = m.plan_id
        WHERE m.usuario_id = ? AND m.estado = ? AND (m.fin IS NULL OR m.fin >= ?) AND p.incluye_videos = 1', [$u['id'], 'activa', $h]);
}
function membresia_activa(int $uid): ?array {
    return fila('SELECT m.*, p.nombre AS plan_nombre_actual, p.incluye_videos FROM membresias m LEFT JOIN planes p ON p.id = m.plan_id
        WHERE m.usuario_id = ? AND m.estado = ? ORDER BY m.inicio DESC LIMIT 1', [$uid, 'activa']);
}

/* ---------- Íconos SVG (sin librerías externas) ---------- */
function icono(string $n, string $cls = ''): string {
    static $p = [
        'whatsapp' => '<path d="M20.5 3.5A11 11 0 0 0 3.3 17.2L2 22l4.9-1.3A11 11 0 0 0 23 12a10.9 10.9 0 0 0-2.5-8.5zM12 20.1a9 9 0 0 1-4.6-1.3l-.3-.2-2.9.8.8-2.8-.2-.3A9.1 9.1 0 1 1 12 20.1zm5-6.8c-.3-.1-1.6-.8-1.9-.9s-.4-.1-.6.1-.7.9-.9 1.1-.3.2-.6.1a7.4 7.4 0 0 1-3.7-3.2c-.3-.5.3-.4.8-1.4.1-.2 0-.3 0-.4l-.9-2.1c-.2-.5-.5-.5-.6-.5h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-1 2.3 5.3 5.3 0 0 0 1.1 2.8 12 12 0 0 0 4.6 4.1c1.7.7 2.4.8 3.2.7a2.8 2.8 0 0 0 1.8-1.3 2.3 2.3 0 0 0 .2-1.3c-.1-.2-.3-.3-.6-.4z" fill="currentColor" stroke="none"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>',
        'tiktok' => '<path d="M16.5 3a4.5 4.5 0 0 0 4.5 4.5V11a8 8 0 0 1-4.5-1.4V16a6 6 0 1 1-6-6v3.5a2.5 2.5 0 1 0 2.5 2.5V3z" fill="currentColor" stroke="none"/>',
        'facebook' => '<path d="M14 8h3V4h-3a5 5 0 0 0-5 5v3H6v4h3v8h4v-8h3l1-4h-4V9a1 1 0 0 1 1-1z" fill="currentColor" stroke="none"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'play' => '<path d="M7 4v16l13-8z" fill="currentColor"/>',
        'pause' => '<path d="M7 4h4v16H7zM14 4h4v16h-4z" fill="currentColor"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'unlock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.8-1.2"/>',
        'timer' => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0M16 4.5a3.5 3.5 0 0 1 0 7M22 20a7 7 0 0 0-4-6.3"/>',
        'dumbbell' => '<path d="M6 6v12M18 6v12M3 9v6M21 9v6M6 12h12"/>',
        'flame' => '<path d="M12 22a7 7 0 0 0 7-7c0-4-3-6-4-10-2 2-3 4-3 6-1-1-2-2-2-4-3 3-5 5-5 8a7 7 0 0 0 7 7z"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'money' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01M18 12h.01"/>',
        'download' => '<path d="M12 3v12m0 0-5-5m5 5 5-5M4 21h16"/>',
        'upload' => '<path d="M12 21V9m0 0-5 5m5-5 5 5M4 3h16"/>',
        'camera' => '<path d="M3 8h4l2-3h6l2 3h4v12H3z"/><circle cx="12" cy="13" r="4"/>',
        'book' => '<path d="M4 4h6a3 3 0 0 1 3 3v14a2 2 0 0 0-2-2H4zM20 4h-6a3 3 0 0 0-3 3v14a2 2 0 0 1 2-2h7z"/>',
        'video' => '<rect x="2" y="5" width="14" height="14" rx="2"/><path d="m16 10 6-4v12l-6-4z"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'home' => '<path d="M3 11 12 3l9 8v10h-6v-6H9v6H3z"/>',
        'file' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
        'star' => '<path d="m12 3 2.8 5.8 6.2.9-4.5 4.4 1 6.2L12 17.3l-5.5 3 1-6.2L3 9.7l6.2-.9z"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1" fill="currentColor"/>',
        'heart' => '<path d="M12 21s-8-5-8-11a4.5 4.5 0 0 1 8-3 4.5 4.5 0 0 1 8 3c0 6-8 11-8 11z"/>',
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'logout' => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14"/>',
        'scale' => '<path d="M5 21h14M12 3v18M5 7h14M5 7l-3 7a3 3 0 0 0 6 0zM19 7l-3 7a3 3 0 0 0 6 0z"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .9-1 1.7M12 17h.01"/>',
        'link' => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'volume' => '<path d="M4 9h4l5-4v14l-5-4H4z"/><path d="M17 9a4 4 0 0 1 0 6M19.5 6.5a8 8 0 0 1 0 11"/>',
        'mute' => '<path d="M4 9h4l5-4v14l-5-4H4zM17 9l5 6M22 9l-5 6"/>',
        'refresh' => '<path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7"/>',
        'skip' => '<path d="M5 4l10 8-10 8zM19 5v14"/>',
        'expand' => '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
        'print' => '<path d="M6 9V3h12v6M6 18H4v-7h16v7h-2M8 14h8v7H8z"/>',
        'send' => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
        'telefono' => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 12l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
    ];
    return '<svg class="ico ' . e($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$n] ?? '') . '</svg>';
}

/* ---------- Tema de colores (se elige en el admin) ---------- */
/**
 * Combinaciones de colores: [nombre, color principal, color de acento, fondo base, descripción].
 * El fondo base tiñe todo el fondo (negro puro, azul noche, carbón cálido...).
 */
function temas(): array {
    return [
        'fuego' => ['Rojo fuego', '#ff2a2a', '#d4ff3a', '#0a0a0c', 'La sugerida: rojo intenso con detalles verde volt, como tu catálogo.'],
        'ambar' => ['Gimnasio nocturno (ámbar)', '#ffb020', '#ffe2a6', '#0a0705', 'Negro con luz ámbar de neón, como un gimnasio de noche. Combínalo con la foto en silueta a contraluz.'],
        'metal' => ['Heavy metal', '#d10f1f', '#c9ced6', '#09090b', 'Rojo sangre con plata: oscuro, rudo y elegante.'],
        'carbono' => ['Carbono y oro', '#e9b44c', '#f3ead8', '#0d0b08', 'Dorado sobre carbón cálido: se ve premium.'],
        'acero' => ['Acero azul', '#3d8bff', '#e2e8f0', '#070a10', 'Azul eléctrico con blanco frío: moderno y limpio.'],
        'neon' => ['Neón nocturno', '#ff2bd6', '#22e6ff', '#08061a', 'Rosa y cian neón sobre azul noche: muy llamativo.'],
        'volt' => ['Volt deportivo', '#c6ff3a', '#ff4d2e', '#090b08', 'Verde volt con naranja: energía de marca deportiva.'],
        'lava' => ['Lava', '#ff5a1f', '#ffd23f', '#0e0806', 'Naranja y amarillo sobre negro cálido: intensidad pura.'],
        'bosque' => ['Esmeralda', '#14c784', '#ffc83d', '#06100c', 'Verde esmeralda con oro: salud y naturaleza.'],
        'violeta' => ['Violeta', '#8b5cf6', '#ff3d8b', '#0b0814', 'Violeta con rosa: diferente y moderno.'],
        'mono' => ['Blanco y negro', '#f4f4f5', '#ff2a2a', '#0a0a0a', 'Minimalista: blanco sobre negro con un toque rojo.'],
        'personalizado' => ['Mis propios colores', '', '', '', 'Elige tú el color principal, el de acento y el fondo.'],
    ];
}
/** Texto negro o blanco según qué tan claro sea el color. */
function texto_sobre(string $hex): string {
    $h = ltrim($hex, '#');
    if (strlen($h) !== 6) return '#ffffff';
    [$r, $g, $b] = [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#0a0a0c' : '#ffffff';
}
function oscurecer(string $hex, float $f = 0.75): string {
    $h = ltrim($hex, '#');
    if (strlen($h) !== 6) return $hex;
    return sprintf('#%02x%02x%02x', (int)(hexdec(substr($h, 0, 2)) * $f), (int)(hexdec(substr($h, 2, 2)) * $f), (int)(hexdec(substr($h, 4, 2)) * $f));
}
/** Mezcla un color con blanco (f = cuánto blanco). */
function aclarar(string $hex, float $f): string {
    $h = ltrim($hex, '#');
    if (strlen($h) !== 6) return $hex;
    $c = fn($i) => (int)round(hexdec(substr($h, $i, 2)) + (255 - hexdec(substr($h, $i, 2))) * $f);
    return sprintf('#%02x%02x%02x', $c(0), $c(2), $c(4));
}
function color_valido($v, string $def): string { return preg_match('/^#[0-9a-f]{6}$/i', (string)$v) ? (string)$v : $def; }
/** [principal, principal oscuro, acento, fondo] del tema elegido (o de la temporada activa). */
function tema_colores(): array {
    $t = temporada_activa();
    if ($t && !empty($t['colores'])) { [$p, $a, $f] = $t['colores']; return [$p, oscurecer($p), $a, $f]; }
    $k = ajuste('tema', 'fuego');
    if ($k === 'personalizado') {
        $p = color_valido(ajuste('color_principal'), '#ff2a2a');
        return [$p, oscurecer($p), color_valido(ajuste('color_acento'), '#d4ff3a'), color_valido(ajuste('color_fondo'), '#0a0a0c')];
    }
    $t = temas()[$k] ?? temas()['fuego'];
    return [$t[1], oscurecer($t[1]), $t[2], $t[3]];
}
function tema_css(): string {
    [$p, $p2, $a, $f] = tema_colores();
    $css = ":root{--rojo:$p;--rojo-2:$p2;--volt:$a;--sobre-rojo:" . texto_sobre($p) . ';--sobre-volt:' . texto_sobre($a)
        . ";--negro:$f;--negro-2:" . aclarar($f, .025) . ';--gris-1:' . aclarar($f, .05) . ';--gris-2:' . aclarar($f, .085) . ';--gris-3:' . aclarar($f, .14);
    $css .= ";--textura:url('" . imagen_marca('textura') . "')";
    $css .= '}';
    // Fondo propio para la portada
    if (ajuste('fondo_hero')) $css .= ".hero{background:radial-gradient(ellipse 60% 70% at 72% 55%,color-mix(in srgb,var(--rojo) 22%,transparent),transparent 65%),linear-gradient(rgba(5,5,6,.55),rgba(5,5,6,.92)),url('" . imagen_marca('fondo_hero') . "') center/cover}";
    // Foto principal como silueta a contraluz (estilo gimnasio nocturno)
    if (ajuste('hero_silueta', '0') === '1') $css .= '.hero-foto img{filter:brightness(0) drop-shadow(0 0 1.5px var(--rojo)) drop-shadow(0 0 3px var(--rojo)) drop-shadow(0 0 22px color-mix(in srgb,var(--rojo) 65%,transparent))}'
        . '.hero-foto::before{top:6%;width:118%;aspect-ratio:.62;border-radius:14px;border:2px solid var(--rojo);box-shadow:0 0 22px var(--rojo),0 0 80px color-mix(in srgb,var(--rojo) 40%,transparent),inset 0 0 50px color-mix(in srgb,var(--rojo) 25%,transparent);background:radial-gradient(ellipse 60% 55% at 50% 55%,color-mix(in srgb,var(--rojo) 55%,transparent),transparent 72%)}'
        . '.hero-foto::after{content:"";position:absolute;left:50%;bottom:-14px;width:150%;height:46px;transform:translateX(-50%);background:radial-gradient(ellipse at center,color-mix(in srgb,var(--rojo) 60%,transparent),transparent 70%);filter:blur(8px);z-index:1}'
        . '.hero-fondo{-webkit-text-stroke-color:color-mix(in srgb,var(--rojo) 18%,transparent)}'
        . '.hero:not(.sin-neon)::before{content:"";position:absolute;inset:0;pointer-events:none;z-index:1;opacity:.85;background:'
        . 'linear-gradient(90deg,transparent 34%,var(--rojo) 34% 58%,transparent 58%) 0 16%/100% 2px no-repeat,linear-gradient(90deg,transparent 44%,var(--rojo) 44% 66%,transparent 66%) 0 21%/100% 2px no-repeat;'
        . 'filter:drop-shadow(0 0 6px var(--rojo))}';
    return $css;
}

/* ---------- Temporadas (Navidad, Halloween, Día de la madre...) ---------- */
/** N-ésimo día de la semana de un mes (dow: 0=domingo ... 6=sábado). n = -1 para el último. */
function enesimo_dia(int $anio, int $mes, int $dow, int $n): string {
    if ($n < 0) { $d = new DateTime("$anio-$mes-01"); $d->modify('last day of this month'); while ((int)$d->format('w') !== $dow) $d->modify('-1 day'); return $d->format('Y-m-d'); }
    $d = new DateTime("$anio-$mes-01");
    while ((int)$d->format('w') !== $dow) $d->modify('+1 day');
    $d->modify('+' . ($n - 1) . ' week');
    return $d->format('Y-m-d');
}
/**
 * Catálogo de temporadas: nombre, colores [principal, acento, fondo], adornos que caen, mensaje sugerido y fechas.
 * Las fechas móviles (Día de la madre, del padre, Amor y amistad...) se calculan para Colombia.
 */
function temporadas_catalogo(?int $anio = null): array {
    $y = $anio ?? (int)date('Y');
    $madre = enesimo_dia($y, 5, 0, 2); $padre = enesimo_dia($y, 6, 0, 3); $amistad = enesimo_dia($y, 9, 6, 3);
    $nino = enesimo_dia($y, 4, 6, -1); $black = enesimo_dia($y, 11, 5, -1);
    $menos = fn(string $f, int $d) => date('Y-m-d', strtotime("$f -$d days"));
    return [
        'navidad' => ['Navidad', ['#d4202f', '#f4c95d', '#071510'], '❄️🎅🎄', 'Que esta Navidad el mejor regalo sea tu salud. Gracias por entrenar conmigo este año.', "$y-12-01", "$y-12-25", 'Feliz Navidad'],
        'ano_nuevo' => ['Año nuevo', ['#f5c542', '#fff4d6', '#0a0910'], '🎆✨🥂', 'Año nuevo, cuerpo nuevo. Empieza tu evolución con toda la energía.', "$y-12-26", ($y + 1) . '-01-15', '¡Feliz ' . ($y + 1) . '!'],
        'ano_nuevo_ini' => ['Año nuevo', ['#f5c542', '#fff4d6', '#0a0910'], '🎆✨🥂', 'Año nuevo, cuerpo nuevo. Empieza tu evolución con toda la energía.', "$y-01-01", "$y-01-15", '¡Feliz ' . $y . '!'],
        'san_valentin' => ['San Valentín', ['#ff3d6e', '#ffc2d1', '#14070c'], '💘💕', 'Entrenar juntos también es amor. Celébralo con tu pareja.', "$y-02-07", "$y-02-14", 'Feliz San Valentín'],
        'mujer' => ['Día de la mujer', ['#c084fc', '#fbcfe8', '#120a16'], '🌸💜🌷', 'Por tu fuerza, tu constancia y todo lo que logras. Hoy y siempre.', "$y-03-01", "$y-03-08", 'Feliz Día de la Mujer'],
        'actividad' => ['Día de la actividad física', ['#22c55e', '#facc15', '#07100a'], '⚡🏃', 'El 6 de abril celebramos el movimiento. ¡Hoy no se negocia el entreno!', "$y-04-01", "$y-04-06", '¡A moverse!'],
        'nino' => ['Día del niño', ['#38bdf8', '#facc15', '#07101a'], '🎈🎉', 'Los niños aprenden con el ejemplo. Entrena, juega y disfruta en familia.', $menos($nino, 6), $nino, 'Feliz Día del Niño'],
        'madre' => ['Día de la madre', ['#f472b6', '#fde2e4', '#140a10'], '💐🌷💗', 'Para la mujer que nos enseñó a no rendirnos. Regálale salud a mamá.', "$y-05-01", $madre, 'Feliz día, mamá'],
        'padre' => ['Día del padre', ['#3b82f6', '#e5c07b', '#070b14'], '⭐🏆', 'Para el primer héroe. El mejor regalo para papá es fuerza y salud.', "$y-06-01", $padre, 'Feliz día, papá'],
        'independencia' => ['Independencia de Colombia', ['#fcd116', '#ce1126', '#060a1c'], '🎉⭐', 'Orgullo colombiano en cada repetición. ¡Entrena con toda!', "$y-07-14", "$y-07-20", '¡Viva Colombia!'],
        'amistad' => ['Amor y amistad', ['#ff4d6d', '#ffd166', '#14080b'], '💛🎈', 'Los amigos que entrenan juntos, siguen juntos. Trae a tu parcero.', "$y-09-01", $amistad, 'Feliz Amor y Amistad'],
        'octubre_rosa' => ['Octubre rosa', ['#ff5fa2', '#ffe4ef', '#140810'], '🎗️💗', 'El ejercicio también es prevención. Cuídate, quiérete y tócate.', "$y-10-01", "$y-10-24", 'Octubre Rosa'],
        'halloween' => ['Halloween', ['#ff7a00', '#9d4dff', '#0a0710'], '🎃🦇🕸️', 'Que lo único que te dé miedo sea saltarte el entreno.', "$y-10-25", "$y-10-31", 'Feliz Halloween'],
        'black_friday' => ['Black Friday', ['#f5f5f5', '#ff2a2a', '#000000'], '🏷️⚡', 'El mejor precio del año en planes y paquetes de videos.', $menos($black, 6), date('Y-m-d', strtotime("$black +3 days")), 'Black Friday'],
    ];
}
/** Lista para el panel (sin la entrada técnica de enero). */
function temporadas_lista(): array { $c = temporadas_catalogo(); unset($c['ano_nuevo_ini']); return $c; }
/**
 * Temporada que se muestra hoy, o null. Prioridad: la que Bryan encendió a mano; luego la que toca por fecha
 * (si está en automático). Un administrador puede ver cualquiera con ?temporada=clave.
 */
function temporada_activa(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    $conf = ajuste_json('temporadas');
    $cat = temporadas_catalogo();
    $fila = function (string $k) use ($cat, $conf) {
        $base = $k === 'ano_nuevo_ini' ? 'ano_nuevo' : $k;
        [$n, $col, $ad, $msg, , , $tit] = $cat[$k];
        $c = $conf[$base] ?? [];
        return ['clave' => $base, 'nombre' => $n, 'colores' => !empty($c['sin_colores']) ? null : $col, 'adornos' => !empty($c['sin_adornos']) ? '' : $ad,
            'efectos' => empty($c['sin_adornos']), 'titulo' => trim((string)($c['titulo'] ?? '')) !== '' ? $c['titulo'] : $tit,
            'mensaje' => trim((string)($c['mensaje'] ?? '')) !== '' ? $c['mensaje'] : $msg, 'enlace' => (string)($c['enlace'] ?? '')];
    };
    $prev = (string)($_GET['temporada'] ?? '');
    if ($prev !== '' && isset($cat[$prev]) && session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['uid']) && es_admin()) return $cache = $fila($prev);
    foreach ($cat as $k => $t) { $base = $k === 'ano_nuevo_ini' ? 'ano_nuevo' : $k; if (($conf[$base]['modo'] ?? '') === 'on') return $cache = $fila($k); }
    if (ajuste('temporadas_auto', '1') !== '1') return null;
    $h = hoy();
    foreach ($cat as $k => $t) {
        $base = $k === 'ano_nuevo_ini' ? 'ano_nuevo' : $k;
        if (($conf[$base]['modo'] ?? 'auto') !== 'auto') continue;
        if ($h >= $t[4] && $h <= $t[5]) return $cache = $fila($k);
    }
    return null;
}

/* ---------- Beneficios (regalo de cumpleaños, referidos...) ---------- */
function beneficios(): array {
    $b = [];
    if (ajuste('regalo_cumple_activo', '1') === '1') $b['cumple'] = ['🎂', 'Regalo de cumpleaños', ajuste('regalo_cumple_texto')];
    if (ajuste('referidos_activo', '0') === '1') $b['referidos'] = ['🤝', 'Trae a un amigo', ajuste('referidos_texto')];
    if (ajuste('valoracion_gratis_activo', '0') === '1') $b['valoracion'] = ['📋', 'Valoración gratis', ajuste('valoracion_gratis_texto')];
    if (ajuste('beneficio_extra_activo', '0') === '1' && ajuste('beneficio_extra_titulo')) $b['extra'] = ['⭐', ajuste('beneficio_extra_titulo'), ajuste('beneficio_extra_texto')];
    return $b;
}
/** ¿Es (o fue hace pocos días) el cumpleaños de esta persona? Devuelve los días desde el cumpleaños (0 = hoy) o null. */
function dias_desde_cumple(?string $nac, int $ventana): ?int {
    if (!$nac || !preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $nac, $m)) return null;
    for ($i = 0; $i <= $ventana; $i++) { $d = strtotime("-$i days"); if (date('m-d', $d) === "$m[1]-$m[2]") return $i; }
    return null;
}

function tienda_activa(): bool { return ajuste('tienda_activa', '0') === '1'; }

/* ---------- Logo y fotos del diseño (se cambian en el admin) ---------- */
function imagen_marca(string $clave): string {
    $def = ['logo' => 'img/logo.webp', 'foto_hero' => 'img/bryan-full.webp', 'foto_sobre' => 'img/bryan-polo.webp', 'textura' => 'img/textura.jpg', 'fondo_hero' => 'img/textura.jpg'][$clave] ?? '';
    $v = (string)ajuste($clave, '');
    return ($v !== '' && is_file(APP_ROOT . '/uploads/' . $v)) ? url('uploads/' . $v) . '?v=' . filemtime(APP_ROOT . '/uploads/' . $v) : asset($def);
}
function icono_app(int $tam): string {
    $f = 'uploads/marca/icono-' . $tam . '.png';
    return is_file(APP_ROOT . '/' . $f) && ajuste('logo') ? url($f) . '?v=' . filemtime(APP_ROOT . '/' . $f) : asset('img/icono-' . ($tam === 48 ? '192' : $tam) . '.png');
}
/** Crea los íconos de la app (192, 512) y el favicon a partir del logo subido, sobre fondo negro. */
function generar_iconos(string $logoRel): void {
    if (!function_exists('imagecreatefromstring')) return;
    $src = @imagecreatefromstring((string)@file_get_contents(APP_ROOT . '/uploads/' . $logoRel));
    if (!$src) return;
    $dir = APP_ROOT . '/uploads/marca';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $w = imagesx($src); $h = imagesy($src);
    foreach ([48, 192, 512] as $t) {
        $c = imagecreatetruecolor($t, $t);
        imagefill($c, 0, 0, imagecolorallocate($c, 10, 10, 12));
        imagealphablending($c, true);
        $r = min($t * 0.8 / $w, $t * 0.8 / $h);
        $nw = (int)round($w * $r); $nh = (int)round($h * $r);
        imagecopyresampled($c, $src, (int)(($t - $nw) / 2), (int)(($t - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
        imagepng($c, "$dir/icono-$t.png");
    }
}

/* ---------- Estadísticas de visitas ---------- */
function registrar_visita(string $ruta): void {
    try {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua === '' || preg_match('~bot|crawl|spider|slurp|facebookexternalhit|headless|preview|lighthouse|python|curl|wget|monitor|uptime~i', $ua)) return;
        if (es_admin()) return; // las visitas de Bryan no cuentan
        $vis = $_COOKIE['bj_v'] ?? '';
        if (!preg_match('/^[a-f0-9]{24}$/', $vis)) {
            $vis = token(12);
            setcookie('bj_v', $vis, ['expires' => time() + 60 * 60 * 24 * 400, 'path' => (BASE ?: '/'), 'samesite' => 'Lax', 'httponly' => false]);
            $_COOKIE['bj_v'] = $vis;
        }
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($ref && parse_url($ref, PHP_URL_HOST) === $host) $ref = '';
        $u = usuario();
        $GLOBALS['BJ_VISITA_ID'] = insertar('visitas', [
            'fecha' => ahora(), 'dia' => hoy(), 'hora' => (int)date('G'), 'ruta' => mb_substr($ruta, 0, 190),
            'ref' => mb_substr($ref, 0, 250), 'utm_source' => mb_substr((string)($_GET['utm_source'] ?? ''), 0, 60),
            'utm_medium' => mb_substr((string)($_GET['utm_medium'] ?? ''), 0, 60), 'utm_campaign' => mb_substr((string)($_GET['utm_campaign'] ?? ''), 0, 80),
            'dispositivo' => dispositivo($ua), 'navegador' => navegador($ua), 'so' => sistema($ua),
            'pais' => substr((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''), 0, 2), 'ciudad' => '',
            'ip_hash' => ip_hash(), 'visitante' => $vis, 'usuario_id' => $u['id'] ?? null, 'duracion' => 0,
        ]);
        $_SESSION['bj_ip_pendiente'] = ip_real(); // la ubicación se calcula luego, sin demorar la página
    } catch (Throwable $t) { error_log('BJFIT visita: ' . $t->getMessage()); /* nunca romper la página por estadísticas */ }
}
function dispositivo(string $ua): string {
    if (preg_match('~iPad|Tablet~i', $ua)) return 'Tablet';
    if (preg_match('~Mobi|Android|iPhone~i', $ua)) return 'Celular';
    return 'Computador';
}
function navegador(string $ua): string {
    foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'SamsungBrowser' => 'Samsung', 'Instagram' => 'Instagram', 'FBAN' => 'Facebook', 'FBAV' => 'Facebook', 'TikTok' => 'TikTok', 'musical_ly' => 'TikTok', 'Chrome' => 'Chrome', 'CriOS' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'] as $k => $n)
        if (str_contains($ua, $k)) return $n;
    return 'Otro';
}
function sistema(string $ua): string {
    foreach (['Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iOS', 'Windows' => 'Windows', 'Mac OS' => 'macOS', 'Linux' => 'Linux'] as $k => $n)
        if (str_contains($ua, $k)) return $n;
    return 'Otro';
}
function evento(string $tipo, string $ref = '', string $valor = ''): void {
    try {
        $u = usuario();
        insertar('eventos', ['fecha' => ahora(), 'visitante' => substr((string)($_COOKIE['bj_v'] ?? ''), 0, 24),
            'usuario_id' => $u['id'] ?? null, 'tipo' => $tipo, 'ref' => mb_substr($ref, 0, 190), 'valor' => mb_substr($valor, 0, 250)]);
    } catch (Throwable $t) {}
}
/** Ubicación aproximada (ciudad/país) a partir de la IP, con caché. Se llama desde api.php (en segundo plano). */
function geolocalizar(string $ip): array {
    if (ajuste('geo_activo', '1') !== '1') return ['', ''];
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return ['', 'Red local'];
    $h = substr(hash('sha256', $ip . '|' . ajuste('sal', 'bj')), 0, 20);
    $c = fila('SELECT pais, ciudad FROM geo_cache WHERE ip_hash = ?', [$h]);
    if ($c) return [$c['pais'], $c['ciudad']];
    $pais = ''; $ciudad = '';
    $ctx = stream_context_create(['http' => ['timeout' => 2, 'header' => "User-Agent: BryanJFit-web/1.0\r\n"]]);
    $r = @file_get_contents('https://ipwho.is/' . rawurlencode($ip) . '?fields=success,country_code,city,region', false, $ctx);
    $j = $r ? json_decode($r, true) : null;
    if (!empty($j['success'])) { $pais = (string)$j['country_code']; $ciudad = trim((string)$j['city'] . ((!empty($j['region']) && $j['region'] !== $j['city']) ? ', ' . $j['region'] : '')); }
    insertar('geo_cache', ['ip_hash' => $h, 'pais' => $pais, 'ciudad' => mb_substr($ciudad, 0, 120), 'fecha' => ahora()]);
    return [$pais, $ciudad];
}

/* ---------- Cálculos de entrenamiento ---------- */
/**
 * Carga sugerida para una máquina según el peso corporal.
 * coef = fracción del peso corporal que mueve un hombre intermedio para 10-12 repeticiones.
 */
function carga_sugerida(array $v, float $peso, string $sexo, string $nivel, string $objetivo, int $edad = 30): ?array {
    $coef = (float)($sexo === 'F' ? ($v['coef_mujer'] ?: $v['coef_hombre'] * 0.7) : $v['coef_hombre']);
    if ($coef <= 0 || $peso <= 0) return null;
    $fn = ['principiante' => 0.6, 'intermedio' => 1.0, 'avanzado' => 1.3][$nivel] ?? 0.6;
    [$fo, $reps, $desc] = match ($objetivo) {
        'fuerza' => [1.18, '5 a 6', 150],
        'resistencia' => [0.75, '15 a 20', 45],
        'grasa' => [0.82, '12 a 15', 45],
        default => [1.0, '10 a 12', 75],
    };
    $fe = $edad >= 60 ? 0.75 : ($edad >= 50 ? 0.85 : ($edad < 16 ? 0.7 : 1.0));
    $inc = max(0.5, (float)($v['incremento'] ?: 2.5));
    $kg = $peso * $coef * $fn * $fo * $fe;
    $r = fn($x) => max($inc, round($x / $inc) * $inc);
    return ['kg' => $r($kg), 'min' => $r($kg * 0.85), 'max' => $r($kg * 1.1), 'reps' => $reps, 'descanso' => $desc,
        'series' => $nivel === 'principiante' ? 3 : 4];
}
function un_rm(float $kg, int $reps): float { return $reps <= 1 ? $kg : round($kg * (1 + $reps / 30), 1); } // Epley
