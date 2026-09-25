=== Harmony Saved Addresses for WooCommerce ===
Contributors: harmonyplugins
Tags: woocommerce, checkout, direcciones, addresses, mexico
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
WC requires at least: 7.0
WC tested up to: 9.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convierte la seccion de direccion del checkout de WooCommerce en un selector visual de direcciones guardadas, similar a la experiencia de Mercado Libre.

== Descripcion ==

Harmony Saved Addresses for WooCommerce reemplaza el formulario tradicional de direccion del checkout por tarjetas visuales con las direcciones guardadas del cliente. El cliente puede elegir, editar, agregar o eliminar direcciones sin salir del checkout, y WooCommerce recalcula automaticamente envio, impuestos y totales.

Caracteristicas principales:

* Selector visual de direcciones guardadas en el checkout.
* Multiples direcciones por cliente, con alias (Casa, Trabajo, etc.).
* Edicion, eliminacion y marcado de direccion predeterminada.
* Formulario de direccion en ventana modal, con validacion en el servidor.
* Direccion de facturacion independiente (opcional).
* Nueva seccion "Mis direcciones" dentro de Mi cuenta.
* Panel de ajustes en WooCommerce > Ajustes > Direcciones guardadas.
* Importacion automatica de la direccion clasica de WooCommerce como primera direccion guardada.
* Compatible con el checkout clasico (shortcode) y con WooCommerce Checkout Blocks (sincronizacion best-effort via el store de datos del bloque).
* Compatible con HPOS (almacenamiento de pedidos de alto rendimiento).
* No modifica archivos del tema, de WooCommerce ni de WordPress.

== Instalacion ==

1. En tu panel de WordPress ve a Plugins > Anadir nuevo > Subir plugin.
2. Selecciona el archivo harmony-saved-addresses.zip y pulsa "Instalar ahora".
3. Activa el plugin "Harmony Saved Addresses for WooCommerce".
4. Ve a WooCommerce > Ajustes > Direcciones guardadas para configurar el numero maximo de direcciones, el color principal, el texto del boton y demas opciones.
5. Visita la pagina de checkout con una cuenta de cliente para ver el selector en accion. Si el cliente ya tenia una direccion de facturacion capturada previamente, el plugin la importara automaticamente como su primera direccion guardada.
6. Revisa Mi cuenta > Mis direcciones para administrar direcciones fuera del checkout.

Instalacion manual (FTP):

1. Descomprime harmony-saved-addresses.zip.
2. Sube la carpeta harmony-saved-addresses completa a /wp-content/plugins/.
3. Activa el plugin desde el panel de Plugins de WordPress.

== Notas sobre WooCommerce Checkout Blocks ==

El plugin declara compatibilidad con "cart_checkout_blocks" y sincroniza la direccion seleccionada con el store de datos del checkout por bloques (wc/store/cart) cuando este disponible en el navegador. Si tu tienda usa el checkout clasico basado en shortcode [woocommerce_checkout] (el mas comun), la integracion es completa. Si usas el checkout 100% por bloques, te recomendamos probar el flujo completo antes de publicarlo, ya que WooCommerce Blocks evoluciona rapido y algunas versiones futuras podrian requerir ajustes menores en el script de sincronizacion.

== Registro de errores ==

El plugin utiliza el sistema de logs de WooCommerce (WooCommerce > Estado > Registros) con la fuente "harmony-saved-addresses" para registrar la creacion, edicion y eliminacion de direcciones, y para depurar errores.

== Preguntas frecuentes ==

= ¿El plugin modifica archivos de mi tema o de WooCommerce? =
No. Todo el comportamiento vive dentro de la carpeta del plugin. Las plantillas visuales pueden sobrescribirse de forma segura copiandolas a wp-content/themes/tu-tema/harmony-saved-addresses/, siguiendo el sistema estandar de plantillas de WooCommerce.

= ¿Que pasa si el cliente edita o borra una direccion despues de comprar? =
Los pedidos ya realizados no cambian: la direccion utilizada se copia dentro de los metadatos del pedido en el momento de la compra.

= ¿Puedo desactivar las direcciones multiples? =
Si, desde WooCommerce > Ajustes > Direcciones guardadas puedes ajustar el numero maximo de direcciones o restringir otras opciones.

== Changelog ==

= 1.1.1 =
* Rediseño visual de la página de confirmación de pedido (Thank You Page): encabezado centrado, tarjeta de información en 3 columnas, listado de productos con cantidad en insignia circular, tarjeta de resumen de compra con total destacado, tarjeta de estado de pago y botón "Seguir comprando" con la identidad visual del sitio (gradiente morado, tipografía y espaciados de Harmony).

= 1.1.0 =
* Nueva página de confirmación de pedido personalizada (Thank You Page), manteniendo la cabecera y el pie del tema activo mediante el sistema de plantillas de WooCommerce.

= 1.0.0 =
* Version inicial del plugin.
