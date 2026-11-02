<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
function reply68(array $v,int $code=200):never {http_response_code($code);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true))reply68(['error'=>'Método no permitido'],405);
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')reply68(['error'=>'Se requiere HTTPS'],403);
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.use_trans_sid','0');
session_name('UCOADMIN');session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Strict','path'=>'/']);session_start();
require_once dirname(__DIR__,2).'/private/session-security.php';
if(ucoSecurityIdleExpired())reply68(['error'=>'Sesión caducada'],401);
$id=(int)($_SESSION['uco_user_id']??0);if($id<1)reply68(['error'=>'No autenticado'],401);
try {
 $base=dirname(__DIR__,2);$db=new PDO('sqlite:'.$base.'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000');require_once $base.'/private/identity.php';require_once $base.'/private/access-permissions.php';
 $q=$db->prepare('SELECT id,role,active,password_hash FROM admin_users WHERE id=?');$q->execute([$id]);$u=$q->fetch();
 if(!$u||(int)$u['active']!==1||$u['role']!=='admin'||!hash_equals((string)($_SESSION['uco_auth_stamp']??''),hash('sha256',(string)$u['password_hash']))||!ucoIdentitySessionValid($db,'staff',$id,$_SESSION['uco_identity_id']??null))reply68(['error'=>'Solo administración principal'],403);
 $file=$base.'/private/config/payment.php';$example=$base.'/private/config/payment.example.php';
 $defaults=['enabled'=>false,'base_url'=>'https://uco.reijohan.com','redsys'=>['enabled'=>false,'sandbox'=>true,'merchant_code'=>'','terminal'=>'1','secret_key'=>''],'paypal'=>['enabled'=>false,'sandbox'=>true,'client_id'=>'','client_secret'=>'','webhook_id'=>''],'transfer'=>['enabled'=>false,'iban'=>'','beneficiary'=>'','concept_prefix'=>'UCO PEDIDO']];
 $cfg=is_file($file)?require $file:(is_file($example)?require $example:$defaults);if(!is_array($cfg))throw new RuntimeException('Configuración incorrecta');
 $cfg=array_replace_recursive($defaults,$cfg);
 if($_SERVER['REQUEST_METHOD']==='GET'){
  if(empty($_SESSION['uco_payment_settings_csrf']))$_SESSION['uco_payment_settings_csrf']=bin2hex(random_bytes(32));
  reply68(['ok'=>true,'csrf'=>$_SESSION['uco_payment_settings_csrf'],'exists'=>is_file($file),'config'=>['enabled'=>false,'redsys'=>['merchant_code'=>(string)$cfg['redsys']['merchant_code'],'terminal'=>(string)$cfg['redsys']['terminal'],'sandbox'=>(bool)$cfg['redsys']['sandbox'],'has_secret'=>((string)$cfg['redsys']['secret_key']!=='')],'paypal'=>['client_id'=>(string)$cfg['paypal']['client_id'],'webhook_id'=>(string)$cfg['paypal']['webhook_id'],'sandbox'=>(bool)$cfg['paypal']['sandbox'],'has_secret'=>((string)$cfg['paypal']['client_secret']!=='')],'transfer'=>['iban'=>(string)$cfg['transfer']['iban'],'beneficiary'=>(string)$cfg['transfer']['beneficiary'],'concept_prefix'=>(string)$cfg['transfer']['concept_prefix']]]]);
 }
 if(!ucoSecurityCsrfValid('uco_payment_settings_csrf'))reply68(['error'=>'CSRF inválido'],403);
 if(!str_starts_with((string)($_SERVER['CONTENT_TYPE']??''),'application/json'))reply68(['error'=>'Formato no admitido'],415);
 $raw=file_get_contents('php://input',false,null,0,8193);if($raw===false||strlen($raw)>8192)reply68(['error'=>'Solicitud demasiado grande'],413);
 $data=json_decode($raw,true);if(!is_array($data)||array_diff(array_keys($data),['redsys','paypal','transfer']))reply68(['error'=>'Datos inválidos'],422);
 foreach(['redsys','paypal','transfer'] as $part)if(!isset($data[$part])||!is_array($data[$part]))reply68(['error'=>'Faltan datos'],422);
 $allowed=['redsys'=>['merchant_code','terminal','sandbox','secret_key'],'paypal'=>['client_id','webhook_id','sandbox','client_secret'],'transfer'=>['iban','beneficiary','concept_prefix']];
 foreach($allowed as $part=>$keys){if(array_diff(array_keys($data[$part]),$keys))reply68(['error'=>'Campo no permitido'],422);foreach($data[$part] as $key=>$value){if(in_array($key,['sandbox'],true)){if(!is_bool($value))reply68(['error'=>'Valor inválido'],422);}elseif(!is_string($value)||strlen($value)>512)reply68(['error'=>'Valor inválido'],422);}}
 $red=$data['redsys'];$pay=$data['paypal'];$tr=$data['transfer'];
 $merchant=trim((string)($red['merchant_code']??$cfg['redsys']['merchant_code']));$terminal=trim((string)($red['terminal']??$cfg['redsys']['terminal']));
 if($merchant!==''&&!preg_match('/^\d{9}$/',$merchant))reply68(['error'=>'Código Redsys: 9 cifras'],422);
 if(!preg_match('/^\d{1,3}$/',$terminal))reply68(['error'=>'Terminal Redsys inválido'],422);
 $iban=strtoupper(preg_replace('/\s+/', '',(string)($tr['iban']??$cfg['transfer']['iban'])));
 if($iban!==''&&!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/',$iban))reply68(['error'=>'Formato IBAN inválido'],422);
 if($iban!=='' && !ibanValid68($iban))reply68(['error'=>'Dígitos de control IBAN inválidos'],422);
 $beneficiary=trim((string)($tr['beneficiary']??$cfg['transfer']['beneficiary']));$concept=trim((string)($tr['concept_prefix']??$cfg['transfer']['concept_prefix']));
 if(strlen($beneficiary)>140||strlen($concept)>60||$concept==='')reply68(['error'=>'Beneficiario o concepto inválido'],422);
 $cfg['redsys']['merchant_code']=$merchant;$cfg['redsys']['terminal']=$terminal;$cfg['redsys']['sandbox']=$red['sandbox']??$cfg['redsys']['sandbox'];
 $cfg['paypal']['client_id']=trim((string)($pay['client_id']??$cfg['paypal']['client_id']));$cfg['paypal']['webhook_id']=trim((string)($pay['webhook_id']??$cfg['paypal']['webhook_id']));$cfg['paypal']['sandbox']=$pay['sandbox']??$cfg['paypal']['sandbox'];
 $cfg['transfer']['iban']=$iban;$cfg['transfer']['beneficiary']=$beneficiary;$cfg['transfer']['concept_prefix']=$concept;
 foreach(['redsys'=>['secret_key'],'paypal'=>['client_secret']] as $part=>$fields)foreach($fields as $key)if(isset($data[$part][$key])&&$data[$part][$key]!==''){$cfg[$part][$key]=$data[$part][$key];}
 // Política 6.8: no activar pagos reales desde este panel ni modificar flags de pasarela.
 $cfg['enabled']=false;$cfg['redsys']['enabled']=false;$cfg['paypal']['enabled']=false;$cfg['transfer']['enabled']=false;
 $dir=dirname($file);if(!is_dir($dir)||!is_writable($dir))reply68(['error'=>'Directorio de configuración no escribible'],500);
 $tmp=tempnam($dir,'.payment-');if($tmp===false)throw new RuntimeException('No se pudo crear archivo temporal');
 try{if(file_put_contents($tmp,"<?php\nreturn ".var_export($cfg,true).";\n",LOCK_EX)===false)throw new RuntimeException('Error de escritura');chmod($tmp,0600);if(!rename($tmp,$file))throw new RuntimeException('Error al guardar');}finally{if(is_file($tmp))unlink($tmp);}
 error_log('UCO payment settings 6.8 updated by admin id='.$id.'; gateways remain disabled');reply68(['ok'=>true,'message'=>'Configuración guardada; cobros reales desactivados']);
}catch(Throwable $e){error_log('UCO payment settings 6.8: '.$e->getMessage());reply68(['error'=>'No se pudo completar la operación'],500);}
function ibanValid68(string $iban):bool {
 $s=substr($iban,4).substr($iban,0,4);$remainder=0;
 foreach(str_split($s) as $c){$digits=ctype_alpha($c)?(string)(ord($c)-55):$c;foreach(str_split($digits) as $d)$remainder=($remainder*10+(int)$d)%97;}
 return $remainder===1;
}
