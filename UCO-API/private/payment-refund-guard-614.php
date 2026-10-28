<?php
declare(strict_types=1);
require_once __DIR__.'/payment-operations-65.php';

/**
 * Fase 6.14. Prevalidación de cancelaciones/reembolsos.
 * NO ejecuta devoluciones ni cambia estados de pedidos.
 */
function ucoRefundGuard614(PDO $db,string $reference,string $operation):array {
 if(!in_array($operation,['refund','cancel'],true))throw new InvalidArgumentException('Operación inválida');
 if(!preg_match('/^[A-Za-z0-9_.:@-]{3,128}$/D',$reference))throw new InvalidArgumentException('Referencia inválida');
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $q=$db->prepare('SELECT p.reference,p.method,p.state,p.amount_cents,p.currency,p.provider_id,o.id AS order_id,o.status AS order_status,o.payment_status FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id WHERE p.reference=? LIMIT 1');
 $q->execute([$reference]);$payment=$q->fetch(PDO::FETCH_ASSOC);
 if(!$payment)throw new RuntimeException('Operación inexistente');
 $reason=null;
 if($operation==='refund'){
  if($payment['state']!=='paid')$reason='No consta pago liquidado';
  elseif($payment['method']==='transfer')$reason='La transferencia requiere devolución bancaria manual';
  elseif(empty($payment['provider_id']))$reason='Falta identificador de transacción del proveedor';
  elseif((int)$payment['amount_cents']<=0)$reason='Importe inválido';
 }else{
  if($payment['state']==='paid')$reason='Cobro confirmado: solicitar devolución, no cancelación directa';
  elseif(in_array($payment['order_status'],['processing','shipped','completed'],true))$reason='Pedido ya en preparación o expedición';
 }
 $taskType=$operation==='refund'?'refund_request':'cancel_request';
 // No crear esquema ni escribir durante la consulta.
 $exists=(bool)$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='uco_payment_review_tasks'")->fetchColumn();
 $open=false;
 if($exists){
  $q=$db->prepare("SELECT 1 FROM uco_payment_review_tasks WHERE reference=? AND kind=? AND status='open' LIMIT 1");
  $q->execute([$reference,$taskType]);$open=(bool)$q->fetchColumn();
 }
 return [
  'reference'=>$reference,'operation'=>$operation,
  'eligible_for_manual_review'=>$reason===null,
  'reason'=>$reason,'existing_open_request'=>$open,
  'method'=>$payment['method'],'payment_state'=>$payment['state'],
  'order_status'=>$payment['order_status'],
  'amount_cents'=>(int)$payment['amount_cents'],'currency'=>$payment['currency'],
  'warning'=>'Elegible para revisión no significa autorizado para devolución o cancelación bancaria.'
 ];
}
/** Crea únicamente una solicitud de revisión, mediante la cola idempotente 6.5. */
function ucoRefundRequest614(PDO $db,string $reference,string $operation,string $actor,string $reason):array {
 if(trim($reason)===''||mb_strlen($reason)>1000)throw new InvalidArgumentException('Motivo obligatorio (máximo 1000 caracteres)');
 $check=ucoRefundGuard614($db,$reference,$operation);
 if(!$check['eligible_for_manual_review'])throw new DomainException($check['reason']);
 $kind=$operation==='refund'?'refund_request':'cancel_request';
 return ucoPayment65Queue($db,$reference,$kind,$actor,trim($reason));
}
