<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
final class PluginEditcontrolConflict extends RuntimeException {}

/** All reads and mutations for a ticket use the SAME connection-scoped mutex.
 * A guarded GLPI write keeps it until shutdown, including nested hooks.
 * No open transaction is imposed on GLPI or other plugins.
 */
final class PluginEditcontrolService {
    private $db;
    private array $held = [];
    private int $ttl;
    public function __construct($db, int $ttl = 120) {
        $this->db = $db;
        $this->ttl = max(60, min(1800, $ttl));
    }
    private function q(string $sql) { return $this->db->doQuery($sql); }
    private function quote(string $value): string { return "'" . $this->db->escape($value) . "'"; }
    public function row(int $id): array {
        $r = $this->q("SELECT * FROM glpi_plugin_editcontrol_leases WHERE tickets_id=$id");
        return $this->db->fetchAssoc($r) ?: [];
    }
    public function lock(int $id): void {
        if ($id <= 0) { throw new InvalidArgumentException('Ticket no válido'); }
        if (isset($this->held[$id])) { return; }
        // Include database name: independent GLPI installations may share a server.
        $r = $this->q("SELECT GET_LOCK(CONCAT('editcontrol:',LEFT(SHA2(DATABASE(),256),16),':$id'),5) AS acquired");
        if ((int)($this->db->fetchAssoc($r)['acquired'] ?? 0) !== 1) {
            throw new PluginEditcontrolConflict('Operación en curso. Vuelva a intentarlo.');
        }
        $this->held[$id] = true;
        $this->q("INSERT INTO glpi_plugin_editcontrol_leases (tickets_id) VALUES ($id) ON DUPLICATE KEY UPDATE tickets_id=tickets_id");
    }
    public function unlockAll(): void {
        foreach (array_keys($this->held) as $id) {
            $this->q("SELECT RELEASE_LOCK(CONCAT('editcontrol:',LEFT(SHA2(DATABASE(),256),16),':$id'))");
            unset($this->held[$id]);
        }
    }
    public function now(): int {
        return (int)$this->db->fetchAssoc($this->q('SELECT UNIX_TIMESTAMP() AS clock'))['clock'];
    }
    public function fingerprint(array $fields, int $revision): string {
        ksort($fields);
        // Database drivers may return numeric values with different PHP types.
        $fields = array_map(static fn($v) => $v === null ? null : (string)$v, $fields);
        return hash('sha256', json_encode([$fields, $revision], JSON_THROW_ON_ERROR));
    }
    public function snapshot(int $id): string {
        $fields = $this->db->fetchAssoc($this->q("SELECT * FROM glpi_tickets WHERE id=$id"));
        if (!$fields) { throw new PluginEditcontrolConflict('El ticket ya no existe.'); }
        return $this->fingerprint($fields, (int)($this->row($id)['revision'] ?? 0));
    }
    public function event(int $id, int $user, string $event, int $previous = 0): void {
        $e = $this->quote($event);
        $this->q("INSERT INTO glpi_plugin_editcontrol_events (tickets_id,users_id,previous_users_id,event,occurred) VALUES ($id,$user,$previous,$e,UNIX_TIMESTAMP())");
    }
    public function expire(int $id): array {
        $this->lock($id);
        $r = $this->row($id);
        if ((int)$r['users_id'] > 0 && (int)$r['expires'] <= $this->now()) {
            $this->clear($id, 0, 'expired');
            $r = $this->row($id);
        }
        return $r;
    }
    public function owns(array $r, int $user, string $token, string $session): bool {
        return $token !== '' && (int)$r['users_id'] === $user && $user > 0
            && (int)$r['expires'] > $this->now()
            && hash_equals($r['token_hash'], hash('sha256', $token))
            && hash_equals($r['session_hash'], hash('sha256', $session));
    }
    public function acquire(int $id, int $user, string $session, string $baseline, bool $force = false): string {
        $r = $this->expire($id);
        if (!hash_equals($this->snapshot($id), $baseline)) {
            throw new PluginEditcontrolConflict('El ticket ha cambiado. Recargue antes de editar.');
        }
        if ((int)$r['users_id'] && !$force) { throw new PluginEditcontrolConflict('Otro técnico o pestaña tiene el control.'); }
        $token = bin2hex(random_bytes(32));
        $hash = $this->quote(hash('sha256', $token));
        $session = $this->quote(hash('sha256', $session));
        $this->q("UPDATE glpi_plugin_editcontrol_leases SET generation=generation+1, users_id=$user,token_hash=$hash,session_hash=$session,started=UNIX_TIMESTAMP(),heartbeat=UNIX_TIMESTAMP(),expires=UNIX_TIMESTAMP()+$this->ttl,requester=0,requested=0 WHERE tickets_id=$id");
        $this->event($id, $user, $force ? 'takeover' : 'acquired', (int)$r['users_id']);
        return $token;
    }
    public function heartbeat(int $id, int $user, string $token, string $session): void {
        $r = $this->expire($id);
        if (!$this->owns($r, $user, $token, $session)) { throw new PluginEditcontrolConflict('Ha perdido el control. Conserve el texto y recargue.'); }
        $this->q("UPDATE glpi_plugin_editcontrol_leases SET heartbeat=UNIX_TIMESTAMP(),expires=UNIX_TIMESTAMP()+$this->ttl WHERE tickets_id=$id");
    }
    public function clear(int $id, int $actor, string $reason): void {
        $this->lock($id);
        $old = (int)$this->row($id)['users_id'];
        $this->q("UPDATE glpi_plugin_editcontrol_leases SET users_id=0,token_hash='',session_hash='',expires=0,requester=0,requested=0 WHERE tickets_id=$id");
        if ($old) { $this->event($id, $actor, $reason, $old); }
    }
    public function release(int $id, int $user, string $token, string $session): void {
        $r = $this->expire($id);
        if (!$this->owns($r, $user, $token, $session)) { throw new PluginEditcontrolConflict('La pestaña ya no tiene el control.'); }
        $this->clear($id, $user, 'released');
    }
    public function request(int $id, int $user): void {
        $r = $this->expire($id);
        if (!(int)$r['users_id']) { return; }
        if ((int)$r['requester'] === $user && (int)$r['requested'] > $this->now() - 30) { return; }
        $this->q("UPDATE glpi_plugin_editcontrol_leases SET requester=$user,requested=UNIX_TIMESTAMP() WHERE tickets_id=$id");
        $this->event($id, $user, 'requested', (int)$r['users_id']);
    }
    public function validateWrite(int $id, int $user, string $token, string $session, string $baseline, bool $strict): void {
        $r = $this->expire($id);
        if (!$this->owns($r, $user, $token, $session) || !hash_equals($this->snapshot($id), $baseline)) {
            $this->event($id, $user, $strict ? 'write_denied' : 'write_warning');
            if ($strict) { throw new PluginEditcontrolConflict('Guardado rechazado: control perdido o datos modificados. Conserve el texto y recargue.'); }
        }
        $this->invalidate($id);
    }
    public function invalidate(int $id): void {
        $this->lock($id);
        $this->q("UPDATE glpi_plugin_editcontrol_leases SET revision=revision+1 WHERE tickets_id=$id");
    }
}
