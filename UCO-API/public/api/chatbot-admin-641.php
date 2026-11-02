<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
ini_set('session.use_trans_sid','0');
session_name('UCOADMIN');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);
session_start();
require_once dirname(__DIR__,2).'/private/session-security.php';
function uco641Out(array $v,int $code=200):never {http_response_code($code);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true))uco641Out(['error'=>'Método no permitido'],405);
if(ucoSecurityIdleExpired())uco641Out(['error'=>'Sesión caducada'],401);
$id=(int)($_SESSION['uco_user_id']??0);
if($id<1)uco641Out(['error'=>'Acceso no autenticado'],401);
try{
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,
 [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000');$db->exec('PRAGMA foreign_keys=ON');
 require_once dirname(__DIR__,2).'/private/identity.php';
 require_once dirname(__DIR__,2).'/private/access-permissions.php';
 $q=$db->prepare('SELECT id,role,active,password_hash FROM admin_users WHERE id=?');$q->execute([$id]);$u=$q->fetch();
 if(!$u||(int)$u['active']!==1||!in_array($u['role'],['admin','manager'],true)
 ||!hash_equals((string)($_SESSION['uco_auth_stamp']??''),hash('sha256',(string)$u['password_hash']))
 ||!ucoIdentitySessionValid($db,'staff',$id,$_SESSION['uco_identity_id']??null))
 uco641Out(['error'=>'Sesión no válida'],403);
 $caps=ucoOrderCapabilities($db,$id,(string)$u['role']);
 if(!ucoOrderCan($caps,'orders.view'))uco641Out(['error'=>'Sin permiso para consultar pedidos'],403);
 require_once dirname(__DIR__,2).'/private/chatbot/admin-readiness-641.php';
 uco641Schema($db);
 if(empty($_SESSION['uco_bot_csrf_641']))$_SESSION['uco_bot_csrf_641']=bin2hex(random_bytes(32));
 if($method==='POST'){
  if((string)$u['role']!=='admin')uco641Out(['error'=>'Solo el administrador puede cambiar autorizaciones'],403);
  if(!hash_equals($_SESSION['uco_bot_csrf_641'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))uco641Out(['error'=>'CSRF inválido'],403);
  if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)uco641Out(['error'=>'Solicitud demasiado grande'],413);
  $data=json_decode(file_get_contents('php://input'),true);
  if(!is_array($data)||($data['action']??'')!=='set_access'
  ||!is_int($data['customer_id']??null)||!is_bool($data['allowed']??null))
   uco641Out(['error'=>'Solicitud inválida'],400);
  uco641SetAllowed($db,$data['customer_id'],$data['allowed'],$id);
  uco641Out(['ok'=>true,'message'=>'Autorización administrativa actualizada; el consentimiento del cliente sigue pendiente']);
 }
 $cid=filter_input(INPUT_GET,'customer_id',FILTER_VALIDATE_INT);
 $customer=null;
 if($cid!==null&&$cid!==false&&$cid>0){
  $q=$db->prepare("SELECT c.id,c.name,COALESCE(a.allowed_by_admin,0) allowed_by_admin,
   a.consent_at FROM customers c LEFT JOIN uco_bot_customer_access_641 a ON a.customer_id=c.id WHERE c.id=?");
  // Detectar estructura de clientes en lugar de asumir un nombre de columna.
  $columns=$db->query('PRAGMA table_info(customers)')->fetchAll();
  $names=array_column($columns,'name');
  $display=in_array('name',$names,true)?'c.name':(in_array('business_name',$names,true)?'c.business_name':"''");
  $q=$db->prepare("SELECT c.id,$display display_name,COALESCE(a.allowed_by_admin,0) allowed_by_admin,
   a.consent_at FROM customers c LEFT JOIN uco_bot_customer_access_641 a ON a.customer_id=c.id WHERE c.id=?");
  $q->execute([$cid]);$customer=$q->fetch()?:null;
 }
 $errors=$db->query("SELECT id,category,reference,detail,created_at FROM uco_bot_errors_641 ORDER BY id DESC LIMIT 20")->fetchAll();
 $origins=$db->query("SELECT o.order_id,o.origin,o.created_at FROM uco_bot_order_origin_641 o ORDER BY o.order_id DESC LIMIT 20")->fetchAll();
 $audit=$db->query("SELECT actor_id,action,subject_id,created_at FROM uco_bot_admin_audit_641 ORDER BY id DESC LIMIT 20")->fetchAll();
 uco641Out(['ok'=>true,'csrf'=>$_SESSION['uco_bot_csrf_641'],'is_admin'=>((string)$u['role']==='admin'),
  'flags'=>['chatbot_live'=>false,'real_payments_enabled_by_phase'=>false,'customer_consent_flow_ready'=>false],
  'summary'=>uco641Summary($db),'customer'=>$customer,'errors'=>$errors,'origins'=>$origins,'audit'=>$audit]);
}catch(InvalidArgumentException $e){uco641Out(['error'=>$e->getMessage()],400);
}catch(Throwable $e){error_log('UCO chatbot admin 641: '.$e->getMessage());uco641Out(['error'=>'No se pudo consultar la preparación del chatbot'],500);}
