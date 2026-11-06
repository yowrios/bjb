<?php
/** Ficha del cliente (y creación de clientes nuevos). */
require_once APP_ROOT . '/inc/bio.php';
$id = (int)($_GET['id'] ?? 0);
$t = $_GET['t'] ?? 'resumen';
$planes = filas("SELECT * FROM planes ORDER BY orden, id");
$volver = fn(string $tab = '') => redirigir(url('admin/?p=cliente&id=' . $id . '&t=' . ($tab ?: $t)));

if (es_post()) {
    csrf_verificar();
    $acc = post('acc');
    try {
        if ($acc === 'crear') {
            $email = mb_strtolower(post('email'));
            if (post('nombre') === '') throw new RuntimeException('Escribe el nombre.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = 'cliente' . time() . '@sin-correo.local';
            if (valor('SELECT COUNT(*) FROM usuarios WHERE LOWER(email) = ?', [$email])) throw new RuntimeException('Ya existe una cuenta con ese correo.');
            $clave = clave_temporal();
            $id = insertar('usuarios', ['rol' => 'cliente', 'nombre' => post('nombre'), 'email' => $email, 'telefono' => post('telefono'), 'clave_hash' => password_hash($clave, PASSWORD_DEFAULT),
                'activo' => 1, 'debe_cambiar' => 1, 'fecha_nacimiento' => post('fecha_nacimiento') ?: null, 'sexo' => in_array(post('sexo'), ['M', 'F'], true) ? post('sexo') : null,
                'estatura_cm' => num(post('estatura_cm')), 'nivel' => post('nivel') ?: null, 'modalidad' => post('modalidad') ?: null, 'objetivo' => post('objetivo'),
                'notas_admin' => post('notas_admin'), 'origen' => post('origen') ?: 'creado por Bryan', 'cliente_desde' => post('cliente_desde') ?: null, 'consentimiento' => post('consentimiento') ? ahora() : null, 'creado' => ahora()]);
            if (num(post('peso'))) insertar('medidas', ['usuario_id' => $id, 'fecha' => hoy(), 'peso' => num(post('peso')), 'grasa' => num(post('grasa')), 'nota' => 'Valoración inicial', 'por' => 'admin', 'creado' => ahora()]);
            if (post_int('plan_id') && ($pl = fila('SELECT * FROM planes WHERE id = ?', [post_int('plan_id')]))) {
                activar_plan($id, $pl, post('inicio') ?: hoy(), post_num('monto') ?: (float)$pl['precio']);
                if (post('registrar_pago')) insertar('pagos', ['usuario_id' => $id, 'nombre' => post('nombre'), 'telefono' => post('telefono'), 'email' => $email, 'concepto_tipo' => 'plan', 'concepto_id' => $pl['id'],
                    'concepto' => $pl['nombre'], 'monto' => post_num('monto') ?: $pl['precio'], 'metodo' => post('metodo'), 'estado' => 'aprobado', 'nota' => 'Registrado por Bryan', 'creado' => ahora(), 'revisado' => ahora()]);
            }
            $_SESSION['clave_nueva'] = [$id, $clave];
            flash('Cliente creado. Envíale su acceso por WhatsApp.');
            redirigir(url('admin/?p=cliente&id=' . $id));
        }
        $c = fila("SELECT * FROM usuarios WHERE id = ? AND rol = 'cliente'", [$id]);
        if (!$c) throw new RuntimeException('Cliente no encontrado.');
        switch ($acc) {
            case 'editar':
                $em = mb_strtolower(post('email'));
                if (!filter_var($em, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Correo no válido.');
                if (valor('SELECT COUNT(*) FROM usuarios WHERE LOWER(email) = ? AND id <> ?', [$em, $id])) throw new RuntimeException('Otro usuario ya tiene ese correo.');
                actualizar('usuarios', $id, ['nombre' => post('nombre'), 'email' => $em, 'telefono' => post('telefono'), 'fecha_nacimiento' => post('fecha_nacimiento') ?: null,
                    'sexo' => in_array(post('sexo'), ['M', 'F'], true) ? post('sexo') : null, 'estatura_cm' => num(post('estatura_cm')), 'nivel' => post('nivel') ?: null,
                    'modalidad' => post('modalidad') ?: null, 'objetivo' => post('objetivo'), 'notas_admin' => post('notas_admin'), 'etiquetas' => post('etiquetas'),
                    'cliente_desde' => post('cliente_desde') ?: null, 'activo' => post('activo') ? 1 : 0]);
                flash('Datos guardados.'); break;
            case 'clave':
                $clave = clave_temporal();
                q('UPDATE usuarios SET clave_hash = ?, debe_cambiar = 1 WHERE id = ?', [password_hash($clave, PASSWORD_DEFAULT), $id]);
                $_SESSION['clave_nueva'] = [$id, $clave];
                flash('Nueva contraseña temporal creada.'); break;
            case 'plan':
                $pl = fila('SELECT * FROM planes WHERE id = ?', [post_int('plan_id')]);
                if (!$pl) throw new RuntimeException('Elige un plan.');
                activar_plan($id, $pl, post('inicio') ?: hoy(), post_num('monto') ?: (float)$pl['precio']);
                if (post('registrar_pago')) insertar('pagos', ['usuario_id' => $id, 'nombre' => $c['nombre'], 'telefono' => $c['telefono'], 'email' => $c['email'], 'concepto_tipo' => 'plan', 'concepto_id' => $pl['id'],
                    'concepto' => $pl['nombre'], 'monto' => post_num('monto') ?: $pl['precio'], 'metodo' => post('metodo'), 'estado' => 'aprobado', 'nota' => 'Registrado por Bryan', 'creado' => ahora(), 'revisado' => ahora()]);
                flash('Plan ' . $pl['nombre'] . ' activado.'); $t = 'plan'; break;
            case 'sesion':
                $m = membresia_activa($id);
                insertar('sesiones', ['usuario_id' => $id, 'membresia_id' => $m['id'] ?? null, 'fecha' => (post('fecha') ?: hoy()) . ' ' . date('H:i:s'), 'nota' => mb_substr(post('nota'), 0, 250)]);
                if ($m) q('UPDATE membresias SET sesiones_usadas = sesiones_usadas + 1 WHERE id = ?', [$m['id']]);
                flash('Sesión registrada' . ($m && $m['sesiones_total'] ? ' (' . ($m['sesiones_usadas'] + 1) . ' de ' . $m['sesiones_total'] . ')' : '') . '.'); break;
            case 'quitar_sesion':
                $s = fila('SELECT * FROM sesiones WHERE id = ? AND usuario_id = ?', [post_int('sesion_id'), $id]);
                if ($s) { q('DELETE FROM sesiones WHERE id = ?', [$s['id']]); if ($s['membresia_id']) q('UPDATE membresias SET sesiones_usadas = CASE WHEN sesiones_usadas > 0 THEN sesiones_usadas - 1 ELSE 0 END WHERE id = ?', [$s['membresia_id']]); }
                flash('Sesión quitada.'); $t = 'plan'; break;
            case 'membresia':
                $m = fila('SELECT * FROM membresias WHERE id = ? AND usuario_id = ?', [post_int('membresia_id'), $id]);
                if (!$m) throw new RuntimeException('Plan no encontrado.');
                $d = ['fin' => post('fin') ?: $m['fin'], 'sesiones_total' => post_int('sesiones_total'), 'sesiones_usadas' => post_int('sesiones_usadas'), 'nota' => post('nota')];
                if (in_array(post('estado'), ['activa', 'pausada', 'finalizada', 'vencida'], true)) $d['estado'] = post('estado');
                if (($d['estado'] ?? '') === 'activa') q("UPDATE membresias SET estado = 'finalizada' WHERE usuario_id = ? AND estado = 'activa' AND id <> ?", [$id, $m['id']]);
                actualizar('membresias', (int)$m['id'], $d);
                flash('Plan actualizado.'); $t = 'plan'; break;
            case 'medida':
                if (!num(post('peso')) && !num(post('cintura')) && !num(post('grasa'))) throw new RuntimeException('Escribe al menos el peso.');
                $d = ['usuario_id' => $id, 'fecha' => post('fecha') ?: hoy(), 'por' => 'admin', 'nota' => mb_substr(post('nota'), 0, 250), 'creado' => ahora()];
                foreach (['peso', 'grasa', 'musculo', 'cintura', 'cadera', 'pecho', 'brazo', 'pierna', 'abdomen'] as $k) $d[$k] = num(post($k));
                insertar('medidas', $d); flash('Medidas guardadas.'); $t = 'evolucion'; break;
            case 'borrar_medida':
                q('DELETE FROM medidas WHERE id = ? AND usuario_id = ?', [post_int('medida_id'), $id]); flash('Registro borrado.'); $t = 'evolucion'; break;
            case 'foto':
                $f = subir_archivo($_FILES['foto'] ?? ['error' => UPLOAD_ERR_NO_FILE], TIPOS_IMAGEN, true, 'fotos', 15);
                if (!$f) throw new RuntimeException('Elige una foto.');
                insertar('fotos', ['usuario_id' => $id, 'fecha' => post('fecha') ?: hoy(), 'tipo' => post('tipo') ?: 'otra', 'archivo' => $f, 'nota' => mb_substr(post('nota'), 0, 250), 'por' => 'admin', 'creado' => ahora()]);
                flash('Foto guardada.'); $t = 'evolucion'; break;
            case 'borrar_foto':
                $fo = fila('SELECT * FROM fotos WHERE id = ? AND usuario_id = ?', [post_int('foto_id'), $id]);
                if ($fo) { @unlink(PRIV_DIR . '/archivos/' . $fo['archivo']); q('DELETE FROM fotos WHERE id = ?', [$fo['id']]); }
                flash('Foto borrada.'); $t = 'evolucion'; break;
            case 'asignar_rutina':
                $base = fila('SELECT * FROM rutinas WHERE id = ?', [post_int('rutina_id')]);
                if (!$base) throw new RuntimeException('Elige una rutina.');
                $nid = insertar('rutinas', ['nombre' => post('nombre') ?: $base['nombre'], 'descripcion' => $base['descripcion'], 'objetivo' => $base['objetivo'], 'nivel' => $base['nivel'], 'plantilla' => 0, 'usuario_id' => $id, 'activa' => 1, 'creado' => ahora()]);
                foreach (filas('SELECT * FROM rutina_ejercicios WHERE rutina_id = ? ORDER BY orden, id', [$base['id']]) as $e) { unset($e['id']); $e['rutina_id'] = $nid; insertar('rutina_ejercicios', $e); }
                flash('Rutina asignada. Puedes ajustarla para este cliente.');
                redirigir(url('admin/?p=rutinas&id=' . $nid));
            case 'quitar_rutina':
                q('UPDATE rutinas SET activa = 0 WHERE id = ? AND usuario_id = ?', [post_int('rutina_id'), $id]); flash('Rutina archivada.'); $t = 'rutinas'; break;
            case 'acceso':
                $vid = post('video_id') === 'todos' ? null : post_int('video_id');
                dar_acceso_video($id, $vid, post_int('dias'), 'Bryan');
                flash('Acceso habilitado.'); $t = 'accesos'; break;
            case 'quitar_acceso':
                q('DELETE FROM accesos_video WHERE id = ? AND usuario_id = ?', [post_int('acceso_id'), $id]); flash('Acceso quitado.'); $t = 'accesos'; break;
            case 'recomendacion':
                if (post('titulo') === '') throw new RuntimeException('Escribe un título.');
                insertar('contenidos', ['tipo' => 'recomendacion', 'titulo' => post('titulo'), 'cuerpo' => post('cuerpo'), 'usuario_id' => $id, 'visible' => 1, 'orden' => 0, 'categoria' => 'Personal', 'creado' => ahora()]);
                flash('Recomendación enviada a su portal.'); $t = 'checkins'; break;
            case 'responder':
                q('UPDATE checkins SET respuesta = ?, respondido = ? WHERE id = ? AND usuario_id = ?', [post('respuesta'), ahora(), post_int('checkin_id'), $id]);
                flash('Respuesta guardada.'); $t = 'checkins'; break;
            case 'borrar_bio':
                $b = fila('SELECT * FROM bioimpedancias WHERE id = ? AND usuario_id = ?', [post_int('bio_id'), $id]);
                if ($b) { if ($b['archivo']) @unlink(PRIV_DIR . '/archivos/' . $b['archivo']); q('DELETE FROM medidas WHERE bio_id = ?', [$b['id']]); q('DELETE FROM bioimpedancias WHERE id = ?', [$b['id']]); }
                flash('Bioimpedancia borrada.'); $t = 'bio'; break;
            case 'pago':
                insertar('pagos', ['usuario_id' => $id, 'nombre' => $c['nombre'], 'telefono' => $c['telefono'], 'email' => $c['email'], 'concepto_tipo' => 'otro', 'concepto' => post('concepto') ?: 'Pago',
                    'monto' => post_num('monto'), 'metodo' => post('metodo'), 'estado' => 'aprobado', 'nota' => 'Registrado por Bryan', 'creado' => (post('fecha') ?: hoy()) . ' ' . date('H:i:s'), 'revisado' => ahora()]);
                flash('Pago registrado.'); $t = 'pagos'; break;
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'error'); }
    if ($id) $volver($t); else redirigir(url('admin/?p=cliente&nuevo=1'));
}

/* ---------- Nuevo cliente ---------- */
if (!empty($_GET['nuevo']) || !$id) {
    admin_cabecera('cliente', 'Nuevo cliente');
    $opPlanes = ['' => 'Sin plan por ahora'] + array_column(array_map(fn($p) => ['id' => $p['id'], 't' => $p['nombre'] . ' · ' . pesos($p['precio'])], $planes), 't', 'id');
    ?>
    <form method="post" class="ad-caja" style="max-width:900px"><?= csrf_campo() ?><input type="hidden" name="acc" value="crear">
      <div class="ad-nota">El sistema crea una <b>contraseña temporal</b> y te muestra el mensaje para enviarla por WhatsApp. Al entrar, el cliente la cambia.</div>
      <h2><?= icono('user') ?> Datos</h2>
      <div class="campos">
        <?= campo('nombre', 'Nombre completo *', '', 'text', ['required' => true, 'ancho' => true]) ?>
        <?= campo('telefono', 'WhatsApp', '', 'tel', ['placeholder' => '300 123 4567']) ?>
        <?= campo('email', 'Correo (para entrar al portal)', '', 'email', ['ayuda' => 'Si no tiene correo, puede entrar con su celular.']) ?>
        <?= campo('fecha_nacimiento', 'Fecha de nacimiento', '', 'date') ?>
        <?= campo('sexo', 'Sexo', '', 'select', ['opciones' => ['' => '—', 'M' => 'Hombre', 'F' => 'Mujer']]) ?>
        <?= campo('estatura_cm', 'Estatura (cm)', '', 'number') ?>
        <?= campo('nivel', 'Nivel', 'principiante', 'select', ['opciones' => ['principiante' => 'Principiante', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado']]) ?>
        <?= campo('modalidad', 'Modalidad', 'presencial', 'select', ['opciones' => ['presencial' => 'Presencial', 'virtual' => 'Virtual', 'mixta' => 'Mixta']]) ?>
        <?= campo('origen', '¿Cómo llegó?', '', 'select', ['opciones' => ['creado por Bryan' => 'Referido / directo', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'gimnasio' => 'En el gimnasio', 'web' => 'Página web']]) ?>
        <?= campo('peso', 'Peso inicial (kg)', '', 'number', ['step' => '0.1']) ?>
        <?= campo('grasa', '% grasa inicial', '', 'number', ['step' => '0.1']) ?>
        <?= campo('cliente_desde', 'Cliente desde', '', 'date', ['ayuda' => 'Déjalo vacío y se toma la fecha de su primer plan.']) ?>
        <?= campo('objetivo', 'Objetivo', '', 'textarea', ['ancho' => true]) ?>
        <?= campo('notas_admin', 'Notas privadas (lesiones, preferencias...)', '', 'textarea', ['ancho' => true, 'ayuda' => 'Solo tú las ves.']) ?>
        <?= campo('consentimiento', 'El cliente autorizó el tratamiento de sus datos (incluye datos de salud y fotos)', 1, 'checkbox', ['ancho' => true]) ?>
      </div>
      <h2 class="mt2"><?= icono('flame') ?> Plan inicial (opcional)</h2>
      <div class="campos">
        <?= campo('plan_id', 'Plan', '', 'select', ['opciones' => $opPlanes]) ?>
        <?= campo('inicio', 'Fecha de inicio', hoy(), 'date') ?>
        <?= campo('monto', 'Valor cobrado', '', 'text', ['inputmode' => 'numeric', 'placeholder' => 'Vacío = precio del plan']) ?>
        <?= campo('metodo', 'Medio de pago', 'Nequi', 'select', ['opciones' => ['Nequi' => 'Nequi', 'Daviplata' => 'Daviplata', 'Davivienda' => 'Transferencia', 'Efectivo' => 'Efectivo', 'Tarjeta / PSE' => 'Tarjeta / PSE']]) ?>
        <?= campo('registrar_pago', 'Registrar también el pago (ya lo recibí)', 1, 'checkbox', ['ancho' => true]) ?>
      </div>
      <button class="btn btn-rojo mt2" type="submit"><?= icono('check') ?> Crear cliente</button>
    </form>
    <?php admin_pie(); return;
}

/* ---------- Ficha ---------- */
$c = fila("SELECT * FROM usuarios WHERE id = ? AND rol = 'cliente'", [$id]);
if (!$c) { flash('Cliente no encontrado.', 'error'); redirigir(url('admin/?p=clientes')); }
$m = membresia_activa($id);
$medidas = filas('SELECT * FROM medidas WHERE usuario_id = ? ORDER BY fecha, id', [$id]);
$pesos = array_values(array_filter($medidas, fn($x) => $x['peso'] !== null));
$desde = $c['cliente_desde'] ?: (valor('SELECT MIN(inicio) FROM membresias WHERE usuario_id = ?', [$id]) ?: substr((string)$c['creado'], 0, 10));
$n1 = explode(' ', $c['nombre'])[0];
$nuevaClave = (($_SESSION['clave_nueva'][0] ?? 0) == $id) ? $_SESSION['clave_nueva'][1] : null;
unset($_SESSION['clave_nueva']);
$cnt = [
    'pagos' => (int)valor('SELECT COUNT(*) FROM pagos WHERE usuario_id = ?', [$id]),
    'checkins' => (int)valor("SELECT COUNT(*) FROM checkins WHERE usuario_id = ? AND (respuesta IS NULL OR respuesta = '')", [$id]),
    'rutinas' => (int)valor('SELECT COUNT(*) FROM rutinas WHERE usuario_id = ? AND activa = 1', [$id]),
    'formularios' => (int)valor('SELECT COUNT(*) FROM formularios WHERE usuario_id = ?', [$id]),
];
admin_cabecera('cliente', $c['nombre']);
$tabs = ['resumen' => 'Resumen', 'plan' => 'Plan y sesiones', 'evolucion' => 'Evolución', 'bio' => 'Bioimpedancia <em>' . (int)valor('SELECT COUNT(*) FROM bioimpedancias WHERE usuario_id = ?', [$id]) . '</em>', 'rutinas' => 'Rutinas <em>' . $cnt['rutinas'] . '</em>', 'checkins' => 'Check-ins' . ($cnt['checkins'] ? ' <em>(' . $cnt['checkins'] . ')</em>' : ''),
    'pagos' => 'Pagos <em>' . $cnt['pagos'] . '</em>', 'accesos' => 'Videos', 'datos' => 'Datos', 'formulario' => 'Formulario' . ($cnt['formularios'] ? '' : ' <em>—</em>')];
?>
<?php if ($nuevaClave): $msg = "Hola $n1, ¡bienvenido a " . ajuste('marca') . "! 💪\nEste es tu acceso a tu portal de evolución:\n" . url_absoluta('ingresar.php') . "\nUsuario: " . (str_ends_with($c['email'], '@sin-correo.local') ? $c['telefono'] : $c['email']) . "\nContraseña temporal: $nuevaClave\nAl entrar te pedirá cambiarla."; ?>
  <div class="ad-caja" style="border-color:var(--volt);margin-bottom:20px">
    <h2><?= icono('lock') ?> Contraseña temporal: <span style="font-family:monospace;color:var(--volt);font-size:22px"><?= e($nuevaClave) ?></span></h2>
    <p class="sec" style="margin:0 0 12px">Solo se muestra esta vez. Envíasela al cliente:</p>
    <div class="ad-acciones" style="justify-content:flex-start"><a class="btn btn-wa btn-sm" target="_blank" rel="noopener" href="<?= e(wa_link($c['telefono'], $msg)) ?>"><?= icono('whatsapp') ?> Enviar por WhatsApp</a><button class="btn btn-sm btn-borde" type="button" data-copiar="<?= e($msg) ?>">Copiar mensaje</button></div>
  </div>
<?php endif; ?>
<div class="ad-ficha-cab">
  <span class="ad-avatar"><?= e(mb_strtoupper(mb_substr($c['nombre'], 0, 1))) ?></span>
  <div><h2><?= e($c['nombre']) ?></h2><span class="sec"><?= e($c['telefono']) ?> · <?= e($c['email']) ?> · Cliente desde <?= fecha_larga($desde) ?></span>
    <div class="plan-meta" style="margin:8px 0 0"><?= $m ? '<span class="chip chip-ok">' . e($m['nombre_plan']) . '</span>' : '<span class="chip">Sin plan activo</span>' ?><?php foreach (array_filter(array_map('trim', explode(',', (string)$c['etiquetas']))) as $et): ?><span class="chip"><?= e($et) ?></span><?php endforeach; ?></div></div>
  <div class="der">
    <form method="post" data-confirmar="¿Registrar una sesión de hoy?"><?= csrf_campo() ?><input type="hidden" name="acc" value="sesion"><button class="btn btn-rojo btn-sm" type="submit"><?= icono('check') ?> Asistió hoy</button></form>
    <a class="btn btn-wa btn-sm" target="_blank" rel="noopener" href="<?= e(wa_link($c['telefono'], "Hola $n1, ")) ?>"><?= icono('whatsapp') ?> WhatsApp</a>
    <a class="btn btn-borde btn-sm" href="<?= url('admin/?p=reportes&cliente=' . $id) ?>" target="_blank"><?= icono('print') ?> Reporte de evolución</a>
  </div>
</div>
<nav class="ad-pestanas"><?php foreach ($tabs as $k => $tt): ?><a href="<?= url('admin/?p=cliente&id=' . $id . '&t=' . $k) ?>" class="<?= $t === $k ? 'on' : '' ?>"><?= $tt ?></a><?php endforeach; ?></nav>

<?php if ($t === 'resumen'):
  $ini = $pesos[0]['peso'] ?? null; $act = $pesos ? end($pesos)['peso'] : null;
  $ult = $medidas ? end($medidas) : null; $pri = $medidas[0] ?? null;
  $entrenos = (int)valor('SELECT COUNT(*) FROM entrenos WHERE usuario_id = ?', [$id]);
  $sesTot = (int)valor('SELECT COUNT(*) FROM sesiones WHERE usuario_id = ?', [$id]);
  $pagado = (float)valor("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE usuario_id = ? AND estado = 'aprobado'", [$id]);
  $ultEnt = filas('SELECT * FROM entrenos WHERE usuario_id = ? ORDER BY fecha DESC LIMIT 5', [$id]);
  $vis = (int)valor('SELECT COUNT(*) FROM visitas WHERE usuario_id = ?', [$id]); ?>
  <div class="ad-kpis">
    <div class="ad-kpi"><span>Plan</span><b style="font-size:20px"><?= $m ? e($m['nombre_plan']) : '—' ?></b><small><?= $m ? 'Vence ' . fecha_corta($m['fin']) . ' · ' . (int)$m['sesiones_usadas'] . '/' . (int)$m['sesiones_total'] . ' sesiones' : 'Sin plan activo' ?></small></div>
    <div class="ad-kpi"><span>Peso</span><b><?= $act ? (float)$act . ' kg' : '—' ?></b><small class="<?= $ini && $act && $act < $ini ? 'sube' : '' ?>"><?= $ini && $act ? (($act - $ini) > 0 ? '+' : '') . round($act - $ini, 1) . ' kg desde el inicio' : 'Sin registros' ?></small></div>
    <div class="ad-kpi"><span>Cintura</span><b><?= $ult && $ult['cintura'] ? (float)$ult['cintura'] . ' cm' : '—' ?></b><small><?= $pri && $ult && $pri['cintura'] && $ult['cintura'] ? round($ult['cintura'] - $pri['cintura'], 1) . ' cm desde el inicio' : '' ?></small></div>
    <div class="ad-kpi"><span>Sesiones / entrenos</span><b><?= $sesTot ?> / <?= $entrenos ?></b><small>Con Bryan / registrados por el cliente</small></div>
    <div class="ad-kpi"><span>Total pagado</span><b><?= pesos($pagado) ?></b><small><?= $vis ?> visitas a la web · último ingreso <?= hace($c['ultimo_acceso']) ?></small></div>
  </div>
  <div class="ad-rejilla">
    <div class="ad-caja"><h2><?= icono('user') ?> Perfil</h2>
      <div class="ad-dato"><span>Edad</span><span><?= edad($c['fecha_nacimiento']) ?? '—' ?> años</span></div>
      <div class="ad-dato"><span>Sexo · estatura</span><span><?= $c['sexo'] === 'F' ? 'Mujer' : ($c['sexo'] === 'M' ? 'Hombre' : '—') ?> · <?= $c['estatura_cm'] ? (float)$c['estatura_cm'] . ' cm' : '—' ?></span></div>
      <div class="ad-dato"><span>Nivel · modalidad</span><span><?= e(ucfirst((string)$c['nivel'])) ?: '—' ?> · <?= e(ucfirst((string)$c['modalidad'])) ?: '—' ?></span></div>
      <div class="ad-dato"><span>Origen</span><span><?= e($c['origen']) ?></span></div>
      <p class="mt1"><b>Objetivo:</b> <?= nl2br(e($c['objetivo'] ?: '—')) ?></p>
      <?php if ($c['notas_admin']): ?><div class="ad-nota"><b>Notas privadas:</b><br><?= nl2br(e($c['notas_admin'])) ?></div><?php endif; ?>
    </div>
    <div class="ad-caja"><h2><?= icono('timer') ?> Últimos entrenos del cliente</h2>
      <?php foreach ($ultEnt as $x): ?><div class="fila-lista"><div><b><?= fecha_corta($x['fecha']) ?></b> · <?= e(ucfirst((string)$x['dia'])) ?><br><span><?= round($x['duracion_seg'] / 60) ?> min · <?= (int)$x['series_hechas'] ?> series<?= $x['nota'] ? ' · «' . e($x['nota']) . '»' : '' ?></span></div><span style="font-size:22px"><?= $x['sensacion'] ? ['', '😫', '😕', '🙂', '💪', '🔥'][(int)$x['sensacion']] : '' ?></span></div><?php endforeach; ?>
      <?php if (!$ultEnt): ?><p class="ad-vacio">Aún no registra entrenos con el cronómetro.</p><?php endif; ?>
    </div>
  </div>

<?php elseif ($t === 'plan'):
  $mems = filas('SELECT * FROM membresias WHERE usuario_id = ? ORDER BY inicio DESC, id DESC', [$id]);
  $ses = filas('SELECT * FROM sesiones WHERE usuario_id = ? ORDER BY fecha DESC LIMIT 60', [$id]); ?>
  <div class="ad-rejilla">
    <form method="post" class="ad-caja"><?= csrf_campo() ?><input type="hidden" name="acc" value="plan">
      <h2><?= icono('flame') ?> <?= $m ? 'Renovar o cambiar de plan' : 'Activar un plan' ?></h2>
      <div class="campos">
        <?= campo('plan_id', 'Plan', $m['plan_id'] ?? '', 'select', ['opciones' => array_column(array_map(fn($p) => ['id' => $p['id'], 't' => $p['nombre'] . ' · ' . pesos($p['precio'])], $planes), 't', 'id')]) ?>
        <?= campo('inicio', 'Inicio', ($m && $m['fin'] >= hoy()) ? $m['fin'] : hoy(), 'date') ?>
        <?= campo('monto', 'Valor cobrado', '', 'text', ['inputmode' => 'numeric', 'placeholder' => 'Vacío = precio del plan']) ?>
        <?= campo('metodo', 'Medio de pago', 'Nequi', 'select', ['opciones' => ['Nequi' => 'Nequi', 'Daviplata' => 'Daviplata', 'Davivienda' => 'Transferencia', 'Efectivo' => 'Efectivo', 'Tarjeta / PSE' => 'Tarjeta / PSE']]) ?>
        <?= campo('registrar_pago', 'Registrar el pago (ya lo recibí)', 1, 'checkbox', ['ancho' => true]) ?>
      </div>
      <button class="btn btn-rojo mt2" type="submit">Activar plan</button>
      <p class="ad-vacio" style="text-align:left">Si tenía un plan activo, queda como «Finalizada» y empieza el nuevo.</p>
    </form>
    <form method="post" class="ad-caja"><?= csrf_campo() ?><input type="hidden" name="acc" value="sesion">
      <h2><?= icono('check') ?> Registrar una sesión</h2>
      <div class="campos"><?= campo('fecha', 'Fecha', hoy(), 'date') ?><?= campo('nota', 'Qué se trabajó (opcional)', '', 'text', ['placeholder' => 'Pierna y glúteo']) ?></div>
      <button class="btn btn-rojo mt2" type="submit">Registrar sesión</button>
      <h2 class="mt2">Sesiones recientes</h2>
      <?php foreach ($ses as $s): ?><div class="fila-lista"><div><b><?= fecha_corta($s['fecha']) ?></b><br><span><?= e($s['nota'] ?: '—') ?></span></div></div><?php endforeach; ?>
      <?php if ($ses): ?></form><form method="post" data-confirmar="¿Quitar la última sesión registrada?"><?= csrf_campo() ?><input type="hidden" name="acc" value="quitar_sesion"><input type="hidden" name="sesion_id" value="<?= (int)$ses[0]['id'] ?>"><button class="btn btn-xs btn-borde mt1" type="submit">Deshacer la última sesión</button><?php endif; ?>
    </form>
  </div>
  <div class="ad-caja mt2"><h2><?= icono('calendar') ?> Historial de planes</h2>
    <?php foreach ($mems as $x): ?>
      <form method="post" class="campos" style="grid-template-columns:2fr repeat(4,1fr) 1.5fr auto;align-items:end;padding:12px 0;border-bottom:1px solid var(--linea)"><?= csrf_campo() ?><input type="hidden" name="acc" value="membresia"><input type="hidden" name="membresia_id" value="<?= (int)$x['id'] ?>">
        <div><b><?= e($x['nombre_plan']) ?></b><br><span class="sec"><?= fecha_corta($x['inicio']) ?> · <?= pesos($x['precio']) ?></span></div>
        <?= campo('fin', 'Vence', $x['fin'], 'date') ?>
        <?= campo('sesiones_usadas', 'Usadas', $x['sesiones_usadas'], 'number') ?>
        <?= campo('sesiones_total', 'Total', $x['sesiones_total'], 'number') ?>
        <?= campo('estado', 'Estado', $x['estado'], 'select', ['opciones' => ['activa' => 'Activa', 'pausada' => 'Pausada', 'finalizada' => 'Finalizada', 'vencida' => 'Vencida']]) ?>
        <?= campo('nota', 'Nota', $x['nota']) ?>
        <button class="btn btn-sm btn-borde" type="submit">Guardar</button>
      </form>
    <?php endforeach; ?>
    <?php if (!$mems): ?><p class="ad-vacio">Sin planes todavía.</p><?php endif; ?>
    <p class="ad-vacio" style="text-align:left">Para pausar un plan (viaje, incapacidad) cambia el estado a «Pausada» y luego mueve la fecha de vencimiento.</p>
  </div>

<?php elseif ($t === 'evolucion'):
  $fotos = filas('SELECT * FROM fotos WHERE usuario_id = ? ORDER BY fecha DESC, id DESC', [$id]);
  $g = ['peso' => 'Peso', 'grasa' => '% grasa', 'cintura' => 'Cintura', 'abdomen' => 'Abdomen'];
  $datos = []; foreach ($g as $k => $tt) $datos[$k] = array_values(array_filter(array_map(fn($x) => $x[$k] !== null ? ['x' => $x['fecha'], 'y' => (float)$x[$k]] : null, $medidas))); ?>
  <div class="ad-rejilla">
    <div class="ad-caja"><h2><?= icono('chart') ?> Gráfica</h2><div style="height:260px"><canvas id="gEvo"></canvas></div></div>
    <form method="post" class="ad-caja"><?= csrf_campo() ?><input type="hidden" name="acc" value="medida">
      <h2><?= icono('scale') ?> Nueva valoración</h2>
      <div class="campos" style="grid-template-columns:repeat(3,1fr)">
        <?= campo('fecha', 'Fecha', hoy(), 'date') ?><?= campo('peso', 'Peso (kg)', '', 'number', ['step' => '0.1']) ?><?= campo('grasa', '% grasa', '', 'number', ['step' => '0.1']) ?>
        <?= campo('musculo', '% músculo', '', 'number', ['step' => '0.1']) ?><?= campo('cintura', 'Cintura', '', 'number', ['step' => '0.1']) ?><?= campo('cadera', 'Cadera', '', 'number', ['step' => '0.1']) ?>
        <?= campo('abdomen', 'Abdomen', '', 'number', ['step' => '0.1']) ?><?= campo('pecho', 'Pecho', '', 'number', ['step' => '0.1']) ?><?= campo('brazo', 'Brazo', '', 'number', ['step' => '0.1']) ?>
        <?= campo('pierna', 'Pierna', '', 'number', ['step' => '0.1']) ?><div class="campo" style="grid-column:span 2"><label>Nota</label><input name="nota"></div>
      </div>
      <button class="btn btn-rojo mt2" type="submit">Guardar valoración</button>
    </form>
  </div>
  <div class="ad-caja mt2 ad-tabla-envoltura"><h2>Historial de medidas</h2>
    <table class="ad-tabla"><thead><tr><th>Fecha</th><?php foreach (['Peso', '% grasa', '% músc.', 'Cintura', 'Cadera', 'Abdomen', 'Pecho', 'Brazo', 'Pierna'] as $h): ?><th class="num"><?= $h ?></th><?php endforeach; ?><th>Por</th><th></th></tr></thead><tbody>
      <?php foreach (array_reverse($medidas) as $x): ?><tr><td><?= fecha_corta($x['fecha']) ?><?= $x['nota'] ? '<br><span class="sec">' . e($x['nota']) . '</span>' : '' ?></td>
        <?php foreach (['peso', 'grasa', 'musculo', 'cintura', 'cadera', 'abdomen', 'pecho', 'brazo', 'pierna'] as $k): ?><td class="num"><?= $x[$k] !== null ? (float)$x[$k] : '—' ?></td><?php endforeach; ?>
        <td class="sec"><?= ['admin' => 'Tú', 'bio' => 'Smart Fit'][$x['por']] ?? 'Cliente' ?></td>
        <td><form method="post" data-confirmar="¿Borrar este registro?"><?= csrf_campo() ?><input type="hidden" name="acc" value="borrar_medida"><input type="hidden" name="medida_id" value="<?= (int)$x['id'] ?>"><button class="btn-icono peligro" type="submit" title="Borrar"><?= icono('trash') ?></button></form></td></tr><?php endforeach; ?>
      <?php if (!$medidas): ?><tr><td colspan="12" class="ad-vacio">Sin medidas.</td></tr><?php endif; ?>
    </tbody></table></div>
  <div class="ad-caja mt2"><h2><?= icono('camera') ?> Fotos</h2>
    <div class="galeria"><?php foreach ($fotos as $f): ?><div><a href="<?= e(media_url('foto', (int)$f['id'])) ?>" target="_blank"><img src="<?= e(media_url('foto', (int)$f['id'])) ?>" alt="" loading="lazy"></a><span><?= fecha_corta($f['fecha']) ?> · <?= e($f['tipo']) ?> · <?= $f['por'] === 'admin' ? 'tú' : 'cliente' ?></span>
      <form method="post" data-confirmar="¿Borrar esta foto?"><?= csrf_campo() ?><input type="hidden" name="acc" value="borrar_foto"><input type="hidden" name="foto_id" value="<?= (int)$f['id'] ?>"><button class="btn btn-xs btn-borde" type="submit">Borrar</button></form></div><?php endforeach; ?></div>
    <?php if (!$fotos): ?><p class="ad-vacio">Sin fotos.</p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="campos mt2" style="grid-template-columns:2fr 1fr 1fr auto;align-items:end"><?= csrf_campo() ?><input type="hidden" name="acc" value="foto">
      <div class="campo"><label>Foto</label><input type="file" name="foto" accept="image/*" required></div>
      <?= campo('tipo', 'Tipo', 'frente', 'select', ['opciones' => ['frente' => 'Frente', 'perfil' => 'Perfil', 'espalda' => 'Espalda', 'otra' => 'Otra']]) ?><?= campo('fecha', 'Fecha', hoy(), 'date') ?>
      <button class="btn btn-rojo btn-sm" type="submit">Subir</button></form>
  </div>
  <?php $sc = '<script>document.addEventListener("DOMContentLoaded",function(){if(!window.Chart)return;var D=' . json_encode($datos) . ',cols=["#ff2a2a","#d4ff3a","#38bdf8","#f59e0b"],ds=[],i=0;for(var k in D){if(D[k].length)ds.push({label:' . json_encode($g) . '[k],data:D[k].map(function(p){return{x:p.x,y:p.y}}),borderColor:cols[i],backgroundColor:cols[i],tension:.3,yAxisID:k==="peso"?"y":"y2"});i++}'
      . 'var labels=[].concat.apply([],Object.values(D).map(function(a){return a.map(function(p){return p.x})})).filter(function(v,i,a){return a.indexOf(v)===i}).sort();'
      . 'new Chart(document.getElementById("gEvo"),{type:"line",data:{labels:labels,datasets:ds},options:{maintainAspectRatio:false,parsing:{xAxisKey:"x",yAxisKey:"y"},scales:{x:{type:"category",ticks:{color:"#a8a8b3"},grid:{color:"rgba(255,255,255,.06)"}},y:{position:"left",ticks:{color:"#a8a8b3"},grid:{color:"rgba(255,255,255,.06)"}},y2:{position:"right",ticks:{color:"#a8a8b3"},grid:{display:false}}},plugins:{legend:{labels:{color:"#d4d4d8"}}}}});});</script>'; ?>

<?php elseif ($t === 'rutinas'):
  $rut = filas('SELECT r.*, (SELECT COUNT(*) FROM rutina_ejercicios e WHERE e.rutina_id = r.id) AS n FROM rutinas r WHERE r.usuario_id = ? AND r.activa = 1 ORDER BY r.id DESC', [$id]);
  $plantillas = filas('SELECT * FROM rutinas WHERE plantilla = 1 AND activa = 1 ORDER BY nombre'); ?>
  <div class="ad-rejilla">
    <div class="ad-caja"><h2><?= icono('clipboard') ?> Rutinas de <?= e($n1) ?></h2>
      <?php foreach ($rut as $r): ?><div class="fila-lista"><div><b><?= e($r['nombre']) ?></b><br><span><?= (int)$r['n'] ?> ejercicios · <?= e($r['objetivo']) ?></span></div>
        <div class="ad-acciones"><a class="btn btn-sm btn-borde" href="<?= url('admin/?p=rutinas&id=' . $r['id']) ?>"><?= icono('edit') ?> Editar</a>
          <form method="post" data-confirmar="¿Archivar esta rutina? El cliente dejará de verla."><?= csrf_campo() ?><input type="hidden" name="acc" value="quitar_rutina"><input type="hidden" name="rutina_id" value="<?= (int)$r['id'] ?>"><button class="btn-icono peligro" type="submit" title="Archivar"><?= icono('trash') ?></button></form></div></div><?php endforeach; ?>
      <?php if (!$rut): ?><p class="ad-vacio">Sin rutinas asignadas.</p><?php endif; ?>
    </div>
    <form method="post" class="ad-caja"><?= csrf_campo() ?><input type="hidden" name="acc" value="asignar_rutina">
      <h2><?= icono('plus') ?> Asignar rutina</h2>
      <div class="campos"><?= campo('rutina_id', 'Desde la plantilla', '', 'select', ['opciones' => array_column($plantillas, 'nombre', 'id'), 'ancho' => true]) ?><?= campo('nombre', 'Nombre para el cliente (opcional)', '', 'text', ['ancho' => true, 'placeholder' => 'Ej: Mes 1 · Hipertrofia']) ?></div>
      <button class="btn btn-rojo mt2" type="submit">Asignar y ajustar</button>
      <p class="ad-vacio" style="text-align:left">Se crea una copia para <?= e($n1) ?>: puedes cambiar series, cargas o ejercicios sin afectar la plantilla. <a href="<?= url('admin/?p=rutinas&nueva=1') ?>" style="color:var(--rojo)">Crear una plantilla nueva</a>.</p>
    </form>
  </div>

<?php elseif ($t === 'checkins'):
  $chks = filas('SELECT * FROM checkins WHERE usuario_id = ? ORDER BY id DESC LIMIT 30', [$id]);
  $recs = filas("SELECT * FROM contenidos WHERE tipo = 'recomendacion' AND usuario_id = ? ORDER BY id DESC", [$id]); ?>
  <div class="ad-rejilla">
    <div class="ad-caja"><h2><?= icono('heart') ?> Check-ins</h2>
      <?php foreach ($chks as $h): ?>
        <div class="checkin-item"><div class="ci-cab"><b><?= fecha_corta($h['fecha']) ?><?= $h['peso'] ? ' · ' . (float)$h['peso'] . ' kg' : '' ?></b><span>Energía <?= (int)$h['energia'] ?> · Sueño <?= (int)$h['sueno'] ?> · Plan <?= ((int)$h['adherencia'] - 1) * 25 ?>% · Ánimo <?= (int)$h['animo'] ?></span></div>
          <?php if ($h['comentario']): ?><p><?= nl2br(e($h['comentario'])) ?></p><?php endif; ?>
          <form method="post" class="mt1"><?= csrf_campo() ?><input type="hidden" name="acc" value="responder"><input type="hidden" name="checkin_id" value="<?= (int)$h['id'] ?>">
            <textarea name="respuesta" rows="2" placeholder="Tu respuesta (la ve en su portal)"><?= e($h['respuesta']) ?></textarea><button class="btn btn-xs btn-rojo mt1" type="submit"><?= $h['respuesta'] ? 'Actualizar' : 'Responder' ?></button></form></div>
      <?php endforeach; ?>
      <?php if (!$chks): ?><p class="ad-vacio">Aún no envía check-ins. Pídele que lo haga cada semana desde su portal.</p><?php endif; ?>
    </div>
    <div class="ad-caja"><h2><?= icono('star') ?> Recomendaciones personales</h2>
      <form method="post"><?= csrf_campo() ?><input type="hidden" name="acc" value="recomendacion"><div class="campos"><?= campo('titulo', 'Título', '', 'text', ['ancho' => true, 'placeholder' => 'Ej: Sube la proteína en la cena']) ?><?= campo('cuerpo', 'Mensaje', '', 'textarea', ['ancho' => true]) ?></div><button class="btn btn-rojo btn-sm mt1" type="submit">Enviar a su portal</button></form>
      <?php foreach ($recs as $r): ?><div class="fila-lista"><div><b><?= e($r['titulo']) ?></b><br><span><?= e($r['cuerpo']) ?> · <?= hace($r['creado']) ?></span></div></div><?php endforeach; ?>
    </div>
  </div>

<?php elseif ($t === 'pagos'):
  $pagos = filas('SELECT * FROM pagos WHERE usuario_id = ? ORDER BY id DESC', [$id]); ?>
  <div class="ad-rejilla">
    <div class="ad-caja ad-tabla-envoltura"><h2><?= icono('money') ?> Pagos</h2>
      <table class="ad-tabla"><thead><tr><th>Fecha</th><th>Concepto</th><th class="num">Valor</th><th>Estado</th></tr></thead><tbody>
        <?php foreach ($pagos as $x): ?><tr><td><?= fecha_corta($x['creado']) ?></td><td><?= e($x['concepto']) ?><br><span class="sec"><?= e($x['metodo']) ?><?= $x['comprobante'] ? ' · <a href="' . e(media_url('comprobante', (int)$x['id'])) . '" target="_blank" style="color:var(--rojo)">comprobante</a>' : '' ?></span></td><td class="num"><?= pesos($x['monto']) ?></td><td><?= estado_chip($x['estado']) ?></td></tr><?php endforeach; ?>
        <?php if (!$pagos): ?><tr><td colspan="4" class="ad-vacio">Sin pagos.</td></tr><?php endif; ?></tbody></table></div>
    <form method="post" class="ad-caja"><?= csrf_campo() ?><input type="hidden" name="acc" value="pago"><h2><?= icono('plus') ?> Registrar un pago recibido</h2>
      <div class="campos"><?= campo('concepto', 'Concepto', '', 'text', ['ancho' => true, 'placeholder' => 'Ej: Sesión adicional']) ?><?= campo('monto', 'Valor', '', 'text', ['inputmode' => 'numeric']) ?><?= campo('fecha', 'Fecha', hoy(), 'date') ?>
        <?= campo('metodo', 'Medio', 'Efectivo', 'select', ['opciones' => ['Nequi' => 'Nequi', 'Daviplata' => 'Daviplata', 'Davivienda' => 'Transferencia', 'Efectivo' => 'Efectivo', 'Tarjeta / PSE' => 'Tarjeta / PSE']]) ?></div>
      <button class="btn btn-rojo mt2" type="submit">Registrar</button>
      <p class="ad-vacio" style="text-align:left">Para cobrar un plan usa la pestaña «Plan y sesiones»: activa el plan y registra el pago al mismo tiempo.</p></form>
  </div>

<?php elseif ($t === 'accesos'):
  $acc = filas('SELECT a.*, v.titulo FROM accesos_video a LEFT JOIN videos v ON v.id = a.video_id WHERE a.usuario_id = ? ORDER BY a.id DESC', [$id]);
  $vids = filas('SELECT id, titulo FROM videos WHERE gratis = 0 ORDER BY orden'); ?>
  <div class="ad-rejilla">
    <div class="ad-caja"><h2><?= icono('video') ?> Accesos a videos</h2>
      <?php if ($m && $m['incluye_videos']): ?><div class="ad-nota">Su plan <b><?= e($m['nombre_plan']) ?></b> ya incluye todos los videos mientras esté activo.</div><?php endif; ?>
      <?php foreach ($acc as $a): ?><div class="fila-lista"><div><b><?= $a['video_id'] ? e($a['titulo']) : 'Toda la biblioteca' ?></b><br><span><?= $a['hasta'] ? 'Hasta ' . fecha_corta($a['hasta']) . ($a['hasta'] < hoy() ? ' (vencido)' : '') : 'Sin vencimiento' ?> · <?= e($a['origen']) ?></span></div>
        <form method="post" data-confirmar="¿Quitar este acceso?"><?= csrf_campo() ?><input type="hidden" name="acc" value="quitar_acceso"><input type="hidden" name="acceso_id" value="<?= (int)$a['id'] ?>"><button class="btn-icono peligro" type="submit"><?= icono('trash') ?></button></form></div><?php endforeach; ?>
      <?php if (!$acc): ?><p class="ad-vacio">Sin accesos individuales.</p><?php endif; ?></div>
    <form method="post" class="ad-caja"><?= csrf_campo() ?><input type="hidden" name="acc" value="acceso"><h2><?= icono('unlock') ?> Dar acceso</h2>
      <div class="campos"><?= campo('video_id', 'Video', 'todos', 'select', ['opciones' => ['todos' => 'Toda la biblioteca'] + array_column($vids, 'titulo', 'id'), 'ancho' => true]) ?><?= campo('dias', 'Días de acceso (0 = sin límite)', ajuste('dias_acceso_video', '90'), 'number') ?></div>
      <button class="btn btn-rojo mt2" type="submit">Habilitar</button></form>
  </div>

<?php elseif ($t === 'bio'):
  $bios = filas('SELECT * FROM bioimpedancias WHERE usuario_id = ? ORDER BY fecha DESC', [$id]); ?>
  <div class="ad-caja">
    <h2><?= icono('scale') ?> Bioimpedancia Smart Fit <span class="der"><a href="<?= url('admin/?p=bio') ?>">Cargar varias a la vez</a></span></h2>
    <label class="zona-archivo"><?= icono('upload') ?><b>Cargar el PDF de bioimpedancia de <?= e($n1) ?></b><br><span class="sec">Se suma a su progreso y se arma el comparativo. Sus medidas manuales no se tocan.</span>
      <input type="file" accept="application/pdf" data-bio-subir data-usuario="<?= (int)$id ?>" data-nombre="<?= e($c['nombre']) ?>" data-estado="#bioEstado"></label>
    <div id="bioEstado"></div>
    <div class="mt2"><?= bio_comparativo($id, true) ?></div>
  </div>
  <?php if ($bios): ?><div class="ad-caja mt2 ad-tabla-envoltura"><h2>Reportes cargados</h2><table class="ad-tabla"><thead><tr><th>Fecha</th><th class="num">Peso</th><th class="num">% grasa</th><th class="num">Músculo</th><th>Origen</th><th></th></tr></thead><tbody>
    <?php foreach ($bios as $b): ?><tr><td><?= fecha_corta($b['fecha']) ?></td><td class="num"><?= (float)$b['peso'] ?></td><td class="num"><?= $b['grasa_pct'] !== null ? (float)$b['grasa_pct'] : '—' ?></td><td class="num"><?= $b['musculo_esq'] !== null ? (float)$b['musculo_esq'] : '—' ?></td>
      <td class="sec"><?= $b['origen'] === 'historial' ? 'Historial del reporte (redondeado)' : 'PDF cargado por ' . ($b['por'] === 'cliente' ? 'el cliente' : 'ti') ?></td>
      <td><div class="ad-acciones"><?php if ($b['archivo']): ?><a class="btn-icono" target="_blank" href="<?= e(media_url('bio', (int)$b['id'])) ?>" title="Ver PDF"><?= icono('file') ?></a><?php endif; ?>
        <form method="post" data-confirmar="¿Borrar esta bioimpedancia?"><?= csrf_campo() ?><input type="hidden" name="acc" value="borrar_bio"><input type="hidden" name="bio_id" value="<?= (int)$b['id'] ?>"><button class="btn-icono peligro" type="submit"><?= icono('trash') ?></button></form></div></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
  <?php $sc = '<script src="' . asset('js/bioimpedancia.js') . '"></script>'; ?>

<?php elseif ($t === 'datos'): ?>
  <div class="ad-rejilla">
    <form method="post" class="ad-caja"><?= csrf_campo() ?><input type="hidden" name="acc" value="editar"><h2><?= icono('edit') ?> Editar datos</h2>
      <div class="campos">
        <?= campo('nombre', 'Nombre', $c['nombre'], 'text', ['required' => true, 'ancho' => true]) ?><?= campo('email', 'Correo', $c['email'], 'email') ?><?= campo('telefono', 'WhatsApp', $c['telefono'], 'tel') ?>
        <?= campo('fecha_nacimiento', 'Nacimiento', $c['fecha_nacimiento'], 'date') ?><?= campo('sexo', 'Sexo', $c['sexo'], 'select', ['opciones' => ['' => '—', 'M' => 'Hombre', 'F' => 'Mujer']]) ?>
        <?= campo('estatura_cm', 'Estatura (cm)', $c['estatura_cm'], 'number') ?><?= campo('nivel', 'Nivel', $c['nivel'], 'select', ['opciones' => ['principiante' => 'Principiante', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado']]) ?>
        <?= campo('modalidad', 'Modalidad', $c['modalidad'], 'select', ['opciones' => ['presencial' => 'Presencial', 'virtual' => 'Virtual', 'mixta' => 'Mixta']]) ?><?= campo('cliente_desde', 'Cliente desde', $c['cliente_desde'], 'date') ?>
        <?= campo('etiquetas', 'Etiquetas (separadas por coma)', $c['etiquetas'], 'text', ['ancho' => true, 'placeholder' => 'mañanas, rodilla, referido de Ana']) ?>
        <?= campo('objetivo', 'Objetivo', $c['objetivo'], 'textarea', ['ancho' => true]) ?><?= campo('notas_admin', 'Notas privadas', $c['notas_admin'], 'textarea', ['ancho' => true]) ?>
        <?= campo('activo', 'Cuenta activa (puede entrar al portal)', (int)$c['activo'], 'checkbox', ['ancho' => true]) ?>
      </div><button class="btn btn-rojo mt2" type="submit">Guardar</button></form>
    <div class="ad-caja"><h2><?= icono('lock') ?> Acceso al portal</h2>
      <p class="sec">Entra con: <b style="color:var(--txt)"><?= e(str_ends_with($c['email'], '@sin-correo.local') ? $c['telefono'] : $c['email']) ?></b> · Consentimiento de datos: <?= $c['consentimiento'] ? fecha_corta($c['consentimiento']) : '<span style="color:var(--alerta)">no registrado</span>' ?></p>
      <form method="post" data-confirmar="¿Crear una contraseña temporal nueva? La anterior deja de funcionar."><?= csrf_campo() ?><input type="hidden" name="acc" value="clave"><button class="btn btn-borde" type="submit"><?= icono('refresh') ?> Restablecer contraseña</button></form>
    </div>
  </div>

<?php elseif ($t === 'formulario'):
  $forms = filas('SELECT * FROM formularios WHERE usuario_id = ? ORDER BY id DESC', [$id]); ?>
  <?php foreach ($forms as $f) { include __DIR__ . '/_formulario.php'; } ?>
  <?php if (!$forms): ?><div class="ad-caja"><p class="ad-vacio">No ha llenado el formulario inicial. Envíale el enlace: <a style="color:var(--rojo)" target="_blank" rel="noopener" href="<?= e(wa_link($c['telefono'], "Hola $n1, por favor llena este formulario para diseñar tu plan: " . url_absoluta('formulario.php'))) ?>">enviar por WhatsApp</a></p></div><?php endif; ?>
<?php endif; ?>
<?php admin_pie($sc ?? '');
