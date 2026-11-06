<?php
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
$resp = ajuste('pago_titular') ?: ajuste('nombre_entrenador');
cabecera('Tratamiento de datos y términos', ['body' => 'pagina']);
?>
<section class="cabeza-pag" style="padding-bottom:30px"><div class="envoltura" style="max-width:860px"><h1 class="titulo" style="font-size:clamp(36px,5vw,60px)">Tratamiento de datos y términos</h1></div></section>
<section class="seccion" style="padding-top:20px">
  <article class="articulo envoltura">
    <h2>Responsable</h2>
    <p><?= e($resp) ?> (<?= e(ajuste('marca')) ?>), contacto: <?= e(ajuste('email')) ?> · WhatsApp <?= e(ajuste('whatsapp')) ?>.</p>
    <h2>Qué datos recogemos y para qué</h2>
    <ul>
      <li>Datos de contacto (nombre, WhatsApp, correo) para responder tus solicitudes y prestarte el servicio.</li>
      <li>Datos de salud y del cuerpo (peso, medidas, lesiones, patologías, alimentación, fotos de progreso). Son <strong>datos sensibles</strong>: solo se usan para diseñar y hacer seguimiento a tu plan de entrenamiento y alimentación. Darlos es opcional y requiere tu autorización expresa.</li>
      <li>Comprobantes de pago para verificar y activar tu plan.</li>
      <li>Datos de navegación (páginas visitadas, tipo de dispositivo, ciudad aproximada) para mejorar la página. No vendemos ni compartimos estos datos.</li>
    </ul>
    <h2>Tus derechos</h2>
    <p>Conforme a la Ley 1581 de 2012 y sus decretos reglamentarios, puedes conocer, actualizar, rectificar y pedir la supresión de tus datos, revocar la autorización y presentar quejas ante la Superintendencia de Industria y Comercio. Escríbenos al correo o WhatsApp indicados.</p>
    <h2>Seguridad</h2>
    <p>Tus fotos y comprobantes se guardan fuera del acceso público y solo los ves tú y tu entrenador cuando inicias sesión.</p>
    <h2>Salud y responsabilidad</h2>
    <p>Las calculadoras, cargas sugeridas y artículos son orientación general y no reemplazan una valoración médica. Antes de iniciar un programa de ejercicio, especialmente si tienes alguna condición de salud, consulta a tu médico. Informa siempre a tu entrenador cualquier lesión, dolor o síntoma.</p>
    <h2>Pagos y accesos</h2>
    <p>Los planes se activan cuando el pago es verificado. Los accesos a videos son personales e intransferibles y duran el tiempo indicado al comprarlos. Las sesiones de cada plan deben usarse dentro de su vigencia.</p>
    <p style="color:var(--txt-3);font-size:14px">Última actualización: <?= fecha_larga(hoy()) ?>.</p>
  </article>
</section>
<?php pie(); ?>
