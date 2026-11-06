<?php
if (es_post()) {
    csrf_verificar();
    $p = fila('SELECT * FROM pagos WHERE id = ?', [post_int('pago_id')]);
    if ($p && $p['estado'] === 'pendiente') {
        if (post('acc') === 'aprobar') {
            if (post('monto') !== '') q('UPDATE pagos SET monto = ? WHERE id = ?', [post_num('monto'), $p['id']]);
            $p = fila('SELECT * FROM pagos WHERE id = ?', [$p['id']]);
            $msg = aprobar_pago($p);
            $_SESSION['aviso_wa'] = [$p['telefono'], $msg, $p['nombre']];
            flash('Pago aprobado y acceso activado.');
        } elseif (post('acc') === 'rechazar') {
            q("UPDATE pagos SET estado = 'rechazado', nota = ?, revisado = ? WHERE id = ?", [trim(($p['nota'] ? $p['nota'] . "\n" : '') . 'Motivo: ' . post('motivo')), ahora(), $p['id']]);
            $_SESSION['aviso_wa'] = [$p['telefono'], 'Hola ' . explode(' ', (string)$p['nombre'])[0] . ', revisé tu comprobante de ' . $p['concepto'] . ' y no pude validarlo' . (post('motivo') ? ': ' . post('motivo') : '') . '. ¿Me lo envías de nuevo, por favor?', $p['nombre']];
            flash('Pago rechazado.', 'info');
        }
    }
    redirigir(url('admin/?p=pagos'));
}
$estado = $_GET['e'] ?? 'pendiente';
$pagos = filas('SELECT p.*, u.nombre AS unombre FROM pagos p LEFT JOIN usuarios u ON u.id = p.usuario_id' . ($estado !== 'todos' ? ' WHERE p.estado = ?' : '') . ' ORDER BY p.id DESC LIMIT 300', $estado !== 'todos' ? [$estado] : []);
if (isset($_GET['exportar'])) csv_descarga('pagos', ['Fecha', 'Cliente', 'Concepto', 'Valor', 'Medio', 'Referencia', 'Estado'], array_map(fn($x) => [$x['creado'], $x['nombre'], $x['concepto'], $x['monto'], $x['metodo'], $x['referencia'], $x['estado']], $pagos));
$aviso = $_SESSION['aviso_wa'] ?? null; unset($_SESSION['aviso_wa']);
admin_cabecera('pagos', 'Pagos');
$conteo = []; foreach (filas('SELECT estado, COUNT(*) AS n FROM pagos GROUP BY estado') as $r) $conteo[$r['estado']] = $r['n'];
?>
<?php if ($aviso): ?><div class="ad-caja" style="border-color:#25d366;margin-bottom:18px"><h2><?= icono('whatsapp') ?> Avísale a <?= e($aviso[2]) ?></h2><p class="sec"><?= e($aviso[1]) ?></p><a class="btn btn-wa btn-sm" target="_blank" rel="noopener" href="<?= e(wa_link($aviso[0], $aviso[1])) ?>"><?= icono('whatsapp') ?> Enviar mensaje</a></div><?php endif; ?>
<nav class="ad-pestanas">
  <?php foreach (['pendiente' => 'Por revisar', 'aprobado' => 'Aprobados', 'rechazado' => 'Rechazados', 'todos' => 'Todos'] as $k => $tt): ?><a class="<?= $estado === $k ? 'on' : '' ?>" href="<?= url('admin/?p=pagos&e=' . $k) ?>"><?= $tt ?> <em><?= $k === 'todos' ? array_sum($conteo) : (int)($conteo[$k] ?? 0) ?></em></a><?php endforeach; ?>
</nav>
<div class="ad-barra"><input type="search" placeholder="Buscar..." data-filtrar="#tablaPagos"><div class="der"><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=pagos&e=' . $estado . '&exportar=1') ?>"><?= icono('download') ?> Excel</a></div></div>
<?php if ($estado === 'pendiente'): ?>
  <div class="ad-nota">Revisa en tu Nequi, Daviplata o banco que el dinero <b>sí llegó</b> antes de aprobar. Al aprobar, el sistema activa el plan o el video automáticamente y te prepara el mensaje de WhatsApp.</div>
  <div class="ad-rejilla">
  <?php foreach ($pagos as $x): $esPdf = str_ends_with((string)$x['comprobante'], '.pdf'); ?>
    <div class="ad-caja" id="pago<?= (int)$x['id'] ?>">
      <h2><?= icono('money') ?> <?= e($x['concepto']) ?> <span class="der"><?= hace($x['creado']) ?></span></h2>
      <div class="ad-rejilla" style="gap:16px">
        <div>
          <div class="ad-dato"><span>Cliente</span><span><?= $x['usuario_id'] ? '<a href="' . url('admin/?p=cliente&id=' . $x['usuario_id']) . '">' . e($x['nombre']) . '</a>' : e($x['nombre']) ?></span></div>
          <div class="ad-dato"><span>WhatsApp</span><span><a href="<?= e(wa_link($x['telefono'])) ?>" target="_blank" rel="noopener"><?= e($x['telefono']) ?></a></span></div>
          <div class="ad-dato"><span>Valor</span><span style="font-size:20px"><?= pesos($x['monto']) ?></span></div>
          <div class="ad-dato"><span>Medio</span><span><?= e($x['metodo']) ?><?= $x['referencia'] ? ' · ' . e($x['referencia']) : '' ?></span></div>
          <?php if ($x['nota']): ?><p class="sec mt1">«<?= e($x['nota']) ?>»</p><?php endif; ?>
          <?php if ($x['concepto_tipo'] === 'pedido'): ?><div class="ad-nota mt1"><b>Pedido:</b><br><?php foreach (json_decode((string)$x['detalle'], true) ?: [] as $l): ?><?= (int)$l['cant'] ?> × <?= e($l['nombre']) ?> (<?= pesos($l['precio'] * $l['cant']) ?>)<br><?php endforeach; ?><b>Entrega:</b> <?= e($x['entrega']) ?></div><?php endif; ?>
        </div>
        <div><?php if ($x['comprobante']): ?><a href="<?= e(media_url('comprobante', (int)$x['id'])) ?>" target="_blank"><?= $esPdf ? '<span class="btn btn-borde btn-bloque">' . icono('file') . ' Ver PDF</span>' : '<img src="' . e(media_url('comprobante', (int)$x['id'])) . '" alt="Comprobante" style="border-radius:12px;max-height:260px;width:100%;object-fit:contain;background:#000">' ?></a><?php endif; ?></div>
      </div>
      <div class="ad-rejilla mt2" style="gap:10px">
        <form method="post"><?= csrf_campo() ?><input type="hidden" name="acc" value="aprobar"><input type="hidden" name="pago_id" value="<?= (int)$x['id'] ?>">
          <div class="campo"><label>Valor recibido</label><input name="monto" inputmode="numeric" value="<?= (int)$x['monto'] ?>"></div>
          <button class="btn btn-rojo btn-bloque mt1" type="submit"><?= icono('check') ?> Aprobar y activar</button></form>
        <form method="post" data-confirmar="¿Rechazar este pago?"><?= csrf_campo() ?><input type="hidden" name="acc" value="rechazar"><input type="hidden" name="pago_id" value="<?= (int)$x['id'] ?>">
          <div class="campo"><label>Motivo (se lo dices al cliente)</label><input name="motivo" placeholder="No veo el dinero en mi cuenta"></div>
          <button class="btn btn-borde btn-bloque mt1" type="submit"><?= icono('x') ?> Rechazar</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <?php if (!$pagos): ?><div class="ad-caja"><p class="ad-vacio">No hay pagos por revisar. 👌</p></div><?php endif; ?>
<?php else: ?>
  <div class="ad-caja ad-tabla-envoltura"><table class="ad-tabla" id="tablaPagos">
    <thead><tr><th>Fecha</th><th>Cliente</th><th>Concepto</th><th>Medio</th><th class="num">Valor</th><th>Estado</th><th></th></tr></thead><tbody>
    <?php foreach ($pagos as $x): ?><tr><td><?= fecha_corta($x['creado']) ?></td><td><?= $x['usuario_id'] ? '<a href="' . url('admin/?p=cliente&id=' . $x['usuario_id'] . '&t=pagos') . '">' . e($x['nombre']) . '</a>' : e($x['nombre']) ?></td><td><?= e($x['concepto']) ?></td><td class="sec"><?= e($x['metodo']) ?></td><td class="num"><?= pesos($x['monto']) ?></td><td><?= estado_chip($x['estado']) ?></td>
      <td><?= $x['comprobante'] ? '<a class="btn-icono" href="' . e(media_url('comprobante', (int)$x['id'])) . '" target="_blank" title="Comprobante">' . icono('file') . '</a>' : '' ?></td></tr><?php endforeach; ?>
    <?php if (!$pagos): ?><tr><td colspan="7" class="ad-vacio">Sin pagos.</td></tr><?php endif; ?>
    </tbody></table></div>
<?php endif; ?>
<?php admin_pie();
