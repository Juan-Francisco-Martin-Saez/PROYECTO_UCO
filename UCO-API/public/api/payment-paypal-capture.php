<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.use_trans_sid','0');session_name('UCOCLIENT');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);session_start();
require_once dirname(__DIR__,2).'/private/session-security.php';require_once dirname(__DIR__,2).'/private/payment-ledger.php';
function captureReply(array $v,int $status=200):never{http_response_code($status);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!ucoSecurityCsrfValid('customer_csrf'))captureReply(['error'=>'Solicitud no autorizada'],403);
 $cid=(int)($_SESSION['customer_id']??0);if($cid<1||ucoSecurityIdleExpired())captureReply(['error'=>'Sesión caducada'],401);
 $reference=$_POST['reference']??null;if(!is_string($reference)||!preg_match('/^UCO[0-9]{8}[A-F0-9]{6}$/D',$reference))captureReply(['error'=>'Referencia incorrecta'],422);
 $config=ucoPaymentConfig();if(empty($config['enabled'])||empty($config['paypal']['enabled']))captureReply(['error'=>'PayPal desactivado'],503);
 $db=ucoPaymentDb();$q=$db->prepare("SELECT p.*,c.password_hash FROM uco_real_payments p JOIN customers c ON c.id=p.customer_id AND c.active=1 WHERE p.reference=? AND p.customer_id=? AND p.method='paypal'");$q->execute([$reference,$cid]);$p=$q->fetch();
 if(!$p||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',(string)$p['password_hash'])))captureReply(['error'=>'Operación no disponible'],404);
 if($p['state']==='paid')captureReply(['state'=>'paid','reference'=>$reference]);
 if($p['state']!=='pending'||empty($p['provider_id']))captureReply(['error'=>'Operación no pendiente'],409);
 $r=ucoPaypalCapture($config['paypal'],(string)$p['provider_id']);
 if(($r['status']??'')!=='COMPLETED')captureReply(['state'=>'pending','reference'=>$reference,'message'=>'Aún no hay confirmación del cobro'],202);
 $capture=$r['purchase_units'][0]['payments']['captures'][0]??[];
 if(($capture['status']??'')!=='COMPLETED'||($capture['amount']['currency_code']??'')!=='EUR'||(string)($capture['amount']['value']??'')!==number_format((int)$p['amount_cents']/100,2,'.',''))captureReply(['error'=>'Importe o estado de PayPal no coincide; requiere conciliación'],409);
 $result=ucoPaymentSettle($db,$reference,(string)$p['provider_id'],'capture-'.(string)($capture['id']??''));
 if(!in_array($result,['paid','already_paid'],true))captureReply(['error'=>'Cobro registrado, pero el pedido requiere conciliación manual','state'=>'review'],409);
 captureReply(['state'=>'paid','reference'=>$reference]);
}catch(Throwable $e){error_log('UCO PayPal capture: '.$e->getMessage());captureReply(['error'=>'No se ha podido confirmar el cobro. Consulta el estado antes de repetir'],503);}
