<?php
declare(strict_types=1);
/** UCO Fase 2A: estructura aditiva para preparación de pedidos. CLI --apply para ejecutar. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI'); }
$dbPath = dirname(__DIR__).'/database/catalogo.sqlite';
$apply = in_array('--apply',$argv,true);
try {
 if (!is_file($dbPath)) throw new RuntimeException('No existe SQLite: '.$dbPath);
 $db = new PDO('sqlite:'.$dbPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec('PRAGMA busy_timeout=10000');
 $db->exec('PRAGMA foreign_keys=ON');
 foreach (['uco_orders','uco_simulated_payments','admin_users'] as $table) {
  $q=$db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
  $q->execute([$table]); if (!$q->fetchColumn()) throw new RuntimeException('Falta tabla '.$table);
 }
 $orders=(int)$db->query('SELECT COUNT(*) FROM uco_orders')->fetchColumn();
 $approved=(int)$db->query("SELECT COUNT(*) FROM uco_orders o JOIN uco_simulated_payments p ON p.order_id=o.id WHERE o.status='paid' AND o.payment_status='simulated_approved' AND p.state='approved'")->fetchColumn();
 echo "UCO Pedidos 2A | pedidos=$orders | pagos simulados aprobados=$approved\n";
 if (!$apply) { echo "PREVISUALIZACIÓN: sin cambios. Ejecuta con --apply para aplicar.\n"; exit(0); }
 $backup=dirname($dbPath).'/catalogo.pre-pedidos-2a-'.date('Ymd-His').'.sqlite';
 $source=new SQLite3($dbPath,SQLITE3_OPEN_READONLY);
 $dest=new SQLite3($backup,SQLITE3_OPEN_READWRITE|SQLITE3_OPEN_CREATE);
 if (!$source->backup($dest)) throw new RuntimeException('No se pudo completar el respaldo');
 $source->close();$dest->close();
 echo "Respaldo: $backup\n";
 $db->exec('BEGIN IMMEDIATE');
 $db->exec("CREATE TABLE IF NOT EXISTS uco_order_fulfillment (
   order_id INTEGER PRIMARY KEY REFERENCES uco_orders(id) ON DELETE CASCADE,
   state TEXT NOT NULL DEFAULT 'pending_preparation' CHECK(state IN ('pending_preparation','preparing','prepared','dispatched','delivered','incident','cancelled')),
   assigned_staff_id INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
   updated_by_staff_id INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
   created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
 )");
 $db->exec("CREATE TABLE IF NOT EXISTS uco_order_fulfillment_events (
   id INTEGER PRIMARY KEY AUTOINCREMENT,
   order_id INTEGER NOT NULL REFERENCES uco_orders(id) ON DELETE CASCADE,
   previous_state TEXT,
   new_state TEXT NOT NULL CHECK(new_state IN ('pending_preparation','preparing','prepared','dispatched','delivered','incident','cancelled')),
   actor_staff_id INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
   note TEXT NOT NULL DEFAULT '',
   created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
 )");
 $db->exec('CREATE INDEX IF NOT EXISTS idx_uco_fulfillment_state ON uco_order_fulfillment(state,updated_at)');
 $db->exec('CREATE INDEX IF NOT EXISTS idx_uco_fulfillment_events_order ON uco_order_fulfillment_events(order_id,id)');
 // No activamos estados ni añadimos pedidos antiguos a la cola en esta fase.
 // La fase 2B decidirá cómo incorporar pedidos históricos con pago verificado.
 $fk=$db->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_NUM);
 if ($fk) throw new RuntimeException('Se han detectado errores de claves foráneas');
 $db->exec('COMMIT');
 echo "OK: tablas operativas preparadas; pedidos existentes intactos; registros operativos nuevos=0\n";
 echo "PRAGMA integrity_check: ".$db->query('PRAGMA integrity_check')->fetchColumn()."\n";
} catch(Throwable $e) {
 if(isset($db)&&$db->inTransaction()) $db->rollBack();
 fwrite(STDERR,'ERROR: '.$e->getMessage()."\n");exit(1);
}
