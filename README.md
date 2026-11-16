# Control de edición para GLPI — 0.1.0

Primera versión experimental para impedir ediciones simultáneas de un ticket
desde la interfaz central de GLPI. Repositorio independiente, rama `develop`.
No modifica el núcleo de GLPI y no reutiliza sus tablas ObjectLock.

**Estado: preparado para pruebas; pendiente de validación integrada en GLPI.**
Compatibilidad objetivo: GLPI 11.x y 12.0.x, PHP 8.2 o posterior, MariaDB/MySQL
con un único servidor de escritura y conexión con afinidad de sesión.
No se declara compatibilidad con GLPI 10 ni con versiones futuras no probadas.
La versión exacta desplegada en producción no se pudo identificar desde su login.

## Uso

Abrir un ticket permite consultarlo sin adquirir el control. La barra muestra
quién edita, inicio y última actividad. Pulsar **Editar** adquiere el control
exclusivo de ese ticket para esa pestaña y sesión. No se comparte el token entre
pestañas, ni siquiera del mismo usuario.

**Terminar edición** libera el control. Tras un guardado protegido con éxito,
incluidos seguimiento, tarea o solución, el servidor intenta liberarlo al finalizar
la petición. Si este paso falla, la caducidad sigue siendo obligatoria. Una pestaña
cerrada o abandonada deja de renovar; el bloqueo vence sin depender de cron.
Una pestaña oculta no renueva. Una visible sin interacción deja de renovar tras
tres minutos. El control vence después del último heartbeat más el TTL configurado.

Un técnico puede **Solicitar control**. El propietario recibe un aviso en la barra
en el siguiente sondeo. Los observadores ven cuándo queda libre, sin depender
del correo ni de las notificaciones nativas. No se envían notificaciones de escritorio.
El permiso específico `plugin_editcontrol_admin` permite tomar el control y liberar
bloqueos administrativos dentro de los tickets accesibles al perfil y entidad.

Si el ticket cambia, caduca el control, se pierde la conexión o un administrador
toma el control, **conservar el texto y recargar antes de continuar**. No hay
mezcla automática de borradores ni recuperación de texto garantizada al redirigir GLPI.

## Instalación en pruebas

1. Respaldar archivos y base de datos del GLPI de pruebas.
2. Descomprimir el paquete: su carpeta superior debe llamarse `editcontrol`.
3. Copiarla a `GLPI/plugins/editcontrol` (o al directorio marketplace administrado
   por GLPI). No renombrarla. El proceso web necesita lectura, no escritura en el plugin.
4. Desactivar ObjectLock nativo para `Ticket`. El plugin rechaza esa coexistencia
   durante la comprobación de requisitos.
5. En Configuración → Complementos, instalar y activar **Control de edición**.
6. Abrir su configuración desde la lista de complementos. Por defecto funciona
   en **solo aviso**, con caducidad de 120 segundos.
7. Con un perfil autorizado para configurar GLPI, conceder el permiso específico
   de administración a los perfiles elegidos. Renovar sesión/perfil para aplicar derechos.
8. Completar primero las pruebas del modo aviso. Activar **bloqueo estricto** y
   recargar todas las pestañas para realizar la matriz de aceptación.

No se ha instalado ni desplegado este plugin en gise.huc.es ni gisepre.huc.es.

## Alcance de protección

| Operación | Tratamiento en esta versión |
|---|---|
| Actualizar, eliminar o purgar Ticket mediante CommonDBTM | Validación de control y versión en modo estricto |
| Añadir, actualizar, eliminar o purgar ITILFollowup de Ticket | Mismo control exclusivo del ticket |
| Añadir, actualizar, eliminar o purgar TicketTask | Mismo control exclusivo del ticket |
| Añadir, actualizar, eliminar o purgar ITILSolution de Ticket | Mismo control exclusivo del ticket |
| Resolver o cerrar mediante actualización de Ticket | Validación previa y liberación tras éxito |
| Creación de Ticket y elementos automáticos de esa creación | No necesita control previo; se reconoce el objeto nuevo en memoria |
| API oficial, CLI y autoservicio | No necesitan token; incrementan la revisión e invalidan formularios centrales anteriores |
| Actores, documentos, validaciones, relaciones, restauración, acciones de otros plugins y SQL directo | Sin cobertura completa; véase LIMITACIONES.md |
| Acciones masivas centrales sobre tipos protegidos | Rechazadas en estricto si no incluyen control válido por ticket |

El plugin no sustituye las comprobaciones de permisos de GLPI. Para adquirir el
control esta versión requiere permiso UPDATE sobre el ticket. Perfiles con derecho
a añadir seguimientos pero sin UPDATE necesitan una adaptación antes de usar estricto.
Otros formularios o plugins pueden necesitar transmitir token y versión: en estricto,
las escrituras protegidas sin estos datos se rechazan en el servidor.

El modo **solo aviso permite guardar sin control** y no evita conflictos.

## Pruebas y entregables

- `tests/run.php`: reglas de control, sesiones, versión obsoleta y ocho procesos concurrentes.
- `tests/README.md`: ejecución con modelo SQLite y con MariaDB/MySQL reales.
- `docs/RESULTADOS.txt`: salida real de pruebas locales.
- `docs/INVESTIGACION.md`: fuentes oficiales y decisión de arquitectura.
- `docs/ACEPTACION.md`: doce escenarios para dos navegadores en GLPI 11 y 12.
- `docs/MANTENIMIENTO.md`: actualización, desactivación, retirada y diagnóstico.
- `docs/LIMITACIONES.md`: riesgos, cobertura y condiciones previas de uso.

Ejecutar `python tools/package.py` para crear el paquete instalable en `dist/`.
El nombre de carpeta dentro del ZIP siempre es `editcontrol`.

No se ha publicado un repositorio remoto ni una versión en GitHub. El repositorio
local contiene el código completo y los commits de desarrollo.
