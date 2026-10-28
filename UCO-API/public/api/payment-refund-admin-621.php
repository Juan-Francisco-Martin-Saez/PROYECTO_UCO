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
function out621(array $v,int $status=200):never {http_response_code($status);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')out621(['error'=>'Método no permitido'],405);
if(ucoSecurityIdleExpired())out621(['error'=>'Sesión caducada'],401);
$id=(int)($_SESSION['uco_user_id']??0);
if($id<1)out621(['error'=>'No autenticado'],401);
try {
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000');
 require_once dirname(__DIR__,2).'/private/identity.php';
 require_once dirname(__DIR__,2).'/private/access-permissions.php';
 $q=$db->prepare('SELECT id,role,active,password_hash FROM admin_users WHERE id=?');$q->execute([$id]);$u=$q->fetch();
 if(!$u||(int)$u['active']!==1||!in_array($u['role'],['admin','manager'],true)||!hash_equals((string)($_SESSION['uco_auth_stamp']??''),hash('sha256',(string)$u['password_hash']))||!ucoIdentitySessionValid($db,'staff',$id,$_SESSION['uco_identity_id']??null))out621(['error'=>'Sesión no válida'],403);
 $caps=ucoOrderCapabilities($db,$id,(string)$u['role']);
 if(!ucoOrderCan($caps,'orders.view'))out621(['error'=>'No autorizado para consultar pedidos'],403);
 
 $status=(string)($_GET['status']??'all');
 $limit=filter_input(INPUT_GET,'limit',FILTER_VALIDATE_INT,['options'=>['default'=>50,'min_range'=>1,'max_range'=>200]]);
 if($limit===false||$limit===null)$limit=50;
 $offset=filter_input(INPUT_GET,'offset',FILTER_VALIDATE_INT,['options'=>['default'=>0,'min_range'=>0]]);
 if($offset===false||$offset===null)$offset=0;
 $exists=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='uco_refund_intents_615'")->fetchColumn();
 $verify=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='uco_refund_verifications_619'")->fetchColumn();
 if(!$exists||!$verify)out621(['ok'=>true,'configured'=>false,'items'=>[],'total'=>0,'read_only'=>true]);
 require_once dirname(__DIR__,2).'/private/payment-refund-admin-620.php';
 $report=ucoRefund620Report($db,$status,$limit,$offset);
 out621(['ok'=>true,'configured'=>true]+$report);
}catch(Throwable $e){error_log('UCO refund-admin-621: '.$e->getMessage());out621(['error'=>'No se pudo consultar el historial de devoluciones'],500);}
