<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store,private');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');session_name('UCOCLIENT');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);session_start();
require_once __DIR__.'/commercial.php';
require_once dirname(__DIR__,2).'/private/economy.php';
function simReply(array $v,int $code=200):never{http_response_code($code);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')simReply(['error'=>'Método no permitido'],405);
 if(empty($_SESSION['customer_csrf'])||!hash_equals((string)$_SESSION['customer_csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))simReply(['error'=>'Solicitud no válida'],403);
 $cid=(int)($_SESSION['customer_id']??0);if(!$cid)simReply(['error'=>'Inicia sesión'],401);
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=10000');commercialDb($db);
 $s=$db->prepare('SELECT password_hash FROM customers WHERE id=? AND active=1');$s->execute([$cid]);$user=$s->fetch();
 if(!$user||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',$user['password_hash'])))simReply(['error'=>'Sesión caducada'],401);
 $oid=filter_var($_POST['order_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $action=(string)($_POST['action']??'');$method=(string)($_POST['method']??'');
 if(!$oid||!in_array($action,['start','approve','reject','cancel'],true))simReply(['error'=>'Solicitud inválida'],422);
 $db->exec('BEGIN IMMEDIATE');
 try{
  $db->exec("CREATE TABLE IF NOT EXISTS uco_simulated_payments(order_id INTEGER PRIMARY KEY REFERENCES uco_orders(id),customer_id INTEGER NOT NULL,method TEXT NOT NULL CHECK(method IN ('redsys','paypal','bizum','transfer')),state TEXT NOT NULL CHECK(state IN ('pending','approved','rejected','cancelled')),subtotal_cents INTEGER NOT NULL,tax_cents INTEGER NOT NULL,shipping_cents INTEGER NOT NULL,total_cents INTEGER NOT NULL,delivery_json TEXT NOT NULL,tax_json TEXT NOT NULL,reference TEXT NOT NULL UNIQUE,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
  $s=$db->prepare('SELECT id,status,expires_at,subtotal_cents FROM uco_orders WHERE id=? AND customer_id=?');$s->execute([$oid,$cid]);$order=$s->fetch();
  if(!$order){$db->rollBack();simReply(['error'=>'Pedido no encontrado'],404);}
  $s=$db->prepare('SELECT * FROM uco_simulated_payments WHERE order_id=? AND customer_id=?');$s->execute([$oid,$cid]);$payment=$s->fetch();
  if($action==='start'){
   if($payment){$db->commit();simReply(['state'=>$payment['state'],'reference'=>$payment['reference'],'method'=>$payment['method'],'total_cents'=>(int)$payment['total_cents'],'already_started'=>true,'simulation'=>true]);}
   if(!in_array($method,['redsys','paypal','bizum','transfer'],true)){$db->rollBack();simReply(['error'=>'Método inválido'],422);}
   if($order['status']!=='reserved'||(int)$order['expires_at']<=time()){$db->rollBack();simReply(['error'=>'La reserva ha caducado'],409);}
   $s=$db->prepare('SELECT product_id,name,quantity,line_cents FROM uco_order_lines WHERE order_id=?');$s->execute([$oid]);$items=$s->fetchAll();
   if(!$items){$db->rollBack();simReply(['error'=>'Pedido sin productos'],409);}
   $s=$db->prepare('SELECT contact,phone,address,postal_code,city,province,notes FROM uco_checkout_details WHERE customer_id=?');$s->execute([$cid]);$delivery=$s->fetch()?:null;
   $quote=ucoEconomyQuote($db,$items,$delivery);
   if(!$quote['ready']||(int)$quote['subtotal_cents']!==(int)$order['subtotal_cents']){$db->rollBack();simReply(['error'=>'Revisa el importe y los datos de entrega antes de continuar','issues'=>$quote['issues']],409);}
   // Validación del stock: una reserva pendiente no puede competir con otra.
   $s=$db->prepare("SELECT p.stock,COALESCE((SELECT SUM(l2.quantity) FROM uco_order_lines l2 JOIN uco_orders o2 ON o2.id=l2.order_id WHERE l2.product_id=l.product_id AND o2.id<>? AND o2.status='reserved' AND o2.expires_at>?),0) AS others,l.quantity FROM uco_order_lines l JOIN products p ON p.id=l.product_id WHERE l.order_id=?");
   $s->execute([$oid,time(),$oid]);
   foreach($s->fetchAll() as $v)if((int)$v['stock']-(int)$v['others']<(int)$v['quantity']){$db->rollBack();simReply(['error'=>'Existencias insuficientes'],409);}
   $reference='SIM-'.strtoupper(bin2hex(random_bytes(8)));
   $s=$db->prepare("INSERT INTO uco_simulated_payments(order_id,customer_id,method,state,subtotal_cents,tax_cents,shipping_cents,total_cents,delivery_json,tax_json,reference) VALUES(?,?,?,'pending',?,?,?,?,?,?,?)");
   $s->execute([$oid,$cid,$method,$quote['subtotal_cents'],$quote['tax_cents'],$quote['shipping_cents'],$quote['total_cents'],json_encode($delivery,JSON_UNESCAPED_UNICODE),json_encode($quote['tax_lines'],JSON_UNESCAPED_UNICODE),$reference]);
   // Pedido pendiente mantiene la reserva protegida: ampliar plazo de espera de prueba.
   // Los pedidos pending_payment también bloquean existencias durante la simulación.
   $db->prepare("UPDATE uco_orders SET status='pending_payment',payment_status='pending',expires_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([time()+1800,$oid]);
   $db->commit();simReply(['state'=>'pending','reference'=>$reference,'method'=>$method,'total_cents'=>$quote['total_cents'],'simulation'=>true]);
  }
  if(!$payment){$db->rollBack();simReply(['error'=>'Inicia antes el pago simulado'],409);}
  if($payment['state']!=='pending'){$db->commit();simReply(['state'=>$payment['state'],'reference'=>$payment['reference'],'total_cents'=>(int)$payment['total_cents'],'simulation'=>true]);}
  if($order['status']!=='pending_payment'){$db->rollBack();simReply(['error'=>'El pedido no está pendiente de pago'],409);}
  if($action==='approve' && (int)$order['expires_at']<=time()){$db->rollBack();simReply(['error'=>'La operación ha caducado; no se puede aprobar'],409);}
  if($action==='approve'){
   // Stock se descuenta exactamente una vez, en la misma transacción que la aprobación.
   $s=$db->prepare("SELECT l.product_id,l.quantity,p.stock,(SELECT COALESCE(SUM(l2.quantity),0) FROM uco_order_lines l2 JOIN uco_orders o2 ON o2.id=l2.order_id WHERE l2.product_id=l.product_id AND o2.id<>l.order_id AND o2.status='reserved' AND o2.expires_at>CAST(strftime('%s','now') AS INTEGER)) AS others FROM uco_order_lines l JOIN products p ON p.id=l.product_id WHERE l.order_id=?");$s->execute([$oid]);$lines=$s->fetchAll();
   foreach($lines as $v)if((int)$v['stock']-(int)$v['others']<(int)$v['quantity']){$db->rollBack();simReply(['error'=>'Stock insuficiente para aprobar el pago simulado'],409);}
   // El trigger de reservas puede bloquear decrementos: primero cambiar estado en la transacción.
   $change=$db->prepare("UPDATE uco_orders SET status='paid',payment_status='simulated_approved',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending_payment'");$change->execute([$oid]);if($change->rowCount()!==1)throw new RuntimeException('Estado de pedido no válido');
   $s=$db->prepare('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?');
   foreach($lines as $v){$s->execute([(int)$v['quantity'],(int)$v['product_id'],(int)$v['quantity']]);if($s->rowCount()!==1)throw new RuntimeException('No se pudo consolidar stock');}
   $db->prepare("DELETE FROM shopping_cart_items WHERE customer_id=?")->execute([$cid]);
   $state='approved';
  }else{
   $state=$action==='reject'?'rejected':'cancelled';
   $db->prepare("UPDATE uco_orders SET status='cancelled',payment_status=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending_payment'")->execute(['simulated_'.$state,$oid]);
  }
  $db->prepare("UPDATE uco_simulated_payments SET state=?,updated_at=CURRENT_TIMESTAMP WHERE order_id=? AND state='pending'")->execute([$state,$oid]);
  $db->commit();simReply(['state'=>$state,'reference'=>$payment['reference'],'total_cents'=>(int)$payment['total_cents'],'simulation'=>true]);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}catch(Throwable $e){error_log('UCO pago simulado: '.$e->getMessage());simReply(['error'=>'No se pudo completar la simulación'],500);}
