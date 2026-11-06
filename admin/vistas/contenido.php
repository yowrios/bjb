<?php
/** Contenido de la web: artículos, testimonios, transformaciones, preguntas, redes, catálogos y recomendaciones. */
$tipos = [
    'articulo' => ['Artículos', 'book', 'Guías educativas que aparecen en «Aprende». Atraen visitas desde Google.'],
    'social' => ['Instagram y TikTok', 'instagram', 'Pega el enlace de una publicación y aparece en la sección «En redes» de la portada.'],
    'transformacion' => ['Antes y después', 'camera', 'Fotos de antes y después de tus clientes (con su autorización por escrito).'],
    'testimonio' => ['Testimonios', 'star', 'Lo que dicen tus clientes reales. Solo publica testimonios verdaderos y con permiso.'],
    'faq' => ['Preguntas frecuentes', 'help', 'Responde las dudas que más te hacen por WhatsApp.'],
    'catalogo' => ['Catálogos y PDF', 'download', 'Archivos que tus visitantes pueden descargar (puedes pedir su WhatsApp antes).'],
    'recomendacion' => ['Recomendaciones', 'heart', 'Consejos que ven todos tus clientes en su portal. Para uno solo, hazlo desde su ficha.'],
];
$tipo = isset($tipos[$_GET['tipo'] ?? '']) ? $_GET['tipo'] : 'articulo';
$id = (int)($_GET['id'] ?? 0);
if (es_post()) {
    csrf_verificar();
    try {
        if (post('acc') === 'guardar') {
            $act = $id ? fila('SELECT * FROM contenidos WHERE id = ?', [$id]) : null;
            $d = ['tipo' => $tipo, 'titulo' => post('titulo'), 'resumen' => post('resumen'), 'cuerpo' => post('cuerpo'), 'url' => post('url'), 'categoria' => post('categoria'),
                'visible' => post('visible') ? 1 : 0, 'orden' => post_int('orden'), 'pide_datos' => post('pide_datos') ? 1 : 0];
            if ($tipo === 'social') {
                if (!preg_match('~^https://(www\.)?(instagram\.com/(p|reel|tv)/[\w-]+|tiktok\.com/@[\w.]+/video/\d+)~', $d['url'])) throw new RuntimeException('Pega el enlace completo de la publicación (instagram.com/p/... o tiktok.com/@usuario/video/...).');
                $d['url'] = strtok($d['url'], '?'); $d['categoria'] = str_contains($d['url'], 'tiktok') ? 'tiktok' : 'instagram'; $d['titulo'] = ucfirst($d['categoria']);
            } elseif ($d['titulo'] === '') throw new RuntimeException('Escribe el título.');
            if (in_array($tipo, ['articulo', 'testimonio', 'transformacion'], true)) $d['imagen'] = imagen_post('imagen', $act['imagen'] ?? null);
            if ($tipo === 'transformacion') $d['imagen2'] = imagen_post('imagen2', $act['imagen2'] ?? null);
            if ($tipo === 'catalogo') {
                $pdf = subir_archivo($_FILES['archivo'] ?? ['error' => UPLOAD_ERR_NO_FILE], TIPOS_PDF + TIPOS_IMAGEN, false, 'catalogos', 40);
                if ($pdf) $d['archivo'] = $pdf; elseif (!$act || !$act['archivo']) throw new RuntimeException('Sube el archivo PDF.');
            }
            if ($tipo === 'articulo') $d['slug'] = $act['slug'] ?? (slugify(post('titulo')) . (valor('SELECT COUNT(*) FROM contenidos WHERE slug = ?', [slugify(post('titulo'))]) ? '-' . substr(token(2), 0, 4) : ''));
            if ($act) actualizar('contenidos', $id, $d); else { $d['creado'] = ahora(); $id = insertar('contenidos', $d); }
            flash('Guardado y publicado' . ($d['visible'] ? '' : ' (oculto)') . '.');
            redirigir(url('admin/?p=contenido&tipo=' . $tipo));
        } elseif (post('acc') === 'borrar' && $id) {
            q('DELETE FROM contenidos WHERE id = ?', [$id]); flash('Borrado.');
        } elseif (post('acc') === 'visible') {
            q('UPDATE contenidos SET visible = 1 - visible WHERE id = ?', [post_int('cid')]);
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'error'); redirigir(url('admin/?p=contenido&tipo=' . $tipo . ($id ? '&id=' . $id : '&nuevo=1'))); }
    redirigir(url('admin/?p=contenido&tipo=' . $tipo));
}

if ($id || isset($_GET['nuevo'])) {
    $c = $id ? fila('SELECT * FROM contenidos WHERE id = ?', [$id]) : ['titulo' => '', 'resumen' => '', 'cuerpo' => '', 'url' => '', 'categoria' => '', 'imagen' => '', 'imagen2' => '', 'archivo' => '', 'visible' => 1, 'orden' => 10, 'pide_datos' => 1, 'slug' => ''];
    admin_cabecera('contenido', ($id ? 'Editar · ' : 'Nuevo · ') . $tipos[$tipo][0]);
    ?>
    <form method="post" enctype="multipart/form-data" class="ad-caja" style="max-width:980px"><?= csrf_campo() ?><input type="hidden" name="acc" value="guardar">
      <div class="ad-nota"><?= e($tipos[$tipo][2]) ?></div>
      <div class="campos">
      <?php if ($tipo === 'articulo'): ?>
        <?= campo('titulo', 'Título *', $c['titulo'], 'text', ['required' => true, 'ancho' => true]) ?>
        <?= campo('categoria', 'Categoría', $c['categoria'], 'text', ['placeholder' => 'Nutrición, Entrenamiento, Mitos...']) ?><?= campo('orden', 'Orden', $c['orden'], 'number') ?>
        <?= campo('resumen', 'Resumen (1 o 2 frases)', $c['resumen'], 'textarea', ['ancho' => true, 'filas' => 2]) ?>
        <?= campo('cuerpo', 'Texto del artículo', $c['cuerpo'], 'textarea', ['ancho' => true, 'filas' => 18, 'ayuda' => 'Formato sencillo: <b>## Subtítulo</b> en su propia línea · <b>- </b> al inicio para listas · <b>**negrilla**</b> · <b>&gt; </b> para un consejo destacado. Deja una línea en blanco entre párrafos.']) ?>
        <?= campo_imagen('imagen', 'Imagen de portada (opcional)', $c['imagen'] ?: null) ?>
      <?php elseif ($tipo === 'social'): ?>
        <?= campo('url', 'Enlace de la publicación *', $c['url'], 'url', ['required' => true, 'ancho' => true, 'placeholder' => 'https://www.instagram.com/p/XXXX/  o  https://www.tiktok.com/@usuario/video/123']) ?>
        <?= campo('orden', 'Orden (menor = primero)', $c['orden'], 'number') ?>
        <div class="ad-nota ancho">En Instagram: abre la publicación › ⋯ › <b>Copiar enlace</b>. En TikTok: <b>Compartir › Copiar enlace</b> (abre el enlace en el navegador y copia la dirección completa que tiene /video/). En la portada se muestran las primeras 9 visibles.</div>
      <?php elseif ($tipo === 'transformacion'): ?>
        <?= campo('titulo', 'Nombre o título *', $c['titulo'], 'text', ['required' => true, 'placeholder' => 'Andrés · 12 semanas']) ?><?= campo('resumen', 'Resultado', $c['resumen'], 'text', ['placeholder' => '−9 kg y −11 cm de cintura con Hard Gold']) ?>
        <?= campo_imagen('imagen', 'Foto ANTES *', $c['imagen'] ?: null) ?><?= campo_imagen('imagen2', 'Foto DESPUÉS *', $c['imagen2'] ?: null) ?>
        <?= campo('orden', 'Orden', $c['orden'], 'number') ?>
        <div class="ad-nota ancho">Usa la misma pose, luz y distancia en las dos fotos. <b>Pide autorización escrita</b> del cliente antes de publicarlas.</div>
      <?php elseif ($tipo === 'testimonio'): ?>
        <?= campo('titulo', 'Nombre del cliente *', $c['titulo'], 'text', ['required' => true]) ?><?= campo('resumen', 'Detalle', $c['resumen'], 'text', ['placeholder' => 'Cliente Hard Gold desde 2025']) ?>
        <?= campo('cuerpo', 'Testimonio', $c['cuerpo'], 'textarea', ['ancho' => true]) ?><?= campo_imagen('imagen', 'Foto (opcional)', $c['imagen'] ?: null) ?><?= campo('orden', 'Orden', $c['orden'], 'number') ?>
      <?php elseif ($tipo === 'faq'): ?>
        <?= campo('titulo', 'Pregunta *', $c['titulo'], 'text', ['required' => true, 'ancho' => true]) ?><?= campo('cuerpo', 'Respuesta', $c['cuerpo'], 'textarea', ['ancho' => true]) ?><?= campo('orden', 'Orden', $c['orden'], 'number') ?>
      <?php elseif ($tipo === 'catalogo'): ?>
        <?= campo('titulo', 'Título *', $c['titulo'], 'text', ['required' => true, 'ancho' => true]) ?><?= campo('resumen', 'Descripción corta', $c['resumen'], 'text', ['ancho' => true]) ?>
        <div class="campo ancho"><label>Archivo (PDF o imagen, máx. 40 MB)</label><?= $c['archivo'] ? '<span class="chip chip-ok">Archivo cargado ✓</span> <a href="' . url('descargar.php?id=' . $id) . '" style="color:var(--rojo)">ver</a>' : '' ?><input type="file" name="archivo" accept="application/pdf,image/*"></div>
        <?= campo('pide_datos', 'Pedir nombre y WhatsApp antes de descargar (te llega como interesado)', $c['pide_datos'], 'checkbox', ['ancho' => true]) ?><?= campo('orden', 'Orden', $c['orden'], 'number') ?>
      <?php elseif ($tipo === 'recomendacion'): ?>
        <?= campo('titulo', 'Título *', $c['titulo'], 'text', ['required' => true, 'ancho' => true]) ?><?= campo('cuerpo', 'Consejo', $c['cuerpo'], 'textarea', ['ancho' => true]) ?><?= campo('orden', 'Orden', $c['orden'], 'number') ?>
      <?php endif; ?>
        <?= campo('visible', 'Publicado (visible)', $c['visible'], 'checkbox', ['ancho' => true]) ?>
      </div>
      <div class="ad-acciones mt2" style="justify-content:flex-start"><button class="btn btn-rojo" type="submit">Guardar</button><a class="btn btn-borde" href="<?= url('admin/?p=contenido&tipo=' . $tipo) ?>">Cancelar</a>
        <?php if ($id && $tipo === 'articulo'): ?><a class="btn btn-borde" target="_blank" href="<?= url('articulo.php?s=' . urlencode($c['slug'])) ?>"><?= icono('eye') ?> Ver</a><?php endif; ?></div>
    </form>
    <?php if ($id): ?><form method="post" class="mt2" data-confirmar="¿Borrar definitivamente?"><?= csrf_campo() ?><input type="hidden" name="acc" value="borrar"><button class="btn btn-sm btn-borde" type="submit"><?= icono('trash') ?> Borrar</button></form><?php endif; ?>
    <?php admin_pie(); return;
}
$lista = filas("SELECT * FROM contenidos WHERE tipo = ? AND (usuario_id IS NULL) ORDER BY orden, id DESC", [$tipo]);
$cuentas = []; foreach (filas('SELECT tipo, COUNT(*) AS n FROM contenidos WHERE usuario_id IS NULL GROUP BY tipo') as $r) $cuentas[$r['tipo']] = $r['n'];
admin_cabecera('contenido', 'Contenido de la web');
?>
<nav class="ad-pestanas"><?php foreach ($tipos as $k => [$tt, $ic]): ?><a class="<?= $tipo === $k ? 'on' : '' ?>" href="<?= url('admin/?p=contenido&tipo=' . $k) ?>"><?= e($tt) ?> <em><?= (int)($cuentas[$k] ?? 0) ?></em></a><?php endforeach; ?></nav>
<div class="ad-barra"><div class="ad-nota" style="margin:0;flex:1"><?= e($tipos[$tipo][2]) ?> <?= $tipo === 'social' ? 'Textos de la portada, cifras y método: <a href="' . url('admin/?p=ajustes&s=textos') . '" style="color:#fff">Ajustes › Textos</a>.' : '' ?></div><div class="der"><a class="btn btn-sm btn-rojo" href="<?= url('admin/?p=contenido&tipo=' . $tipo . '&nuevo=1') ?>"><?= icono('plus') ?> Agregar</a></div></div>
<div class="ad-caja ad-tabla-envoltura"><table class="ad-tabla"><thead><tr><th></th><th>Título</th><th>Detalle</th><th class="num"><?= $tipo === 'catalogo' ? 'Descargas' : ($tipo === 'articulo' ? 'Lecturas' : 'Orden') ?></th><th>Estado</th><th></th></tr></thead><tbody>
  <?php foreach ($lista as $c): ?>
    <tr><td style="width:70px"><?php if ($c['imagen']): ?><img src="<?= e(img_publica($c['imagen'])) ?>" alt="" style="width:60px;height:44px;object-fit:cover;border-radius:8px"><?php else: ?><span class="btn-icono"><?= icono($tipos[$tipo][1]) ?></span><?php endif; ?></td>
      <td><b><?= e($tipo === 'social' ? $c['url'] : $c['titulo']) ?></b></td>
      <td class="sec" style="max-width:380px"><?= e(mb_strimwidth((string)($c['resumen'] ?: $c['cuerpo'] ?: $c['categoria']), 0, 120, '…')) ?></td>
      <td class="num"><?= in_array($tipo, ['catalogo', 'articulo'], true) ? (int)$c['vistas'] : (int)$c['orden'] ?></td>
      <td><form method="post"><?= csrf_campo() ?><input type="hidden" name="acc" value="visible"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><button type="submit" class="chip <?= $c['visible'] ? 'chip-ok' : '' ?>" style="cursor:pointer" title="Cambiar"><?= $c['visible'] ? 'Publicado' : 'Oculto' ?></button></form></td>
      <td><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=contenido&tipo=' . $tipo . '&id=' . $c['id']) ?>"><?= icono('edit') ?> Editar</a></td></tr>
  <?php endforeach; ?>
  <?php if (!$lista): ?><tr><td colspan="6" class="ad-vacio">Nada todavía. <a href="<?= url('admin/?p=contenido&tipo=' . $tipo . '&nuevo=1') ?>" style="color:var(--rojo)">Agrega el primero</a>.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php admin_pie();
