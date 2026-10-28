<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.use_trans_sid','0');session_name('UCOCLIENT');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);session_start();
require_once dirname(__DIR__,2).'/private/session-security.php';require_once dirname(__DIR__,2).'/private/payment-ledger.php';require_once dirname(__DIR__,2).'/private/economy.php';
function realReply(array $body,int $code=200):never {http_response_code($code);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try {
 if(($_SERVER['REQUEST_METHOD']??'')==='GET'){ $c=ucoPaymentConfig();realReply(['enabled'=>!empty($c['enabled']),'methods'=>['redsys'=>!empty($c['redsys']['enabled']),'bizum'=>!empty($c['redsys']['enabled']),'paypal'=>!empty($c['paypal']['enabled']),'transfer'=>!empty($c['transfer']['enabled'])]]); }
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')realReply(['error'=>'Método no permitido'],405);
 if((int)($_SERVER['CONTENT_LENGTH']??0)>65536)realReply(['error'=>'Solicitud demasiado grande'],413);
 if(!ucoSecurityCsrfValid('customer_csrf'))realReply(['error'=>'CSRF inválido'],403);
 $cid=(int)($_SESSION['customer_id']??0);if($cid<1||ucoSecurityIdleExpired())realReply(['error'=>'Sesión caducada'],401);
 $config=ucoPaymentConfig();if(empty($config['enabled']))realReply(['error'=>'Los pagos reales aún no están activados'],503);
 $oid=filter_var($_POST['order_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $method=$_POST['method']??null;if(!$oid||!is_string($method)||!in_array($method,['redsys','bizum','paypal','transfer'],true))realReply(['error'=>'Datos de pago incorrectos'],422);
 $provider=$method==='bizum'?'redsys':$method;$pc=$config[$provider]??[];
 if(empty($pc['enabled']))realReply(['error'=>'Método de pago no habilitado'],409);
 $db=ucoPaymentDb();$q=$db->prepare('SELECT password_hash FROM customers WHERE id=? AND active=1');$q->execute([$cid]);$hash=$q->fetchColumn();
 if(!$hash||!hash_equals((string)($_SESSION['customer_stamp']??''),hash('sha256',(string)$hash)))realReply(['error'=>'Sesión no válida'],401);
 $db->exec('BEGIN IMMEDIATE');
 try {
  $q=$db->prepare('SELECT * FROM uco_orders WHERE id=? AND customer_id=?');$q->execute([$oid,$cid]);$order=$q->fetch();if(!$order)throw new DomainException('Pedido no encontrado');
  $q=$db->prepare('SELECT * FROM uco_real_payments WHERE order_id=?');$q->execute([$oid]);$existing=$q->fetch();
  if($existing){$db->commit();realReply(['state'=>$existing['state'],'reference'=>$existing['reference'],'method'=>$existing['method'],'total_cents'=>(int)$existing['amount_cents'],'message'=>'Operación existente; no se genera otro cargo.']);}
  if($order['status']!=='reserved'||(int)$order['expires_at']<=time())throw new DomainException('La reserva ha caducado');
  $q=$db->prepare('SELECT product_id,name,quantity,line_cents FROM uco_order_lines WHERE order_id=?');$q->execute([$oid]);$items=$q->fetchAll();
  $q=$db->prepare('SELECT contact,phone,address,postal_code,city,province,notes FROM uco_checkout_details WHERE customer_id=?');$q->execute([$cid]);$delivery=$q->fetch()?:null;
  $quote=ucoEconomyQuote($db,$items,$delivery);if(!$quote['ready']||(int)$quote['subtotal_cents']!==(int)$order['subtotal_cents']||(int)$quote['total_cents']<=0)throw new DomainException('Revisa los importes y la entrega');
  $q=$db->prepare("SELECT l.quantity,p.stock,COALESCE((SELECT SUM(l2.quantity) FROM uco_order_lines l2 JOIN uco_orders o2 ON o2.id=l2.order_id WHERE l2.product_id=l.product_id AND o2.id<>l.order_id AND o2.status IN ('reserved','pending_payment') AND o2.expires_at>?),0) AS others FROM uco_order_lines l JOIN products p ON p.id=l.product_id WHERE l.order_id=?");
  $q->execute([time(),$oid]);$stockLines=$q->fetchAll();
  if(!$stockLines)throw new DomainException('Pedido sin productos');
  foreach($stockLines as $line)if((int)$line['stock']-(int)$line['others']<(int)$line['quantity'])throw new DomainException('Existencias insuficientes');
  $reference='UCO'.str_pad((string)$oid,8,'0',STR_PAD_LEFT).strtoupper(bin2hex(random_bytes(3)));
  $q=$db->prepare("INSERT INTO uco_real_payments(order_id,customer_id,method,state,reference,amount_cents) VALUES(?,?,?,'pending',?,?)");$q->execute([$oid,$cid,$method,$reference,(int)$quote['total_cents']]);
  $db->prepare("UPDATE uco_orders SET status='pending_payment',payment_status='pending',expires_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([time()+86400,$oid]);
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 $base=rtrim((string)($config['base_url']??''),'/');if(!preg_match('~^https://[a-z0-9.-]+$~i',$base))throw new RuntimeException('URL base de pagos incorrecta');
 if($method==='transfer'){
  $iban=preg_replace('/\s+/','',strtoupper((string)($pc['iban']??'')));$beneficiary=trim((string)($pc['beneficiary']??''));
  if(!preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/',$iban)||$beneficiary==='')throw new RuntimeException('Faltan datos bancarios de transferencia');
  realReply(['state'=>'pending','reference'=>$reference,'method'=>'transfer','total_cents'=>(int)$quote['total_cents'],'bank'=>['iban'=>$iban,'beneficiary'=>$beneficiary,'concept'=>trim((string)($pc['concept_prefix']??'UCO PEDIDO')).' '.$reference],'notice'=>'Pedido pendiente de transferencia. El pago requiere conciliación bancaria verificada.']);
 }
 if($method==='paypal'){
  $r=ucoPaypalCreate($pc,$reference,(int)$quote['total_cents'],$base.'/clientes/',$base.'/clientes/');
  $db->prepare('UPDATE uco_real_payments SET provider_id=? WHERE reference=? AND state=\'pending\'')->execute([$r['provider_id'],$reference]);
  realReply(['state'=>'pending','method'=>'paypal','reference'=>$reference,'total_cents'=>(int)$quote['total_cents'],'redirect_url'=>$r['redirect_url']]);
 }
 $r=ucoRedsysForm($pc,$reference,(int)$quote['total_cents'],$base.'/api/payment-redsys-notify.php',$base.'/clientes/',$base.'/clientes/',$method);
 $db->prepare('UPDATE uco_real_payments SET provider_id=? WHERE reference=? AND state=\'pending\'')->execute([$r['provider_id'],$reference]);
 realReply(['state'=>'pending','method'=>$method,'reference'=>$reference,'total_cents'=>(int)$quote['total_cents'],'form_url'=>$r['url'],'form_fields'=>$r['fields']]);
}catch(DomainException $e){realReply(['error'=>$e->getMessage()],409);}catch(Throwable $e){error_log('UCO real payment start: '.$e->getMessage());realReply(['error'=>'No se ha podido iniciar el pago; comprueba el estado del pedido antes de reintentar'],503);}
