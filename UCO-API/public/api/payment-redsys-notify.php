<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Cache-Control: no-store');
require_once dirname(__DIR__,2).'/private/payment-ledger.php';
try {
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||(int)($_SERVER['CONTENT_LENGTH']??0)>16384){http_response_code(405);exit;}
 $config=ucoPaymentConfig();if(empty($config['enabled'])||empty($config['redsys']['enabled'])){http_response_code(503);exit;}
 $params=$_POST['Ds_MerchantParameters']??null;$signature=$_POST['Ds_Signature']??null;
 if(!is_string($params)||!is_string($signature)||strlen($params)>12000){http_response_code(400);exit;}
 $data=ucoRedsysVerify($config['redsys'],$params,$signature);if($data===null){http_response_code(403);exit;}
 $reference=(string)($data['ds_merchantdata']??'');$providerId=(string)($data['ds_order']??'');$amount=(string)($data['ds_amount']??'');$response=(string)($data['ds_response']??'');
 $merchant=(string)($data['ds_merchantcode']??'');$currency=(string)($data['ds_currency']??'');
 if(!preg_match('/^\d{1,4}$/',$response)||!preg_match('/^\d+$/',$amount)||$currency!=='978'||$merchant!==(string)$config['redsys']['merchant_code']){http_response_code(400);exit;}
 $db=ucoPaymentDb();$q=$db->prepare('SELECT amount_cents,method FROM uco_real_payments WHERE reference=? AND provider_id=?');$q->execute([$reference,$providerId]);$p=$q->fetch();
 if(!$p||(int)$p['amount_cents']!==(int)$amount||!in_array($p['method'],['redsys','bizum'],true)){http_response_code(409);exit;}
 if((int)$response>=0&&(int)$response<=99){$result=ucoPaymentSettle($db,$reference,$providerId,'redsys-'.hash('sha256',$params));if(!in_array($result,['paid','already_paid'],true)){error_log('UCO Redsys conciliación requerida '.$reference.' '.$result);http_response_code(409);exit;}}
 http_response_code(200);echo 'OK';
}catch(Throwable $e){error_log('UCO Redsys callback: '.$e->getMessage());http_response_code(500);}
