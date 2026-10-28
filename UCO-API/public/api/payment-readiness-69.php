<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
function readiness69(array $v,int $status=200):never {
 http_response_code($status);
 echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')readiness69(['error'=>'Método no permitido'],405);
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')readiness69(['error'=>'HTTPS obligatorio'],403);
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
ini_set('session.use_trans_sid','0');
session_name('UCOADMIN');
session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Strict','path'=>'/']);
session_start();
$base=dirname(__DIR__,2);
require_once $base.'/private/session-security.php';
if(ucoSecurityIdleExpired())readiness69(['error'=>'Sesión caducada'],401);
$id=(int)($_SESSION['uco_user_id']??0);
if($id<1)readiness69(['error'=>'No autenticado'],401);
try {
 $db=new PDO('sqlite:'.$base.'/private/database/catalogo.sqlite',null,null,[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
 ]);
 $db->exec('PRAGMA busy_timeout=5000');
 require_once $base.'/private/identity.php';
 require_once $base.'/private/access-permissions.php';
 $q=$db->prepare('SELECT id,role,active,password_hash FROM admin_users WHERE id=?');
 $q->execute([$id]);$u=$q->fetch();
 if(!$u||(int)$u['active']!==1||$u['role']!=='admin'
   ||!hash_equals((string)($_SESSION['uco_auth_stamp']??''),hash('sha256',(string)$u['password_hash']))
   ||!ucoIdentitySessionValid($db,'staff',$id,$_SESSION['uco_identity_id']??null))
   readiness69(['error'=>'Acceso restringido'],403);
 $file=$base.'/private/config/payment.php';
 $config=is_file($file)?require $file:[];
 if(!is_array($config))throw new RuntimeException('Configuración incorrecta');
 $r=is_array($config['redsys']??null)?$config['redsys']:[];
 $p=is_array($config['paypal']??null)?$config['paypal']:[];
 $t=is_array($config['transfer']??null)?$config['transfer']:[];
 $checks=[];
 $add=static function(string $id,string $title,bool $pass,string $detail)use(&$checks):void{
  $checks[]=['id'=>$id,'title'=>$title,'ok'=>$pass,'detail'=>$detail];
 };
 $add('config','Configuración privada',is_file($file),'Archivo de configuración en private/config');
 $add('https','URL pública HTTPS',str_starts_with((string)($config['base_url']??''),'https://'),'La URL base debe usar HTTPS');
 $add('curl','Extensión cURL',extension_loaded('curl'),'Necesaria para PayPal');
 $add('openssl','Extensión OpenSSL',extension_loaded('openssl'),'Necesaria para firmas Redsys');
 $add('sqlite','PDO SQLite',extension_loaded('pdo_sqlite'),'Necesaria para el registro de operaciones');
 $add('redsys','Credenciales Redsys',preg_match('/^\d{9}$/',(string)($r['merchant_code']??''))===1
   && preg_match('/^\d{1,3}$/',(string)($r['terminal']??''))===1
   && strlen((string)base64_decode((string)($r['secret_key']??''),true))===24,
   'Comercio, terminal y clave de firma presentes');
 $add('paypal','Credenciales PayPal',(string)($p['client_id']??'')!==''
   && (string)($p['client_secret']??'')!=='' && (string)($p['webhook_id']??'')!=='',
   'Client ID, secreto y webhook presentes');
 $iban=strtoupper(preg_replace('/\s+/','',(string)($t['iban']??'')));
 $add('transfer','Datos de transferencia',preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/',$iban)===1
   && trim((string)($t['beneficiary']??''))!==''
   && trim((string)($t['concept_prefix']??''))!=='',
   'IBAN, beneficiario y concepto presentes; no verifica ingreso');
 foreach(['private/payment-gateways.php','private/payment-ledger.php',
   'public/api/payment-real.php','public/api/payment-paypal-webhook.php',
   'public/api/payment-redsys-notify.php','public/api/payment-paypal-capture.php'] as $path){
  $add('file-'.basename($path),$path,is_file($base.'/'.$path),'Presencia del componente');
 }
 $add('disabled','Cobros reales desactivados',empty($config['enabled'])
   && empty($r['enabled']) && empty($p['enabled']) && empty($t['enabled']),
   'Bloqueo de seguridad hasta pruebas de extremo a extremo');
 readiness69([
  'ok'=>true,'phase'=>'6.9','read_only'=>true,
  'gateways_enabled'=>!empty($config['enabled']),
  'checks'=>$checks,
  'note'=>'Este diagnóstico es estático: no prueba pagos, webhooks, conciliación ni existencias.'
 ]);
}catch(Throwable $e){
 error_log('UCO readiness 6.9: '.$e->getMessage());
 readiness69(['error'=>'No se pudo realizar el diagnóstico'],500);
}
