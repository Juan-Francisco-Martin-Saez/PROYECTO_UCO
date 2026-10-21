<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
ini_set('session.use_strict_mode','1');session_name('UCOADMIN');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);session_start();
require_once dirname(__DIR__,2).'/private/economy.php';
function reply(array $a,int $status=200):never{http_response_code($status);echo json_encode($a,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 if((int)($_SESSION['uco_user_id']??0)<1)reply(['error'=>'Acceso restringido a administración'],403);
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=10000');
 require_once dirname(__DIR__,2).'/private/identity.php';
 $check=$db->prepare('SELECT role,password_hash FROM admin_users WHERE id=? AND active=1');
 $check->execute([(int)$_SESSION['uco_user_id']]);
 $current=$check->fetch();
 if(!$current||$current['role']!=='admin'||!ucoIdentitySessionValid($db,'staff',(int)$_SESSION['uco_user_id'],$_SESSION['uco_identity_id']??null)||!hash_equals((string)($_SESSION['uco_auth_stamp']??''),hash('sha256',(string)$current['password_hash'])))reply(['error'=>'Sesión o permisos no válidos. Vuelve a iniciar sesión.'],403);
 ucoEconomySchema($db);
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))reply(['error'=>'Sesión no válida'],403);
  $db->exec('BEGIN IMMEDIATE');
  try{
   $type=(string)($_POST['type']??'');
   if($type==='tax'){
    $pid=filter_var($_POST['product_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $rate=filter_var($_POST['rate_bp']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>10000]]);
    if(!$pid||$rate===false||$rate===null){$db->rollBack();reply(['error'=>'Producto o IVA inválido'],422);}
    $s=$db->prepare('INSERT INTO uco_product_taxes(product_id,rate_bp) VALUES(?,?) ON CONFLICT(product_id) DO UPDATE SET rate_bp=excluded.rate_bp');$s->execute([$pid,$rate]);
   }elseif($type==='tax_batch'){
    $ids=json_decode((string)($_POST['product_ids']??''),true);
    $rate=filter_var($_POST['rate_bp']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>10000]]);
    if(!is_array($ids)||!count($ids)||count($ids)>5000||$rate===false||$rate===null){$db->rollBack();reply(['error'=>'Selección o IVA inválidos'],422);}
    foreach($ids as $rawId){if(!is_int($rawId)||$rawId<1){$db->rollBack();reply(['error'=>'Referencias inválidas'],422);}}
    $ids=array_values(array_unique($ids));
    if(count($ids)>5000){$db->rollBack();reply(['error'=>'Referencias inválidas'],422);}
    $check=$db->prepare('SELECT id FROM products WHERE id=? AND active=1');
    $s=$db->prepare('INSERT INTO uco_product_taxes(product_id,rate_bp) VALUES(?,?) ON CONFLICT(product_id) DO UPDATE SET rate_bp=excluded.rate_bp');
    foreach($ids as $pid){
     $check->execute([$pid]);if(!$check->fetchColumn()){$db->rollBack();reply(['error'=>'Una referencia ya no está disponible'],422);}
     $s->execute([$pid,$rate]);
    }
   }elseif($type==='shipping'){
    $price=filter_var($_POST['shipping_cents']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>10000000]]);
    $free=filter_var($_POST['free_shipping_from_cents']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100000000]]);
    $prefixes=trim((string)($_POST['allowed_postal_prefixes']??''));
    if($price===false||$price===null||$free===false||$free===null||!preg_match('/^\d{1,5}(,\s*\d{1,5})*$/',$prefixes)){$db->rollBack();reply(['error'=>'Configuración de transporte no válida'],422);}
    $s=$db->prepare('INSERT INTO uco_economy_settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
    foreach(['shipping_cents'=>(string)$price,'free_shipping_from_cents'=>(string)$free,'allowed_postal_prefixes'=>$prefixes] as $k=>$v)$s->execute([$k,$v]);
   }else {$db->rollBack();reply(['error'=>'Operación no válida'],422);}
   $db->commit();
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }elseif($_SERVER['REQUEST_METHOD']!=='GET')reply(['error'=>'Método no permitido'],405);
 $settings=$db->query('SELECT key,value FROM uco_economy_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
 $taxes=$db->query('SELECT product_id,rate_bp FROM uco_product_taxes')->fetchAll();
 $products=$db->query('SELECT id,sku,name FROM products WHERE active=1 ORDER BY name')->fetchAll();
 reply(['settings'=>$settings,'taxes'=>$taxes,'products'=>$products]);
}catch(Throwable $e){error_log('UCO economy-admin: '.$e->getMessage());reply(['error'=>'No se pudo gestionar la configuración económica'],500);}
