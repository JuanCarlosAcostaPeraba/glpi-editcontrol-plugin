<?php
/** Invoke the real guard under CGI, so its central-interface branch is exercised. */
$cgi=dirname(PHP_BINARY).DIRECTORY_SEPARATOR.(PHP_OS_FAMILY==='Windows'?'php-cgi.exe':'php-cgi');
if (!is_file($cgi)) { throw new RuntimeException('php-cgi is required for browser guard tests'); }
$cases=['owner','other','wrong-session','stale','ajax-owner','actor-owner','actor-other','group-other',
    'document-other','unrelated-document','linked-busy','linked-free','reparent','api',
    'takeover-editor','takeover-admins-only','takeover-readonly','ajax-task-save','ajax-fetch-task-save','normal-task-save'];
foreach ($cases as $case) {
    $env=getenv(); $env['REDIRECT_STATUS']='1'; $env['EC_GUARD_CASE']=$case; $env['EC_TEST_DSN']='';
    $pipes=[];
    $p=proc_open([$cgi,'-d','session.use_cookies=0','-d','extension_dir='.ini_get('extension_dir'),'-d','extension=pdo_sqlite','-f',__DIR__.'/guard-request.php'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($p);
    $position=strpos($out,'GUARD_RESULT=');
    $result=$position===false?null:json_decode(substr($out,$position+13),true);
    if ($exit!==0 || !($result['ok']??false)) { throw new RuntimeException($case.': '.$out.$err); }
    echo 'PASS CGI: '.$case.PHP_EOL;
}
