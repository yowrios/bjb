<?php
/** Formulario inicial en línea (versión web del PDF "Formulario Inicial BJFIT"). */
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
iniciar_sesion();
$u = usuario();
$ok = false; $error = '';
$alimentos = ['Leche de vaca', 'Kumis o yogurt', 'Queso', 'Carne', 'Pollo', 'Pescado, atún', 'Cerdo', 'Vísceras', 'Embutidos', 'Huevo', 'Leguminosas', 'Verduras', 'Frutas', 'Pan', 'Arroz', 'Pasta', 'Papa', 'Plátano', 'Productos de paquete', 'Comidas rápidas', 'Dulces', 'Gaseosas', 'Margarina / mantequilla', 'Aceite', 'Agua'];
$planes = filas("SELECT nombre FROM planes WHERE visible = 1 ORDER BY orden");

if (es_post()) {
    csrf_verificar();
    $d = [];
    foreach ($_POST as $k => $v) {
        if (in_array($k, ['_csrf', 'clave', 'crear_cuenta'], true)) continue;
        $d[$k] = is_array($v) ? array_map(fn($x) => mb_substr((string)$x, 0, 500), $v) : mb_substr(trim((string)$v), 0, 3000);
    }
    $nombre = $d['nombre'] ?? '';
    $tel = $d['whatsapp'] ?? '';
    if ($nombre === '' || strlen(solo_digitos($tel)) < 7) $error = 'Escribe tu nombre y tu WhatsApp (paso 1).';
    elseif (empty($_POST['acepto'])) $error = 'Debes autorizar el tratamiento de tus datos.';
    elseif (intentos_superados('formulario')) $error = 'Demasiados envíos. Intenta más tarde.';
    else {
        registrar_intento('formulario');
        $fotos = [];
        try {
            foreach (['foto_frente', 'foto_perfil', 'foto_espalda'] as $f) {
                if (!empty($_FILES[$f]['name'])) { $r = subir_archivo($_FILES[$f], TIPOS_IMAGEN, true, 'formularios', 15); if ($r) $fotos[] = $r; }
            }
        } catch (RuntimeException $ex) { $error = 'Foto: ' . $ex->getMessage(); }
        if (!$error) {
            $uid = $u['id'] ?? null;
            // Crear cuenta si lo pidió
            if (!$uid && !empty($_POST['crear_cuenta']) && filter_var($d['email'] ?? '', FILTER_VALIDATE_EMAIL) && mb_strlen((string)($_POST['clave'] ?? '')) >= 8) {
                $email = mb_strtolower($d['email']);
                if (!valor('SELECT COUNT(*) FROM usuarios WHERE LOWER(email) = ?', [$email])) {
                    $uid = insertar('usuarios', ['rol' => 'cliente', 'nombre' => $nombre, 'email' => $email, 'telefono' => $tel, 'clave_hash' => password_hash((string)$_POST['clave'], PASSWORD_DEFAULT),
                        'activo' => 1, 'debe_cambiar' => 0, 'origen' => 'formulario inicial', 'consentimiento' => ahora(), 'creado' => ahora()]);
                    login_usuario(fila('SELECT * FROM usuarios WHERE id = ?', [$uid]));
                }
            }
            // Si la misma persona ya dejó sus datos (catálogo, calculadora), se actualiza en vez de duplicarla
            $datosLead = ['nombre' => $nombre, 'telefono' => $tel, 'email' => $d['email'] ?? '', 'origen' => 'formulario', 'interes' => $d['plan_interes'] ?? '',
                'mensaje' => $d['objetivo'] ?? '', 'visitante' => substr((string)($_COOKIE['bj_v'] ?? ''), 0, 24), 'usuario_id' => $uid, 'actualizado' => ahora()];
            $previo = null;
            foreach (filas("SELECT id, telefono FROM leads WHERE estado IN ('nuevo','contactado') ORDER BY id DESC LIMIT 300") as $x)
                if (substr(solo_digitos($x['telefono']), -10) === substr(solo_digitos($tel), -10)) { $previo = $x; break; }
            if ($previo) { actualizar('leads', (int)$previo['id'], $datosLead); $leadId = (int)$previo['id']; }
            else $leadId = insertar('leads', $datosLead + ['datos' => '', 'estado' => 'nuevo', 'creado' => ahora()]);
            insertar('formularios', ['usuario_id' => $uid, 'lead_id' => $leadId, 'nombre' => $nombre, 'datos' => json_encode($d, JSON_UNESCAPED_UNICODE), 'fotos' => json_encode($fotos), 'creado' => ahora(), 'revisado' => 0]);
            if ($uid) {
                $act = [];
                if (!empty($d['fecha_nacimiento'])) $act['fecha_nacimiento'] = substr($d['fecha_nacimiento'], 0, 10);
                if (in_array($d['sexo'] ?? '', ['M', 'F'], true)) $act['sexo'] = $d['sexo'];
                if (num($d['estatura'] ?? null)) $act['estatura_cm'] = num($d['estatura']);
                if (!empty($d['objetivo'])) $act['objetivo'] = $d['objetivo'];
                if (!empty($d['nivel'])) $act['nivel'] = $d['nivel'];
                if (!empty($d['modalidad'])) $act['modalidad'] = $d['modalidad'];
                actualizar('usuarios', (int)$uid, $act);
                if (num($d['peso'] ?? null)) insertar('medidas', ['usuario_id' => $uid, 'fecha' => hoy(), 'peso' => num($d['peso']), 'nota' => 'Formulario inicial', 'por' => 'cliente', 'creado' => ahora()]);
                foreach ($fotos as $i => $f) insertar('fotos', ['usuario_id' => $uid, 'fecha' => hoy(), 'tipo' => ['frente', 'perfil', 'espalda'][$i] ?? 'otra', 'archivo' => $f, 'nota' => 'Formulario inicial', 'por' => 'cliente', 'creado' => ahora()]);
            }
            evento('lead', 'formulario', $d['plan_interes'] ?? '');
            $ok = true;
        }
    }
}
cabecera('Formulario inicial', ['body' => 'pagina', 'desc' => 'Cuéntale a Bryan tu objetivo, tus hábitos y tu nivel para diseñar tu plan de entrenamiento y alimentación.']);
$v = fn($k, $def = '') => e($_POST[$k] ?? $def);
?>
<section class="cabeza-pag" style="padding-bottom:30px">
  <div class="envoltura" style="max-width:860px">
    <div class="migas"><a href="<?= url('') ?>">Inicio</a> / Formulario inicial</div>
    <h1 class="titulo">Formulario <span class="marca">inicial</span></h1>
    <p class="sub">Contesta con la mayor sinceridad posible: así conozco tus hábitos, tu nivel de entrenamiento y tus condiciones, y tu plan de entrenamiento y alimentación lo desarrollo basándome en tus respuestas. Toma unos 8 minutos y se guarda solo mientras lo llenas.</p>
  </div>
</section>
<section class="seccion" style="padding-top:10px">
  <div class="envoltura" style="max-width:860px">
  <?php if ($ok): ?>
    <div class="caja centro">
      <div class="bloqueo" style="position:static;background:none;padding:0"><div class="candado" style="background:var(--volt);color:#0a0a0c"><?= icono('check') ?></div></div>
      <h2 class="titulo" style="font-size:54px">¡Lo vamos a lograr!</h2>
      <p class="sub" style="margin-inline:auto">Recibí tu formulario. Voy a revisar tus respuestas y en los próximos días te enviaré tu plan y me pondré en contacto contigo. A partir de este momento me comprometo contigo, pero esto solo funciona si TÚ también te comprometes.</p>
      <div class="hero-ctas" style="justify-content:center">
        <a class="btn btn-wa" target="_blank" rel="noopener" href="<?= e(wa_link(ajuste('whatsapp'), 'Hola Bryan, soy ' . ($_POST['nombre'] ?? '') . '. Acabo de enviar el formulario inicial en tu página.')) ?>" data-ev="whatsapp:formulario"><?= icono('whatsapp') ?> Avisarle a Bryan</a>
        <?php if (usuario()): ?><a class="btn btn-rojo" href="<?= url('cliente/') ?>">Ir a mi portal</a><?php else: ?><a class="btn btn-borde" href="<?= url('#planes') ?>">Ver planes</a><?php endif; ?>
      </div>
    </div>
    <script>try { localStorage.removeItem('bj_formulario'); } catch (e) {}</script>
  <?php else: ?>
    <?php if ($error): ?><div class="msg msg-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="caja" id="formIni" novalidate>
      <?= csrf_campo() ?>
      <div class="progreso-pasos" aria-hidden="true"><i class="on"></i><i></i><i></i><i></i><i></i><i></i></div>

      <div class="paso-form on" data-paso="1">
        <span class="antetitulo">Paso 1 de 6</span><h2>Información personal</h2>
        <div class="campos">
          <div class="campo ancho"><label for="f_nombre">Nombre completo *</label><input id="f_nombre" name="nombre" required value="<?= $v('nombre', $u['nombre'] ?? '') ?>"></div>
          <div class="campo"><label for="f_wa">WhatsApp (con indicativo) *</label><input id="f_wa" name="whatsapp" type="tel" required placeholder="57 300 123 4567" value="<?= $v('whatsapp', $u['telefono'] ?? '') ?>"></div>
          <div class="campo"><label for="f_email">Correo</label><input id="f_email" name="email" type="email" value="<?= $v('email', $u['email'] ?? '') ?>"></div>
          <div class="campo"><label for="f_nac">Fecha de nacimiento</label><input id="f_nac" name="fecha_nacimiento" type="date" value="<?= $v('fecha_nacimiento', $u['fecha_nacimiento'] ?? '') ?>"></div>
          <div class="campo"><span class="etq">Sexo</span><div class="opciones"><label><input type="radio" name="sexo" value="M" <?= ($_POST['sexo'] ?? $u['sexo'] ?? '') === 'M' ? 'checked' : '' ?>><span>Hombre</span></label><label><input type="radio" name="sexo" value="F" <?= ($_POST['sexo'] ?? $u['sexo'] ?? '') === 'F' ? 'checked' : '' ?>><span>Mujer</span></label></div></div>
          <div class="campo"><label for="f_peso">Peso (kg)</label><input id="f_peso" name="peso" type="number" inputmode="decimal" step="0.1" value="<?= $v('peso') ?>"></div>
          <div class="campo"><label for="f_est">Estatura (cm)</label><input id="f_est" name="estatura" type="number" inputmode="numeric" value="<?= $v('estatura', $u['estatura_cm'] ?? '') ?>"></div>
          <div class="campo ancho"><label for="f_obj">Describe cuál es tu objetivo al recibir mi asesoría personalizada</label><textarea id="f_obj" name="objetivo"><?= $v('objetivo') ?></textarea></div>
          <div class="campo"><label for="f_desp">Hora de despertar</label><input id="f_desp" name="hora_despertar" type="time" value="<?= $v('hora_despertar') ?>"></div>
          <div class="campo"><label for="f_dorm">Hora de dormir</label><input id="f_dorm" name="hora_dormir" type="time" value="<?= $v('hora_dormir') ?>"></div>
          <div class="campo"><label for="f_ent">Hora de entrenar</label><input id="f_ent" name="hora_entrenar" type="time" value="<?= $v('hora_entrenar') ?>"></div>
          <div class="campo"><label for="f_lab">Horario laboral</label><input id="f_lab" name="horario_laboral" placeholder="Ej: 8 a. m. a 5 p. m." value="<?= $v('horario_laboral') ?>"></div>
          <div class="campo"><label for="f_veces">¿Cuántas veces por semana vas a entrenar?</label><select id="f_veces" name="veces_semana"><?php for ($i = 1; $i <= 7; $i++): ?><option <?= ($_POST['veces_semana'] ?? '3') == $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select></div>
          <div class="campo"><span class="etq">Modalidad que prefieres</span><div class="opciones"><label><input type="radio" name="modalidad" value="presencial" checked><span>Presencial</span></label><label><input type="radio" name="modalidad" value="virtual"><span>Virtual</span></label></div></div>
          <div class="campo ancho"><label for="f_pat">¿Tienes alguna alergia o patología que deba saber?</label><input id="f_pat" name="alergias_patologias" value="<?= $v('alergias_patologias') ?>"></div>
          <div class="campo ancho"><label for="f_plan">¿Qué programa te interesa?</label><select id="f_plan" name="plan_interes"><option value="">Aún no sé, recomiéndame</option><?php foreach ($planes as $p): ?><option <?= ($_POST['plan_interes'] ?? ($_GET['plan'] ?? '')) === $p['nombre'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option><?php endforeach; ?></select></div>
        </div>
      </div>

      <div class="paso-form" data-paso="2">
        <span class="antetitulo">Paso 2 de 6</span><h2>Información nutricional</h2>
        <div class="campos">
          <?php foreach (['frutas' => '¿Cuáles son tus FRUTAS preferidas?', 'vegetales' => '¿Cuáles son tus VEGETALES preferidos?', 'comida_saludable' => '¿Cuál es tu comida saludable favorita?', 'comida_chatarra' => '¿Cuál es tu comida CHATARRA favorita y con qué frecuencia la comes?', 'azucar' => '¿Te hace falta el AZÚCAR? Si es así, ¿cuántas veces al día la consumes?', 'no_puede' => '¿Qué alimento no puedes consumir y por qué razón?', 'dulce' => '¿Cuál es tu comida DULCE favorita y con qué frecuencia la comes?', 'suplementos' => '¿Consumes SUPLEMENTOS? ¿Cuáles?'] as $k => $t): ?>
            <div class="campo ancho"><label for="f_<?= $k ?>"><?= e($t) ?></label><input id="f_<?= $k ?>" name="<?= $k ?>" value="<?= $v($k) ?>"></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="paso-form" data-paso="3">
        <span class="antetitulo">Paso 3 de 6</span><h2>Tu alimentación en un día normal</h2>
        <p class="sub" style="font-size:15px">Describe lo más detallado posible qué comes, si puedes con cantidades.</p>
        <div class="pestanas" style="max-width:420px" id="tabsComida"><button type="button" class="on" data-c="semana">Entre semana</button><button type="button" data-c="finde">Fin de semana</button></div>
        <?php foreach (['semana' => 'Entre semana', 'finde' => 'Fin de semana'] as $c => $tt): ?>
          <div class="campos <?= $c === 'finde' ? 'oculto' : '' ?>" data-comida="<?= $c ?>">
            <?php for ($i = 1; $i <= 6; $i++): ?>
              <div class="campo" style="grid-column:span 1"><label for="f_<?= $c ?>_<?= $i ?>">Comida <?= $i ?></label><input id="f_<?= $c ?>_<?= $i ?>" name="<?= $c ?>_comida_<?= $i ?>" placeholder="¿Qué comes?" value="<?= $v($c . '_comida_' . $i) ?>"></div>
              <div class="campo"><label for="f_<?= $c ?>_h<?= $i ?>">¿A qué hora?</label><input id="f_<?= $c ?>_h<?= $i ?>" name="<?= $c ?>_hora_<?= $i ?>" type="time" value="<?= $v($c . '_hora_' . $i) ?>"></div>
            <?php endfor; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="paso-form" data-paso="4">
        <span class="antetitulo">Paso 4 de 6</span><h2>Frecuencia de consumo</h2>
        <p class="sub" style="font-size:15px">¿Con qué frecuencia consumes cada alimento?</p>
        <div style="overflow-x:auto">
          <table class="tabla-frec">
            <thead><tr><th>Alimento</th><th>Nunca</th><th>Mensual</th><th>Semanal</th><th>Diario</th></tr></thead>
            <tbody><?php foreach ($alimentos as $i => $a): ?><tr><td><?= e($a) ?></td><?php foreach (['Nunca', 'Mensual', 'Semanal', 'Diario'] as $op): ?><td><input type="radio" name="frecuencia[<?= e($a) ?>]" value="<?= $op ?>" aria-label="<?= e($a . ': ' . $op) ?>" <?= (($_POST['frecuencia'][$a] ?? '') === $op) ? 'checked' : '' ?>></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
          </table>
        </div>
      </div>

      <div class="paso-form" data-paso="5">
        <span class="antetitulo">Paso 5 de 6</span><h2>Entrenamiento</h2>
        <div class="campos">
          <div class="campo ancho"><span class="etq">Tu nivel de entrenamiento</span><div class="opciones"><?php foreach (['principiante' => 'Novato', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'] as $k => $t): ?><label><input type="radio" name="nivel" value="<?= $k ?>" <?= ($_POST['nivel'] ?? '') === $k ? 'checked' : '' ?>><span><?= $t ?></span></label><?php endforeach; ?></div></div>
          <?php foreach (['tiempo_entrenando' => '¿Cuánto tiempo llevas entrenando?', 'lesion' => '¿Tienes alguna lesión que deba saber?', 'ejercicio_dificil' => '¿Hay algún ejercicio que se te dificulte o no puedas hacer?', 'cardiovascular' => '¿Sufres de algún problema cardiovascular?', 'esteroides' => '¿Usas o has usado algún tipo de esteroide anabólico? Si es así, cuál y cómo', 'deporte_alterno' => '¿Practicas algún deporte alterno? ¿Cuál y cuántas veces por semana?', 'mejorar' => '¿Qué parte de tu cuerpo te gustaría más mejorar y por qué?'] as $k => $t): ?>
            <div class="campo ancho"><label for="f_<?= $k ?>"><?= e($t) ?></label><input id="f_<?= $k ?>" name="<?= $k ?>" value="<?= $v($k) ?>"></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="paso-form" data-paso="6">
        <span class="antetitulo">Paso 6 de 6</span><h2>Tus fotos <small style="font-family:var(--cuerpo);font-size:14px;color:var(--volt)">(opcional)</small></h2>
        <p class="sub" style="font-size:15px">3 fotos en traje de baño o con prendas que permitan ver tu punto físico actual. Buena iluminación y la cámara a la altura del pecho. Solo las vemos tú y Bryan.</p>
        <div class="fotos-guia">
          <?php foreach (['foto_frente' => 'Frente', 'foto_perfil' => 'Perfil', 'foto_espalda' => 'Espalda'] as $k => $t): ?>
            <label class="foto-slot"><?= icono('camera') ?><b><?= $t ?></b><span>Toca para subir</span><input type="file" name="<?= $k ?>" accept="image/*" capture="environment"></label>
          <?php endforeach; ?>
        </div>
        <?php if (!$u): ?>
        <div class="tarjeta mt2" style="background:var(--negro)">
          <label class="check"><input type="checkbox" name="crear_cuenta" value="1" id="crearCuenta" checked> <span><b style="color:var(--txt)">Crear mi cuenta para ver mi evolución</b><br>Con tu correo y una contraseña entras a tu portal: plan, rutinas, medidas y fotos.</span></label>
          <div class="campo mt1" id="campoClave"><label for="f_clave">Contraseña (mínimo 8 caracteres)</label><input id="f_clave" name="clave" type="password" minlength="8" autocomplete="new-password"></div>
        </div>
        <?php endif; ?>
        <label class="check mt2"><input type="checkbox" name="acepto" value="1" required> <span>Autorizo de forma expresa el tratamiento de mis datos personales, incluidos los datos de salud y las fotos, solo para diseñar y hacer seguimiento a mi plan, según la <a href="<?= url('privacidad.php') ?>" target="_blank" style="color:var(--rojo)">política de datos</a>.</span></label>
      </div>

      <div class="nav-pasos">
        <button class="btn btn-borde" type="button" id="bAtras" style="visibility:hidden">Atrás</button>
        <button class="btn btn-rojo" type="button" id="bSig">Siguiente <?= icono('arrow') ?></button>
        <button class="btn btn-rojo oculto" type="submit" id="bEnviar"><?= icono('send') ?> Enviar a Bryan</button>
      </div>
    </form>
  <?php endif; ?>
  </div>
</section>
<script>
(function () {
  var f = document.getElementById('formIni'); if (!f) return;
  var pasos = f.querySelectorAll('.paso-form'), barras = f.querySelectorAll('.progreso-pasos i'), n = 0;
  function ir(i) {
    n = Math.max(0, Math.min(pasos.length - 1, i));
    pasos.forEach(function (p, k) { p.classList.toggle('on', k === n); });
    barras.forEach(function (b, k) { b.classList.toggle('on', k <= n); });
    document.getElementById('bAtras').style.visibility = n ? 'visible' : 'hidden';
    document.getElementById('bSig').classList.toggle('oculto', n === pasos.length - 1);
    document.getElementById('bEnviar').classList.toggle('oculto', n !== pasos.length - 1);
    f.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  document.getElementById('bSig').addEventListener('click', function () {
    if (n === 0) { var nom = f.nombre.value.trim(), wa = f.whatsapp.value.replace(/\D/g, ''); if (!nom || wa.length < 7) { alert('Escribe tu nombre y tu WhatsApp para continuar.'); return; } }
    ir(n + 1);
  });
  document.getElementById('bAtras').addEventListener('click', function () { ir(n - 1); });
  f.addEventListener('submit', function (e) {
    if (!f.acepto.checked) { e.preventDefault(); alert('Debes autorizar el tratamiento de tus datos.'); return; }
    var cc = document.getElementById('crearCuenta');
    if (cc && cc.checked && (f.clave.value.length < 8 || !f.email.value)) { e.preventDefault(); alert('Para crear tu cuenta escribe tu correo (paso 1) y una contraseña de al menos 8 caracteres, o desmarca la opción.'); return; }
    document.getElementById('bEnviar').disabled = true; document.getElementById('bEnviar').textContent = 'Enviando...';
  });
  var cc = document.getElementById('crearCuenta');
  if (cc) cc.addEventListener('change', function () { document.getElementById('campoClave').classList.toggle('oculto', !cc.checked); });
  document.querySelectorAll('#tabsComida button').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('#tabsComida button').forEach(function (x) { x.classList.toggle('on', x === b); });
      document.querySelectorAll('[data-comida]').forEach(function (d) { d.classList.toggle('oculto', d.dataset.comida !== b.dataset.c); });
    });
  });
  // Borrador automático en este celular
  var K = 'bj_formulario';
  try {
    var g = JSON.parse(localStorage.getItem(K) || '{}');
    Object.keys(g).forEach(function (k) {
      var els = f.querySelectorAll('[name="' + k.replace(/"/g, '\\"') + '"]');
      els.forEach(function (el) { if (el.type === 'radio') el.checked = el.value === g[k]; else if (el.type !== 'file' && el.type !== 'password' && el.type !== 'hidden' && !el.value) el.value = g[k]; });
    });
  } catch (e) {}
  f.addEventListener('input', function () {
    var o = {};
    f.querySelectorAll('input, select, textarea').forEach(function (el) {
      if (!el.name || el.type === 'file' || el.type === 'password' || el.type === 'hidden' || el.type === 'checkbox') return;
      if (el.type === 'radio') { if (el.checked) o[el.name] = el.value; } else if (el.value) o[el.name] = el.value;
    });
    try { localStorage.setItem(K, JSON.stringify(o)); } catch (e) {}
  });
})();
</script>
<?php pie(); ?>
