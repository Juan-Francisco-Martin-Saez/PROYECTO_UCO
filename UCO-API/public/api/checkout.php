<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');session_name('UCOCLIENT');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);session_start();
function checkoutReply(array $body,int $status=200):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->exec('PRAGMA busy_timeout=10000');$db->exec('PRAGMA foreign_keys=ON');
 $id=(int)($_SESSION['customer_id']??0);if(!$id)checkoutReply(['error'=>'Inicia sesión'],401);
 $s=$db->prepare('SELECT password_hash FROM customers WHERE id=? AND active=1');$s->execute([$id]);$user=$s->fetch();if(!$user||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',$user['password_hash'])))checkoutReply(['error'=>'Sesión caducada'],401);
 $method=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($method,['GET','POST'],true))checkoutReply(['error'=>'Método no permitido'],405);
 if($method==='POST'&&!hash_equals((string)($_SESSION['customer_csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))checkoutReply(['error'=>'Solicitud no válida'],403);
 $db->exec('CREATE TABLE IF NOT EXISTS uco_checkout_details(customer_id INTEGER PRIMARY KEY REFERENCES customers(id) ON DELETE CASCADE,contact TEXT NOT NULL,phone TEXT NOT NULL,address TEXT NOT NULL,postal_code TEXT NOT NULL,city TEXT NOT NULL,province TEXT NOT NULL,notes TEXT NOT NULL,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
 if($method==='GET'){$s=$db->prepare('SELECT contact,phone,address,postal_code,city,province,notes FROM uco_checkout_details WHERE customer_id=?');$s->execute([$id]);checkoutReply(['details'=>$s->fetch()?:null]);}
 $keys=['contact'=>100,'phone'=>35,'address'=>180,'postal_code'=>12,'city'=>90,'province'=>90,'notes'=>500];$values=[];
 foreach($keys as $key=>$limit){$value=trim((string)($_POST[$key]??''));if(mb_strlen($value,'UTF-8')>$limit)checkoutReply(['error'=>'El campo '.$key.' es demasiado largo'],422);if($key!=='notes'&&$value==='')checkoutReply(['error'=>'Completa todos los datos de entrega'],422);$values[$key]=$value;}
 if(!preg_match('/^[0-9+() .-]{7,35}$/D',$values['phone']))checkoutReply(['error'=>'Introduce un teléfono válido'],422);
 if(!preg_match('/^[0-9A-Za-z -]{3,12}$/D',$values['postal_code']))checkoutReply(['error'=>'Introduce un código postal válido'],422);
 $s=$db->prepare('INSERT INTO uco_checkout_details(customer_id,contact,phone,address,postal_code,city,province,notes) VALUES(:customer_id,:contact,:phone,:address,:postal_code,:city,:province,:notes) ON CONFLICT(customer_id) DO UPDATE SET contact=excluded.contact,phone=excluded.phone,address=excluded.address,postal_code=excluded.postal_code,city=excluded.city,province=excluded.province,notes=excluded.notes,updated_at=CURRENT_TIMESTAMP');$s->execute(['customer_id'=>$id]+$values);
 checkoutReply(['saved'=>true,'message'=>'Datos de entrega guardados. No se ha confirmado ni cobrado ningún pedido.']);
}catch(Throwable $e){error_log('UCO checkout: '.$e->getMessage());checkoutReply(['error'=>'No se pudieron guardar los datos de entrega'],500);}
