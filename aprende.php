<?php
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
$arts = filas("SELECT * FROM contenidos WHERE tipo = 'articulo' AND visible = 1 ORDER BY orden, id DESC");
$cats = array_values(array_unique(array_filter(array_column($arts, 'categoria'))));
cabecera('Aprende', ['activo' => 'aprende', 'body' => 'pagina', 'desc' => 'Guías de entrenamiento, nutrición y hábitos de ' . ajuste('marca') . '.']);
?>
<section class="cabeza-pag">
  <div class="envoltura">
    <div class="migas"><a href="<?= url('') ?>">Inicio</a> / Aprende</div>
    <h1 class="titulo">Entrena con <span class="marca">conocimiento</span></h1>
    <p class="sub">Lo que necesitas saber sobre entrenamiento, nutrición, descanso y mentalidad, explicado sin complicaciones.</p>
  </div>
</section>
<section class="seccion" style="padding-top:40px">
  <div class="envoltura">
    <div class="filtros" id="filtros"><button type="button" class="on" data-cat="">Todo</button><?php foreach ($cats as $c): ?><button type="button" data-cat="<?= e($c) ?>"><?= e($c) ?></button><?php endforeach; ?></div>
    <div class="rejilla-3" id="lista">
      <?php foreach ($arts as $a): ?>
        <a class="art" data-cat="<?= e($a['categoria']) ?>" href="<?= url('articulo.php?s=' . urlencode($a['slug'])) ?>">
          <div class="art-img"><?= $a['imagen'] ? '<img src="' . e(img_publica($a['imagen'])) . '" alt="" loading="lazy">' : '<div class="art-gen"><span>' . e($a['categoria']) . '</span></div>' ?></div>
          <div class="art-cuerpo"><span class="chip" style="margin-bottom:10px"><?= e($a['categoria']) ?></span><h3><?= e($a['titulo']) ?></h3><p><?= e($a['resumen']) ?></p></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<script>
document.querySelectorAll('#filtros button').forEach(function (b) {
  b.addEventListener('click', function () {
    document.querySelectorAll('#filtros button').forEach(function (x) { x.classList.toggle('on', x === b); });
    document.querySelectorAll('#lista .art').forEach(function (a) { a.style.display = (!b.dataset.cat || a.dataset.cat === b.dataset.cat) ? '' : 'none'; });
  });
});
</script>
<?php pie(); ?>
