<?php
declare(strict_types=1);
/**
 * Fase 6.17: conciliación de solo lectura de intenciones de reembolso.
 * No verifica autenticidad de respuestas bancarias ni altera estados.
 */
function ucoRefund617Report(PDO $db,int $limit=200):array {
 $limit=max(1,min(500,$limit));
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
 $sql="SELECT r.id,r.payment_reference,r.provider,r.capture_id,r.amount_cents,r.currency,
 r.idempotency_key,r.status,r.provider_refund_id,r.created_at,r.updated_at,
 p.method AS payment_method,p.state AS payment_state,p.amount_cents AS paid_cents,
 p.currency AS paid_currency,o.status AS order_status,o.payment_status AS order_payment_status
 FROM uco_refund_intents_615 r
 LEFT JOIN uco_real_payments p ON p.reference=r.payment_reference
 LEFT JOIN uco_orders o ON o.id=p.order_id
 ORDER BY r.id DESC LIMIT ".$limit;
 $rows=$db->query($sql)->fetchAll();$items=[];$issues=0;
 foreach($rows as $row){
  $flags=[];
  if($row['payment_method']===null)$flags[]='payment_missing';
  else {
   if($row['payment_state']!=='paid')$flags[]='payment_not_paid';
   if($row['provider']!==$row['payment_method'])$flags[]='provider_mismatch';
   if($row['currency']!==$row['paid_currency'])$flags[]='currency_mismatch';
   if($row['provider']==='paypal' && !$row['capture_id'])$flags[]='paypal_capture_missing';
   if($row['status']==='succeeded' && !$row['provider_refund_id'])$flags[]='success_without_provider_ref';
  }
  if($row['status']==='submitted')$flags[]='submitted_requires_provider_check';
  if($row['status']==='requires_review')$flags[]='manual_review_required';
  if($flags)$issues++;
  $items[]=['intent_id'=>(int)$row['id'],'payment_reference'=>$row['payment_reference'],
   'provider'=>$row['provider'],'amount_cents'=>(int)$row['amount_cents'],
   'refund_status'=>$row['status'],'provider_refund_id'=>$row['provider_refund_id'],
   'order_status'=>$row['order_status'],'flags'=>$flags,
   'verified_by_provider'=>false];
 }
 $totals=$db->query("SELECT r.payment_reference,
 SUM(CASE WHEN r.status='succeeded' THEN r.amount_cents ELSE 0 END) AS completed_cents,
 SUM(CASE WHEN r.status IN ('prepared','submitted','requires_review') THEN r.amount_cents ELSE 0 END) AS reserved_cents,
 MAX(p.amount_cents) AS paid_cents
 FROM uco_refund_intents_615 r
 LEFT JOIN uco_real_payments p ON p.reference=r.payment_reference
 GROUP BY r.payment_reference")->fetchAll();
 $over=[];
 foreach($totals as $t){
  if((int)$t['completed_cents']+(int)$t['reserved_cents']>(int)$t['paid_cents'])
   $over[]=['payment_reference'=>$t['payment_reference'],'completed_cents'=>(int)$t['completed_cents'],
    'reserved_cents'=>(int)$t['reserved_cents'],'paid_cents'=>(int)$t['paid_cents']];
 }
 return ['read_only'=>true,'checked'=>count($items),'flagged'=>$issues,
 'overallocated_references'=>$over,'items'=>$items,
 'notice'=>'No verifica firmas ni consulta proveedores. succeeded es un estado local, no prueba bancaria.'];
}
