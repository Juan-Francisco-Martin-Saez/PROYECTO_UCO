<?php
declare(strict_types=1);
/** UCO 3E.1: catálogo de roles/permisos, sin modificar accesos existentes. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI'); }
$path = dirname(__DIR__) . '/database/catalogo.sqlite';
$apply = in_array('--apply', $argv, true);
try {
    if (!is_file($path)) throw new RuntimeException('No existe catalogo.sqlite');
    $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout=10000');
    $db->exec('PRAGMA foreign_keys=ON');
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('admin_users', $tables, true)) throw new RuntimeException('Falta admin_users');
    echo "UCO 3E.1 · Perfiles y permisos por departamento\n";
    echo "Usuarios existentes: " . (int)$db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() . "\n";
    if (!$apply) { echo "Previsualización: sin cambios. Usa --apply.\n"; exit(0); }
    $backup = dirname($path) . '/catalogo.pre-3e1-' . date('Ymd-His') . '.sqlite';
    $source = new SQLite3($path, SQLITE3_OPEN_READONLY);
    $target = new SQLite3($backup, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    if (!$source->backup($target)) throw new RuntimeException('No se pudo crear el respaldo');
    $source->close(); $target->close();
    echo "Respaldo: $backup\n";
    $db->exec('BEGIN IMMEDIATE');
    $db->exec('CREATE TABLE IF NOT EXISTS uco_access_roles (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, label TEXT NOT NULL, description TEXT NOT NULL DEFAULT \'\', is_system INTEGER NOT NULL DEFAULT 1 CHECK(is_system IN (0,1)))');
    $db->exec('CREATE TABLE IF NOT EXISTS uco_access_permissions (code TEXT PRIMARY KEY, label TEXT NOT NULL, area TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS uco_access_role_permissions (role_id INTEGER NOT NULL REFERENCES uco_access_roles(id) ON DELETE CASCADE, permission_code TEXT NOT NULL REFERENCES uco_access_permissions(code) ON DELETE CASCADE, PRIMARY KEY(role_id,permission_code))');
    $db->exec('CREATE TABLE IF NOT EXISTS uco_access_staff_roles (staff_id INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE, role_id INTEGER NOT NULL REFERENCES uco_access_roles(id) ON DELETE CASCADE, assigned_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(staff_id,role_id))');
    $roles = [
        ['administrator','Administrador','Acceso completo, sujeto a la futura activación de permisos'],
        ['commercial','Comercial','Clientes, catálogo, tarifas y pedidos autorizados'],
        ['warehouse_preparation','Almacén · Preparación','Preparación y verificación de artículos'],
        ['warehouse_shipping','Almacén · Expedición','Expedición y entrega de pedidos'],
        ['warehouse_supervisor','Responsable de almacén','Preparación, expedición e historial'],
        ['viewer','Consulta','Acceso de lectura a las áreas autorizadas'],
    ];
    $permissions = [
        ['catalog.view','Consultar catálogo','catalog'],['catalog.manage','Gestionar catálogo','catalog'],
        ['customers.view','Consultar clientes','customers'],['customers.manage','Gestionar clientes','customers'],
        ['pricing.view','Consultar tarifas','pricing'],['pricing.manage','Gestionar tarifas','pricing'],
        ['orders.view','Consultar pedidos','orders'],['orders.manage','Administrar pedidos','orders'],
        ['orders.prepare.view','Consultar preparación','preparation'],['orders.prepare.manage','Preparar y verificar artículos','preparation'],
        ['orders.assign','Asignar responsables','preparation'],
        ['orders.ship.view','Consultar expedición','shipping'],['orders.ship.manage','Registrar expediciones y entregas','shipping'],
        ['orders.history.view','Consultar historial','history'],
        ['staff.manage','Administrar usuarios y permisos','security'],
    ];
    $insertRole = $db->prepare('INSERT OR IGNORE INTO uco_access_roles(code,label,description) VALUES(?,?,?)');
    foreach ($roles as $role) $insertRole->execute($role);
    $insertPermission = $db->prepare('INSERT OR IGNORE INTO uco_access_permissions(code,label,area) VALUES(?,?,?)');
    foreach ($permissions as $permission) $insertPermission->execute($permission);
    $grants = [
        'administrator'=>array_column($permissions,0),
        'commercial'=>['catalog.view','customers.view','customers.manage','pricing.view','pricing.manage','orders.view'],
        'warehouse_preparation'=>['orders.view','orders.prepare.view','orders.prepare.manage'],
        'warehouse_shipping'=>['orders.view','orders.ship.view','orders.ship.manage'],
        'warehouse_supervisor'=>['orders.view','orders.prepare.view','orders.prepare.manage','orders.assign','orders.ship.view','orders.ship.manage','orders.history.view'],
        'viewer'=>['orders.view'],
    ];
    $getRole = $db->prepare('SELECT id FROM uco_access_roles WHERE code=?');
    $grant = $db->prepare('INSERT OR IGNORE INTO uco_access_role_permissions(role_id,permission_code) VALUES(?,?)');
    foreach ($grants as $code=>$list) { $getRole->execute([$code]); $id=(int)$getRole->fetchColumn(); $getRole->closeCursor(); foreach($list as $permission) $grant->execute([$id,$permission]); }
    $fk = $db->query('PRAGMA foreign_key_check')->fetchAll();
    if ($fk) throw new RuntimeException('Falló la comprobación de claves foráneas');
    $db->commit();
    $integrity = $db->query('PRAGMA integrity_check')->fetchColumn();
    if ($integrity !== 'ok') throw new RuntimeException('Integridad: ' . $integrity);
    echo 'OK: ' . count($roles) . ' perfiles y ' . count($permissions) . " permisos preparados. Integridad: ok\n";
    echo "Sin asignaciones automáticas; la autenticación y los permisos actuales no cambian.\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n"); exit(1);
}
