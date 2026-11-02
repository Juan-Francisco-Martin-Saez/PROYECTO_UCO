<?php
declare(strict_types=1);
/** Fase 3D: registro auditado de expediciones y entregas. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Solo CLI');}
$path=dirname(__DIR__).'/database/catalogo.sqlite';
try{
 if(!is_file($path))throw new RuntimeException('No existe la base de datos');
 $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec('PRAGMA busy_timeout=10000');$db->exec('PRAGMA foreign_keys=ON');
 foreach(['uco_orders','admin_users','uco_order_fulfillment','uco_order_line_checks'] as $table){$q=$db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$q->execute([$table]);$exists=$q->fetchColumn();$q->closeCursor();if(!$exists)throw new RuntimeException('Falta la tabla '.$table);}
 echo "UCO Fase 3D · Expedición y entregas\n";
 $exists=(bool)$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='uco_order_shipping_events'")->fetchColumn();
 if(!in_array('--apply',$argv,true)){echo $exists?"Tabla ya presente. Sin cambios.\n":"Previsualización: se creará la auditoría de expediciones. Sin cambios. Usa --apply.\n";exit;}
 if($exists){echo "OK: la tabla ya existe. Integridad: ".$db->query('PRAGMA integrity_check')->fetchColumn()."\n";exit;}
 $src=new SQLite3($path,SQLITE3_OPEN_READONLY);$backup=dirname($path).'/catalogo.pre-3d-'.date('Ymd-His').'.sqlite';$dst=new SQLite3($backup,SQLITE3_OPEN_READWRITE|SQLITE3_OPEN_CREATE);
 if(!$src->backup($dst))throw new RuntimeException('No se pudo crear el respaldo');$src->close();$dst->close();echo "Respaldo: $backup\n";
 $db->exec('BEGIN IMMEDIATE');
 $db->exec("CREATE TABLE uco_order_shipping_events (id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES uco_orders(id) ON DELETE RESTRICT,event_type TEXT NOT NULL CHECK(event_type IN ('dispatch','deliver')),carrier TEXT,tracking_reference TEXT,note TEXT NOT NULL DEFAULT '',actor_staff_id INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
 $db->exec('CREATE INDEX idx_uco_shipping_events_order ON uco_order_shipping_events(order_id,id)');
 if($db->query('PRAGMA foreign_key_check')->fetchAll())throw new RuntimeException('Claves foráneas inconsistentes');
 $db->commit();$integrity=$db->query('PRAGMA integrity_check')->fetchColumn();if($integrity!=='ok')throw new RuntimeException('Integridad: '.$integrity);
 echo "OK: auditoría de expediciones creada. Integridad: ok\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,'ERROR: '.$e->getMessage()."\n");exit(1);}
