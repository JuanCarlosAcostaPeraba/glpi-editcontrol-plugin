# Pruebas reproducibles

Requiere PHP 8.2+, PDO y pdo_sqlite (modelo) o pdo_mysql (motor real).

```powershell
php -d extension=pdo_sqlite tests/run.php
```

El modelo SQLite adapta INSERT IGNORE y sustituye los mutex MySQL por flock.
Valida reglas de negocio y ocho procesos independientes; **no valida** el SQL de
instalación, GET_LOCK real, los hooks de GLPI, permisos ni la interfaz de navegador.

Para ejecutar exactamente la misma suite sobre un servidor MariaDB/MySQL local,
crear una base **desechable y vacía** llamada `editcontrol_test` y un usuario
exclusivo con permisos sobre ella. La suite borra sus tres tablas de pruebas.
No usar datos de GLPI reales.

```powershell
$env:EC_TEST_DSN='mysql:host=127.0.0.1;port=33317;dbname=editcontrol_test;charset=utf8mb4'
$env:EC_TEST_USER='editcontrol_test'
$env:EC_TEST_PASSWORD='contraseña-local-de-pruebas'
php -d extension=pdo_mysql tests/run.php
```

En Linux se usan las mismas variables de entorno y comando PHP.
Los resultados deben guardarse junto con versión de PHP, motor SQL y versión GLPI.
La matriz de aceptación con dos navegadores está en `docs/ACEPTACION.md`.

## Barra simplificada y transporte AJAX

`tests/fixtures/bar.html` carga el JavaScript real con respuestas ficticias y sin
sesiones de GLPI. `tests/ui-checks.js` comprueba adquisición automática, solicitud,
permisos de lectura, carrera de apertura, recuperación tras caída temporal, cierre
con keepalive y la cabecera CSRF exigida por GLPI 11. No sustituye la prueba integrada.

Para reproducir con Playwright CLI, iniciar `python tests/preview.py` en una terminal
(solo escucha en 127.0.0.1 y se detiene en cinco minutos). En otra terminal:

```powershell
npx --yes --package @playwright/cli playwright-cli -s=editcontrol-ui open http://127.0.0.1:18769/tests/fixtures/bar.html --browser chrome
$checks = (Get-Content -Raw tests/ui-checks.js) -replace '\r?\n',' '
npx --yes --package @playwright/cli playwright-cli -s=editcontrol-ui run-code $checks
npx --yes --package @playwright/cli playwright-cli -s=editcontrol-ui close
```

La comprobación visual se realizó a anchura de escritorio y a 360 px, con la
apariencia de prueba; las clases de la barra real heredan el tema de GLPI.

`tests/ui-handoff.js` abre dos pestañas con un backend simulado compartido. A recibe
el aviso y pulsa Ceder control; B reconoce su credencial pre-registrada en su siguiente
consulta. Verifica ausencia de navegación, borradores intactos, token nuevo en el
formulario de B y permiso de envío retirado a A en modo estricto.
Se ejecuta con `run-code` igual que ui-checks.js. La suite PHP comprueba también
cesión a una solicitud sustituida, una pestaña ajena y solicitudes abandonadas.
