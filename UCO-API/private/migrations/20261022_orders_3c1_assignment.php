<?php
declare(strict_types=1);
/** Fase 3C.1: auditoría aditiva de asignaciones. Ejecutar con --apply. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Solo CLI');}
$path=dirname(__DIR__).'/database/catalogo.sqlite';
try{
 if(!is_file($path))throw new RuntimeException('No existe la base SQLite');
 $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec('PRAGMA busy_timeout=10000');$db->exec('PRAGMA foreign_keys=ON');
 foreach(['uco_order_fulfillment','uco_orders','admin_users'] as $name){$q=$db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$q->execute([$name]);if(!$q->fetchColumn())throw new RuntimeException('Falta tabla '.$name);}
 echo "UCO 3C.1 · Asignación de responsables de preparación\n";
 if(!in_array('--apply',$argv,true)){echo "Previsualización: sin cambios. Usa --apply.\n";exit;}
 $src=new SQLite3($path,SQLITE3_OPEN_READONLY);$backup=dirname($path).'/catalogo.pre-3c1-'.date('Ymd-His').'.sqlite';$dst=new SQLite3($backup,SQLITE3_OPEN_READWRITE|SQLITE3_OPEN_CREATE);
 if(!$src->backup($dst))throw new RuntimeException('No se pudo hacer el respaldo');$src->close();$dst->close();echo "Respaldo: $backup\n";
 $db->exec('BEGIN IMMEDIATE');
 $db->exec("CREATE TABLE IF NOT EXISTS uco_order_assignment_events (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 order_id INTEGER NOT NULL REFERENCES uco_orders(id) ON DELETE RESTRICT,
 previous_staff_id INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
 new_staff_id INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE RESTRICT,
 actor_staff_id INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
 )");
 $db->exec('CREATE INDEX IF NOT EXISTS idx_uco_assignment_events_order ON uco_order_assignment_events(order_id,id)');
 if($db->query('PRAGMA foreign_key_check')->fetchAll())throw new RuntimeException('Error de claves foráneas');
 $db->commit();echo "OK: auditoría de asignaciones preparada. Integridad: ".$db->query('PRAGMA integrity_check')->fetchColumn()."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,'ERROR: '.$e->getMessage()."\n");exit(1);}
