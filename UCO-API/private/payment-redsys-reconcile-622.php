<?php
declare(strict_types=1);
require_once __DIR__.'/payment-refund-verify-618.php';

/**
 * Compara una notificación Redsys firmada con una intención registrada.
 * La firma autentica el mensaje, NO acredita abono al comprador.
 * No escribe en SQLite, no confirma devolución ni altera stock.
 */
function ucoRedsys622CompareNotification(
 PDO $db,array $redsysConfig,string $parameters,string $signature,int $intentId
):array {
 if($intentId<1)throw new InvalidArgumentException('Intención inválida');
 $data=ucoRefund618RedsysNotification($redsysConfig,$parameters,$signature);
 $q=$db->prepare("SELECT r.id,r.payment_reference,r.provider,r.amount_cents,r.currency,
 r.status,r.provider_refund_id,p.provider_id AS original_order,p.state AS payment_state
 FROM uco_refund_intents_615 r
 JOIN uco_real_payments p ON p.reference=r.payment_reference
 WHERE r.id=?");
 $q->execute([$intentId]);$r=$q->fetch(PDO::FETCH_ASSOC);
 if(!$r)throw new DomainException('Intención inexistente');
 $flags=[];
 if(!in_array($r['provider'],['redsys','bizum'],true))$flags[]='wrong_provider';
 if($r['payment_state']!=='paid')$flags[]='payment_not_settled';
 if($r['provider']!==$data['provider'] && !($r['provider']==='bizum' && $data['provider']==='redsys'))$flags[]='provider_mismatch';
 if($r['currency']!=='EUR')$flags[]='currency_mismatch';
 if((int)$r['amount_cents']!==(int)$data['amount_cents'])$flags[]='amount_mismatch';
 if(!$data['is_refund_operation'])$flags[]='not_refund_operation';
 // El Ds_Order de la devolución puede no coincidir con el original según
 // contrato TPV: no se asume equivalencia ni se inventa vínculo.
 if($r['original_order']!==$data['order'])$flags[]='order_reference_requires_acquirer_review';
 $merchant=(string)($redsysConfig['merchant_code']??'');
 $terminal=(string)($redsysConfig['terminal']??'');
 $decoded=base64_decode(strtr($parameters,'-_','+/'),true);
 $raw=is_string($decoded)?json_decode($decoded,true):null;
 $lower=is_array($raw)?array_change_key_case($raw,CASE_LOWER):[];
 if($merchant===''||!hash_equals($merchant,(string)($lower['ds_merchantcode']??'')))
  $flags[]='merchant_mismatch';
 if($terminal===''||ltrim($terminal,'0')!==ltrim((string)($lower['ds_terminal']??''),'0'))
  $flags[]='terminal_mismatch';
 if((string)($lower['ds_currency']??'')!=='978')$flags[]='currency_code_mismatch';
 return [
  'intent_id'=>$intentId,'provider'=>$r['provider'],
  'signature_verified'=>true,'reference'=>$r['payment_reference'],
  'reported_order'=>$data['order'],'response_code'=>$data['response_code'],
  'amount_cents'=>$data['amount_cents'],'flags'=>$flags,
  'notification_consistent'=>count($flags)===0,
  'refund_confirmed'=>false,'requires_acquirer_reconciliation'=>true,
  'read_only'=>true
 ];
}

/**
 * Informe de incidencias de devoluciones Redsys/Bizum, solo lectura.
 * Los estados locales no equivalen a prueba de liquidación bancaria.
 */
function ucoRedsys622Audit(PDO $db,int $limit=100,int $offset=0):array {
 $limit=max(1,min(200,$limit));$offset=max(0,$offset);
 $q=$db->prepare("SELECT r.id,r.payment_reference,r.provider,r.status,r.amount_cents,
 r.currency,r.provider_refund_id,r.created_at,
 p.state AS payment_state,p.amount_cents AS payment_amount,p.provider_id AS payment_order,
 o.status AS order_status
 FROM uco_refund_intents_615 r
 LEFT JOIN uco_real_payments p ON p.reference=r.payment_reference
 LEFT JOIN uco_orders o ON o.id=p.order_id
 WHERE r.provider IN ('redsys','bizum')
 ORDER BY r.id DESC LIMIT :limit OFFSET :offset");
 $q->bindValue(':limit',$limit,PDO::PARAM_INT);
 $q->bindValue(':offset',$offset,PDO::PARAM_INT);$q->execute();
 $items=[];
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
  $flags=[];
  if($r['payment_state']!=='paid')$flags[]='original_payment_not_confirmed';
  if($r['currency']!=='EUR')$flags[]='currency_mismatch';
  if((int)$r['amount_cents']<=0)$flags[]='invalid_amount';
  if($r['status']==='succeeded')$flags[]='local_success_not_bank_proof';
  if(in_array($r['status'],['submitted','requires_review'],true))$flags[]='pending_bank_reconciliation';
  if(empty($r['provider_refund_id']))$flags[]='refund_provider_reference_missing';
  $items[]=['intent_id'=>(int)$r['id'],'payment_reference'=>$r['payment_reference'],
   'provider'=>$r['provider'],'status'=>$r['status'],
   'amount_cents'=>(int)$r['amount_cents'],'flags'=>$flags,
   'bank_verified'=>false,'requires_acquirer_reconciliation'=>true];
 }
 return ['read_only'=>true,'items'=>$items,'count'=>count($items),
  'notice'=>'Redsys/Bizum: auditoría local, no confirma reembolsos ni consulta al adquirente.'];
}
