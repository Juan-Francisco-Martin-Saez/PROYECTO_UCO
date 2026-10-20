<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');session_name('UCOCLIENT');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);session_start();
require_once __DIR__.'/commercial.php';
function respond(array $v,int $status=200):never{http_response_code($status);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=5000');commercialDb($db);
 $id=(int)($_SESSION['customer_id']??0);if(!$id)respond(['error'=>'Inicia sesión'],401);
 $q=$db->prepare('SELECT id,password_hash,group_id FROM customers WHERE id=? AND active=1');$q->execute([$id]);$user=$q->fetch();if(!$user||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',$user['password_hash'])))respond(['error'=>'Sesión caducada'],401);
 $db->exec('CREATE TABLE IF NOT EXISTS shopping_cart_items(customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,quantity INTEGER NOT NULL CHECK(quantity BETWEEN 1 AND 99999),updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(customer_id,product_id))');
 $action=(string)($_GET['action']??'list');$method=$_SERVER['REQUEST_METHOD'];
 if($method==='POST'){
  if(!hash_equals((string)($_SESSION['customer_csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))respond(['error'=>'Solicitud no válida'],403);
  $product=filter_var($_POST['product_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$qty=filter_var($_POST['quantity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>99999]]);
  if($action!=='set'||$product===false||$product===null||$qty===false||$qty===null)respond(['error'=>'Datos del carrito no válidos'],422);
  $db->exec('BEGIN IMMEDIATE');
  try{
   if($qty>0){$q=$db->prepare('SELECT stock FROM products WHERE id=? AND active=1');$q->execute([$product]);$stock=$q->fetchColumn();if($stock===false){$db->rollBack();respond(['error'=>'Producto no disponible'],409);}if($qty>(int)$stock){$db->rollBack();respond(['error'=>'No hay existencias suficientes','available'=>(int)$stock],409);} $price=commercialPrice($db,(int)$product,$id,$user['group_id']===null?null:(int)$user['group_id']);if($price['price_cents']===null){$db->rollBack();respond(['error'=>'Este producto no tiene precio de venta'],409);}
    $q=$db->prepare('INSERT INTO shopping_cart_items(customer_id,product_id,quantity) VALUES(?,?,?) ON CONFLICT(customer_id,product_id) DO UPDATE SET quantity=excluded.quantity,updated_at=CURRENT_TIMESTAMP');$q->execute([$id,$product,$qty]);
   }else{
    $q=$db->prepare('DELETE FROM shopping_cart_items WHERE customer_id=? AND product_id=?');$q->execute([$id,$product]);
    $q=$db->prepare('SELECT 1 FROM shopping_cart_items WHERE customer_id=? LIMIT 1');$q->execute([$id]);
    if(!$q->fetchColumn()){
     $exists=$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='uco_orders'")->fetchColumn();
     if($exists){$q=$db->prepare("UPDATE uco_orders SET status='cancelled',updated_at=CURRENT_TIMESTAMP WHERE customer_id=? AND status='reserved'");$q->execute([$id]);}
    }
   }
   $db->commit();
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }elseif($method!=='GET'||$action!=='list')respond(['error'=>'Operación no permitida'],405);
 $q=$db->prepare('SELECT ci.product_id,ci.quantity,p.sku,p.name,p.unit,p.image,p.stock,p.active,c.active AS category_active FROM shopping_cart_items ci JOIN products p ON p.id=ci.product_id JOIN categories c ON c.id=p.category_id WHERE ci.customer_id=? ORDER BY ci.updated_at DESC,ci.product_id');$q->execute([$id]);$items=[];$total=0;$valid=true;
 foreach($q->fetchAll() as $item){$price=commercialPrice($db,(int)$item['product_id'],$id,$user['group_id']===null?null:(int)$user['group_id']);$item['price_cents']=$price['price_cents'];$item['stock']=(int)$item['stock'];$item['quantity']=(int)$item['quantity'];$item['product_id']=(int)$item['product_id'];$item['available']=(bool)$item['active']&&(bool)$item['category_active']&&$item['stock']>=$item['quantity']&&$price['price_cents']!==null;$valid=$valid&&$item['available'];$item['line_cents']=$price['price_cents']===null?null:$price['price_cents']*$item['quantity'];if($item['line_cents']!==null)$total+=$item['line_cents'];unset($item['active'],$item['category_active']);$items[]=$item;}
 if(!$items){
  $db->exec('BEGIN IMMEDIATE');
  try{
   $q=$db->prepare('SELECT 1 FROM shopping_cart_items WHERE customer_id=? LIMIT 1');$q->execute([$id]);
   if(!$q->fetchColumn()&&$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='uco_orders'")->fetchColumn()){
    $q=$db->prepare("UPDATE uco_orders SET status='cancelled',updated_at=CURRENT_TIMESTAMP WHERE customer_id=? AND status='reserved'");$q->execute([$id]);
   }
   $db->commit();
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 respond(['items'=>$items,'subtotal_cents'=>$total,'stock_valid'=>$valid,'checkout_enabled'=>false,'message'=>'El cobro se habilitará cuando se complete la fase de pedidos y pagos. Los importes son provisionales y se verificarán de nuevo antes de cobrar.']);
}catch(Throwable $e){error_log('UCO carrito: '.$e->getMessage());respond(['error'=>'No se pudo completar la operación'],500);}
