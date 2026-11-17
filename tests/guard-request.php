<?php
ob_start();
$case=getenv('EC_GUARD_CASE') ?: 'owner';
session_id($case==='wrong-session'?'wrong-session':'owner-session');
/** Isolated CGI fixture: invokes the actual browser guard with fake GLPI classes.
 * It never boots GLPI, authenticates users, or opens a network listener.
 */
const READ = 1; const UPDATE = 2; const ERROR = 1;
class Config {
    public static string $policy = 'editors';
    public static function getConfigurationValues($context) { return ['mode'=>'strict','ttl'=>120,'takeover_policy'=>self::$policy]; }
}
class Session {
    public static int $user = 10;
    public static bool $admin = false;
    public static function getLoginUserID() { return self::$user; }
    public static function getCurrentInterface() { return 'central'; }
    public static function haveRight($name,$right) { return self::$admin; }
    public static function addMessageAfterRedirect(...$args) {}
}
class Toolbox { public static function logInFile(...$args) {} }
class GuardFixtureItem {
    public array $fields;
    public $input;
    public function __construct(array $fields) { $this->fields=$fields; $this->input=['id'=>$fields['id'] ?? 0,'name'=>'Nuevo texto']; }
    public function getFromDB($id) { return true; }
}
class Ticket extends GuardFixtureItem {
    public bool $editable = true;
    public function can($id,$right) { return $this->editable; }
    public function getFromDB($id) { global $DB; $this->fields=$DB->fetchAssoc($DB->doQuery('SELECT * FROM glpi_tickets WHERE id='.(int)$id)); return !!$this->fields; }
}
class Ticket_User extends GuardFixtureItem {}
class Group_Ticket extends GuardFixtureItem {}
class Document_Item extends GuardFixtureItem {}
class Ticket_Ticket extends GuardFixtureItem {}
class TicketTask extends GuardFixtureItem {}
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/inc/bridge.class.php';
if (PHP_SAPI==='cli') { throw new RuntimeException('Run this fixture using php-cgi'); }
$DB=new TestDB(); $DB->reset();
$s=new PluginEditcontrolService($DB,120);
$s->lock(1); $base=$s->snapshot(1); $token=$s->acquire(1,10,'owner-session',$base);
if ($case==='linked-busy') { $s->lock(2); $s->acquire(2,30,'other-session',$s->snapshot(2)); }
if ($case==='stale') { $s->invalidate(1); $DB->doQuery("UPDATE glpi_tickets SET name='Cambio externo' WHERE id=1"); }
$s->unlockAll();
$_SERVER['REQUEST_URI']=$case==='api'?'/apirest.php/Ticket/1':'/front/ticket.form.php';
$_SERVER['REQUEST_METHOD']='POST';
$_POST=['_editcontrol_token'=>$token,'_editcontrol_baseline'=>$base,'_editcontrol_ticket'=>'1'];
$item=new Ticket(['id'=>1]);
$blocked=in_array($case,['other','wrong-session','stale','actor-other','group-other','document-other','linked-busy','reparent'],true);
if (in_array($case,['other','actor-other','group-other','document-other','api'],true)) { Session::$user=20; $_POST['_editcontrol_token']=''; }
if (str_starts_with($case,'actor')) $item=new Ticket_User(['id'=>7,'tickets_id'=>1]);
if ($case==='group-other') $item=new Group_Ticket(['id'=>7,'tickets_id'=>1]);
if ($case==='document-other') $item=new Document_Item(['id'=>7,'itemtype'=>'Ticket','items_id'=>1]);
if ($case==='unrelated-document') $item=new Document_Item(['id'=>7,'itemtype'=>'Computer','items_id'=>1]);
if (str_starts_with($case,'linked')) $item=new Ticket_Ticket(['id'=>7,'tickets_id_1'=>2,'tickets_id_2'=>1]);
if ($case==='reparent') { $item=new Ticket_User(['id'=>7,'tickets_id'=>1]); $item->input['tickets_id']=2; }
if ($case==='ajax-owner') {
    $_SERVER['HTTP_X_EDITCONTROL_TOKEN']=$token; $_SERVER['HTTP_X_EDITCONTROL_BASELINE']=$base; $_SERVER['HTTP_X_EDITCONTROL_TICKET']='1'; $_POST=[];
}
if (str_starts_with($case,'takeover-')) {
    Session::$user=20;
    if ($case==='takeover-admins-only') Config::$policy='admins';
    if ($case==='takeover-readonly') $item->editable=false;
    $allowed=PluginEditcontrolBridge::canTakeover($item,1);
    $expected=$case==='takeover-editor';
} else {
    PluginEditcontrolBridge::guard($item);
    $allowed=is_array($item->input); $expected=!$blocked;
}
PluginEditcontrolBridge::shutdown();
if ($allowed!==$expected) { throw new RuntimeException('Unexpected guard result: '.$case); }
echo 'GUARD_RESULT='.json_encode(['case'=>$case,'allowed'=>$allowed,'ok'=>true]);
