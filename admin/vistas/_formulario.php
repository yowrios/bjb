<?php
/** Muestra un formulario inicial ($f) de forma legible. */
$d = json_decode((string)$f['datos'], true) ?: [];
$fotosF = json_decode((string)$f['fotos'], true) ?: [];
$secciones = [
    'Información personal' => ['nombre' => 'Nombre', 'whatsapp' => 'WhatsApp', 'email' => 'Correo', 'fecha_nacimiento' => 'Nacimiento', 'sexo' => 'Sexo', 'peso' => 'Peso (kg)', 'estatura' => 'Estatura (cm)',
        'objetivo' => 'Objetivo', 'hora_despertar' => 'Despierta', 'hora_dormir' => 'Duerme', 'hora_entrenar' => 'Entrena a las', 'horario_laboral' => 'Horario laboral', 'veces_semana' => 'Veces por semana',
        'modalidad' => 'Modalidad', 'alergias_patologias' => 'Alergias / patologías', 'plan_interes' => 'Programa de interés'],
    'Nutrición' => ['frutas' => 'Frutas', 'vegetales' => 'Vegetales', 'comida_saludable' => 'Comida saludable favorita', 'comida_chatarra' => 'Comida chatarra', 'azucar' => 'Azúcar', 'no_puede' => 'No puede consumir', 'dulce' => 'Dulce favorito', 'suplementos' => 'Suplementos'],
    'Entrenamiento' => ['nivel' => 'Nivel', 'tiempo_entrenando' => 'Tiempo entrenando', 'lesion' => 'Lesiones', 'ejercicio_dificil' => 'Ejercicio difícil', 'cardiovascular' => 'Cardiovascular', 'esteroides' => 'Esteroides', 'deporte_alterno' => 'Deporte alterno', 'mejorar' => 'Quiere mejorar'],
];
?>
<div class="ad-caja mt2" id="form<?= (int)$f['id'] ?>">
  <h2><?= icono('clipboard') ?> Formulario inicial de <?= e($f['nombre']) ?> <span class="der"><?= fecha_corta($f['creado']) ?></span></h2>
  <div class="ad-rejilla-3">
    <?php foreach ($secciones as $tit => $campos): ?>
      <div><h3 style="font-size:15px;color:var(--rojo);text-transform:uppercase;letter-spacing:.08em"><?= e($tit) ?></h3>
        <?php foreach ($campos as $k => $et): if (($d[$k] ?? '') === '') continue; ?><div class="ad-dato"><span><?= e($et) ?></span><span><?= nl2br(e($k === 'sexo' ? ($d[$k] === 'F' ? 'Mujer' : 'Hombre') : $d[$k])) ?></span></div><?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="ad-rejilla mt2">
    <div><h3 style="font-size:15px;color:var(--rojo);text-transform:uppercase;letter-spacing:.08em">Comidas</h3>
      <table class="ad-tabla"><thead><tr><th></th><th>Entre semana</th><th>Fin de semana</th></tr></thead><tbody>
        <?php for ($i = 1; $i <= 6; $i++): if (empty($d["semana_comida_$i"]) && empty($d["finde_comida_$i"])) continue; ?>
          <tr><td><b><?= $i ?></b></td><td><?= e($d["semana_comida_$i"] ?? '') ?> <span class="sec"><?= e($d["semana_hora_$i"] ?? '') ?></span></td><td><?= e($d["finde_comida_$i"] ?? '') ?> <span class="sec"><?= e($d["finde_hora_$i"] ?? '') ?></span></td></tr>
        <?php endfor; ?></tbody></table></div>
    <div><h3 style="font-size:15px;color:var(--rojo);text-transform:uppercase;letter-spacing:.08em">Frecuencia de consumo</h3>
      <?php $fr = $d['frecuencia'] ?? []; $grupos = []; foreach ($fr as $al => $v) $grupos[$v][] = $al; ?>
      <?php foreach (['Diario', 'Semanal', 'Mensual', 'Nunca'] as $g): if (empty($grupos[$g])) continue; ?><div class="ad-dato"><span><?= $g ?></span><span><?= e(implode(', ', $grupos[$g])) ?></span></div><?php endforeach; ?>
      <?php if (!$fr): ?><p class="sec">Sin datos.</p><?php endif; ?></div>
  </div>
  <?php if ($fotosF): ?><div class="galeria mt2" style="max-width:520px"><?php foreach ($fotosF as $n => $ff): ?><a href="<?= e(url('media.php?t=formulario&id=' . $f['id'] . '&n=' . $n)) ?>" target="_blank"><img src="<?= e(url('media.php?t=formulario&id=' . $f['id'] . '&n=' . $n)) ?>" alt="" loading="lazy"></a><?php endforeach; ?></div><?php endif; ?>
</div>
