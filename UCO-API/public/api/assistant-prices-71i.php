<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
function reply71i(array $x,int $code=200):never {
 http_response_code($code);
 echo json_encode($x,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')reply71i(['error'=>'Método no permitido'],405);
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
ini_set('session.use_trans_sid','0');
session_name('UCOCLIENT');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);
session_start();
require_once dirname(__DIR__,2).'/private/session-security.php';
if(!empty($_SESSION['customer_id'])&&ucoSecurityIdleExpired())reply71i(['error'=>'Sesión caducada'],401);
$ids=explode(',',(string)($_GET['ids']??''));
if(count($ids)>12||!count($ids))reply71i(['error'=>'Límite de productos superado'],422);
$clean=[];
foreach($ids as $id){
 if(!ctype_digit($id)||(int)$id<1)reply71i(['error'=>'Identificador no válido'],422);
 $clean[]=(int)$id;
}
$clean=array_values(array_unique($clean));
try {
 require_once __DIR__.'/commercial.php';
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,
  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000');
 $cid=(int)($_SESSION['customer_id']??0);
 if(!$cid)reply71i(['error'=>'Inicia sesión para consultar tu tarifa'],401);
 $s=$db->prepare('SELECT password_hash,group_id FROM customers WHERE id=? AND active=1');
 $s->execute([$cid]);$user=$s->fetch();
 if(!$user||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',(string)$user['password_hash'])))reply71i(['error'=>'Sesión caducada'],401);
 $out=[];$unavailable=[];
 $inspect=$db->prepare('SELECT p.id,p.active,p.is_demo,c.active AS category_active FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=?');
 $stmt=$db->prepare('SELECT p.id,p.name,p.unit FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.active=1 AND p.is_demo=0 AND (c.id IS NULL OR c.active=1)');
 foreach($clean as $pid){
  $stmt->execute([$pid]);$p=$stmt->fetch();
  if(!$p){
   $inspect->execute([$pid]);$state=$inspect->fetch();
   $reason=!$state?'not_found':((int)$state['is_demo']===1?'demo':((int)$state['active']!==1?'inactive':'category_inactive'));
   $unavailable[]=['id'=>$pid,'reason'=>$reason];
   continue;
  }
  $price=commercialPrice($db,$pid,$cid,isset($user['group_id'])?(int)$user['group_id']:null);
  if($price['price_cents']===null)$unavailable[]=['id'=>$pid,'reason'=>'price_missing'];
  $out[]=['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],
   'price_cents'=>$price['price_cents'],'price_source'=>$price['price_source']];
 }
 reply71i(['items'=>$out,'unavailable'=>$unavailable,'authenticated'=>true]);
}catch(Throwable $e){error_log('[UCO 7.1I precios] '.$e->getMessage());reply71i(['error'=>'No se pudieron consultar los precios'],503);}
