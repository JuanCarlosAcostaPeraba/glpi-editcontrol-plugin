# Instalación autorizada del 16 de noviembre de 2026

El usuario indicó expresamente probar en producción y, después, concretó el alcance:
instalar, comprobar que GLPI sigue funcionando y realizar él mismo la prueba con
un compañero. No se crearon tickets de prueba ni se activó el bloqueo estricto.

Estado final:

- GLPI 11.0.4; PHP 8.2.35; MariaDB 11.8.3.
- Plugin 0.3.0 instalado y activado por la consola oficial, ejecutada como usuario apache.
- Modo `strict`, toma de control `editors`, TTL 120 segundos.
- ObjectLock nativo ya estaba desactivado; no se cambió esa configuración.
- Código del plugin separado del núcleo, sin modificar archivos centrales.
- Copia previa de metadatos y de la versión inicial instalada fuera del directorio web.
- No se reiniciaron Apache, PHP-FPM ni la base de datos.

Comprobaciones realizadas:

- Sintaxis con el PHP real del servidor.
- Creación de las tablas del plugin y carga de configuración.
- GET_LOCK y RELEASE_LOCK reales en MariaDB, sin modificar tickets.
- Plugin listado como activo.
- Página de acceso y JavaScript devueltos con HTTP 200.
- Revisión del tramo nuevo del registro después de la corrección, sin nuevos errores del plugin.
- 32 comprobaciones PHP locales, incluyendo ocho procesos concurrentes sobre el modelo SQLite.
- Seis escenarios de navegador con respuestas simuladas y revisión visual a escritorio y 360 px.
- La cabecera corregida se comprobó contra CheckCsrfListener real de GLPI 11.0.4,
  en una prueba CLI aislada en memoria, sin autenticar usuarios ni crear sesiones persistentes.

Durante la primera comprobación se detectaron y corrigieron tres problemas:
ubicación del recurso JavaScript, ámbito de la variable global DB en la pantalla
de configuración y avisos por INSERT IGNORE. Se desactivó temporalmente el plugin
durante la sustitución. El modo aviso también se reforzó para no impedir guardados
si falla la comprobación del plugin, y para no romper el renderizado del formulario.

La revisión 0.1.3 responde a la petición de simplificar la barra: se adquiere el
control al abrir la primera pestaña elegible y se muestra el nombre junto con
Solicitar control para el otro técnico. Se retiraron Editar, Terminar edición,
Recargar ticket y Tomar control de esa barra. La administración sigue disponible
en su panel. La salida intenta liberar mediante fetch keepalive y mantiene TTL.

También corrige el rechazo CSRF: GLPI 11 exige X-Glpi-Csrf-Token cuando la petición
declara XMLHttpRequest, aunque el cuerpo ya incluya el token. No se desactiva
ninguna protección. Una respuesta HTML o JSON inválida muestra un aviso comprensible.
Los campos virtuales del formulario se excluyen de la huella y la respuesta de
adquisición calcula correctamente la propiedad usando el token recién emitido.

## Prueba manual pendiente

1. Recargar completamente la página del ticket (Ctrl+F5 si es necesario).
2. Abrir un ticket: la primera pestaña visible con UPDATE adquiere automáticamente.
3. El compañero abre el mismo ticket y comprueba el nombre del propietario y el aviso.
4. El compañero pulsa Solicitar control y el propietario recibe el aviso con Ceder control.
5. El propietario cede sin salir; el compañero recibe el control sin recargar su página.
6. Comprobar que se conservan los textos sin guardar de ambas pestañas.

## Actualización del 17 de noviembre: 0.2.0

La cesión es directa y atómica a la pestaña que solicitó el control, usando sus
hashes de token y sesión registrados previamente. No se libera el ticket para
que compitan todos. Un clic con una solicitud ya sustituida se rechaza, y el token
del cedente deja de ser válido. El historial registra envío y recepción de control.
La instalación añade los dos campos mediante la migración v1 → v2 de GLPI.

Validación de 0.2.0: 48 comprobaciones PHP, los seis escenarios de navegador y
una cesión entre dos pestañas con backend simulado. Además se comprobó la cesión
real contra MariaDB en tablas TEMPORARY de una conexión independiente: receptor
correcto, versión del ticket conservada, rechazo del token anterior, exclusión de
un tercero y liberación por el receptor. No se modificaron tickets, usuarios ni
auditoría permanentes durante esa prueba. Modo aviso y TTL 120 se conservaron.

Las pestañas que estaban abiertas con 0.1.3 necesitan cargar 0.2.0 una vez para usar
el protocolo nuevo. Después, avisos y cesiones no recargan la página ni sus formularios.

En modo aviso los guardados siguen permitidos. Esta prueba no valida todavía
el rechazo de escrituras ni certifica todos los casos de concurrencia.

## Actualización del 17 de noviembre: bloqueo estricto y recuperación

El usuario autorizó explícitamente el modo estricto y confirmó que todos los
técnicos con permiso de edición del ticket pueden tomar el control sin solicitarlo.
La barra de quienes no son propietarios es fucsia, con contraste blanco 5,16:1.
Tomar control conserva borradores, sustituye el token y registra la operación.
Una generación distinta a la mostrada rechaza el clic para evitar robar a un
propietario que acaba de cambiar inadvertidamente.

Se añadieron guardas para técnicos, grupos, proveedores, activos, asociaciones de
documentos, validaciones, satisfacción, costes, relaciones y restauración. Una
relación entre tickets no se modifica si el otro está bajo control de otra pestaña.
El contexto se transmite también en fetch y formularios ordinarios, además de jQuery.
Las API oficiales, CLI y autoservicio siguen su política de integración documentada.

Los campos del técnico que pierde el control pasan a solo lectura, conservando el
texto. Su pestaña no vuelve a adquirirlo automáticamente después de perderlo.
Los navegadores que permanecen abiertos con una versión anterior deben cargar la
actualización una vez; el servidor aplica estricto desde su activación.

Estado verificado: 0.3.0 activa, schema_version=2, mode=strict,
takeover_policy=editors y TTL 120. GLPI, JavaScript y CSS responden HTTP 200.
Pasaron 52 comprobaciones de servicio, 17 guardas CGI y los 12 escenarios de
navegador. En MariaDB real se verificaron robo sin solicitud, escritura del
propietario y rechazo de token anterior/generación obsoleta usando exclusivamente
tablas temporales en una conexión independiente.

## Desactivación inmediata

Desde Configuración → Complementos, desactivar **Control de edición**, o ejecutar
por SSH como administrador del servidor:

```sh
cd /var/www/html/glpi
runuser -u apache -- php bin/console plugin:deactivate editcontrol --no-interaction
```

La desactivación conserva las tablas y el historial y deja de cargar los hooks.
No hace falta desinstalar ni editar el núcleo para recuperar el comportamiento anterior.
