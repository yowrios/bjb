<?php /* Ventanas emergentes reutilizables: contacto rápido y descarga de catálogos. */ ?>
<div class="modal" id="modalLead" role="dialog" aria-modal="true" aria-labelledby="mlTit">
  <div class="modal-caja">
    <button class="modal-cerrar" type="button" aria-label="Cerrar"><?= icono('x') ?></button>
    <span class="antetitulo">Hablemos</span>
    <h3 id="mlTit" class="titulo" style="font-size:38px">Recibe tu plan</h3>
    <p class="sub" style="font-size:15px">Déjame tus datos y te escribo por WhatsApp para contarte cuál programa es el ideal para ti.</p>
    <form data-lead>
      <input type="hidden" name="origen" value="calculadora">
      <input type="hidden" name="interes" id="modalLeadValor" value="">
      <div class="campos">
        <div class="campo ancho"><label for="mlNom">Nombre</label><input id="mlNom" name="nombre" required maxlength="120" autocomplete="name"></div>
        <div class="campo ancho"><label for="mlTel">WhatsApp</label><input id="mlTel" name="telefono" type="tel" required maxlength="20" autocomplete="tel" placeholder="300 123 4567"></div>
        <div class="campo ancho"><label for="mlObj">¿Cuál es tu objetivo?</label><select id="mlObj" name="mensaje"><option>Perder grasa</option><option>Ganar masa muscular</option><option>Mejorar mi condición física</option><option>Recuperarme de una lesión</option><option>Aprender a entrenar</option></select></div>
        <label class="check ancho"><input type="checkbox" name="acepto" value="1" required> <span>Autorizo el tratamiento de mis datos según la <a href="<?= url('privacidad.php') ?>" target="_blank" style="color:var(--rojo)">política de datos</a>.</span></label>
      </div>
      <button class="btn btn-rojo btn-bloque mt2" type="submit"><?= icono('send') ?> Enviar</button>
    </form>
  </div>
</div>
<div class="modal" id="modalDescarga" role="dialog" aria-modal="true" aria-labelledby="mdTit">
  <div class="modal-caja">
    <button class="modal-cerrar" type="button" aria-label="Cerrar"><?= icono('x') ?></button>
    <span class="antetitulo">Descarga gratis</span>
    <h3 id="mdTit" class="titulo" style="font-size:38px">¿A quién se lo enviamos?</h3>
    <p class="sub" style="font-size:15px">Escribe tu nombre y tu WhatsApp y la descarga empieza de inmediato.</p>
    <form data-lead>
      <input type="hidden" name="origen" value="catalogo">
      <input type="hidden" name="catalogo" id="modalDescargaValor" value="">
      <div class="campos">
        <div class="campo ancho"><label for="mdNom">Nombre</label><input id="mdNom" name="nombre" required maxlength="120" autocomplete="name"></div>
        <div class="campo ancho"><label for="mdTel">WhatsApp</label><input id="mdTel" name="telefono" type="tel" required maxlength="20" autocomplete="tel" placeholder="300 123 4567"></div>
        <label class="check ancho"><input type="checkbox" name="acepto" value="1" required> <span>Autorizo el tratamiento de mis datos según la <a href="<?= url('privacidad.php') ?>" target="_blank" style="color:var(--rojo)">política de datos</a>.</span></label>
      </div>
      <button class="btn btn-rojo btn-bloque mt2" type="submit"><?= icono('download') ?> Descargar</button>
    </form>
  </div>
</div>
