<?php
/** Test harness: SQLite is a behavioural model, NOT GLPI/MariaDB certification. */
require_once dirname(__DIR__) . '/inc/service.class.php';
final class TestDB {
    public PDO $pdo;
    private array $locks = [];
    public bool $mysql;
    public function __construct() {
        $dsn = getenv('EC_TEST_DSN') ?: 'sqlite:' . __DIR__ . '/results/model.sqlite';
        $this->mysql = str_starts_with($dsn, 'mysql:');
        if ($this->mysql && !preg_match('/(?:^|;)dbname=editcontrol_test(?:;|$)/', $dsn)) {
            throw new RuntimeException('Use exclusively a disposable database named editcontrol_test');
        }
        if (!$this->mysql && $dsn !== 'sqlite:' . __DIR__ . '/results/model.sqlite') {
            throw new RuntimeException('Unexpected SQLite path');
        }
        if (!is_dir(__DIR__ . '/results')) { mkdir(__DIR__ . '/results', 0700, true); }
        $this->pdo = new PDO($dsn, getenv('EC_TEST_USER') ?: null, getenv('EC_TEST_PASSWORD') ?: null,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        if (!$this->mysql) {
            $this->pdo->exec('PRAGMA busy_timeout=10000');
            $this->pdo->sqliteCreateFunction('UNIX_TIMESTAMP', fn() => time(), 0);
            $this->pdo->sqliteCreateFunction('DATABASE', fn() => 'editcontrol_test', 0);
            $this->pdo->sqliteCreateFunction('SHA2', fn($s,$n) => hash('sha256', $s), 2);
            $this->pdo->sqliteCreateFunction('ECLEFT', fn($s,$n) => substr($s,0,$n), 2);
            $this->pdo->sqliteCreateFunction('CONCAT', fn(...$s) => implode('', $s));
            $this->pdo->sqliteCreateFunction('GET_LOCK', function($name,$timeout) {
                if (isset($this->locks[$name])) { return 1; }
                $f = fopen(__DIR__ . '/results/' . hash('sha256', $name) . '.lock', 'c');
                $end = microtime(true) + $timeout;
                do {
                    if (flock($f, LOCK_EX | LOCK_NB)) { $this->locks[$name] = $f; return 1; }
                    usleep(10000);
                } while (microtime(true) < $end);
                fclose($f); return 0;
            }, 2);
            $this->pdo->sqliteCreateFunction('RELEASE_LOCK', function($name) {
                if (!isset($this->locks[$name])) { return null; }
                flock($this->locks[$name], LOCK_UN); fclose($this->locks[$name]); unset($this->locks[$name]); return 1;
            }, 1);
        }
    }
    public function doQuery(string $sql) {
        if (!$this->mysql) { $sql = str_replace(['INSERT IGNORE','LEFT(', 'ON DUPLICATE KEY UPDATE tickets_id=tickets_id'], ['INSERT OR IGNORE','ECLEFT(', 'ON CONFLICT(tickets_id) DO NOTHING'], $sql); }
        return $this->pdo->query($sql);
    }
    public function escape(string $s): string { return substr($this->pdo->quote($s), 1, -1); }
    public function fetchAssoc($r) { return $r->fetch(PDO::FETCH_ASSOC); }
    public function reset(): void {
        // This method is permitted only for the fixed disposable test database.
        foreach (['glpi_plugin_editcontrol_events','glpi_plugin_editcontrol_leases','glpi_tickets'] as $t) {
            $this->pdo->exec("DROP TABLE IF EXISTS $t");
        }
        if ($this->mysql) {
            foreach (explode(';', file_get_contents(dirname(__DIR__) . '/install/schema.sql')) as $sql) {
                if (trim($sql)) { $this->pdo->exec($sql); }
            }
        } else {
            $this->pdo->exec("CREATE TABLE glpi_plugin_editcontrol_leases (tickets_id INTEGER PRIMARY KEY,generation INTEGER DEFAULT 0,revision INTEGER DEFAULT 0,users_id INTEGER DEFAULT 0,token_hash TEXT DEFAULT '',session_hash TEXT DEFAULT '',started INTEGER DEFAULT 0,heartbeat INTEGER DEFAULT 0,expires INTEGER DEFAULT 0,requester INTEGER DEFAULT 0,requested INTEGER DEFAULT 0,requester_token_hash TEXT DEFAULT '',requester_session_hash TEXT DEFAULT '',write_from TEXT DEFAULT '',write_to TEXT DEFAULT '')");
            $this->pdo->exec('CREATE TABLE glpi_plugin_editcontrol_events (id INTEGER PRIMARY KEY AUTOINCREMENT,tickets_id INTEGER,users_id INTEGER,previous_users_id INTEGER,event TEXT,occurred INTEGER)');
        }
        $this->pdo->exec('CREATE TABLE glpi_tickets (id INTEGER PRIMARY KEY,name VARCHAR(255),status INTEGER,date_mod VARCHAR(40))');
        foreach ([1,2,3] as $id) { $this->pdo->exec("INSERT INTO glpi_tickets VALUES ($id,'Ticket de prueba',1,'2026-11-16 12:00:00')"); }
    }
}
function check(bool $value, string $label): void {
    if (!$value) { throw new RuntimeException('FAIL: ' . $label); }
    echo 'PASS: ' . $label . PHP_EOL;
}
function conflict(callable $fn, string $label): void {
    try { $fn(); } catch (PluginEditcontrolConflict $e) { check(true,$label); return; }
    check(false,$label);
}
