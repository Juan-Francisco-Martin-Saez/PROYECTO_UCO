<?php
declare(strict_types=1);
/** UCO Fase 2E: diagnóstico de integridad, estrictamente solo lectura. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Solo CLI');}
$path=dirname(__DIR__).'/database/catalogo.sqlite';
$errors=[];$warnings=[];
function check(string $name,int $count,array &$errors):void{
 echo sprintf("%-56s %d\n",$name,$count);
 if($count>0)$errors[]=$name.' ('.$count.')';
}
try{
 if(!is_file($path))throw new RuntimeException('No se encuentra catalogo.sqlite');
 $db=new PDO('sqlite:file:'.str_replace('\\','/',$path).'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5]);
 $db->exec('PRAGMA foreign_keys=ON');
 $required=['uco_orders','uco_order_lines','uco_simulated_payments','uco_order_fulfillment','uco_order_fulfillment_events','uco_order_fulfillment_resets','admin_users','customers'];
 foreach($required as $table){$q=$db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$q->execute([$table]);if(!$q->fetchColumn())throw new RuntimeException('Falta tabla '.$table.'. ¿Están instaladas las fases 2A y 2C?');}
 echo "UCO Pedidos 2E — auditoría de solo lectura\n";
 foreach(['uco_orders'=>'Pedidos','uco_order_fulfillment'=>'Preparaciones','uco_order_fulfillment_events'=>'Eventos de preparación','uco_order_fulfillment_resets'=>'Reinicios auditados'] as $t=>$label){echo $label.': '.(int)$db->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()."\n";}
 $integrity=(string)$db->query('PRAGMA integrity_check')->fetchColumn();echo 'PRAGMA integrity_check: '.$integrity."\n";if($integrity!=='ok')$errors[]='Integridad SQLite incorrecta';
 $fk=$db->query('PRAGMA foreign_key_check')->fetchAll();check('Claves foráneas incorrectas',count($fk),$errors);
 $queries=[
  'Preparaciones sin pago simulado aprobado'=>"SELECT COUNT(*) FROM uco_order_fulfillment f JOIN uco_orders o ON o.id=f.order_id WHERE NOT (o.status='paid' AND o.payment_status='simulated_approved' AND EXISTS (SELECT 1 FROM uco_simulated_payments p WHERE p.order_id=o.id AND p.state='approved'))",
  'Eventos sin pedido'=>"SELECT COUNT(*) FROM uco_order_fulfillment_events e LEFT JOIN uco_orders o ON o.id=e.order_id WHERE o.id IS NULL",
  'Reinicios sin pedido'=>"SELECT COUNT(*) FROM uco_order_fulfillment_resets r LEFT JOIN uco_orders o ON o.id=r.order_id WHERE o.id IS NULL",
  'Reinicios sin motivo suficiente'=>"SELECT COUNT(*) FROM uco_order_fulfillment_resets WHERE length(trim(reason))<12",
  'Reinicios sin verificación de contraseña'=>"SELECT COUNT(*) FROM uco_order_fulfillment_resets WHERE verification_method IS NULL OR verification_method!='admin_password'",
  'Reinicios sin administrador identificable'=>"SELECT COUNT(*) FROM uco_order_fulfillment_resets r LEFT JOIN admin_users a ON a.id=r.actor_staff_id WHERE a.id IS NULL",
  'Eventos con estados no reconocidos'=>"SELECT COUNT(*) FROM uco_order_fulfillment_events WHERE new_state NOT IN ('pending_preparation','preparing','prepared','dispatched','delivered','incident','cancelled')",
  'Estados operativos no reconocidos'=>"SELECT COUNT(*) FROM uco_order_fulfillment WHERE state NOT IN ('pending_preparation','preparing','prepared','dispatched','delivered','incident','cancelled')",
  'Pedidos sin cliente'=>"SELECT COUNT(*) FROM uco_orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE c.id IS NULL"
 ];
 foreach($queries as $label=>$sql){check($label,(int)$db->query($sql)->fetchColumn(),$errors);}
 $q=$db->query("SELECT f.order_id,f.state, (SELECT e.new_state FROM uco_order_fulfillment_events e WHERE e.order_id=f.order_id ORDER BY e.id DESC LIMIT 1) AS last_state, (SELECT r.id FROM uco_order_fulfillment_resets r WHERE r.order_id=f.order_id ORDER BY r.id DESC LIMIT 1) AS reset_id, (SELECT e.id FROM uco_order_fulfillment_events e WHERE e.order_id=f.order_id ORDER BY e.id DESC LIMIT 1) AS event_id FROM uco_order_fulfillment f");
 $missing=0;$mismatch=0;
 foreach($q as $r){if($r['last_state']===null)$missing++;elseif($r['last_state']!==$r['state'])$mismatch++;}
 check('Preparaciones sin evento de inicio', $missing,$errors);
 if($mismatch){$warnings[]='Revisar '.$mismatch.' pedidos cuyo último evento no coincide con su estado; los reinicios pueden explicar la diferencia';}
 echo 'Resultado: '.($errors?'ERRORES DETECTADOS':'COMPROBACIONES SUPERADAS')."\n";
 foreach($warnings as $warning)echo 'AVISO: '.$warning."\n";
 if($errors){foreach($errors as $error)fwrite(STDERR,'ERROR: '.$error."\n");exit(2);}
 echo "No se ha modificado ningún dato.\n";
}catch(Throwable $e){fwrite(STDERR,'ERROR: '.$e->getMessage()."\n");exit(1);}
