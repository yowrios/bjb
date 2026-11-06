<?php
/** Tarjeta de presentación (PDF), QR y afiche para compartir la web. */
$destinos = [
    'tarjeta.php' => 'Mi tarjeta digital (recomendado): botones de WhatsApp, planes, Instagram y «Guardar contacto»',
    '' => 'La página principal',
    '#planes' => 'Los planes y precios',
    'formulario.php' => 'El formulario inicial',
    'cronometro.php' => 'El cronómetro de entreno',
    'maquinas.php' => 'La biblioteca de máquinas',
];
$d = array_key_exists($_GET['d'] ?? 'tarjeta.php', $destinos) ? ($_GET['d'] ?? 'tarjeta.php') : 'tarjeta.php';
$campana = $d === '' ? 'inicio' : (trim(preg_replace('/\W+/', '-', str_replace('.php', '', $d)), '-') ?: 'inicio');
$enlace = str_starts_with($d, '#') ? url_publica('?utm_source=qr&utm_campaign=' . $campana . $d) : url_publica($d . '?utm_source=qr&utm_campaign=' . $campana);
$host = parse_url(url_publica(''), PHP_URL_HOST);
$local = in_array($host, ['127.0.0.1', 'localhost'], true) || str_starts_with((string)$host, '192.168.');
[$color] = tema_colores();
$escaneos = (int)valor("SELECT COUNT(*) FROM visitas WHERE utm_source = 'qr'");
$datos = ['enlace' => $enlace, 'logo' => imagen_marca('logo'), 'color' => $color, 'nombre' => mb_strtoupper((string)ajuste('nombre_entrenador')),
    'marca' => (string)ajuste('marca'), 'lema' => mb_strtoupper((string)ajuste('eslogan')), 'rol' => 'Entrenador personal · Ciencias del deporte',
    'whatsapp' => (string)ajuste('whatsapp'), 'instagram' => (string)ajuste('instagram'), 'web' => preg_replace('~^https?://~', '', rtrim(url_publica(''), '/')),
    'lugar' => (string)(ajuste('gym_nombre') ?: ajuste('ciudad')), 'archivo' => slugify((string)ajuste('marca') . '-' . (string)ajuste('nombre_entrenador'))];
$tel = preg_replace('/^57(\d{3})(\d{3})(\d{4})$/', '$1 $2 $3', wa_numero($datos['whatsapp']));
admin_cabecera('tarjeta', 'Tarjeta de presentación y QR');
?>
<?php if ($local): ?>
  <div class="msg msg-error">Estás en pruebas: el QR apunta a <b><?= e($host) ?></b>, que solo funciona en este computador. Cuando tengas el dominio, escríbelo en <a href="<?= url('admin/?p=ajustes&s=contacto') ?>" style="color:#fff">Ajustes › Contacto › Dirección de tu web</a> y vuelve a descargar la tarjeta y el QR.</div>
<?php endif; ?>
<div class="ad-rejilla">
  <div class="ad-caja">
    <h2><?= icono('link') ?> ¿A dónde lleva el QR?</h2>
    <form method="get" class="campos" style="grid-template-columns:1fr"><input type="hidden" name="p" value="tarjeta">
      <div class="campo"><select name="d" onchange="this.form.submit()"><?php foreach ($destinos as $k => $t): ?><option value="<?= e($k) ?>" <?= $d === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
    </form>
    <div class="campo mt1"><label>Enlace</label><input readonly value="<?= e($enlace) ?>" onclick="this.select()"></div>
    <div class="ad-acciones mt1" style="justify-content:flex-start">
      <button class="btn btn-sm btn-borde" type="button" data-copiar="<?= e($enlace) ?>">Copiar enlace</button>
      <a class="btn btn-sm btn-wa" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode('Conoce mis planes de entrenamiento 💪 ' . $enlace) ?>"><?= icono('whatsapp') ?> Compartir por WhatsApp</a>
      <a class="btn btn-sm btn-borde" target="_blank" href="<?= e(url($d ?: '')) ?>"><?= icono('eye') ?> Abrir</a>
    </div>
    <p class="sec mt2">Personas que han llegado escaneando un QR: <b style="color:var(--txt)"><?= $escaneos ?></b> (se ven en Visitas como «Qr»).</p>
  </div>
  <div class="ad-caja centro">
    <h2 style="justify-content:center"><?= icono('target') ?> Tu código QR</h2>
    <div id="qrVista" style="display:inline-block;padding:14px;background:#fff;border-radius:14px"></div>
    <div class="ad-acciones mt2" style="justify-content:center"><button class="btn btn-rojo btn-sm" type="button" id="bQr"><?= icono('download') ?> Descargar QR (PNG)</button></div>
  </div>
</div>

<div class="ad-caja mt2">
  <h2><?= icono('user') ?> Tarjeta de presentación <span class="der">8,5 × 5,5 cm · frente y reverso</span></h2>
  <div class="tj-vista">
    <div class="tj" style="--c:<?= e($color) ?>">
      <img src="<?= e($datos['logo']) ?>" alt="">
      <div><b><?= e($datos['nombre']) ?></b><span class="tj-lema"><?= e($datos['lema']) ?></span><span><?= e($datos['rol']) ?></span>
        <small>WhatsApp <?= e($tel) ?><?= $datos['instagram'] ? '<br>@' . e($datos['instagram']) : '' ?><br><?= e($datos['web']) ?></small></div>
    </div>
    <div class="tj tj-reverso" style="--c:<?= e($color) ?>">
      <div class="tj-qr" id="qrTarjeta"></div>
      <div><b>ESCANÉAME</b><span>y entrena conmigo</span><small><?= e($datos['marca']) ?> · <?= e($datos['lema']) ?><?= $datos['lugar'] ? '<br>' . e($datos['lugar']) : '' ?></small></div>
    </div>
  </div>
  <div class="ad-acciones mt2" style="justify-content:flex-start">
    <button class="btn btn-rojo" type="button" id="bTarjeta"><?= icono('download') ?> Descargar tarjeta en PDF</button>
    <button class="btn btn-borde" type="button" id="bAfiche"><?= icono('print') ?> Descargar afiche con QR (A5)</button>
  </div>
  <p class="sec mt1">La tarjeta en PDF sirve para enviarla por WhatsApp o llevarla a imprimir (cualquier papelería imprime tarjetas de 8,5 × 5,5 cm, frente y reverso). El afiche es para pegarlo en el gimnasio o en la recepción. Si cambias el logo, los colores o tus datos, vuelve a descargarlos.</p>
</div>
<style>
.tj-vista { display: flex; gap: 18px; flex-wrap: wrap; }
.tj { width: 340px; max-width: 100%; aspect-ratio: 85/55; border-radius: 12px; background: #0a0a0c; border-left: 6px solid var(--c); display: flex; align-items: center; gap: 12px; padding: 14px; box-shadow: 0 14px 40px rgba(0,0,0,.6); color: #fff; }
.tj img { width: 44%; height: auto; }
.tj > div { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.tj b { font-family: var(--display); font-weight: 400; font-size: 20px; line-height: 1.05; letter-spacing: .02em; }
.tj .tj-lema { color: var(--c); font-size: 8px; font-weight: 800; letter-spacing: .16em; }
.tj span { font-size: 9.5px; color: #bbb; }
.tj small { font-size: 9px; color: #999; margin-top: 6px; line-height: 1.5; }
.tj-reverso .tj-qr { background: #fff; padding: 6px; border-radius: 8px; width: 44%; aspect-ratio: 1; flex: none; display: grid; place-items: center; }
.tj-reverso .tj-qr img, .tj-reverso .tj-qr canvas { width: 100% !important; height: auto !important; }
.tj-reverso b { font-size: 22px; }
</style>
<?php
$sc = '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>'
    . '<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>'
    . '<script>window.TJ = ' . json_encode($datos + ['tel' => $tel], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';</script>'
    . '<script src="' . asset('js/tarjeta.js') . '"></script>';
admin_pie($sc);
