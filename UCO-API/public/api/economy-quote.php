<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
ini_set('session.use_strict_mode','1');session_name('UCOCLIENT');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);session_start();
require_once __DIR__.'/commercial.php';require_once dirname(__DIR__,2).'/private/economy.php';
function respondQuote(array $a,int $s=200):never{http_response_code($s);echo json_encode($a,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 if($_SERVER['REQUEST_METHOD']!=='GET')respondQuote(['error'=>'Método no permitido'],405);
 $id=(int)($_SESSION['customer_id']??0);if(!$id)respondQuote(['error'=>'Inicia sesión'],401);
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=10000');commercialDb($db);
 $s=$db->prepare('SELECT password_hash,group_id FROM customers WHERE id=? AND active=1');$s->execute([$id]);$u=$s->fetch();if(!$u||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',(string)$u['password_hash'])))respondQuote(['error'=>'Sesión caducada'],401);
 $s=$db->prepare('SELECT ci.product_id,ci.quantity,p.name,p.stock,p.active,c.active AS cat_active FROM shopping_cart_items ci JOIN products p ON p.id=ci.product_id JOIN categories c ON c.id=p.category_id WHERE ci.customer_id=?');$s->execute([$id]);$items=[];$issues=[];
 foreach($s->fetchAll() as $item){$price=commercialPrice($db,(int)$item['product_id'],$id,$u['group_id']===null?null:(int)$u['group_id']);$item['line_cents']=$price['price_cents']===null?null:(int)$price['price_cents']*(int)$item['quantity'];$items[]=$item;if(!(int)$item['active']||!(int)$item['cat_active']||(int)$item['stock']<(int)$item['quantity'])$issues[]='Existencias insuficientes: '.$item['name'];}
 if(!$items)$issues[]='El carrito está vacío';
 $delivery=null;$exists=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='uco_checkout_details'")->fetchColumn();
 if($exists){$s=$db->prepare('SELECT postal_code,province FROM uco_checkout_details WHERE customer_id=?');$s->execute([$id]);$delivery=$s->fetch()?:null;}
 $quote=ucoEconomyQuote($db,$items,$delivery);$quote['issues']=array_values(array_unique(array_merge($issues,$quote['issues'])));$quote['ready']=$quote['ready']&&!$issues;
 if(!$quote['ready']){$quote['total_cents']=null;$quote['tax_cents']=null;$quote['shipping_cents']=null;}
 respondQuote($quote);
}catch(Throwable $e){error_log('UCO economy-quote: '.$e->getMessage());respondQuote(['error'=>'No se pudo calcular el pedido'],500);}
