<?php
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/inc/bridge.class.php';
check(PluginEditcontrolBridge::isApiPath('/apirest.php/Ticket/1',''), 'API REST reconocida por ruta exacta');
check(PluginEditcontrolBridge::isApiPath('/glpi/api.php/v2/Ticket/1','/glpi'), 'API con GLPI en subdirectorio');
check(!PluginEditcontrolBridge::isApiPath('/front/ticket.form.php?x=api.php',''), 'Parámetro api.php no permite eludir el bloqueo');
check(!PluginEditcontrolBridge::isApiPath('/front/ticket.form.php/api.php',''), 'PATH_INFO falso no permite eludir el bloqueo');
check(!PluginEditcontrolBridge::isApiPath('/plugins/other/api.php',''), 'Endpoint de otro plugin no obtiene exención de API');
foreach (['warning','strict'] as $mode) {
    $env = getenv(); $env['EC_FAILURE_MODE'] = $mode;
    $pipes=[];
    $p=proc_open([PHP_BINARY,__DIR__.'/failure.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($p);
    check($exit===0 && $out===$mode.'-safe', 'Fallo SQL: '.($mode==='warning' ? 'aviso permite guardar sin romper formulario' : 'estricto rechaza guardado sin romper formulario').' '.$err);
}
$db = new TestDB(); $db->reset();
echo 'Backend: ' . ($db->mysql ? 'MariaDB/MySQL real' : 'SQLite behavioural model (GET_LOCK simulated by flock)') . PHP_EOL;
$s = new PluginEditcontrolService($db,60);
$s->lock(1); $base = $s->snapshot(1);
$a = $s->acquire(1,10,'session-a',$base);
check($s->owns($s->row(1),10,$a,'session-a'), 'Adquisición explícita y propiedad');
conflict(fn()=>$s->acquire(1,20,'session-b',$base), 'Segundo técnico rechazado');
conflict(fn()=>$s->acquire(1,10,'session-a',$base), 'Otra pestaña del mismo técnico rechazada');
conflict(fn()=>$s->heartbeat(1,10,$a,'expired-session'), 'Sesión diferente no renueva');
$s->heartbeat(1,10,$a,'session-a');
check((int)$s->row(1)['expires'] > time(), 'Heartbeat mantiene caducidad limitada');
$s->request(1,20); $s->request(1,20);
check((int)$s->row(1)['requester'] === 20, 'Solicitud visible para el propietario');
check((int)$db->pdo->query("SELECT COUNT(*) FROM glpi_plugin_editcontrol_events WHERE event='requested'")->fetchColumn() === 1, 'Solicitudes repetidas limitadas');
$b = $s->acquire(1,20,'session-b',$base,true);
conflict(fn()=>$s->validateWrite(1,10,$a,'session-a',$base,true), 'Propietario anterior no puede guardar tras toma de control');
conflict(fn()=>$s->release(1,10,$a,'session-a'), 'Liberación tardía no libera al nuevo propietario');
check($s->owns($s->row(1),20,$b,'session-b'), 'Nuevo propietario conserva el control');
$s->clear(1,99,'admin_release');
check((int)$s->row(1)['users_id'] === 0, 'Liberación administrativa');
conflict(fn()=>$s->heartbeat(1,20,$b,'session-b'), 'Heartbeat tras liberación administrativa rechazado');
$a = $s->acquire(1,10,'session-a',$base);
$db->pdo->exec('UPDATE glpi_plugin_editcontrol_leases SET expires=UNIX_TIMESTAMP()-1 WHERE tickets_id=1');
conflict(fn()=>$s->heartbeat(1,10,$a,'session-a'), 'Heartbeat no resucita un control caducado');
check((int)$s->row(1)['users_id'] === 0, 'Caducidad libera sin cron ni cierre del navegador');
$b = $s->acquire(1,20,'session-b',$base);
conflict(fn()=>$s->validateWrite(1,10,$a,'session-a',$base,true), 'Token caducado no guarda tras readquisición');
$s->release(1,20,$b,'session-b');
$a = $s->acquire(1,10,'session-a',$base);
$s->invalidate(1); $db->pdo->exec("UPDATE glpi_tickets SET name='Actualización API' WHERE id=1");
conflict(fn()=>$s->validateWrite(1,10,$a,'session-a',$base,true), 'Cambio externo invalida el formulario anterior');
check($db->pdo->query('SELECT name FROM glpi_tickets WHERE id=1')->fetchColumn() === 'Actualización API', 'Cambio externo permanece intacto');
$s->clear(1,0,'test_reset');
conflict(fn()=>$s->acquire(1,10,'session-a',$base), 'No se adquiere control sobre formulario obsoleto');
$base = $s->snapshot(1); $a = $s->acquire(1,10,'session-a',$base);
$s->validateWrite(1,10,$a,'session-a',$base,true);
$db->pdo->exec('UPDATE glpi_tickets SET status=6 WHERE id=1');
$s->release(1,10,$a,'session-a');
check((int)$s->row(1)['users_id'] === 0, 'Guardado y cierre simulados liberan el control');
$revision = (int)$s->row(1)['revision'];
$s->validateWrite(1,30,'','session-c','',false);
check((int)$s->row(1)['revision'] === $revision+1, 'Modo aviso permite avanzar sin control');
check((int)$db->pdo->query("SELECT COUNT(*) FROM glpi_plugin_editcontrol_events WHERE event='takeover' AND users_id=20 AND previous_users_id=10")->fetchColumn() === 1, 'Auditoría identifica toma de control y propietario anterior');
$s->unlockAll();
// Actual independent PHP processes race over one row. MySQL uses real GET_LOCK.
$cmd = [PHP_BINARY, '-d', 'extension_dir=' . ini_get('extension_dir')];
if (!$db->mysql) { array_push($cmd,'-d','extension=pdo_sqlite'); }
else { array_push($cmd,'-d','extension=pdo_mysql'); }
array_push($cmd, __DIR__ . '/worker.php');
$processes = [];
for ($i=0;$i<8;$i++) {
    $pipes=[]; $p=proc_open(array_merge($cmd,[(string)(100+$i)]), [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]); $processes[]=[$p,$pipes];
}
$wins = 0;
foreach ($processes as [$p,$pipes]) {
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($p);
    if ($exit!==0) { throw new RuntimeException($err . $out); }
    $wins += trim($out) === 'won' ? 1 : 0;
}
check($wins === 1, '8 procesos concurrentes: exactamente un ganador');
check((int)$db->pdo->query('SELECT COUNT(*) FROM glpi_plugin_editcontrol_leases WHERE tickets_id=2')->fetchColumn() === 1, 'Restricción única conserva una sola fila');
echo "Suite completada. La validación de GLPI y navegador se ejecuta por separado.\n";
