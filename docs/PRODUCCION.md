# Instalación autorizada del 16 de noviembre de 2026

El usuario indicó expresamente probar en producción y, después, concretó el alcance:
instalar, comprobar que GLPI sigue funcionando y realizar él mismo la prueba con
un compañero. No se crearon tickets de prueba ni se activó el bloqueo estricto.

Estado final:

- GLPI 11.0.4; PHP 8.2.35; MariaDB 11.8.3.
- Plugin 0.1.2 instalado y activado por la consola oficial, ejecutada como usuario apache.
- Modo `warning` (solo aviso), TTL 120 segundos.
- ObjectLock nativo ya estaba desactivado; no se cambió esa configuración.
- Código del plugin separado del núcleo, sin modificar archivos centrales.
- Copia previa de metadatos y de la versión inicial instalada fuera del directorio web.
- No se reiniciaron Apache, PHP-FPM ni la base de datos.

Comprobaciones realizadas:

- Sintaxis con el PHP real del servidor.
- Creación de las tablas del plugin y carga de configuración.
- GET_LOCK y RELEASE_LOCK reales en MariaDB, sin modificar tickets.
- Plugin listado como activo y versión 0.1.2.
- Página de acceso y JavaScript devueltos con HTTP 200.
- Revisión del tramo nuevo del registro después de la corrección, sin nuevos errores del plugin.
- 30 comprobaciones locales, incluyendo ocho procesos concurrentes sobre el modelo SQLite.

Durante la primera comprobación se detectaron y corrigieron tres problemas:
ubicación del recurso JavaScript, ámbito de la variable global DB en la pantalla
de configuración y avisos por INSERT IGNORE. Se desactivó temporalmente el plugin
durante la sustitución. El modo aviso también se reforzó para no impedir guardados
si falla la comprobación del plugin, y para no romper el renderizado del formulario.

## Prueba manual pendiente

1. Recargar completamente la página del ticket (Ctrl+F5 si es necesario).
2. Abrir un ticket y pulsar **Editar** en la barra de control. Abrirlo por sí solo
   no adquiere el control, para permitir la consulta sin bloqueo.
3. El compañero abre el mismo ticket y comprueba el nombre del propietario y el aviso.
4. Terminar edición y comprobar el aviso de disponibilidad en la otra pestaña.

En modo aviso los guardados siguen permitidos. Esta prueba no valida todavía
el rechazo de escrituras ni certifica todos los casos de concurrencia.

## Desactivación inmediata

Desde Configuración → Complementos, desactivar **Control de edición**, o ejecutar
por SSH como administrador del servidor:

```sh
cd /var/www/html/glpi
runuser -u apache -- php bin/console plugin:deactivate editcontrol --no-interaction
```

La desactivación conserva las tablas y el historial y deja de cargar los hooks.
No hace falta desinstalar ni editar el núcleo para recuperar el comportamiento anterior.
