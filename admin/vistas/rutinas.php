<?php
$id = (int)($_GET['id'] ?? 0);
if (es_post()) {
    csrf_verificar();
    try {
        if (post('acc') === 'guardar') {
            if (post('nombre') === '') throw new RuntimeException('Escribe el nombre de la rutina.');
            $act = $id ? fila('SELECT * FROM rutinas WHERE id = ?', [$id]) : null;
            $d = ['nombre' => post('nombre'), 'descripcion' => post('descripcion'), 'objetivo' => post('objetivo'), 'nivel' => post('nivel')];
            if ($act) actualizar('rutinas', $id, $d);
            else $id = insertar('rutinas', $d + ['plantilla' => 1, 'usuario_id' => null, 'activa' => 1, 'creado' => ahora()]);
            q('DELETE FROM rutina_ejercicios WHERE rutina_id = ?', [$id]);
            $orden = 0;
            foreach ((array)($_POST['ej'] ?? []) as $e) {
                $vid = (int)($e['video_id'] ?? 0);
                $nom = trim((string)($e['nombre'] ?? ''));
                if (!$vid && $nom === '') continue;
                insertar('rutina_ejercicios', ['rutina_id' => $id, 'dia' => mb_substr(trim((string)($e['dia'] ?? '')) ?: 'Día A', 0, 60), 'orden' => ++$orden, 'video_id' => $vid ?: null, 'nombre' => mb_substr($nom, 0, 160),
                    'series' => max(1, (int)($e['series'] ?? 3)), 'reps' => mb_substr((string)($e['reps'] ?? '12'), 0, 30), 'descanso_seg' => max(0, (int)($e['descanso'] ?? 60)),
                    'trabajo_seg' => max(0, (int)($e['trabajo'] ?? 0)), 'carga' => mb_substr((string)($e['carga'] ?? ''), 0, 60), 'nota' => mb_substr((string)($e['nota'] ?? ''), 0, 250)]);
            }
            flash('Rutina guardada con ' . $orden . ' ejercicios.');
        } elseif (post('acc') === 'duplicar' && $id) {
            $r = fila('SELECT * FROM rutinas WHERE id = ?', [$id]);
            $nid = insertar('rutinas', ['nombre' => $r['nombre'] . ' (copia)', 'descripcion' => $r['descripcion'], 'objetivo' => $r['objetivo'], 'nivel' => $r['nivel'], 'plantilla' => 1, 'usuario_id' => null, 'activa' => 1, 'creado' => ahora()]);
            foreach (filas('SELECT * FROM rutina_ejercicios WHERE rutina_id = ?', [$id]) as $e) { unset($e['id']); $e['rutina_id'] = $nid; insertar('rutina_ejercicios', $e); }
            flash('Copia creada.'); $id = $nid;
        } elseif (post('acc') === 'archivar' && $id) {
            q('UPDATE rutinas SET activa = 0 WHERE id = ?', [$id]); flash('Rutina archivada.'); redirigir(url('admin/?p=rutinas'));
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'error'); }
    redirigir(url('admin/?p=rutinas&id=' . $id));
}
$vids = filas('SELECT id, titulo, categoria FROM videos ORDER BY categoria, titulo');
$opVid = '<option value="">— Ejercicio libre (escribe el nombre) —</option>';
$grupo = '';
foreach ($vids as $v) { if ($v['categoria'] !== $grupo) { $opVid .= ($grupo ? '</optgroup>' : '') . '<optgroup label="' . e($v['categoria']) . '">'; $grupo = $v['categoria']; } $opVid .= '<option value="' . (int)$v['id'] . '">' . e($v['titulo']) . '</option>'; }
$opVid .= $grupo ? '</optgroup>' : '';
$fila = function (string $n, array $e) use ($opVid): string {
    $sel = $e['video_id'] ? str_replace('value="' . (int)$e['video_id'] . '"', 'value="' . (int)$e['video_id'] . '" selected', $opVid) : $opVid;
    return '<div class="ad-fila-ej" data-fila>'
        . '<input name="ej[' . $n . '][dia]" value="' . e($e['dia']) . '" placeholder="Día A" data-copiar-valor aria-label="Día">'
        . '<div style="display:grid;gap:4px"><select name="ej[' . $n . '][video_id]" aria-label="Máquina">' . $sel . '</select><input name="ej[' . $n . '][nombre]" value="' . e($e['nombre']) . '" placeholder="Nombre (opcional si eliges máquina)" aria-label="Nombre"></div>'
        . '<input name="ej[' . $n . '][series]" type="number" min="1" value="' . (int)$e['series'] . '" aria-label="Series">'
        . '<input name="ej[' . $n . '][reps]" value="' . e($e['reps']) . '" aria-label="Repeticiones">'
        . '<input name="ej[' . $n . '][descanso]" type="number" min="0" step="5" value="' . (int)$e['descanso_seg'] . '" aria-label="Descanso">'
        . '<input name="ej[' . $n . '][trabajo]" type="number" min="0" step="5" value="' . (int)$e['trabajo_seg'] . '" aria-label="Segundos de trabajo">'
        . '<input name="ej[' . $n . '][carga]" value="' . e($e['carga']) . '" placeholder="Auto" aria-label="Carga">'
        . '<input name="ej[' . $n . '][nota]" value="' . e($e['nota']) . '" placeholder="Nota" aria-label="Nota">'
        . '<div style="display:flex;gap:4px"><button type="button" class="btn-icono" data-subir title="Subir">↑</button><button type="button" class="btn-icono peligro" data-quitar title="Quitar">' . icono('x') . '</button></div></div>';
};

if ($id || isset($_GET['nueva'])) {
    $r = $id ? fila('SELECT r.*, u.nombre AS cliente FROM rutinas r LEFT JOIN usuarios u ON u.id = r.usuario_id WHERE r.id = ?', [$id]) : ['nombre' => '', 'descripcion' => '', 'objetivo' => 'hipertrofia', 'nivel' => 'principiante', 'usuario_id' => null, 'cliente' => null];
    $ejs = $id ? filas('SELECT * FROM rutina_ejercicios WHERE rutina_id = ? ORDER BY orden, id', [$id]) : [];
    $vacio = ['dia' => 'Día A', 'video_id' => null, 'nombre' => '', 'series' => 3, 'reps' => '12', 'descanso_seg' => 60, 'trabajo_seg' => 0, 'carga' => '', 'nota' => ''];
    if (!$ejs) $ejs = [$vacio, $vacio, $vacio];
    admin_cabecera('rutinas', $id ? $r['nombre'] : 'Nueva rutina');
    ?>
    <?php if ($r['usuario_id']): ?><div class="ad-nota">Esta rutina es de <b><?= e($r['cliente']) ?></b>: lo que cambies solo le afecta a él/ella. <a href="<?= url('admin/?p=cliente&id=' . $r['usuario_id'] . '&t=rutinas') ?>" style="color:#fff">Volver a su ficha</a></div>
    <?php else: ?><div class="ad-nota">Esta es una <b>plantilla</b>. Para dársela a un cliente, ve a su ficha › Rutinas › Asignar: se crea una copia que puedes ajustar.</div><?php endif; ?>
    <form method="post" class="ad-caja"><?= csrf_campo() ?><input type="hidden" name="acc" value="guardar">
      <div class="campos" style="grid-template-columns:2fr 1fr 1fr">
        <?= campo('nombre', 'Nombre *', $r['nombre'], 'text', ['required' => true]) ?>
        <?= campo('objetivo', 'Objetivo', $r['objetivo'], 'select', ['opciones' => ['hipertrofia' => 'Ganar músculo', 'fuerza' => 'Fuerza', 'grasa' => 'Quemar grasa', 'resistencia' => 'Resistencia'], 'ayuda' => 'Se usa para calcular las cargas sugeridas.']) ?>
        <?= campo('nivel', 'Nivel', $r['nivel'], 'select', ['opciones' => ['principiante' => 'Principiante', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado']]) ?>
        <div class="campo" style="grid-column:1/-1"><label>Descripción</label><input name="descripcion" value="<?= e($r['descripcion']) ?>"></div>
      </div>
      <h2 class="mt2"><?= icono('dumbbell') ?> Ejercicios</h2>
      <div class="ad-fila-ej cab"><span>Día</span><span>Máquina o ejercicio</span><span>Series</span><span>Reps</span><span>Desc. (s)</span><span>Trabajo (s)</span><span>Carga</span><span>Nota</span><span></span></div>
      <div id="filasEj" data-plantilla="tplEj"><?php foreach ($ejs as $i => $e) echo $fila('e' . $i, $e); ?></div>
      <template id="tplEj"><?= $fila('__N__', $vacio) ?></template>
      <button type="button" class="btn btn-sm btn-borde mt1" data-agregar="filasEj"><?= icono('plus') ?> Agregar ejercicio</button>
      <p class="sec mt1">«Carga» vacía = el sistema la calcula con el peso del cliente. «Trabajo (s)» solo si el ejercicio es por tiempo (plancha, cardio); si es 0, el cliente marca «Terminé la serie».</p>
      <div class="ad-acciones mt2" style="justify-content:flex-start"><button class="btn btn-rojo" type="submit">Guardar rutina</button><a class="btn btn-borde" href="<?= url('admin/?p=rutinas') ?>">Volver</a></div>
    </form>
    <?php if ($id): ?><div class="ad-acciones mt2" style="justify-content:flex-start">
      <form method="post"><?= csrf_campo() ?><input type="hidden" name="acc" value="duplicar"><button class="btn btn-sm btn-borde" type="submit">Duplicar como plantilla</button></form>
      <a class="btn btn-sm btn-borde" target="_blank" href="<?= url('cronometro.php?modo=rutina&rutina=' . $id) ?>"><?= icono('timer') ?> Probar en el cronómetro</a>
      <form method="post" data-confirmar="¿Archivar esta rutina?"><?= csrf_campo() ?><input type="hidden" name="acc" value="archivar"><button class="btn btn-sm btn-borde" type="submit"><?= icono('trash') ?> Archivar</button></form></div><?php endif; ?>
    <?php admin_pie(); return;
}
$plant = filas('SELECT r.*, (SELECT COUNT(*) FROM rutina_ejercicios e WHERE e.rutina_id = r.id) AS n FROM rutinas r WHERE r.plantilla = 1 AND r.activa = 1 ORDER BY r.nombre');
$asig = filas('SELECT r.*, u.nombre AS cliente, (SELECT COUNT(*) FROM rutina_ejercicios e WHERE e.rutina_id = r.id) AS n FROM rutinas r JOIN usuarios u ON u.id = r.usuario_id WHERE r.plantilla = 0 AND r.activa = 1 ORDER BY r.id DESC LIMIT 100');
admin_cabecera('rutinas', 'Rutinas');
?>
<div class="ad-barra"><div class="der"><a class="btn btn-sm btn-rojo" href="<?= url('admin/?p=rutinas&nueva=1') ?>"><?= icono('plus') ?> Nueva plantilla</a></div></div>
<div class="ad-rejilla">
  <div class="ad-caja"><h2><?= icono('clipboard') ?> Plantillas</h2>
    <?php foreach ($plant as $r): ?><div class="fila-lista"><div><b><?= e($r['nombre']) ?></b><br><span><?= (int)$r['n'] ?> ejercicios · <?= e($r['nivel']) ?> · <?= e($r['objetivo']) ?></span></div><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=rutinas&id=' . $r['id']) ?>"><?= icono('edit') ?> Editar</a></div><?php endforeach; ?>
    <?php if (!$plant): ?><p class="ad-vacio">Crea tu primera plantilla.</p><?php endif; ?></div>
  <div class="ad-caja"><h2><?= icono('users') ?> Asignadas a clientes</h2>
    <?php foreach ($asig as $r): ?><div class="fila-lista"><div><b><?= e($r['cliente']) ?></b><br><span><?= e($r['nombre']) ?> · <?= (int)$r['n'] ?> ejercicios</span></div><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=rutinas&id=' . $r['id']) ?>"><?= icono('edit') ?></a></div><?php endforeach; ?>
    <?php if (!$asig): ?><p class="ad-vacio">Asigna rutinas desde la ficha de cada cliente.</p><?php endif; ?></div>
</div>
<?php admin_pie();
