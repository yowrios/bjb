<?php
/** Plantilla común de las páginas públicas y del portal del cliente. */

function cabecera(string $titulo = '', array $o = []): void {
    cabeceras_seguridad();
    iniciar_sesion();
    if (empty($o['sin_visita'])) registrar_visita($o['ruta'] ?? strtok($_SERVER['REQUEST_URI'] ?? '/', '#'));
    $marca = ajuste('marca', 'BJB');
    $tituloCompleto = $titulo ? $titulo . ' · ' . $marca : ajuste('seo_titulo', $marca);
    $desc = $o['desc'] ?? ajuste('seo_desc');
    $u = usuario();
    $activo = $o['activo'] ?? '';
    $ogImg = (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) : '') . icono_app(512);
    $nav = [
        'inicio' => [url(''), 'Inicio'],
        'planes' => [url('#planes'), 'Planes'],
        'maquinas' => [url('maquinas.php'), 'Máquinas'],
        'aprende' => [url('aprende.php'), 'Aprende'],
        'cronometro' => [url('cronometro.php'), 'Cronómetro'],
    ];
    if (tienda_activa()) $nav = array_slice($nav, 0, 3, true) + ['tienda' => [url('tienda.php'), 'Tienda']] + array_slice($nav, 3, null, true);
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($tituloCompleto) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#0a0a0c">
<meta property="og:title" content="<?= e($tituloCompleto) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:image" content="<?= e($ogImg) ?>">
<meta property="og:type" content="website">
<link rel="icon" href="<?= icono_app(48) ?>">
<link rel="apple-touch-icon" href="<?= icono_app(192) ?>">
<link rel="manifest" href="<?= url('manifest.php') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700;800&display=swap" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700;800&display=swap"></noscript>
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<style><?= tema_css() ?></style>
<?php $tp = empty($o['sin_nav']) ? temporada_activa() : null; if ($tp): ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Mountains+of+Christmas:wght@700&family=Creepster&family=Great+Vibes&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= asset('css/temporada.css') ?>">
<?php endif; ?>
<?php if (!empty($o['head'])) echo $o['head']; ?>
<script>window.BJ = {base: <?= json_encode(BASE) ?>, csrf: <?= json_encode(csrf()) ?>, visita: <?= json_encode($GLOBALS['BJ_VISITA_ID'] ?? null) ?>};</script>
</head>
<body class="<?= e(($o['body'] ?? '') . ($tp ? ' temporada temporada-' . $tp['clave'] : '')) ?>">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<?php if (empty($o['sin_nav'])): ?>
<header class="nav" id="nav">
  <div class="nav-in">
    <a class="nav-logo" href="<?= url('') ?>" aria-label="<?= e($marca) ?> - inicio">
      <img src="<?= imagen_marca('logo') ?>" alt="" width="46" height="46"><span class="nav-marca"><?= e($marca) ?><?php if (ajuste('eslogan')): ?><small><?= e(ajuste('eslogan')) ?></small><?php endif; ?></span>
    </a>
    <nav class="nav-links" id="navLinks" aria-label="Principal">
      <?php foreach ($nav as $k => [$href, $txt]): ?>
        <a href="<?= e($href) ?>" class="<?= $activo === $k ? 'activo' : '' ?>"><?= e($txt) ?></a>
      <?php endforeach; ?>
      <?php if ($u): ?>
        <a href="<?= url($u['rol'] === 'admin' ? 'admin/' : 'cliente/') ?>" class="nav-cta-sec"><?= icono('user') ?> <?= $u['rol'] === 'admin' ? 'Panel' : 'Mi portal' ?></a>
      <?php else: ?>
        <a href="<?= url('ingresar.php') ?>" class="nav-cta-sec"><?= icono('user') ?> Ingresar</a>
      <?php endif; ?>
      <a href="<?= url('formulario.php') ?>" class="btn btn-rojo btn-sm nav-cta">Empieza hoy</a>
    </nav>
    <button class="nav-burger" id="navBurger" aria-label="Abrir menú" aria-expanded="false" aria-controls="navLinks"><?= icono('menu') ?></button>
  </div>
</header>
<?php endif; ?>
<?php $av = trim((string)ajuste('aviso_portada', '')); if ($av !== '' && empty($o['sin_nav'])): ?>
<div class="aviso-top"><?= e($av) ?></div>
<?php endif; ?>
<?php if ($tp): ?>
<aside class="saludo" id="saludo" data-clave="<?= e($tp['clave']) ?>" aria-label="<?= e($tp['titulo']) ?>">
  <div class="saludo-orn" aria-hidden="true"></div>
  <button class="saludo-cerrar" type="button" aria-label="Recoger saludo">×</button>
  <p class="saludo-titulo"><?= e($tp['titulo']) ?></p>
  <p class="saludo-texto"><?= e($tp['mensaje']) ?></p>
  <?php if ($tp['enlace']): ?><a class="saludo-btn" href="<?= e($tp['enlace']) ?>">Ver más</a><?php endif; ?>
  <span class="saludo-firma">— <?= e(ajuste('nombre_entrenador')) ?></span>
</aside>
<button class="saludo-pill" id="saludoPill" type="button" hidden><?= e($tp['titulo']) ?></button>
<?php endif; ?>
<main id="contenido">
<?php
    echo flashes();
}

function pie(array $o = []): void {
    $wa = ajuste('whatsapp');
    $ig = ajuste('instagram');
    $tt = ajuste('tiktok');
    $fb = ajuste('facebook');
    ?>
</main>
<?php if (empty($o['sin_pie'])): ?>
<footer class="pie">
  <div class="pie-in">
    <div class="pie-marca">
      <img src="<?= imagen_marca('logo') ?>" alt="<?= e(ajuste('marca')) ?>" width="120" height="93" loading="lazy">
      <p><?= e(ajuste('eslogan')) ?></p>
      <div class="redes">
        <?php if ($ig): ?><a href="https://instagram.com/<?= e($ig) ?>" target="_blank" rel="noopener" aria-label="Instagram" data-ev="red:instagram"><?= icono('instagram') ?></a><?php endif; ?>
        <?php if ($tt): ?><a href="https://www.tiktok.com/@<?= e(ltrim($tt, '@')) ?>" target="_blank" rel="noopener" aria-label="TikTok" data-ev="red:tiktok"><?= icono('tiktok') ?></a><?php endif; ?>
        <?php if ($fb): ?><a href="<?= e($fb) ?>" target="_blank" rel="noopener" aria-label="Facebook" data-ev="red:facebook"><?= icono('facebook') ?></a><?php endif; ?>
        <?php if ($wa): ?><a href="<?= e(wa_link($wa, ajuste('whatsapp_texto'))) ?>" target="_blank" rel="noopener" aria-label="WhatsApp" data-ev="whatsapp:pie"><?= icono('whatsapp') ?></a><?php endif; ?>
      </div>
    </div>
    <div>
      <h4>Programa</h4>
      <a href="<?= url('#planes') ?>">Planes y precios</a>
      <a href="<?= url('formulario.php') ?>">Formulario inicial</a>
      <a href="<?= url('catalogo.php') ?>">Catálogo</a>
      <a href="<?= url('pagar.php') ?>">Pagar / subir comprobante</a>
    </div>
    <div>
      <h4>Herramientas</h4>
      <a href="<?= url('cronometro.php') ?>">Cronómetro de entreno</a>
      <a href="<?= url('maquinas.php') ?>">Biblioteca de máquinas</a>
      <a href="<?= url('#calculadoras') ?>">Calculadoras</a>
      <a href="<?= url('aprende.php') ?>">Aprende</a>
      <?php if (tienda_activa()): ?><a href="<?= url('tienda.php') ?>">Tienda</a><?php endif; ?>
    </div>
    <div>
      <h4>Contacto</h4>
      <?php if ($wa): ?><a href="<?= e(wa_link($wa, ajuste('whatsapp_texto'))) ?>" target="_blank" rel="noopener" data-ev="whatsapp:pie">WhatsApp <?= e(ajuste('pago_nequi') ?: $wa) ?></a><?php endif; ?>
      <a href="mailto:<?= e(ajuste('email')) ?>"><?= e(ajuste('email')) ?></a>
      <?php if (ajuste('ciudad')): ?><span><?= e(ajuste('ciudad')) ?></span><?php endif; ?>
      <span><?= e(ajuste('horario')) ?></span>
      <a href="<?= url('ingresar.php') ?>">Ingresar a mi portal</a>
    </div>
  </div>
  <div class="pie-legal">
    <span>© <?= date('Y') ?> <?= e(ajuste('marca')) ?> · <?= e(ajuste('nombre_entrenador')) ?></span>
    <span><a href="<?= url('privacidad.php') ?>">Tratamiento de datos y términos</a> · <a href="<?= url('admin/') ?>">Panel del entrenador</a></span>
  </div>
</footer>
<?php endif; ?>
<?php if ($wa && empty($o['sin_wa'])): ?>
<a class="wa-flotante" href="<?= e(wa_link($wa, ajuste('whatsapp_texto'))) ?>" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp" data-ev="whatsapp:flotante"><?= icono('whatsapp') ?><span>¿Hablamos?</span></a>
<?php endif; ?>
<?php $tp = empty($o['sin_pie']) ? temporada_activa() : null; if ($tp): ?><script>window.BJ_TEMP = <?= json_encode(['clave' => $tp['clave'], 'efectos' => $tp['efectos']]) ?>;</script><script src="<?= asset('js/temporada.js') ?>" defer></script><?php endif; ?>
<?php if (tienda_activa() || basename($_SERVER['SCRIPT_NAME'] ?? '') === 'tienda.php'): ?>
<button class="carrito-boton" id="carritoBoton" type="button" data-modal="modalCarrito"><?= icono('money') ?> Carrito <em id="carritoN">0</em></button>
<div class="modal" id="modalCarrito" role="dialog" aria-modal="true" aria-label="Carrito"><div class="modal-caja">
  <button class="modal-cerrar" type="button" aria-label="Cerrar"><?= icono('x') ?></button>
  <h3 class="titulo" style="font-size:34px">Tu carrito</h3>
  <div id="carritoLista"></div>
  <a class="btn btn-rojo btn-bloque mt1" href="<?= url('tienda.php?pedido=1') ?>"><?= icono('send') ?> Finalizar pedido</a>
</div></div>
<?php endif; ?>
<?php if (empty($o['sin_crono'])): ?>
<button class="crono-flotante" id="cronoFlotante" type="button" aria-label="Abrir cronómetro" title="Cronómetro" data-url="<?= url('cronometro.php?mini=1') ?>" data-completo="<?= url('cronometro.php') ?>"><?= icono('timer') ?><span id="cronoPill"></span></button>
<?php endif; ?>
<script src="<?= asset('js/site.js') ?>" defer></script>
<?php if (!empty($o['scripts'])) echo $o['scripts']; ?>
</body>
</html>
<?php
}

/** Markdown mínimo para los artículos (##, ###, listas, **negrilla**, >, enlaces). */
function md(string $t): string {
    $t = str_replace("\r\n", "\n", $t);
    $html = '';
    $bloques = preg_split("/\n{2,}/", trim($t));
    $inline = function (string $s): string {
        $s = e($s);
        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
        $s = preg_replace('/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/s', '<em>$1</em>', $s);
        $s = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[^)\s]*)\)/', '<a href="$2" rel="noopener">$1</a>', $s);
        return nl2br($s, false);
    };
    foreach ($bloques as $b) {
        $lin = explode("\n", $b);
        if (preg_match('/^(#{2,3})\s+(.*)$/', $b, $m) && count($lin) === 1) {
            $n = strlen($m[1]);
            $html .= "<h$n>" . $inline($m[2]) . "</h$n>";
        } elseif (preg_match('/^[-*]\s/', $lin[0])) {
            $html .= '<ul>' . implode('', array_map(fn($l) => '<li>' . $inline(preg_replace('/^[-*]\s+/', '', $l)) . '</li>', $lin)) . '</ul>';
        } elseif (preg_match('/^\d+\.\s/', $lin[0])) {
            $html .= '<ol>' . implode('', array_map(fn($l) => '<li>' . $inline(preg_replace('/^\d+\.\s+/', '', $l)) . '</li>', $lin)) . '</ol>';
        } elseif (str_starts_with($b, '>')) {
            $html .= '<blockquote>' . $inline(preg_replace('/^>\s?/m', '', $b)) . '</blockquote>';
        } else {
            $html .= '<p>' . $inline($b) . '</p>';
        }
    }
    return $html;
}

/** Mapa del cuerpo (frente y espalda) con los músculos trabajados resaltados. */
function mapa_musculos(string $musculos, string $cls = ''): string {
    $act = array_flip(array_map('trim', explode(',', $musculos)));
    $c = fn(string $m) => isset($act[$m]) ? 'm on' : 'm';
    $f = '<g class="cuerpo">'
        . '<circle cx="50" cy="16" r="10"/><rect x="45" y="25" width="10" height="7" rx="2"/>'
        . '</g>'
        . '<path class="' . $c('hombros') . '" d="M30 36c-6 1-9 6-9 12l8 1 4-10zM70 36c6 1 9 6 9 12l-8 1-4-10z"/>'
        . '<path class="' . $c('pecho') . '" d="M33 35h16v18c-7 1-13 0-17-4zM51 35h16l1 14c-4 4-10 5-17 4z"/>'
        . '<path class="' . $c('biceps') . '" d="M21 50l8 1-1 15h-7zM79 50l-8 1 1 15h7z"/>'
        . '<path class="' . $c('antebrazos') . '" d="M21 68h7l-2 17h-5zM79 68h-7l2 17h5z"/>'
        . '<path class="' . $c('abdomen') . '" d="M40 55h20v26H40z"/>'
        . '<path class="' . $c('oblicuos') . '" d="M33 53l6 2v24l-5-2zM67 53l-6 2v24l5-2z"/>'
        . '<path class="' . $c('cuadriceps') . '" d="M34 84h15l-2 34h-11zM51 84h15l-2 34H53z"/>'
        . '<path class="' . $c('pantorrillas') . '" d="M37 122h9l-1 26h-7zM54 122h9l-1 26h-7z"/>';
    $b = '<g class="cuerpo"><circle cx="150" cy="16" r="10"/><rect x="145" y="25" width="10" height="7" rx="2"/></g>'
        . '<path class="' . $c('trapecio') . '" d="M140 32h20l8 6-18 6-18-6z"/>'
        . '<path class="' . $c('hombros') . '" d="M130 36c-6 1-9 6-9 12l8 1 4-10zM170 36c6 1 9 6 9 12l-8 1-4-10z"/>'
        . '<path class="' . $c('dorsales') . '" d="M133 40l16 6v22l-12-4zM167 40l-16 6v22l12-4z"/>'
        . '<path class="' . $c('triceps') . '" d="M121 50l8 1-1 15h-7zM179 50l-8 1 1 15h7z"/>'
        . '<path class="' . $c('antebrazos') . '" d="M121 68h7l-2 17h-5zM179 68h-7l2 17h5z"/>'
        . '<path class="' . $c('lumbar') . '" d="M138 66l11 4v11h-12zM162 66l-11 4v11h12z"/>'
        . '<path class="' . $c('gluteos') . '" d="M134 83h15v16c-7 1-12-2-15-6zM151 83h15v10c-3 4-8 7-15 6z"/>'
        . '<path class="' . $c('isquios') . '" d="M135 101h14l-2 19h-11zM151 101h14l-1 19h-11z"/>'
        . '<path class="' . $c('pantorrillas') . '" d="M137 122h10l-1 26h-8zM153 122h10l-1 26h-8z"/>';
    return '<svg class="mapa-musculos ' . e($cls) . '" viewBox="0 0 200 152" role="img" aria-label="Músculos que trabaja: ' . e(str_replace(',', ', ', $musculos)) . '">' . $f . $b
        . '<text x="50" y="151" text-anchor="middle">FRENTE</text><text x="150" y="151" text-anchor="middle">ESPALDA</text></svg>';
}

function nombre_musculo(string $m): string {
    return ['cuadriceps' => 'Cuádriceps', 'gluteos' => 'Glúteos', 'isquios' => 'Isquiotibiales', 'pantorrillas' => 'Pantorrillas', 'abdomen' => 'Abdomen',
        'oblicuos' => 'Oblicuos', 'pecho' => 'Pecho', 'hombros' => 'Hombros', 'biceps' => 'Bíceps', 'triceps' => 'Tríceps', 'antebrazos' => 'Antebrazos',
        'dorsales' => 'Dorsales', 'trapecio' => 'Trapecio', 'lumbar' => 'Lumbar'][$m] ?? ucfirst($m);
}

/** Portada de una máquina sin foto: tarjeta tipográfica con el mapa muscular. */
function portada_video(array $v, string $cls = ''): string {
    if (!empty($v['imagen'])) return '<img class="' . e($cls) . '" src="' . e(img_publica($v['imagen'])) . '" alt="' . e($v['titulo']) . '" loading="lazy">';
    return '<div class="portada-gen ' . e($cls) . '"><span class="pg-cat">' . e($v['categoria']) . '</span>' . mapa_musculos((string)$v['musculos']) . '</div>';
}

/** Tarjeta de un producto de la tienda (con botón para agregar al carrito). */
function tarjeta_producto(array $p): string {
    $img = $p['imagen'] ? '<img src="' . e(img_publica($p['imagen'])) . '" alt="' . e($p['nombre']) . '" loading="lazy">'
        : '<div class="producto-gen">' . icono('flame') . '<span>' . e($p['categoria'] ?: 'Producto') . '</span></div>';
    $agotado = $p['stock'] !== null && $p['stock'] !== '' && (int)$p['stock'] <= 0;
    $datos = json_encode(['id' => (int)$p['id'], 'nombre' => $p['nombre'], 'precio' => (float)$p['precio']], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    return '<article class="producto" data-cat="' . e($p['categoria']) . '">'
        . '<div class="producto-img">' . $img . ($p['destacado'] ? '<span class="chip chip-rojo producto-tag">Recomendado</span>' : '') . '</div>'
        . '<div class="producto-cuerpo"><span class="sec">' . e(trim($p['marca'] . ' · ' . $p['presentacion'], ' ·')) . '</span><h3>' . e($p['nombre']) . '</h3>'
        . ($p['descripcion'] ? '<p>' . e(mb_strimwidth((string)$p['descripcion'], 0, 140, '…')) . '</p>' : '')
        . '<div class="producto-pie"><div><b>' . pesos($p['precio']) . '</b>' . ((float)$p['precio_antes'] > (float)$p['precio'] ? ' <s>' . pesos($p['precio_antes']) . '</s>' : '') . '</div>'
        . ($agotado ? '<span class="chip">Agotado</span>' : '<button class="btn btn-rojo btn-sm" type="button" data-agregar-carrito=\'' . $datos . '\'>' . icono('plus') . ' Agregar</button>')
        . '</div></div></article>';
}
