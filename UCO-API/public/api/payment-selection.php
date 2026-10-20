<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');
session_name('UCOCLIENT');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);
session_start();
function payReply(array $body,int $status=200):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if(!in_array($method,['GET','POST'],true))payReply(['error'=>'Método no permitido'],405);
 $id=(int)($_SESSION['customer_id']??0);if($id<1)payReply(['error'=>'Inicia sesión'],401);
 if($method==='POST'&&!hash_equals((string)($_SESSION['customer_csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))payReply(['error'=>'Solicitud no válida'],403);
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=10000');$db->exec('PRAGMA foreign_keys=ON');
 $db->exec('BEGIN IMMEDIATE');
 $q=$db->prepare('SELECT password_hash FROM customers WHERE id=? AND active=1');$q->execute([$id]);$user=$q->fetch();
 if(!$user||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',(string)$user['password_hash']))){$db->rollBack();payReply(['error'=>'Sesión caducada'],401);}
 $q=$db->prepare("SELECT id,expires_at,subtotal_cents FROM uco_orders WHERE customer_id=? AND status='reserved' AND expires_at>? ORDER BY id DESC LIMIT 1");$q->execute([$id,time()]);$order=$q->fetch();
 if(!$order){$db->rollBack();payReply(['error'=>'No hay una reserva activa. Comprueba las existencias y reserva de nuevo.'],409);}
 $q=$db->prepare('SELECT contact,phone,address,postal_code,city,province FROM uco_checkout_details WHERE customer_id=?');$q->execute([$id]);$delivery=$q->fetch();
 if(!$delivery||count(array_filter($delivery,static fn($v)=>trim((string)$v)!==''))!==6){$db->rollBack();payReply(['error'=>'Completa los datos de entrega antes de seleccionar el pago.'],409);}
 $q=$db->prepare('SELECT product_id,quantity FROM uco_order_lines WHERE order_id=?');$q->execute([(int)$order['id']]);$lines=$q->fetchAll();
 if(!$lines){$db->rollBack();payReply(['error'=>'La reserva no contiene productos.'],409);}
 $product=$db->prepare('SELECT p.stock,p.active,c.active category_active FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=?');
 $reserved=$db->prepare("SELECT COALESCE(SUM(l.quantity),0) FROM uco_order_lines l JOIN uco_orders o ON o.id=l.order_id WHERE l.product_id=? AND o.status='reserved' AND o.expires_at>?");
 foreach($lines as $line){$product->execute([(int)$line['product_id']]);$p=$product->fetch();$reserved->execute([(int)$line['product_id'],time()]);$held=(int)$reserved->fetchColumn();if(!$p||!(int)$p['active']||!(int)$p['category_active']||(int)$p['stock']<$held){$db->rollBack();payReply(['error'=>'Las existencias han cambiado. Revisa tu reserva antes de continuar.'],409);}}
 $db->exec("CREATE TABLE IF NOT EXISTS uco_order_payment_choices(order_id INTEGER PRIMARY KEY REFERENCES uco_orders(id) ON DELETE CASCADE,method TEXT NOT NULL CHECK(method IN ('redsys','transfer','paypal','bizum')),updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
 $q=$db->prepare('SELECT method FROM uco_order_payment_choices WHERE order_id=?');$q->execute([(int)$order['id']]);$selected=$q->fetchColumn()?:null;
 if($method==='POST'){
  $chosen=(string)($_POST['payment_method']??'');
  if(!in_array($chosen,['redsys','transfer','paypal','bizum'],true)){$db->rollBack();payReply(['error'=>'Método de pago no válido'],422);}
  $q=$db->prepare('INSERT INTO uco_order_payment_choices(order_id,method) VALUES(?,?) ON CONFLICT(order_id) DO UPDATE SET method=excluded.method,updated_at=CURRENT_TIMESTAMP');$q->execute([(int)$order['id'],$chosen]);$selected=$chosen;
 }
 $db->commit();
 payReply(['order_id'=>(int)$order['id'],'reservation_expires_at'=>(int)$order['expires_at'],'subtotal_cents'=>(int)$order['subtotal_cents'],'selected_method'=>$selected,'methods'=>['redsys','transfer','paypal','bizum'],'payment_enabled'=>false,'message'=>'Método guardado. No se ha confirmado ni cobrado el pedido.']);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('UCO payment selection: '.$e->getMessage());payReply(['error'=>'No se ha podido preparar la selección de pago'],500);}
