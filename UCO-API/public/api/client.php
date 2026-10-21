<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');session_name('UCOCLIENT');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);session_start();
require_once __DIR__.'/commercial.php';
require_once dirname(__DIR__,2).'/private/identity.php';
function reply(array $a,int $status=200):never{http_response_code($status);echo json_encode($a,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function connect():PDO{$db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=5000');return $db;}
$action=(string)($_GET['action']??'session');$method=$_SERVER['REQUEST_METHOD'];
try{
 $db=connect();commercialDb($db);
 if($action==='register'&&$method==='POST'){
  // Preparado para confirmación futura: token y caducidad registrados, sin enviar correo todavía.
  $now=time();$attempts=array_filter($_SESSION['register_attempts']??[],static fn($t)=>$t>$now-3600);
  if(count($attempts)>=5)reply(['error'=>'Demasiados intentos. Prueba más tarde.'],429);
  $attempts[]=$now;$_SESSION['register_attempts']=array_values($attempts);
  $username=trim((string)($_POST['username']??''));$company=trim((string)($_POST['company']??''));$email=mb_strtolower(trim((string)($_POST['email']??'')),'UTF-8');$pass=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm']??'');
  if(!preg_match('/^[\p{L}\p{N}_.-]{3,40}$/u',$username))reply(['error'=>'El usuario debe tener de 3 a 40 caracteres: letras, números, punto, guion o guion bajo.'],422);
  if(mb_strlen($company)<2||mb_strlen($company)>160||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>190)reply(['error'=>'Revisa el nombre de empresa y el correo electrónico.'],422);
  if(strlen($pass)<12||strlen($pass)>256||$pass!==$confirm)reply(['error'=>'Las contraseñas deben coincidir y tener al menos 12 caracteres.'],422);
  $existing=$db->prepare('SELECT 1 FROM customers WHERE email=? COLLATE NOCASE OR username=? COLLATE NOCASE');$existing->execute([$email,$username]);
  if($existing->fetchColumn())reply(['error'=>'El correo electrónico o el nombre de usuario ya está registrado.'],409);
  $token=bin2hex(random_bytes(32));$tokenHash=hash('sha256',$token);
  $st=$db->prepare("INSERT INTO customers(company,contact,email,password_hash,username,email_verified,verification_token_hash,verification_expires,active) VALUES(?,?,?,?,?,0,?,datetime('now','+2 days'),1)");
  $st->execute([$company,$username,$email,password_hash($pass,PASSWORD_DEFAULT),$username,$tokenHash]);
  // No se expone el token: el envío de correo y la confirmación se activarán en una fase posterior.
  reply(['ok'=>true,'message'=>'Cuenta creada. Ya puedes acceder; la confirmación por correo se activará próximamente.']);
 }
 if($action==='login'&&$method==='POST'){
  $identity=mb_strtolower(trim((string)($_POST['email']??'')),'UTF-8');$pass=(string)($_POST['password']??'');$st=$db->prepare('SELECT * FROM customers WHERE email=? COLLATE NOCASE OR username=? COLLATE NOCASE LIMIT 1');$st->execute([$identity,$identity]);$u=$st->fetch();
  if(!$u||!(int)$u['active']||!password_verify($pass,$u['password_hash'])){usleep(450000);reply(['error'=>'Credenciales incorrectas o cuenta desactivada.'],401);}
  $identityId=ucoIdentityResolve($db,'customer',(int)$u['id'],true);if($identityId===null)reply(['error'=>'No se pudo vincular la identidad.'],503);
  session_regenerate_id(true);$_SESSION['customer_id']=(int)$u['id'];$_SESSION['uco_identity_id']=$identityId;$_SESSION['customer_stamp']=hash('sha256',$u['password_hash']);$_SESSION['customer_csrf']=bin2hex(random_bytes(32));reply(['ok'=>true,'csrf'=>$_SESSION['customer_csrf'],'customer'=>customerPublic($u)]);
 }
 if($action==='session'&&$method==='GET'&&!isset($_SESSION['customer_id']))reply(['authenticated'=>false]);
 if(!isset($_SESSION['customer_id']))reply(['error'=>'Acceso restringido'],401);
 $st=$db->prepare('SELECT * FROM customers WHERE id=? AND active=1');$st->execute([$_SESSION['customer_id']]);$u=$st->fetch();
 if(!$u||!ucoIdentitySessionValid($db,'customer',(int)$_SESSION['customer_id'],$_SESSION['uco_identity_id']??null)||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',$u['password_hash']))){$_SESSION=[];session_destroy();reply(['authenticated'=>false,'error'=>'La sesión ha caducado'],401);}
 if($action==='session'&&$method==='GET')reply(['authenticated'=>true,'csrf'=>$_SESSION['customer_csrf'],'customer'=>customerPublic($u)]);
 if($action==='logout'&&$method==='POST'){
  if(!hash_equals((string)$_SESSION['customer_csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))reply(['error'=>'Solicitud no válida'],403);
  $_SESSION=[];session_destroy();reply(['ok'=>true]);
 }
 if($action==='change_password'&&$method==='POST'){
  if(!hash_equals((string)$_SESSION['customer_csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))reply(['error'=>'Solicitud no válida'],403);
  $old=(string)($_POST['old_password']??'');$new=(string)($_POST['new_password']??'');if(!password_verify($old,$u['password_hash']))reply(['error'=>'Contraseña actual incorrecta'],422);if(strlen($new)<12||strlen($new)>256)reply(['error'=>'La contraseña debe tener entre 12 y 256 caracteres'],422);
  $hash=password_hash($new,PASSWORD_DEFAULT);$db->prepare('UPDATE customers SET password_hash=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$hash,$u['id']]);$_SESSION['customer_stamp']=hash('sha256',$hash);reply(['ok'=>true]);
 }
 if($action==='catalog'&&$method==='GET'){
  $page=max(1,min(100000,(int)($_GET['page']??1)));$limit=12;$q=trim((string)($_GET['q']??''));if(mb_strlen($q)>100)reply(['error'=>'Búsqueda demasiado larga'],400);
  $where=' WHERE p.active=1 AND c.active=1';$args=[];if($q!==''){$where.=" AND (p.name LIKE :qname ESCAPE '!' OR p.sku LIKE :qsku ESCAPE '!')";$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';$args[':qname']=$term;$args[':qsku']=$term;}
  $from=' FROM products p JOIN categories c ON c.id=p.category_id';$st=$db->prepare('SELECT COUNT(*)'.$from.$where);$st->execute($args);$total=(int)$st->fetchColumn();
  $sql='SELECT p.id,p.sku,p.name,p.description,p.unit,p.image,p.stock,c.name AS family'.$from.$where.' ORDER BY p.name COLLATE NOCASE,p.id LIMIT :limit OFFSET :offset';
  $st=$db->prepare($sql);foreach($args as $k=>$v)$st->bindValue($k,$v);$st->bindValue(':limit',$limit,PDO::PARAM_INT);$st->bindValue(':offset',($page-1)*$limit,PDO::PARAM_INT);$st->execute();
  $rows=$st->fetchAll();foreach($rows as &$row){$price=commercialPrice($db,(int)$row['id'],(int)$u['id'],$u['group_id']===null?null:(int)$u['group_id']);$row=array_merge($row,$price);}unset($row);
  $group=null;if($u['group_id']){$g=$db->prepare('SELECT name FROM customer_groups WHERE id=? AND active=1');$g->execute([$u['group_id']]);$group=$g->fetchColumn()?:null;}
  reply(['products'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit,'group'=>$group]);
 }
 reply(['error'=>'Operación no permitida'],405);
}catch(Throwable $e){error_log('UCO clientes: '.$e->getMessage());reply(['error'=>'No se pudo completar la operación'],500);}
