<?php
/** Propuesta personalizada que Bryan envía por enlace. Registra cuándo se abre y si la aceptan. */
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
iniciar_sesion();
$t = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''));
$p = $t ? fila('SELECT * FROM propuestas WHERE token = ?', [$t]) : null;
if (!$p) { http_response_code(404); cabecera('Propuesta', ['body' => 'pagina', 'sin_visita' => true]); echo '<section class="cabeza-pag"><div class="envoltura"><h1 class="titulo">Propuesta no encontrada</h1><p class="sub">Revisa el enlace o escríbele a Bryan.</p></div></section>'; pie(); exit; }
$vencida = $p['vence'] && $p['vence'] < hoy();
if (es_post() && !$vencida) {
    csrf_verificar();
    if (($_POST['acc'] ?? '') === 'aceptar' && $p['estado'] !== 'aceptada') {
        q("UPDATE propuestas SET estado = 'aceptada' WHERE id = ?", [$p['id']]);
        evento('propuesta', 'aceptada', $p['nombre']);
        if ($p['lead_id']) q("UPDATE leads SET estado = 'contactado', actualizado = ? WHERE id = ? AND estado = 'nuevo'", [ahora(), $p['lead_id']]);
        redirigir(wa_link(ajuste('whatsapp'), 'Hola Bryan, soy ' . $p['nombre'] . '. ¡Acepto la propuesta! ¿Cómo seguimos?'));
    }
}
if (!es_admin()) {
    q('UPDATE propuestas SET vistas = vistas + 1, ultima_vista = ? WHERE id = ?', [ahora(), $p['id']]);
    if ((int)$p['vistas'] === 0) evento('propuesta', 'abierta', $p['nombre']);
}
$items = json_decode((string)$p['items'], true) ?: [];
cabecera('Propuesta para ' . $p['nombre'], ['body' => 'pagina', 'desc' => 'Propuesta personalizada de ' . ajuste('marca')]);
?>
<section class="seccion" style="padding-top:110px">
  <div class="envoltura">
    <div class="propuesta-hoja">
      <div class="propuesta-top">
        <div><span class="antetitulo">Propuesta personalizada</span><h1 class="titulo" style="font-size:clamp(40px,6vw,70px)">Para <?= e($p['nombre']) ?></h1>
          <p style="margin:0;color:#d4d4d8">Preparada por <?= e(ajuste('nombre_entrenador')) ?> · <?= fecha_larga(substr($p['creado'], 0, 10)) ?></p></div>
        <img src="<?= imagen_marca('logo') ?>" alt="" width="120">
      </div>
      <div class="propuesta-cuerpo">
        <?php if ($p['objetivo']): ?><h3>Tu objetivo</h3><p style="color:#d4d4d8"><?= nl2br(e($p['objetivo'])) ?></p><?php endif; ?>
        <?php if ($p['mensaje']): ?><div class="articulo" style="font-size:17px;max-width:none"><?= md((string)$p['mensaje']) ?></div><?php endif; ?>
        <h3 class="mt2">Lo que incluye</h3>
        <table class="tabla">
          <thead><tr><th>Concepto</th><th class="num">Valor</th></tr></thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr><td><b><?= e($it['concepto'] ?? '') ?></b><?php if (!empty($it['detalle'])): ?><br><span style="color:var(--txt-2);font-size:14px"><?= nl2br(e($it['detalle'])) ?></span><?php endif; ?></td><td class="num"><?= (float)($it['valor'] ?? 0) > 0 ? pesos($it['valor']) : 'Incluido' ?></td></tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot><tr><td style="font-weight:800;font-size:18px">Inversión total</td><td class="num" style="font-family:var(--display);font-size:34px;color:var(--rojo)"><?= pesos($p['total']) ?></td></tr></tfoot>
        </table>
        <?php if ($p['vence']): ?><p class="mt1" style="color:<?= $vencida ? '#fca5a5' : 'var(--txt-2)' ?>;font-size:14px"><?= $vencida ? 'Esta propuesta venció el ' : 'Válida hasta el ' ?><?= fecha_larga($p['vence']) ?>.</p><?php endif; ?>
        <div class="hero-ctas mt2 no-imprimir">
          <?php if ($p['estado'] === 'aceptada'): ?><span class="chip chip-ok" style="font-size:15px;padding:10px 16px"><?= icono('check') ?> Propuesta aceptada</span>
          <?php elseif (!$vencida): ?><form method="post"><?= csrf_campo() ?><input type="hidden" name="acc" value="aceptar"><button class="btn btn-rojo" type="submit"><?= icono('check') ?> Acepto, ¡empecemos!</button></form><?php endif; ?>
          <a class="btn btn-borde" target="_blank" rel="noopener" href="<?= e(wa_link(ajuste('whatsapp'), 'Hola Bryan, soy ' . $p['nombre'] . ', tengo una pregunta sobre la propuesta.')) ?>"><?= icono('whatsapp') ?> Tengo una pregunta</a>
          <button class="btn btn-borde" type="button" onclick="window.print()"><?= icono('print') ?> Guardar PDF</button>
        </div>
        <div class="tarjeta mt2" style="background:var(--negro)">
          <h3 style="font-size:17px">Medios de pago</h3>
          <p style="margin:0;color:var(--txt-2);font-size:14px">Nequi / Daviplata: <?= e(ajuste('pago_nequi')) ?> · <?= e(ajuste('pago_banco')) ?> <?= e(ajuste('pago_cuenta_tipo')) ?> <?= e(ajuste('pago_cuenta')) ?> · <?= e(ajuste('pago_titular')) ?>.
          Sube tu comprobante en <a href="<?= url('pagar.php') ?>" style="color:var(--rojo)">la página de pago</a>.</p>
        </div>
      </div>
    </div>
  </div>
</section>
<?php pie(); ?>
