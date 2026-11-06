<?php
/** Utilidades del panel de Bryan: plantilla, menú, formularios y acciones de negocio. */

function admin_menu(): array {
    $pend = (int)valor("SELECT COUNT(*) FROM pagos WHERE estado = 'pendiente'");
    $leads = (int)valor("SELECT COUNT(*) FROM leads WHERE estado = 'nuevo'");
    $chk = (int)valor("SELECT COUNT(*) FROM checkins WHERE respuesta IS NULL OR respuesta = ''");
    $form = (int)valor('SELECT COUNT(*) FROM formularios WHERE revisado = 0');
    return [
        'Día a día' => [
            'inicio' => ['home', 'Resumen', 0],
            'clientes' => ['users', 'Clientes', 0],
            'pagos' => ['money', 'Pagos', $pend],
            'leads' => ['bell', 'Interesados', $leads + $form],
            'checkins' => ['heart', 'Check-ins', $chk],
            'bio' => ['scale', 'Bioimpedancia', 0],
        ],
        'Mi servicio' => [
            'planes' => ['flame', 'Planes y precios', 0],
            'videos' => ['video', 'Videos y máquinas', 0],
            'rutinas' => ['clipboard', 'Rutinas', 0],
            'paquetes' => ['book', 'Paquetes de videos', 0],
            'tienda' => ['money', 'Tienda' . (tienda_activa() ? '' : ' (apagada)'), (int)valor("SELECT COUNT(*) FROM pagos WHERE concepto_tipo = 'pedido' AND estado = 'pendiente'")],
            'propuestas' => ['send', 'Propuestas', 0],
        ],
        'Mi web' => [
            'contenido' => ['book', 'Contenido', 0],
            'estadisticas' => ['chart', 'Visitas', 0],
            'temporadas' => ['star', 'Temporadas y beneficios', 0],
            'tarjeta' => ['link', 'Tarjeta y QR', 0],
            'reportes' => ['file', 'Reportes', 0],
            'ajustes' => ['settings', 'Ajustes', 0],
            'manual' => ['help', 'Manual', 0],
        ],
    ];
}

function admin_cabecera(string $p, string $titulo): void {
    cabeceras_seguridad();
    $u = usuario();
    ?><!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titulo) ?> · Panel <?= e(ajuste('marca')) ?></title>
<link rel="icon" href="<?= icono_app(48) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700;800&display=swap" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700;800&display=swap"></noscript>
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<style><?= tema_css() ?></style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" defer></script>
<script>window.BJ = {base: <?= json_encode(BASE) ?>, csrf: <?= json_encode(csrf()) ?>};</script>
</head><body class="admin">
<aside class="ad-lateral" id="lateral">
  <a class="ad-logo" href="<?= url('admin/') ?>"><img src="<?= imagen_marca('logo') ?>" alt=""><span>Panel<br><b><?= e(ajuste('marca')) ?></b></span></a>
  <nav>
    <?php foreach (admin_menu() as $grupo => $items): ?>
      <div class="ad-grupo"><?= e($grupo) ?></div>
      <?php foreach ($items as $k => [$ic, $t, $n]): ?>
        <a href="<?= url('admin/?p=' . $k) ?>" class="<?= $p === $k || ($k === 'clientes' && $p === 'cliente') ? 'on' : '' ?>"><?= icono($ic) ?><span><?= e($t) ?></span><?= $n ? '<em>' . $n . '</em>' : '' ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="ad-pie">
    <a href="<?= url('') ?>" target="_blank"><?= icono('globe') ?> Ver mi web</a>
    <form method="post" action="<?= url('admin/?p=salir') ?>"><?= csrf_campo() ?><button type="submit"><?= icono('logout') ?> Salir</button></form>
  </div>
</aside>
<div class="ad-principal">
  <header class="ad-top">
    <button class="ad-burger" type="button" onclick="document.getElementById('lateral').classList.toggle('abierto')" aria-label="Menú"><?= icono('menu') ?></button>
    <h1><?= e($titulo) ?></h1>
    <div class="ad-top-der">
      <a class="btn btn-sm btn-borde" href="<?= url('admin/?p=cliente&nuevo=1') ?>"><?= icono('plus') ?> Cliente</a>
      <span class="ad-yo"><?= icono('user') ?> <?= e(explode(' ', $u['nombre'])[0]) ?></span>
    </div>
  </header>
  <main class="ad-contenido">
  <?= flashes() ?>
<?php
}

function admin_pie(string $scripts = ''): void {
    ?>
  </main>
</div>
<script src="<?= asset('js/site.js') ?>" defer></script>
<script src="<?= asset('js/admin.js') ?>" defer></script>
<?= $scripts ?>
</body></html>
<?php
}

/* ---------- Ayudas de formulario ---------- */
function campo(string $nombre, string $etq, $valor = '', string $tipo = 'text', array $o = []): string {
    $id = 'c_' . preg_replace('/\W/', '_', $nombre);
    $attrs = '';
    foreach (['placeholder', 'min', 'max', 'step', 'maxlength', 'accept', 'inputmode', 'pattern'] as $a) if (isset($o[$a])) $attrs .= " $a=\"" . e($o[$a]) . '"';
    if (!empty($o['required'])) $attrs .= ' required';
    $ayuda = isset($o['ayuda']) ? '<span class="ayuda">' . $o['ayuda'] . '</span>' : '';
    $cls = 'campo' . (!empty($o['ancho']) ? ' ancho' : '');
    if ($tipo === 'textarea') $in = '<textarea id="' . $id . '" name="' . e($nombre) . '"' . $attrs . (isset($o['filas']) ? ' rows="' . (int)$o['filas'] . '"' : '') . '>' . e($valor) . '</textarea>';
    elseif ($tipo === 'select') {
        $in = '<select id="' . $id . '" name="' . e($nombre) . '"' . $attrs . '>';
        foreach ($o['opciones'] ?? [] as $k => $t) $in .= '<option value="' . e($k) . '"' . ((string)$k === (string)$valor ? ' selected' : '') . '>' . e($t) . '</option>';
        $in .= '</select>';
    } elseif ($tipo === 'checkbox') {
        return '<label class="check ' . (!empty($o['ancho']) ? 'ancho' : '') . '"><input type="hidden" name="' . e($nombre) . '" value="0"><input type="checkbox" name="' . e($nombre) . '" value="1"' . ($valor ? ' checked' : '') . '> <span>' . e($etq) . ($ayuda ? '<br>' . $ayuda : '') . '</span></label>';
    } else $in = '<input id="' . $id . '" type="' . $tipo . '" name="' . e($nombre) . '" value="' . e($valor) . '"' . $attrs . '>';
    return '<div class="' . $cls . '"><label for="' . $id . '">' . e($etq) . '</label>' . $in . $ayuda . '</div>';
}
function campo_imagen(string $nombre, string $etq, ?string $actual, bool $publica = true): string {
    $prev = $actual ? '<img src="' . e($publica ? img_publica($actual) : $actual) . '" alt="" class="ad-mini">' : '';
    return '<div class="campo"><label>' . e($etq) . '</label>' . $prev . '<input type="file" name="' . e($nombre) . '" accept="image/*">'
        . ($actual ? '<label class="check"><input type="checkbox" name="quitar_' . e($nombre) . '" value="1"> <span>Quitar imagen</span></label>' : '') . '</div>';
}
function post(string $k, $def = '') { return is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : ($_POST[$k] ?? $def); }
function post_int(string $k): int { return (int)($_POST[$k] ?? 0); }
function post_num(string $k): float { return (float)str_replace(['.', ','], ['', '.'], (string)preg_replace('/[^\d.,]/', '', (string)($_POST[$k] ?? '0'))); }
/** Procesa una imagen del formulario: nueva, quitar o conservar. */
function imagen_post(string $campo, ?string $actual, string $sub = 'contenido'): ?string {
    if (!empty($_POST['quitar_' . $campo])) return '';
    $n = subir_archivo($_FILES[$campo] ?? ['error' => UPLOAD_ERR_NO_FILE], TIPOS_IMAGEN, false, $sub, 15);
    return $n ?? $actual;
}
function estado_chip(string $e): string {
    $m = ['pendiente' => ['En revisión', 'chip-alerta'], 'aprobado' => ['Aprobado', 'chip-ok'], 'rechazado' => ['Rechazado', 'chip-rojo'], 'activa' => ['Activa', 'chip-ok'],
        'vencida' => ['Vencida', 'chip-rojo'], 'finalizada' => ['Finalizada', ''], 'pausada' => ['Pausada', 'chip-alerta'], 'nuevo' => ['Nuevo', 'chip-volt'], 'contactado' => ['Contactado', 'chip-alerta'],
        'cliente' => ['Ya es cliente', 'chip-ok'], 'descartado' => ['Descartado', ''], 'enviada' => ['Enviada', 'chip-alerta'], 'aceptada' => ['Aceptada', 'chip-ok']];
    [$t, $c] = $m[$e] ?? [ucfirst($e), ''];
    return '<span class="chip ' . $c . '">' . e($t) . '</span>';
}
function clave_temporal(): string {
    $s = 'abcdefghjkmnpqrstuvwxyz23456789';
    $c = '';
    for ($i = 0; $i < 8; $i++) $c .= $s[random_int(0, strlen($s) - 1)];
    return ucfirst($c);
}
function url_absoluta(string $p = ''): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($p);
}
function edad(?string $f): ?int { return $f ? (int)date_diff(date_create($f), date_create('today'))->y : null; }

/* ---------- Negocio ---------- */
/** Activa un plan para un cliente (cierra el anterior si estaba activo). */
function activar_plan(int $uid, array $plan, string $inicio, ?float $precio = null): int {
    q("UPDATE membresias SET estado = 'finalizada' WHERE usuario_id = ? AND estado = 'activa'", [$uid]);
    $fin = date('Y-m-d', strtotime($inicio . ' +' . max(1, (int)$plan['duracion_dias']) . ' days'));
    $id = insertar('membresias', ['usuario_id' => $uid, 'plan_id' => $plan['id'], 'nombre_plan' => $plan['nombre'], 'precio' => $precio ?? $plan['precio'], 'inicio' => $inicio, 'fin' => $fin,
        'sesiones_total' => (int)$plan['sesiones'], 'sesiones_usadas' => 0, 'estado' => 'activa', 'creado' => ahora()]);
    q('UPDATE usuarios SET cliente_desde = COALESCE(cliente_desde, ?), modalidad = COALESCE(modalidad, ?) WHERE id = ?', [$inicio, $plan['modalidad'], $uid]);
    q("UPDATE leads SET estado = 'cliente', actualizado = ? WHERE usuario_id = ? AND estado <> 'cliente'", [ahora(), $uid]);
    return $id;
}
function dar_acceso_video(int $uid, ?int $videoId, int $dias, string $origen): void {
    insertar('accesos_video', ['usuario_id' => $uid, 'video_id' => $videoId, 'desde' => hoy(), 'hasta' => $dias > 0 ? date('Y-m-d', strtotime("+$dias days")) : null, 'origen' => $origen, 'creado' => ahora()]);
}
/** Aprueba un pago y activa lo comprado. Devuelve el mensaje de WhatsApp para avisar al cliente. */
function aprobar_pago(array $p): string {
    $uid = (int)$p['usuario_id'];
    $nombre = explode(' ', (string)$p['nombre'])[0];
    $msg = 'Hola ' . $nombre . ', ¡tu pago de ' . $p['concepto'] . ' fue aprobado! ';
    if ($uid && $p['concepto_tipo'] === 'plan' && ($pl = fila('SELECT * FROM planes WHERE id = ?', [$p['concepto_id']]))) {
        activar_plan($uid, $pl, hoy(), (float)$p['monto']);
        $msg .= 'Tu plan ya está activo. Entra a tu portal para ver tu progreso: ' . url_absoluta('cliente/');
    } elseif ($uid && $p['concepto_tipo'] === 'biblioteca' && ($pl = fila('SELECT * FROM planes WHERE id = ?', [$p['concepto_id']]))) {
        dar_acceso_video($uid, null, (int)$pl['duracion_dias'], 'Pago #' . $p['id']);
        $msg .= 'Ya tienes acceso a todos los videos por ' . (int)$pl['duracion_dias'] . ' días: ' . url_absoluta('maquinas.php');
    } elseif ($uid && $p['concepto_tipo'] === 'video' && ($v = fila('SELECT * FROM videos WHERE id = ?', [$p['concepto_id']]))) {
        dar_acceso_video($uid, (int)$v['id'], (int)ajuste('dias_acceso_video', '90'), 'Pago #' . $p['id']);
        $msg .= 'Ya puedes ver el video: ' . url_absoluta('maquina.php?s=' . $v['slug']);
    } elseif ($p['concepto_tipo'] === 'paquete' && ($pq = fila('SELECT * FROM paquetes WHERE id = ?', [$p['concepto_id']]))) {
        if ($uid) {
            foreach (filas('SELECT video_id FROM paquete_videos WHERE paquete_id = ?', [$pq['id']]) as $pv) dar_acceso_video($uid, (int)$pv['video_id'], (int)$pq['dias_acceso'], 'Paquete #' . $p['id']);
            if ($pq['rutina_id'] && ($base = fila('SELECT * FROM rutinas WHERE id = ?', [$pq['rutina_id']]))) copiar_rutina($base, $uid);
        }
        $msg .= 'Ya tienes los videos del paquete ' . $pq['nombre'] . ' y la rutina en tu portal: ' . url_absoluta('cliente/?p=rutinas');
    } elseif ($p['concepto_tipo'] === 'pedido') {
        foreach (json_decode((string)($p['detalle'] ?? '[]'), true) ?: [] as $l) q('UPDATE productos SET stock = stock - ? WHERE id = ? AND stock IS NOT NULL', [(int)$l['cant'], (int)$l['id']]);
        q("UPDATE pagos SET estado_entrega = 'por entregar' WHERE id = ?", [$p['id']]);
        $msg .= 'Estoy preparando tu pedido (' . ($p['entrega'] ?: 'entrega por acordar') . '). Te aviso cuando esté listo.';
    } else $msg .= 'Gracias por tu confianza.';
    q("UPDATE pagos SET estado = 'aprobado', revisado = ? WHERE id = ?", [ahora(), $p['id']]);
    return $msg;
}
/** Copia una rutina (plantilla) para un cliente. */
function copiar_rutina(array $base, int $uid, string $nombre = ''): int {
    $nid = insertar('rutinas', ['nombre' => $nombre ?: $base['nombre'], 'descripcion' => $base['descripcion'], 'objetivo' => $base['objetivo'], 'nivel' => $base['nivel'], 'plantilla' => 0, 'usuario_id' => $uid, 'activa' => 1, 'creado' => ahora()]);
    foreach (filas('SELECT * FROM rutina_ejercicios WHERE rutina_id = ? ORDER BY orden, id', [$base['id']]) as $e) { unset($e['id']); $e['rutina_id'] = $nid; insertar('rutina_ejercicios', $e); }
    return $nid;
}
/** Borra el contenido de una carpeta (sin borrar la carpeta ni los archivos de protección). */
function vaciar_carpeta(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        if (in_array($f->getFilename(), ['.htaccess', 'index.html'], true)) continue;
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
}
/**
 * Reinicia la web. $todo = false: borra clientes y datos de prueba, conserva la configuración.
 * $todo = true: restablecer de fábrica (todo, incluido el administrador). Siempre guarda una copia antes.
 */
function reiniciar_web(bool $todo): string {
    $pdo = db();
    $dir = PRIV_DIR . '/respaldos';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $copia = 'antes-de-reiniciar-' . date('Ymd-His');
    if (DB_DRIVER === 'sqlite') { $copia .= '.sqlite'; $pdo->exec("VACUUM INTO '" . str_replace("'", "''", "$dir/$copia") . "'"); }
    else { $copia .= '.json'; $o = []; foreach (['ajustes', 'usuarios', 'planes', 'membresias', 'pagos', 'medidas', 'contenidos', 'videos', 'paquetes', 'productos', 'rutinas', 'rutina_ejercicios'] as $t) $o[$t] = filas("SELECT * FROM $t"); file_put_contents("$dir/$copia", json_encode($o, JSON_UNESCAPED_UNICODE)); }
    $datos = ['sesiones', 'pagos', 'membresias', 'medidas', 'fotos', 'entrenos', 'checkins', 'leads', 'visitas', 'eventos', 'geo_cache', 'intentos', 'formularios', 'propuestas', 'accesos_video', 'bioimpedancias'];
    if (!$todo) {
        foreach ($datos as $t) $pdo->exec("DELETE FROM $t");
        $pdo->exec("DELETE FROM rutina_ejercicios WHERE rutina_id IN (SELECT id FROM rutinas WHERE plantilla = 0)");
        $pdo->exec("DELETE FROM rutinas WHERE plantilla = 0");
        $pdo->exec("DELETE FROM contenidos WHERE usuario_id IS NOT NULL");
        $pdo->exec("DELETE FROM recordar WHERE usuario_id IN (SELECT id FROM usuarios WHERE rol = 'cliente')");
        $pdo->exec("DELETE FROM usuarios WHERE rol = 'cliente'");
        $pdo->exec("UPDATE videos SET vistas = 0");
        $pdo->exec("UPDATE contenidos SET vistas = 0");
        foreach (['fotos', 'comprobantes', 'formularios', 'bio'] as $sub) vaciar_carpeta(PRIV_DIR . '/archivos/' . $sub);
        return $copia;
    }
    $tablas = ['ajustes', 'usuarios', 'formularios', 'planes', 'membresias', 'sesiones', 'pagos', 'videos', 'accesos_video', 'medidas', 'fotos', 'rutinas', 'rutina_ejercicios',
        'entrenos', 'checkins', 'leads', 'contenidos', 'propuestas', 'visitas', 'eventos', 'geo_cache', 'intentos', 'recordar', 'productos', 'paquetes', 'paquete_videos', 'bioimpedancias'];
    foreach ($tablas as $t) $pdo->exec("DROP TABLE IF EXISTS $t");
    if (DB_DRIVER === 'sqlite') $pdo->exec('VACUUM');
    foreach (['fotos', 'comprobantes', 'formularios', 'videos', 'bio'] as $sub) vaciar_carpeta(PRIV_DIR . '/archivos/' . $sub);
    vaciar_carpeta(APP_ROOT . '/uploads');
    schema_asegurar($pdo); // vuelve a crear todo con el contenido inicial
    return $copia;
}
function csv_descarga(string $nombre, array $cab, array $filas): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '-' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF"); // para que Excel lea las tildes
    fputcsv($o, $cab, ';');
    foreach ($filas as $f) fputcsv($o, array_values($f), ';');
    fclose($o);
    exit;
}
