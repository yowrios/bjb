<?php
$s = $_GET['s'] ?? 'marca';
$secciones = ['marca' => 'Logo y fotos', 'contacto' => 'Contacto y redes', 'gimnasio' => 'Gimnasio', 'textos' => 'Textos de la portada', 'pagos' => 'Medios de pago', 'apariencia' => 'Colores', 'sistema' => 'Opciones', 'cuenta' => 'Mi cuenta', 'respaldo' => 'Copia de seguridad', 'reiniciar' => 'Reiniciar la web'];
$campos = [
    'contacto' => ['marca', 'nombre_entrenador', 'eslogan', 'whatsapp', 'whatsapp_texto', 'email', 'instagram', 'tiktok', 'facebook', 'ciudad', 'zona_atencion', 'horario', 'url_publica'],
    'textos' => ['franja', 'hero_pre', 'hero_titulo', 'hero_texto', 'sobre_titulo', 'sobre_texto', 'cta_final', 'cta_final_texto', 'aviso_portada'],
    'pagos' => ['pago_nequi', 'pago_daviplata', 'pago_banco', 'pago_cuenta_tipo', 'pago_cuenta', 'pago_titular', 'pago_cedula', 'pago_link', 'pago_nota'],
    'apariencia' => ['tema', 'color_principal', 'color_acento', 'color_fondo', 'hero_silueta'],
    'marca' => ['marca', 'eslogan'],
    'gimnasio' => ['gym_nombre', 'gym_direccion', 'gym_texto', 'gym_mapa'],
    'sistema' => ['seo_titulo', 'seo_desc', 'pausa_aviso_seg', 'precio_video_defecto', 'dias_acceso_video', 'geo_activo', 'mostrar_instagram', 'mostrar_tiktok'],
];
if (es_post()) {
    csrf_verificar();
    try {
        if (post('acc') === 'restablecer') {
            // Vuelve a los valores sugeridos solo en la sección indicada
            $grupos = ['apariencia' => ['tema', 'color_principal', 'color_acento', 'color_fondo', 'hero_silueta'],
                'textos' => ['franja', 'hero_pre', 'hero_titulo', 'hero_texto', 'sobre_titulo', 'sobre_texto', 'cta_final', 'cta_final_texto', 'aviso_portada', 'cifras', 'metodo'],
                'marca' => ['logo', 'foto_hero', 'foto_sobre', 'textura', 'fondo_hero']];
            $def = ajustes_defecto();
            foreach ($grupos[$s] ?? [] as $k) if (array_key_exists($k, $def)) guardar_ajuste($k, $def[$k]);
            if ($s === 'marca') foreach ([48, 192, 512] as $t) @unlink(APP_ROOT . "/uploads/marca/icono-$t.png");
            flash('Listo: volviste a los valores sugeridos de esta sección.');
        } elseif (in_array(post('acc'), ['reiniciar_datos', 'reiniciar_todo'], true)) {
            $u = usuario();
            if (!password_verify((string)($_POST['clave_confirma'] ?? ''), $u['clave_hash'])) throw new RuntimeException('La contraseña no es correcta. No se borró nada.');
            if (mb_strtoupper(trim((string)($_POST['palabra'] ?? ''))) !== 'BORRAR') throw new RuntimeException('Escribe la palabra BORRAR para confirmar. No se borró nada.');
            $copia = reiniciar_web(post('acc') === 'reiniciar_todo');
            if (post('acc') === 'reiniciar_todo') { logout_usuario(); redirigir(url('admin/')); }
            flash('Datos de prueba borrados. Se guardó una copia de seguridad antes: ' . $copia);
        } elseif (isset($campos[$s])) {
            foreach ($campos[$s] as $k) if (array_key_exists($k, $_POST)) {
                $v = trim((string)$_POST[$k]);
                if ($k === 'whatsapp') $v = wa_numero($v);
                if ($k === 'instagram' || $k === 'tiktok') $v = ltrim(preg_replace('~^https?://(www\.)?(instagram|tiktok)\.com/@?~i', '', rtrim($v, '/')), '@');
                if ($k === 'tema' && !isset(temas()[$v])) $v = 'fuego';
                if (in_array($k, ['color_principal', 'color_acento', 'color_fondo'], true) && !preg_match('/^#[0-9a-f]{6}$/i', $v)) continue;
                guardar_ajuste($k, $v);
            }
            if ($s === 'marca' || $s === 'gimnasio') {
                foreach (($s === 'marca' ? ['logo', 'foto_hero', 'foto_sobre', 'textura', 'fondo_hero'] : ['gym_logo']) as $img) {
                    if (!empty($_POST['quitar_' . $img])) { guardar_ajuste($img, ''); continue; }
                    $n = subir_archivo($_FILES[$img] ?? ['error' => UPLOAD_ERR_NO_FILE], TIPOS_IMAGEN, false, 'marca', 15);
                    if ($n) { guardar_ajuste($img, $n); if ($img === 'logo') generar_iconos($n); }
                }
                if (!empty($_POST['quitar_logo'])) foreach ([48, 192, 512] as $t) @unlink(APP_ROOT . "/uploads/marca/icono-$t.png");
            }
            if ($s === 'textos') {
                $cif = []; foreach ((array)($_POST['cifra'] ?? []) as $c) if (trim((string)($c[0] ?? '')) !== '') $cif[] = [trim((string)$c[0]), trim((string)($c[1] ?? ''))];
                guardar_ajuste('cifras', json_encode($cif, JSON_UNESCAPED_UNICODE));
                $met = []; foreach ((array)($_POST['metodo'] ?? []) as $c) if (trim((string)($c[0] ?? '')) !== '') $met[] = [trim((string)$c[0]), trim((string)($c[1] ?? ''))];
                guardar_ajuste('metodo', json_encode($met, JSON_UNESCAPED_UNICODE));
            }
            flash('Guardado. Ya se ve en tu web.');
        } elseif ($s === 'cuenta') {
            $u = usuario();
            if (!password_verify((string)($_POST['actual'] ?? ''), $u['clave_hash'])) throw new RuntimeException('La contraseña actual no es correcta.');
            $em = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            if (!filter_var($em, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Correo no válido.');
            if (valor('SELECT COUNT(*) FROM usuarios WHERE LOWER(email) = ? AND id <> ?', [$em, $u['id']])) throw new RuntimeException('Ese correo ya lo usa otra cuenta.');
            $d = ['nombre' => trim((string)$_POST['nombre']) ?: $u['nombre'], 'email' => $em, 'debe_cambiar' => 0];
            $n = (string)($_POST['nueva'] ?? '');
            if ($n !== '') { if (mb_strlen($n) < 10) throw new RuntimeException('La nueva contraseña debe tener al menos 10 caracteres.'); if ($n !== ($_POST['repite'] ?? '')) throw new RuntimeException('Las contraseñas no coinciden.'); $d['clave_hash'] = password_hash($n, PASSWORD_DEFAULT); }
            actualizar('usuarios', (int)$u['id'], $d);
            flash('Cuenta actualizada.');
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'error'); }
    redirigir(url('admin/?p=ajustes&s=' . $s));
}
if ($s === 'respaldo' && isset($_GET['descargar'])) {
    if (DB_DRIVER === 'sqlite') {
        $dest = PRIV_DIR . '/respaldos/bryanjfit-' . date('Ymd-His') . '.sqlite';
        db()->exec("VACUUM INTO '" . str_replace("'", "''", $dest) . "'");
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($dest) . '"');
        header('Content-Length: ' . filesize($dest));
        readfile($dest);
        foreach (array_slice(glob(PRIV_DIR . '/respaldos/*.sqlite') ?: [], 0, -5) as $viejo) @unlink($viejo); // guarda solo los 5 últimos
        exit;
    }
    $out = [];
    foreach (['ajustes', 'usuarios', 'planes', 'membresias', 'sesiones', 'pagos', 'videos', 'accesos_video', 'medidas', 'fotos', 'rutinas', 'rutina_ejercicios', 'entrenos', 'checkins', 'leads', 'contenidos', 'propuestas', 'formularios'] as $tb) $out[$tb] = filas("SELECT * FROM $tb");
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="bryanjfit-' . date('Ymd-His') . '.json"');
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
admin_cabecera('ajustes', 'Ajustes');
$a = fn($k) => ajuste($k);
?>
<nav class="ad-pestanas"><?php foreach ($secciones as $k => $tt): ?><a class="<?= $s === $k ? 'on' : '' ?>" href="<?= url('admin/?p=ajustes&s=' . $k) ?>"><?= $tt ?></a><?php endforeach; ?></nav>
<?php if (isset($_GET['cambiar'])): ?><div class="msg msg-error">Por seguridad, cambia tu contraseña.</div><?php endif; ?>
<form method="post" class="ad-caja" style="max-width:1100px" enctype="multipart/form-data"><?= csrf_campo() ?>
<?php if ($s === 'marca'):
  $fotoCampo = function (string $k, string $tit, string $req) {
      $tiene = ajuste($k) !== '';
      return '<div class="ad-caja" style="background:var(--negro)"><h2>' . e($tit) . '</h2><div class="ad-foto-campo">'
          . '<div class="ad-foto-previa"><img src="' . imagen_marca($k) . '" alt=""></div>'
          . '<div><p class="sec" style="margin-top:0">' . $req . '</p><input type="file" name="' . $k . '" accept="image/png,image/webp,image/jpeg">'
          . ($tiene ? '<label class="check mt1"><input type="checkbox" name="quitar_' . $k . '" value="1"> <span>Volver a la imagen original</span></label>' : '<p class="sec">Usando la imagen original.</p>') . '</div></div></div>';
  }; ?>
  <div class="ad-nota">Aquí cambias el nombre de la marca, el logo y las fotos del diseño. Los cambios se ven de inmediato en toda la web, el panel, el catálogo, las propuestas y el ícono de la app.</div>
  <div class="campos"><?= campo('marca', 'Nombre de la marca', $a('marca'), 'text', ['ayuda' => 'Aparece en el menú, el pie de página, Google y la app instalada.']) ?><?= campo('eslogan', 'Eslogan', $a('eslogan')) ?></div>
  <div class="ad-rejilla mt2">
    <?= $fotoCampo('logo', 'Logo', 'PNG con fondo transparente (o WebP). Horizontal o cuadrado, mínimo 600 px de ancho, máximo 2 MB. Que se vea bien sobre fondo negro (letras claras). Con él se crean solos el ícono de la app y el de la pestaña del navegador.') ?>
    <?= $fotoCampo('foto_hero', 'Foto principal (portada)', 'Foto de cuerpo completo, VERTICAL y con el fondo quitado (PNG transparente o WebP). Recomendado 1000 × 2400 px o más, de la cabeza a los pies, sin cortar los zapatos. El fondo se quita gratis en remove.bg o con la app Fotos del iPhone (mantener presionada la persona). Máximo 8 MB.') ?>
    <?= $fotoCampo('foto_sobre', 'Foto de «Sobre mí»', 'Retrato de la cintura hacia arriba, VERTICAL, ideal con el fondo quitado (PNG o WebP). Recomendado 1000 × 1500 px. Buena luz de frente, mirando a la cámara. Máximo 8 MB.') ?>
    <?= $fotoCampo('fondo_hero', 'Fondo de la portada (detrás de la foto principal)', 'Foto HORIZONTAL de 1920 × 1080 px o más, menos de 1,5 MB, muy oscura y con luz de un solo color (estilo gimnasio de noche). Va detrás de la foto principal y se oscurece sola para que se lea el texto. Usa una foto propia o una que tengas permiso de usar.') ?>
    <?= $fotoCampo('textura', 'Textura de fondo', 'Imagen oscura de fondo (concreto, pared, gimnasio desenfocado), JPG de 1920 × 1080 px o más y menos de 1 MB. Debe ser muy oscura para que se lean los textos encima.') ?>
  </div>
  <div class="ad-nota mt2">Consejos para tus fotos: luz natural de frente (no a contraluz), celular a la altura del pecho, ropa negra o de tu marca, postura segura (brazos cruzados o pose de entreno) y sonrisa. Toma varias y elige la mejor. Las fotos muy grandes se reducen solas para que la web cargue rápido.</div>
<?php elseif ($s === 'gimnasio'): ?>
  <div class="ad-nota">Muestra dónde entrenas: aparece en la portada con mapa y botón «Cómo llegar».</div>
  <div class="campos">
    <?= campo('gym_nombre', 'Nombre del gimnasio (vacío = no mostrar la sección)', $a('gym_nombre'), 'text', ['ancho' => true]) ?>
    <?= campo('gym_direccion', 'Dirección para el mapa', $a('gym_direccion'), 'text', ['ancho' => true]) ?>
    <?= campo('gym_texto', 'Texto', $a('gym_texto'), 'textarea', ['ancho' => true]) ?>
    <?= campo('gym_mapa', 'Mostrar mapa', $a('gym_mapa'), 'select', ['opciones' => ['1' => 'Sí', '0' => 'No']]) ?>
  </div>
  <div class="ad-caja mt2" style="background:var(--negro)"><h2>Logo del gimnasio (opcional)</h2>
    <?php if (ajuste('gym_logo')): ?><img src="<?= e(img_publica(ajuste('gym_logo'))) ?>" alt="" style="max-height:70px;margin-bottom:10px"><label class="check"><input type="checkbox" name="quitar_gym_logo" value="1"> <span>Quitar logo</span></label><?php endif; ?>
    <input type="file" name="gym_logo" accept="image/png,image/webp,image/jpeg">
    <p class="sec mt1">Importante: el logo de Smart Fit es una marca registrada. Súbelo solo si Smart Fit te autoriza por escrito a usarlo en tu publicidad (pregunta al administrador de la sede). Mientras tanto, la web muestra el nombre del gimnasio en texto, que sí puedes usar para decir dónde entrenas.</p>
  </div>
<?php elseif ($s === 'contacto'): ?>
  <div class="campos">
    <?= campo('marca', 'Nombre de la marca', $a('marca')) ?><?= campo('nombre_entrenador', 'Tu nombre', $a('nombre_entrenador')) ?>
    <?= campo('eslogan', 'Eslogan (aparece en el pie de página)', $a('eslogan'), 'text', ['ancho' => true]) ?>
    <?= campo('whatsapp', 'WhatsApp', $a('whatsapp'), 'tel', ['ayuda' => 'Con indicativo: 573182086805']) ?><?= campo('email', 'Correo', $a('email'), 'email') ?>
    <?= campo('whatsapp_texto', 'Mensaje que llega por WhatsApp desde la web', $a('whatsapp_texto'), 'text', ['ancho' => true]) ?>
    <?= campo('instagram', 'Usuario de Instagram', $a('instagram'), 'text', ['placeholder' => 'brynanopk']) ?><?= campo('tiktok', 'Usuario de TikTok', $a('tiktok'), 'text', ['placeholder' => 'brynanopk', 'ayuda' => 'Déjalo vacío si no tienes.']) ?>
    <?= campo('facebook', 'Página de Facebook', $a('facebook'), 'url', ['ancho' => true]) ?>
    <?= campo('ciudad', 'Ciudad / gimnasio', $a('ciudad'), 'text', ['placeholder' => 'Bogotá · Gimnasio X']) ?><?= campo('horario', 'Horario de atención', $a('horario')) ?>
    <?= campo('zona_atencion', 'Dónde atiendes', $a('zona_atencion'), 'text', ['ancho' => true]) ?>
    <?= campo('url_publica', 'Dirección de tu web (cuando tengas dominio)', $a('url_publica'), 'url', ['ancho' => true, 'placeholder' => 'https://www.bjb.com.co', 'ayuda' => 'Se usa en el QR, la tarjeta de presentación y los enlaces que compartes. Vacío = la dirección actual.']) ?>
  </div>
<?php elseif ($s === 'textos'): $cif = ajuste_json('cifras'); $met = ajuste_json('metodo'); ?>
  <h2><?= icono('home') ?> Portada</h2>
  <div class="campos">
    <?= campo('hero_pre', 'Texto pequeño superior', $a('hero_pre'), 'text', ['ancho' => true]) ?>
    <?= campo('hero_titulo', 'Título grande', $a('hero_titulo'), 'text', ['ancho' => true, 'ayuda' => 'La segunda mitad de las palabras se pinta de rojo.']) ?>
    <?= campo('hero_texto', 'Texto de presentación', $a('hero_texto'), 'textarea', ['ancho' => true]) ?>
    <?= campo('franja', 'Palabras de la franja metálica (una por línea, en orden)', $a('franja'), 'textarea', ['ancho' => true, 'filas' => 7, 'ayuda' => 'Se muestran numeradas y en este orden: 01 Mentalidad, 02 Disciplina...']) ?>
    <?= campo('aviso_portada', 'Aviso en la parte superior (opcional)', $a('aviso_portada'), 'text', ['ancho' => true, 'placeholder' => '🔥 Noviembre: valoración gratis al tomar Hard Gold', 'ayuda' => 'Déjalo vacío para no mostrar nada.']) ?>
  </div>
  <h2 class="mt2"><?= icono('chart') ?> Cifras (4 casillas)</h2>
  <div class="campos"><?php for ($i = 0; $i < 4; $i++): ?><div class="campo"><label>Cifra <?= $i + 1 ?></label><input name="cifra[<?= $i ?>][0]" value="<?= e($cif[$i][0] ?? '') ?>" placeholder="4+"></div><div class="campo"><label>Texto</label><input name="cifra[<?= $i ?>][1]" value="<?= e($cif[$i][1] ?? '') ?>" placeholder="años entrenando personas"></div><?php endfor; ?></div>
  <p class="sec">Usa solo cifras reales. Cuando tengas más clientes, cambia una por «+50 clientes transformados».</p>
  <h2 class="mt2"><?= icono('target') ?> El método (4 pasos)</h2>
  <div class="campos"><?php for ($i = 0; $i < 4; $i++): ?><div class="campo"><label>Paso <?= $i + 1 ?></label><input name="metodo[<?= $i ?>][0]" value="<?= e($met[$i][0] ?? '') ?>"></div><div class="campo"><label>Descripción</label><input name="metodo[<?= $i ?>][1]" value="<?= e($met[$i][1] ?? '') ?>"></div><?php endfor; ?></div>
  <h2 class="mt2"><?= icono('user') ?> Sobre ti</h2>
  <div class="campos"><?= campo('sobre_titulo', 'Título', $a('sobre_titulo')) ?><div></div><?= campo('sobre_texto', 'Tu historia (deja una línea en blanco entre párrafos)', $a('sobre_texto'), 'textarea', ['ancho' => true, 'filas' => 8]) ?></div>
  <h2 class="mt2"><?= icono('flame') ?> Llamado final</h2>
  <div class="campos"><?= campo('cta_final', 'Frase grande', $a('cta_final')) ?><div></div><?= campo('cta_final_texto', 'Texto', $a('cta_final_texto'), 'textarea', ['ancho' => true]) ?></div>
<?php elseif ($s === 'pagos'): ?>
  <div class="ad-nota">Estos datos se muestran en la página de pago, el catálogo y las propuestas.</div>
  <div class="campos">
    <?= campo('pago_nequi', 'Nequi', $a('pago_nequi')) ?><?= campo('pago_daviplata', 'Daviplata', $a('pago_daviplata')) ?>
    <?= campo('pago_banco', 'Banco', $a('pago_banco')) ?><?= campo('pago_cuenta_tipo', 'Tipo de cuenta', $a('pago_cuenta_tipo')) ?>
    <?= campo('pago_cuenta', 'Número de cuenta', $a('pago_cuenta')) ?><?= campo('pago_titular', 'Titular', $a('pago_titular')) ?>
    <?= campo('pago_cedula', 'Cédula del titular', $a('pago_cedula'), 'text', ['ayuda' => 'Déjalo vacío si no quieres mostrarla.']) ?>
    <?= campo('pago_link', 'Enlace general de pago con tarjeta / PSE (opcional)', $a('pago_link'), 'url', ['placeholder' => 'https://checkout.wompi.co/l/...']) ?>
    <?= campo('pago_nota', 'Nota debajo del formulario de pago', $a('pago_nota'), 'text', ['ancho' => true]) ?>
  </div>
<?php elseif ($s === 'apariencia'): $tAct = ajuste('tema', 'fuego'); $tp = temporada_activa(); ?>
  <?php if ($tp && !empty($tp['colores'])): ?><div class="ad-nota">Ahora mismo se ve la temporada <b><?= e($tp['nombre']) ?></b> con sus colores. Cuando termine, la web vuelve a la combinación que elijas aquí. Se cambia en <a href="<?= url('admin/?p=temporadas') ?>" style="color:#fff">Temporadas</a>.</div><?php endif; ?>
  <?php [$v1, , $v3, $vf] = tema_colores(); if ($tp && !empty($tp['colores'])) { $tt = temas()[$tAct] ?? temas()['fuego']; if ($tAct === 'personalizado') { $v1 = color_valido(ajuste('color_principal'), '#ff2a2a'); $v3 = color_valido(ajuste('color_acento'), '#d4ff3a'); $vf = color_valido(ajuste('color_fondo'), '#0a0a0c'); } else { [$v1, $v3, $vf] = [$tt[1], $tt[2], $tt[3]]; } } ?>
  <div class="vp" id="vistaPrevia" style="--p:<?= $v1 ?>;--a:<?= $v3 ?>;--f:<?= $vf ?>;--f2:<?= aclarar($vf, .06) ?>;--sp:<?= texto_sobre($v1) ?>">
    <div class="vp-cab"><b>Así se verá tu web</b><span id="vpNombre"><?= e((temas()[$tAct] ?? temas()['fuego'])[0]) ?></span></div>
    <div class="vp-web">
      <div class="vp-nav"><span class="vp-logo"><?= e(ajuste('marca')) ?></span><span class="vp-links"><i>Inicio</i><i>Planes</i><i>Máquinas</i></span><span class="vp-btn">EMPIEZA HOY</span></div>
      <div class="vp-cuerpo">
        <div><span class="vp-pre">● Entrenador personal</span><div class="vp-h1">TU EVOLUCIÓN<br><em>EMPIEZA HOY</em></div><p>Un sistema integral donde te guío paso a paso en tu transformación.</p>
          <div class="vp-acc"><span class="vp-btn">VER PLANES</span><span class="vp-btn2">HABLAR CON BRYAN</span></div></div>
        <div class="vp-plan"><span class="vp-et">EL MÁS ELEGIDO</span><b>HARD GOLD</b><strong>$480.000</strong><span class="vp-chip">✓ Videos incluidos</span><span class="vp-li">✓ Valoración física</span><span class="vp-li">✓ Plan a tu medida</span></div>
      </div>
    </div>
    <div class="vp-colores">
      <span><i style="background:var(--p)"></i>Principal <code id="vpP"><?= $v1 ?></code></span>
      <span><i style="background:var(--a)"></i>Acento <code id="vpA"><?= $v3 ?></code></span>
      <span><i style="background:var(--f)"></i>Fondo <code id="vpF"><?= $vf ?></code></span>
    </div>
  </div>
  <p class="sec mt2">Toca una combinación para verla arriba. Cuando te guste, presiona <b>Guardar</b>.</p>
  <div class="ad-temas"><?php foreach (temas() as $k => [$n, $c1, $c3, $cf, $desc]):
    if ($k === 'personalizado') { $c1 = color_valido(ajuste('color_principal'), '#ff2a2a'); $c3 = color_valido(ajuste('color_acento'), '#d4ff3a'); $cf = color_valido(ajuste('color_fondo'), '#0a0a0c'); } ?>
    <label><input type="radio" name="tema" value="<?= $k ?>" data-n="<?= e($n) ?>" data-p="<?= $c1 ?>" data-a="<?= $c3 ?>" data-f="<?= $cf ?>" <?= $tAct === $k ? 'checked' : '' ?>><span class="tema">
      <span class="franja-color">
        <span style="background:<?= $c1 ?>;color:<?= texto_sobre($c1) ?>">Principal</span>
        <span style="background:<?= $c3 ?>;color:<?= texto_sobre($c3) ?>">Acento</span>
        <span style="background:<?= $cf ?>;color:#fff">Fondo</span>
      </span>
      <b><?= e($n) ?></b><?= $k === 'fuego' ? ' <span class="chip chip-volt" style="font-size:10px;padding:2px 8px">Sugerida</span>' : '' ?><?= $tAct === $k ? ' <span class="chip chip-ok" style="font-size:10px;padding:2px 8px">Actual</span>' : '' ?><br><span class="sec"><?= e($desc) ?></span></span></label>
  <?php endforeach; ?></div>
  <div class="ad-caja mt2" style="background:var(--negro)"><h2>Mis propios colores</h2><p class="sec">Elige «Mis propios colores» arriba y escoge aquí los tres colores.</p>
    <div class="campos" style="grid-template-columns:repeat(3,1fr)"><div class="campo"><label>Principal (botones, títulos)</label><input type="color" name="color_principal" value="<?= e(color_valido(ajuste('color_principal'), '#ff2a2a')) ?>" style="height:52px;padding:4px"></div>
      <div class="campo"><label>Acento (detalles)</label><input type="color" name="color_acento" value="<?= e(color_valido(ajuste('color_acento'), '#d4ff3a')) ?>" style="height:52px;padding:4px"></div>
      <div class="campo"><label>Fondo (muy oscuro)</label><input type="color" name="color_fondo" value="<?= e(color_valido(ajuste('color_fondo'), '#0a0a0c')) ?>" style="height:52px;padding:4px"></div></div></div>
  <div class="ad-caja mt2" style="background:var(--negro)"><h2>Estilo de la foto principal</h2>
    <input type="hidden" name="hero_silueta" value="0"><label class="check"><input type="checkbox" name="hero_silueta" value="1" <?= ajuste('hero_silueta', '0') === '1' ? 'checked' : '' ?>> <span><b>Silueta a contraluz</b>: la foto principal se muestra como una sombra con borde de luz del color principal, marco de neón y reflejo en el piso (estilo gimnasio nocturno). Queda espectacular con «Gimnasio nocturno (ámbar)». Funciona mejor si la foto tiene el fondo quitado.</span></label></div>
  <button class="btn btn-borde mt2" type="submit" name="acc" value="restablecer" formnovalidate onclick="return confirm('¿Volver a los colores sugeridos (Rojo fuego)?')"><?= icono('refresh') ?> Restablecer colores sugeridos</button>
  <script>
  (function () {
    var vp = document.getElementById('vistaPrevia'); if (!vp) return;
    var aclara = function (h, f) { var n = parseInt(h.slice(1), 16), r = n >> 16, g = n >> 8 & 255, b = n & 255; var c = function (x) { return Math.round(x + (255 - x) * f); }; return 'rgb(' + c(r) + ',' + c(g) + ',' + c(b) + ')'; };
    var sobre = function (h) { var n = parseInt(h.slice(1), 16); return (0.299 * (n >> 16) + 0.587 * (n >> 8 & 255) + 0.114 * (n & 255)) > 160 ? '#0a0a0c' : '#ffffff'; };
    var pintar = function (p, a, f, nombre) {
      vp.style.setProperty('--p', p); vp.style.setProperty('--a', a); vp.style.setProperty('--f', f); vp.style.setProperty('--f2', aclara(f, .06)); vp.style.setProperty('--sp', sobre(p));
      document.getElementById('vpP').textContent = p; document.getElementById('vpA').textContent = a; document.getElementById('vpF').textContent = f;
      if (nombre) document.getElementById('vpNombre').textContent = nombre;
    };
    var propios = function () { return [document.querySelector('[name=color_principal]').value, document.querySelector('[name=color_acento]').value, document.querySelector('[name=color_fondo]').value]; };
    document.querySelectorAll('input[name=tema]').forEach(function (r) {
      r.addEventListener('change', function () {
        if (r.value === 'personalizado') { var c = propios(); pintar(c[0], c[1], c[2], r.dataset.n); }
        else pintar(r.dataset.p, r.dataset.a, r.dataset.f, r.dataset.n);
        vp.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
    ['color_principal', 'color_acento', 'color_fondo'].forEach(function (n) {
      document.querySelector('[name=' + n + ']').addEventListener('input', function () {
        var per = document.querySelector('input[name=tema][value=personalizado]'); per.checked = true;
        var c = propios(); pintar(c[0], c[1], c[2], per.dataset.n);
        var fr = per.closest('label').querySelectorAll('.franja-color span'); fr[0].style.background = c[0]; fr[1].style.background = c[1]; fr[2].style.background = c[2];
      });
    });
  })();
  </script>
<?php elseif ($s === 'sistema'): ?>
  <div class="campos">
    <?= campo('seo_titulo', 'Título en Google', $a('seo_titulo'), 'text', ['ancho' => true]) ?>
    <?= campo('seo_desc', 'Descripción en Google', $a('seo_desc'), 'textarea', ['ancho' => true, 'filas' => 2]) ?>
    <?= campo('pausa_aviso_seg', 'Cronómetro: avisar si alguien deja la pausa más de (segundos)', $a('pausa_aviso_seg'), 'number') ?>
    <?= campo('precio_video_defecto', 'Precio sugerido para videos nuevos', $a('precio_video_defecto'), 'number') ?>
    <?= campo('dias_acceso_video', 'Días de acceso al comprar un video', $a('dias_acceso_video'), 'number') ?>
    <?= campo('geo_activo', 'Mostrar ciudad aproximada de los visitantes', $a('geo_activo'), 'select', ['opciones' => ['1' => 'Sí (usa el servicio gratuito ipwho.is)', '0' => 'No']]) ?>
    <?= campo('mostrar_instagram', 'Mostrar publicaciones de Instagram', $a('mostrar_instagram'), 'select', ['opciones' => ['1' => 'Sí', '0' => 'No']]) ?>
    <?= campo('mostrar_tiktok', 'Mostrar publicaciones de TikTok', $a('mostrar_tiktok'), 'select', ['opciones' => ['1' => 'Sí', '0' => 'No']]) ?>
  </div>
<?php elseif ($s === 'cuenta'): $u = usuario(); ?>
  <div class="campos">
    <?= campo('nombre', 'Tu nombre', $u['nombre']) ?><?= campo('email', 'Correo para entrar', $u['email'], 'email') ?>
    <?= campo('nueva', 'Nueva contraseña (opcional, mín. 10)', '', 'password') ?><?= campo('repite', 'Repite la nueva', '', 'password') ?>
    <?= campo('actual', 'Contraseña actual (obligatoria para guardar)', '', 'password', ['required' => true]) ?>
  </div>
<?php elseif ($s === 'reiniciar'): ?>
  <h2><?= icono('trash') ?> Reiniciar la web</h2>
  <div class="ad-nota">Antes de borrar, el sistema guarda sola una copia de seguridad completa en la carpeta privada (respaldos). Aun así, <b>esto no se puede deshacer desde el panel</b>.</div>
  <div class="ad-rejilla">
    <div class="ad-caja" style="background:var(--negro)"><h2>1. Borrar datos de prueba</h2>
      <p class="sec">Borra clientes, pagos, pedidos, medidas, fotos, rutinas asignadas, check-ins, interesados, propuestas y visitas. Conserva tu cuenta, tus planes y precios, máquinas y videos, paquetes, productos, artículos, textos, logo y colores. Úsalo cuando termines de probar y vayas a empezar con clientes reales.</p>
      <button class="btn btn-borde" type="submit" name="acc" value="reiniciar_datos" onclick="return confirm('¿Borrar todos los clientes y datos de prueba? Se conserva la configuración.')">Borrar datos de prueba</button></div>
    <div class="ad-caja" style="background:var(--negro);border-color:#ef4444"><h2 style="color:#fca5a5">2. Restablecer de fábrica</h2>
      <p class="sec">Deja la web como nueva: borra TODO, incluida tu cuenta de administrador, planes creados, textos, logo, fotos y archivos subidos. Al terminar, /admin/ vuelve a pedir crear el administrador. Úsalo al entregar la web a su dueño.</p>
      <button class="btn btn-rojo" type="submit" name="acc" value="reiniciar_todo" style="background:#b91c1c" onclick="return confirm('ÚLTIMA ADVERTENCIA: se borra TODO, también tu cuenta. ¿Continuar?')">Restablecer de fábrica</button></div>
  </div>
  <div class="campos mt2" style="max-width:620px">
    <?= campo('clave_confirma', 'Tu contraseña del panel', '', 'password', ['ayuda' => 'Obligatoria para cualquiera de las dos opciones.']) ?>
    <?= campo('palabra', 'Escribe BORRAR para confirmar', '', 'text', ['placeholder' => 'BORRAR']) ?>
  </div>
<?php elseif ($s === 'respaldo'): ?>
  <h2><?= icono('download') ?> Copia de seguridad</h2>
  <p class="sec">Descarga una copia de toda la información (clientes, pagos, medidas, rutinas, contenido). Guárdala en tu Drive o en tu computador <b>una vez por semana</b>. Las fotos y comprobantes están en la carpeta privada del servidor: pide a quien administra tu hosting que la incluya en su copia.</p>
  <a class="btn btn-rojo" href="<?= url('admin/?p=ajustes&s=respaldo&descargar=1') ?>"><?= icono('download') ?> Descargar copia ahora</a>
  <p class="sec mt2">Base de datos: <?= DB_DRIVER === 'sqlite' ? 'archivo SQLite' : 'MySQL' ?> · Versión de la web <?= APP_VERSION ?></p>
<?php endif; ?>
<?php if (!in_array($s, ['respaldo', 'reiniciar'], true)): ?><div class="ad-acciones mt2" style="justify-content:flex-start"><button class="btn btn-rojo" type="submit">Guardar</button>
  <?php if (in_array($s, ['textos', 'marca'], true)): ?><button class="btn btn-borde" type="submit" name="acc" value="restablecer" formnovalidate onclick="return confirm('¿Volver a los <?= $s === 'textos' ? 'textos' : 'logo y fotos' ?> sugeridos? Se pierden tus cambios de esta sección.')"><?= icono('refresh') ?> Restablecer <?= $s === 'textos' ? 'textos sugeridos' : 'logo y fotos originales' ?></button><?php endif; ?></div><?php endif; ?>
</form>
<?php admin_pie();
