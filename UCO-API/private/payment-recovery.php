<?php
declare(strict_types=1);
require_once __DIR__.'/payment-reconciliation.php';
/** Safe recovery: verify completed captures with PayPal, never charge/capture from this job. */
function ucoPaymentRecoverPaypal(PDO $db,array $config,int $limit=50,bool $apply=false):array {
 $limit=max(1,min(200,$limit));
 $q=$db->query("SELECT p.reference,p.provider_id,p.amount_cents,p.state,p.order_id,p.created_at,o.status AS order_status,o.expires_at FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id WHERE p.method='paypal' AND p.state IN ('pending','review') ORDER BY p.id ASC LIMIT ".$limit);
 $out=[];
 foreach($q->fetchAll() as $p){
  $row=['reference'=>$p['reference'],'local_state'=>$p['state'],'order_status'=>$p['order_status'],'action'=>'manual_review'];
  if(empty($config['enabled'])||empty($config['paypal']['enabled'])||empty($p['provider_id'])){$row['reason']='provider_not_configured';$out[]=$row;continue;}
  try {
   $id=(string)$p['provider_id'];if(!preg_match('/^[A-Z0-9]{10,30}$/D',$id))throw new RuntimeException('invalid_provider_id');
   $token=ucoPaypalToken($config['paypal']);
   $remote=ucoPaymentHttp(ucoPaypalBase($config['paypal']).'/v2/checkout/orders/'.rawurlencode($id),'GET',['Authorization: Bearer '.$token]);
   $row['provider_state']=(string)($remote['status']??'UNKNOWN');
   $unit=$remote['purchase_units'][0]??[];
   if((string)($remote['id']??'')!==$id||(string)($unit['custom_id']??'')!==(string)$p['reference']||($unit['amount']['currency_code']??'')!=='EUR'||(string)($unit['amount']['value']??'')!==number_format((int)$p['amount_cents']/100,2,'.','')){$row['reason']='order_mismatch';$out[]=$row;continue;}
   $captures=$unit['payments']['captures']??[];$verified=[];
   foreach($captures as $capture){
    if(($capture['status']??'')!=='COMPLETED')continue;
    $captureId=(string)($capture['id']??'');if(!preg_match('/^[A-Z0-9]{10,30}$/D',$captureId))continue;
    $detail=ucoPaymentHttp(ucoPaypalBase($config['paypal']).'/v2/payments/captures/'.rawurlencode($captureId),'GET',['Authorization: Bearer '.$token]);
    if(($detail['id']??'')===$captureId&&($detail['status']??'')==='COMPLETED'&&($detail['custom_id']??'')===$p['reference']&&($detail['amount']['currency_code']??'')==='EUR'&&(string)($detail['amount']['value']??'')===number_format((int)$p['amount_cents']/100,2,'.',''))$verified[]=$captureId;
   }
   if(count($verified)!==1){$row['reason']=count($verified)>1?'multiple_captures_manual_review':'no_verified_capture';$out[]=$row;continue;}
   $row['verified_capture_id']=$verified[0];
   if($p['state']!=='pending'||$p['order_status']!=='pending_payment'){$row['reason']='order_or_payment_not_settleable';$out[]=$row;continue;}
   if(!$apply){$row['action']='eligible_for_settlement_dry_run';$out[]=$row;continue;}
   $result=ucoPaymentSettle($db,(string)$p['reference'],$id,'recovery-paypal-'.$verified[0]);
   $row['action']=in_array($result,['paid','already_paid'],true)?'settled':'manual_review';$row['result']=$result;
  }catch(Throwable $e){error_log('UCO recovery '.$p['reference'].': '.$e->getMessage());$row['reason']='provider_lookup_failed';}
  $out[]=$row;
 }
 return $out;
}
/** Audit expired pending operations; never cancel an unverified charge. */
function ucoPaymentExpiredAudit(PDO $db,int $limit=100):array {
 $limit=max(1,min(500,$limit));
 $q=$db->query("SELECT p.reference,p.method,p.state,o.status AS order_status,o.expires_at FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id WHERE p.state IN ('pending','review') AND o.expires_at IS NOT NULL AND o.expires_at < strftime('%s','now') ORDER BY o.expires_at ASC LIMIT ".$limit);
 $rows=[];foreach($q->fetchAll() as $p)$rows[]=['reference'=>$p['reference'],'method'=>$p['method'],'state'=>$p['state'],'order_status'=>$p['order_status'],'action'=>'verify_provider_before_expiration'];return $rows;
}
