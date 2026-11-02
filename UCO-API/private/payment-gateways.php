<?php
declare(strict_types=1);
/** Fase 6: adaptadores de pago. Credenciales en private/config/payment.php, nunca en public/. */
function ucoPaymentConfig():array {
 $file=__DIR__.'/config/payment.php';
 if(!is_file($file))return ['enabled'=>false];
 $config=require $file;
 if(!is_array($config))throw new RuntimeException('Configuración de pagos inválida');
 return $config;
}
function ucoPaymentSchema(PDO $db):void {
 $db->exec("CREATE TABLE IF NOT EXISTS uco_real_payments(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL UNIQUE REFERENCES uco_orders(id),customer_id INTEGER NOT NULL,method TEXT NOT NULL CHECK(method IN ('redsys','bizum','paypal','transfer')),state TEXT NOT NULL CHECK(state IN ('pending','paid','failed','cancelled','review')),reference TEXT NOT NULL UNIQUE,provider_id TEXT,amount_cents INTEGER NOT NULL CHECK(amount_cents>0),currency TEXT NOT NULL DEFAULT 'EUR',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
 $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS uco_real_payment_provider ON uco_real_payments(method,provider_id) WHERE provider_id IS NOT NULL");
 $db->exec("CREATE TABLE IF NOT EXISTS uco_payment_events(id INTEGER PRIMARY KEY AUTOINCREMENT,provider TEXT NOT NULL,event_id TEXT NOT NULL,reference TEXT NOT NULL,received_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(provider,event_id))");
}
function ucoPaymentHttp(string $url,string $method,array $headers=[],?string $body=null):array {
 if(!function_exists('curl_init'))throw new RuntimeException('La extensión cURL es necesaria');
 $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
 if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
 $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
 if($raw===false||$status<200||$status>=300)throw new RuntimeException('Error de comunicación con pasarela HTTP '.$status.($error?' '.$error:''));
 $json=json_decode((string)$raw,true);if(!is_array($json))throw new RuntimeException('Respuesta de pasarela no válida');return $json;
}
function ucoPaypalBase(array $config):string {return !empty($config['sandbox'])?'https://api-m.sandbox.paypal.com':'https://api-m.paypal.com';}
function ucoPaypalToken(array $config):string {
 $id=(string)($config['client_id']??'');$secret=(string)($config['client_secret']??'');if($id===''||$secret==='')throw new RuntimeException('Faltan credenciales PayPal');
 $result=ucoPaymentHttp(ucoPaypalBase($config).'/v1/oauth2/token','POST',['Authorization: Basic '.base64_encode($id.':'.$secret),'Content-Type: application/x-www-form-urlencoded'],'grant_type=client_credentials');
 if(empty($result['access_token']))throw new RuntimeException('PayPal no entregó token');return (string)$result['access_token'];
}
function ucoPaypalCreate(array $config,string $reference,int $amount,string $returnUrl,string $cancelUrl):array {
 $token=ucoPaypalToken($config);
 $payload=['intent'=>'CAPTURE','purchase_units'=>[['reference_id'=>$reference,'custom_id'=>$reference,'amount'=>['currency_code'=>'EUR','value'=>number_format($amount/100,2,'.','')]]],'payment_source'=>['paypal'=>['experience_context'=>['return_url'=>$returnUrl,'cancel_url'=>$cancelUrl,'user_action'=>'PAY_NOW']]]];
 $r=ucoPaymentHttp(ucoPaypalBase($config).'/v2/checkout/orders','POST',['Authorization: Bearer '.$token,'Content-Type: application/json','PayPal-Request-Id: '.$reference],json_encode($payload,JSON_THROW_ON_ERROR));
 $url='';foreach(($r['links']??[]) as $link){if(in_array($link['rel']??'',['payer-action','approve'],true)){$url=(string)$link['href'];break;}}
 if(empty($r['id'])||!str_starts_with($url,'https://'))throw new RuntimeException('PayPal no devolvió una autorización válida');
 return ['provider_id'=>(string)$r['id'],'redirect_url'=>$url];
}
function ucoPaypalCapture(array $config,string $providerId):array {
 $token=ucoPaypalToken($config);
 return ucoPaymentHttp(ucoPaypalBase($config).'/v2/checkout/orders/'.rawurlencode($providerId).'/capture','POST',['Authorization: Bearer '.$token,'Content-Type: application/json','PayPal-Request-Id: capture-'.$providerId],'{}');
}
function ucoPaypalVerifyWebhook(array $config,array $headers,array $event):bool {
 $token=ucoPaypalToken($config);$id=(string)($config['webhook_id']??'');if($id==='')return false;
 $payload=['auth_algo'=>$headers['paypal-auth-algo']??'','cert_url'=>$headers['paypal-cert-url']??'','transmission_id'=>$headers['paypal-transmission-id']??'','transmission_sig'=>$headers['paypal-transmission-sig']??'','transmission_time'=>$headers['paypal-transmission-time']??'','webhook_id'=>$id,'webhook_event'=>$event];
 foreach(['auth_algo','cert_url','transmission_id','transmission_sig','transmission_time'] as $key)if($payload[$key]==='')return false;
 $r=ucoPaymentHttp(ucoPaypalBase($config).'/v1/notifications/verify-webhook-signature','POST',['Authorization: Bearer '.$token,'Content-Type: application/json'],json_encode($payload,JSON_THROW_ON_ERROR));return ($r['verification_status']??'')==='SUCCESS';
}
function ucoRedsysKey(string $base64Key,string $order):string {
 $key=base64_decode($base64Key,true);if($key===false||strlen($key)!==24)throw new RuntimeException('Clave Redsys incorrecta');
 // Redsys HMAC-SHA256 utiliza 3DES CBC con IV de ceros para diversificar la clave.
 $padded=str_pad($order,(int)(ceil(strlen($order)/8)*8),"\0");
 $derived=openssl_encrypt($padded,'des-ede3-cbc',$key,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING,str_repeat("\0",8));
 if($derived===false)throw new RuntimeException('No se pudo generar la firma Redsys');return $derived;
}
function ucoRedsysSignature(string $base64Key,string $order,string $merchantParameters):string {
 return strtr(base64_encode(hash_hmac('sha256',$merchantParameters,ucoRedsysKey($base64Key,$order),true)),'+/','-_');
}
function ucoRedsysForm(array $config,string $reference,int $amount,string $notifyUrl,string $okUrl,string $koUrl,string $method):array {
 $merchant=(string)($config['merchant_code']??'');$terminal=(string)($config['terminal']??'1');$key=(string)($config['secret_key']??'');
 if(!preg_match('/^\d{9}$/',$merchant)||!preg_match('/^\d{1,3}$/',$terminal)||$key==='')throw new RuntimeException('Faltan datos Redsys');
 // Redsys exige pedido de 4-12 caracteres, cuatro primeros numéricos.
 $order=sprintf('%04d',((int)substr($reference,-8))%10000).substr(hash('sha256',$reference),0,8);
 $params=['DS_MERCHANT_AMOUNT'=>(string)$amount,'DS_MERCHANT_ORDER'=>$order,'DS_MERCHANT_MERCHANTCODE'=>$merchant,'DS_MERCHANT_CURRENCY'=>'978','DS_MERCHANT_TRANSACTIONTYPE'=>'0','DS_MERCHANT_TERMINAL'=>$terminal,'DS_MERCHANT_MERCHANTURL'=>$notifyUrl,'DS_MERCHANT_URLOK'=>$okUrl,'DS_MERCHANT_URLKO'=>$koUrl,'DS_MERCHANT_MERCHANTDATA'=>$reference];
 if($method==='bizum')$params['DS_MERCHANT_PAYMETHODS']='z';
 $encoded=base64_encode(json_encode($params,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
 return ['url'=>!empty($config['sandbox'])?'https://sis-t.redsys.es:25443/sis/realizarPago':'https://sis.redsys.es/sis/realizarPago','fields'=>['Ds_SignatureVersion'=>'HMAC_SHA256_V1','Ds_MerchantParameters'=>$encoded,'Ds_Signature'=>ucoRedsysSignature($key,$order,$encoded)],'provider_id'=>$order];
}
function ucoRedsysVerify(array $config,string $parameters,string $signature):?array {
 $json=base64_decode(strtr($parameters,'-_','+/'),true);$data=$json===false?null:json_decode($json,true);if(!is_array($data))return null;
 $lower=array_change_key_case($data,CASE_LOWER);$order=(string)($lower['ds_order']??'');if($order==='')return null;
 $expected=ucoRedsysSignature((string)($config['secret_key']??''),$order,$parameters);
 if(!hash_equals($expected,rtrim(strtr($signature,'+/','-_'),'=')))return null;
 return $lower;
}
