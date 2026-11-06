<?php
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
$vids = filas('SELECT * FROM videos WHERE visible = 1 ORDER BY orden, id');
$cats = array_values(array_unique(array_filter(array_column($vids, 'categoria'))));
$u = usuario();
$bib = fila("SELECT * FROM planes WHERE tipo = 'videos' AND visible = 1 ORDER BY orden LIMIT 1");
$paquetes = filas('SELECT * FROM paquetes WHERE visible = 1 ORDER BY orden, id');
cabecera('Biblioteca de máquinas', ['activo' => 'maquinas', 'body' => 'pagina', 'desc' => 'Aprende a usar cada máquina del gimnasio: video, pasos, errores comunes y la carga sugerida para tu peso.']);
?>
<section class="cabeza-pag">
  <div class="envoltura">
    <div class="migas"><a href="<?= url('') ?>">Inicio</a> / Máquinas</div>
    <h1 class="titulo">Biblioteca de <span class="marca">máquinas</span></h1>
    <p class="sub">Cada máquina con su video, los pasos para usarla, los errores que debes evitar y la carga con la que deberías empezar según tu peso.</p>
    <?php if ($bib && (float)$bib['precio'] > 0): ?>
      <div class="hero-ctas mt2"><a class="btn btn-rojo" href="<?= url('pagar.php?plan=' . urlencode($bib['slug'])) ?>"><?= icono('unlock') ?> Desbloquear todo por <?= pesos($bib['precio']) ?></a><span class="chip"><?= e($bib['frecuencia']) ?></span></div>
    <?php endif; ?>
  </div>
</section>
<section class="seccion" style="padding-top:40px">
  <div class="envoltura">
    <?php if ($paquetes): ?>
    <h2 class="titulo" style="font-size:clamp(32px,4.5vw,48px)">Paquetes <span class="marca">de rutina</span></h2>
    <p class="sub" style="font-size:15px">Todos los videos de una rutina completa, con la rutina lista en tu portal y el cronómetro guiado.</p>
    <div class="paquetes mt1">
      <?php foreach ($paquetes as $pq): $vs = filas('SELECT v.* FROM paquete_videos pv JOIN videos v ON v.id = pv.video_id WHERE pv.paquete_id = ? ORDER BY pv.orden', [$pq['id']]); $todos = $vs && !array_filter($vs, fn($v) => !puede_ver_video($u, $v)); ?>
        <div class="paquete" id="paquete-<?= e($pq['slug']) ?>">
          <span class="chip chip-volt"><?= icono('video') ?> <?= count($vs) ?> videos + rutina · <?= (int)$pq['dias_acceso'] ?> días</span>
          <h3><?= e($pq['nombre']) ?></h3>
          <p><?= e($pq['descripcion']) ?></p>
          <ul class="paquete-lista"><?php foreach ($vs as $v): ?><li><?= icono(puede_ver_video($u, $v) ? 'unlock' : 'play') ?><a href="<?= url('maquina.php?s=' . urlencode($v['slug'])) ?>" style="color:inherit"><?= e($v['titulo']) ?></a></li><?php endforeach; ?></ul>
          <div class="paquete-precio"><b><?= pesos($pq['precio']) ?></b><?php if ((float)$pq['precio_antes'] > (float)$pq['precio']): ?><s><?= pesos($pq['precio_antes']) ?></s><?php endif; ?>
            <?= $todos ? '<span class="chip chip-ok">' . icono('unlock') . ' Ya lo tienes</span>' : '<a class="btn btn-rojo btn-sm" href="' . url('pagar.php?paquete=' . urlencode($pq['slug'])) . '" data-ev="pago:paquete-' . e($pq['slug']) . '">Comprar paquete</a>' ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <h2 class="titulo mt3" style="font-size:clamp(32px,4.5vw,48px)">Todas las <span class="marca">máquinas</span></h2>
    <?php endif; ?>
    <div class="filtros" id="filtros">
      <button type="button" class="on" data-cat="">Todas</button>
      <?php foreach ($cats as $c): ?><button type="button" data-cat="<?= e($c) ?>"><?= e($c) ?></button><?php endforeach; ?>
      <input type="search" id="buscar" placeholder="Buscar máquina o músculo..." aria-label="Buscar">
    </div>
    <div class="maquinas" id="lista">
      <?php foreach ($vids as $m): $ok = puede_ver_video($u, $m); ?>
        <a class="maq" href="<?= url('maquina.php?s=' . urlencode($m['slug'])) ?>" data-cat="<?= e($m['categoria']) ?>" data-txt="<?= e(mb_strtolower($m['titulo'] . ' ' . $m['musculos'] . ' ' . $m['categoria'])) ?>">
          <div class="maq-img">
            <span class="maq-estado"><?= $m['gratis'] ? '<span class="chip chip-ok">' . icono('play') . ' Gratis</span>' : ($ok ? '<span class="chip chip-volt">' . icono('unlock') . ' Desbloqueado</span>' : '<span class="chip chip-rojo">' . icono('lock') . ' Premium</span>') ?></span>
            <?= portada_video($m) ?>
          </div>
          <div class="maq-cuerpo">
            <h3><?= e($m['titulo']) ?></h3>
            <span class="musc"><?= e(implode(' · ', array_map('nombre_musculo', array_filter(explode(',', (string)$m['musculos']))))) ?></span>
            <div class="maq-pie"><span><?= ($m['gratis'] || $ok) ? 'Ver video' : ((float)$m['precio'] > 0 ? pesos($m['precio']) : 'Con tu plan') ?></span><?= icono('arrow') ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <p id="sinRes" class="sub oculto centro mt3">No encontramos esa máquina. Escríbele a Bryan y la agrega a la biblioteca.</p>
  </div>
</section>
<script>
(function () {
  var cat = '', txt = '', items = document.querySelectorAll('#lista .maq');
  function filtrar() {
    var n = 0;
    items.forEach(function (it) {
      var ok = (!cat || it.dataset.cat === cat) && (!txt || it.dataset.txt.indexOf(txt) >= 0);
      it.style.display = ok ? '' : 'none'; if (ok) n++;
    });
    document.getElementById('sinRes').classList.toggle('oculto', n > 0);
  }
  document.querySelectorAll('#filtros button').forEach(function (b) {
    b.addEventListener('click', function () { document.querySelectorAll('#filtros button').forEach(function (x) { x.classList.toggle('on', x === b); }); cat = b.dataset.cat; filtrar(); });
  });
  document.getElementById('buscar').addEventListener('input', function (e) { txt = e.target.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); filtrar(); });
  items.forEach(function (it) { it.dataset.txt = it.dataset.txt.normalize('NFD').replace(/[̀-ͯ]/g, ''); });
})();
</script>
<?php pie(); ?>
