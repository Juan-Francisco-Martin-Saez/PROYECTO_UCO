<?php
declare(strict_types=1);
/** UCO 1A: migración aditiva de identidades. CLI: php private/migrations/20261021_identity_1a.php [--apply] */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI'); }
$dbPath = dirname(__DIR__) . '/database/catalogo.sqlite';
if (!is_file($dbPath)) { fwrite(STDERR, "No existe la base de datos: $dbPath\n"); exit(1); }
$apply = in_array('--apply', $argv, true);
try {
    $db = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout=10000');
    $db->exec('PRAGMA foreign_keys=ON');
    foreach (['admin_users','customers'] as $table) {
        $exists = $db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name=" . $db->quote($table))->fetchColumn();
        if (!$exists) throw new RuntimeException("Falta tabla $table");
    }
    $counts = [];
    foreach (['admin_users','customers'] as $table) $counts[$table] = (int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    echo "UCO Identidades 1A | admin_users={$counts['admin_users']} | customers={$counts['customers']}\n";
    if (!$apply) { echo "MODO PREVISUALIZACION: sin cambios. Para aplicar: php private/migrations/20261021_identity_1a.php --apply\n"; exit(0); }
    $backup = dirname($dbPath) . '/catalogo.pre-identidad-1a-' . date('Ymd-His') . '.sqlite';
    $source = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
    $dest = new SQLite3($backup, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    if (!$source->backup($dest)) throw new RuntimeException('No se pudo generar respaldo');
    $source->close(); $dest->close();
    echo "Respaldo: $backup\n";
    $db->exec('BEGIN IMMEDIATE');
    $db->exec("CREATE TABLE IF NOT EXISTS identity_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL CHECK(kind IN ('staff','customer')),
        legacy_id INTEGER NOT NULL CHECK(legacy_id > 0),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(kind,legacy_id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS identity_customer_links (
        identity_id INTEGER NOT NULL REFERENCES identity_accounts(id) ON DELETE CASCADE,
        customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE RESTRICT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(identity_id,customer_id)
    )");
    $db->exec('CREATE INDEX IF NOT EXISTS idx_identity_customer_links_customer ON identity_customer_links(customer_id)');
    $db->exec("INSERT OR IGNORE INTO identity_accounts(kind,legacy_id) SELECT 'staff',id FROM admin_users");
    $db->exec("INSERT OR IGNORE INTO identity_accounts(kind,legacy_id) SELECT 'customer',id FROM customers");
    $db->exec("INSERT OR IGNORE INTO identity_customer_links(identity_id,customer_id)
        SELECT a.id,c.id FROM identity_accounts a JOIN customers c ON a.kind='customer' AND a.legacy_id=c.id");
    // No se vinculan empleados y clientes automáticamente por correo/nombre: exige verificación posterior.
    $missing = (int)$db->query("SELECT COUNT(*) FROM customers c WHERE NOT EXISTS
        (SELECT 1 FROM identity_accounts a JOIN identity_customer_links l ON l.identity_id=a.id
         WHERE a.kind='customer' AND a.legacy_id=c.id AND l.customer_id=c.id)")->fetchColumn();
    if ($missing) throw new RuntimeException("Clientes sin vínculo: $missing");
    $integrity = $db->query('PRAGMA integrity_check')->fetchColumn();
    $foreign = $db->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_NUM);
    if ($integrity !== 'ok' || $foreign) throw new RuntimeException('Error de integridad/relaciones');
    $db->exec('COMMIT');
    echo 'OK: identidades=' . $db->query('SELECT COUNT(*) FROM identity_accounts')->fetchColumn()
        . ', vínculos=' . $db->query('SELECT COUNT(*) FROM identity_customer_links')->fetchColumn() . "\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n"); exit(1);
}
