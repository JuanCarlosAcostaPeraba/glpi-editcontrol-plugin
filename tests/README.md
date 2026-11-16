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
