# Investigación y decisión técnica

Investigación inicial: 16 de noviembre de 2026.
La validación posterior se realizó en GLPI 11.0.4 / PHP 8.2.35 / MariaDB 11.8.3.
Inspeccionar código de otras versiones no acredita compatibilidad integrada.
Véanse README y PRODUCCION.md para el estado actual.

## ObjectLock

En el código local de 11 y la referencia oficial de 12 se observan el flujo de
autobloqueo/consulta, el cambio temporal a un perfil de lectura y un registro de
bloqueo por objeto. La interfaz nativa implementa solicitudes de liberación y
sondeo de disponibilidad. No se afirma que estas funciones estén ausentes.

En 11, `ObjectLock::lockObject` condiciona la opción de solicitar desbloqueo a
que exista correo predeterminado del propietario y estén habilitadas las
notificaciones por correo. El módulo JS gestiona salida de página y consulta del
estado. `cronUnlockObject` elimina bloqueos antiguos en función de un plazo en horas.

Estas dependencias explican riesgos de bloqueo olvidado si falla la petición de
salida y no actúa la tarea periódica. No se han reproducido los fallos históricos
del entorno inicial ni se atribuyen a un defecto concreto del núcleo. Para diagnosticarlos se
necesitarían versión desplegada, configuración, registros y una reproducción.

Fuentes primarias:

- [ObjectLock GLPI 11.0.7](https://github.com/glpi-project/glpi/blob/11.0.7/src/ObjectLock.php)
- [JavaScript ObjectLock 11.0.7](https://github.com/glpi-project/glpi/blob/11.0.7/js/modules/ObjectLock.js)
- [ObjectLock GLPI 12.0.2](https://github.com/glpi-project/glpi/blob/12.0.2/src/ObjectLock.php)
- [CommonDBTM 12.0.2](https://github.com/glpi-project/glpi/blob/12.0.2/src/CommonDBTM.php)
- [Hooks oficiales](https://glpi-developer-documentation.readthedocs.io/en/latest/plugins/hooks.html)
- [Requisitos e instalación de plugins](https://glpi-developer-documentation.readthedocs.io/en/latest/plugins/requirements.html)

## Comparación

| Alternativa | Ventajas | Costes y riesgos |
|---|---|---|
| Reutilizar ObjectLock | Interfaz existente, derechos y mantenimiento del núcleo | Comparte sus condiciones de liberación/notificación; extenderlo no garantiza fencing por pestaña ni control de versión |
| Plugin independiente | Control persistente y heartbeat propio, token por pestaña, revisión monotónica, mensajes internos | Más código y pruebas; requiere cubrir explícitamente cada ruta de escritura y revisar hooks al actualizar GLPI |

Se elige un plugin independiente, sin sobrescribir clases ni editar archivos del
núcleo, con alcance inicial limitado y declarado. Es una decisión para controlar
el comportamiento solicitado, no una afirmación de que el nativo sea inutilizable.

## Modelo de concurrencia

Una fila persistente por ticket (PRIMARY KEY), token aleatorio de 256 bits cuyo
hash se almacena, hash de sesión, propietario, generación, revisión, inicio,
heartbeat, vencimiento y última solicitud. Los tokens no se incluyen en historial.

Un mutex MySQL/MariaDB GET_LOCK por base de datos y ticket serializa adquisición,
renovación, toma de control, liberación y escrituras registradas. Las mutaciones
de GLPI retienen el mismo mutex hasta finalizar la petición, también en hooks
anidados. Recargan los campos leídos por CommonDBTM antes del hook, ya bajo mutex.
Esto evita una ventana entre validar el control y escribir en el núcleo. No se
impone una transacción global a GLPI ni a los otros plugins.

La adquisición compara la huella del formulario con la fila actual y la revisión.
Cada escritura registrada incrementa la revisión antes de modificar: incluso una
escritura fallida puede exigir recargar. Se prefiere un rechazo conservador a aceptar
un formulario obsoleto. El token nuevo hace inválido al anterior después de tomar
el control. El contador de generación queda disponible para diagnóstico.

Desde 0.3.1 el propietario no caduca por tiempo: la suspensión del navegador no
puede invalidar su control. El heartbeat registra presencia; la recuperación de un
control abandonado se realiza mediante toma explícita o liberación administrativa.
Las solicitudes sin respuesta sí caducan usando el reloj SQL. Los mutex SQL de
conexión se liberan al perder la conexión y son distintos del control persistente.

La inserción de la fila usa ON DUPLICATE KEY UPDATE sin cambios en vez de INSERT
IGNORE: el segundo generaba avisos por clave duplicada en el registro de GLPI.
Los recursos estáticos se ubican en `public/js`, como requiere el enrutador de
GLPI 11. Los scripts legacy declaran explícitamente los globales que utilizan
porque GLPI los incluye desde el ámbito de un controlador.

Los hooks PRE_ITEM_UPDATE/ADD/DELETE/PURGE cancelan la operación mediante
`input=false`, como documenta GLPI. Los hooks de éxito permiten intentar la
liberación al terminar la petición. La interfaz usa PRE/POST_ITEM_FORM y la
inclusión oficial de JavaScript. La compatibilidad declarada sigue pendiente de
ejecutar la integración completa contra ambas versiones.

Las API oficiales se reconocen por la ruta de entrada anclada, excluyendo la
cadena de consulta; parámetros, cabeceras o rutas de plugins no conceden exención.
Los permisos de consulta y edición de los endpoints propios se delegan en Ticket::can.
