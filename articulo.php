<?php
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
$a = fila("SELECT * FROM contenidos WHERE tipo = 'articulo' AND slug = ? AND visible = 1", [(string)($_GET['s'] ?? '')]);
if (!$a) { http_response_code(404); cabecera('No encontrado', ['body' => 'pagina']); echo '<section class="cabeza-pag"><div class="envoltura"><h1 class="titulo">Artículo no encontrado</h1><a class="btn btn-rojo" href="' . url('aprende.php') . '">Ver artículos</a></div></section>'; pie(); exit; }
q('UPDATE contenidos SET vistas = vistas + 1 WHERE id = ?', [$a['id']]);
$mas = filas("SELECT * FROM contenidos WHERE tipo = 'articulo' AND visible = 1 AND id <> ? ORDER BY orden, id DESC LIMIT 3", [$a['id']]);
$wa = ajuste('whatsapp');
cabecera($a['titulo'], ['activo' => 'aprende', 'body' => 'pagina', 'desc' => $a['resumen']]);
?>
<section class="cabeza-pag" style="padding-bottom:40px">
  <div class="envoltura" style="max-width:860px">
    <div class="migas"><a href="<?= url('') ?>">Inicio</a> / <a href="<?= url('aprende.php') ?>">Aprende</a> / <?= e($a['categoria']) ?></div>
    <h1 class="titulo" style="font-size:clamp(38px,5.5vw,64px);text-transform:none;font-family:var(--cuerpo);font-weight:800;letter-spacing:-.02em"><?= e($a['titulo']) ?></h1>
    <p class="sub"><?= e($a['resumen']) ?></p>
    <p style="color:var(--txt-3);font-size:14px">Por <?= e(ajuste('nombre_entrenador')) ?> · <?= fecha_larga($a['creado']) ?></p>
  </div>
</section>
<section class="seccion" style="padding-top:30px">
  <div class="envoltura">
    <?php if ($a['imagen']): ?><img src="<?= e(img_publica($a['imagen'])) ?>" alt="" style="max-width:860px;width:100%;margin:0 auto 40px;border-radius:22px"><?php endif; ?>
    <article class="articulo"><?= md((string)$a['cuerpo']) ?></article>
    <div class="caja mt3" style="max-width:760px;margin-inline:auto;text-align:center">
      <h3 class="titulo" style="font-size:36px">¿Quieres un plan hecho para ti?</h3>
      <p class="sub" style="margin-inline:auto">Empieza con la valoración y el formulario inicial. Bryan diseña tu programa con base en tus respuestas.</p>
      <div class="hero-ctas" style="justify-content:center;margin:0">
        <a class="btn btn-rojo" href="<?= url('formulario.php') ?>">Empezar ahora</a>
        <a class="btn btn-borde" href="<?= e(wa_link($wa, 'Hola Bryan, leí tu artículo "' . $a['titulo'] . '" y quiero más información.')) ?>" target="_blank" rel="noopener" data-ev="whatsapp:articulo"><?= icono('whatsapp') ?> WhatsApp</a>
      </div>
    </div>
  </div>
</section>
<?php if ($mas): ?>
<section class="seccion seccion-oscura" style="padding:70px 0">
  <div class="envoltura"><h2 class="titulo" style="font-size:44px">Sigue aprendiendo</h2>
    <div class="rejilla-3 mt2"><?php foreach ($mas as $m): ?>
      <a class="art" href="<?= url('articulo.php?s=' . urlencode($m['slug'])) ?>"><div class="art-img"><?= $m['imagen'] ? '<img src="' . e(img_publica($m['imagen'])) . '" alt="" loading="lazy">' : '<div class="art-gen"><span>' . e($m['categoria']) . '</span></div>' ?></div><div class="art-cuerpo"><h3><?= e($m['titulo']) ?></h3><p><?= e($m['resumen']) ?></p></div></a>
    <?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>
<?php pie(); ?>
