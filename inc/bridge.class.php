<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
final class PluginEditcontrolBridge {
    private static ?PluginEditcontrolService $service = null;
    private static array $validated = [];
    private static array $successful = [];
    private static array $tokens = [];
    private static array $writeBaselines = [];
    private static array $creationItems = [];
    private static array $baselines = [];
    public static function config(): array {
        return Config::getConfigurationValues('plugin:editcontrol') + ['ttl'=>120, 'mode'=>'warning', 'takeover_policy'=>'admins'];
    }
    public static function service(): PluginEditcontrolService {
        global $DB;
        if (self::$service === null) {
            self::$service = new PluginEditcontrolService($DB, (int)self::config()['ttl']);
            register_shutdown_function([self::class, 'shutdown']);
        }
        return self::$service;
    }
    public static function shutdown(): void {
        $successful = self::$successful;
        self::$successful = [];
        try {
            foreach ($successful as $id => $_) {
                if (isset(self::$tokens[$id])) {
                    if (self::asynchronousWrite()) {
                        if (isset(self::$writeBaselines[$id])) {
                            self::$service->completeWrite($id, (int)Session::getLoginUserID(), self::$tokens[$id], session_id(), self::$writeBaselines[$id]);
                        }
                    } else {
                        self::$service->release($id, (int)Session::getLoginUserID(), self::$tokens[$id], session_id());
                    }
                }
            }
        } catch (Throwable $e) {
            // Explicit takeover recovers ownership if the best-effort release fails.
            Toolbox::logInFile('editcontrol', 'Shutdown: ' . $e->getMessage() . "\n");
        } finally {
            try { self::$service?->unlockAll(); }
            catch (Throwable $e) { Toolbox::logInFile('editcontrol', 'Unlock: ' . $e->getMessage() . "\n"); }
        }
    }
    private static function asynchronousWrite(): bool {
        return strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') === 0
            || isset($_SERVER['HTTP_X_EDITCONTROL_TOKEN']);
    }
    public static function nonce(): string {
        return $_SESSION['plugin_editcontrol_nonce'] ??= bin2hex(random_bytes(32));
    }
    public static function admin(): bool { return Session::haveRight('plugin_editcontrol_admin', READ); }
    public static function canTakeover($ticket, int $id): bool {
        return Session::getCurrentInterface() === 'central' && $ticket->can($id, UPDATE)
            && (self::config()['takeover_policy'] === 'editors' || self::admin() || Session::haveRight('config', UPDATE));
    }
    public static function ticketIds($item): array {
        if ($item instanceof Ticket) { $ids = [(int)($item->fields['id'] ?? $item->input['id'] ?? 0)]; }
        else {
            // Existing children are always guarded using persisted parents, never a submitted replacement.
            $f = !empty($item->fields['id']) ? $item->fields : (is_array($item->input) ? $item->input : []);
            if (isset($f['tickets_id_1']) || isset($f['tickets_id_2'])) { $ids = [(int)($f['tickets_id_1'] ?? 0), (int)($f['tickets_id_2'] ?? 0)]; }
            elseif (isset($f['tickets_id'])) { $ids = [(int)$f['tickets_id']]; }
            else { $ids = ($f['itemtype'] ?? '') === 'Ticket' ? [(int)($f['items_id'] ?? 0)] : []; }
        }
        return array_values(array_unique(array_filter($ids, static fn($id)=>$id>0)));
    }
    public static function ticketId($item): int {
        $ids = self::ticketIds($item);
        $context = (int)($_POST['_editcontrol_ticket'] ?? $_SERVER['HTTP_X_EDITCONTROL_TICKET'] ?? 0);
        return in_array($context, $ids, true) ? $context : ($ids[0] ?? 0);
    }
    private static function protectRelated($item, int $primary): void {
        $s = self::service();
        $ids = self::ticketIds($item); sort($ids);
        foreach ($ids as $id) {
            if ($id === $primary || isset(self::$validated[$id])) { continue; }
            $r = $s->expire($id);
            if (self::interactive() && self::config()['mode'] === 'strict' && (int)$r['users_id'] > 0) {
                throw new PluginEditcontrolConflict('El otro ticket relacionado está bloqueado. Solicite su control antes de cambiar la relación.');
            }
            // Native relation updates may update both tickets. The connection mutex
            // protects the otherwise unowned target until the entire request ends.
            $s->invalidate($id);
            self::$validated[$id] = true;
        }
    }
    public static function interactive(): bool {
        global $CFG_GLPI;
        // Trusted server execution context; never trust an HTTP bypass header/parameter.
        if (PHP_SAPI === 'cli' || self::isApiPath($_SERVER['REQUEST_URI'] ?? '', $CFG_GLPI['root_doc'] ?? '')) { return false; }
        return Session::getCurrentInterface() === 'central';
    }
    public static function isApiPath(string $uri, string $root): bool {
        // GLPI 11 isAPI() searches REQUEST_URI substrings (including query parameters).
        // Use only the anchored entrypoint path so ?x=api.php cannot bypass strict mode.
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) && preg_match('#^' . preg_quote(rtrim($root, '/'), '#') . '/(?:api|apirest)\.php(?:/|$)#D', $path) === 1;
    }
    public static function guard($item): void {
        $id = self::ticketId($item);
        if (!$id || !is_array($item->input)) { return; }
        try {
            if (isset(self::$validated[$id])) { self::protectRelated($item, $id); return; }
            if (!($item instanceof Ticket) && !empty($item->fields['id'])) {
                foreach (['tickets_id','tickets_id_1','tickets_id_2','itemtype','items_id'] as $key) {
                    if (isset($item->input[$key], $item->fields[$key]) && (string)$item->input[$key] !== (string)$item->fields[$key]) {
                        throw new PluginEditcontrolConflict('No se permite cambiar el ticket de un elemento existente.');
                    }
                }
            }
            $s = self::service();
            $s->lock($id);
            $newTicket = false;
            foreach (self::$creationItems as $creating) {
                if ((int)($creating->fields['id'] ?? 0) === $id) { $newTicket = true; break; }
            }
            if ($newTicket) {
                // Ticket post_addItem may generate template tasks before ITEM_ADD.
                // The tracked in-memory Ticket object now contains its newly inserted ID.
                $s->invalidate($id);
            } elseif (self::interactive()) {
                $token = (string)($_POST['_editcontrol_token'] ?? $_SERVER['HTTP_X_EDITCONTROL_TOKEN'] ?? '');
                $baseline = (string)($_POST['_editcontrol_baseline'] ?? $_SERVER['HTTP_X_EDITCONTROL_BASELINE'] ?? '');
                $valid = $s->validateWrite($id, (int)Session::getLoginUserID(), $token, session_id(), $baseline, self::config()['mode'] === 'strict');
                if ($valid) { self::$writeBaselines[$id] = $baseline; }
                if ($token !== '') { self::$tokens[$id] = $token; }
            } else {
                $s->invalidate($id);
                $s->event($id, (int)Session::getLoginUserID(), 'external_write');
            }
            // CommonDBTM loaded fields before the pre hook. Refresh under the mutex.
            if (!empty($item->fields['id'])) { $item->getFromDB((int)$item->fields['id']); }
            self::$validated[$id] = true;
            self::protectRelated($item, $id);
        } catch (Throwable $e) {
            // Advisory mode must never stop a GLPI write, including infrastructure failures.
            if (self::config()['mode'] === 'strict') {
                $item->input = false;
                Session::addMessageAfterRedirect($e instanceof PluginEditcontrolConflict ? $e->getMessage() : 'No se pudo comprobar el control de edición. Reintente sin cerrar esta pestaña.', false, ERROR);
            }
            Toolbox::logInFile('editcontrol', $e->getMessage() . "\n");
        }
    }
    public static function saved($item): void {
        $id = self::ticketId($item);
        if (isset(self::$validated[$id])) { self::$successful[$id] = true; }
    }
    public static function created($item): void {
        // A newly created ticket has no previously editable version or lease.
        // Its subsequent automatic tasks/solutions belong to the creation request.
        $id = (int)($item->fields['id'] ?? 0);
        if ($id > 0) {
            try {
                self::service()->lock($id);
                self::service()->invalidate($id);
                self::$validated[$id] = true;
            } catch (Throwable $e) {
                // The new ticket is already inserted; do not turn its success into a fatal error.
                Toolbox::logInFile('editcontrol', $e->getMessage() . "\n");
            }
        }
    }
    public static function beginCreation($item): void { self::$creationItems[] = $item; }
    public static function capture(array $params): void {
        $item = $params['item'] ?? null;
        if (!$item instanceof Ticket || empty($item->fields['id'])) { return; }
        $id = (int)$item->fields['id'];
        if (isset(self::$baselines[$id])) { return; }
        try {
            $s = self::service();
            $s->lock($id);
            self::$baselines[$id] = $s->renderedSnapshot($id, $item->fields);
            if (!self::$validated) { $s->unlockAll(); }
        } catch (Throwable $e) {
            Toolbox::logInFile('editcontrol', $e->getMessage() . "\n");
        }
    }
    public static function form(array $params): void {
        $item = $params['item'] ?? null;
        if (!$item instanceof Ticket || empty($item->fields['id']) || Session::getCurrentInterface() !== 'central') { return; }
        $id = (int)$item->fields['id'];
        try {
            $s = self::service();
            $s->lock($id);
            // Keep the form's actual fields, rather than replacing a stale form with a fresh baseline.
            $baseline = self::$baselines[$id] ?? $s->renderedSnapshot($id, $item->fields);
            $cfg = ['id'=>$id, 'baseline'=>$baseline, 'nonce'=>self::nonce(),
                'csrf'=>Session::getNewCSRFToken(), 'mode'=>self::config()['mode'],
                'canEdit'=>$item->can($id, UPDATE),
                'canTakeover'=>self::canTakeover($item, $id),
                'interval'=>max(15, (int)self::config()['ttl'] / 3), 'admin'=>self::admin(),
                'pollInterval'=>8,
                'url'=>Plugin::getWebDir('editcontrol') . '/ajax/lease.php'];
            echo '<div class="editcontrol-bootstrap" data-config="' . htmlspecialchars(json_encode($cfg, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') . '"></div>';
            // Read rendering is complete; a long page render must not hold the ticket mutex.
            if (!self::$validated) { $s->unlockAll(); }
        } catch (Throwable $e) { echo '<div class="alert alert-danger">No se pudo iniciar el control de edición.</div>'; }
    }
}
