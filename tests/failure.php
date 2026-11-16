<?php
/** Isolated bridge regression: no GLPI installation or SQL connection required. */
const ERROR = 1;
class Config {
    public static function getConfigurationValues($context) { return ['mode'=>getenv('EC_FAILURE_MODE') ?: 'warning', 'ttl'=>120]; }
}
class Session {
    public static array $messages = [];
    public static function getLoginUserID() { return 1; }
    public static function addMessageAfterRedirect(...$args) { self::$messages[] = $args; }
}
class Toolbox { public static function logInFile(...$args) {} }
class Ticket {
    public array $fields = ['id'=>1];
    public $input = ['id'=>1,'name'=>'Cambios del técnico'];
}
$DB = new class {
    public function doQuery($sql) { throw new RuntimeException('SQL unavailable (injected test failure)'); }
};
require dirname(__DIR__) . '/inc/service.class.php';
require dirname(__DIR__) . '/inc/bridge.class.php';
$ticket = new Ticket();
PluginEditcontrolBridge::guard($ticket);
$strict = Config::getConfigurationValues('plugin:editcontrol')['mode'] === 'strict';
if ($strict ? $ticket->input !== false : !is_array($ticket->input)) { throw new RuntimeException('Incorrect failure behaviour'); }
PluginEditcontrolBridge::capture(['item'=>$ticket]);
PluginEditcontrolBridge::created($ticket);
PluginEditcontrolBridge::shutdown();
echo $strict ? 'strict-safe' : 'warning-safe';
