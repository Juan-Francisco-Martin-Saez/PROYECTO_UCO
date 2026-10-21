<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');session_name('UCOCLIENT');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);session_start();
require_once __DIR__.'/commercial.php';
function orderReply(array $data,int $code=200):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function orderSchema(PDO $db):void{
 $db->exec("CREATE TABLE IF NOT EXISTS uco_orders(id INTEGER PRIMARY KEY AUTOINCREMENT,customer_id INTEGER NOT NULL REFERENCES customers(id),status TEXT NOT NULL CHECK(status IN ('reserved','expired','cancelled','pending_payment','paid','processing','shipped','completed')),payment_status TEXT NOT NULL DEFAULT 'not_started',subtotal_cents INTEGER NOT NULL CHECK(subtotal_cents>=0),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,expires_at INTEGER NOT NULL,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
 $db->exec('CREATE TABLE IF NOT EXISTS uco_order_lines(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL REFERENCES uco_orders(id) ON DELETE CASCADE,product_id INTEGER NOT NULL REFERENCES products(id),sku TEXT NOT NULL,name TEXT NOT NULL,unit TEXT NOT NULL,quantity INTEGER NOT NULL CHECK(quantity>0),unit_price_cents INTEGER NOT NULL CHECK(unit_price_cents>=0),line_cents INTEGER NOT NULL CHECK(line_cents>=0))');
 $db->exec('CREATE INDEX IF NOT EXISTS idx_uco_orders_customer ON uco_orders(customer_id,status)');
 $db->exec('CREATE INDEX IF NOT EXISTS idx_uco_order_lines_product ON uco_order_lines(product_id,order_id)');
 // Protege la actualización administrativa de stock incluso si se usa otra API.
 $db->exec("CREATE TRIGGER IF NOT EXISTS uco_guard_reserved_stock BEFORE UPDATE OF stock ON products WHEN NEW.stock < COALESCE((SELECT SUM(l.quantity) FROM uco_order_lines l JOIN uco_orders o ON o.id=l.order_id WHERE l.product_id=OLD.id AND o.status='reserved' AND o.expires_at>CAST(strftime('%s','now') AS INTEGER)),0) BEGIN SELECT RAISE(ABORT,'STOCK_RESERVADO_UCO'); END");
 $db->exec("CREATE TRIGGER IF NOT EXISTS uco_guard_reserved_delete BEFORE DELETE ON products WHEN EXISTS(SELECT 1 FROM uco_order_lines l JOIN uco_orders o ON o.id=l.order_id WHERE l.product_id=OLD.id AND o.status='reserved' AND o.expires_at>CAST(strftime('%s','now') AS INTEGER)) BEGIN SELECT RAISE(ABORT,'STOCK_RESERVADO_UCO'); END");

}
function orderExpire(PDO $db):void{$db->prepare("UPDATE uco_orders SET status='expired',updated_at=CURRENT_TIMESTAMP WHERE status='reserved' AND expires_at<=?")->execute([time()]);}
function orderCart(PDO $db,int $customerId,int $groupId):array{
 $q=$db->prepare('SELECT ci.product_id,ci.quantity,p.sku,p.name,p.unit,p.stock,p.active,c.active category_active FROM shopping_cart_items ci JOIN products p ON p.id=ci.product_id JOIN categories c ON c.id=p.category_id WHERE ci.customer_id=? ORDER BY ci.product_id');$q->execute([$customerId]);$lines=[];$issues=[];$subtotal=0;
 $reserved=$db->prepare("SELECT COALESCE(SUM(l.quantity),0) FROM uco_order_lines l JOIN uco_orders o ON o.id=l.order_id WHERE l.product_id=? AND o.status='reserved' AND o.expires_at>?");
 foreach($q->fetchAll() as $p){$pid=(int)$p['product_id'];$qty=(int)$p['quantity'];$reserved->execute([$pid,time()]);$free=max(0,(int)$p['stock']-(int)$reserved->fetchColumn());$price=commercialPrice($db,$pid,$customerId,$groupId?:null);$cents=$price['price_cents'];$available=(bool)$p['active']&&(bool)$p['category_active']?$free:0;
  if($available<$qty||$cents===null)$issues[]=['product_id'=>$pid,'name'=>$p['name'],'requested'=>$qty,'available'=>$available,'reason'=>$cents===null?'sin_precio':'sin_stock'];
  $line=['product_id'=>$pid,'sku'=>$p['sku'],'name'=>$p['name'],'unit'=>$p['unit'],'quantity'=>$qty,'available'=>$available,'unit_price_cents'=>$cents,'line_cents'=>$cents===null?null:$qty*$cents];$lines[]=$line;if($cents!==null)$subtotal+=$qty*$cents;
 }
 return ['items'=>$lines,'issues'=>$issues,'subtotal_cents'=>$subtotal,'valid'=>count($lines)>0&&!$issues];
}
try{
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=10000');commercialDb($db);
 $id=(int)($_SESSION['customer_id']??0);if(!$id)orderReply(['error'=>'Inicia sesión'],401);$q=$db->prepare('SELECT password_hash,group_id FROM customers WHERE id=? AND active=1');$q->execute([$id]);$user=$q->fetch();if(!$user||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',$user['password_hash'])))orderReply(['error'=>'Sesión caducada'],401);
 $action=(string)($_GET['action']??'preview');$method=$_SERVER['REQUEST_METHOD'];if(!in_array($action,['preview','reserve','cancel','status','history','methods'],true))orderReply(['error'=>'Operación desconocida'],404);
 if(in_array($action,['reserve','cancel'],true)&&($method!=='POST'||!hash_equals((string)($_SESSION['customer_csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??''))))orderReply(['error'=>'Solicitud no válida'],403);
 if(in_array($action,['preview','status','history','methods'],true)&&$method!=='GET')orderReply(['error'=>'Método no permitido'],405);
 $db->exec('BEGIN IMMEDIATE');try{
  orderSchema($db);orderExpire($db);
  if($action==='methods'){$db->commit();orderReply(['methods'=>[['id'=>'redsys','name'=>'Tarjeta bancaria (TPV multibanco)','enabled'=>false],['id'=>'transfer','name'=>'Transferencia bancaria','enabled'=>false],['id'=>'paypal','name'=>'PayPal','enabled'=>false],['id'=>'bizum','name'=>'Bizum','enabled'=>false]],'notice'=>'Las pasarelas todavía no están activadas; no se aceptan pagos.']);}
  if($action==='preview'){$result=orderCart($db,$id,(int)($user['group_id']??0));$db->commit();orderReply($result);}
  if($action==='history'){
   $q=$db->prepare('SELECT o.id,o.status,o.payment_status,o.subtotal_cents,o.created_at,f.state AS fulfillment_state,f.updated_at AS fulfillment_updated_at FROM uco_orders o LEFT JOIN uco_order_fulfillment f ON f.order_id=o.id WHERE o.customer_id=? ORDER BY o.id DESC LIMIT 100');
   $q->execute([$id]);$rows=$q->fetchAll();
   $lines=$db->prepare('SELECT name,sku,unit,quantity,line_cents FROM uco_order_lines WHERE order_id=? ORDER BY id');
   foreach($rows as &$row){$lines->execute([(int)$row['id']]);$row['items']=$lines->fetchAll();}unset($row);
   $db->commit();orderReply(['orders'=>$rows]);
  }
  if($action==='status'){$q=$db->prepare('SELECT id,status,payment_status,subtotal_cents,expires_at FROM uco_orders WHERE customer_id=? ORDER BY id DESC LIMIT 20');$q->execute([$id]);$rows=$q->fetchAll();$db->commit();orderReply(['orders'=>$rows]);}
  if($action==='cancel'){$oid=filter_var($_POST['order_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$oid){$db->rollBack();orderReply(['error'=>'Pedido inválido'],422);}$q=$db->prepare("UPDATE uco_orders SET status='cancelled',updated_at=CURRENT_TIMESTAMP WHERE id=? AND customer_id=? AND status='reserved'");$q->execute([$oid,$id]);$changed=$q->rowCount();$db->commit();orderReply(['cancelled'=>(bool)$changed]);}
  // La reserva es interna: no habilita ventas ni inicia pagos hasta la siguiente fase.
  $existing=$db->prepare("SELECT id FROM uco_orders WHERE customer_id=? AND status='reserved'");$existing->execute([$id]);if($existing->fetchColumn()){$db->commit();orderReply(['error'=>'Ya existe una reserva activa; cancélala o espera a su caducidad'],409);}
  $result=orderCart($db,$id,(int)($user['group_id']??0));if(!$result['valid']){$db->commit();orderReply(['error'=>'Hay productos sin existencias o sin precio. Revisa el carrito.']+$result,409);}
  $expires=time()+600;$q=$db->prepare("INSERT INTO uco_orders(customer_id,status,payment_status,subtotal_cents,expires_at) VALUES(?,'reserved','not_started',?,?)");$q->execute([$id,$result['subtotal_cents'],$expires]);$oid=(int)$db->lastInsertId();$q=$db->prepare('INSERT INTO uco_order_lines(order_id,product_id,sku,name,unit,quantity,unit_price_cents,line_cents) VALUES(?,?,?,?,?,?,?,?)');foreach($result['items'] as $line)$q->execute([$oid,$line['product_id'],$line['sku'],$line['name'],$line['unit'],$line['quantity'],$line['unit_price_cents'],$line['line_cents']]);$db->commit();orderReply(['order_id'=>$oid,'status'=>'reserved','expires_at'=>$expires,'subtotal_cents'=>$result['subtotal_cents'],'message'=>'Reserva temporal creada durante 10 minutos. El pago todavía no está habilitado.']);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}catch(Throwable $e){error_log('UCO pedidos: '.$e->getMessage());orderReply(['error'=>'No se pudo procesar el pedido'],500);}
