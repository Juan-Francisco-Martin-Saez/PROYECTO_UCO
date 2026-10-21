<?php
declare(strict_types=1);
/* Fase 5A: revisión económica de una reserva, sin confirmar ni cobrar. */
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');
session_name('UCOCLIENT');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);
session_start();
require_once __DIR__.'/commercial.php';
require_once dirname(__DIR__,2).'/private/economy.php';
function reviewReply(array $data,int $code=200):never{
 http_response_code($code);
 echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
try{
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')reviewReply(['error'=>'Método no permitido'],405);
 $cid=(int)($_SESSION['customer_id']??0);
 if($cid<1)reviewReply(['error'=>'Inicia sesión'],401);
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
 ]);
 $db->exec('PRAGMA foreign_keys=ON');
 $db->exec('PRAGMA busy_timeout=10000');
 commercialDb($db);
 $s=$db->prepare('SELECT password_hash,group_id FROM customers WHERE id=? AND active=1');
 $s->execute([$cid]);$customer=$s->fetch();
 if(!$customer||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',(string)$customer['password_hash'])))
  reviewReply(['error'=>'Sesión caducada'],401);
 $oid=filter_var($_GET['order_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$oid)reviewReply(['error'=>'Reserva inválida'],422);
 $s=$db->prepare('SELECT id,status,expires_at,subtotal_cents FROM uco_orders WHERE id=? AND customer_id=?');
 $s->execute([$oid,$cid]);$order=$s->fetch();
 if(!$order)reviewReply(['error'=>'Reserva no encontrada'],404);
 if($order['status']!=='reserved'||(int)$order['expires_at']<=time())
  reviewReply(['error'=>'La reserva ya no está vigente','status'=>$order['status']],409);
 $s=$db->prepare('SELECT l.product_id,l.sku,l.name,l.unit,l.quantity,l.unit_price_cents,l.line_cents,p.active,c.active AS category_active,p.stock,p.image FROM uco_order_lines l JOIN products p ON p.id=l.product_id JOIN categories c ON c.id=p.category_id WHERE l.order_id=? ORDER BY l.id');
 $s->execute([$oid]);$lines=$s->fetchAll();
 if(!$lines)reviewReply(['error'=>'La reserva no tiene productos'],409);
 $s=$db->prepare('SELECT contact,phone,address,postal_code,city,province,notes FROM uco_checkout_details WHERE customer_id=?');
 try{$s->execute([$cid]);$delivery=$s->fetch()?:null;}catch(PDOException $e){$delivery=null;}
 $issues=[];
 $sum=0;
 foreach($lines as &$line){
  $line['product_id']=(int)$line['product_id'];$line['quantity']=(int)$line['quantity'];
  $line['unit_price_cents']=(int)$line['unit_price_cents'];$line['line_cents']=(int)$line['line_cents'];
  $sum+=$line['line_cents'];
  if(!$line['active']||!$line['category_active'])$issues[]='Hay productos que ya no están activos';
  unset($line['stock'],$line['active'],$line['category_active']);
 }
 unset($line);
 if($sum!==(int)$order['subtotal_cents'])$issues[]='El subtotal de la reserva no coincide con sus líneas';
 $quote=ucoEconomyQuote($db,$lines,$delivery);
 $issues=array_values(array_unique(array_merge($issues,$quote['issues'])));
 // Evita presentar un importe como definitivo si falta algún dato.
 $ready=!$issues&&$quote['ready']&&$quote['subtotal_cents']===(int)$order['subtotal_cents'];
 if(!$ready){$quote['total_cents']=null;$quote['tax_cents']=null;$quote['shipping_cents']=null;}
 reviewReply([
  'order_id'=>(int)$order['id'],'status'=>'reserved','expires_at'=>(int)$order['expires_at'],
  'delivery'=>$delivery,'items'=>$lines,'economy'=>$quote,'ready'=>$ready,
  'issues'=>$issues,
  'simulation'=>true,
  'notice'=>'Revisión de importes para pruebas. No confirma el pedido, no congela los impuestos y no realiza cobros.'
 ]);
}catch(Throwable $e){
 error_log('UCO review fase5A: '.$e->getMessage());
 reviewReply(['error'=>'No se pudo revisar el pedido'],500);
}
