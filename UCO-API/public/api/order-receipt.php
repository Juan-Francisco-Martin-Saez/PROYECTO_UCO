<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');session_name('UCOCLIENT');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);session_start();
function receiptReply(array $v,int $status=200):never{http_response_code($status);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
try{
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')receiptReply(['error'=>'Método no permitido'],405);
 $cid=(int)($_SESSION['customer_id']??0);if(!$cid)receiptReply(['error'=>'Inicia sesión'],401);
 $oid=filter_var($_GET['order_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$oid)receiptReply(['error'=>'Pedido no válido'],422);
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=10000');
 $s=$db->prepare('SELECT password_hash FROM customers WHERE id=? AND active=1');$s->execute([$cid]);$customer=$s->fetch();
 if(!$customer||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',(string)$customer['password_hash'])))receiptReply(['error'=>'Sesión caducada'],401);
 $s=$db->prepare('SELECT o.id,o.status,o.payment_status,o.created_at,p.method,p.state,p.reference,p.subtotal_cents,p.tax_cents,p.shipping_cents,p.total_cents,p.delivery_json,p.tax_json,p.updated_at AS operation_at FROM uco_orders o JOIN uco_simulated_payments p ON p.order_id=o.id WHERE o.id=? AND o.customer_id=? AND p.customer_id=?');$s->execute([$oid,$cid,$cid]);$order=$s->fetch();
 if(!$order)receiptReply(['error'=>'Operación no encontrada'],404);
 if($order['state']!=='approved'||$order['payment_status']!=='simulated_approved')receiptReply(['error'=>'El justificante solo está disponible tras la aprobación simulada'],409);
 $s=$db->prepare('SELECT l.product_id,l.sku,l.name,l.unit,l.quantity,l.unit_price_cents,l.line_cents,p.image FROM uco_order_lines l LEFT JOIN products p ON p.id=l.product_id WHERE l.order_id=? ORDER BY l.id');$s->execute([$oid]);$items=$s->fetchAll();
 if(!$items)receiptReply(['error'=>'El pedido no tiene líneas'],409);
 foreach($items as &$item){$src=(string)($item['image']??'');$item['image']=preg_match('~^\./img/(?:productos/[a-f0-9]+|[0-9]{2})\.webp$~',$src)?'../'.substr($src,2):null;}unset($item);
 $delivery=json_decode($order['delivery_json'],true);$tax=json_decode($order['tax_json'],true);
 receiptReply(['simulation'=>true,'order_id'=>(int)$order['id'],'status'=>$order['status'],'method'=>$order['method'],'state'=>$order['state'],'reference'=>$order['reference'],'created_at'=>$order['created_at'],'operation_at'=>$order['operation_at'],'delivery'=>is_array($delivery)?$delivery:[],'tax_lines'=>is_array($tax)?$tax:[],'items'=>$items,'subtotal_cents'=>(int)$order['subtotal_cents'],'tax_cents'=>(int)$order['tax_cents'],'shipping_cents'=>(int)$order['shipping_cents'],'total_cents'=>(int)$order['total_cents']]);
}catch(Throwable $e){error_log('UCO receipt: '.$e->getMessage());receiptReply(['error'=>'No se pudo consultar el justificante'],500);}
