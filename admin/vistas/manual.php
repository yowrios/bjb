<?php
admin_cabecera('manual', 'Manual de tu web');
$web = url_absoluta('');
?>
<div class="manual" style="max-width:920px">
  <p class="sub" style="font-size:16px">Esta guía explica, paso a paso, cómo usar tu web en el día a día. Puedes volver aquí cuando quieras desde el menú: Mi web › Manual.</p>
  <div class="indice">
    <a href="#primeros"><?= icono('flame') ?> Primeros pasos</a><a href="#cliente"><?= icono('users') ?> Clientes nuevos</a><a href="#pagos"><?= icono('money') ?> Pagos</a>
    <a href="#sesiones"><?= icono('check') ?> Sesiones y medidas</a><a href="#rutinas"><?= icono('clipboard') ?> Rutinas</a><a href="#videos"><?= icono('video') ?> Videos que se venden</a>
    <a href="#precios"><?= icono('edit') ?> Precios y textos</a><a href="#contenido"><?= icono('book') ?> Contenido y redes</a><a href="#propuestas"><?= icono('send') ?> Propuestas</a>
    <a href="#visitas"><?= icono('chart') ?> Visitas y reportes</a><a href="#cronometro"><?= icono('timer') ?> El cronómetro</a><a href="#paquetes"><?= icono('book') ?> Paquetes de videos</a><a href="#tienda"><?= icono('money') ?> Tienda</a><a href="#marca"><?= icono('settings') ?> Logo, fotos y colores</a><a href="#temporadas"><?= icono('calendar') ?> Temporadas y beneficios</a><a href="#reiniciar"><?= icono('trash') ?> Reiniciar la web</a><a href="#bio"><?= icono('scale') ?> Bioimpedancia Smart Fit</a>
    <a href="#crecer"><?= icono('star') ?> Ideas para crecer</a>
  </div>

  <div class="tip">Para entrar a este panel: escribe la dirección de tu web seguida de /admin/ (por ejemplo <?= e(url_absoluta('admin/')) ?>). También hay un enlace «Panel del entrenador» al final de cada página. Si marcas «Recordarme 30 días» al ingresar, ese equipo no te vuelve a pedir la contraseña durante un mes (no lo marques en computadores prestados).</div>

  <h2 id="primeros">1. Primeros pasos (hazlo una sola vez)</h2>
  <ol>
    <li>Entra a Ajustes › Contacto y redes y revisa tu WhatsApp, tu correo y tu Instagram. Si tienes TikTok, escribe tu usuario.</li>
    <li>En Ajustes › Contacto escribe tu ciudad y el gimnasio donde entrenas: a la gente le da confianza saber dónde estás.</li>
    <li>Revisa los precios en Planes y precios. Los planes Online y Biblioteca están ocultos: ponles precio y actívalos cuando quieras.</li>
    <li>Graba los videos de las máquinas (ver la sección 6) y súbelos.</li>
    <li>Agrega la dirección de tu web en la biografía de Instagram y TikTok: <?= e($web) ?></li>
    <li>Cambia la contraseña del panel cada cierto tiempo en Ajustes › Mi cuenta, y descarga una copia de seguridad cada semana en Ajustes › Copia de seguridad.</li>
  </ol>

  <h2 id="cliente">2. Cuando llega un cliente nuevo</h2>
  <h3>Si llegó por la web</h3>
  <ol>
    <li>Te aparece en Interesados (el número rojo del menú te avisa). Si llenó el formulario inicial, verás sus respuestas y fotos completas.</li>
    <li>Escríbele con el botón verde de WhatsApp: el mensaje ya va escrito.</li>
    <li>Cuando acepte, presiona el ícono de persona («Crear como cliente»). El sistema crea su cuenta con los datos del formulario y te muestra el mensaje con su usuario y contraseña temporal para enviarle por WhatsApp.</li>
  </ol>
  <h3>Si llegó en persona</h3>
  <ol>
    <li>Presiona «+ Cliente» arriba a la derecha.</li>
    <li>Llena nombre, WhatsApp y correo. Si ya pagó, elige el plan y deja marcada la casilla «Registrar también el pago».</li>
    <li>Envíale el mensaje de bienvenida con su acceso. Al entrar por primera vez, el sistema le pide cambiar la contraseña.</li>
  </ol>
  <div class="tip">Si un cliente olvida su contraseña, abre su ficha › Datos › Restablecer contraseña, y envíale la nueva por WhatsApp.</div>

  <h2 id="pagos">3. Pagos</h2>
  <ol>
    <li>Cuando alguien paga desde la web, sube la foto del comprobante y te aparece en Pagos › Por revisar.</li>
    <li>Revisa en tu Nequi, Daviplata o banco que el dinero sí llegó. Nunca apruebes solo por la foto.</li>
    <li>Presiona «Aprobar y activar». El sistema activa solo lo que compró: el plan (con sus sesiones y fecha de vencimiento) o el acceso al video.</li>
    <li>Te aparece el botón para avisarle por WhatsApp que ya quedó activo.</li>
  </ol>
  <p>Si te pagan en efectivo o por fuera de la web, regístralo desde la ficha del cliente › Plan y sesiones (al activar el plan) o › Pagos.</p>

  <h2 id="sesiones">4. Sesiones, medidas y fotos</h2>
  <ul>
    <li>Cada vez que entrenes con un cliente, abre su ficha y presiona «Asistió hoy». Se descuenta una sesión de su plan y él lo ve en su portal.</li>
    <li>Si te equivocas, en Plan y sesiones está el botón «Deshacer la última sesión».</li>
    <li>En la valoración mensual, entra a Evolución › Nueva valoración y escribe peso, porcentaje de grasa y medidas. El cliente ve sus gráficas al instante.</li>
    <li>Sube las fotos de frente, perfil y espalda. Con el botón «Reporte de evolución» generas un PDF con gráficas y fotos de antes y ahora para enviarle: es la mejor herramienta para que renueve.</li>
    <li>Para pausar un plan (viaje o incapacidad), cambia el estado a «Pausada» y mueve la fecha de vencimiento.</li>
  </ul>

  <h2 id="rutinas">5. Rutinas</h2>
  <ol>
    <li>En Rutinas crea plantillas (por ejemplo «Full body principiante» o «Pierna y glúteo»). Ya tienes 2 de ejemplo.</li>
    <li>En cada renglón escribe el día (Día A, Día B...), elige la máquina, las series, las repeticiones y el descanso en segundos.</li>
    <li>Deja «Carga» vacía y el sistema calcula la carga sugerida con el peso, sexo, edad y nivel de cada cliente.</li>
    <li>Para dársela a un cliente: su ficha › Rutinas › Asignar. Se crea una copia solo para él, que puedes ajustar.</li>
    <li>El cliente ve su rutina en el portal y presiona «Entrenar con cronómetro»: el cronómetro lo guía serie por serie, con sus descansos y cargas, y al final guarda el entreno en su historial (y tú lo ves en su ficha).</li>
  </ol>

  <h2 id="videos">6. Videos de máquinas (una fuente de ingresos)</h2>
  <p>Cada máquina es un producto que vendes. Un visitante que no sabe usar la prensa paga, tú apruebas el pago y puede ver el video, los pasos, los errores comunes y la carga sugerida para su peso.</p>
  <ol>
    <li>Graba el video con el celular en horizontal: explica cómo ajustar la máquina, haz 3 o 4 repeticiones perfectas y muestra los errores más comunes. Entre 1 y 3 minutos es ideal.</li>
    <li>Súbelo a YouTube como «No listado» (no aparece en búsquedas) y copia el enlace. También puedes subir el archivo MP4 directo, si tu hosting lo permite.</li>
    <li>En Videos y máquinas abre la máquina, pega el enlace, sube una foto y revisa el precio.</li>
    <li>Para agregar una máquina nueva: «Agregar máquina». Marca los músculos que trabaja y se iluminan en el dibujo del cuerpo.</li>
    <li>Deja 1 o 2 videos gratis: atraen gente y demuestran la calidad de lo que vendes.</li>
  </ol>
  <div class="tip">Ideas de paquetes: «Pierna completa» (5 videos), «Primer mes en el gimnasio», o el plan Biblioteca con acceso a todo por 30 días.</div>

  <h2 id="precios">7. Cambiar precios y textos (sin programar)</h2>
  <ul>
    <li>Precios: Planes y precios › escribe el nuevo valor › Guardar precios. Se actualiza en la web, el catálogo y la página de pago.</li>
    <li>Beneficios de cada plan: botón Editar › «Qué incluye», un beneficio por línea.</li>
    <li>Textos de la portada, tu historia, las cifras y los 4 pasos del método: Ajustes › Textos de la portada.</li>
    <li>Promociones: Ajustes › Textos › «Aviso en la parte superior». Ejemplo: «Valoración gratis esta semana». Bórralo cuando termine.</li>
    <li>Colores: Ajustes › Colores.</li>
  </ul>

  <h2 id="contenido">8. Contenido, redes y catálogos</h2>
  <ul>
    <li>Instagram y TikTok: en Contenido › Instagram y TikTok pega el enlace de cada publicación que quieras mostrar en la portada. Ya están cargadas tus 12 publicaciones más recientes (6 visibles).</li>
    <li>Artículos: Contenido › Artículos. Escribe consejos cortos. Cada artículo ayuda a que Google te encuentre.</li>
    <li>Antes y después: sube las dos fotos y la web las muestra con un deslizador. Pide siempre autorización escrita al cliente.</li>
    <li>Testimonios: publica solo testimonios reales. Pídelos por WhatsApp a tus clientes contentos.</li>
    <li>Catálogos: sube PDF (planes, guías, menús). Puedes pedir nombre y WhatsApp antes de descargar: cada descarga te llega como interesado.</li>
    <li>Recomendaciones: consejos que ven todos tus clientes en su portal. Para uno solo, hazlo desde su ficha › Check-ins.</li>
  </ul>

  <h2 id="propuestas">9. Propuestas personalizadas</h2>
  <ol>
    <li>En Propuestas › Nueva propuesta (o desde un interesado, con el ícono de enviar).</li>
    <li>Agrega uno o varios planes, ajusta el precio total si quieres dar un descuento y pon una fecha límite.</li>
    <li>Guarda y presiona «Enviar por WhatsApp». La persona recibe un enlace con una propuesta elegante, con tu logo.</li>
    <li>Verás cuántas veces la abrió y cuándo. Si presiona «Acepto», te llega por WhatsApp y la propuesta queda como aceptada.</li>
  </ol>

  <h2 id="visitas">10. Visitas, check-ins y reportes</h2>
  <ul>
    <li>Visitas muestra cuántas personas entraron, de dónde llegaron (Instagram, Google, WhatsApp...), desde qué ciudad, en qué dispositivo, a qué hora y qué miraron. Si alguien dejó sus datos o es cliente, verás su nombre y todas sus visitas.</li>
    <li>El embudo te dice dónde se pierde la gente: si muchos miran planes y pocos escriben, revisa precios o agrega una promoción.</li>
    <li>Check-ins: tus clientes cuentan cómo les fue en la semana. Responde aquí: tu respuesta les aparece en su portal.</li>
    <li>Reportes: ingresos por mes, clientes nuevos, sesiones, mejores clientes y la lista de quienes vencieron y no renovaron, con botón para invitarlos a volver. Todo se descarga en Excel.</li>
  </ul>

  <h2 id="cronometro">11. El cronómetro</h2>
  <p>Es gratis para cualquier visitante y funciona en el celular sin descargar nada, incluso sin internet. Tiene modo Series (fuerza e hipertrofia), Intervalos (HIIT, Tabata, EMOM), Rutina (la que tú le asignas), cronómetro libre y cuenta regresiva. Avisa con pitido, voz y vibración, cambia de color en trabajo y descanso, y si alguien deja la pausa demasiado tiempo le avisa. El tiempo del aviso se cambia en Ajustes › Opciones.</p>
  <div class="tip">Comparte el enlace del cronómetro en tus historias: <?= e(url_absoluta('cronometro.php')) ?>. Es una forma de que la gente use tu marca todos los días en el gimnasio.</div>

  <h2 id="paquetes">12. Paquetes de videos</h2>
  <ol>
    <li>Entra a Mi servicio › Paquetes de videos › Nuevo paquete.</li>
    <li>Escribe el nombre (por ejemplo «Tren inferior · Piernas»), el precio y marca los videos que incluye. El panel te muestra cuánto costarían sueltos para que pongas el «precio antes».</li>
    <li>Elige la rutina que recibe el cliente al comprar, o marca «Crear automáticamente una rutina con estos videos».</li>
    <li>Cuando apruebas el pago, el cliente recibe todos los videos del paquete y la rutina en su portal, lista para entrenar con el cronómetro.</li>
  </ol>
  <p>Ya hay uno de ejemplo: Tren inferior a $30.000 con hack, prensa, extensión, aductores y pantorrilla.</p>

  <h2 id="tienda">13. Tienda de suplementos y accesorios</h2>
  <ul>
    <li>En Mi servicio › Tienda agrega productos con foto (cuadrada, fondo blanco), precio, marca, presentación y unidades disponibles.</li>
    <li>La tienda empieza apagada. Mientras esté apagada solo tú la ves (en la vista previa). Cuando consigas el patrocinio presiona «Encender la tienda» y aparece en el menú y en la portada.</li>
    <li>Los clientes agregan productos al carrito, eligen la entrega y suben el comprobante (o eligen pagar al recibir). El pedido te llega en Pagos y en Tienda › Pedidos.</li>
    <li>Al aprobar el pago se descuentan las unidades. Cambia el estado de la entrega (por preparar, por entregar, entregado) en la lista de pedidos.</li>
  </ul>

  <h2 id="marca">14. Logo, nombre, fotos y colores</h2>
  <ul>
    <li>Ajustes › Logo y fotos: cambia el nombre de la marca, el logo, la foto principal, la foto de «Sobre mí» y la textura del fondo. Al lado de cada una están las medidas recomendadas. Si no te gusta, marca «Volver a la imagen original».</li>
    <li>Ajustes › Colores: elige una de las 9 combinaciones o «Mis propios colores» con los selectores de color.</li>
    <li>Ajustes › Gimnasio: nombre, dirección y mapa del gimnasio donde entrenas. El logo del gimnasio súbelo solo si el gimnasio te autoriza por escrito a usarlo.</li>
  </ul>

  <h2 id="temporadas">15. Temporadas y beneficios</h2>
  <ul>
    <li>En Mi web › Temporadas y beneficios están Navidad, Año nuevo, San Valentín, Día de la mujer, Día de la actividad física, Día del niño, Día de la madre, Día del padre, Independencia, Amor y amistad, Octubre rosa, Halloween y Black Friday.</li>
    <li>Cada una cambia los colores de la web, pone un aviso arriba con tu mensaje (por ejemplo una promoción) y adornos que caen suavemente. Tus visitantes pueden ocultarlos con la ×.</li>
    <li>Modo Automática: se enciende sola en sus fechas y al terminar la web vuelve a tus colores. Encendida: se muestra ya, sin importar la fecha. Apagada: nunca.</li>
    <li>Con «Ver» revisas cómo se vería antes de activarla. «Mantener mis colores» deja tus colores y solo pone el aviso y los adornos.</li>
    <li>Beneficios: el regalo de cumpleaños (por defecto, una clase de 15 minutos) aparece en el portal del cliente el día de su cumpleaños, y tú ves los cumpleaños de la semana en el Resumen con el saludo listo para WhatsApp. También puedes activar «Trae a un amigo», «Valoración gratis» o uno propio.</li>
  </ul>

  <h2 id="reiniciar">16. Restablecer y reiniciar</h2>
  <ul>
    <li>Si cambiaste colores o textos y quieres volver a los sugeridos: Ajustes › Colores › «Restablecer colores sugeridos». En Textos y en Logo y fotos hay botones parecidos.</li>
    <li>Ajustes › Reiniciar la web › «Borrar datos de prueba»: borra clientes, pagos y todo lo de prueba, y conserva tus planes, videos, productos, textos y colores.</li>
    <li>«Restablecer de fábrica»: deja la web como nueva, sin administrador. Úsalo al entregarla. Ambas opciones piden tu contraseña y escribir BORRAR, y guardan una copia de seguridad antes.</li>
  </ul>

  <h2 id="bio">18. Bioimpedancia de Smart Fit</h2>
  <ul>
    <li>Día a día › Bioimpedancia: arrastra uno o varios PDF del reporte de Smart Fit. La web los lee y te muestra el nombre, la fecha, el peso, el porcentaje de grasa y el músculo antes de guardar.</li>
    <li>Si la persona ya es cliente, se elige sola por el nombre. Si no existe, aparece «Crear cliente nuevo»: se crea con sus datos y una contraseña temporal.</li>
    <li>Cada mes, el cliente (desde Mi evolución en su portal) o tú (desde su ficha › Bioimpedancia) cargan el nuevo PDF y se arma el comparativo: primera, anterior y última medición, con flechas verdes cuando mejora y rojas cuando empeora, la gráfica y la comparación por segmento del cuerpo.</li>
    <li>El reporte trae el historial de meses anteriores: se agrega solo, marcado como «historial» (valores redondeados).</li>
    <li>Tus medidas manuales nunca se reemplazan: lo de Smart Fit queda aparte, marcado «Smart Fit». Si cargan dos veces el mismo reporte, se actualiza en vez de duplicarse.</li>
  </ul>

  <h2 id="crecer">19. Ideas para conseguir más clientes</h2>
  <ul>
    <li>Pon el enlace de la web en tu biografía y en cada historia. Para saber qué publicación funciona, agrega al final del enlace ?utm_campaign=nombre (por ejemplo ?utm_campaign=reto-noviembre) y míralo en Visitas.</li>
    <li>Publica un video corto por semana de una máquina («El error que todos cometen en la prensa») e invita a ver el video completo en tu web.</li>
    <li>Haz un reto de 21 o 30 días con cupos limitados y un precio de lanzamiento, usando el aviso superior de la portada.</li>
    <li>Pide a cada cliente que renueva un testimonio y, si acepta, sus fotos de antes y después.</li>
    <li>Revisa cada lunes el Resumen: planes por vencer, pagos pendientes e interesados sin responder. Responder rápido es lo que más vende.</li>
    <li>Ofrece referidos: si un cliente trae a un amigo, una sesión gratis para los dos.</li>
    <li>Usa el reporte de evolución mensual: un cliente que ve su progreso en un PDF bonito renueva.</li>
  </ul>
  <p class="sec mt3">Versión <?= APP_VERSION ?>. Si algo no funciona, toma una captura de pantalla y envíala a quien te instaló la web.</p>
</div>
<?php admin_pie();
