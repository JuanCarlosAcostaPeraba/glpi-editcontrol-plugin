<?php
require __DIR__ . '/bootstrap.php';
$db = new TestDB(); $s = new PluginEditcontrolService($db,60);
try {
    $s->lock(2); $base=$s->snapshot(2);
    $s->acquire(2,(int)$argv[1],'worker-'.$argv[1],$base);
    echo 'won';
} catch (PluginEditcontrolConflict $e) { echo 'lost'; }
finally { $s->unlockAll(); }
