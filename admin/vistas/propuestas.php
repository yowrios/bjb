<?php
$id = (int)($_GET['id'] ?? 0);
if (es_post()) {
    csrf_verificar();
    if (post('acc') === 'guardar') {
        $items = [];
        foreach ((array)($_POST['it'] ?? []) as $it) {
            $c = trim((string)($it['concepto'] ?? ''));
            if ($c === '') continue;
            $items[] = ['concepto' => mb_substr($c, 0, 160), 'detalle' => mb_substr((string)($it['detalle'] ?? ''), 0, 600), 'valor' => (float)preg_replace('/\D/', '', (string)($it['valor'] ?? '0'))];
        }
        $total = post('total') !== '' ? post_num('total') : array_sum(array_column($items, 'valor'));
        $d = ['nombre' => post('nombre'), 'telefono' => post('telefono'), 'objetivo' => post('objetivo'), 'mensaje' => post('mensaje'), 'items' => json_encode($items, JSON_UNESCAPED_UNICODE),
            'total' => $total, 'vence' => post('vence') ?: null, 'lead_id' => post_int('lead_id') ?: null];
        if ($id) actualizar('propuestas', $id, $d);
        else $id = insertar('propuestas', $d + ['token' => token(16), 'estado' => 'enviada', 'vistas' => 0, 'creado' => ahora()]);
        flash('Propuesta guardada. Envíala por WhatsApp.');
    } elseif (post('acc') === 'borrar' && $id) { q('DELETE FROM propuestas WHERE id = ?', [$id]); flash('Borrada.'); redirigir(url('admin/?p=propuestas')); }
    redirigir(url('admin/?p=propuestas&id=' . $id));
}
if ($id || isset($_GET['nueva']) || isset($_GET['lead'])) {
    $lead = isset($_GET['lead']) ? fila('SELECT * FROM leads WHERE id = ?', [(int)$_GET['lead']]) : null;
    $pr = $id ? fila('SELECT * FROM propuestas WHERE id = ?', [$id]) : ['nombre' => $lead['nombre'] ?? '', 'telefono' => $lead['telefono'] ?? '', 'objetivo' => $lead['mensaje'] ?? '', 'lead_id' => $lead['id'] ?? null,
        'mensaje' => "Gracias por confiar en mí para tu proceso. Después de conocer tu objetivo, esta es la propuesta que diseñé para ti.\n\nEl compromiso es de los dos: yo pongo el plan, el acompañamiento y la motivación; tú pones la constancia. ¡Lo vamos a lograr!",
        'items' => '[]', 'total' => 0, 'vence' => date('Y-m-d', strtotime('+15 days')), 'token' => '', 'estado' => 'enviada', 'vistas' => 0];
    $items = json_decode((string)$pr['items'], true) ?: [];
    if (!$items) { $pl = fila('SELECT * FROM planes WHERE destacado = 1 LIMIT 1') ?: fila('SELECT * FROM planes ORDER BY orden LIMIT 1'); $items = [['concepto' => 'Programa ' . ($pl['nombre'] ?? ''), 'detalle' => str_replace("\n", ' · ', (string)($pl['incluye'] ?? '')), 'valor' => (float)($pl['precio'] ?? 0)]]; }
    $planes = filas('SELECT * FROM planes ORDER BY orden');
    $filaIt = fn(string $n, array $it) => '<div class="campos" data-fila style="grid-template-columns:2fr 3fr 1fr auto;align-items:end;margin-bottom:8px"><div class="campo"><label>Concepto</label><input name="it[' . $n . '][concepto]" value="' . e($it['concepto']) . '"></div><div class="campo"><label>Detalle</label><input name="it[' . $n . '][detalle]" value="' . e($it['detalle']) . '"></div><div class="campo"><label>Valor</label><input name="it[' . $n . '][valor]" value="' . (int)$it['valor'] . '" inputmode="numeric" data-valor></div><button type="button" class="btn-icono peligro" data-quitar>' . icono('x') . '</button></div>';
    admin_cabecera('propuestas', $id ? 'Propuesta para ' . $pr['nombre'] : 'Nueva propuesta');
    if ($id) { $link = url_absoluta('propuesta.php?t=' . $pr['token']); ?>
      <div class="ad-caja" style="border-color:var(--volt);margin-bottom:18px"><h2><?= icono('link') ?> Enlace para enviar <span class="der"><?= estado_chip($pr['estado']) ?> · Abierta <?= (int)$pr['vistas'] ?> veces<?= $pr['ultima_vista'] ? ' · última ' . hace($pr['ultima_vista']) : '' ?></span></h2>
        <input readonly value="<?= e($link) ?>" onclick="this.select()">
        <div class="ad-acciones mt1" style="justify-content:flex-start"><a class="btn btn-wa btn-sm" target="_blank" rel="noopener" href="<?= e(wa_link($pr['telefono'], 'Hola ' . explode(' ', (string)$pr['nombre'])[0] . ', te preparé esta propuesta personalizada 💪 ' . $link)) ?>"><?= icono('whatsapp') ?> Enviar por WhatsApp</a><a class="btn btn-sm btn-borde" target="_blank" href="<?= e($link) ?>"><?= icono('eye') ?> Ver</a><button class="btn btn-sm btn-borde" type="button" data-copiar="<?= e($link) ?>">Copiar enlace</button></div></div>
    <?php } ?>
    <form method="post" class="ad-caja"><?= csrf_campo() ?><input type="hidden" name="acc" value="guardar"><input type="hidden" name="lead_id" value="<?= (int)$pr['lead_id'] ?>">
      <div class="campos">
        <?= campo('nombre', 'Para (nombre) *', $pr['nombre'], 'text', ['required' => true]) ?><?= campo('telefono', 'WhatsApp', $pr['telefono'], 'tel') ?>
        <?= campo('objetivo', 'Su objetivo', $pr['objetivo'], 'textarea', ['ancho' => true, 'filas' => 2]) ?>
        <?= campo('mensaje', 'Mensaje personal', $pr['mensaje'], 'textarea', ['ancho' => true, 'filas' => 5]) ?>
      </div>
      <h2 class="mt2"><?= icono('money') ?> Qué incluye</h2>
      <p class="sec">Agrega desde un plan: <?php foreach ($planes as $p): ?><button type="button" class="btn btn-xs btn-borde" style="margin:2px" onclick='agregarPlan(<?= json_encode(['concepto' => 'Programa ' . $p['nombre'], 'detalle' => str_replace("\n", ' · ', (string)$p['incluye']), 'valor' => (float)$p['precio']], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'><?= e($p['nombre']) ?></button><?php endforeach; ?></p>
      <div id="filasIt" data-plantilla="tplIt"><?php foreach ($items as $i => $it) echo $filaIt('i' . $i, $it); ?></div>
      <template id="tplIt"><?= $filaIt('__N__', ['concepto' => '', 'detalle' => '', 'valor' => 0]) ?></template>
      <button type="button" class="btn btn-sm btn-borde" data-agregar="filasIt"><?= icono('plus') ?> Agregar renglón</button>
      <div class="campos mt2"><?= campo('total', 'Total (puedes poner un precio especial)', (int)$pr['total'] ?: '', 'text', ['inputmode' => 'numeric']) ?><?= campo('vence', 'Válida hasta', $pr['vence'], 'date') ?></div>
      <input type="hidden" id="totalPropuesta">
      <div class="ad-acciones mt2" style="justify-content:flex-start"><button class="btn btn-rojo" type="submit">Guardar propuesta</button><a class="btn btn-borde" href="<?= url('admin/?p=propuestas') ?>">Volver</a></div>
    </form>
    <?php if ($id): ?><form method="post" class="mt2" data-confirmar="¿Borrar la propuesta?"><?= csrf_campo() ?><input type="hidden" name="acc" value="borrar"><button class="btn btn-sm btn-borde" type="submit"><?= icono('trash') ?> Borrar</button></form><?php endif; ?>
    <script>
    function agregarPlan(p) {
      document.querySelector('[data-agregar=filasIt]').click();
      var filas = document.querySelectorAll('#filasIt [data-fila]'), f = filas[filas.length - 1], i = f.querySelectorAll('input');
      i[0].value = p.concepto; i[1].value = p.detalle; i[2].value = Math.round(p.valor);
      var t = document.getElementById('c_total'); if (t && !t.dataset.tocado) { var s = 0; document.querySelectorAll('[data-valor]').forEach(function (x) { s += parseFloat(x.value) || 0; }); t.value = s; }
    }
    document.addEventListener('DOMContentLoaded', function () { var t = document.getElementById('c_total'); if (t) t.addEventListener('input', function () { t.dataset.tocado = 1; }); });
    </script>
    <?php admin_pie(); return;
}
$lista = filas('SELECT * FROM propuestas ORDER BY id DESC LIMIT 200');
admin_cabecera('propuestas', 'Propuestas');
?>
<div class="ad-barra"><div class="ad-nota" style="margin:0;flex:1">Arma una propuesta con precio especial, envíala por WhatsApp como enlace y mira cuándo la abrió. El cliente puede aceptarla con un botón.</div><div class="der"><a class="btn btn-sm btn-rojo" href="<?= url('admin/?p=propuestas&nueva=1') ?>"><?= icono('plus') ?> Nueva propuesta</a></div></div>
<div class="ad-caja ad-tabla-envoltura"><table class="ad-tabla"><thead><tr><th>Para</th><th>Creada</th><th class="num">Total</th><th>Vence</th><th class="num">Abierta</th><th>Estado</th><th></th></tr></thead><tbody>
  <?php foreach ($lista as $p): ?><tr><td><b><?= e($p['nombre']) ?></b><br><span class="sec"><?= e($p['telefono']) ?></span></td><td><?= fecha_corta($p['creado']) ?></td><td class="num"><?= pesos($p['total']) ?></td><td><?= fecha_corta($p['vence']) ?></td><td class="num"><?= (int)$p['vistas'] ?> <?= $p['ultima_vista'] ? '<br><span class="sec">' . hace($p['ultima_vista']) . '</span>' : '' ?></td><td><?= estado_chip($p['estado']) ?></td><td><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=propuestas&id=' . $p['id']) ?>"><?= icono('edit') ?> Abrir</a></td></tr><?php endforeach; ?>
  <?php if (!$lista): ?><tr><td colspan="7" class="ad-vacio">Aún no hay propuestas.</td></tr><?php endif; ?></tbody></table></div>
<?php admin_pie();
