# Actualización del 18 de noviembre de 2026

El responsable reactivó 0.3.1 y pasó a modo solo aviso por nuevos bloqueos del editor.
0.3.2 corrige el flujo de guardado AJAX y conserva ese modo. No se pasa a estricto.
El fucsia se aplica también en aviso; la escritura sigue permitida por ese modo.
Se añade esquema 3 para reconocer guardados propios sin aceptar cambios externos.
Las pruebas de navegador usan tareas ficticias; no se guardan tareas de prueba reales.

## Registro anterior (0.3.1)

# Estado actual

El responsable desactivó el plugin tras reportar dos regresiones en 0.3.0:
pérdida de control por inactividad y editor de tareas bloqueado en un ticket grande.
0.3.1 contiene las correcciones, pendientes de repetir la prueba en los tickets
afectados antes de reactivar. El estado desactivado se conserva durante la actualización.

Estado comprobado de 0.3.1: instalada, no activada (state=4), esquema 2,
modo strict, toma editors y timeout de solicitudes 120 segundos. No se activó el
plugin, no se reiniciaron servicios ni se modificaron tickets reales. Se conserva
copia privada del código anterior y de la configuración/tablas propias del plugin.

Prueba adicional en MariaDB real, solo con tablas temporales en conexión independiente:
propiedad conservada tras simular un día sin heartbeat, heartbeat sin reintroducir
caducidad, cesión, toma explícita y rechazo de token/generación anteriores: OK.

## Registro de la prueba inicial de 0.3.0

El 17 de noviembre de 2026 el responsable confirmó que 0.3.0 funciona correctamente
tras probarla con un compañero en producción. La confirmación cubre su flujo con
dos técnicos; no certifica toda la matriz de operaciones ni otras personalizaciones.

Entorno: GLPI 11.0.4, PHP 8.2.35, MariaDB 11.8.3. Plugin activo, esquema 2,
modo estricto, toma de control para editores, TTL 120. ObjectLock de Ticket desactivado.

Comprobaciones técnicas previas:

- Sintaxis PHP del servidor, plugin activo y HTTP 200 para GLPI, JS y CSS.
- 52 comprobaciones de servicio, 17 escenarios CGI y 12 escenarios de navegador.
  CGI usa clases GLPI simuladas y el navegador usa respuestas ficticias.
- MariaDB con tablas temporales en conexión independiente: cesión, toma directa,
  escritura del propietario y rechazo de token/generación anteriores.
- Código separado del núcleo, sin reiniciar servicios. Las pruebas automatizadas
  no crearon ni modificaron tickets reales.

Barra fucsia #c026a6 / blanco, contraste 5,16:1; revisión visual a 360 px.
Los borradores permanecen en su pestaña cuando cambia el propietario.

Consultar ACEPTACION.md para ampliar la validación y MANTENIMIENTO.md para retirada.
