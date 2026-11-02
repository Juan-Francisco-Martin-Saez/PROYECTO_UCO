<?php
declare(strict_types=1);
/** Fase 3C.2: comprobación de artículos. CLI, con copia de seguridad previa. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Solo CLI');}
$path=dirname(__DIR__).'/database/catalogo.sqlite';
try{
 if(!is_file($path))throw new RuntimeException('No existe la base SQLite');
 $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec('PRAGMA busy_timeout=10000');$db->exec('PRAGMA foreign_keys=ON');
 foreach(['uco_orders','uco_order_lines','uco_order_fulfillment','admin_users'] as $name){$q=$db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$q->execute([$name]);if(!$q->fetchColumn())throw new RuntimeException('Falta tabla '.$name);}
 echo "UCO 3C.2 · Verificación individual de artículos\n";
 if(!in_array('--apply',$argv,true)){echo "Previsualización: sin cambios. Usa --apply.\n";exit;}
 $src=new SQLite3($path,SQLITE3_OPEN_READONLY);$backup=dirname($path).'/catalogo.pre-3c2-'.date('Ymd-His').'.sqlite';$dst=new SQLite3($backup,SQLITE3_OPEN_READWRITE|SQLITE3_OPEN_CREATE);
 if(!$src->backup($dst))throw new RuntimeException('No se pudo realizar el respaldo');$src->close();$dst->close();echo "Respaldo: $backup\n";
 $db->exec('BEGIN IMMEDIATE');
 $db->exec("CREATE TABLE IF NOT EXISTS uco_order_line_checks (
 line_id INTEGER PRIMARY KEY REFERENCES uco_order_lines(id) ON DELETE CASCADE,
 order_id INTEGER NOT NULL REFERENCES uco_orders(id) ON DELETE CASCADE,
 prepared_quantity REAL NOT NULL CHECK(prepared_quantity>=0),
 note TEXT NOT NULL DEFAULT '',
 verified_by_staff_id INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
 )");
 $db->exec('CREATE INDEX IF NOT EXISTS idx_uco_line_checks_order ON uco_order_line_checks(order_id)');
 $db->exec("CREATE TABLE IF NOT EXISTS uco_order_line_check_events (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 order_id INTEGER NOT NULL REFERENCES uco_orders(id) ON DELETE CASCADE,
 line_id INTEGER NOT NULL REFERENCES uco_order_lines(id) ON DELETE CASCADE,
 prepared_quantity REAL NOT NULL,
 note TEXT NOT NULL DEFAULT '',
 actor_staff_id INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
 )");
 $db->exec('CREATE INDEX IF NOT EXISTS idx_uco_line_check_events_order ON uco_order_line_check_events(order_id,id)');
 if($db->query('PRAGMA foreign_key_check')->fetchAll())throw new RuntimeException('Error de claves foráneas');
 $db->commit();echo 'OK: tablas de verificación creadas. Integridad: '.$db->query('PRAGMA integrity_check')->fetchColumn()."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,'ERROR: '.$e->getMessage()."\n");exit(1);}
