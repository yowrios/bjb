<?php
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';

$planes = filas("SELECT * FROM planes WHERE visible = 1 ORDER BY orden, id");
$maquinas = filas("SELECT * FROM videos WHERE visible = 1 ORDER BY orden, id");
$articulos = filas("SELECT * FROM contenidos WHERE tipo = 'articulo' AND visible = 1 ORDER BY orden, id DESC LIMIT 3");
$faq = filas("SELECT * FROM contenidos WHERE tipo = 'faq' AND visible = 1 ORDER BY orden, id");
$sociales = filas("SELECT * FROM contenidos WHERE tipo = 'social' AND visible = 1 ORDER BY orden, id LIMIT 9");
$testimonios = filas("SELECT * FROM contenidos WHERE tipo = 'testimonio' AND visible = 1 ORDER BY orden, id");
$transf = filas("SELECT * FROM contenidos WHERE tipo = 'transformacion' AND visible = 1 AND imagen <> '' AND imagen2 <> '' ORDER BY orden, id");
$catalogos = filas("SELECT * FROM contenidos WHERE tipo = 'catalogo' AND visible = 1 ORDER BY orden, id");
$paquetes = filas('SELECT p.*, (SELECT COUNT(*) FROM paquete_videos pv WHERE pv.paquete_id = p.id) AS n FROM paquetes p WHERE p.visible = 1 ORDER BY p.orden, p.id LIMIT 3');
$productos = tienda_activa() ? filas('SELECT * FROM productos WHERE visible = 1 ORDER BY destacado DESC, orden, id LIMIT 4') : [];
$cifras = ajuste_json('cifras');
$metodo = ajuste_json('metodo');
$wa = ajuste('whatsapp');
$ig = ajuste('instagram');
$tt = ltrim((string)ajuste('tiktok'), '@');
$iconosMetodo = ['clipboard', 'target', 'dumbbell', 'chart'];
$datosMaq = array_map(fn($m) => ['id' => $m['id'], 'slug' => $m['slug'], 'titulo' => $m['titulo'], 'coef_hombre' => $m['coef_hombre'], 'coef_mujer' => $m['coef_mujer'], 'incremento' => $m['incremento'], 'nota' => $m['nota_carga']], array_filter($maquinas, fn($m) => (float)$m['coef_hombre'] > 0));

cabecera('', ['activo' => 'inicio', 'body' => 'portada']);
?>
<section class="hero">
  <div class="hero-fondo" aria-hidden="true">EVOLUCIÓN</div>
  <div class="hero-in">
    <div>
      <div class="hero-pre aparece"><span class="punto"></span><?= e(ajuste('hero_pre')) ?></div>
      <?php $t = preg_split('/\s+/', trim((string)ajuste('hero_titulo', 'TU EVOLUCIÓN EMPIEZA HOY'))); $mitad = (int)ceil(count($t) / 2); ?>
      <h1 class="aparece d1"><?= e(implode(' ', array_slice($t, 0, $mitad))) ?> <span class="l2"><?= e(implode(' ', array_slice($t, $mitad))) ?></span></h1>
      <p class="hero-texto aparece d2"><?= e(ajuste('hero_texto')) ?></p>
      <div class="hero-ctas aparece d3">
        <a class="btn btn-rojo" href="#planes"><?= icono('flame') ?> Ver planes</a>
        <a class="btn btn-borde" href="<?= e(wa_link($wa, ajuste('whatsapp_texto'))) ?>" target="_blank" rel="noopener" data-ev="whatsapp:hero"><?= icono('whatsapp') ?> Hablar con Bryan</a>
      </div>
      <div class="hero-chips aparece d3">
        <span><?= icono('check') ?> Presencial y online</span>
        <span><?= icono('check') ?> Nutrición incluida</span>
        <span><?= icono('check') ?> Tu progreso en línea</span>
        <?php if (ajuste('gym_nombre')): ?><a href="#gimnasio" style="color:inherit;text-decoration:none"><span><?= icono('target') ?> <?= e(ajuste('gym_nombre')) ?></span></a><?php endif; ?>
      </div>
    </div>
    <div class="hero-foto">
      <img src="<?= imagen_marca('foto_hero') ?>" alt="<?= e(ajuste('nombre_entrenador')) ?>, entrenador personal" width="411" height="1283" fetchpriority="high">
      <div class="hero-tarjeta t1"><span class="ic"><?= icono('target') ?></span><span><b>Plan a tu medida</b>Según tu objetivo</span></div>
      <div class="hero-tarjeta t2"><span class="ic"><?= icono('chart') ?></span><span><b>Mide tu evolución</b>Fotos, medidas y gráficas</span></div>
    </div>
  </div>
  <a href="#cifras" class="scroll-hint" aria-label="Bajar"></a>
</section>

<?php $palabras = lineas(ajuste('franja')) ?: ['Mentalidad', 'Disciplina', 'Hábitos', 'Nutrición', 'Constancia', 'Fuerza', 'Tu evolución']; ?>
<div class="franja" role="img" aria-label="<?= e(implode(', ', $palabras)) ?>"><div class="franja-in">
  <?php for ($i = 0; $i < 4; $i++): foreach ($palabras as $n => $w): ?><span><em><?= str_pad((string)($n + 1), 2, '0', STR_PAD_LEFT) ?></em><?= e($w) ?></span><?php endforeach; endfor; ?>
</div></div>

<section class="seccion" id="cifras" style="padding-top:90px;padding-bottom:40px">
  <div class="envoltura">
    <div class="cifras">
      <?php foreach ($cifras as $c): ?>
        <div class="cifra aparece"><b data-contar="<?= e($c[0]) ?>"><?= e($c[0]) ?></b><span><?= e($c[1]) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="seccion" id="metodo">
  <div class="envoltura">
    <div class="cabeza-sec">
      <span class="antetitulo">El método</span>
      <h2 class="titulo">Así funciona <span class="marca">tu evolución</span></h2>
      <p class="sub">No es una rutina copiada de internet. Es un proceso con datos, acompañamiento y ajustes constantes.</p>
    </div>
    <div class="metodo">
      <?php foreach ($metodo as $i => $m): ?>
        <div class="paso aparece d<?= $i % 4 ?>">
          <div class="ic"><?= icono($iconosMetodo[$i] ?? 'check') ?></div>
          <h3><?= e($m[0]) ?></h3>
          <p><?= e($m[1]) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="seccion seccion-textura" id="planes">
  <div class="envoltura">
    <div class="cabeza-sec centro">
      <span class="antetitulo">Programas</span>
      <h2 class="titulo">Elige tu <span class="hueco">nivel</span></h2>
      <p class="sub">Todos incluyen valoración, plan personalizado, asesoría en nutrición y acceso a tu portal de evolución.</p>
    </div>
    <div class="planes">
      <?php foreach ($planes as $i => $p): $inc = lineas($p['incluye']); ?>
        <article class="plan aparece d<?= $i % 3 ?> <?= $p['destacado'] ? 'destacado' : '' ?>" id="plan-<?= e($p['slug']) ?>">
          <div class="plan-fondo" aria-hidden="true"><?= e(explode(' ', $p['nombre'])[0]) ?></div>
          <span class="plan-etiqueta"><?= e($p['etiqueta'] ?: 'Programa') ?></span>
          <h3><?= e($p['nombre']) ?></h3>
          <p class="plan-sub"><?= e($p['subtitulo']) ?></p>
          <div class="plan-precio">
            <?php if ((float)$p['precio'] > 0): ?><b><?= pesos($p['precio']) ?></b><?php if ((float)$p['precio_antes'] > (float)$p['precio']): ?><s><?= pesos($p['precio_antes']) ?></s><?php endif; ?>
            <?php else: ?><b style="font-size:40px">Consúltalo</b><?php endif; ?>
          </div>
          <div class="plan-meta">
            <?php if ($p['sesiones']): ?><span class="chip"><?= icono('dumbbell') ?> <?= (int)$p['sesiones'] ?> sesiones</span><?php endif; ?>
            <?php if ($p['frecuencia']): ?><span class="chip"><?= icono('calendar') ?> <?= e($p['frecuencia']) ?></span><?php endif; ?>
            <?php if ($p['incluye_videos']): ?><span class="chip chip-volt"><?= icono('video') ?> Videos incluidos</span><?php endif; ?>
          </div>
          <p class="plan-desc"><?= e($p['descripcion']) ?></p>
          <ul><?php foreach ($inc as $l): ?><li><?= icono('check') ?><span><?= e($l) ?></span></li><?php endforeach; ?></ul>
          <div class="acciones">
            <a class="btn <?= $p['destacado'] ? 'btn-rojo' : 'btn-borde' ?>" href="<?= url('pagar.php?plan=' . urlencode($p['slug'])) ?>" data-ev="plan:<?= e($p['slug']) ?>">Lo quiero</a>
            <a class="btn btn-sm" style="color:var(--txt-2)" href="<?= e(wa_link($wa, 'Hola Bryan, quiero información del programa ' . $p['nombre'] . '.')) ?>" target="_blank" rel="noopener" data-ev="whatsapp:plan-<?= e($p['slug']) ?>"><?= icono('whatsapp') ?> Preguntar por WhatsApp</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php $bens = beneficios(); if ($bens): ?>
    <div class="beneficios"><?php foreach ($bens as [$ic, $tit, $txt]): ?><div class="beneficio aparece"><span><?= $ic ?></span><div><b><?= e($tit) ?></b><p><?= e($txt) ?></p></div></div><?php endforeach; ?></div>
    <?php endif; ?>
    <p class="nota-precios">Precios en pesos colombianos. ¿No sabes cuál elegir? <a href="<?= url('formulario.php') ?>" style="color:var(--rojo)">Llena el formulario inicial</a> y Bryan te recomienda el tuyo.</p>
  </div>
</section>

<section class="seccion" id="calculadoras">
  <div class="envoltura">
    <div class="cabeza-sec">
      <span class="antetitulo">Herramientas gratis</span>
      <h2 class="titulo">Conoce tus <span class="marca">números</span></h2>
      <p class="sub">Calcula tu índice de masa corporal, tus calorías y proteína diarias, o la carga con la que deberías empezar en cada máquina.</p>
    </div>
    <div class="calc aparece">
      <div class="calc-form">
        <div class="pestanas" role="tablist">
          <button type="button" class="on" data-tab="imc" role="tab" aria-selected="true">IMC</button>
          <button type="button" data-tab="calorias" role="tab" aria-selected="false">Calorías y proteína</button>
          <button type="button" data-tab="carga" role="tab" aria-selected="false">Carga en máquina</button>
        </div>
        <div class="campos">
          <div class="campo"><label for="cPeso">Peso (kg)</label><input id="cPeso" type="number" inputmode="decimal" min="25" max="300" step="0.1" placeholder="Ej: 72"></div>
          <div class="campo"><label for="cAltura">Estatura (cm)</label><input id="cAltura" type="number" inputmode="numeric" min="120" max="230" placeholder="Ej: 172"></div>
          <div class="campo"><label for="cEdad">Edad</label><input id="cEdad" type="number" inputmode="numeric" min="12" max="95" placeholder="Ej: 30"></div>
          <div class="campo"><span class="etq">Sexo</span>
            <div class="opciones"><label><input type="radio" name="cSexo" value="M" checked><span>Hombre</span></label><label><input type="radio" name="cSexo" value="F"><span>Mujer</span></label></div>
          </div>
          <div class="campo ancho oculto" data-panel="calorias"><label for="cActividad">Actividad diaria</label>
            <select id="cActividad"><option value="1.2">Sedentaria (oficina, poco movimiento)</option><option value="1.375">Ligera (entreno 1 a 3 días)</option><option value="1.55" selected>Moderada (entreno 3 a 5 días)</option><option value="1.725">Alta (entreno 6 a 7 días)</option><option value="1.9">Muy alta (trabajo físico + entreno)</option></select>
          </div>
          <div class="campo ancho oculto" data-panel="calorias"><span class="etq">Objetivo</span>
            <div class="opciones"><label><input type="radio" name="cObjetivo" value="perder" checked><span>Perder grasa</span></label><label><input type="radio" name="cObjetivo" value="mantener"><span>Mantenerme</span></label><label><input type="radio" name="cObjetivo" value="ganar"><span>Ganar músculo</span></label></div>
          </div>
          <div class="campo ancho oculto" data-panel="carga"><label for="cMaquina">Máquina</label>
            <select id="cMaquina"><?php foreach ($datosMaq as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['titulo']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="campo oculto" data-panel="carga"><label for="cNivel">Tu nivel</label>
            <select id="cNivel"><option value="principiante">Principiante (menos de 6 meses)</option><option value="intermedio">Intermedio (6 meses a 2 años)</option><option value="avanzado">Avanzado (más de 2 años)</option></select>
          </div>
          <div class="campo oculto" data-panel="carga"><label for="cMeta">Objetivo</label>
            <select id="cMeta"><option value="hipertrofia">Ganar músculo</option><option value="fuerza">Fuerza</option><option value="grasa">Quemar grasa</option><option value="resistencia">Resistencia</option></select>
          </div>
        </div>
      </div>
      <div class="calc-res" id="calcRes" aria-live="polite"></div>
    </div>
    <script type="application/json" id="datosMaquinas"><?= json_encode(array_values($datosMaq), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
  </div>
</section>

<section class="seccion seccion-oscura">
  <div class="envoltura">
    <div class="herramientas">
      <div class="herr herr-crono aparece">
        <svg class="anillo-demo" viewBox="0 0 160 160" aria-hidden="true"><circle class="fondo" cx="80" cy="80" r="70"/><circle class="barra" cx="80" cy="80" r="70"/><text x="80" y="94">0:45</text></svg>
        <span class="antetitulo">Sin descargar nada</span>
        <h3>Cronómetro de entreno</h3>
        <p>Ábrelo en el celular desde el gimnasio: tiempos de trabajo y descanso, rondas, aviso con sonido, voz y vibración, y alerta si te quedaste en pausa.</p>
        <div><a class="btn btn-rojo" href="<?= url('cronometro.php') ?>" data-ev="cronometro:portada"><?= icono('timer') ?> Abrir cronómetro</a></div>
      </div>
      <div class="herr herr-portal aparece d1">
        <svg class="mini-grafica" viewBox="0 0 210 120" aria-hidden="true"><path d="M5 100 C40 95 50 70 80 72 S120 40 150 38 190 12 205 10"/></svg>
        <span class="antetitulo" style="color:var(--volt)">Tu portal</span>
        <h3>Mira tu evolución</h3>
        <ul class="lista-check">
          <li><?= icono('check') ?> Desde cuándo entrenas y cuántas sesiones llevas</li>
          <li><?= icono('check') ?> Peso y medidas en gráficas, fotos de antes y después</li>
          <li><?= icono('check') ?> Tus rutinas con videos y carga sugerida</li>
        </ul>
        <div><a class="btn btn-volt" href="<?= url('ingresar.php') ?>"><?= icono('user') ?> Ingresar</a></div>
      </div>
    </div>
  </div>
</section>

<section class="seccion" id="maquinas">
  <div class="envoltura">
    <div class="cabeza-sec">
      <span class="antetitulo">Biblioteca de máquinas</span>
      <h2 class="titulo">Aprende a usar <span class="marca">cada máquina</span></h2>
      <p class="sub">¿No sabes cómo usar la prensa? Desbloquea el video, mira los pasos y los errores comunes, y recibe la carga sugerida para tu peso.</p>
    </div>
    <?php if ($paquetes): ?>
    <div class="paquetes">
      <?php foreach ($paquetes as $pq): ?>
        <a class="paquete aparece" href="<?= url('maquinas.php#paquete-' . $pq['slug']) ?>">
          <span class="chip chip-volt"><?= icono('video') ?> Paquete · <?= (int)$pq['n'] ?> videos + rutina</span>
          <h3><?= e($pq['nombre']) ?></h3>
          <p><?= e($pq['descripcion']) ?></p>
          <div class="paquete-precio"><b><?= pesos($pq['precio']) ?></b><?php if ((float)$pq['precio_antes'] > (float)$pq['precio']): ?><s><?= pesos($pq['precio_antes']) ?></s><?php endif; ?><span class="btn btn-rojo btn-sm">Ver paquete</span></div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="maquinas">
      <?php foreach (array_slice($maquinas, 0, 8) as $i => $m): ?>
        <a class="maq aparece d<?= $i % 4 ?>" href="<?= url('maquina.php?s=' . urlencode($m['slug'])) ?>">
          <div class="maq-img">
            <span class="maq-estado"><?= $m['gratis'] ? '<span class="chip chip-ok">' . icono('play') . ' Gratis</span>' : '<span class="chip chip-rojo">' . icono('lock') . ' Premium</span>' ?></span>
            <?= portada_video($m) ?>
          </div>
          <div class="maq-cuerpo">
            <h3><?= e($m['titulo']) ?></h3>
            <span class="musc"><?= e(implode(' · ', array_map('nombre_musculo', array_filter(explode(',', (string)$m['musculos']))))) ?></span>
            <div class="maq-pie"><span><?= $m['gratis'] ? 'Ver video' : ((float)$m['precio'] > 0 ? pesos($m['precio']) : 'Con tu plan') ?></span><?= icono('arrow') ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="centro mt3"><a class="btn btn-borde" href="<?= url('maquinas.php') ?>">Ver todas las máquinas <?= icono('arrow') ?></a></div>
  </div>
</section>

<section class="seccion seccion-oscura" id="sobre">
  <div class="envoltura sobre">
    <div class="sobre-foto aparece">
      <img src="<?= imagen_marca('foto_sobre') ?>" alt="<?= e(ajuste('nombre_entrenador')) ?>" loading="lazy" width="572" height="1037">
      <span class="firma"><?= e(ajuste('marca')) ?></span>
    </div>
    <div class="sobre-texto aparece d1">
      <span class="antetitulo">Tu entrenador</span>
      <h2 class="titulo"><?= e(ajuste('sobre_titulo')) ?></h2>
      <div class="credenciales">
        <span class="chip"><?= icono('book') ?> Ciencias del deporte</span>
        <span class="chip"><?= icono('dumbbell') ?> Acondicionamiento físico</span>
        <span class="chip"><?= icono('heart') ?> Nutrición deportiva</span>
        <span class="chip"><?= icono('bolt') ?> Entrenamiento deportivo</span>
      </div>
      <?php foreach (preg_split("/\n{2,}/", (string)ajuste('sobre_texto')) as $par): ?><p><?= e(trim($par)) ?></p><?php endforeach; ?>
      <div class="hero-ctas mt2">
        <a class="btn btn-rojo" href="<?= url('formulario.php') ?>"><?= icono('clipboard') ?> Empezar mi valoración</a>
        <?php if ($ig): ?><a class="btn btn-borde" href="https://instagram.com/<?= e($ig) ?>" target="_blank" rel="noopener" data-ev="red:instagram"><?= icono('instagram') ?> @<?= e($ig) ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php if (ajuste('gym_nombre')): $dir = (string)ajuste('gym_direccion'); ?>
<section class="seccion" id="gimnasio">
  <div class="envoltura gym">
    <div class="aparece">
      <span class="antetitulo">Dónde entrenamos</span>
      <h2 class="titulo" style="font-size:clamp(36px,5vw,60px)"><?= e(ajuste('gym_nombre')) ?></h2>
      <?php if (ajuste('gym_logo')): ?><img class="gym-logo" src="<?= e(img_publica(ajuste('gym_logo'))) ?>" alt="<?= e(ajuste('gym_nombre')) ?>" loading="lazy"><?php endif; ?>
      <p class="sub"><?= e(ajuste('gym_texto')) ?></p>
      <?php if ($dir): ?><p style="color:var(--txt-2)"><?= icono('target') ?> <?= e($dir) ?></p><?php endif; ?>
      <p style="color:var(--txt-2)"><?= icono('calendar') ?> <?= e(ajuste('horario')) ?></p>
      <div class="hero-ctas mt2">
        <?php if ($dir): ?><a class="btn btn-borde" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($dir) ?>"><?= icono('globe') ?> Cómo llegar</a><?php endif; ?>
        <a class="btn btn-rojo" href="<?= e(wa_link($wa, 'Hola Bryan, quiero agendar una clase en ' . ajuste('gym_nombre') . '.')) ?>" target="_blank" rel="noopener" data-ev="whatsapp:gimnasio"><?= icono('whatsapp') ?> Agendar clase</a>
      </div>
    </div>
    <?php if ($dir && ajuste('gym_mapa', '1') === '1'): ?>
      <div class="gym-mapa aparece d1"><iframe title="Mapa" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=<?= rawurlencode($dir) ?>&output=embed"></iframe></div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($productos): ?>
<section class="seccion seccion-oscura" id="tienda">
  <div class="envoltura">
    <div class="cabeza-sec"><span class="antetitulo">Tienda</span><h2 class="titulo"><?= e(ajuste('tienda_titulo')) ?></h2><p class="sub"><?= e(ajuste('tienda_texto')) ?></p></div>
    <div class="productos"><?php foreach ($productos as $pr) echo tarjeta_producto($pr); ?></div>
    <div class="centro mt3"><a class="btn btn-borde" href="<?= url('tienda.php') ?>">Ver toda la tienda <?= icono('arrow') ?></a></div>
  </div>
</section>
<?php endif; ?>

<?php if ($transf): ?>
<section class="seccion" id="transformaciones">
  <div class="envoltura">
    <div class="cabeza-sec centro"><span class="antetitulo">Resultados reales</span><h2 class="titulo">Transformaciones</h2><p class="sub">Desliza para ver el antes y el después.</p></div>
    <div class="rejilla-3">
      <?php foreach ($transf as $t): ?>
        <figure class="aparece" style="margin:0">
          <div class="antes-despues">
            <img src="<?= e(img_publica($t['imagen'])) ?>" alt="Antes" loading="lazy">
            <img class="despues" src="<?= e(img_publica($t['imagen2'])) ?>" alt="Después" loading="lazy">
            <span class="etq-a">ANTES</span><span class="etq-d">DESPUÉS</span><div class="divisor"></div>
            <input type="range" min="0" max="100" value="50" aria-label="Comparar antes y después">
          </div>
          <figcaption class="mt1"><b><?= e($t['titulo']) ?></b><br><span style="color:var(--txt-2);font-size:14px"><?= e($t['resumen']) ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($testimonios): ?>
<section class="seccion <?= $transf ? 'seccion-oscura' : '' ?>" id="testimonios">
  <div class="envoltura">
    <div class="cabeza-sec centro"><span class="antetitulo">Lo que dicen</span><h2 class="titulo">Testimonios</h2></div>
    <div class="rejilla-3">
      <?php foreach ($testimonios as $t): ?>
        <blockquote class="testimonio aparece" style="margin:0">
          <div class="estrellas">★★★★★</div>
          <p><?= e($t['cuerpo']) ?></p>
          <div class="quien"><?php if ($t['imagen']): ?><img src="<?= e(img_publica($t['imagen'])) ?>" alt="" loading="lazy"><?php endif; ?><div><b><?= e($t['titulo']) ?></b><br><span style="color:var(--txt-2);font-size:13px"><?= e($t['resumen']) ?></span></div></div>
        </blockquote>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php $redesActivas = ajuste('mostrar_instagram', '1') === '1' || ajuste('mostrar_tiktok', '1') === '1'; if ($redesActivas && ($sociales || $ig || $tt)): ?>
<section class="seccion" id="redes">
  <div class="envoltura">
    <div class="cabeza-sec centro">
      <span class="antetitulo">En redes</span>
      <h2 class="titulo">Entrena <span class="marca">conmigo</span></h2>
      <p class="sub">Rutinas, consejos y el día a día del entrenamiento en Instagram<?= $tt ? ' y TikTok' : '' ?>.</p>
    </div>
    <?php if ($sociales): ?>
    <div class="redes-grid" id="redesGrid">
      <?php foreach ($sociales as $s):
        $esTT = str_contains((string)$s['url'], 'tiktok.com');
        if ($esTT && ajuste('mostrar_tiktok', '1') !== '1') continue;
        if (!$esTT && ajuste('mostrar_instagram', '1') !== '1') continue; ?>
        <div class="red-item">
          <?php if ($esTT && preg_match('~/video/(\d+)~', (string)$s['url'], $mm)): ?>
            <blockquote class="tiktok-embed" cite="<?= e($s['url']) ?>" data-video-id="<?= e($mm[1]) ?>"><section><a class="red-carga" href="<?= e($s['url']) ?>" target="_blank" rel="noopener"><span><?= icono('tiktok') ?>Ver en TikTok</span></a></section></blockquote>
          <?php else: ?>
            <blockquote class="instagram-media" data-instgrm-permalink="<?= e($s['url']) ?>?utm_source=ig_embed" data-instgrm-version="14"><a class="red-carga" href="<?= e($s['url']) ?>" target="_blank" rel="noopener"><span><?= icono('instagram') ?>Ver publicación en Instagram</span></a></blockquote>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="redes-ctas">
      <?php if ($ig): ?><a class="btn btn-rojo" href="https://instagram.com/<?= e($ig) ?>" target="_blank" rel="noopener" data-ev="red:instagram"><?= icono('instagram') ?> Seguir en Instagram</a><?php endif; ?>
      <?php if ($tt): ?><a class="btn btn-borde" href="https://www.tiktok.com/@<?= e($tt) ?>" target="_blank" rel="noopener" data-ev="red:tiktok"><?= icono('tiktok') ?> Seguir en TikTok</a><?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($articulos): ?>
<section class="seccion seccion-oscura" id="aprende">
  <div class="envoltura">
    <div class="cabeza-sec"><span class="antetitulo">Aprende</span><h2 class="titulo">Entrena con <span class="marca">conocimiento</span></h2><p class="sub">Guías cortas y claras sobre entrenamiento, nutrición y hábitos.</p></div>
    <div class="rejilla-3">
      <?php foreach ($articulos as $a): ?>
        <a class="art aparece" href="<?= url('articulo.php?s=' . urlencode($a['slug'])) ?>">
          <div class="art-img"><?= $a['imagen'] ? '<img src="' . e(img_publica($a['imagen'])) . '" alt="" loading="lazy">' : '<div class="art-gen"><span>' . e($a['categoria']) . '</span></div>' ?></div>
          <div class="art-cuerpo"><span class="chip" style="margin-bottom:10px"><?= e($a['categoria']) ?></span><h3><?= e($a['titulo']) ?></h3><p><?= e($a['resumen']) ?></p></div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="centro mt3"><a class="btn btn-borde" href="<?= url('aprende.php') ?>">Ver todos los artículos <?= icono('arrow') ?></a></div>
  </div>
</section>
<?php endif; ?>

<?php if ($catalogos): ?>
<section class="seccion" id="catalogos" style="padding-bottom:40px">
  <div class="envoltura">
    <div class="cabeza-sec"><span class="antetitulo">Descargas</span><h2 class="titulo">Catálogos</h2><p class="sub">Llévate la información completa de los programas o compártela con alguien que la necesite.</p></div>
    <div class="catalogos">
      <?php foreach ($catalogos as $c): ?>
        <div class="catalogo aparece"><span class="ic">PDF</span><div><h3><?= e($c['titulo']) ?></h3><p><?= e($c['resumen']) ?></p></div>
          <?php if ($c['pide_datos']): ?><button class="btn btn-rojo btn-sm" type="button" data-modal="modalDescarga" data-modal-valor="<?= (int)$c['id'] ?>"><?= icono('download') ?> Descargar</button>
          <?php else: ?><a class="btn btn-rojo btn-sm" href="<?= url('descargar.php?id=' . (int)$c['id']) ?>"><?= icono('download') ?> Descargar</a><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="catalogo aparece"><span class="ic" style="background:var(--volt);color:#0a0a0c">WEB</span><div><h3>Catálogo siempre actualizado</h3><p>Planes y precios vigentes, listo para imprimir o guardar en PDF.</p></div><a class="btn btn-borde btn-sm" href="<?= url('catalogo.php') ?>">Abrir</a></div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($faq): ?>
<section class="seccion" id="preguntas">
  <div class="envoltura">
    <div class="cabeza-sec centro"><span class="antetitulo">Resolvemos tus dudas</span><h2 class="titulo">Preguntas frecuentes</h2></div>
    <div class="faq">
      <?php foreach ($faq as $f): ?><details class="aparece"><summary><?= e($f['titulo']) ?></summary><p><?= e($f['cuerpo']) ?></p></details><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="cta-final">
  <div class="fondo-txt" aria-hidden="true">BJ</div>
  <div class="envoltura" style="position:relative">
    <h2><?= e(ajuste('cta_final')) ?></h2>
    <p><?= e(ajuste('cta_final_texto')) ?></p>
    <div class="hero-ctas" style="justify-content:center">
      <a class="btn btn-negro" href="<?= url('formulario.php') ?>"><?= icono('clipboard') ?> Llenar formulario inicial</a>
      <a class="btn btn-borde" href="<?= e(wa_link($wa, ajuste('whatsapp_texto'))) ?>" target="_blank" rel="noopener" data-ev="whatsapp:final"><?= icono('whatsapp') ?> Escribir a Bryan</a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/inc/modales.php'; ?>
<?php pie(); ?>
