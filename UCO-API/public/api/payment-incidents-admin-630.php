<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
ini_set('session.use_trans_sid','0');
session_name('UCOADMIN');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);
session_start();
require_once dirname(__DIR__,2).'/private/session-security.php';
function out630(array $v,int $status=200):never {http_response_code($status);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')out630(['error'=>'Método no permitido'],405);
if(ucoSecurityIdleExpired())out630(['error'=>'Sesión caducada'],401);
$id=(int)($_SESSION['uco_user_id']??0);
if($id<1)out630(['error'=>'No autenticado'],401);
try {
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000');
 require_once dirname(__DIR__,2).'/private/identity.php';
 require_once dirname(__DIR__,2).'/private/access-permissions.php';
 $q=$db->prepare('SELECT id,role,active,password_hash FROM admin_users WHERE id=?');$q->execute([$id]);$u=$q->fetch();
 if(!$u||(int)$u['active']!==1||!in_array($u['role'],['admin','manager'],true)||!hash_equals((string)($_SESSION['uco_auth_stamp']??''),hash('sha256',(string)$u['password_hash']))||!ucoIdentitySessionValid($db,'staff',$id,$_SESSION['uco_identity_id']??null))out630(['error'=>'Sesión no válida'],403);
 $caps=ucoOrderCapabilities($db,$id,(string)$u['role']);
 if(!ucoOrderCan($caps,'orders.view'))out630(['error'=>'No autorizado para consultar pedidos'],403);
 
 $limit=filter_input(INPUT_GET,'limit',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100]]);
 $offset=filter_input(INPUT_GET,'offset',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);
 if($limit===false||$offset===false)out630(['error'=>'Parámetros de paginación inválidos'],400);
 if($limit===null)$limit=25;
 if($offset===null)$offset=0;
 require_once dirname(__DIR__,2).'/private/payment-incidents-unified-629.php';
 $report=ucoIncidents629Report($db,$limit,$offset);
 out630(['ok'=>true]+$report);
}catch(Throwable $e){error_log('UCO incidents-admin-630: '.$e->getMessage());out630(['error'=>'No se pudo consultar el informe de incidencias'],500);}
