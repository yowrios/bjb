<?php
/** Datos de la app instalable: toma el nombre y el logo que se configuren en el panel. */
require __DIR__ . '/inc/app.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$marca = (string)ajuste('marca', 'BJB');
echo json_encode([
    'name' => $marca . ' · ' . ajuste('eslogan', 'Built by discipline'),
    'short_name' => mb_substr($marca, 0, 12),
    'description' => 'Cronómetro de entreno, tus rutinas y tu evolución.',
    'start_url' => url('cronometro.php?app=1'),
    'scope' => url(''),
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#0a0a0c',
    'theme_color' => '#0a0a0c',
    'lang' => 'es-CO',
    'icons' => [
        ['src' => icono_app(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ['src' => icono_app(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
    ],
    'shortcuts' => [['name' => 'Cronómetro', 'url' => url('cronometro.php')], ['name' => 'Mi portal', 'url' => url('cliente/')]],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
