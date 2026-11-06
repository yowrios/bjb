<?php
/** Catálogo vivo: se arma con los planes y precios actuales. Se imprime o se guarda en PDF desde el navegador. */
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
$planes = filas("SELECT * FROM planes WHERE visible = 1 ORDER BY orden, id");
cabecera('Catálogo de planes', ['body' => 'pagina', 'desc' => 'Planes y precios vigentes del programa Tu Evolución.']);
?>
<section class="cabeza-pag" style="padding-bottom:30px">
  <div class="envoltura" style="display:flex;justify-content:space-between;gap:20px;align-items:flex-end;flex-wrap:wrap">
    <div>
      <img src="<?= imagen_marca('logo') ?>" alt="" width="110" style="margin-bottom:16px">
      <h1 class="titulo">Programa <span class="marca">Tu Evolución</span></h1>
      <p class="sub"><?= e(ajuste('hero_texto')) ?></p>
    </div>
    <div class="no-imprimir" style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-rojo" type="button" onclick="window.print()"><?= icono('print') ?> Imprimir / guardar PDF</button>
      <a class="btn btn-wa" href="https://wa.me/?text=<?= rawurlencode('Mira los planes de ' . ajuste('marca') . ': ' . ((!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . url('catalogo.php'))) ?>" target="_blank" rel="noopener"><?= icono('whatsapp') ?> Compartir</a>
    </div>
  </div>
</section>
<section class="seccion" style="padding-top:20px">
  <div class="envoltura">
    <div class="planes">
      <?php foreach ($planes as $p): ?>
        <article class="plan <?= $p['destacado'] ? 'destacado' : '' ?>">
          <span class="plan-etiqueta"><?= e($p['etiqueta'] ?: 'Programa') ?></span>
          <h3><?= e($p['nombre']) ?></h3>
          <p class="plan-sub"><?= e($p['descripcion']) ?></p>
          <div class="plan-precio"><b><?= (float)$p['precio'] > 0 ? pesos($p['precio']) : 'Consúltalo' ?></b></div>
          <div class="plan-meta"><?php if ($p['sesiones']): ?><span class="chip"><?= (int)$p['sesiones'] ?> sesiones</span><?php endif; ?><?php if ($p['frecuencia']): ?><span class="chip"><?= e($p['frecuencia']) ?></span><?php endif; ?></div>
          <ul><?php foreach (lineas($p['incluye']) as $l): ?><li><?= icono('check') ?><span><?= e($l) ?></span></li><?php endforeach; ?></ul>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="dos-col mt3">
      <div class="tarjeta">
        <h3>Medios de pago</h3>
        <?php if (ajuste('pago_nequi')): ?><p><b>Nequi:</b> <?= e(ajuste('pago_nequi')) ?></p><?php endif; ?>
        <?php if (ajuste('pago_daviplata')): ?><p><b>Daviplata:</b> <?= e(ajuste('pago_daviplata')) ?></p><?php endif; ?>
        <?php if (ajuste('pago_cuenta')): ?><p><b><?= e(ajuste('pago_banco')) ?>:</b> <?= e(ajuste('pago_cuenta_tipo')) ?> <?= e(ajuste('pago_cuenta')) ?><br>Titular: <?= e(ajuste('pago_titular')) ?></p><?php endif; ?>
        <p style="color:var(--txt-2);font-size:14px">Envía tu comprobante o súbelo en la página: <?= e(url('pagar.php')) ?></p>
      </div>
      <div class="tarjeta">
        <h3><?= e(ajuste('nombre_entrenador')) ?></h3>
        <p style="color:var(--txt-2)">Profesional en ciencias del deporte y la educación física.</p>
        <p><b>WhatsApp:</b> <?= e(ajuste('whatsapp')) ?><br><b>Correo:</b> <?= e(ajuste('email')) ?><?php if (ajuste('instagram')): ?><br><b>Instagram:</b> @<?= e(ajuste('instagram')) ?><?php endif; ?></p>
        <p style="color:var(--txt-3);font-size:13px">Precios vigentes al <?= fecha_larga(hoy()) ?>.</p>
      </div>
    </div>
  </div>
</section>
<?php pie(); ?>
