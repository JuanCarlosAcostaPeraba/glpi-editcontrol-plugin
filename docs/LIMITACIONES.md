# Limitaciones y riesgos conocidos

La versión 0.3.0 fue validada por dos técnicos en producción en GLPI 11.0.4.
La matriz completa de operaciones, perfiles y personalizaciones sigue pendiente.

1. Se verificaron esquema y GET_LOCK en MariaDB 11.8.3 y escenarios aislados con
   tablas temporales. Los 52 controles de servicio usan SQLite y mutex simulado;
   los 17 escenarios CGI usan clases simuladas y los 12 escenarios de navegador
   usan respuestas ficticias. No sustituyen la matriz integrada en GLPI.
2. La producción comprobada por SSH es GLPI 11.0.4 con PHP 8.2.35. La compatibilidad
   completa con GLPI 12 sigue pendiente. Esta entrega rechaza GLPI 10.
3. El alcance se limita a las clases y hooks declarados en README. Las asociaciones
   de actores, activos y documentos, validaciones, satisfacción, costes, relaciones
   y restauración ahora están registradas. Escrituras SQL directas, cascadas y plugins
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
8. La solicitud y recepción de la cesión tardan hasta ocho segundos en pestañas
   visibles de propietario y solicitante; pestañas ocultas pueden sufrir throttling
   adicional del navegador. Los observadores sin solicitud usan TTL/3 (mínimo 15 s).
   Existe una última solicitud visible por ticket; otras solicitudes posteriores
   la sustituyen, aunque se registran en historial. No hay notificación push.
   El propietario solo puede ceder a la solicitud que está viendo: una sustitución
   concurrente obliga a comprobar el nuevo aviso. La solicitud caduca tras TTL sin
   renovarse; una pestaña cerrada no puede retener una cesión permanentemente.
9. Al ocultar una pestaña se detiene la renovación. Tras tres minutos sin interacción
   en una pestaña visible, también se detiene. Debe ajustarse el TTL al trabajo real.
10. Los textos de formularios no se guardan como borradores. El rechazo del servidor
    usa el mecanismo de mensajes de GLPI: la recuperación de contenido depende del
    formulario. Los controles y TinyMCE pasan a solo lectura sin perder su valor;
    el servidor rechaza envíos sin control aunque se altere el navegador. Probar editores enriquecidos,
    AJAX, llamadas fetch y formularios aportados por terceros.
11. El control requiere UPDATE del ticket; técnicos que solo pueden añadir tareas o
    seguimientos sin UPDATE necesitan una adaptación de permisos del endpoint.
    Las acciones masivas centrales protegidas se rechazan sin credenciales por ticket.
    Formularios de planificación u otros plugins sin contexto de pestaña pueden
    necesitar un adaptador; un rechazo exige abrir el ticket y adquirir control.
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
    informar error o mantener el lease hasta el TTL. El modo aviso deja continuar
    los guardados cuando falla la comprobación; el modo estricto los rechaza.
    No se garantiza auditoría
    transaccional completa ante caída de SQL. Nunca se registran tokens en el historial.

No utilizar esta versión como garantía absoluta de ausencia de sobrescrituras:
esa garantía solo puede evaluarse para las rutas cubiertas tras validar la
integración real y las personalizaciones instaladas en el HUC.

Las integraciones por API oficial, CLI y autoservicio conservan su exención del token,
como se pidió en el diseño inicial. Sus cambios invalidan las versiones centrales.
Una toma de control no reescribe campos ni actualiza un formulario obsoleto: si hay
cambios en base de datos, se exige conservar el texto y cargar los datos actuales.
