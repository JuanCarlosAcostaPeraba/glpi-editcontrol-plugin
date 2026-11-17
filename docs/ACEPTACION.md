# Matriz de aceptación integrada — pendiente de ejecución

Ejecutar en dos instalaciones de pruebas: versión exacta de producción y GLPI 12.
Usar cuentas ficticias A y B con UPDATE del ticket, administrador C con el permiso
específico y dos navegadores/sesiones distintas. Preparar un ticket accesible para
ambos y otro fuera de la entidad de B. Activar estricto y TTL 60 para pruebas.
No usar datos asistenciales. Guardar versión, plugins activos, registros y resultado.

| Caso | Pasos | Resultado exigido |
|---|---|---|
| 1. Dos técnicos abren | A abre el ticket y B lo abre después | A adquiere automáticamente; B ve su nombre y Solicitar control; un perfil solo lectura no adquiere |
| 2. Adquisición simultánea | A y B abren el mismo ticket a la vez; repetir 20 veces | Un ganador y una fila por ticket; el otro ve al ganador sin error técnico |
| 3. Guardado | A cambia título y guarda; B espera | Cambio de A persiste; control liberado; B recibe aviso; debe recargar si su versión cambió |
| 4. Cierre inesperado | A adquiere y termina navegador desde sistema | B puede tomar el control explícitamente; no se libera por tiempo |
| 5. Red | A adquiere, editar sin enviar, desconectar red más de TTL, reconectar | A conserva control si nadie lo toma; si B lo toma, el POST antiguo de A se rechaza |
| 6. Resolver/cerrar | A resuelve y luego probar cierre con nueva adquisición | Solución/estado guardados; liberación tras éxito; toma explícita como respaldo si falla |
| 7. Administración | C libera desde panel y toma control desde barra en otra repetición | A pierde control; no puede guardar ni liberar el control nuevo; historial identifica C, fecha y anterior |
| 8. Solicitud y cesión | B solicita, A ve el aviso y pulsa Ceder control | Sin salir ni recargar: A deja de tener control y B lo recibe; ambos borradores se conservan; un tercero no puede adelantarse |
| 9. Pestaña antigua | A conserva pestaña, B toma el control y guarda; reenviar POST de A | El cambio de B persiste y el POST antiguo falla incluso sin JavaScript |
| 10. Desactivar/actualizar | Finalizar edición, mantenimiento, desactivar, actualizar y revisar controles antes de reactivar | Sin protección mientras está desactivado; esquema/datos se conservan; controles antiguos revisados o liberados; no caducan por esperar |
| 11. Mismo usuario | Dos pestañas A, ambas intentan adquirir; repetir con pestaña duplicada | Solo una posee token; otra no renueva, guarda ni libera su control |
| 12. Integración externa | A edita; API oficial actualiza o añade tarea/seguimiento/solución | API funciona sin token; revisión invalida formulario de A; A no sobrescribe silenciosamente |

Repetir 3, 7, 9 y 12 para: Ticket, ITILFollowup, TicketTask e ITILSolution;
añadir/actualizar/eliminar donde aplique, formulario ordinario y acciones AJAX.
Comprobar creación de Ticket con plantillas que generan tareas/soluciones.
Repetir el modo aviso: los avisos deben verse y los envíos sin token deben permitirse.

Seguridad: B no puede consultar/adquirir/liberar tickets de otra entidad. Con política admins, un perfil
sin derecho específico no puede forzar control. Con política editors, cualquier
técnico con UPDATE sobre ese ticket puede tomarlo; un perfil solo lectura nunca.
Rechazar nonce/CSRF ausente, sesión caducada y token de otra pestaña. Probar que
añadir `?x=api.php`, cabeceras falsas o PATH_INFO no elude estricto.

Verificar además: sesión caducada con petición ya abierta, caída/reinicio de PHP y SQL,
timeouts del mutex, peticiones largas, varias ediciones API en una petición, editores
enriquecidos, caché de navegador al volver atrás, pestaña oculta y pestaña inactiva.
Medir consultas y tiempo de respuesta con carga similar a la real.

**Criterio de salida:** todos los casos relevantes pasan en ambas versiones, se
cierran los huecos de cobertura usados en el HUC y se revisan los logs. Hasta entonces
no proponer instalación en producción ni declarar ausencia total de sobrescrituras.

## Regresiones 0.3.1

- Dejar abierto e inactivo más de un día, ocultar y suspender el navegador: no perder
  control por tiempo. Comprobar rechazo del token anterior si otro técnico lo toma.
- Abrir un ticket grande y crear una tarea mientras la adquisición tarda. Repetir
  después de adquirir y con editores que se inician al hacer clic. Se puede escribir
  al tener el control; TinyMCE nativamente readonly permanece protegido.
- Desplazarse hasta el final: aparece solo el indicador compacto. Probar hover,
  clic, Tab/Enter/Escape, toque, solicitud, cesión, toma y vuelta arriba. Sin saltos
  de scroll ni pérdida de texto. Probar temas claro/oscuro y ancho de 360 px.
- Liberar administrativamente un ticket sin cambios: el propietario anterior puede
  pulsar Recuperar control aunque no tenga permiso para quitarlo a otro técnico.
