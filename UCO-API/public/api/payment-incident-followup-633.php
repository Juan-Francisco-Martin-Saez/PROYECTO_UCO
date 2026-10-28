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
function out633(array $v,int $status=200):never {http_response_code($status);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(!in_array(($_SERVER['REQUEST_METHOD']??''),['GET','POST'],true))out633(['error'=>'Método no permitido'],405);
if(ucoSecurityIdleExpired())out633(['error'=>'Sesión caducada'],401);
$id=(int)($_SESSION['uco_user_id']??0);
if($id<1)out633(['error'=>'No autenticado'],401);
try {
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000');
 require_once dirname(__DIR__,2).'/private/identity.php';
 require_once dirname(__DIR__,2).'/private/access-permissions.php';
 $q=$db->prepare('SELECT id,role,active,password_hash FROM admin_users WHERE id=?');$q->execute([$id]);$u=$q->fetch();
 if(!$u||(int)$u['active']!==1||!in_array($u['role'],['admin','manager'],true)||!hash_equals((string)($_SESSION['uco_auth_stamp']??''),hash('sha256',(string)$u['password_hash']))||!ucoIdentitySessionValid($db,'staff',$id,$_SESSION['uco_identity_id']??null))out633(['error'=>'Sesión no válida'],403);
 $caps=ucoOrderCapabilities($db,$id,(string)$u['role']);
 if(!ucoOrderCan($caps,'orders.view'))out633(['error'=>'No autorizado para consultar pedidos'],403);
 

 require_once dirname(__DIR__,2).'/private/payment-incident-followup-633.php';
 uco633Schema($db);
 if($_SERVER['REQUEST_METHOD']==='GET'){
  if(empty($_SESSION['uco_incident_csrf_633']))$_SESSION['uco_incident_csrf_633']=bin2hex(random_bytes(32));
  $type=(string)($_GET['type']??'');$reference=trim((string)($_GET['reference']??''));
  if(!in_array($type,['payment','refund'],true)||$reference===''||strlen($reference)>160)out633(['error'=>'Referencia inválida'],400);
  out633(['ok'=>true,'events'=>uco633Latest($db,$type,$reference),'csrf'=>$_SESSION['uco_incident_csrf_633']]);
 }
 if(!ucoOrderCan($caps,'orders.manage'))out633(['error'=>'No autorizado para gestionar seguimientos'],403);
 $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
 if(empty($_SESSION['uco_incident_csrf_633'])||!hash_equals($_SESSION['uco_incident_csrf_633'],$token))
  out633(['error'=>'CSRF inválido'],403);
 $payload=json_decode(file_get_contents('php://input'),true);
 if(!is_array($payload))out633(['error'=>'JSON inválido'],400);
 $type=(string)($payload['type']??'');$reference=trim((string)($payload['reference']??''));
 $status=(string)($payload['status']??'');$owner=trim((string)($payload['owner']??''));
 $note=trim((string)($payload['note']??''));
 if($note==='')out633(['error'=>'Escribe una observación'],400);
 $saved=uco633Add($db,$type,$reference,$status,$owner,$note,$id);
 out633(['ok'=>true,'saved'=>$saved]);
}catch(InvalidArgumentException $e){out633(['error'=>$e->getMessage()],400);
}catch(Throwable $e){error_log('UCO incident-followup-633: '.$e->getMessage());out633(['error'=>'No se pudo gestionar el seguimiento'],500);}
