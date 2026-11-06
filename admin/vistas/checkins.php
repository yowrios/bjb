<?php
if (es_post()) {
    csrf_verificar();
    q('UPDATE checkins SET respuesta = ?, respondido = ? WHERE id = ?', [post('respuesta'), ahora(), post_int('checkin_id')]);
    flash('Respuesta enviada a su portal.');
    redirigir(url('admin/?p=checkins'));
}
$pend = filas("SELECT c.*, u.nombre, u.telefono FROM checkins c JOIN usuarios u ON u.id = c.usuario_id WHERE c.respuesta IS NULL OR c.respuesta = '' ORDER BY c.id");
$resp = filas("SELECT c.*, u.nombre FROM checkins c JOIN usuarios u ON u.id = c.usuario_id WHERE c.respuesta <> '' ORDER BY c.respondido DESC LIMIT 20");
admin_cabecera('checkins', 'Check-ins semanales');
$cara = fn($n) => ['', '😫', '😕', '🙂', '😃', '🔥'][(int)$n] ?? '';
?>
<div class="ad-nota">Tus clientes cuentan cómo les fue en la semana desde su portal. Responder rápido es lo que más los motiva a seguir. <b>Consejo:</b> pídeles que lo hagan cada domingo.</div>
<div class="ad-rejilla">
<?php foreach ($pend as $h): ?>
  <form method="post" class="ad-caja"><?= csrf_campo() ?><input type="hidden" name="checkin_id" value="<?= (int)$h['id'] ?>">
    <h2><a href="<?= url('admin/?p=cliente&id=' . $h['usuario_id'] . '&t=checkins') ?>" style="text-decoration:none"><?= e($h['nombre']) ?></a> <span class="der"><?= fecha_corta($h['fecha']) ?></span></h2>
    <div class="plan-meta" style="margin:0 0 10px"><?php if ($h['peso']): ?><span class="chip"><?= (float)$h['peso'] ?> kg</span><?php endif; ?><span class="chip">Energía <?= $cara($h['energia']) ?></span><span class="chip">Sueño <?= $cara($h['sueno']) ?></span><span class="chip">Plan <?= ((int)$h['adherencia'] - 1) * 25 ?>%</span><span class="chip">Ánimo <?= $cara($h['animo']) ?></span></div>
    <?php if ($h['comentario']): ?><p style="color:#d4d4d8">«<?= nl2br(e($h['comentario'])) ?>»</p><?php endif; ?>
    <textarea name="respuesta" rows="3" required placeholder="Escríbele tu respuesta..."></textarea>
    <div class="ad-acciones mt1" style="justify-content:flex-start"><button class="btn btn-rojo btn-sm" type="submit">Responder</button><a class="btn btn-wa btn-sm" target="_blank" rel="noopener" href="<?= e(wa_link($h['telefono'])) ?>"><?= icono('whatsapp') ?> WhatsApp</a></div>
  </form>
<?php endforeach; ?>
</div>
<?php if (!$pend): ?><div class="ad-caja"><p class="ad-vacio">Todos los check-ins están respondidos. 👌</p></div><?php endif; ?>
<?php if ($resp): ?><div class="ad-caja mt2"><h2>Respondidos recientemente</h2><?php foreach ($resp as $h): ?><div class="fila-lista"><div><b><?= e($h['nombre']) ?></b> · <?= fecha_corta($h['fecha']) ?><br><span>Tú: <?= e(mb_strimwidth((string)$h['respuesta'], 0, 140, '…')) ?></span></div></div><?php endforeach; ?></div><?php endif; ?>
<?php admin_pie();
