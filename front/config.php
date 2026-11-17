<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
include '../../../inc/includes.php';
global $DB;
require_once dirname(__DIR__) . '/inc/service.class.php';
require_once dirname(__DIR__) . '/inc/bridge.class.php';
Session::checkLoginUser();
$configure = Session::haveRight('config', UPDATE);
if (!$configure && !PluginEditcontrolBridge::admin()) { Html::displayRightError(); exit; }
if (!Plugin::isPluginActive('editcontrol')) { Html::displayRightError(); exit; }
$escape = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // GLPI middleware checks its CSRF token; this nonce additionally protects direct entrypoints.
    if (!is_string($_POST['nonce'] ?? null) || !hash_equals(PluginEditcontrolBridge::nonce(), $_POST['nonce'])) { Html::displayRightError(); exit; }
    if ($configure && isset($_POST['save'])) {
        $ttl = filter_var($_POST['ttl'] ?? null, FILTER_VALIDATE_INT);
        $takeoverPolicy = $_POST['takeover_policy'] ?? PluginEditcontrolBridge::config()['takeover_policy'];
        if ($ttl < 60 || $ttl > 1800 || !in_array($_POST['mode'] ?? '', ['strict','warning'], true) || !in_array($takeoverPolicy, ['editors','admins'], true)) {
            Session::addMessageAfterRedirect('Caducidad: entre 60 y 1800 segundos. Modo no válido.', false, ERROR);
        } else {
            Config::setConfigurationValues('plugin:editcontrol', ['ttl'=>$ttl, 'mode'=>$_POST['mode'], 'takeover_policy'=>$takeoverPolicy]);
            Session::addMessageAfterRedirect('Configuración guardada. Recargue las pestañas abiertas.', false, INFO);
        }
    } elseif ($configure && isset($_POST['rights'])) {
        $profile = new Profile();
        $profileId = (int)($_POST['profile'] ?? 0);
        if ($profileId > 0 && $profile->getFromDB($profileId)) {
            ProfileRight::updateProfileRights($profileId, ['plugin_editcontrol_admin'=>isset($_POST['grant']) ? READ : 0]);
            Session::addMessageAfterRedirect('Permiso actualizado. El usuario debe renovar su sesión/perfil.', false, INFO);
        }
    } elseif (PluginEditcontrolBridge::admin() && isset($_POST['unlock'])) {
        $id = (int)$_POST['unlock'];
        $ticket = new Ticket();
        if ($id > 0 && $ticket->can($id, UPDATE)) {
            PluginEditcontrolBridge::service()->clear($id, (int)Session::getLoginUserID(), 'admin_release');
        }
    }
    Html::redirect(Plugin::getWebDir('editcontrol') . '/front/config.php');
}
Html::header('Control de edición', $_SERVER['PHP_SELF'], 'config', 'plugins');
$config = PluginEditcontrolBridge::config();
$hidden = static function () use ($escape) {
    echo '<input type="hidden" name="_glpi_csrf_token" value="' . $escape(Session::getNewCSRFToken()) . '">';
    echo '<input type="hidden" name="nonce" value="' . $escape(PluginEditcontrolBridge::nonce()) . '">';
};
echo '<main class="container my-4"><h1>Control de edición</h1>';
if ($configure) {
    echo '<form method="post" class="card card-body mb-4">'; $hidden();
    echo '<label>Caducidad del control (segundos)<input class="form-control" name="ttl" type="number" min="60" max="1800" value="' . (int)$config['ttl'] . '"></label>';
    echo '<label>Modo<select class="form-select" name="mode">';
    foreach (['warning'=>'Solo aviso', 'strict'=>'Bloqueo estricto'] as $v=>$label) {
        echo '<option value="' . $v . '"' . ($config['mode'] === $v ? ' selected' : '') . '>' . $label . '</option>';
    }
    echo '</select></label><label>Tomar control sin solicitud<select class="form-select" name="takeover_policy">';
    foreach (['admins'=>'Solo administradores autorizados','editors'=>'Todos los técnicos con permiso de edición del ticket'] as $value=>$label) {
        echo '<option value="'.$value.'"'.($config['takeover_policy']===$value?' selected':'').'>'.$label.'</option>';
    }
    echo '</select></label><p>El modo aviso permite guardar sin control y no evita conflictos. Cambiar el modo exige recargar las pestañas.</p><button class="btn btn-primary" name="save" value="1">Guardar</button></form>';
    echo '<form method="post" class="card card-body mb-4">'; $hidden();
    echo '<h2>Permiso específico de administración</h2><label>Perfil<select class="form-select" name="profile">';
    foreach ($DB->request(['FROM'=>'glpi_profiles', 'ORDER'=>'name']) as $p) {
        echo '<option value="' . (int)$p['id'] . '">' . $escape($p['name']) . '</option>';
    }
    echo '</select></label><label><input type="checkbox" name="grant"> Permitir toma de control y liberación administrativa</label><button class="btn btn-primary" name="rights" value="1">Actualizar permiso</button></form>';
}
echo '<h2>Bloqueos activos</h2><p>Solo se muestran tickets accesibles en el perfil y las entidades actuales. Los bloqueos caducados nunca impiden adquirir el control.</p><table class="table"><thead><tr><th>Ticket</th><th>Técnico</th><th>Inicio</th><th>Actividad</th><th>Caduca</th><th>Acción</th></tr></thead><tbody>';
foreach ($DB->request(['FROM'=>'glpi_plugin_editcontrol_leases', 'WHERE'=>['expires'=>['>',time()], 'users_id'=>['>',0]], 'ORDER'=>'expires', 'LIMIT'=>200]) as $r) {
    $id = (int)$r['tickets_id']; $t = new Ticket();
    if (!$t->can($id, READ)) { continue; }
    $u = new User(); $u->getFromDB((int)$r['users_id']);
    echo '<tr><td>' . $id . '</td><td>' . $escape($u->getName()) . '</td>';
    foreach (['started','heartbeat','expires'] as $field) { echo '<td>' . $escape(date('Y-m-d H:i:s', (int)$r[$field])) . '</td>'; }
    echo '<td>';
    if (PluginEditcontrolBridge::admin() && $t->can($id, UPDATE)) {
        echo '<form method="post">'; $hidden(); echo '<button class="btn btn-warning" name="unlock" value="' . $id . '">Liberar</button></form>';
    }
    echo '</td></tr>';
}
echo '</tbody></table><h2>Historial reciente</h2><table class="table"><thead><tr><th>Ticket</th><th>Actor</th><th>Anterior</th><th>Evento</th><th>Fecha</th></tr></thead><tbody>';
foreach ($DB->request(['FROM'=>'glpi_plugin_editcontrol_events', 'ORDER'=>'id DESC', 'LIMIT'=>200]) as $r) {
    $t = new Ticket(); if (!$t->can((int)$r['tickets_id'], READ)) { continue; }
    echo '<tr>';
    foreach (['tickets_id','users_id','previous_users_id','event'] as $field) { echo '<td>' . $escape($r[$field]) . '</td>'; }
    echo '<td>' . $escape(date('Y-m-d H:i:s', (int)$r['occurred'])) . '</td></tr>';
}
echo '</tbody></table></main>';
Html::footer();
