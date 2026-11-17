<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
include '../../../inc/includes.php';
require_once dirname(__DIR__) . '/inc/service.class.php';
require_once dirname(__DIR__) . '/inc/bridge.class.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
Session::checkLoginUser();
$csrf = $_SERVER['HTTP_X_GLPI_CSRF_TOKEN'] ?? Session::getNewCSRFToken();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Plugin::isPluginActive('editcontrol')) {
        throw new RuntimeException('Operación no permitida', 403);
    }
    if (!is_string($_POST['nonce'] ?? null) || !hash_equals(PluginEditcontrolBridge::nonce(), $_POST['nonce'])) {
        throw new RuntimeException('Sesión caducada. Recargue.', 403);
    }
    $id = filter_var($_POST['ticket'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) { throw new RuntimeException('Ticket no válido', 400); }
    $ticket = new Ticket();
    // can() enforces GLPI profile, entity and per-ticket visibility.
    if (!$ticket->can($id, READ)) { throw new RuntimeException('Sin permiso para consultar este ticket', 403); }
    $action = (string)($_POST['action'] ?? 'status');
    if (!in_array($action, ['status','acquire','heartbeat','release','request','handoff','takeover','force_release'], true)) {
        throw new RuntimeException('Acción no válida', 400);
    }
    if ($action !== 'status' && !$ticket->can($id, UPDATE)) { throw new RuntimeException('Sin permiso de edición', 403); }
    if (in_array($action, ['takeover','force_release'], true) && !PluginEditcontrolBridge::admin()) {
        throw new RuntimeException('Se requiere el permiso específico de administración', 403);
    }
    $s = PluginEditcontrolBridge::service();
    $s->lock($id);
    $user = (int)Session::getLoginUserID();
    $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
    $baseline = is_string($_POST['baseline'] ?? null) ? $_POST['baseline'] : '';
    $requestToken = is_string($_POST['request_token'] ?? null) ? $_POST['request_token'] : '';
    $out = [];
    switch ($action) {
        case 'acquire': case 'takeover':
            $token = $s->acquire($id, $user, session_id(), $baseline, $action === 'takeover');
            $out['token'] = $token; break;
        case 'heartbeat': $s->heartbeat($id, $user, $token, session_id()); break;
        case 'release': $s->release($id, $user, $token, session_id()); break;
        case 'request': $s->request($id, $user, $requestToken, session_id()); break;
        case 'handoff':
            $pending = $s->expire($id);
            $recipient = new User();
            if (!$recipient->getFromDB((int)$pending['requester']) || empty($recipient->fields['is_active']) || !empty($recipient->fields['is_deleted'])) {
                throw new PluginEditcontrolConflict('El técnico solicitante ya no está disponible.');
            }
            $s->handoff($id, $user, $token, session_id(), is_string($_POST['request_key'] ?? null) ? $_POST['request_key'] : '');
            break;
        case 'force_release': $s->clear($id, $user, 'admin_release'); break;
    }
    if ($action === 'status' && $requestToken !== '' && $ticket->can($id, UPDATE)) {
        $s->refreshRequest($id, $user, $requestToken, session_id());
    }
    $row = $s->expire($id);
    $mine = $s->owns($row, $user, $token, session_id());
    $name = '';
    if ((int)$row['users_id']) { $u = new User(); if ($u->getFromDB((int)$row['users_id'])) { $name = $u->getName(); } }
    $requesterName = '';
    if ((int)$row['requester']) { $u = new User(); if ($u->getFromDB((int)$row['requester'])) { $requesterName = $u->getName(); } }
    $out += ['ok'=>true, 'owner'=>$name, 'started'=>(int)$row['started'], 'heartbeat'=>(int)$row['heartbeat'],
        'expires'=>(int)$row['expires'], 'available'=>!(int)$row['users_id'],
        'mine'=>$mine, 'same_user'=>(int)$row['users_id'] === $user,
        'requester'=>(int)$row['requester'], 'requester_name'=>$requesterName,
        'request_key'=>$mine ? $row['requester_token_hash'] : '',
        'requested_by_me'=>$requestToken !== '' ? $s->requestedBy($row, $user, $requestToken, session_id()) : (int)$row['requester'] === $user,
        'changed'=>!hash_equals($s->snapshot($id), $baseline), 'csrf'=>$csrf];
    echo json_encode($out, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code($e instanceof PluginEditcontrolConflict ? 409 : (in_array($e->getCode(), [400,403], true) ? $e->getCode() : 503));
    echo json_encode(['ok'=>false, 'message'=>$e instanceof PluginEditcontrolConflict || in_array($e->getCode(), [400,403], true) ? $e->getMessage() : 'Control de edición no disponible. Conserve el texto y reintente.', 'csrf'=>$csrf]);
}
