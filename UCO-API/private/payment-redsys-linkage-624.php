<?php
declare(strict_types=1);
/**
 * Fase 6.24: informe de vínculos entre pago original, intención de devolución
 * y notificaciones registradas. SOLO LECTURA. No afirma liquidación bancaria.
 */
function ucoRedsys624Linkage(PDO $db,int $limit=100,int $offset=0):array {
 $limit=max(1,min(200,$limit));$offset=max(0,$offset);
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
 $hasEvents=(bool)$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='uco_redsys_refund_events_623'")->fetchColumn();
 $sql="SELECT r.id,r.payment_reference,r.provider,r.amount_cents,r.currency,r.status,
 r.provider_refund_id,r.created_at,
 p.provider_id AS original_provider_id,p.method AS payment_method,
 p.state AS payment_state,p.amount_cents AS original_amount_cents,
 p.currency AS original_currency,o.id AS order_id,o.status AS order_status
 FROM uco_refund_intents_615 r
 LEFT JOIN uco_real_payments p ON p.reference=r.payment_reference
 LEFT JOIN uco_orders o ON o.id=p.order_id
 WHERE r.provider IN ('redsys','bizum')
 ORDER BY r.id DESC LIMIT :lim OFFSET :off";
 $q=$db->prepare($sql);
 $q->bindValue(':lim',$limit,PDO::PARAM_INT);
 $q->bindValue(':off',$offset,PDO::PARAM_INT);
 $q->execute();$items=[];
 $eventsStmt=$hasEvents?$db->prepare(
  "SELECT reported_order,response_code,amount_cents,flags_json,created_at
   FROM uco_redsys_refund_events_623 WHERE intent_id=?
   ORDER BY id DESC LIMIT 20"
 ):null;
 foreach($q->fetchAll() as $r){
  $flags=[];
  if($r['payment_method']===null)$flags[]='original_payment_missing';
  else{
   if($r['payment_method']!==$r['provider'])$flags[]='provider_mismatch';
   if($r['payment_state']!=='paid')$flags[]='original_payment_not_paid';
   if($r['original_currency']!=='EUR'||$r['currency']!=='EUR')$flags[]='currency_mismatch';
   if((int)$r['amount_cents']>(int)$r['original_amount_cents'])$flags[]='refund_exceeds_original_payment';
   if(empty($r['original_provider_id']))$flags[]='original_provider_reference_missing';
  }
  $events=[];
  if($eventsStmt){$eventsStmt->execute([(int)$r['id']]);$events=$eventsStmt->fetchAll();}
  foreach($events as $event){
   $eventFlags=json_decode((string)$event['flags_json'],true);
   if(!is_array($eventFlags)){$flags[]='unreadable_event_flags';continue;}
   foreach($eventFlags as $flag)$flags[]='event:'.(string)$flag;
   if((int)$event['amount_cents']!==(int)$r['amount_cents'])$flags[]='event_amount_mismatch';
   if($r['original_provider_id']!==$event['reported_order'])$flags[]='refund_order_not_linked_to_original';
  }
  if(!$events)$flags[]='no_signed_refund_notification_registered';
  if($r['status']==='succeeded')$flags[]='local_success_not_bank_proof';
  $items[]=[
   'intent_id'=>(int)$r['id'],'payment_reference'=>$r['payment_reference'],
   'order_id'=>$r['order_id']===null?null:(int)$r['order_id'],
   'provider'=>$r['provider'],'refund_status'=>$r['status'],
   'original_provider_id'=>$r['original_provider_id'],
   'provider_refund_id'=>$r['provider_refund_id'],
   'refund_amount_cents'=>(int)$r['amount_cents'],
   'original_amount_cents'=>$r['original_amount_cents']===null?null:(int)$r['original_amount_cents'],
   'signed_events_sampled'=>count($events),
   'flags'=>array_values(array_unique($flags)),
   'verified_refund'=>false,'requires_acquirer_reconciliation'=>true
  ];
 }
 return ['read_only'=>true,'event_table_present'=>$hasEvents,
  'items'=>$items,'count'=>count($items),
  'note'=>'Las referencias de pedido de devolución pueden diferir de las originales según el adquirente. No se valida ni confirma el abono bancario. Se muestran hasta 20 eventos recientes por intención.'];
}
