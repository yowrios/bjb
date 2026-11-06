<?php
/** Tarjeta de presentación digital (la que se abre al escanear el QR). */
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
$wa = ajuste('whatsapp'); $ig = ajuste('instagram'); $tt = ltrim((string)ajuste('tiktok'), '@'); $fb = ajuste('facebook');
$nombre = ajuste('nombre_entrenador');
cabecera($nombre, ['sin_nav' => true, 'body' => 'tarjeta-digital', 'desc' => ajuste('marca') . ' · ' . ajuste('eslogan') . ' · Entrenador personal']);
$botones = [
    ['whatsapp', 'Escríbeme por WhatsApp', wa_link($wa, ajuste('whatsapp_texto')), 'btn-wa', 'whatsapp:tarjeta'],
    ['flame', 'Ver planes y precios', url('#planes'), 'btn-rojo', 'plan:tarjeta'],
    ['clipboard', 'Llenar formulario inicial', url('formulario.php'), 'btn-borde', ''],
    ['timer', 'Cronómetro de entreno', url('cronometro.php'), 'btn-borde', 'cronometro:tarjeta'],
];
?>
<style>
body.tarjeta-digital { background: radial-gradient(ellipse 80% 50% at 50% 0%, color-mix(in srgb, var(--rojo) 30%, transparent), transparent 70%), var(--negro); }
.td { max-width: 440px; margin: 0 auto; padding: 34px 18px 50px; text-align: center; }
.td-logo { width: 190px; height: auto; margin: 0 auto 6px; filter: drop-shadow(0 10px 30px rgba(0,0,0,.6)); }
.td h1 { font-family: var(--display); font-weight: 400; font-size: 38px; text-transform: uppercase; margin: 8px 0 2px; letter-spacing: .02em; }
.td .lema { font-weight: 800; letter-spacing: .24em; font-size: 12px; color: var(--rojo); margin: 0 0 6px; }
.td .rol { color: var(--txt-2); font-size: 14px; margin: 0 0 4px; }
.td .lugar { color: var(--txt-3); font-size: 13px; margin: 0 0 22px; }
.td-botones { display: grid; gap: 10px; }
.td-botones .btn { width: 100%; justify-content: center; padding: 16px; }
.td-redes { display: flex; justify-content: center; gap: 12px; margin: 22px 0; }
.td-redes a { width: 50px; height: 50px; border-radius: 50%; display: grid; place-items: center; background: var(--gris-2); color: var(--txt); }
.td-redes .ico { width: 22px; height: 22px; }
.td-extra { display: flex; gap: 10px; }
.td-extra .btn { flex: 1; font-size: 12px; padding: 12px 8px; }
</style>
<div class="td">
  <img class="td-logo" src="<?= imagen_marca('logo') ?>" alt="<?= e(ajuste('marca')) ?>">
  <h1><?= e($nombre) ?></h1>
  <?php if (ajuste('eslogan')): ?><p class="lema"><?= e(mb_strtoupper(ajuste('eslogan'))) ?></p><?php endif; ?>
  <p class="rol">Entrenador personal · Ciencias del deporte</p>
  <?php if (ajuste('gym_nombre') || ajuste('ciudad')): ?><p class="lugar"><?= e(ajuste('gym_nombre') ?: ajuste('ciudad')) ?></p><?php endif; ?>
  <div class="td-botones">
    <?php foreach ($botones as [$ic, $txt, $href, $cls, $ev]): ?><a class="btn <?= $cls ?>" href="<?= e($href) ?>" <?= str_starts_with($href, 'http') ? 'target="_blank" rel="noopener"' : '' ?> <?= $ev ? 'data-ev="' . e($ev) . '"' : '' ?>><?= icono($ic) ?> <?= e($txt) ?></a><?php endforeach; ?>
  </div>
  <div class="td-redes">
    <?php if ($ig): ?><a href="https://instagram.com/<?= e($ig) ?>" target="_blank" rel="noopener" aria-label="Instagram" data-ev="red:instagram"><?= icono('instagram') ?></a><?php endif; ?>
    <?php if ($tt): ?><a href="https://www.tiktok.com/@<?= e($tt) ?>" target="_blank" rel="noopener" aria-label="TikTok" data-ev="red:tiktok"><?= icono('tiktok') ?></a><?php endif; ?>
    <?php if ($fb): ?><a href="<?= e($fb) ?>" target="_blank" rel="noopener" aria-label="Facebook" data-ev="red:facebook"><?= icono('facebook') ?></a><?php endif; ?>
    <a href="tel:+<?= e(wa_numero($wa)) ?>" aria-label="Llamar"><?= icono('telefono') ?></a>
    <?php if (ajuste('email')): ?><a href="mailto:<?= e(ajuste('email')) ?>" aria-label="Correo"><?= icono('send') ?></a><?php endif; ?>
  </div>
  <div class="td-extra">
    <a class="btn btn-borde" href="<?= url('vcard.php') ?>"><?= icono('user') ?> Guardar contacto</a>
    <button class="btn btn-borde" type="button" id="tdCompartir"><?= icono('link') ?> Compartir</button>
  </div>
  <p class="mt3"><a href="<?= url('') ?>" style="color:var(--txt-2);font-size:14px">Ver la página completa →</a></p>
</div>
<script>
document.getElementById('tdCompartir').addEventListener('click', function () {
  var d = { title: document.title, text: <?= json_encode($nombre . ' · ' . ajuste('marca') . ' · Entrenador personal') ?>, url: location.href.split('?')[0] };
  if (navigator.share) navigator.share(d).catch(function () {});
  else if (navigator.clipboard) navigator.clipboard.writeText(d.url).then(function () { alert('Enlace copiado'); });
});
</script>
<?php pie(['sin_pie' => true, 'sin_crono' => true, 'sin_wa' => true]); ?>
