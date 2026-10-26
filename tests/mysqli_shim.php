<?php
/**
 * TEST HARNESS ONLY — never deployed.
 *
 * A tiny SQLite-backed stand-in for the parts of mysqli that Cinema Vault uses, so
 * the application code can be exercised in an environment without a MySQL server:
 *
 *   CV_TEST_DB=/tmp/cv.sqlite php -n -d extension=sqlite3 -d extension=mbstring \
 *       -d extension=ctype -d extension=fileinfo -d auto_prepend_file=tests/mysqli_shim.php -S 127.0.0.1:8099
 *
 * It only loads when the real mysqli extension is absent (php -n).
 * It does NOT validate MySQL-specific syntax; run tests against MariaDB for that.
 */
if (extension_loaded('mysqli')) return;

const MYSQLI_ASSOC = 1; const MYSQLI_NUM = 2; const MYSQLI_BOTH = 3;
const MYSQLI_REPORT_OFF = 0; const MYSQLI_REPORT_ERROR = 1; const MYSQLI_REPORT_STRICT = 2;
function mysqli_report(int $flags): bool { return true; }

class mysqli_sql_exception extends RuntimeException {}

class mysqli_result {
    private int $i = 0;
    public int $num_rows;
    public function __construct(private array $rows) { $this->num_rows = count($rows); }
    public function fetch_assoc(): ?array { return $this->rows[$this->i++] ?? null; }
    public function fetch_all(int $mode = MYSQLI_ASSOC): array { return $this->rows; }
}

class mysqli_stmt {
    public int $affected_rows = 0; public int $num_rows = 0; public int $errno = 0; public string $error = '';
    private array $vals = []; private string $types = ''; private ?array $rows = null;
    public function __construct(private mysqli $c, private string $sql) {}
    public function bind_param(string $types, &...$vars): bool { $this->types = $types; $this->vals = $vars; return true; }
    public function execute(): bool {
        $st = $this->c->db->prepare($this->sql);
        foreach ($this->vals as $i => $v) {
            $t = $this->types[$i] ?? 's';
            if ($v === null) $st->bindValue($i + 1, null, SQLITE3_NULL);
            elseif ($t === 'i') $st->bindValue($i + 1, (int)$v, SQLITE3_INTEGER);
            elseif ($t === 'd') $st->bindValue($i + 1, (float)$v, SQLITE3_FLOAT);
            else $st->bindValue($i + 1, (string)$v, SQLITE3_TEXT);
        }
        try { $res = $st->execute(); }
        catch (Exception $e) { throw mysqli::wrap($e); }
        $this->rows = [];
        if ($res->numColumns() > 0) { while ($r = $res->fetchArray(SQLITE3_ASSOC)) $this->rows[] = $r; }
        $this->num_rows = count($this->rows);
        $this->affected_rows = $this->c->db->changes();
        if (preg_match('/^\s*INSERT/i', $this->sql)) $this->c->insert_id = $this->c->db->lastInsertRowID();
        $res->finalize();
        return true;
    }
    public function get_result(): mysqli_result { return new mysqli_result($this->rows ?? []); }
    public function store_result(): bool { return true; }
    public function close(): bool { return true; }
}

class mysqli {
    public SQLite3 $db; public int $insert_id = 0; public string $server_info = '10.4.32-SQLite-shim';
    public function __construct(...$args) {
        $this->db = new SQLite3(getenv('CV_TEST_DB') ?: ':memory:');
        $this->db->enableExceptions(true);
        $this->db->busyTimeout(5000);
        $this->db->exec('PRAGMA foreign_keys = ON');
    }
    public static function wrap(Exception $e): mysqli_sql_exception {
        $m = $e->getMessage();
        $code = stripos($m, 'UNIQUE constraint') !== false ? 1062 : (stripos($m, 'FOREIGN KEY') !== false ? 1452 : 1064);
        return new mysqli_sql_exception($m, $code);
    }
    private function tr(string $sql): string {
        $sql = preg_replace('/\bINSERT\s+IGNORE\b/i', 'INSERT OR IGNORE', $sql);
        return preg_replace('/\bNOW\(\)/i', "datetime('now')", $sql);
    }
    public function set_charset(string $c): bool { return true; }
    public function prepare(string $sql): mysqli_stmt { return new mysqli_stmt($this, $this->tr($sql)); }
    public function query(string $sql) { try { $this->db->exec($this->tr($sql)); } catch (Exception $e) { throw self::wrap($e); } return true; }
    public function begin_transaction(): bool { $this->db->exec('BEGIN'); return true; }
    public function commit(): bool { $this->db->exec('COMMIT'); return true; }
    public function rollback(): bool { $this->db->exec('ROLLBACK'); return true; }
}
