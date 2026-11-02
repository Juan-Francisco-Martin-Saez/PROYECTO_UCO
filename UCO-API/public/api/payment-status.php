<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
ini_set('session.use_trans_sid','0');
session_name('UCOCLIENT');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);
session_start();
require_once dirname(__DIR__,2).'/private/session-security.php';
require_once dirname(__DIR__,2).'/private/payment-ledger.php';
function ucoStatusReply(array $v,int $code=200):never { http_response_code($code); echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit; }
try {
 if(($_SERVER['REQUEST_METHOD']??'')!=='GET')ucoStatusReply(['error'=>'Método no permitido'],405);
 $cid=(int)($_SESSION['customer_id']??0);
 if($cid<1||ucoSecurityIdleExpired())ucoStatusReply(['error'=>'Sesión caducada'],401);
 $reference=$_GET['reference']??null;
 if(!is_string($reference)||!preg_match('/^UCO[0-9]{8}[A-F0-9]{6}$/D',$reference))ucoStatusReply(['error'=>'Referencia incorrecta'],422);
 $db=ucoPaymentDb();
 $q=$db->prepare("SELECT p.reference,p.method,p.state,p.amount_cents,p.currency,p.order_id,o.status AS order_status,c.password_hash FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id JOIN customers c ON c.id=p.customer_id AND c.active=1 WHERE p.reference=? AND p.customer_id=?");
 $q->execute([$reference,$cid]);$p=$q->fetch();
 if(!$p||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',(string)$p['password_hash'])))ucoStatusReply(['error'=>'Operación no disponible'],404);
 $out=['reference'=>$p['reference'],'method'=>$p['method'],'state'=>$p['state'],'amount_cents'=>(int)$p['amount_cents'],'currency'=>$p['currency'],'order_id'=>(int)$p['order_id'],'order_status'=>$p['order_status']];
 if($p['method']==='transfer'&&$p['state']==='pending'){
  $config=ucoPaymentConfig();$t=$config['transfer']??[];
  if(!empty($config['enabled'])&&!empty($t['enabled']))$out['bank']=['iban'=>(string)($t['iban']??''),'beneficiary'=>(string)($t['beneficiary']??''),'concept'=>trim((string)($t['concept_prefix']??'UCO PEDIDO')).' '.$p['reference']];
 }
 ucoStatusReply($out);
}catch(Throwable $e){error_log('UCO payment status: '.$e->getMessage());ucoStatusReply(['error'=>'No se ha podido consultar el pago'],503);}
