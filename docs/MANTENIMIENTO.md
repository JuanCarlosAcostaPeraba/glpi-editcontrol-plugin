# Operación y mantenimiento

## Configuración

Solo un usuario con `config UPDATE` modifica TTL, modo y concesiones de perfiles.
El derecho específico `plugin_editcontrol_admin` con READ autoriza tomar y liberar
el control. No concede acceso adicional a tickets: se comprueba Ticket::can y se
mantienen las restricciones de perfil/entidad. Separar ambos tipos de administradores.

TTL: 60–1800 segundos, por defecto 120. Heartbeat/sondeo: TTL/3, mínimo 15 segundos.
Cada pestaña abierta realiza una petición por intervalo; 100 pestañas con TTL 120
suponen aproximadamente 2,5 peticiones por segundo. Medir en preproducción.
El historial registra adquisición, toma de control, liberación, caducidad observada,
solicitudes, rechazos, avisos y escrituras externas. La fecha usa epoch del SQL;
el navegador representa horas en su zona y el panel usa la zona configurada en PHP.

## Actualizar

1. Anunciar una ventana de mantenimiento y finalizar las ediciones.
2. Guardar copia del plugin y de sus tablas/configuración.
3. Desactivar desde GLPI; cerrar pestañas para no enviar formularios anteriores.
4. Sustituir la carpeta completa, conservando nombre `editcontrol`.
5. Ejecutar Instalar/Actualizar en la gestión de plugins. El esquema v1 usa
   CREATE TABLE IF NOT EXISTS y no borra datos; repetirlo es idempotente.
6. Esperar como mínimo el TTL anterior antes de reactivar y recargar los tickets.
7. Validar en aviso y posteriormente en estricto.

La primera entrega solo contiene esquema v1. Al añadir columnas en futuras
versiones se deben incorporar migraciones ordenadas y probadas mediante el
mecanismo Migration de GLPI; incrementar `schema_version` tras éxito, no antes.
No se declara que CREATE IF NOT EXISTS migre cambios estructurales futuros.

## Desactivar

Finalizar las ediciones, desactivar el plugin en GLPI y recargar las pestañas.
La desactivación deja de cargar los hooks y elimina la protección de servidor;
conserva tablas e historial. Los registros no pueden bloquear GLPI sin sus hooks.
Antes de reactivar, esperar el TTL máximo previo para que los controles antiguos
estén vencidos. Los formularios antiguos deben recargarse siempre.

## Desinstalar

Exportar historial si se necesita conservarlo. Pulsar Desinstalar en GLPI:
elimina las dos tablas, configuración del contexto `plugin:editcontrol` y derecho
específico. Finalmente retirar la carpeta. La eliminación del historial es
irreversible y debe ajustarse a la política del hospital. No desinstalar durante
peticiones activas. No se borran tickets ni elementos del núcleo.

## Diagnóstico y retención

- Comprobar hora de SQL, TTL, estado del plugin y modo actual.
- Verificar permisos del ticket, entidad y permiso específico del perfil.
- Revisar respuesta HTTP del endpoint propio; 409 significa conflicto, 403 sesión,
  permiso o CSRF; 503 indica servicio no disponible.
- Revisar logs de GLPI `editcontrol` y errores PHP. No copiar cookies o tokens a incidencias.
- Nunca editar tokens para «rescatar» una pestaña. Conservar texto, liberar desde
  panel si procede y recargar.
- No hace falta purgar filas de leases vencidas para recuperar el control.
- Planificar retención del historial según necesidades locales. Para una limpieza,
  usar fecha límite y revisar/exportar antes; no hay tarea automática de eliminación.

## Revisión de compatibilidad

En cada actualización de GLPI comprobar orden y contratos de hooks CommonDBTM,
plantillas PRE/POST_ITEM_FORM, rutas oficiales API, sesión/CSRF, derechos y formularios
AJAX del timeline. Ejecutar la suite en SQL real y los doce escenarios de aceptación
en las dos versiones objetivo. Repetir con los plugins y personalizaciones del HUC.
