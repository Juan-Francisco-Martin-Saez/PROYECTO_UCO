<?php
declare(strict_types=1);
/** UCO 3C.1: permite NULL en la auditoría al desasignar un responsable. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI'); }
$path = dirname(__DIR__) . '/database/catalogo.sqlite';
$db = null;
try {
    if (!is_file($path)) throw new RuntimeException('No existe catalogo.sqlite');
    $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout=10000');
    $db->exec('PRAGMA foreign_keys=ON');
    $name = 'uco_order_assignment_events';
    $stmt = $db->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$name]);
    $sql = $stmt->fetchColumn();
    $stmt->closeCursor();
    if (!$sql) throw new RuntimeException('Falta la tabla de auditoría 3C.1');
    $info = $db->query('PRAGMA table_info(' . $name . ')');
    $cols = $info->fetchAll(PDO::FETCH_ASSOC);
    $info->closeCursor(); unset($info);
    $target = null;
    foreach ($cols as $col) if ($col['name'] === 'new_staff_id') $target = $col;
    if (!$target) throw new RuntimeException('Falta la columna new_staff_id');
    $counter = $db->query('SELECT COUNT(*) FROM ' . $name);
    $count = (int)$counter->fetchColumn();
    $counter->closeCursor(); unset($counter);
    echo "UCO 3C.1 · Corrección de desasignación «Nadie»\nRegistros de auditoría existentes: $count\n";
    if (!(int)$target['notnull']) { echo "OK: new_staff_id ya permite NULL. No se requieren cambios.\n"; exit(0); }
    if (!in_array('--apply', $argv, true)) { echo "Previsualización: se permitirá NULL en new_staff_id. Sin cambios. Usa --apply.\n"; exit(0); }
    if (!class_exists('SQLite3')) throw new RuntimeException('La extensión sqlite3 es necesaria para el respaldo');
    $backup = dirname($path) . '/catalogo.pre-3c1-fix-' . date('Ymd-His') . '.sqlite';
    $source = new SQLite3($path, SQLITE3_OPEN_READONLY);
    $dest = new SQLite3($backup, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    if (!$source->backup($dest)) throw new RuntimeException('No se pudo crear el respaldo');
    $source->close(); $dest->close();
    echo "Respaldo: $backup\n";
    $objectQuery = $db->query("SELECT type,name,sql FROM sqlite_master WHERE tbl_name='uco_order_assignment_events' AND type IN ('index','trigger') AND sql IS NOT NULL ORDER BY type,name");
    $objects = $objectQuery->fetchAll(PDO::FETCH_ASSOC);
    $objectQuery->closeCursor(); unset($objectQuery);
    $newSql = preg_replace('/\bnew_staff_id\s+INTEGER\s+NOT\s+NULL\b/i', 'new_staff_id INTEGER', $sql, 1, $replacements);
    if ($replacements !== 1) throw new RuntimeException('Esquema inesperado: no se modifica la tabla');
    $newSql = preg_replace('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?["`\[]?uco_order_assignment_events["`\]]?/i', 'CREATE TABLE uco_order_assignment_events__new', $newSql, 1, $replacedName);
    if ($replacedName !== 1) throw new RuntimeException('No se pudo preparar la tabla temporal');
    $names = array_column($cols, 'name');
    foreach ($names as $col) if (!preg_match('/^[A-Za-z_][A-Za-z_0-9]*$/', $col)) throw new RuntimeException('Nombre de columna no esperado');
    $columns = implode(',', array_map(static fn($col) => '"' . $col . '"', $names));
    $db->exec('PRAGMA foreign_keys=OFF'); // SQLite requiere desactivar antes de BEGIN para reconstruir tablas.
    $db->exec('BEGIN IMMEDIATE');
    $db->exec($newSql);
    $db->exec("INSERT INTO uco_order_assignment_events__new ($columns) SELECT $columns FROM uco_order_assignment_events");
    $db->exec('DROP TABLE uco_order_assignment_events');
    $db->exec('ALTER TABLE uco_order_assignment_events__new RENAME TO uco_order_assignment_events');
    foreach ($objects as $object) $db->exec($object['sql']);
    $after = (int)$db->query('SELECT COUNT(*) FROM uco_order_assignment_events')->fetchColumn();
    if ($after !== $count) throw new RuntimeException('El número de registros no coincide');
    if ($db->query('PRAGMA foreign_key_check')->fetchAll()) throw new RuntimeException('Error de claves foráneas');
    $integrity = $db->query('PRAGMA integrity_check')->fetchColumn();
    if ($integrity !== 'ok') throw new RuntimeException('Error de integridad: ' . $integrity);
    $db->commit();
    $db->exec('PRAGMA foreign_keys=ON');
    echo "OK: new_staff_id admite NULL. Auditoría conservada: $after registros. Integridad: $integrity\n";
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
