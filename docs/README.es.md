# Control de edición para GLPI — 0.3.2

Control de edición simultánea de tickets desde la interfaz central de GLPI.
No modifica el núcleo de GLPI y no reutiliza sus tablas ObjectLock.

**0.3.2: corrección del guardado de tareas AJAX y del fucsia en modo aviso.**
Se mantiene el modo solo aviso de producción. La 0.3.1 no resolvió todos los
casos de tareas bloqueadas. Se reproducen ahora guardado, apertura de una segunda
tarea y guardados consecutivos sin recargar, manteniendo la detección de cambios
externos. Entorno conocido: GLPI 11.0.4 / PHP 8.2.35 / MariaDB 11.8.3.

## Uso

Al abrir un ticket, la primera pestaña visible de un técnico con permiso UPDATE
adquiere automáticamente el control. La barra muestra su nombre y ofrece
**Solicitar control** a otro técnico. Con la política para todos los editores, también
puede **Tomar control** sin petición cualquier técnico con UPDATE en ese ticket.
Los perfiles de solo lectura nunca adquieren
el control. No se comparte el token entre pestañas, ni siquiera del mismo usuario.
Inicio y última actividad se pueden consultar al mantener el puntero sobre la barra.

En **solo aviso**, la presencia caduca después del tiempo configurado sin recibir
señales de la pestaña. En **bloqueo estricto**, el control persiste aunque se
suspenda el navegador. Al salir o guardar con navegación completa se intenta liberar.
El guardado AJAX conserva el control y actualiza la versión confirmada de esa
pestaña. Un cambio de otro técnico o integración sigue invalidando el formulario. Si el navegador
se cierra inesperadamente o falla la liberación, otro técnico autorizado puede
**Tomar control**. La caducidad de las solicitudes se aplica en los dos modos.
Las sesiones y los permisos de GLPI siguen aplicándose.

Cuando la barra superior queda fuera de pantalla, aparece un indicador compacto
abajo a la derecha. Muestra el propietario y permite desplegar el mismo aviso y
sus acciones por ratón, clic, teclado o toque. Una solicitud cambia el indicador
a «Solicitud de control»; se puede ceder sin volver al principio del ticket.

Un técnico puede **Solicitar control**. El propietario recibe un aviso en la barra
con **Ceder control** y puede entregarlo directamente a la pestaña solicitante,
sin salir del ticket ni recargar formularios. El compañero recibe el control en
su pestaña abierta. Ambos conservan sus borradores; ceder no guarda los cambios.
El propietario y quien espera consultan cambios cada ocho segundos cuando la
pestaña está visible. Los observadores ven cuándo queda libre, sin depender
del correo ni de las notificaciones nativas. No se envían notificaciones de escritorio.
El permiso específico `plugin_editcontrol_admin` conserva las liberaciones administrativas.
La política de toma de control se configura entre administradores y todos los
técnicos con permiso de edición. La segunda permite recuperar tickets de técnicos ausentes.
La toma registra actor, propietario anterior y fecha, invalida el token anterior
y exige que el propietario mostrado no haya cambiado antes del clic.

Cuando otro técnico tiene el control, la barra es fucsia en aviso y en estricto.
En solo aviso, una pestaña de la misma cuenta muestra un mensaje neutro.
Solo el modo estricto deja el formulario en solo lectura. No se borran textos al perderlo. TinyMCE registra la actividad al
escribir dentro del editor para mantener el control durante ediciones largas.

Si el ticket cambia, se pierde la conexión o un técnico autorizado
toma el control, **conservar el texto y recargar antes de continuar**. No hay
mezcla automática de borradores ni recuperación de texto garantizada al redirigir GLPI.

## Instalación

1. Respaldar archivos y base de datos del GLPI de pruebas.
2. Descomprimir el paquete: su carpeta superior debe llamarse `editcontrol`.
3. Copiarla a `GLPI/plugins/editcontrol` (o al directorio marketplace administrado
   por GLPI). No renombrarla. El proceso web necesita lectura, no escritura en el plugin.
4. Desactivar ObjectLock nativo para `Ticket`. El plugin rechaza esa coexistencia
   durante la comprobación de requisitos.
5. En Configuración → Complementos, instalar y activar **Control de edición**.
6. Abrir su configuración desde la lista de complementos. Por defecto funciona
   en **solo aviso**, con caducidad de presencia y solicitudes de 120 segundos.
7. Con un perfil autorizado para configurar GLPI, conceder el permiso específico
   de administración a los perfiles elegidos. Renovar sesión/perfil para aplicar derechos.
8. Completar primero las pruebas del modo aviso. Activar **bloqueo estricto** y
   recargar todas las pestañas para realizar la matriz de aceptación.

Véanse [validación](PRODUCCION.md) y [mantenimiento](MANTENIMIENTO.md).

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
| Técnicos, grupos, proveedores, activos, validaciones, satisfacción y costes | Protegidos por hooks de sus clases CommonDBTM |
| Asociaciones Document_Item con Ticket | Protegidas; no se bloquean documentos de otros tipos de objetos |
| Relaciones Ticket_Ticket | Se exige control del ticket de origen; otro ticket con control activo impide modificar la relación |
| Restauración de tipos protegidos | Validación de control en pre_item_restore |
| SQL directo, cascadas que evitan hooks y clases de terceros | Sin cobertura completa; véase LIMITACIONES.md |
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
- `RESULTADOS.txt`: salida real de pruebas locales.
- `tests/fixtures/bar.html` y `tests/ui-checks.js`: seis escenarios de navegador con respuestas simuladas.
- `tests/ui-handoff.js`: cesión entre dos pestañas, sin navegación ni pérdida de borradores.
- `tests/ui-takeover.js`: fucsia, contraste, solo lectura, toma de control y aviso al propietario anterior.
- `tests/run-guards.php`: guardas centrales reales bajo CGI, con clases GLPI simuladas.
- `INVESTIGACION.md`: fuentes oficiales y decisión de arquitectura.
- `ACEPTACION.md`: doce escenarios para dos navegadores en GLPI 11 y 12.
- `MANTENIMIENTO.md`: actualización, desactivación, retirada y diagnóstico.
- `LIMITACIONES.md`: riesgos, cobertura y condiciones previas de uso.

Ejecutar `python tools/package.py` para crear el paquete instalable en `dist/`.
El nombre de carpeta dentro del ZIP siempre es `editcontrol`.

Repositorio: https://github.com/JuanCarlosAcostaPeraba/glpi-editcontrol-plugin
Los cambios se desarrollan en `develop` y se integran en `main`.
