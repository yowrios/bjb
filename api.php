<?php
/** Servicios internos de la web: estadísticas, contactos, rutinas del cronómetro. Responde JSON. */
require __DIR__ . '/inc/app.php';
cabeceras_seguridad();
iniciar_sesion();
$a = $_GET['a'] ?? '';

function cuerpo_json(): array {
    $j = json_decode((string)file_get_contents('php://input'), true);
    return is_array($j) ? $j : [];
}

switch ($a) {
    /* --- Clics y duración de la visita (navigator.sendBeacon) --- */
    case 'ev': {
        $d = cuerpo_json();
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)($d['csrf'] ?? ''))) json_out(['ok' => false], 403);
        if (es_admin()) json_out(['ok' => true]);
        $tipo = preg_replace('/[^a-z_]/', '', (string)($d['tipo'] ?? ''));
        if ($tipo === '_duracion') {
            $seg = max(0, min(7200, (int)($d['seg'] ?? 0)));
            q('UPDATE visitas SET duracion = ? WHERE id = ? AND visitante = ?', [$seg, (int)($d['visita'] ?? 0), (string)($_COOKIE['bj_v'] ?? '')]);
        } elseif (in_array($tipo, ['whatsapp', 'plan', 'red', 'cronometro', 'descarga', 'video', 'calculadora', 'propuesta', 'pago'], true)) {
            evento($tipo, (string)($d['ref'] ?? ''), (string)($d['valor'] ?? ''));
        }
        json_out(['ok' => true]);
    }

    /* --- Ciudad y país aproximados de la visita (en segundo plano) --- */
    case 'geo': {
        $ip = $_SESSION['bj_ip_pendiente'] ?? '';
        unset($_SESSION['bj_ip_pendiente']);
        session_write_close();
        if ($ip && !empty($_COOKIE['bj_v'])) {
            [$pais, $ciudad] = geolocalizar($ip);
            if ($pais || $ciudad) q("UPDATE visitas SET pais = CASE WHEN pais = '' OR pais IS NULL THEN ? ELSE pais END, ciudad = ? WHERE visitante = ? AND (ciudad = '' OR ciudad IS NULL)", [$pais, $ciudad, $_COOKIE['bj_v']]);
        }
        json_out(['ok' => true]);
    }

    /* --- Contacto rápido (calculadoras, catálogos, botones) --- */
    case 'lead': {
        if (!es_post()) json_out(['ok' => false], 405);
        csrf_verificar();
        $nombre = trim(mb_substr((string)($_POST['nombre'] ?? ''), 0, 120));
        $tel = trim(mb_substr((string)($_POST['telefono'] ?? ''), 0, 30));
        if ($nombre === '' || strlen(solo_digitos($tel)) < 7) json_out(['ok' => false, 'error' => 'Escribe tu nombre y un número de WhatsApp válido.']);
        if (empty($_POST['acepto'])) json_out(['ok' => false, 'error' => 'Debes autorizar el tratamiento de datos.']);
        if (intentos_superados('lead')) json_out(['ok' => false, 'error' => 'Demasiados envíos. Intenta más tarde.']);
        registrar_intento('lead');
        $origen = preg_replace('/[^a-z_]/', '', (string)($_POST['origen'] ?? 'web')) ?: 'web';
        $interes = mb_substr((string)($_POST['interes'] ?? ''), 0, 190);
        $catalogo = (int)($_POST['catalogo'] ?? 0);
        $cat = $catalogo ? fila("SELECT * FROM contenidos WHERE id = ? AND tipo = 'catalogo' AND visible = 1", [$catalogo]) : null;
        if ($cat) $interes = 'Descargó: ' . $cat['titulo'];
        $vis = substr((string)($_COOKIE['bj_v'] ?? ''), 0, 24);
        $ya = fila('SELECT id FROM leads WHERE telefono = ? AND creado > ? ORDER BY id DESC LIMIT 1', [$tel, date('Y-m-d H:i:s', time() - 86400 * 3)]);
        $datos = ['nombre' => $nombre, 'telefono' => $tel, 'origen' => $origen, 'interes' => $interes,
            'mensaje' => mb_substr((string)($_POST['mensaje'] ?? ''), 0, 2000), 'visitante' => $vis, 'actualizado' => ahora()];
        if ($ya) actualizar('leads', (int)$ya['id'], $datos);
        else insertar('leads', $datos + ['email' => mb_substr((string)($_POST['email'] ?? ''), 0, 160), 'estado' => 'nuevo', 'creado' => ahora(), 'datos' => '']);
        $resp = ['ok' => true];
        if ($cat) {
            $_SESSION['descargas'][$cat['id']] = time();
            evento('descarga', 'catalogo', $cat['titulo']);
            $resp['descarga'] = url('descargar.php?id=' . $cat['id']);
            $resp['mensaje'] = '¡Gracias, ' . $nombre . '! Tu descarga empieza ahora.';
        } else {
            evento('lead', $origen, $interes);
            $resp['mensaje'] = '¡Gracias, ' . $nombre . '! Bryan te escribirá muy pronto.';
            $resp['wa'] = wa_link(ajuste('whatsapp'), 'Hola Bryan, soy ' . $nombre . '. ' . ($interes ? $interes . '. ' : '') . 'Quiero información de tus planes.');
        }
        json_out($resp);
    }

    /* --- Rutina para el cronómetro guiado (cliente con sesión) --- */
    case 'rutina': {
        $u = usuario();
        if (!$u) json_out(['ok' => false, 'error' => 'Inicia sesión para cargar tu rutina.'], 401);
        $id = (int)($_GET['id'] ?? 0);
        $r = fila('SELECT * FROM rutinas WHERE id = ? AND activa = 1', [$id]);
        if (!$r || ($u['rol'] !== 'admin' && (int)$r['usuario_id'] !== (int)$u['id'])) json_out(['ok' => false, 'error' => 'Rutina no disponible.'], 404);
        $ej = filas('SELECT e.*, v.titulo AS vtitulo, v.slug AS vslug, v.coef_hombre, v.coef_mujer, v.incremento FROM rutina_ejercicios e LEFT JOIN videos v ON v.id = e.video_id WHERE e.rutina_id = ? ORDER BY e.orden, e.id', [$id]);
        $peso = (float)valor('SELECT peso FROM medidas WHERE usuario_id = ? AND peso IS NOT NULL ORDER BY fecha DESC, id DESC LIMIT 1', [$u['id']]);
        $edad = $u['fecha_nacimiento'] ? (int)date_diff(date_create($u['fecha_nacimiento']), date_create('today'))->y : 30;
        $dia = (string)($_GET['dia'] ?? '');
        $out = [];
        foreach ($ej as $e) {
            if ($dia !== '' && $e['dia'] !== $dia) continue;
            $carga = $e['carga'];
            if ($carga === '' && $peso > 0 && $e['video_id'] && (float)$e['coef_hombre'] > 0) {
                $c = carga_sugerida($e, $peso, (string)($u['sexo'] ?: 'M'), (string)($u['nivel'] ?: 'principiante'), (string)($r['objetivo'] ?: 'hipertrofia'), $edad);
                if ($c) $carga = $c['kg'] . ' kg (sugerida)';
            }
            $out[] = ['nombre' => $e['nombre'] ?: $e['vtitulo'], 'dia' => $e['dia'], 'series' => (int)$e['series'], 'reps' => $e['reps'],
                'descanso' => (int)$e['descanso_seg'], 'trabajo' => (int)$e['trabajo_seg'], 'carga' => $carga, 'nota' => $e['nota'], 'slug' => $e['vslug']];
        }
        json_out(['ok' => true, 'rutina' => ['id' => (int)$r['id'], 'nombre' => $r['nombre'], 'dia' => $dia], 'ejercicios' => $out]);
    }

    /* --- Guardar un entrenamiento terminado desde el cronómetro --- */
    case 'entreno': {
        $u = usuario();
        if (!$u) json_out(['ok' => false, 'error' => 'Sin sesión'], 401);
        $d = cuerpo_json();
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)($d['csrf'] ?? ''))) json_out(['ok' => false], 403);
        $rid = (int)($d['rutina_id'] ?? 0);
        if ($rid && !valor('SELECT COUNT(*) FROM rutinas WHERE id = ? AND (usuario_id = ? OR ? = 1)', [$rid, $u['id'], $u['rol'] === 'admin' ? 1 : 0])) $rid = 0;
        insertar('entrenos', ['usuario_id' => $u['id'], 'rutina_id' => $rid ?: null, 'dia' => mb_substr((string)($d['dia'] ?? ''), 0, 60), 'fecha' => ahora(),
            'duracion_seg' => max(0, min(36000, (int)($d['duracion'] ?? 0))), 'series_hechas' => max(0, min(500, (int)($d['series'] ?? 0))),
            'sensacion' => isset($d['sensacion']) ? max(1, min(5, (int)$d['sensacion'])) : null,
            'detalle' => mb_substr(json_encode($d['detalle'] ?? [], JSON_UNESCAPED_UNICODE), 0, 20000), 'nota' => mb_substr((string)($d['nota'] ?? ''), 0, 250)]);
        json_out(['ok' => true]);
    }
}
json_out(['ok' => false, 'error' => 'Acción no válida'], 404);
