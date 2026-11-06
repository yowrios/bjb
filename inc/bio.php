<?php
/**
 * Bioimpedancia (reporte de Smart Fit): validar, guardar, comparar.
 * Los valores manuales nunca se tocan: cada bioimpedancia crea su propia medida marcada «Smart Fit».
 */

/** Métricas que se comparan: clave => [nombre, unidad, mejor (+1 subir, -1 bajar, 0 neutro), decimales] */
function bio_metricas(): array {
    return [
        'peso' => ['Peso', 'kg', 0, 1], 'grasa_pct' => ['% de grasa', '%', -1, 1], 'grasa_kg' => ['Masa de grasa', 'kg', -1, 1],
        'musculo_esq' => ['Músculo esquelético', 'kg', 1, 1], 'masa_libre' => ['Masa libre de grasa', 'kg', 1, 1], 'agua' => ['Agua corporal', 'L', 1, 1],
        'visceral' => ['Grasa visceral', 'nivel', -1, 0], 'tmb' => ['Metabolismo basal', 'kcal', 1, 0], 'imc' => ['IMC', '', 0, 1],
    ];
}
function bio_segmentos_nombres(): array { return ['bd' => 'Brazo derecho', 'bi' => 'Brazo izquierdo', 'tr' => 'Tronco', 'pd' => 'Pierna derecha', 'pi' => 'Pierna izquierda']; }

/** Revisa y limpia lo que llega del lector del PDF (nunca se confía en valores fuera de rango). */
function bio_validar(array $r): array {
    $f = function ($k, $min, $max) use ($r) { $v = $r[$k] ?? null; if ($v === null || $v === '' || !is_numeric($v)) return null; $v = (float)$v; return ($v >= $min && $v <= $max) ? round($v, 2) : null; };
    $fecha = (string)($r['fecha'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha) || $fecha > date('Y-m-d', strtotime('+1 day')) || $fecha < '2000-01-01') throw new RuntimeException('La fecha del reporte no es válida.');
    $d = ['fecha' => $fecha, 'nombre_reporte' => mb_substr(trim((string)($r['nombre'] ?? '')), 0, 160), 'edad' => ($e = $f('edad', 5, 110)) !== null ? (int)$e : null,
        'estatura_cm' => $f('estatura_cm', 90, 240), 'sexo' => in_array($r['sexo'] ?? '', ['M', 'F'], true) ? $r['sexo'] : null,
        'peso' => $f('peso', 20, 350), 'grasa_kg' => $f('grasa_kg', 0, 250), 'grasa_pct' => $f('grasa_pct', 1, 80), 'masa_libre' => $f('masa_libre', 5, 200), 'agua' => $f('agua', 5, 150),
        'imc' => $f('imc', 8, 90), 'musculo_esq' => $f('musculo_esq', 3, 120), 'tmb' => $f('tmb', 500, 6000), 'visceral' => $f('visceral', 0, 60), 'indice_apendicular' => $f('indice_apendicular', 1, 30),
        'peso_ideal' => $f('peso_ideal', 20, 250), 'control_peso' => $f('control_peso', -200, 200), 'control_grasa' => $f('control_grasa', -200, 200), 'control_magra' => $f('control_magra', -200, 200)];
    if ($d['peso'] === null) throw new RuntimeException('El reporte no tiene un peso válido.');
    if ($d['grasa_pct'] === null && $d['grasa_kg'] !== null) $d['grasa_pct'] = round($d['grasa_kg'] / $d['peso'] * 100, 1);
    $seg = ['magra' => [], 'grasa' => []];
    foreach (['magra', 'grasa'] as $t) foreach (array_keys(bio_segmentos_nombres()) as $k) { $v = $r['segmentos'][$t][$k] ?? null; if (is_numeric($v) && $v >= 0 && $v < 100) $seg[$t][$k] = round((float)$v, 2); }
    $d['segmentos'] = json_encode($seg);
    $imp = []; foreach ((array)($r['impedancia'] ?? []) as $k => $fila) if (preg_match('/^\d+ kHz$/', (string)$k) && is_array($fila)) $imp[$k] = array_map('intval', array_slice($fila, 0, 5));
    $d['impedancia'] = json_encode($imp);
    $rg = []; foreach ((array)($r['rangos'] ?? []) as $k => $v) if (in_array($k, ['visceral', 'grasa_pct', 'indice_apendicular'], true)) $rg[$k] = mb_substr((string)$v, 0, 30);
    $d['rangos'] = json_encode($rg, JSON_UNESCAPED_UNICODE);
    $hist = [];
    foreach ((array)($r['historial'] ?? []) as $h) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($h['fecha'] ?? '')) || $h['fecha'] >= $fecha) continue;
        $hp = $h['peso'] ?? null; if (!is_numeric($hp) || $hp < 20 || $hp > 350) continue;
        $hist[] = ['fecha' => $h['fecha'], 'peso' => (float)$hp, 'masa_libre' => is_numeric($h['masa_libre'] ?? null) ? (float)$h['masa_libre'] : null,
            'grasa_kg' => is_numeric($h['grasa_kg'] ?? null) ? (float)$h['grasa_kg'] : null, 'musculo_esq' => is_numeric($h['musculo_esq'] ?? null) ? (float)$h['musculo_esq'] : null];
    }
    $d['historial'] = $hist;
    return $d;
}
function bio_normalizar(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z ]/', ' ', $s)));
}
/** Clientes cuyo nombre coincide con el del reporte (exacto primero, luego por palabras). */
function bio_buscar_cliente(string $nombre): array {
    $n = bio_normalizar($nombre); if ($n === '') return [];
    $pal = explode(' ', $n); $exactos = []; $parecidos = [];
    foreach (filas("SELECT id, nombre FROM usuarios WHERE rol = 'cliente'") as $c) {
        $cn = bio_normalizar($c['nombre']);
        if ($cn === $n) { $exactos[] = $c; continue; }
        $cp = explode(' ', $cn); $comunes = count(array_intersect($pal, $cp));
        if ($comunes >= 2 && $comunes >= min(count($pal), count($cp)) - 0) $parecidos[] = $c;
    }
    return $exactos ?: $parecidos;
}
/** Medida «Smart Fit» enlazada a la bioimpedancia (crea o actualiza solo la suya). */
function bio_medida(int $uid, int $bioId, array $d, string $nota): void {
    $m = ['usuario_id' => $uid, 'fecha' => $d['fecha'], 'peso' => $d['peso'], 'grasa' => $d['grasa_pct'] ?? null,
        'musculo' => (!empty($d['musculo_esq']) && $d['peso']) ? round($d['musculo_esq'] / $d['peso'] * 100, 1) : null, 'nota' => $nota, 'por' => 'bio', 'bio_id' => $bioId];
    $ya = fila('SELECT id FROM medidas WHERE bio_id = ?', [$bioId]);
    if ($ya) actualizar('medidas', (int)$ya['id'], $m); else insertar('medidas', $m + ['creado' => ahora()]);
}
/**
 * Guarda una bioimpedancia para el usuario. Si ya existe ese día, la actualiza (no duplica).
 * También guarda los meses del historial del reporte que aún no estén (marcados como «historial»).
 * Devuelve ['id', 'estado' => nuevo|actualizado, 'historial' => n agregados].
 */
function bio_guardar(int $uid, array $d, ?string $archivo, string $por): array {
    $hist = $d['historial']; unset($d['historial']);
    $ya = fila('SELECT * FROM bioimpedancias WHERE usuario_id = ? AND fecha = ?', [$uid, $d['fecha']]);
    $fila = $d + ['usuario_id' => $uid, 'origen' => 'reporte', 'por' => $por, 'actualizado' => ahora()];
    if ($archivo) $fila['archivo'] = $archivo;
    if ($ya) {
        if ($archivo && $ya['archivo'] && $ya['archivo'] !== $archivo) @unlink(PRIV_DIR . '/archivos/' . $ya['archivo']);
        actualizar('bioimpedancias', (int)$ya['id'], $fila); $id = (int)$ya['id']; $estado = 'actualizado';
    } else { $id = insertar('bioimpedancias', $fila + ['creado' => ahora()]); $estado = 'nuevo'; }
    bio_medida($uid, $id, $d, 'Bioimpedancia Smart Fit');
    $n = 0;
    foreach ($hist as $h) {
        if (valor('SELECT COUNT(*) FROM bioimpedancias WHERE usuario_id = ? AND fecha = ?', [$uid, $h['fecha']])) continue;
        $hid = insertar('bioimpedancias', ['usuario_id' => $uid, 'fecha' => $h['fecha'], 'origen' => 'historial', 'nombre_reporte' => $d['nombre_reporte'], 'peso' => $h['peso'],
            'grasa_kg' => $h['grasa_kg'], 'grasa_pct' => $h['grasa_kg'] !== null ? round($h['grasa_kg'] / $h['peso'] * 100, 1) : null, 'masa_libre' => $h['masa_libre'],
            'musculo_esq' => $h['musculo_esq'], 'por' => $por, 'creado' => ahora(), 'actualizado' => ahora()]);
        bio_medida($uid, $hid, ['fecha' => $h['fecha'], 'peso' => $h['peso'], 'grasa_pct' => $h['grasa_kg'] !== null ? round($h['grasa_kg'] / $h['peso'] * 100, 1) : null, 'musculo_esq' => $h['musculo_esq']], 'Historial Smart Fit (valores redondeados)');
        $n++;
    }
    // Datos del perfil: solo se completan si estaban vacíos (nunca se reemplaza lo que escribió Bryan)
    $u = fila('SELECT * FROM usuarios WHERE id = ?', [$uid]); $act = [];
    if (empty($u['estatura_cm']) && $d['estatura_cm']) $act['estatura_cm'] = $d['estatura_cm'];
    if (empty($u['sexo']) && $d['sexo']) $act['sexo'] = $d['sexo'];
    if ($act) actualizar('usuarios', $uid, $act);
    return ['id' => $id, 'estado' => $estado, 'historial' => $n];
}

/** Comparativo listo para mostrar (portal del cliente, ficha y reporte). */
function bio_comparativo(int $uid, bool $panel = false): string {
    $bios = filas('SELECT * FROM bioimpedancias WHERE usuario_id = ? ORDER BY fecha', [$uid]);
    if (!$bios) return '<p class="sec">Aún no hay bioimpedancias. Carga el PDF que te entregan en Smart Fit y aquí verás tu progreso mes a mes.</p>';
    $ult = end($bios); $ant = count($bios) > 1 ? $bios[count($bios) - 2] : null; $pri = $bios[0];
    $fmt = function ($v, int $dec) { return $v === null || $v === '' ? '—' : number_format((float)$v, $dec, ',', '.'); };
    $delta = function ($a, $b, int $mejor, int $dec, string $u) {
        if ($a === null || $b === null || $a === '' || $b === '') return '<span class="bio-d">—</span>';
        $x = round((float)$b - (float)$a, $dec ?: 0); if (abs($x) < 0.05) return '<span class="bio-d igual">= sin cambio</span>';
        $bien = $mejor === 0 ? null : (($x > 0) === ($mejor > 0));
        return '<span class="bio-d ' . ($bien === null ? '' : ($bien ? 'bien' : 'mal')) . '">' . ($x > 0 ? '▲ +' : '▼ ') . number_format($x, $dec, ',', '.') . ' ' . $u . '</span>';
    };
    $h = '<div class="bio">';
    $h .= '<p class="bio-sub">Última: <b>' . fecha_larga($ult['fecha']) . '</b>' . ($ant ? ' · comparada con ' . fecha_corta($ant['fecha']) : '') . ' · ' . count($bios) . (count($bios) > 1 ? ' mediciones' : ' medición') . '</p>';
    $h .= '<div class="bio-kpis">';
    foreach (['peso', 'grasa_pct', 'musculo_esq', 'grasa_kg', 'visceral', 'tmb'] as $k) {
        [$n, $u, $mej, $dec] = bio_metricas()[$k];
        $h .= '<div class="bio-kpi"><span>' . e($n) . '</span><b>' . $fmt($ult[$k], $dec) . ' <small>' . e($u) . '</small></b>' . ($ant ? $delta($ant[$k], $ult[$k], $mej, $dec, $u) : '') . '</div>';
    }
    $h .= '</div>';
    $h .= '<div class="bio-tabla"><table class="' . ($panel ? 'ad-tabla' : 'tabla') . '"><thead><tr><th>Medida</th><th class="num">Primera<br><small>' . fecha_corta($pri['fecha']) . '</small></th>'
        . ($ant && $ant['id'] !== $pri['id'] ? '<th class="num">Anterior<br><small>' . fecha_corta($ant['fecha']) . '</small></th>' : '')
        . '<th class="num">Última<br><small>' . fecha_corta($ult['fecha']) . '</small></th><th class="num">Vs. anterior</th><th class="num">Desde el inicio</th></tr></thead><tbody>';
    foreach (bio_metricas() as $k => [$n, $u, $mej, $dec]) {
        if ($ult[$k] === null && $pri[$k] === null) continue;
        $h .= '<tr><td>' . e($n) . '</td><td class="num">' . $fmt($pri[$k], $dec) . '</td>' . ($ant && $ant['id'] !== $pri['id'] ? '<td class="num">' . $fmt($ant[$k], $dec) . '</td>' : '')
            . '<td class="num"><b>' . $fmt($ult[$k], $dec) . '</b> ' . e($u) . '</td><td class="num">' . ($ant ? $delta($ant[$k], $ult[$k], $mej, $dec, $u) : '—') . '</td><td class="num">' . ($pri['id'] !== $ult['id'] ? $delta($pri[$k], $ult[$k], $mej, $dec, $u) : '—') . '</td></tr>';
    }
    $h .= '</tbody></table></div>';
    $rg = json_decode((string)$ult['rangos'], true) ?: [];
    if ($rg) $h .= '<p class="bio-sub">Rangos de referencia del reporte: ' . (isset($rg['grasa_pct']) ? '% de grasa ' . e($rg['grasa_pct']) . ' · ' : '') . (isset($rg['visceral']) ? 'grasa visceral ' . e($rg['visceral']) . ' · ' : '') . (isset($rg['indice_apendicular']) ? 'índice apendicular ' . e($rg['indice_apendicular']) : '') . '</p>';
    // Segmentos
    $sU = json_decode((string)$ult['segmentos'], true) ?: [];
    $completas = array_values(array_filter($bios, fn($b) => $b['origen'] === 'reporte' && $b['id'] !== $ult['id']));
    $sA = $completas ? (json_decode((string)end($completas)['segmentos'], true) ?: []) : [];
    if (!empty($sU['magra'])) {
        $h .= '<div class="bio-seg">';
        foreach (['magra' => ['Masa magra por segmento', 1], 'grasa' => ['Grasa por segmento', -1]] as $t => [$tit, $mej]) {
            $h .= '<div><h4>' . e($tit) . '</h4>';
            foreach (bio_segmentos_nombres() as $k => $n) {
                if (!isset($sU[$t][$k])) continue;
                $h .= '<div class="bio-fila"><span>' . e($n) . '</span><b>' . $fmt($sU[$t][$k], 2) . ' kg</b>' . (isset($sA[$t][$k]) ? $delta($sA[$t][$k], $sU[$t][$k], $mej, 2, 'kg') : '') . '</div>';
            }
            $h .= '</div>';
        }
        $h .= '</div>';
    }
    // Datos para la gráfica
    $graf = ['fechas' => array_map(fn($b) => fecha_corta($b['fecha']), $bios), 'peso' => array_map(fn($b) => $b['peso'] !== null ? (float)$b['peso'] : null, $bios),
        'grasa' => array_map(fn($b) => $b['grasa_kg'] !== null ? (float)$b['grasa_kg'] : null, $bios), 'musculo' => array_map(fn($b) => $b['musculo_esq'] !== null ? (float)$b['musculo_esq'] : null, $bios),
        'libre' => array_map(fn($b) => $b['masa_libre'] !== null ? (float)$b['masa_libre'] : null, $bios)];
    if (count($bios) > 1) $h .= '<div class="bio-graf"><canvas data-bio=\'' . e(json_encode($graf)) . '\'></canvas></div>';
    if (array_filter($bios, fn($b) => $b['origen'] === 'historial')) $h .= '<p class="bio-sub">Los meses marcados como «historial» vienen del mismo reporte de Smart Fit y traen valores redondeados.</p>';
    return $h . '</div>';
}
