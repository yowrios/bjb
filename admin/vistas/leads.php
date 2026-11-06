<?php
/** Interesados (contactos de la web) y formularios iniciales. */
if (es_post()) {
    csrf_verificar();
    $l = fila('SELECT * FROM leads WHERE id = ?', [post_int('lead_id')]);
    if (post('acc') === 'estado' && $l) {
        q('UPDATE leads SET estado = ?, nota = ?, actualizado = ? WHERE id = ?', [in_array(post('estado'), ['nuevo', 'contactado', 'cliente', 'descartado'], true) ? post('estado') : $l['estado'], post('nota'), ahora(), $l['id']]);
        flash('Guardado.');
    } elseif (post('acc') === 'convertir' && $l) {
        // Crea la cuenta del cliente a partir del interesado
        $f = $l['id'] ? fila('SELECT * FROM formularios WHERE lead_id = ? ORDER BY id DESC LIMIT 1', [$l['id']]) : null;
        $d = $f ? (json_decode((string)$f['datos'], true) ?: []) : [];
        $email = mb_strtolower(trim((string)($l['email'] ?: ($d['email'] ?? ''))));
        $ya = $email ? fila('SELECT * FROM usuarios WHERE LOWER(email) = ?', [$email]) : null;
        if ($ya) { q("UPDATE leads SET usuario_id = ?, estado = 'cliente' WHERE id = ?", [$ya['id'], $l['id']]); redirigir(url('admin/?p=cliente&id=' . $ya['id'])); }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = 'cliente' . time() . '@sin-correo.local';
        $clave = clave_temporal();
        $uid = insertar('usuarios', ['rol' => 'cliente', 'nombre' => $l['nombre'], 'email' => $email, 'telefono' => $l['telefono'], 'clave_hash' => password_hash($clave, PASSWORD_DEFAULT), 'activo' => 1, 'debe_cambiar' => 1,
            'fecha_nacimiento' => $d['fecha_nacimiento'] ?? null, 'sexo' => in_array($d['sexo'] ?? '', ['M', 'F'], true) ? $d['sexo'] : null, 'estatura_cm' => num($d['estatura'] ?? null), 'nivel' => $d['nivel'] ?? null,
            'modalidad' => $d['modalidad'] ?? null, 'objetivo' => $d['objetivo'] ?? $l['mensaje'], 'origen' => 'web: ' . $l['origen'], 'consentimiento' => $l['creado'], 'creado' => ahora()]);
        q("UPDATE leads SET usuario_id = ?, estado = 'cliente', actualizado = ? WHERE id = ?", [$uid, ahora(), $l['id']]);
        if ($f) {
            q('UPDATE formularios SET usuario_id = ? WHERE id = ?', [$uid, $f['id']]);
            if (num($d['peso'] ?? null)) insertar('medidas', ['usuario_id' => $uid, 'fecha' => substr($f['creado'], 0, 10), 'peso' => num($d['peso']), 'nota' => 'Formulario inicial', 'por' => 'cliente', 'creado' => ahora()]);
            foreach (json_decode((string)$f['fotos'], true) ?: [] as $i => $ar) {
                $nuevo = 'fotos/' . basename($ar);
                if (@copy(PRIV_DIR . '/archivos/' . $ar, PRIV_DIR . '/archivos/' . $nuevo)) insertar('fotos', ['usuario_id' => $uid, 'fecha' => substr($f['creado'], 0, 10), 'tipo' => ['frente', 'perfil', 'espalda'][$i] ?? 'otra', 'archivo' => $nuevo, 'nota' => 'Formulario inicial', 'por' => 'cliente', 'creado' => ahora()]);
            }
        }
        $_SESSION['clave_nueva'] = [$uid, $clave];
        flash('Cuenta creada para ' . $l['nombre'] . '. Ahora activa su plan.');
        redirigir(url('admin/?p=cliente&id=' . $uid . '&t=plan'));
    } elseif (post('acc') === 'revisado') {
        q('UPDATE formularios SET revisado = 1 WHERE id = ?', [post_int('form_id')]);
        flash('Formulario marcado como revisado.');
    } elseif (post('acc') === 'borrar' && $l) {
        q('DELETE FROM leads WHERE id = ?', [$l['id']]); flash('Interesado borrado.');
    }
    redirigir(url('admin/?p=leads' . (isset($_GET['formulario']) ? '&formulario=' . (int)$_GET['formulario'] : '')));
}

if (!empty($_GET['formulario'])) {
    $f = fila('SELECT * FROM formularios WHERE id = ?', [(int)$_GET['formulario']]);
    admin_cabecera('leads', 'Formulario inicial');
    if (!$f) { echo '<p class="ad-vacio">No encontrado.</p>'; admin_pie(); return; }
    $l = $f['lead_id'] ? fila('SELECT * FROM leads WHERE id = ?', [$f['lead_id']]) : null;
    $d = json_decode((string)$f['datos'], true) ?: [];
    ?>
    <div class="ad-barra"><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=leads') ?>">← Interesados</a>
      <div class="der">
        <a class="btn btn-wa btn-sm" target="_blank" rel="noopener" href="<?= e(wa_link($d['whatsapp'] ?? '', 'Hola ' . explode(' ', $f['nombre'])[0] . ', soy Bryan. Recibí tu formulario inicial y ya estoy preparando tu plan. 💪')) ?>"><?= icono('whatsapp') ?> Escribirle</a>
        <?php if ($f['usuario_id']): ?><a class="btn btn-sm btn-rojo" href="<?= url('admin/?p=cliente&id=' . $f['usuario_id']) ?>">Abrir ficha</a>
        <?php elseif ($l): ?><form method="post"><?= csrf_campo() ?><input type="hidden" name="acc" value="convertir"><input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-rojo" type="submit"><?= icono('user') ?> Crear como cliente</button></form><?php endif; ?>
        <?php if (!$f['revisado']): ?><form method="post"><?= csrf_campo() ?><input type="hidden" name="acc" value="revisado"><input type="hidden" name="form_id" value="<?= (int)$f['id'] ?>"><button class="btn btn-sm btn-borde" type="submit"><?= icono('check') ?> Marcar revisado</button></form><?php endif; ?>
        <button class="btn btn-sm btn-borde" onclick="window.print()"><?= icono('print') ?> Imprimir</button>
      </div></div>
    <?php include __DIR__ . '/_formulario.php'; admin_pie(); return;
}

$estado = $_GET['e'] ?? 'abiertos';
$leads = filas('SELECT l.*, (SELECT COUNT(*) FROM visitas v WHERE v.visitante = l.visitante AND l.visitante <> \'\') AS visitas, (SELECT f.id FROM formularios f WHERE f.lead_id = l.id LIMIT 1) AS form_id FROM leads l'
    . ($estado === 'abiertos' ? " WHERE l.estado IN ('nuevo','contactado')" : ($estado !== 'todos' ? ' WHERE l.estado = ?' : '')) . ' ORDER BY l.id DESC LIMIT 500', ($estado !== 'todos' && $estado !== 'abiertos') ? [$estado] : []);
if (isset($_GET['exportar'])) csv_descarga('interesados', ['Fecha', 'Nombre', 'WhatsApp', 'Correo', 'Origen', 'Interés', 'Mensaje', 'Estado', 'Nota'], array_map(fn($x) => [$x['creado'], $x['nombre'], $x['telefono'], $x['email'], $x['origen'], $x['interes'], $x['mensaje'], $x['estado'], $x['nota']], $leads));
$formsPend = filas('SELECT * FROM formularios WHERE revisado = 0 ORDER BY id DESC');
admin_cabecera('leads', 'Interesados');
?>
<?php if ($formsPend): ?>
  <div class="ad-caja" style="margin-bottom:20px;border-color:var(--volt)"><h2><?= icono('clipboard') ?> Formularios iniciales sin revisar (<?= count($formsPend) ?>)</h2>
    <?php foreach ($formsPend as $f): ?><div class="fila-lista"><div><b><?= e($f['nombre']) ?></b><br><span><?= hace($f['creado']) ?></span></div><a class="btn btn-sm btn-rojo" href="<?= url('admin/?p=leads&formulario=' . $f['id']) ?>">Abrir</a></div><?php endforeach; ?></div>
<?php endif; ?>
<nav class="ad-pestanas"><?php foreach (['abiertos' => 'Por atender', 'nuevo' => 'Nuevos', 'contactado' => 'Contactados', 'cliente' => 'Ya son clientes', 'descartado' => 'Descartados', 'todos' => 'Todos'] as $k => $tt): ?><a class="<?= $estado === $k ? 'on' : '' ?>" href="<?= url('admin/?p=leads&e=' . $k) ?>"><?= $tt ?></a><?php endforeach; ?></nav>
<div class="ad-barra"><input type="search" placeholder="Buscar..." data-filtrar="#tablaLeads"><div class="der"><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=leads&e=' . $estado . '&exportar=1') ?>"><?= icono('download') ?> Excel</a></div></div>
<div class="ad-caja ad-tabla-envoltura"><table class="ad-tabla" id="tablaLeads">
  <thead><tr><th>Persona</th><th>Llegó por</th><th>Interés</th><th class="num">Visitas</th><th>Estado y nota</th><th></th></tr></thead><tbody>
  <?php foreach ($leads as $l): $n1 = explode(' ', (string)$l['nombre'])[0]; ?>
    <tr><td><b><?= e($l['nombre']) ?></b><br><span class="sec"><?= e($l['telefono']) ?><?= $l['email'] ? ' · ' . e($l['email']) : '' ?><br><?= hace($l['creado']) ?></span></td>
      <td><?= e(ucfirst((string)$l['origen'])) ?><?= $l['form_id'] ? '<br><a href="' . url('admin/?p=leads&formulario=' . $l['form_id']) . '" style="color:var(--rojo);font-size:13px">Ver formulario</a>' : '' ?></td>
      <td class="sec" style="max-width:240px"><?= e($l['interes']) ?><?= $l['mensaje'] ? '<br>«' . e(mb_strimwidth((string)$l['mensaje'], 0, 120, '…')) . '»' : '' ?></td>
      <td class="num"><?= (int)$l['visitas'] ?></td>
      <td><form method="post" style="display:grid;grid-template-columns:130px 1fr auto;gap:6px;min-width:330px"><?= csrf_campo() ?><input type="hidden" name="acc" value="estado"><input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>">
        <select name="estado" style="padding:8px"><?php foreach (['nuevo' => 'Nuevo', 'contactado' => 'Contactado', 'cliente' => 'Ya es cliente', 'descartado' => 'Descartado'] as $k => $tt): ?><option value="<?= $k ?>" <?= $l['estado'] === $k ? 'selected' : '' ?>><?= $tt ?></option><?php endforeach; ?></select>
        <input name="nota" value="<?= e($l['nota']) ?>" placeholder="Nota" style="padding:8px"><button class="btn-icono" type="submit" title="Guardar"><?= icono('check') ?></button></form></td>
      <td><div class="ad-acciones">
        <a class="btn-icono wa" target="_blank" rel="noopener" href="<?= e(wa_link($l['telefono'], "Hola $n1, soy Bryan de " . ajuste('marca') . '. ' . ($l['origen'] === 'catalogo' ? '¿Pudiste ver el catálogo? Cuéntame tu objetivo y te digo qué plan te sirve.' : 'Vi que te interesa entrenar conmigo. ¿Cuál es tu objetivo?'))) ?>" title="WhatsApp"><?= icono('whatsapp') ?></a>
        <?php if (!$l['usuario_id']): ?><form method="post" data-confirmar="¿Crear cuenta de cliente para <?= e($l['nombre']) ?>?"><?= csrf_campo() ?><input type="hidden" name="acc" value="convertir"><input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>"><button class="btn-icono" type="submit" title="Crear como cliente"><?= icono('user') ?></button></form>
        <?php else: ?><a class="btn-icono" href="<?= url('admin/?p=cliente&id=' . $l['usuario_id']) ?>" title="Ficha"><?= icono('arrow') ?></a><?php endif; ?>
        <a class="btn-icono" href="<?= url('admin/?p=propuestas&lead=' . $l['id']) ?>" title="Hacer propuesta"><?= icono('send') ?></a>
        <form method="post" data-confirmar="¿Borrar este interesado?"><?= csrf_campo() ?><input type="hidden" name="acc" value="borrar"><input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>"><button class="btn-icono peligro" type="submit" title="Borrar"><?= icono('trash') ?></button></form>
      </div></td></tr>
  <?php endforeach; ?>
  <?php if (!$leads): ?><tr><td colspan="6" class="ad-vacio">Sin interesados en este filtro.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php admin_pie();
