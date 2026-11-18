# Operación y mantenimiento

## Configuración

Solo un usuario con `config UPDATE` modifica TTL, modo y concesiones de perfiles.
El derecho específico `plugin_editcontrol_admin` con READ autoriza tomar y liberar
el control. No concede acceso adicional a tickets: se comprueba Ticket::can y se
mantienen las restricciones de perfil/entidad. Separar ambos tipos de administradores.

TTL de solicitudes: 60–1800 segundos, por defecto 120. Heartbeat: TTL/3, mínimo 15 segundos.
Desde 0.3.1 el control del propietario no caduca, tampoco con el navegador suspendido.
La recuperación de un control abandonado es por toma explícita o liberación administrativa.
El propietario y quien espera consultan novedades cada ocho segundos en pestañas
visibles; un observador sin solicitud usa el intervalo normal del heartbeat.
Las pestañas ocultas consultan como máximo cada 32 segundos con el temporizador
predeterminado, sin afectar a la propiedad persistente. Cien propietarios/solicitantes
visibles generan unas 12,5 peticiones por segundo; medir la carga real.
El historial registra adquisición, toma de control, liberación,
solicitudes, rechazos, avisos y escrituras externas. La fecha usa epoch del SQL;
el navegador representa horas en su zona y el panel usa la zona configurada en PHP.

## Actualizar

1. Anunciar una ventana de mantenimiento y finalizar las ediciones.
2. Guardar copia del plugin y de sus tablas/configuración.
3. Desactivar desde GLPI; cerrar pestañas para no enviar formularios anteriores.
4. Sustituir la carpeta completa, conservando nombre `editcontrol`.
   Asegurar que cambian las fechas de los archivos PHP para que OPcache detecte
   la actualización; si la validación de fechas está desactivada, seguir el
   procedimiento de invalidación de OPcache de la instalación.
5. Ejecutar Instalar/Actualizar en la gestión de plugins. El esquema v3 añade dos
   hashes de reconocimiento de guardados propios; la migración es idempotente y no borra datos.
6. Revisar controles antiguos y liberarlos si corresponde; esperar no los elimina.
   Recargar todos los tickets antes de reactivar tras la prueba de aceptación.
7. Validar en aviso y posteriormente en estricto.

La actualización v1 → v2 usa Migration::addField y executeMigration, comprueba los
campos resultantes y solo entonces guarda schema_version=2. Los hashes vinculan la
solicitud con su pestaña y sesión. Mantener este orden para futuras migraciones.

## Desactivar

Finalizar las ediciones, desactivar el plugin en GLPI y recargar las pestañas.
La desactivación deja de cargar los hooks y elimina la protección de servidor;
conserva tablas e historial. Los registros no pueden bloquear GLPI sin sus hooks.
Antes de reactivar, revisar los controles anteriores y liberarlos cuando corresponda.
Esperar no los caduca. Los formularios antiguos deben recargarse siempre.

## Desinstalar

Exportar historial si se necesita conservarlo. Pulsar Desinstalar en GLPI:
elimina las dos tablas, configuración del contexto `plugin:editcontrol` y derecho
específico. Finalmente retirar la carpeta. La eliminación del historial es
irreversible y debe ajustarse a la política de la organización. No desinstalar durante
peticiones activas. No se borran tickets ni elementos del núcleo.

## Diagnóstico y retención

- Comprobar hora de SQL, TTL, estado del plugin y modo actual.
- Verificar permisos del ticket, entidad y permiso específico del perfil.
- Revisar respuesta HTTP del endpoint propio; 409 significa conflicto, 403 sesión,
  permiso o CSRF; 503 indica servicio no disponible.
- Revisar logs de GLPI `editcontrol` y errores PHP. No copiar cookies o tokens a incidencias.
- Nunca editar tokens para «rescatar» una pestaña. Conservar texto, liberar desde
  panel si procede y recargar.
- No borrar filas para recuperar control: usar Tomar control o la liberación administrativa.
- Planificar retención del historial según necesidades locales. Para una limpieza,
  usar fecha límite y revisar/exportar antes; no hay tarea automática de eliminación.

## Revisión de compatibilidad

En cada actualización de GLPI comprobar orden y contratos de hooks CommonDBTM,
plantillas PRE/POST_ITEM_FORM, rutas oficiales API, sesión/CSRF, derechos y formularios
AJAX del timeline. Ejecutar la suite en SQL real y los doce escenarios de aceptación
en las dos versiones objetivo. Repetir con los plugins y personalizaciones de la instalación.

## Guardados AJAX desde 0.3.2

El control no se libera al guardar una tarea por AJAX. Tras el hook de éxito y bajo
el mutex del ticket, se registra un recibo con la versión recibida y la confirmada.
El sondeo solo actualiza la versión si conserva token/sesión de propietario y la
base sigue coincidiendo con el recibo. Escrituras externas lo invalidan. No se
reconocen como propias las escrituras ajenas permitidas por el modo aviso.
Una navegación de formulario completa libera al terminar la petición, no antes.
