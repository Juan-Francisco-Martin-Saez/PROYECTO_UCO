<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$path=dirname(__DIR__).'/database/catalogo.sqlite';
if(!is_file($path)){fwrite(STDERR,"Base de datos no encontrada\n");exit(3);}
$backup=$path.'.pre-612-'.date('Ymd-His').'.bak';
if(!copy($path,$backup)){fwrite(STDERR,"No se pudo crear copia de seguridad\n");exit(3);}
try{
 $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>10]);
 $db->exec('PRAGMA foreign_keys=ON');
 $db->exec('BEGIN IMMEDIATE');
 // Reinstalar los triggers existentes para cubrir también pagos pendientes.
 $db->exec('DROP TRIGGER IF EXISTS uco_guard_reserved_stock');
 $db->exec('DROP TRIGGER IF EXISTS uco_guard_reserved_delete');
 $db->exec("CREATE TRIGGER uco_guard_reserved_stock BEFORE UPDATE OF stock ON products
 WHEN NEW.stock < COALESCE((SELECT SUM(l.quantity) FROM uco_order_lines l
 JOIN uco_orders o ON o.id=l.order_id WHERE l.product_id=OLD.id
 AND o.status IN ('reserved','pending_payment')
 AND o.expires_at>CAST(strftime('%s','now') AS INTEGER)),0)
 BEGIN SELECT RAISE(ABORT,'STOCK_RESERVADO_UCO'); END");
 $db->exec("CREATE TRIGGER uco_guard_reserved_delete BEFORE DELETE ON products
 WHEN EXISTS(SELECT 1 FROM uco_order_lines l JOIN uco_orders o ON o.id=l.order_id
 WHERE l.product_id=OLD.id AND o.status IN ('reserved','pending_payment')
 AND o.expires_at>CAST(strftime('%s','now') AS INTEGER))
 BEGIN SELECT RAISE(ABORT,'STOCK_RESERVADO_UCO'); END");
 $db->commit();
 echo "Triggers 6.12 actualizados. Respaldo: $backup\n";
}catch(Throwable $e){
 if(isset($db)&&$db->inTransaction())$db->rollBack();
 fwrite(STDERR,"Migración fallida: ".$e->getMessage()."\n");
 exit(3);
}
