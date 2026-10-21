<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit('Solo CLI');
$path=dirname(__DIR__).'/database/catalogo.sqlite';
try{
 $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=10000');
 $apply=in_array('--apply',$argv,true);
 echo "UCO Pedidos 2C · Auditoría de reinicios\n";
 if(!$apply){echo "Previsualización: sin cambios. Añade --apply.\n";exit;}
 $source=new SQLite3($path,SQLITE3_OPEN_READONLY);
 $backup=dirname($path).'/catalogo.pre-reset-audit-'.date('Ymd-His').'.sqlite';
 $dest=new SQLite3($backup,SQLITE3_OPEN_READWRITE|SQLITE3_OPEN_CREATE);
 if(!$source->backup($dest))throw new RuntimeException('Respaldo fallido');
 $source->close();$dest->close();echo "Respaldo: $backup\n";
 $db->exec('BEGIN IMMEDIATE');
 $db->exec("CREATE TABLE IF NOT EXISTS uco_order_fulfillment_resets (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 order_id INTEGER NOT NULL REFERENCES uco_orders(id) ON DELETE RESTRICT,
 previous_state TEXT NOT NULL,
 actor_staff_id INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
 reason TEXT NOT NULL CHECK(length(reason)>=12),
 verification_method TEXT NOT NULL DEFAULT 'admin_password',
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
 )");
 $db->exec('CREATE INDEX IF NOT EXISTS idx_uco_order_reset_order ON uco_order_fulfillment_resets(order_id,id)');
 $errors=$db->query('PRAGMA foreign_key_check')->fetchAll();
 if($errors)throw new RuntimeException('Integridad referencial incorrecta');
 $db->commit();
 echo "OK: auditoría de reinicios preparada. Integridad: ".$db->query('PRAGMA integrity_check')->fetchColumn()."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
