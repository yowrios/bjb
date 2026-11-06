<?php
/**
 * Estructura de la base de datos y contenido inicial.
 * Se crea sola la primera vez. Funciona igual en SQLite y en MySQL.
 * Para agregar cambios futuros: subir DB_VERSION y agregar el paso en schema_migrar().
 */
const DB_VERSION = 6;

function schema_asegurar(PDO $pdo): void {
    try {
        $v = (int)$pdo->query("SELECT valor FROM ajustes WHERE clave = 'db_version'")->fetchColumn();
    } catch (Throwable $t) {
        $v = 0;
    }
    if ($v >= DB_VERSION) return;
    if ($v === 0) {
        schema_crear($pdo);
        schema_sembrar($pdo);
    }
    schema_migrar($pdo, $v);
    $pdo->prepare("UPDATE ajustes SET valor = ? WHERE clave = 'db_version'")->execute([(string)DB_VERSION]);
}

function schema_migrar(PDO $pdo, int $desde): void {
    if ($desde < 2) schema_v2($pdo);
    if ($desde < 3) schema_v3($pdo);
    if ($desde < 4) schema_v4($pdo);
    if ($desde < 5) schema_v5($pdo);
    if ($desde < 6) schema_v6($pdo);
}

/** Versión 6: resultados de bioimpedancia (Smart Fit) y enlace de cada medida con su bioimpedancia. */
function schema_v6(PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $PK = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $TX = $mysql ? 'MEDIUMTEXT' : 'TEXT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS bioimpedancias (id $PK, usuario_id INT NOT NULL, fecha VARCHAR(10) NOT NULL, origen VARCHAR(20) DEFAULT 'reporte',
        nombre_reporte VARCHAR(160), edad INT, estatura_cm DECIMAL(5,1), sexo VARCHAR(1),
        peso DECIMAL(6,2), grasa_kg DECIMAL(6,2), grasa_pct DECIMAL(5,2), masa_libre DECIMAL(6,2), agua DECIMAL(6,2), imc DECIMAL(5,2), musculo_esq DECIMAL(6,2),
        tmb DECIMAL(7,2), visceral DECIMAL(5,2), indice_apendicular DECIMAL(5,2), peso_ideal DECIMAL(6,2), control_peso DECIMAL(6,2), control_grasa DECIMAL(6,2), control_magra DECIMAL(6,2),
        segmentos $TX, impedancia $TX, rangos $TX, archivo VARCHAR(190), por VARCHAR(10), creado VARCHAR(19), actualizado VARCHAR(19))" . ($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : ''));
    try { $pdo->exec('CREATE INDEX ix_bio_usuario ON bioimpedancias (usuario_id, fecha)'); } catch (Throwable $e) {}
    try { $pdo->exec('ALTER TABLE medidas ADD COLUMN bio_id INT'); } catch (Throwable $e) {}
}

/** Versión 5: nueva marca BJB · BUILT BY DISCIPLINE (solo si seguían los valores anteriores) y dirección pública para el QR. */
function schema_v5(PDO $pdo): void {
    $cambiar = function (string $k, string $nuevo, array $viejos) use ($pdo) {
        $st = $pdo->prepare('SELECT valor FROM ajustes WHERE clave = ?'); $st->execute([$k]); $v = $st->fetchColumn();
        if ($v === false) $pdo->prepare('INSERT INTO ajustes (clave, valor) VALUES (?, ?)')->execute([$k, $nuevo]);
        elseif (in_array((string)$v, $viejos, true)) $pdo->prepare('UPDATE ajustes SET valor = ? WHERE clave = ?')->execute([$nuevo, $k]);
    };
    $cambiar('marca', 'BJB', ['BRYAN JFIT', 'Bryan JFit', '']);
    $cambiar('eslogan', 'BUILT BY DISCIPLINE', ['Transformo tu cuerpo, cambiando tu mente', '']);
    $cambiar('seo_titulo', 'BJB · Bryan Becerra, entrenador personal | Built by discipline', ['Bryan JFit · Entrenador personal | Programa Tu Evolución', '']);
    $cambiar('url_publica', '', []);
}

/** Versión 4: temporadas, beneficios y color de fondo. Solo agrega ajustes que no existan (no toca lo que Bryan cambió). */
function schema_v4(PDO $pdo): void {
    foreach (ajustes_v4() as $k => $v) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM ajustes WHERE clave = ?'); $st->execute([$k]);
        if (!$st->fetchColumn()) $pdo->prepare('INSERT INTO ajustes (clave, valor) VALUES (?, ?)')->execute([$k, $v]);
    }
}

/** Versión 3: franja editable y nombre correcto del gimnasio (Mi Plaza Soacha). Solo cambia lo que siga con el valor anterior. */
function schema_v3(PDO $pdo): void {
    $set = function (string $k, string $nuevo, array $viejos) use ($pdo) {
        $st = $pdo->prepare('SELECT valor FROM ajustes WHERE clave = ?'); $st->execute([$k]); $v = $st->fetchColumn();
        if ($v === false) $pdo->prepare('INSERT INTO ajustes (clave, valor) VALUES (?, ?)')->execute([$k, $nuevo]);
        elseif (in_array((string)$v, $viejos, true)) $pdo->prepare('UPDATE ajustes SET valor = ? WHERE clave = ?')->execute([$nuevo, $k]);
    };
    $set('franja', "Mentalidad\nDisciplina\nHábitos\nNutrición\nConstancia\nFuerza\nTu evolución", []);
    $set('gym_nombre', 'Smart Fit · Mi Plaza Soacha', ['Smart Fit · C.C. Hogares Soacha']);
    $set('gym_direccion', 'Centro Comercial Mi Plaza Soacha, Hogares Soacha, Soacha, Cundinamarca', ['Centro Comercial Hogares Soacha, Soacha, Cundinamarca']);
    $set('gym_texto', 'Entrenamos en las instalaciones de Smart Fit del Centro Comercial Mi Plaza Soacha (Hogares Soacha): máquinas modernas, buen espacio y horarios amplios.',
        ['Entrenamos en las instalaciones de Smart Fit del Centro Comercial Hogares Soacha: máquinas modernas, buen espacio y horarios amplios.']);
    $set('ciudad', 'Soacha · Smart Fit Mi Plaza Soacha', ['Soacha · Smart Fit C.C. Hogares Soacha']);
}

/** Versión 2: recordar sesión, tienda, paquetes de videos, plan en pareja, más máquinas, ajustes de marca. */
function schema_v2(PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $PK = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $TX = $mysql ? 'MEDIUMTEXT' : 'TEXT';
    $FIN = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $now = date('Y-m-d H:i:s');
    foreach ([
        "recordar (id $PK, usuario_id INT NOT NULL, selector VARCHAR(24) NOT NULL, validador VARCHAR(64) NOT NULL, expira VARCHAR(19), creado VARCHAR(19))",
        "productos (id $PK, nombre VARCHAR(160) NOT NULL, categoria VARCHAR(60), marca VARCHAR(80), descripcion $TX, presentacion VARCHAR(120), precio DECIMAL(12,0) DEFAULT 0,
            precio_antes DECIMAL(12,0) DEFAULT 0, imagen VARCHAR(190), stock INT, destacado INT DEFAULT 0, visible INT DEFAULT 1, orden INT DEFAULT 0, creado VARCHAR(19))",
        "paquetes (id $PK, slug VARCHAR(120), nombre VARCHAR(160) NOT NULL, descripcion $TX, precio DECIMAL(12,0) DEFAULT 0, precio_antes DECIMAL(12,0) DEFAULT 0, imagen VARCHAR(190),
            rutina_id INT, dias_acceso INT DEFAULT 90, visible INT DEFAULT 1, orden INT DEFAULT 0, creado VARCHAR(19))",
        "paquete_videos (id $PK, paquete_id INT NOT NULL, video_id INT NOT NULL, orden INT DEFAULT 0)",
    ] as $sql) $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $sql . $FIN);
    foreach (['recordar' => 'selector', 'paquete_videos' => 'paquete_id'] as $t => $c) { try { $pdo->exec("CREATE INDEX ix_{$t}_{$c} ON $t ($c)"); } catch (Throwable $e) {} }
    foreach (["ALTER TABLE pagos ADD COLUMN detalle $TX", "ALTER TABLE pagos ADD COLUMN entrega VARCHAR(250)", "ALTER TABLE pagos ADD COLUMN estado_entrega VARCHAR(20)"] as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
    $ins = function (string $tabla, array $d) use ($pdo): int {
        $c = array_keys($d);
        $pdo->prepare("INSERT INTO $tabla (" . implode(',', $c) . ') VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')')->execute(array_values($d));
        return (int)$pdo->lastInsertId();
    };
    $aj = function (string $k, string $v) use ($pdo) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM ajustes WHERE clave = ?'); $st->execute([$k]);
        if (!$st->fetchColumn()) $pdo->prepare('INSERT INTO ajustes (clave, valor) VALUES (?, ?)')->execute([$k, $v]);
    };
    foreach ([
        'logo' => '', 'foto_hero' => '', 'foto_sobre' => '', 'color_principal' => '#ff2a2a', 'color_acento' => '#d4ff3a',
        'tienda_activa' => '0', 'tienda_titulo' => 'Suplementos y accesorios', 'tienda_texto' => 'Proteínas, creatina y accesorios que recomiendo. Pide por la web y recoge en el gimnasio o recíbelo en casa.',
        'tienda_entrega' => "Recoger en el gimnasio\nEnvío a domicilio en Soacha\nEnvío a otra ciudad (se cotiza)",
        'gym_nombre' => 'Smart Fit · C.C. Hogares Soacha', 'gym_direccion' => 'Centro Comercial Hogares Soacha, Soacha, Cundinamarca',
        'gym_texto' => 'Entrenamos en las instalaciones de Smart Fit del Centro Comercial Hogares Soacha: máquinas modernas, buen espacio y horarios amplios.',
        'gym_logo' => '', 'gym_mapa' => '1',
    ] as $k => $v) $aj($k, $v);
    $pdo->prepare("UPDATE ajustes SET valor = ? WHERE clave = 'ciudad' AND (valor IS NULL OR valor = '')")->execute(['Soacha · Smart Fit C.C. Hogares Soacha']);

    /* Plan en pareja (el precio lo define Bryan: 0 = «Consúltalo») */
    $st = $pdo->query("SELECT COUNT(*) FROM planes WHERE slug = 'en-pareja'");
    if (!$st->fetchColumn()) {
        $ins('planes', ['slug' => 'en-pareja', 'tipo' => 'entrenamiento', 'nombre' => 'DÚO · EN PAREJA', 'etiqueta' => 'Entrenen juntos', 'subtitulo' => 'Ven con tu pareja, amigo o familiar',
            'descripcion' => 'Entrenar solo a veces aburre o da pena. Ven con tu pareja, un amigo o alguien especial: comparten el tiempo y la rutina, fortalecen la relación, aprenden juntos y se corrigen entre los dos. Una clase agradable y motivadora.',
            'incluye' => "Valoración inicial para los dos\nRutina diseñada para entrenar en pareja\nAcompañamiento en cada sesión con corrección de técnica para ambos\nEjercicios en pareja y retos entre ustedes\nGuía de alimentación para cada uno\nPortal de evolución individual para cada persona",
            'sesiones' => 12, 'frecuencia' => '3 veces por semana · 2 personas', 'duracion_dias' => 30, 'precio' => 0, 'precio_antes' => 0, 'modalidad' => 'presencial',
            'incluye_videos' => 0, 'destacado' => 0, 'visible' => 1, 'orden' => 4, 'link_pago' => '', 'imagen' => '']);
        $pdo->exec("UPDATE planes SET orden = orden + 1 WHERE slug IN ('online', 'biblioteca')");
    }

    /* Más máquinas */
    $nuevas = [
        ['sentadilla-hack', 'Sentadilla hack (hack squat)', 'Piernas', 'cuadriceps,gluteos', 'intermedio',
            'Máquina guiada que pone el foco en el cuádriceps con la espalda apoyada. Excelente para piernas fuertes y estables.',
            "Apoya toda la espalda y los hombros en las almohadillas\nPies al ancho de la cadera en el centro de la plataforma\nQuita los seguros y baja controlado hasta que los muslos queden paralelos o un poco más\nEmpuja con todo el pie sin bloquear las rodillas\nPon los seguros al terminar",
            "Despegar la cadera del respaldo abajo\nRodillas que se van hacia adentro\nBajar demasiado rápido",
            "Pies más adelante = más glúteo; pies más atrás = más cuádriceps\nCuenta solo los discos: el carro pesa según la marca", 1.0, 0.75, 10, 'Discos que pones en total; no incluye el carro'],
        ['aductores', 'Aducción de cadera (máquina)', 'Piernas', 'cuadriceps,gluteos', 'principiante',
            'Fortalece la parte interna del muslo, que estabiliza la rodilla y la cadera.',
            "Siéntate con la espalda apoyada y las almohadillas por dentro de las rodillas\nJunta las piernas apretando la parte interna del muslo\nSostén 1 segundo\nAbre lento hasta un estiramiento cómodo",
            "Abrir más de lo cómodo (tirón en la ingle)\nUsar impulso\nDejar caer las placas",
            "Empieza con poco recorrido y amplíalo semana a semana", 0.45, 0.38, 5, 'Peso de la columna de placas'],
        ['hip-thrust', 'Hip thrust (empuje de cadera)', 'Glúteos', 'gluteos,isquios', 'intermedio',
            'El ejercicio más efectivo para glúteos fuertes. Se hace en máquina o con barra apoyando la espalda en un banco.',
            "Apoya la parte alta de la espalda en el banco o el respaldo\nPies al ancho de la cadera, rodillas a 90° arriba\nEmpuja con los talones subiendo la cadera hasta alinear rodillas, cadera y hombros\nAprieta los glúteos 1 segundo y baja controlado",
            "Arquear la espalda baja en vez de subir con la cadera\nPies muy lejos (trabaja más el isquio)\nMirar al techo (mantén la barbilla hacia el pecho)",
            "Usa una almohadilla en la barra para no lastimarte la cadera", 0.9, 0.8, 5, 'Peso de la barra con discos o de la máquina'],
        ['peso-muerto-rumano', 'Peso muerto rumano con mancuernas', 'Piernas', 'isquios,gluteos,lumbar', 'intermedio',
            'Fortalece la parte de atrás del cuerpo y enseña a cuidar la espalda al levantar objetos.',
            "De pie, mancuernas al frente de los muslos, rodillas un poco flexionadas\nLleva la cadera hacia atrás bajando las mancuernas cerca de las piernas\nBaja hasta sentir estiramiento en los isquiotibiales con la espalda recta\nSube apretando glúteos",
            "Redondear la espalda\nDoblar mucho las rodillas (se vuelve sentadilla)\nAlejar las mancuernas del cuerpo",
            "Imagina que cierras una puerta con la cadera", 0.25, 0.18, 1, 'Peso de CADA mancuerna'],
    ];
    $max = (int)$pdo->query('SELECT COALESCE(MAX(orden),0) FROM videos')->fetchColumn();
    foreach ($nuevas as $i => $x) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM videos WHERE slug = ?'); $st->execute([$x[0]]);
        if ($st->fetchColumn()) continue;
        $ins('videos', ['slug' => $x[0], 'titulo' => $x[1], 'categoria' => $x[2], 'musculos' => $x[3], 'nivel' => $x[4], 'descripcion' => $x[5], 'pasos' => $x[6], 'errores' => $x[7], 'tips' => $x[8],
            'imagen' => '', 'video_archivo' => '', 'video_url' => '', 'precio' => 15000, 'gratis' => 0, 'visible' => 1, 'orden' => $max + $i + 1,
            'coef_hombre' => $x[9], 'coef_mujer' => $x[10], 'incremento' => $x[11], 'nota_carga' => $x[12], 'vistas' => 0, 'creado' => $now]);
    }

    /* Paquete de ejemplo: Tren inferior (videos + rutina) */
    if (!$pdo->query("SELECT COUNT(*) FROM paquetes")->fetchColumn()) {
        $ids = [];
        foreach ($pdo->query('SELECT id, slug FROM videos') as $r) $ids[$r['slug']] = (int)$r['id'];
        $lista = ['sentadilla-hack', 'prensa-piernas', 'extension-cuadriceps', 'aductores', 'pantorrilla'];
        $rid = $ins('rutinas', ['nombre' => 'Tren inferior · Piernas', 'descripcion' => 'Rutina completa de pierna con máquinas: hack, prensa, extensión, aductores y pantorrilla.', 'objetivo' => 'hipertrofia', 'nivel' => 'principiante', 'plantilla' => 1, 'usuario_id' => null, 'activa' => 1, 'creado' => $now]);
        $series = ['sentadilla-hack' => [4, '10', 120], 'prensa-piernas' => [4, '12', 90], 'extension-cuadriceps' => [3, '12', 60], 'aductores' => [3, '15', 60], 'pantorrilla' => [4, '15', 45]];
        foreach ($lista as $o => $s) if (isset($ids[$s])) $ins('rutina_ejercicios', ['rutina_id' => $rid, 'dia' => 'Pierna', 'orden' => $o + 1, 'video_id' => $ids[$s], 'nombre' => '', 'series' => $series[$s][0], 'reps' => $series[$s][1], 'descanso_seg' => $series[$s][2], 'trabajo_seg' => 0, 'carga' => '', 'nota' => '']);
        $pid = $ins('paquetes', ['slug' => 'tren-inferior', 'nombre' => 'Tren inferior · Piernas', 'descripcion' => 'Los 5 videos de una rutina completa de pierna (hack, prensa, extensión, aductores y pantorrilla) con la rutina lista en tu portal y el cronómetro guiado.',
            'precio' => 30000, 'precio_antes' => 75000, 'imagen' => '', 'rutina_id' => $rid, 'dias_acceso' => 90, 'visible' => 1, 'orden' => 1, 'creado' => $now]);
        foreach ($lista as $o => $s) if (isset($ids[$s])) $ins('paquete_videos', ['paquete_id' => $pid, 'video_id' => $ids[$s], 'orden' => $o + 1]);
    }
}

function schema_crear(PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $PK = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $TX = $mysql ? 'MEDIUMTEXT' : 'TEXT';
    $FIN = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $t = [
        "ajustes (clave VARCHAR(80) PRIMARY KEY, valor $TX)",
        "usuarios (id $PK, rol VARCHAR(10) NOT NULL DEFAULT 'cliente', nombre VARCHAR(120) NOT NULL, email VARCHAR(160) NOT NULL UNIQUE,
            telefono VARCHAR(30), clave_hash VARCHAR(255) NOT NULL, activo INT NOT NULL DEFAULT 1, debe_cambiar INT NOT NULL DEFAULT 0,
            fecha_nacimiento VARCHAR(10), sexo VARCHAR(1), estatura_cm DECIMAL(5,1), objetivo $TX, nivel VARCHAR(20), modalidad VARCHAR(20),
            cliente_desde VARCHAR(10), origen VARCHAR(60), notas_admin $TX, foto VARCHAR(190), etiquetas VARCHAR(190),
            consentimiento VARCHAR(19), creado VARCHAR(19), ultimo_acceso VARCHAR(19))",
        "formularios (id $PK, usuario_id INT, lead_id INT, nombre VARCHAR(120), datos $TX, fotos $TX, creado VARCHAR(19), revisado INT DEFAULT 0)",
        "planes (id $PK, slug VARCHAR(80), tipo VARCHAR(20) NOT NULL DEFAULT 'entrenamiento', nombre VARCHAR(120) NOT NULL, etiqueta VARCHAR(60),
            subtitulo VARCHAR(190), descripcion $TX, incluye $TX, sesiones INT DEFAULT 0, frecuencia VARCHAR(80), duracion_dias INT DEFAULT 30,
            precio DECIMAL(12,0) DEFAULT 0, precio_antes DECIMAL(12,0) DEFAULT 0, modalidad VARCHAR(30) DEFAULT 'presencial',
            incluye_videos INT DEFAULT 0, destacado INT DEFAULT 0, visible INT DEFAULT 1, orden INT DEFAULT 0, link_pago VARCHAR(250), imagen VARCHAR(190))",
        "membresias (id $PK, usuario_id INT NOT NULL, plan_id INT, nombre_plan VARCHAR(120), precio DECIMAL(12,0) DEFAULT 0, inicio VARCHAR(10),
            fin VARCHAR(10), sesiones_total INT DEFAULT 0, sesiones_usadas INT DEFAULT 0, estado VARCHAR(15) DEFAULT 'activa', nota $TX, creado VARCHAR(19))",
        "sesiones (id $PK, usuario_id INT NOT NULL, membresia_id INT, fecha VARCHAR(19), nota VARCHAR(250))",
        "pagos (id $PK, usuario_id INT, nombre VARCHAR(120), telefono VARCHAR(30), email VARCHAR(160), concepto_tipo VARCHAR(20),
            concepto_id INT, concepto VARCHAR(190), monto DECIMAL(12,0) DEFAULT 0, metodo VARCHAR(40), referencia VARCHAR(120),
            comprobante VARCHAR(190), estado VARCHAR(15) DEFAULT 'pendiente', nota $TX, creado VARCHAR(19), revisado VARCHAR(19))",
        "videos (id $PK, slug VARCHAR(120), titulo VARCHAR(160) NOT NULL, categoria VARCHAR(60), musculos VARCHAR(190), nivel VARCHAR(20),
            descripcion $TX, pasos $TX, errores $TX, tips $TX, imagen VARCHAR(190), video_archivo VARCHAR(190), video_url VARCHAR(250),
            precio DECIMAL(12,0) DEFAULT 0, gratis INT DEFAULT 0, visible INT DEFAULT 1, orden INT DEFAULT 0,
            coef_hombre DECIMAL(5,2) DEFAULT 0, coef_mujer DECIMAL(5,2) DEFAULT 0, incremento DECIMAL(5,2) DEFAULT 5, nota_carga VARCHAR(190),
            vistas INT DEFAULT 0, creado VARCHAR(19))",
        "accesos_video (id $PK, usuario_id INT NOT NULL, video_id INT, desde VARCHAR(10), hasta VARCHAR(10), origen VARCHAR(60), creado VARCHAR(19))",
        "medidas (id $PK, usuario_id INT NOT NULL, fecha VARCHAR(10), peso DECIMAL(6,2), grasa DECIMAL(5,2), musculo DECIMAL(5,2),
            cintura DECIMAL(5,1), cadera DECIMAL(5,1), pecho DECIMAL(5,1), brazo DECIMAL(5,1), pierna DECIMAL(5,1), abdomen DECIMAL(5,1),
            nota VARCHAR(250), por VARCHAR(10) DEFAULT 'admin', creado VARCHAR(19))",
        "fotos (id $PK, usuario_id INT NOT NULL, fecha VARCHAR(10), tipo VARCHAR(15), archivo VARCHAR(190), nota VARCHAR(250), por VARCHAR(10), creado VARCHAR(19))",
        "rutinas (id $PK, nombre VARCHAR(160) NOT NULL, descripcion $TX, objetivo VARCHAR(40), nivel VARCHAR(20), plantilla INT DEFAULT 1,
            usuario_id INT, activa INT DEFAULT 1, creado VARCHAR(19))",
        "rutina_ejercicios (id $PK, rutina_id INT NOT NULL, dia VARCHAR(60), orden INT DEFAULT 0, video_id INT, nombre VARCHAR(160),
            series INT DEFAULT 3, reps VARCHAR(30), descanso_seg INT DEFAULT 60, trabajo_seg INT DEFAULT 0, carga VARCHAR(60), nota VARCHAR(250))",
        "entrenos (id $PK, usuario_id INT NOT NULL, rutina_id INT, dia VARCHAR(60), fecha VARCHAR(19), duracion_seg INT DEFAULT 0,
            series_hechas INT DEFAULT 0, sensacion INT, detalle $TX, nota VARCHAR(250))",
        "checkins (id $PK, usuario_id INT NOT NULL, fecha VARCHAR(10), peso DECIMAL(6,2), energia INT, sueno INT, adherencia INT, animo INT,
            comentario $TX, respuesta $TX, respondido VARCHAR(19), creado VARCHAR(19))",
        "leads (id $PK, nombre VARCHAR(120), telefono VARCHAR(30), email VARCHAR(160), origen VARCHAR(60), interes VARCHAR(190), mensaje $TX,
            datos $TX, visitante VARCHAR(24), estado VARCHAR(15) DEFAULT 'nuevo', nota $TX, usuario_id INT, creado VARCHAR(19), actualizado VARCHAR(19))",
        "contenidos (id $PK, tipo VARCHAR(20) NOT NULL, titulo VARCHAR(190), slug VARCHAR(190), resumen $TX, cuerpo $TX, imagen VARCHAR(190),
            imagen2 VARCHAR(190), archivo VARCHAR(190), url VARCHAR(250), categoria VARCHAR(60), usuario_id INT, pide_datos INT DEFAULT 0,
            visible INT DEFAULT 1, orden INT DEFAULT 0, vistas INT DEFAULT 0, creado VARCHAR(19))",
        "propuestas (id $PK, token VARCHAR(48) NOT NULL, nombre VARCHAR(120), telefono VARCHAR(30), objetivo $TX, mensaje $TX, items $TX,
            total DECIMAL(12,0) DEFAULT 0, vence VARCHAR(10), estado VARCHAR(15) DEFAULT 'enviada', vistas INT DEFAULT 0, ultima_vista VARCHAR(19),
            lead_id INT, creado VARCHAR(19))",
        "visitas (id $PK, fecha VARCHAR(19), dia VARCHAR(10), hora INT, ruta VARCHAR(190), ref VARCHAR(250), utm_source VARCHAR(60),
            utm_medium VARCHAR(60), utm_campaign VARCHAR(80), dispositivo VARCHAR(20), navegador VARCHAR(20), so VARCHAR(20), pais VARCHAR(2),
            ciudad VARCHAR(120), ip_hash VARCHAR(20), visitante VARCHAR(24), usuario_id INT, duracion INT DEFAULT 0)",
        "eventos (id $PK, fecha VARCHAR(19), visitante VARCHAR(24), usuario_id INT, tipo VARCHAR(30), ref VARCHAR(190), valor VARCHAR(250))",
        "geo_cache (ip_hash VARCHAR(20) PRIMARY KEY, pais VARCHAR(2), ciudad VARCHAR(120), fecha VARCHAR(19))",
        "intentos (id $PK, ip VARCHAR(20), tipo VARCHAR(20), fecha VARCHAR(19))",
    ];
    foreach ($t as $sql) $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $sql . $FIN);
    $idx = [
        'visitas' => ['dia', 'visitante', 'usuario_id'], 'eventos' => ['fecha', 'tipo'], 'membresias' => ['usuario_id'],
        'pagos' => ['estado', 'usuario_id'], 'medidas' => ['usuario_id'], 'fotos' => ['usuario_id'], 'sesiones' => ['usuario_id'],
        'accesos_video' => ['usuario_id'], 'contenidos' => ['tipo'], 'rutina_ejercicios' => ['rutina_id'], 'entrenos' => ['usuario_id'],
        'checkins' => ['usuario_id'], 'leads' => ['estado'], 'intentos' => ['ip'],
    ];
    foreach ($idx as $tab => $cols) foreach ($cols as $c) {
        try { $pdo->exec("CREATE INDEX ix_{$tab}_{$c} ON $tab ($c)"); } catch (Throwable $e) {}
    }
}


/** Valores iniciales de los ajustes (también se usan para «restablecer lo sugerido» en el panel). */
function ajustes_defecto_base(): array {
    return [
        'marca' => 'BJB',
        'nombre_entrenador' => 'Bryan Becerra',
        'eslogan' => 'BUILT BY DISCIPLINE',
        'whatsapp' => '573182086805',
        'whatsapp_texto' => 'Hola Bryan, vi tu página web y quiero información sobre tus planes de entrenamiento.',
        'email' => 'bj.becerrabeltran@gmail.com',
        'instagram' => 'brynanopk',
        'tiktok' => '',
        'facebook' => 'https://www.facebook.com/bryan.becerrabeltran',
        'ciudad' => '',
        'zona_atencion' => 'Entrenamiento presencial y asesoría online en toda Colombia',
        'horario' => 'Lunes a sábado · 5:00 a. m. a 9:00 p. m.',
        'hero_pre' => 'Entrenador personal · Ciencias del deporte',
        'hero_titulo' => 'TU EVOLUCIÓN EMPIEZA HOY',
        'hero_texto' => 'Un sistema integral donde te guío paso a paso en la transformación de tu físico, tus hábitos y tu mentalidad. No quiero ayudarte a lograr un objetivo superficial: quiero un cambio permanente que se refleje en tu exterior.',
        'sobre_titulo' => 'BRYAN BECERRA',
        'sobre_texto' => "Profesional en ciencias del deporte y la educación física, con amplia experiencia en entrenamiento deportivo y acondicionamiento físico.\n\nDeportista y entrenador durante cerca de 4 años, capacitado para liderar, ejecutar y guiar procesos en los que se evidencian cambios, tanto físicos como en hábitos y estilos de vida.\n\nApasionado por el bienestar físico y mental, comprometido con el desarrollo integral de cada persona, adaptando cada plan a las necesidades y objetivos específicos de cada cliente.",
        'cifras' => json_encode([
            ['4+', 'años entrenando personas'],
            ['100%', 'planes personalizados'],
            ['3', 'programas para cada meta'],
            ['24/7', 'tu progreso en línea'],
        ], JSON_UNESCAPED_UNICODE),
        'metodo' => json_encode([
            ['Valoración', 'Medimos tu composición corporal, tu condición física y tus hábitos. Sin datos no hay plan.'],
            ['Plan a tu medida', 'Diseño tu entrenamiento y tu guía de alimentación según tu objetivo, tu horario y tu presupuesto.'],
            ['Acompañamiento', 'Entrenamos juntos (presencial o virtual): técnica, prevención de lesiones y motivación.'],
            ['Evolución', 'Cada mes medimos, comparamos fotos y ajustamos. Ves tus avances en tu portal.'],
        ], JSON_UNESCAPED_UNICODE),
        'cta_final' => '¡LO VAMOS A LOGRAR!',
        'cta_final_texto' => 'A partir de este momento me comprometo contigo y voy a dar lo mejor de mí para lograr tus objetivos. Pero esto solo funciona si TÚ también te comprometes contigo.',
        'pago_nequi' => '3182086805',
        'pago_daviplata' => '3182086805',
        'pago_banco' => 'Davivienda',
        'pago_cuenta_tipo' => 'Cuenta de ahorros',
        'pago_cuenta' => '4884 1660 8096',
        'pago_titular' => 'Bryan Jair Becerra Beltrán',
        'pago_cedula' => '1031160512',
        'pago_link' => '',
        'pago_nota' => 'Al realizar tu pago sube aquí el comprobante. Bryan lo revisa y activa tu acceso.',
        'tema' => 'fuego',
        'seo_titulo' => 'BJB · Bryan Becerra, entrenador personal | Built by discipline',
        'seo_desc' => 'Entrenamiento personalizado presencial y online con Bryan Becerra, profesional en ciencias del deporte. Planes, valoración, nutrición y seguimiento de tu evolución.',
        'geo_activo' => '1',
        'pausa_aviso_seg' => '45',
        'precio_video_defecto' => '15000',
        'dias_acceso_video' => '90',
        'aviso_portada' => '',
        'mostrar_tiktok' => '1',
        'mostrar_instagram' => '1',
    ];
}
/** Todos los valores sugeridos, incluidos los que se agregaron en versiones posteriores. */
function ajustes_defecto(): array {
    return ajustes_defecto_base() + ajustes_v2() + ajustes_v4() + ['franja' => "Mentalidad\nDisciplina\nHábitos\nNutrición\nConstancia\nFuerza\nTu evolución"];
}
function ajustes_v2(): array {
    return ['logo' => '', 'foto_hero' => '', 'foto_sobre' => '', 'textura' => '', 'color_principal' => '#ff2a2a', 'color_acento' => '#d4ff3a',
        'tienda_activa' => '0', 'tienda_titulo' => 'Suplementos y accesorios', 'tienda_texto' => 'Proteínas, creatina y accesorios que recomiendo. Pide por la web y recoge en el gimnasio o recíbelo en casa.',
        'tienda_entrega' => "Recoger en el gimnasio\nEnvío a domicilio en Soacha\nEnvío a otra ciudad (se cotiza)",
        'gym_nombre' => 'Smart Fit · Mi Plaza Soacha', 'gym_direccion' => 'Centro Comercial Mi Plaza Soacha, Hogares Soacha, Soacha, Cundinamarca',
        'gym_texto' => 'Entrenamos en las instalaciones de Smart Fit del Centro Comercial Mi Plaza Soacha (Hogares Soacha): máquinas modernas, buen espacio y horarios amplios.',
        'gym_logo' => '', 'gym_mapa' => '1'];
}
function ajustes_v4(): array {
    return ['color_fondo' => '#0a0a0c', 'temporadas_auto' => '1', 'temporadas' => '{}', 'hero_silueta' => '0', 'fondo_hero' => '',
        'regalo_cumple_activo' => '1', 'regalo_cumple_texto' => 'Una clase de 15 minutos gratis conmigo el día de tu cumpleaños. ¡Celebra entrenando! 🎂', 'regalo_cumple_dias' => '7',
        'referidos_activo' => '0', 'referidos_texto' => 'Trae a un amigo que contrate un plan y los dos reciben una sesión gratis.',
        'valoracion_gratis_activo' => '0', 'valoracion_gratis_texto' => 'Tu primera valoración física es gratis, sin compromiso.',
        'beneficio_extra_activo' => '0', 'beneficio_extra_titulo' => '', 'beneficio_extra_texto' => ''];
}

function schema_sembrar(PDO $pdo): void {
    $ins = function (string $tabla, array $d) use ($pdo): int {
        $c = array_keys($d);
        $pdo->prepare("INSERT INTO $tabla (" . implode(',', $c) . ') VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')')->execute(array_values($d));
        return (int)$pdo->lastInsertId();
    };
    $now = date('Y-m-d H:i:s');

    /* ----- Ajustes: textos, contacto, pagos, tema ----- */
    $aj = ['db_version' => '0', 'sal' => bin2hex(random_bytes(16))] + ajustes_defecto_base();
    foreach ($aj as $k => $v) $ins('ajustes', ['clave' => $k, 'valor' => $v]);

    /* ----- Planes (los del catálogo TU EVOLUCIÓN) ----- */
    $planes = [
        ['heavy-initiation', 'HEAVY INITIATION', 'Iniciación', 'Para empezar con todo',
            'Programa diseñado para conseguir el físico que siempre has deseado. El objetivo principal es mejorar tu estado físico, reducir tejido adiposo y lograr un acondicionamiento físico aceptable.',
            "Valoración inicial de tu condición física\nPesaje inicial para controlar tu composición corporal\nPrograma y planificación del entrenamiento diseñado en base a tus objetivos\nAsesoría personalizada en nutrición\nAcompañamiento 2 veces por semana en la rutina de entrenamiento\nAcceso a tu portal de evolución",
            8, '2 veces por semana', 380000, 0, 0],
        ['hard-gold', 'HARD GOLD', 'El más elegido', 'Cambio real en salud, físico y mente',
            'Para todas las personas interesadas en mejorar su estado de salud, físico y mental, y cumplir sus objetivos: aumento de masa muscular, reducción de tejido adiposo, trabajo de capacidades físicas, rehabilitación de lesiones. Creado para todo aquel que quiera un cambio en su vida.',
            "Valoración de la composición corporal y del estado físico\nDiseño de programa de entrenamiento personalizado según tus necesidades\nAcompañamiento en cada sesión: técnica, prevención de lesiones y educación deportiva\nAsesoría en educación nutricional según tus gustos y tu presupuesto\nAcompañamiento 3 veces por semana\nAcceso a tu portal de evolución y a la biblioteca de videos",
            12, '3 veces por semana', 480000, 1, 1],
        ['x-trem-vip', 'X-TREM VIP', 'Máximo potencial', 'Supera tus límites',
            'Programa diseñado para todo tipo de población, con el objetivo de alcanzar tu máximo potencial y superar tus límites, con un acompañamiento frecuente que motiva y convierte el ejercicio en un hábito permanente.',
            "Valoración de la composición corporal y del estado físico\nMediciones antropométricas avanzadas\nPlan de entrenamiento personalizado más detallado\nAcompañamiento físico o virtual en las rutinas\nAsesoría en nutrición con control mensual de macronutrientes\nAcompañamiento 4 veces por semana y control mensual de tu estado físico\nAcceso total a la biblioteca de videos",
            16, '4 veces por semana', 580000, 0, 1],
    ];
    foreach ($planes as $i => $p) {
        $ins('planes', ['slug' => $p[0], 'tipo' => 'entrenamiento', 'nombre' => $p[1], 'etiqueta' => $p[2], 'subtitulo' => $p[3],
            'descripcion' => $p[4], 'incluye' => $p[5], 'sesiones' => $p[6], 'frecuencia' => $p[7], 'duracion_dias' => 30,
            'precio' => $p[8], 'precio_antes' => 0, 'modalidad' => 'presencial', 'incluye_videos' => $p[10], 'destacado' => $p[9],
            'visible' => 1, 'orden' => $i + 1, 'link_pago' => '', 'imagen' => '']);
    }
    // Planes nuevos sugeridos (precio por definir con Bryan: ocultos hasta que él los active).
    $ins('planes', ['slug' => 'online', 'tipo' => 'virtual', 'nombre' => 'TU EVOLUCIÓN ONLINE', 'etiqueta' => 'Desde cualquier lugar',
        'subtitulo' => 'Asesoría virtual', 'descripcion' => 'Tu plan personalizado sin importar dónde vivas: rutinas en tu portal con videos, cronómetro guiado, revisión semanal de tu progreso y contacto directo por WhatsApp.',
        'incluye' => "Formulario inicial y análisis de tus fotos\nPlan de entrenamiento en tu portal con videos de cada ejercicio\nGuía de alimentación según tus gustos\nCheck-in semanal con respuesta de Bryan\nAjustes del plan cada 4 semanas\nAcceso a la biblioteca de videos",
        'sesiones' => 0, 'frecuencia' => 'Seguimiento semanal', 'duracion_dias' => 30, 'precio' => 0, 'precio_antes' => 0,
        'modalidad' => 'virtual', 'incluye_videos' => 1, 'destacado' => 0, 'visible' => 0, 'orden' => 4, 'link_pago' => '', 'imagen' => '']);
    $ins('planes', ['slug' => 'biblioteca', 'tipo' => 'videos', 'nombre' => 'BIBLIOTECA DE MÁQUINAS', 'etiqueta' => 'Aprende la técnica',
        'subtitulo' => 'Todos los videos por 30 días', 'descripcion' => 'Acceso a todos los videos de máquinas y ejercicios, con la carga sugerida para tu peso y tu nivel.',
        'incluye' => "Todos los videos de máquinas y ejercicios\nCarga sugerida según tu peso\nPasos, errores comunes y consejos de cada máquina\nNuevos videos cada mes",
        'sesiones' => 0, 'frecuencia' => '30 días de acceso', 'duracion_dias' => 30, 'precio' => 0, 'precio_antes' => 0,
        'modalidad' => 'virtual', 'incluye_videos' => 1, 'destacado' => 0, 'visible' => 0, 'orden' => 5, 'link_pago' => '', 'imagen' => '']);

    /* ----- Máquinas y ejercicios (biblioteca de videos) ----- */
    // [slug, titulo, categoria, musculos, nivel, descripcion, pasos, errores, tips, coefH, coefM, incremento, nota, gratis]
    $v = [
        ['sentadilla-libre', 'Sentadilla con peso corporal', 'Piernas', 'cuadriceps,gluteos,isquios,abdomen', 'principiante',
            'La base de todo. Antes de cargar peso en cualquier máquina de pierna, domina este patrón.',
            "Pies al ancho de los hombros, puntas un poco hacia afuera\nInhala, saca la cadera hacia atrás como si te fueras a sentar\nBaja con el pecho arriba hasta que los muslos queden al menos paralelos al piso\nEmpuja el piso con todo el pie y sube exhalando",
            "Rodillas que se van hacia adentro\nLevantar los talones\nRedondear la espalda baja al final del recorrido",
            "Mira un punto fijo al frente\nSi no llegas abajo sin levantar talones, trabaja movilidad de tobillo", 0, 0, 0, 'Sin carga: trabaja tu peso corporal', 1],
        ['prensa-piernas', 'Prensa de piernas 45°', 'Piernas', 'cuadriceps,gluteos,isquios', 'principiante',
            'La máquina reina para piernas fuertes con la espalda apoyada. Permite mover cargas altas con menos riesgo que la sentadilla libre.',
            "Siéntate con toda la espalda y la cadera pegadas al espaldar\nPies en el centro de la plataforma, al ancho de la cadera\nQuita los seguros y baja controlando hasta que las rodillas formen unos 90°\nEmpuja con todo el pie sin bloquear las rodillas arriba\nAl terminar, vuelve a poner los seguros antes de soltar",
            "Bajar tanto que la cadera se despega del asiento (la espalda baja se redondea)\nBloquear las rodillas al estirar\nEmpujar solo con la punta de los pies\nRebotar abajo",
            "Pies altos en la plataforma = más glúteo e isquios; pies bajos = más cuádriceps\nNo cuentes el peso del carro: la carga sugerida es la de los discos", 1.6, 1.2, 10, 'Discos que pones en total (los dos lados); no incluye el peso del carro', 0],
        ['extension-cuadriceps', 'Extensión de cuádriceps', 'Piernas', 'cuadriceps', 'principiante',
            'Aísla el frente del muslo. Ideal para fortalecer la rodilla y terminar la rutina de pierna.',
            "Ajusta el espaldar para que la rodilla quede alineada con el eje de la máquina\nEl rodillo va sobre el tobillo, no sobre el pie\nEstira las piernas en 1 segundo y aprieta arriba\nBaja en 2 o 3 segundos sin dejar caer el peso",
            "Rodilla adelantada o atrasada respecto al eje\nUsar impulso y despegar la cadera del asiento\nDejar caer el peso al bajar",
            "Agárrate de las manijas laterales para no levantarte\nSi te molesta la rodilla, reduce el recorrido y la carga", 0.4, 0.3, 5, 'Peso de la columna de placas', 0],
        ['curl-femoral', 'Curl femoral (isquiotibiales)', 'Piernas', 'isquios,pantorrillas', 'principiante',
            'Trabaja la parte de atrás del muslo, clave para prevenir lesiones y equilibrar la rodilla.',
            "Ajusta la máquina para que la rodilla quede en el eje\nEl rodillo va justo encima del talón\nFlexiona las rodillas llevando los talones hacia los glúteos\nBaja lento, sin estirar de golpe",
            "Levantar la cadera del banco\nRecorridos cortos con mucho peso\nBajar sin control",
            "Pies en punta flexionada (hacia ti) para mayor activación\nExhala al subir", 0.35, 0.25, 5, 'Peso de la columna de placas', 0],
        ['abductores', 'Abducción de cadera (máquina)', 'Glúteos', 'gluteos', 'principiante',
            'Fortalece el glúteo medio: estabiliza la rodilla y la cadera y da forma al glúteo.',
            "Siéntate con la espalda apoyada y las almohadillas por fuera de las rodillas\nAbre las piernas lo más que puedas sin despegar la espalda\nSostén 1 segundo afuera\nRegresa lento sin dejar que las placas choquen",
            "Usar impulso\nCerrar de golpe\nInclinarse hacia adelante sin intención",
            "Inclinarte un poco hacia adelante enfatiza el glúteo mayor\nIdeal como activación antes de sentadilla o prensa", 0.45, 0.4, 5, 'Peso de la columna de placas', 0],
        ['pantorrilla', 'Elevación de pantorrillas', 'Piernas', 'pantorrillas', 'principiante',
            'Pantorrillas fuertes = mejor estabilidad del tobillo, más impulso al correr y saltar.',
            "Apoya la parte delantera del pie en el borde, talones al aire\nBaja el talón lo más que puedas (estira)\nSube en la punta lo más alto posible y aprieta 1 segundo\nBaja lento",
            "Rebotar abajo\nDoblar las rodillas para ayudarse\nRecorridos a medias",
            "Las pantorrillas responden a muchas repeticiones con pausa arriba", 0.6, 0.45, 5, 'Peso de placas o discos', 0],
        ['smith-sentadilla', 'Sentadilla en máquina Smith', 'Piernas', 'cuadriceps,gluteos,isquios,abdomen', 'intermedio',
            'Sentadilla con barra guiada: más estable que la libre, ideal para aprender a cargar.',
            "Barra apoyada sobre los trapecios, no sobre el cuello\nPies un poco adelante de la barra, al ancho de los hombros\nGira la barra para liberar los ganchos\nBaja hasta paralelo con el pecho arriba y sube empujando el piso\nAl terminar gira la barra y engánchala",
            "Pies justo debajo de la barra (fuerza la rodilla)\nRedondear la espalda\nOlvidar poner los ganchos o los topes de seguridad",
            "Ajusta los topes de seguridad un poco por debajo de tu punto más bajo\nLa barra Smith pesa entre 7 y 20 kg según la marca: pregunta en tu gimnasio", 0.6, 0.45, 2.5, 'Discos que pones en total; no incluye el peso de la barra', 0],
        ['jalon-pecho', 'Jalón al pecho', 'Espalda', 'dorsales,biceps,hombros', 'principiante',
            'El ejercicio base para una espalda ancha. Paso previo para lograr dominadas.',
            "Ajusta el rodillo para que los muslos queden fijos\nAgarra la barra un poco más ancho que los hombros\nSaca el pecho, lleva los hombros hacia abajo y atrás\nBaja la barra hasta la parte alta del pecho\nSube controlando hasta estirar los brazos",
            "Llevar la barra detrás de la nuca\nBalancearse hacia atrás para bajar más peso\nJalar con los brazos en vez de con la espalda",
            "Piensa en llevar los codos hacia tus bolsillos\nExhala al bajar la barra", 0.6, 0.4, 5, 'Peso de la columna de placas', 0],
        ['remo-sentado', 'Remo sentado en polea', 'Espalda', 'dorsales,trapecio,biceps,lumbar', 'principiante',
            'Espalda gruesa y buena postura: corrige hombros caídos de pasar el día sentado.',
            "Siéntate con las rodillas un poco flexionadas y la espalda recta\nAgarra el maneral con los brazos estirados\nLleva el maneral al ombligo juntando las escápulas\nRegresa lento estirando los brazos sin encorvarte",
            "Balancear el tronco adelante y atrás\nEncoger los hombros hacia las orejas\nRedondear la espalda baja",
            "Pausa de 1 segundo con las escápulas juntas\nEl pecho va alto todo el tiempo", 0.55, 0.38, 5, 'Peso de la columna de placas', 0],
        ['press-pecho', 'Press de pecho en máquina', 'Pecho', 'pecho,triceps,hombros', 'principiante',
            'Pecho y brazos fuertes con un recorrido guiado. Excelente para empezar antes del press con barra.',
            "Ajusta el asiento para que las manijas queden a la altura del pecho\nEspalda pegada al espaldar, escápulas juntas\nEmpuja hasta casi estirar los brazos\nRegresa lento hasta sentir estiramiento en el pecho",
            "Despegar la espalda o arquear exageradamente\nCodos totalmente abiertos a 90° (estresan el hombro)\nBloquear los codos",
            "Codos a unos 45-60° del cuerpo\nExhala al empujar", 0.55, 0.3, 5, 'Peso de la columna de placas', 0],
        ['pec-deck', 'Aperturas en máquina (pec deck)', 'Pecho', 'pecho,hombros', 'principiante',
            'Aísla el pecho con un recorrido amplio y seguro.',
            "Ajusta el asiento para que los brazos queden a la altura del pecho\nCodos un poco flexionados y fijos\nJunta los brazos al frente apretando el pecho\nAbre lento hasta sentir estiramiento",
            "Abrir demasiado (estresa el hombro)\nConvertirlo en un press doblando los codos\nUsar impulso",
            "Imagina que abrazas un árbol grande", 0.35, 0.2, 5, 'Peso de la columna de placas', 0],
        ['press-hombro', 'Press de hombro en máquina', 'Hombros', 'hombros,triceps', 'intermedio',
            'Hombros fuertes y redondos con la espalda apoyada.',
            "Ajusta el asiento: las manijas a la altura de los hombros\nEspalda pegada al espaldar, abdomen firme\nEmpuja hacia arriba sin bloquear los codos\nBaja controlado hasta la altura de las orejas",
            "Arquear la espalda baja\nBajar demasiado rápido\nEncoger los hombros",
            "Si sientes pinchazo en el hombro, baja la carga y el recorrido", 0.35, 0.2, 5, 'Peso de la columna de placas', 0],
        ['triceps-polea', 'Extensión de tríceps en polea', 'Brazos', 'triceps', 'principiante',
            'El tríceps es dos tercios del brazo: este es su ejercicio base.',
            "De pie frente a la polea alta, agarra la barra o la cuerda\nCodos pegados al cuerpo y fijos\nEstira los brazos hacia abajo y aprieta\nSube lento solo hasta que los antebrazos queden paralelos al piso",
            "Mover los codos hacia adelante\nInclinar el cuerpo para empujar con el peso\nSubir las manos hasta la cara",
            "Con cuerda: abre las manos al final para mayor contracción", 0.25, 0.15, 2.5, 'Peso de la columna de placas', 0],
        ['curl-biceps', 'Curl de bíceps con mancuernas', 'Brazos', 'biceps,antebrazos', 'principiante',
            'Bíceps fuertes y definidos con técnica estricta.',
            "De pie, mancuernas a los lados, palmas al frente\nCodos pegados al cuerpo\nSube las mancuernas sin mover los codos\nBaja en 2 o 3 segundos hasta estirar casi por completo",
            "Balancear el cuerpo\nMover los codos hacia adelante\nBajar dejando caer el peso",
            "Mejor menos peso y técnica perfecta", 0.12, 0.07, 1, 'Peso de CADA mancuerna', 0],
    ];
    $precio = 15000;
    foreach ($v as $i => $x) {
        $ins('videos', ['slug' => $x[0], 'titulo' => $x[1], 'categoria' => $x[2], 'musculos' => $x[3], 'nivel' => $x[4],
            'descripcion' => $x[5], 'pasos' => $x[6], 'errores' => $x[7], 'tips' => $x[8], 'imagen' => '', 'video_archivo' => '', 'video_url' => '',
            'precio' => $x[13] ? 0 : $precio, 'gratis' => $x[13], 'visible' => 1, 'orden' => $i + 1,
            'coef_hombre' => $x[9], 'coef_mujer' => $x[10], 'incremento' => $x[11], 'nota_carga' => $x[12], 'vistas' => 0, 'creado' => $now]);
    }

    /* ----- Rutinas modelo ----- */
    $ids = [];
    foreach ($pdo->query('SELECT id, slug FROM videos') as $r) $ids[$r['slug']] = (int)$r['id'];
    $rutinas = [
        ['Full body principiante · 3 días', 'Para tus primeras 4 a 6 semanas: aprende la técnica y crea el hábito.', 'hipertrofia', 'principiante', [
            ['Día A', 'sentadilla-libre', 3, '12', 60], ['Día A', 'prensa-piernas', 3, '12', 90], ['Día A', 'jalon-pecho', 3, '12', 75], ['Día A', 'press-pecho', 3, '12', 75], ['Día A', 'pantorrilla', 3, '15', 45],
            ['Día B', 'curl-femoral', 3, '12', 60], ['Día B', 'remo-sentado', 3, '12', 75], ['Día B', 'press-hombro', 3, '12', 75], ['Día B', 'abductores', 3, '15', 45], ['Día B', 'triceps-polea', 3, '12', 45],
            ['Día C', 'smith-sentadilla', 3, '10', 90], ['Día C', 'extension-cuadriceps', 3, '12', 60], ['Día C', 'pec-deck', 3, '12', 60], ['Día C', 'curl-biceps', 3, '12', 45], ['Día C', 'pantorrilla', 3, '15', 45],
        ]],
        ['Pierna y glúteo · fuerza', 'Día de pierna completo con énfasis en glúteo.', 'fuerza', 'intermedio', [
            ['Pierna', 'abductores', 2, '20', 45], ['Pierna', 'smith-sentadilla', 4, '6-8', 150], ['Pierna', 'prensa-piernas', 4, '8-10', 120],
            ['Pierna', 'curl-femoral', 3, '10', 90], ['Pierna', 'extension-cuadriceps', 3, '12', 60], ['Pierna', 'pantorrilla', 4, '15', 45],
        ]],
    ];
    foreach ($rutinas as $r) {
        $rid = $ins('rutinas', ['nombre' => $r[0], 'descripcion' => $r[1], 'objetivo' => $r[2], 'nivel' => $r[3], 'plantilla' => 1, 'usuario_id' => null, 'activa' => 1, 'creado' => $now]);
        foreach ($r[4] as $o => $e) {
            $ins('rutina_ejercicios', ['rutina_id' => $rid, 'dia' => $e[0], 'orden' => $o + 1, 'video_id' => $ids[$e[1]] ?? null, 'nombre' => '',
                'series' => $e[2], 'reps' => $e[3], 'descanso_seg' => $e[4], 'trabajo_seg' => 0, 'carga' => '', 'nota' => '']);
        }
    }

    /* ----- Contenido educativo ----- */
    $art = [
        ['por-que-un-entrenador-personal', '¿Por qué entrenar con un entrenador personal?', 'Motivación',
            'No es solo alguien que cuenta repeticiones: es quien convierte tu esfuerzo en resultados medibles y sin lesiones.',
            "Entrenar solo funciona... hasta que deja de funcionar. La mayoría de personas que abandonan el gimnasio no lo hacen por pereza, sino porque no ven resultados, se lesionan o no saben qué hacer el siguiente mes.\n\n## Lo que cambia con un entrenador\n\n- **Un plan con dirección.** Cada semana tiene un propósito: no improvisas en el gimnasio.\n- **Técnica segura.** La mayoría de lesiones vienen de mover mucho peso con mala técnica. Un ojo experto las corrige a tiempo.\n- **Progresión medida.** Sin medir no sabes si avanzas. La valoración inicial y los controles mensuales lo muestran con números y fotos.\n- **Compromiso.** Tener una cita con alguien que te espera es la mejor cura contra el \"mañana empiezo\".\n- **Nutrición realista.** El entrenamiento se potencia (o se pierde) con lo que comes. Una guía adaptada a tus gustos y presupuesto es sostenible.\n\n## ¿Para quién es?\n\nPara quien empieza y no quiere perder meses, para quien lleva tiempo estancado, para quien viene de una lesión y para quien quiere un cambio de verdad: físico, de hábitos y de mentalidad.\n\nTu evolución empieza cuando decides no hacerlo solo."],
        ['descansos-entre-series', 'Cuánto descansar entre series según tu objetivo', 'Entrenamiento',
            'El descanso también es entrenamiento. Usa estos tiempos con el cronómetro de la página.',
            "El tiempo que descansas entre series cambia el resultado del entrenamiento. Estas son guías generales que usamos como punto de partida:\n\n## Fuerza (pocas repeticiones, mucho peso)\n\n**2 a 5 minutos.** El sistema nervioso necesita recuperarse para volver a levantar pesado con buena técnica.\n\n## Hipertrofia (ganar músculo)\n\n**60 a 120 segundos.** Suficiente para recuperar y seguir haciendo series de calidad. En ejercicios grandes (prensa, sentadilla) acércate a los 2 minutos.\n\n## Resistencia muscular\n\n**30 a 60 segundos.** Series de 15 o más repeticiones con descansos cortos.\n\n## Quema de grasa / acondicionamiento\n\nCircuitos e intervalos: por ejemplo 40 segundos de trabajo y 20 de descanso. El cronómetro de la página tiene estos modos listos.\n\n> Consejo: no mires el celular entre series. Pon el cronómetro, respira y concéntrate en la siguiente serie."],
        ['cuanta-proteina', '¿Cuánta proteína necesito al día?', 'Nutrición',
            'Una guía sencilla para repartir la proteína en tus comidas con alimentos de casa.',
            "La proteína ayuda a reparar y construir músculo. Para personas que entrenan, las guías deportivas suelen recomendar entre **1,6 y 2,2 gramos por kilo de peso corporal al día**, repartidos en varias comidas.\n\n## Ejemplo para una persona de 70 kg\n\nEntre 112 y 154 gramos al día, por ejemplo en 4 comidas de unos 30 a 35 g.\n\n## Alimentos de casa con buena proteína (aproximado)\n\n- 1 huevo: 6 g\n- 100 g de pechuga de pollo cocida: 30 g\n- 100 g de carne de res magra: 26 g\n- 1 lata de atún en agua: 25 g\n- 1 taza de lentejas o fríjoles cocidos: 15 a 18 g\n- 1 vaso de yogur griego: 15 a 20 g\n\n> Importante: si tienes alguna enfermedad renal, hepática o cualquier condición médica, consulta antes con tu médico. En los planes de Bryan la guía de alimentación se ajusta a tu caso."],
        ['fotos-de-progreso', 'Cómo tomar tus fotos de progreso', 'Evolución',
            'La báscula miente a veces. Las fotos bien tomadas, no.',
            "El peso sube y baja por agua, sal y digestión. Las fotos muestran el cambio real de tu cuerpo, por eso las pedimos al iniciar y cada mes.\n\n## Las 3 fotos\n\n1. **Frente**, brazos ligeramente separados del cuerpo.\n2. **Perfil**, de lado, brazos relajados.\n3. **Espalda**, brazos ligeramente separados.\n\n## Reglas de oro\n\n- Buena iluminación, de frente a una ventana o con luz blanca.\n- Cámara a la altura del pecho, a unos 2 metros.\n- Misma hora (ideal en la mañana, en ayunas), mismo lugar y misma ropa cada vez.\n- Traje de baño o ropa que permita ver tu punto físico actual.\n\nSúbelas en tu portal, en la sección **Mi evolución**: solo tú y Bryan pueden verlas."],
        ['mito-abdominales', 'Mito: el abdomen se marca haciendo abdominales', 'Mitos',
            'Hacer 500 abdominales al día no te va a quitar la grasa del abdomen. Esto sí funciona.',
            "No se puede eliminar grasa solo de una zona entrenando esa zona. La grasa abdominal se reduce cuando baja tu porcentaje de grasa total.\n\n## ¿Entonces qué funciona?\n\n- **Un déficit calórico moderado y sostenible**, con suficiente proteína.\n- **Entrenamiento de fuerza** de todo el cuerpo: más músculo, más gasto de energía.\n- **Movimiento diario**: caminar suma mucho más de lo que crees.\n- **Dormir bien**: dormir poco aumenta el hambre y dificulta perder grasa.\n\n## ¿Y los abdominales?\n\nSí sirven: fortalecen el core, protegen tu espalda y mejoran tu postura y tu rendimiento. Solo que no \"queman\" la grasa de encima."],
        ['primer-dia-gimnasio', 'Tu primer día en el gimnasio: guía sin miedo', 'Principiantes',
            'Qué llevar, cómo calentar y cómo usar las máquinas sin sentirte perdido.',
            "Todos fuimos principiantes. Esta guía te ayuda a que el primer día sea el primero de muchos.\n\n## Qué llevar\n\nToalla, botella de agua, ropa cómoda, tenis cerrados y tu celular con el cronómetro de esta página abierto.\n\n## Calentamiento (8 a 10 minutos)\n\n5 minutos de bicicleta o caminadora a ritmo suave y luego movilidad de cadera, hombros y tobillos.\n\n## Las máquinas son tus amigas\n\nLas máquinas guían el movimiento y son perfectas para aprender. En la **Biblioteca de máquinas** encuentras cómo ajustar cada una, los errores comunes y la carga sugerida para tu peso.\n\n## Empieza liviano\n\nLa primera semana el objetivo es aprender la técnica, no levantar mucho. Si terminas una serie y podrías haber hecho 4 o más repeticiones extra, la próxima vez sube un poco.\n\n## Lo más importante\n\nVolver. La constancia vence a la intensidad."],
    ];
    foreach ($art as $i => $a) {
        $ins('contenidos', ['tipo' => 'articulo', 'titulo' => $a[1], 'slug' => $a[0], 'resumen' => $a[3], 'cuerpo' => $a[4], 'imagen' => '',
            'imagen2' => '', 'archivo' => '', 'url' => '', 'categoria' => $a[2], 'usuario_id' => null, 'pide_datos' => 0, 'visible' => 1, 'orden' => $i + 1, 'vistas' => 0, 'creado' => $now]);
    }
    $faq = [
        ['¿Necesito experiencia para empezar?', 'No. Todos los planes empiezan con una valoración y se adaptan a tu nivel, desde cero. Si tienes alguna lesión o condición, la tenemos en cuenta desde el primer día.'],
        ['¿Cuál es la diferencia entre los planes?', 'La frecuencia de acompañamiento y el nivel de detalle: Heavy Initiation incluye 8 sesiones (2 por semana), Hard Gold 12 sesiones (3 por semana) y X-Trem VIP 16 sesiones (4 por semana) con mediciones antropométricas avanzadas y control mensual de macronutrientes.'],
        ['¿El entrenamiento puede ser virtual?', 'Sí. El plan X-Trem VIP incluye acompañamiento físico o virtual, y en tu portal tienes tus rutinas, videos y el cronómetro guiado para entrenar donde estés.'],
        ['¿Incluye plan de alimentación?', 'Todos los planes incluyen asesoría en nutrición adaptada a tus gustos, horarios y presupuesto. En X-Trem VIP además hay control mensual de macronutrientes.'],
        ['¿Cómo pago?', 'Por Nequi, Daviplata o transferencia a Davivienda. Subes el comprobante en la página y Bryan activa tu plan o tu acceso a los videos.'],
        ['¿Cómo veo mis avances?', 'Con tu usuario entras a tu portal: ves desde cuándo eres cliente, tus sesiones, tus medidas en gráficas, tus fotos de antes y después, tus rutinas y tu historial de pagos.'],
        ['¿Qué es la carga sugerida?', 'Es un punto de partida calculado según tu peso, tu nivel y tu objetivo para cada máquina. Se ajusta con la regla de las repeticiones: si te sobran muchas, sube; si no completas la serie con buena técnica, baja.'],
        ['¿Necesito descargar una aplicación?', 'No. El cronómetro, tu portal y los videos funcionan desde el navegador del celular. Si quieres, puedes agregar la página a tu pantalla de inicio y se abre como una app.'],
    ];
    foreach ($faq as $i => $f) {
        $ins('contenidos', ['tipo' => 'faq', 'titulo' => $f[0], 'slug' => '', 'resumen' => '', 'cuerpo' => $f[1], 'imagen' => '', 'imagen2' => '', 'archivo' => '', 'url' => '',
            'categoria' => '', 'usuario_id' => null, 'pide_datos' => 0, 'visible' => 1, 'orden' => $i + 1, 'vistas' => 0, 'creado' => $now]);
    }
    $ig = ['p/DZ5MYK-BvzU', 'reel/DTBDDhFkWXP', 'p/DHPNRlRShxT', 'p/DHLwU8WMlZX', 'p/DHBuC0AxSpZ', 'p/DE0vr2zylAk', 'p/DD8EUpOSW1j', 'reel/DD2_S7ASKYn', 'reel/DB5WyOUshb1', 'reel/DBQKjeSswoC', 'reel/DA_xeSsSsyP', 'p/DAtqa8vP-ku'];
    foreach ($ig as $i => $p) {
        $ins('contenidos', ['tipo' => 'social', 'titulo' => 'Instagram', 'slug' => '', 'resumen' => '', 'cuerpo' => '', 'imagen' => '', 'imagen2' => '', 'archivo' => '',
            'url' => 'https://www.instagram.com/' . $p . '/', 'categoria' => 'instagram', 'usuario_id' => null, 'pide_datos' => 0, 'visible' => $i < 6 ? 1 : 0, 'orden' => $i + 1, 'vistas' => 0, 'creado' => $now]);
    }
    $ins('contenidos', ['tipo' => 'catalogo', 'titulo' => 'Catálogo TU EVOLUCIÓN · Planes y precios', 'slug' => 'catalogo-planes', 'resumen' => 'Los 3 programas, qué incluye cada uno, la inversión y los medios de pago.',
        'cuerpo' => '', 'imagen' => '', 'imagen2' => '', 'archivo' => 'catalogos/tu-evolucion-planes.pdf', 'url' => '', 'categoria' => 'Planes', 'usuario_id' => null,
        'pide_datos' => 1, 'visible' => 1, 'orden' => 1, 'vistas' => 0, 'creado' => $now]);
    $rec = [
        ['Hidrátate antes de tener sed', 'Toma agua durante todo el día, no solo en el gimnasio. Una guía práctica: lleva siempre tu botella y termina al menos 2 antes de la tarde.'],
        ['Duerme 7 a 9 horas', 'El músculo se construye descansando. Dormir poco aumenta el hambre, baja tu energía y frena tu evolución.'],
        ['Registra tus cargas', 'En cada entreno del portal anota el peso que usaste. Así sabemos cuándo subir y ves tu progreso real.'],
    ];
    foreach ($rec as $i => $r) {
        $ins('contenidos', ['tipo' => 'recomendacion', 'titulo' => $r[0], 'slug' => '', 'resumen' => '', 'cuerpo' => $r[1], 'imagen' => '', 'imagen2' => '', 'archivo' => '', 'url' => '',
            'categoria' => 'General', 'usuario_id' => null, 'pide_datos' => 0, 'visible' => 1, 'orden' => $i + 1, 'vistas' => 0, 'creado' => $now]);
    }
}
