<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Cache-Control: no-store');header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__,2).'/private/payment-ledger.php';
try {
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||(int)($_SERVER['CONTENT_LENGTH']??0)>65536){http_response_code(405);exit;}
 $config=ucoPaymentConfig();if(empty($config['enabled'])||empty($config['paypal']['enabled'])){http_response_code(503);exit;}
 $raw=file_get_contents('php://input');$event=json_decode((string)$raw,true);if(!is_array($event)||empty($event['id'])||!is_string($event['id'])){http_response_code(400);exit;}
 $headers=[];foreach($_SERVER as $key=>$value)if(str_starts_with($key,'HTTP_PAYPAL_'))$headers[strtolower(str_replace('_','-',substr($key,5)))]=$value;
 if(!ucoPaypalVerifyWebhook($config['paypal'],$headers,$event)){http_response_code(403);exit;}
 $type=(string)($event['event_type']??'');
 if($type!=='PAYMENT.CAPTURE.COMPLETED'){http_response_code(200);echo '{"ok":true}';exit;}
 $resource=$event['resource']??[];$reference=(string)($resource['custom_id']??'');$captureId=(string)($resource['id']??'');$amount=$resource['amount']??[];
 if($reference===''||$captureId===''||($amount['currency_code']??'')!=='EUR'||!preg_match('/^\d+\.\d{2}$/',(string)($amount['value']??''))){http_response_code(409);exit;}
 $cents=(int)round(((float)$amount['value'])*100);
 $db=ucoPaymentDb();$q=$db->prepare("SELECT provider_id,amount_cents FROM uco_real_payments WHERE reference=? AND method='paypal'");$q->execute([$reference]);$p=$q->fetch();
 if(!$p||(int)$p['amount_cents']!==$cents){http_response_code(409);exit;}
 // Capture webhook identifies capture ID; verify parent order through provider API.
 $token=ucoPaypalToken($config['paypal']);$capture=ucoPaymentHttp(ucoPaypalBase($config['paypal']).'/v2/payments/captures/'.rawurlencode($captureId),'GET',['Authorization: Bearer '.$token]);
 if(($capture['status']??'')!=='COMPLETED'||($capture['custom_id']??'')!==$reference||($capture['amount']['currency_code']??'')!=='EUR'||(string)($capture['amount']['value']??'')!==(string)$amount['value']){http_response_code(409);exit;}
 $result=ucoPaymentSettle($db,$reference,(string)$p['provider_id'],(string)$event['id']);
 if(!in_array($result,['paid','already_paid'],true)){error_log('UCO PayPal conciliación requerida '.$reference.' '.$result);http_response_code(409);exit;}
 http_response_code(200);echo '{"ok":true}';
}catch(Throwable $e){error_log('UCO PayPal webhook: '.$e->getMessage());http_response_code(500);}
