<?php
$id = (int)($_GET['id'] ?? 0);
if (es_post()) {
    csrf_verificar();
    try {
        if (post('acc') === 'guardar') {
            $act = $id ? fila('SELECT * FROM videos WHERE id = ?', [$id]) : null;
            if (post('titulo') === '') throw new RuntimeException('Escribe el nombre.');
            $mus = implode(',', array_intersect((array)($_POST['musculos'] ?? []), ['cuadriceps', 'gluteos', 'isquios', 'pantorrillas', 'abdomen', 'oblicuos', 'pecho', 'hombros', 'biceps', 'triceps', 'antebrazos', 'dorsales', 'trapecio', 'lumbar']));
            $d = ['titulo' => post('titulo'), 'categoria' => post('categoria') ?: 'General', 'musculos' => $mus, 'nivel' => post('nivel'), 'descripcion' => post('descripcion'),
                'pasos' => post('pasos'), 'errores' => post('errores'), 'tips' => post('tips'), 'video_url' => post('video_url'), 'precio' => post_num('precio'),
                'gratis' => post('gratis') ? 1 : 0, 'visible' => post('visible') ? 1 : 0, 'orden' => post_int('orden'),
                'coef_hombre' => num(post('coef_hombre')) ?? 0, 'coef_mujer' => num(post('coef_mujer')) ?? 0, 'incremento' => num(post('incremento')) ?: 5, 'nota_carga' => post('nota_carga')];
            if (post('video_url') !== '' && !video_embed(post('video_url'))) throw new RuntimeException('El enlace del video debe ser de YouTube o Vimeo.');
            $d['imagen'] = imagen_post('imagen', $act['imagen'] ?? null, 'maquinas');
            $vid = subir_archivo($_FILES['video_archivo'] ?? ['error' => UPLOAD_ERR_NO_FILE], TIPOS_VIDEO, true, 'videos', 500);
            if ($vid) { if (!empty($act['video_archivo'])) @unlink(PRIV_DIR . '/archivos/' . $act['video_archivo']); $d['video_archivo'] = $vid; }
            if (!empty($_POST['quitar_video_archivo']) && !empty($act['video_archivo'])) { @unlink(PRIV_DIR . '/archivos/' . $act['video_archivo']); $d['video_archivo'] = ''; }
            if ($act) actualizar('videos', $id, $d);
            else { $d['slug'] = slugify(post('titulo')); if (valor('SELECT COUNT(*) FROM videos WHERE slug = ?', [$d['slug']])) $d['slug'] .= '-' . substr(token(2), 0, 4); $d['creado'] = ahora(); $id = insertar('videos', $d); }
            flash('Guardado.');
            redirigir(url('admin/?p=videos&id=' . $id));
        } elseif (post('acc') === 'borrar' && $id) {
            $v = fila('SELECT * FROM videos WHERE id = ?', [$id]);
            if ($v && $v['video_archivo']) @unlink(PRIV_DIR . '/archivos/' . $v['video_archivo']);
            q('DELETE FROM videos WHERE id = ?', [$id]); q('DELETE FROM accesos_video WHERE video_id = ?', [$id]); q('UPDATE rutina_ejercicios SET video_id = NULL WHERE video_id = ?', [$id]);
            flash('Borrado.');
            redirigir(url('admin/?p=videos'));
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'error'); }
    redirigir(url('admin/?p=videos' . ($id ? '&id=' . $id : '&nuevo=1')));
}
$musculos = ['cuadriceps' => 'Cuádriceps', 'gluteos' => 'Glúteos', 'isquios' => 'Isquiotibiales', 'pantorrillas' => 'Pantorrillas', 'abdomen' => 'Abdomen', 'oblicuos' => 'Oblicuos', 'pecho' => 'Pecho', 'hombros' => 'Hombros',
    'biceps' => 'Bíceps', 'triceps' => 'Tríceps', 'antebrazos' => 'Antebrazos', 'dorsales' => 'Dorsales', 'trapecio' => 'Trapecio', 'lumbar' => 'Lumbar'];
if ($id || isset($_GET['nuevo'])) {
    $v = $id ? fila('SELECT * FROM videos WHERE id = ?', [$id]) : ['titulo' => '', 'categoria' => '', 'musculos' => '', 'nivel' => 'principiante', 'descripcion' => '', 'pasos' => '', 'errores' => '', 'tips' => '', 'imagen' => '',
        'video_archivo' => '', 'video_url' => '', 'precio' => ajuste('precio_video_defecto', '15000'), 'gratis' => 0, 'visible' => 1, 'orden' => 50, 'coef_hombre' => 0, 'coef_mujer' => 0, 'incremento' => 5, 'nota_carga' => 'Peso de la columna de placas', 'slug' => ''];
    $cats = array_column(filas('SELECT DISTINCT categoria FROM videos ORDER BY categoria'), 'categoria');
    $sel = array_flip(explode(',', (string)$v['musculos']));
    admin_cabecera('videos', $id ? $v['titulo'] : 'Nueva máquina o ejercicio');
    $lim = ini_get('upload_max_filesize');
    ?>
    <form method="post" enctype="multipart/form-data"><?= csrf_campo() ?><input type="hidden" name="acc" value="guardar">
    <div class="ad-rejilla">
      <div class="ad-caja"><h2><?= icono('dumbbell') ?> Información</h2>
        <div class="campos">
          <?= campo('titulo', 'Nombre *', $v['titulo'], 'text', ['required' => true, 'ancho' => true, 'placeholder' => 'Prensa de piernas 45°']) ?>
          <div class="campo"><label for="c_cat">Categoría</label><input id="c_cat" name="categoria" list="listaCats" value="<?= e($v['categoria']) ?>" placeholder="Piernas"><datalist id="listaCats"><?php foreach ($cats as $ct): ?><option value="<?= e($ct) ?>"><?php endforeach; ?></datalist></div>
          <?= campo('nivel', 'Nivel', $v['nivel'], 'select', ['opciones' => ['principiante' => 'Principiante', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado']]) ?>
          <div class="campo ancho"><span class="etq">Músculos que trabaja (se iluminan en el dibujo)</span><div class="opciones"><?php foreach ($musculos as $k => $tt): ?><label><input type="checkbox" name="musculos[]" value="<?= $k ?>" <?= isset($sel[$k]) ? 'checked' : '' ?>><span><?= $tt ?></span></label><?php endforeach; ?></div></div>
          <?= campo('descripcion', 'Descripción corta', $v['descripcion'], 'textarea', ['ancho' => true, 'filas' => 3]) ?>
          <?= campo('pasos', 'Cómo se hace (un paso por línea)', $v['pasos'], 'textarea', ['ancho' => true, 'filas' => 6]) ?>
          <?= campo('errores', 'Errores comunes (uno por línea)', $v['errores'], 'textarea', ['ancho' => true, 'filas' => 4]) ?>
          <?= campo('tips', 'Tus consejos (uno por línea)', $v['tips'], 'textarea', ['ancho' => true, 'filas' => 3]) ?>
        </div>
      </div>
      <div>
        <div class="ad-caja"><h2><?= icono('video') ?> Video y foto</h2>
          <div class="ad-nota">Recomendado: sube el video a <b>YouTube como «No listado»</b> o a Vimeo y pega el enlace aquí; carga más rápido y no gasta espacio de tu hosting. También puedes subir el archivo MP4 (máximo <?= e($lim) ?> en este servidor): queda protegido y solo lo ve quien pagó.</div>
          <div class="campos">
            <?= campo('video_url', 'Enlace de YouTube o Vimeo', $v['video_url'], 'url', ['ancho' => true, 'placeholder' => 'https://youtu.be/...']) ?>
            <div class="campo ancho"><label>O sube el archivo de video (MP4)</label><?= $v['video_archivo'] ? '<span class="chip chip-ok">Video subido ✓</span> <label class="check"><input type="checkbox" name="quitar_video_archivo" value="1"> <span>Quitar video</span></label>' : '' ?><input type="file" name="video_archivo" accept="video/mp4,video/webm,video/quicktime"></div>
            <?= campo_imagen('imagen', 'Foto de la máquina (portada)', $v['imagen'] ?: null) ?>
            <div class="campo"><label>Vista sin foto</label><div style="height:110px;border-radius:12px;overflow:hidden"><?= portada_video($v + ['imagen' => '']) ?></div></div>
          </div>
        </div>
        <div class="ad-caja mt2"><h2><?= icono('money') ?> Venta</h2>
          <div class="campos">
            <?= campo('precio', 'Precio para desbloquear (COP)', (int)$v['precio'], 'text', ['inputmode' => 'numeric']) ?>
            <?= campo('orden', 'Orden', $v['orden'], 'number') ?>
            <?= campo('gratis', 'Video gratis (lo ve cualquiera: sirve para atraer clientes)', $v['gratis'], 'checkbox', ['ancho' => true]) ?>
            <?= campo('visible', 'Mostrar en la biblioteca', $v['visible'], 'checkbox', ['ancho' => true]) ?>
          </div>
        </div>
        <div class="ad-caja mt2"><h2><?= icono('scale') ?> Carga sugerida</h2>
          <p class="sec">El sistema sugiere: <b>peso del cliente × coeficiente</b>, ajustado por nivel (principiante 60%, avanzado 130%), objetivo y edad, y redondeado al incremento de la máquina. El coeficiente es la fracción del peso corporal que mueve un hombre intermedio en 10-12 repeticiones. Deja 0 si el ejercicio es con peso corporal.</p>
          <div class="campos" style="grid-template-columns:repeat(3,1fr)">
            <?= campo('coef_hombre', 'Coeficiente hombre', (float)$v['coef_hombre'], 'text', ['inputmode' => 'decimal', 'placeholder' => '1.6']) ?>
            <?= campo('coef_mujer', 'Coeficiente mujer', (float)$v['coef_mujer'], 'text', ['inputmode' => 'decimal', 'placeholder' => '1.2']) ?>
            <?= campo('incremento', 'Saltos de peso (kg)', (float)$v['incremento'], 'text', ['inputmode' => 'decimal']) ?>
            <?= campo('nota_carga', 'Qué peso se cuenta', $v['nota_carga'], 'text', ['ancho' => true]) ?>
          </div>
          <div class="ad-nota mt1" id="pruebaCarga"></div>
        </div>
      </div>
    </div>
    <div class="ad-acciones mt2" style="justify-content:flex-start"><button class="btn btn-rojo" type="submit">Guardar</button><a class="btn btn-borde" href="<?= url('admin/?p=videos') ?>">Volver</a><?php if ($id): ?><a class="btn btn-borde" target="_blank" href="<?= url('maquina.php?s=' . urlencode($v['slug'])) ?>"><?= icono('eye') ?> Ver en la web</a><?php endif; ?></div>
    </form>
    <?php if ($id): ?><form method="post" class="mt2" data-confirmar="¿Borrar esta máquina y su video?"><?= csrf_campo() ?><input type="hidden" name="acc" value="borrar"><button class="btn btn-sm btn-borde" type="submit"><?= icono('trash') ?> Borrar</button></form><?php endif; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      var f = function () {
        var o = { coef_hombre: document.getElementById('c_coef_hombre').value.replace(',', '.'), coef_mujer: document.getElementById('c_coef_mujer').value.replace(',', '.'), incremento: document.getElementById('c_incremento').value.replace(',', '.') };
        var h = window.bjCarga && window.bjCarga(o, 75, 'M', 'principiante', 'hipertrofia', 30), m = window.bjCarga && window.bjCarga(o, 60, 'F', 'principiante', 'hipertrofia', 30), hi = window.bjCarga && window.bjCarga(o, 75, 'M', 'intermedio', 'hipertrofia', 30);
        document.getElementById('pruebaCarga').innerHTML = h ? 'Ejemplos: hombre de 75 kg principiante → <b>' + h.kg + ' kg</b> · intermedio → <b>' + hi.kg + ' kg</b> · mujer de 60 kg principiante → <b>' + (m ? m.kg : '—') + ' kg</b>' : 'Sin coeficiente: no se sugiere carga.';
      };
      ['c_coef_hombre', 'c_coef_mujer', 'c_incremento'].forEach(function (i) { document.getElementById(i).addEventListener('input', f); }); f();
    });
    </script>
    <?php admin_pie(); return;
}
$vids = filas('SELECT v.*, (SELECT COUNT(*) FROM accesos_video a WHERE a.video_id = v.id) AS ventas FROM videos v ORDER BY v.orden, v.id');
admin_cabecera('videos', 'Videos y máquinas');
?>
<div class="ad-barra"><input type="search" placeholder="Buscar..." data-filtrar="#tablaVideos"><div class="der"><a class="btn btn-sm btn-borde" href="<?= url('maquinas.php') ?>" target="_blank"><?= icono('eye') ?> Ver biblioteca</a><a class="btn btn-sm btn-rojo" href="<?= url('admin/?p=videos&nuevo=1') ?>"><?= icono('plus') ?> Agregar máquina</a></div></div>
<div class="ad-nota">Cada máquina que agregues es un producto que puedes vender. Los videos <b>gratis</b> atraen visitas; los <b>premium</b> se desbloquean con pago o con un plan que incluya videos.</div>
<div class="ad-caja ad-tabla-envoltura"><table class="ad-tabla" id="tablaVideos">
  <thead><tr><th></th><th>Máquina / ejercicio</th><th>Categoría</th><th>Video</th><th class="num">Precio</th><th class="num">Vistas</th><th class="num">Ventas</th><th>En la web</th><th></th></tr></thead><tbody>
  <?php foreach ($vids as $v): ?>
    <tr><td style="width:90px"><div style="width:80px;height:56px;border-radius:8px;overflow:hidden"><?= portada_video($v) ?></div></td>
      <td><b><?= e($v['titulo']) ?></b><br><span class="sec"><?= e(implode(', ', array_map('nombre_musculo', array_filter(explode(',', (string)$v['musculos']))))) ?></span></td>
      <td><?= e($v['categoria']) ?></td>
      <td><?= $v['video_url'] ? '<span class="chip chip-ok">Enlace</span>' : ($v['video_archivo'] ? '<span class="chip chip-ok">Archivo</span>' : '<span class="chip chip-alerta">Falta grabar</span>') ?></td>
      <td class="num"><?= $v['gratis'] ? '<span class="chip chip-volt">Gratis</span>' : pesos($v['precio']) ?></td>
      <td class="num"><?= (int)$v['vistas'] ?></td><td class="num"><?= (int)$v['ventas'] ?></td>
      <td><?= $v['visible'] ? '<span class="chip chip-ok">Sí</span>' : '<span class="chip">No</span>' ?></td>
      <td><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=videos&id=' . $v['id']) ?>"><?= icono('edit') ?> Editar</a></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php admin_pie();
