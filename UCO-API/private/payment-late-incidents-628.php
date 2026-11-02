<?php
declare(strict_types=1);
/**
 * Fase 6.28: inventario de incidencias por pago tardío, sin escrituras.
 * No deduce que un cobro se haya producido por existir un evento local.
 */
function ucoLate628Report(PDO $db,int $limit=100,int $offset=0,?int $now=null):array {
 $limit=max(1,min(200,$limit));$offset=max(0,$offset);$now=$now??time();
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
 $q=$db->prepare("SELECT p.id AS payment_id,p.reference,p.method,p.state AS payment_state,
  p.provider_id,p.amount_cents,p.currency,p.updated_at,
  o.id AS order_id,o.status AS order_status,o.payment_status AS order_payment_status,
  o.expires_at,o.subtotal_cents,
  (SELECT COUNT(*) FROM uco_payment_events e WHERE e.reference=p.reference AND e.provider=p.method) AS local_event_count
  FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id
  WHERE (p.state='review')
     OR (p.state='pending' AND o.expires_at<=:now)
     OR (p.state='paid' AND (o.payment_status<>'confirmed' OR o.status NOT IN ('paid','processing','shipped','completed')))
     OR (o.status IN ('expired','cancelled') AND p.state IN ('pending','paid'))
  ORDER BY p.id ASC LIMIT :limit OFFSET :offset");
 $q->bindValue(':now',$now,PDO::PARAM_INT);
 $q->bindValue(':limit',$limit,PDO::PARAM_INT);
 $q->bindValue(':offset',$offset,PDO::PARAM_INT);
 $q->execute();$items=[];
 foreach($q->fetchAll() as $r){
  $flags=[];
  if((int)$r['expires_at']<=$now)$flags[]='reservation_expired';
  if($r['payment_state']==='review')$flags[]='payment_review';
  if($r['payment_state']==='pending' && (int)$r['expires_at']<=$now)$flags[]='pending_after_expiry';
  if(in_array($r['order_status'],['expired','cancelled'],true))$flags[]='order_closed';
  if($r['payment_state']==='paid' && $r['order_payment_status']!=='confirmed')$flags[]='paid_status_mismatch';
  if($r['payment_state']==='paid' && !in_array($r['order_status'],['paid','processing','shipped','completed'],true))$flags[]='paid_order_mismatch';
  if((int)$r['amount_cents']!==(int)$r['subtotal_cents'])$flags[]='amount_mismatch';
  if($r['currency']!=='EUR')$flags[]='unexpected_currency';
  if(empty($r['provider_id']))$flags[]='provider_reference_missing';
  $items[]=[
   'payment_id'=>(int)$r['payment_id'],'order_id'=>(int)$r['order_id'],
   'reference'=>$r['reference'],'provider'=>$r['method'],
   'payment_state'=>$r['payment_state'],'order_status'=>$r['order_status'],
   'order_payment_status'=>$r['order_payment_status'],
   'expires_at'=>(int)$r['expires_at'],'amount_cents'=>(int)$r['amount_cents'],
   'local_event_count'=>(int)$r['local_event_count'],
   'flags'=>$flags,'recommended_action'=>'verify_directly_with_provider_then_manual_reconciliation',
   'provider_payment_verified'=>false,'auto_prepare_order'=>false,'auto_refund'=>false
  ];
 }
 return ['phase'=>'6.28','read_only'=>true,'count'=>count($items),'items'=>$items,
  'notice'=>'Informe local: no acredita cobro bancario. No reintenta pagos, no devuelve dinero y no modifica pedidos ni stock.'];
}
