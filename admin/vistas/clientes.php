<?php
$f = $_GET['f'] ?? 'todos';
$h = hoy();
$lista = filas("SELECT u.*,
    (SELECT m.nombre_plan FROM membresias m WHERE m.usuario_id = u.id AND m.estado = 'activa' ORDER BY m.inicio DESC LIMIT 1) AS plan,
    (SELECT m.fin FROM membresias m WHERE m.usuario_id = u.id AND m.estado = 'activa' ORDER BY m.inicio DESC LIMIT 1) AS fin,
    (SELECT m.sesiones_usadas FROM membresias m WHERE m.usuario_id = u.id AND m.estado = 'activa' ORDER BY m.inicio DESC LIMIT 1) AS usadas,
    (SELECT m.sesiones_total FROM membresias m WHERE m.usuario_id = u.id AND m.estado = 'activa' ORDER BY m.inicio DESC LIMIT 1) AS total,
    (SELECT md.peso FROM medidas md WHERE md.usuario_id = u.id AND md.peso IS NOT NULL ORDER BY md.fecha DESC, md.id DESC LIMIT 1) AS peso,
    (SELECT MIN(m.inicio) FROM membresias m WHERE m.usuario_id = u.id) AS primer_plan
    FROM usuarios u WHERE u.rol = 'cliente' ORDER BY u.nombre");
$lista = array_filter($lista, function ($c) use ($f, $h) {
    return match ($f) {
        'activos' => $c['plan'] && (!$c['fin'] || $c['fin'] >= $h),
        'sinplan' => !$c['plan'],
        'vencer' => $c['plan'] && $c['fin'] && $c['fin'] <= date('Y-m-d', strtotime('+7 days')),
        'inactivos' => !$c['activo'],
        default => true,
    };
});
if (isset($_GET['exportar'])) {
    csv_descarga('clientes', ['Nombre', 'Correo', 'WhatsApp', 'Plan activo', 'Vence', 'Sesiones', 'Peso actual', 'Cliente desde', 'Último acceso', 'Origen'],
        array_map(fn($c) => [$c['nombre'], $c['email'], $c['telefono'], $c['plan'], $c['fin'], $c['plan'] ? $c['usadas'] . '/' . $c['total'] : '', $c['peso'], $c['cliente_desde'] ?: $c['primer_plan'], $c['ultimo_acceso'], $c['origen']], $lista));
}
admin_cabecera('clientes', 'Clientes');
$filtros = ['todos' => 'Todos', 'activos' => 'Con plan activo', 'vencer' => 'Vencen en 7 días', 'sinplan' => 'Sin plan', 'inactivos' => 'Cuentas desactivadas'];
?>
<div class="ad-barra">
  <input type="search" placeholder="Buscar por nombre, correo o celular..." data-filtrar="#tablaClientes" aria-label="Buscar">
  <select onchange="location.href='<?= url('admin/?p=clientes&f=') ?>'+this.value" aria-label="Filtro"><?php foreach ($filtros as $k => $t): ?><option value="<?= $k ?>" <?= $f === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
  <div class="der">
    <a class="btn btn-sm btn-borde" href="<?= url('admin/?p=clientes&f=' . urlencode($f) . '&exportar=1') ?>"><?= icono('download') ?> Excel</a>
    <a class="btn btn-sm btn-rojo" href="<?= url('admin/?p=cliente&nuevo=1') ?>"><?= icono('plus') ?> Nuevo cliente</a>
  </div>
</div>
<div class="ad-caja ad-tabla-envoltura">
  <table class="ad-tabla" id="tablaClientes">
    <thead><tr><th>Cliente</th><th>Plan activo</th><th>Vence</th><th class="num">Sesiones</th><th class="num">Peso</th><th>Cliente desde</th><th>Último ingreso</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $c): $ini = mb_strtoupper(mb_substr($c['nombre'], 0, 1)); $vence = $c['fin'] && $c['fin'] <= date('Y-m-d', strtotime('+7 days')); ?>
      <tr>
        <td><a class="ad-persona" href="<?= url('admin/?p=cliente&id=' . $c['id']) ?>" style="text-decoration:none"><span class="ad-avatar"><?= e($ini) ?></span><span><b><?= e($c['nombre']) ?></b><?= !$c['activo'] ? ' <span class="chip">Desactivada</span>' : '' ?><br><span class="sec"><?= e($c['email']) ?> · <?= e($c['telefono']) ?></span></span></a></td>
        <td><?= $c['plan'] ? e($c['plan']) : '<span class="sec">—</span>' ?></td>
        <td><?= $c['fin'] ? '<span style="color:' . ($vence ? 'var(--alerta)' : 'inherit') . '">' . fecha_corta($c['fin']) . '</span>' : '—' ?></td>
        <td class="num"><?= $c['plan'] ? (int)$c['usadas'] . '/' . (int)$c['total'] : '—' ?></td>
        <td class="num"><?= $c['peso'] ? (float)$c['peso'] . ' kg' : '—' ?></td>
        <td><?= fecha_corta($c['cliente_desde'] ?: $c['primer_plan']) ?></td>
        <td class="sec"><?= hace($c['ultimo_acceso']) ?></td>
        <td><div class="ad-acciones">
          <a class="btn-icono wa" href="<?= e(wa_link($c['telefono'])) ?>" target="_blank" rel="noopener" title="WhatsApp"><?= icono('whatsapp') ?></a>
          <a class="btn-icono" href="<?= url('admin/?p=cliente&id=' . $c['id']) ?>" title="Abrir ficha"><?= icono('arrow') ?></a></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$lista): ?><tr><td colspan="8" class="ad-vacio">No hay clientes en este filtro. <a href="<?= url('admin/?p=cliente&nuevo=1') ?>" style="color:var(--rojo)">Crea el primero</a>.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php admin_pie();
