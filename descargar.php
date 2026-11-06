<?php
/** Descarga de catálogos. Si el catálogo pide datos, solo descarga después de dejar nombre y WhatsApp. */
require __DIR__ . '/inc/app.php';
iniciar_sesion();
$c = fila("SELECT * FROM contenidos WHERE id = ? AND tipo = 'catalogo' AND visible = 1", [(int)($_GET['id'] ?? 0)]);
if (!$c || !$c['archivo']) { http_response_code(404); exit('Catálogo no encontrado.'); }
if ($c['pide_datos'] && empty($_SESSION['descargas'][$c['id']]) && !usuario()) redirigir(url('#catalogos'));
// Archivos subidos desde el panel (uploads/) o el catálogo original incluido con la web (catalogos/)
$ruta = realpath(APP_ROOT . '/uploads/' . $c['archivo']) ?: realpath(APP_ROOT . '/' . $c['archivo']);
if (!$ruta || !str_starts_with(str_replace('\\', '/', $ruta), APP_ROOT . '/') || !is_file($ruta)) { http_response_code(404); exit('Archivo no disponible.'); }
q('UPDATE contenidos SET vistas = vistas + 1 WHERE id = ?', [$c['id']]);
if (!$c['pide_datos']) evento('descarga', 'catalogo', $c['titulo']);
$nombre = slugify($c['titulo']) . '.' . pathinfo($ruta, PATHINFO_EXTENSION);
header('Content-Type: ' . ((new finfo(FILEINFO_MIME_TYPE))->file($ruta) ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: attachment; filename="' . $nombre . '"');
readfile($ruta);
