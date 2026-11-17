# Estado actual

El responsable desactivó el plugin tras reportar dos regresiones en 0.3.0:
pérdida de control por inactividad y editor de tareas bloqueado en un ticket grande.
0.3.1 contiene las correcciones, pendientes de repetir la prueba en los tickets
afectados antes de reactivar. El estado desactivado se conserva durante la actualización.

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
