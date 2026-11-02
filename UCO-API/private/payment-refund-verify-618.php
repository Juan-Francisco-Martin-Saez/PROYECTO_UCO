<?php
declare(strict_types=1);
require_once __DIR__.'/payment-gateways.php';

/**
 * Verifica el estado de una devolución PayPal consultando directamente
 * su API autenticada. No modifica la base de datos.
 * El caller debe contrastar refund_id, capture_id, divisa e importe
 * con una intención previamente registrada.
 */
function ucoRefund618PaypalFetch(array $config,string $refundId):array {
 if(!preg_match('/^[A-Za-z0-9-]{8,80}$/D',$refundId))
  throw new InvalidArgumentException('Identificador de devolución PayPal inválido');
 $token=ucoPaypalToken($config);
 $data=ucoPaymentHttp(
  ucoPaypalBase($config).'/v2/payments/refunds/'.rawurlencode($refundId),
  'GET',['Authorization: Bearer '.$token,'Accept: application/json']
 );
 return ucoRefund618PaypalNormalize($data);
}
/** Normalizador puro; el origen del JSON debe ser una consulta autenticada. */
function ucoRefund618PaypalNormalize(array $data):array {
 $id=(string)($data['id']??'');
 $status=strtoupper((string)($data['status']??''));
 $amount=$data['amount']??[];
 if(!is_array($amount))$amount=[];
 $currency=(string)($amount['currency_code']??'');
 $value=(string)($amount['value']??'');
 if(!preg_match('/^[A-Za-z0-9-]{8,80}$/D',$id)
    ||!in_array($status,['COMPLETED','PENDING','CANCELLED','FAILED'],true)
    ||$currency!=='EUR'||!preg_match('/^\d{1,12}\.\d{2}$/D',$value))
  throw new UnexpectedValueException('Respuesta de devolución PayPal incompleta o no reconocida');
 [$whole,$fraction]=explode('.',$value);
 $cents=(int)$whole*100+(int)$fraction;
 if($cents<=0)throw new UnexpectedValueException('Importe no válido');
 return ['provider'=>'paypal','refund_id'=>$id,'provider_status'=>$status,
  'amount_cents'=>$cents,'currency'=>'EUR',
  'is_completed'=>$status==='COMPLETED',
  'verified_via_authenticated_api'=>true,
  'requires_intent_match'=>true];
}
/** Comparación estricta contra la intención persistida. */
function ucoRefund618MatchIntent(array $verified,array $intent):array {
 $same=($verified['provider']??null)===($intent['provider']??null)
  &&($verified['refund_id']??null)===($intent['provider_refund_id']??null)
  &&(int)($verified['amount_cents']??-1)===(int)($intent['amount_cents']??-2)
  &&($verified['currency']??null)===($intent['currency']??null);
 return ['matches'=>$same,'completed'=>$same&&($verified['is_completed']??false)===true,
  'requires_manual_or_transactional_update'=>true];
}
/**
 * Redsys: valida la firma de una notificación utilizando la clave privada.
 * NO equivale a confirmar una devolución: el resultado de una operación
 * tipo 3 requiere comprobar códigos y conciliación con el adquirente.
 */
function ucoRefund618RedsysNotification(array $config,string $params,string $signature):array {
 if(strlen($params)>16384||strlen($signature)>512)
  throw new InvalidArgumentException('Notificación demasiado grande');
 $data=ucoRedsysVerify($config,$params,$signature);
 if($data===null)throw new DomainException('Firma Redsys inválida');
 $type=(string)($data['ds_transactiontype']??'');
 $amount=(string)($data['ds_amount']??'');
 $order=(string)($data['ds_order']??'');
 $response=(string)($data['ds_response']??'');
 if(!preg_match('/^[0-9]{4,12}$/D',$order)
  ||!preg_match('/^[0-9]{1,12}$/D',$amount)
  ||!preg_match('/^[0-9]{1,4}$/D',$response))
  throw new UnexpectedValueException('Notificación Redsys sin campos válidos');
 return ['provider'=>'redsys','signature_verified'=>true,
  'order'=>$order,'transaction_type'=>$type,'amount_cents'=>(int)$amount,
  'response_code'=>$response,'is_refund_operation'=>$type==='3',
  'refund_completed'=>false,
  'requires_acquirer_reconciliation'=>true];
}
