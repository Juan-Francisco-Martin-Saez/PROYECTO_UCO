<?php
declare(strict_types=1);
require_once __DIR__.'/payment-ledger.php';
/** Read-only provider reconciliation. Does not capture, settle, cancel or refund money. */
function ucoReconcileSnapshot(PDO $db,array $config,int $limit=100):array {
 $limit=max(1,min(500,$limit));
 $q=$db->query("SELECT p.reference,p.method,p.state,p.provider_id,p.amount_cents,p.created_at,o.status AS order_status FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id WHERE p.state IN ('pending','review') ORDER BY p.id ASC LIMIT ".$limit);
 $results=[];
 foreach($q->fetchAll() as $p){
  $row=['reference'=>$p['reference'],'method'=>$p['method'],'local_state'=>$p['state'],'order_status'=>$p['order_status'],'action'=>'pending_verification'];
  if($p['method']==='transfer'){$row['action']='verify_bank_statement_manually';$results[]=$row;continue;}
  if($p['method']==='redsys'||$p['method']==='bizum'){$row['action']='check_signed_redsys_notification_or_bank_reconciliation';$results[]=$row;continue;}
  if($p['method']!=='paypal'||empty($p['provider_id'])||empty($config['paypal']['enabled'])){$row['action']='provider_not_configured';$results[]=$row;continue;}
  try {
   $pc=$config['paypal'];$token=ucoPaypalToken($pc);
   $id=(string)$p['provider_id'];
   if(!preg_match('/^[A-Z0-9]{10,30}$/D',$id))throw new RuntimeException('Identificador PayPal inválido');
   $remote=ucoPaymentHttp(ucoPaypalBase($pc).'/v2/checkout/orders/'.rawurlencode($id),'GET',['Authorization: Bearer '.$token]);
   $row['provider_status']=(string)($remote['status']??'UNKNOWN');
   $row['action']=match($row['provider_status']){'COMPLETED'=>'verify_capture_and_reconcile','APPROVED'=>'capture_only_via_authorized_flow','VOIDED'=>'review_voided_order',default=>'await_provider_confirmation'};
   $unit=$remote['purchase_units'][0]??[];
   $remoteRef=(string)($unit['custom_id']??$unit['reference_id']??'');
   $remoteAmount=(string)($unit['amount']['value']??'');
   if($remoteRef!==$p['reference']||($unit['amount']['currency_code']??'')!=='EUR'||$remoteAmount!==number_format((int)$p['amount_cents']/100,2,'.',''))$row['action']='mismatch_manual_review';
  }catch(Throwable $e){$row['action']='provider_lookup_failed';error_log('UCO reconciliation '.$p['reference'].': '.$e->getMessage());}
  $results[]=$row;
 }
 return $results;
}
