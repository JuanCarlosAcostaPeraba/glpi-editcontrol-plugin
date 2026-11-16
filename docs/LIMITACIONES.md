# Limitaciones y riesgos conocidos

La versión 0.1.0 es experimental y **no está aprobada para producción**.

1. No se ejecutó una instalación real en GLPI 11 ni 12, ni la matriz con dos
   navegadores. Las 28 comprobaciones locales usan un adaptador SQLite con
   GET_LOCK simulado mediante flock. No prueban el SQL MariaDB ni el funcionamiento
   real de sus mutex, CSRF del núcleo, hooks, derechos o selección de formularios.
2. La versión real de producción sigue pendiente. Los repositorios locales no
   prueban qué versión está desplegada. Si producción usa GLPI 10, esta entrega
   rechaza su instalación: será necesario un adaptador o rama con pruebas propias.
3. El alcance se limita a las clases y hooks declarados en README. Documentos,
   actores, validaciones, relaciones, restauración, escrituras SQL directas y plugins
   que evitan CommonDBTM no están protegidos completamente. Una integración que
   escriba SQL directamente puede modificar datos sin incrementar la revisión.
4. El fingerprint del ticket detecta cambios de su fila; la revisión detecta cambios
   en los tipos relacionados registrados. No hay versionado completo de todos los
   objetos relacionados ni una transacción sobre todo el renderizado de la página.
   Debe verificarse especialmente la carga AJAX del timeline y los formularios
   personalizados antes de habilitar modo estricto.
5. El mutex necesita un único servidor SQL escritor y conexión con afinidad de
   sesión. No compatible sin cambios con proxies que multiplexan conexiones,
   escrituras multi-master o escritores que no cargan el plugin. No se soportan
   conexiones SQL persistentes que retengan mutex al finalizar PHP.
6. Se retiene el mutex durante la petición de escritura; una operación larga puede
   hacer esperar hasta cinco segundos y rechazar conservadoramente otra operación,
   incluidas API/automatismos. Estas rutas están exentas del token, no de la
   serialización. No se garantiza ausencia total de rechazos de integraciones.
   Una petición que modifica varios tickets puede tener contención o timeout;
   no hay atomicidad entre tickets. Revisar acciones masivas y otros plugins.
7. Si otro plugin invalida una escritura después del hook de validación, puede
   haberse incrementado la revisión sin cambios finales. Se exigirá recarga.
8. La comunicación de la barra tarda hasta un intervalo TTL/3 (mínimo 15 s).
   Existe una última solicitud visible por ticket; otras solicitudes posteriores
   la sustituyen, aunque se registran en historial. No hay notificación push.
9. Al ocultar una pestaña se detiene la renovación. Tras tres minutos sin interacción
   en una pestaña visible, también se detiene. Debe ajustarse el TTL al trabajo real.
10. Los textos de formularios no se guardan como borradores. El rechazo del servidor
    usa el mecanismo de mensajes de GLPI: la recuperación de contenido depende del
    formulario. Los campos pueden seguir siendo editables en consulta, pero sus
    envíos protegidos se detienen en modo estricto. Probar editores enriquecidos,
    AJAX, llamadas fetch y formularios aportados por terceros.
11. El control requiere UPDATE del ticket; técnicos que solo pueden añadir tareas o
    seguimientos sin UPDATE necesitan una adaptación de permisos del endpoint.
    Las acciones masivas centrales protegidas se rechazan sin credenciales por ticket.
12. Cambiar modo/TTL requiere recargar las pestañas. El servidor aplica la configuración
    actual; una barra antigua podría mostrar el modo anterior. Desactivar el plugin
    quita su protección. Un periodo de mantenimiento es obligatorio para actualizar.
13. Desactivar conserva tablas y tokens hasta su vencimiento. Tras una reactivación
    inmediata podrían existir leases aún vigentes; esperar el TTL máximo anterior
    y recargar antes de reanudar. No se promete protección con el plugin inactivo.
14. Los paneles muestran como máximo 200 filas candidatas recientes, filtradas por
    accesibilidad después de obtenerlas. No son un inventario paginado completo.
    El historial crece sin retención automática; definir y ejecutar una política local.
15. Si falla el registro de un evento o la liberación final, la operación puede
    informar error o mantener el lease hasta el TTL. No se garantiza auditoría
    transaccional completa ante caída de SQL. Nunca se registran tokens en el historial.

No utilizar esta versión como garantía absoluta de ausencia de sobrescrituras:
esa garantía solo puede evaluarse para las rutas cubiertas tras validar la
integración real y las personalizaciones instaladas en el HUC.
