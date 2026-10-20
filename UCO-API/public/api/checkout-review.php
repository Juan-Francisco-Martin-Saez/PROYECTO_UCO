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
function reviewReply(array $data,int $code=200):never{
 http_response_code($code);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
try{
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')reviewReply(['error'=>'Método no permitido'],405);
 $id=(int)($_SESSION['customer_id']??0);
 if($id<1)reviewReply(['error'=>'Inicia sesión'],401);
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=10000');
 $db->exec('BEGIN IMMEDIATE');
 try{
  $s=$db->prepare('SELECT password_hash FROM customers WHERE id=? AND active=1');$s->execute([$id]);$customer=$s->fetch();
  if(!$customer||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',(string)$customer['password_hash']))){$db->rollBack();reviewReply(['error'=>'Sesión caducada'],401);}
  // Solo se revisan reservas válidas del cliente autenticado. No se inicia cobro.
  $s=$db->prepare("SELECT id,subtotal_cents,expires_at,status,payment_status FROM uco_orders WHERE customer_id=? AND status='reserved' AND expires_at>? ORDER BY id DESC LIMIT 1");
  $s->execute([$id,time()]);$order=$s->fetch();
  if(!$order){$db->commit();reviewReply(['error'=>'No hay una reserva activa. Comprueba de nuevo las existencias desde el carrito.','code'=>'RESERVATION_REQUIRED'],409);}
  $s=$db->prepare('SELECT product_id,sku,name,unit,quantity,unit_price_cents,line_cents FROM uco_order_lines WHERE order_id=? ORDER BY id');$s->execute([(int)$order['id']]);$lines=$s->fetchAll();
  if(!$lines){$db->commit();reviewReply(['error'=>'La reserva no contiene productos','code'=>'EMPTY_RESERVATION'],409);}
  $subtotal=0;$issues=[];
  $stock=$db->prepare('SELECT stock,active,category_id FROM products WHERE id=?');
  $category=$db->prepare('SELECT active FROM categories WHERE id=?');
  $reserved=$db->prepare("SELECT COALESCE(SUM(l.quantity),0) FROM uco_order_lines l JOIN uco_orders o ON o.id=l.order_id WHERE l.product_id=? AND o.status='reserved' AND o.expires_at>?");
  foreach($lines as &$line){
   $pid=(int)$line['product_id'];$qty=(int)$line['quantity'];$subtotal+=(int)$line['line_cents'];
   $stock->execute([$pid]);$p=$stock->fetch();
   if(!$p){$issues[]=['product_id'=>$pid,'name'=>$line['name'],'reason'=>'producto_no_disponible'];continue;}
   $category->execute([(int)$p['category_id']]);$catActive=(bool)$category->fetchColumn();
   $reserved->execute([$pid,time()]);$held=(int)$reserved->fetchColumn();
   // La reserva del propio pedido está incluida en el total reservado.
   if(!(bool)$p['active']||!$catActive||(int)$p['stock']<$held){
    $issues[]=['product_id'=>$pid,'name'=>$line['name'],'reason'=>'sin_stock','requested'=>$qty,'stock'=>(int)$p['stock'],'reserved'=>$held];
   }
   $line['product_id']=$pid;$line['quantity']=$qty;$line['unit_price_cents']=(int)$line['unit_price_cents'];$line['line_cents']=(int)$line['line_cents'];
  }unset($line);
  if($subtotal!==(int)$order['subtotal_cents'])$issues[]=['reason'=>'subtotal_inconsistente'];
  $details=null;
  // Compatibilidad con instalaciones que todavía no hayan utilizado el formulario de entrega.
  $exists=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='uco_checkout_details'")->fetchColumn();
  if($exists){$s=$db->prepare('SELECT contact,phone,address,postal_code,city,province,notes FROM uco_checkout_details WHERE customer_id=?');$s->execute([$id]);$details=$s->fetch()?:null;}
  $deliveryComplete=$details!==null;
  foreach(['contact','phone','address','postal_code','city','province'] as $key){if($details===null||trim((string)($details[$key]??''))==='')$deliveryComplete=false;}
  $db->commit();
  reviewReply([
   'order_id'=>(int)$order['id'],'reservation_expires_at'=>(int)$order['expires_at'],
   'items'=>$lines,'subtotal_cents'=>$subtotal,'delivery'=>$details,'delivery_complete'=>$deliveryComplete,
   'stock_valid'=>!$issues,'issues'=>$issues,
   'tax_cents'=>null,'shipping_cents'=>null,'total_cents'=>null,
   'can_pay'=>false,'payment_methods'=>['redsys','transfer','paypal','bizum'],
   'notice'=>'Revisión previa: impuestos y transporte pendientes de configurar. Ningún pedido ha sido confirmado ni cobrado.'
  ]);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}catch(Throwable $e){error_log('UCO checkout-review: '.$e->getMessage());reviewReply(['error'=>'No se pudo revisar el pedido'],500);}
