<?php
/** Contacto descargable (.vcf): al abrirlo en el celular se guarda Bryan en la agenda. */
require __DIR__ . '/inc/app.php';
$esc = fn($s) => str_replace([',', ';', "\n"], ['\,', '\;', ' '], (string)$s);
$nombre = (string)ajuste('nombre_entrenador');
$partes = explode(' ', $nombre, 2);
$v = "BEGIN:VCARD\r\nVERSION:3.0\r\n"
    . 'N:' . $esc($partes[1] ?? '') . ';' . $esc($partes[0]) . ";;;\r\n"
    . 'FN:' . $esc($nombre) . "\r\n"
    . 'ORG:' . $esc(ajuste('marca')) . "\r\n"
    . 'TITLE:' . $esc('Entrenador personal') . "\r\n"
    . 'TEL;TYPE=CELL:+' . wa_numero(ajuste('whatsapp')) . "\r\n"
    . (ajuste('email') ? 'EMAIL:' . $esc(ajuste('email')) . "\r\n" : '')
    . 'URL:' . url_publica('') . "\r\n"
    . (ajuste('instagram') ? 'X-SOCIALPROFILE;TYPE=instagram:https://instagram.com/' . $esc(ajuste('instagram')) . "\r\n" : '')
    . 'NOTE:' . $esc(ajuste('eslogan')) . "\r\n"
    . "END:VCARD\r\n";
header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="' . slugify($nombre ?: 'contacto') . '.vcf"');
echo $v;
