<?php
declare(strict_types=1);
/**
 * Fase 6.15: libro de solicitudes de devolución idempotentes.
 * No efectúa solicitudes HTTP a bancos. Nunca asume que PayPal order_id
 * es un capture_id; se exige capture_id verificado externamente.
 */
function ucoRefund615Schema(PDO $db):void {
 $db->exec("CREATE TABLE IF NOT EXISTS uco_refund_intents_615 (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 payment_reference TEXT NOT NULL,
 provider TEXT NOT NULL CHECK(provider IN ('paypal','redsys','bizum','transfer')),
 capture_id TEXT,
 amount_cents INTEGER NOT NULL CHECK(amount_cents>0),
 currency TEXT NOT NULL DEFAULT 'EUR' CHECK(currency='EUR'),
 idempotency_key TEXT NOT NULL UNIQUE,
 status TEXT NOT NULL DEFAULT 'prepared' CHECK(status IN ('prepared','submitted','succeeded','failed','requires_review')),
 provider_refund_id TEXT UNIQUE,
 actor TEXT NOT NULL,
 reason TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(payment_reference,idempotency_key)
 )");
 $db->exec("CREATE INDEX IF NOT EXISTS idx_uco_refund_intents_615_reference ON uco_refund_intents_615(payment_reference,status)");
}
/**
 * Solo registra una intención. No se ejecuta ninguna devolución.
 * La clave se suministra por el llamador y debe reutilizarse en reintentos.
 */
function ucoRefund615Prepare(PDO $db,string $reference,int $amountCents,string $key,string $actor,string $reason,?string $captureId=null):array {
 if(!preg_match('/^[A-Za-z0-9_.:@-]{3,128}$/D',$reference))throw new InvalidArgumentException('Referencia inválida');
 if(!preg_match('/^[A-Za-z0-9_.:@-]{12,128}$/D',$key))throw new InvalidArgumentException('Clave de idempotencia inválida');
 if($amountCents<=0)throw new InvalidArgumentException('Importe inválido');
 if(trim($actor)===''||strlen($actor)>120||trim($reason)===''||strlen($reason)>1000)throw new InvalidArgumentException('Actor o motivo inválido');
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 ucoRefund615Schema($db);
 $db->exec('BEGIN IMMEDIATE');
 try {
  $q=$db->prepare('SELECT p.reference,p.method,p.state,p.amount_cents,p.currency,o.status AS order_status FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id WHERE p.reference=?');
  $q->execute([$reference]);$p=$q->fetch(PDO::FETCH_ASSOC);
  if(!$p||$p['state']!=='paid'||$p['currency']!=='EUR')throw new DomainException('Pago no liquidado o no encontrado');
  $q=$db->prepare('SELECT * FROM uco_refund_intents_615 WHERE idempotency_key=?');$q->execute([$key]);$existing=$q->fetch(PDO::FETCH_ASSOC);
  if($existing){
   if($existing['payment_reference']!==$reference||(int)$existing['amount_cents']!==$amountCents||$existing['provider']!==$p['method']
      ||($p['method']==='paypal' && (string)$existing['capture_id']!==(string)$captureId))
    throw new DomainException('Clave reutilizada para otra operación');
   $db->commit();
   return ['id'=>(int)$existing['id'],'status'=>$existing['status'],'existing'=>true];
  }
  $q=$db->prepare("SELECT COALESCE(SUM(amount_cents),0) FROM uco_refund_intents_615 WHERE payment_reference=? AND status IN ('prepared','submitted','succeeded','requires_review')");
  $q->execute([$reference]);$reserved=(int)$q->fetchColumn();
  if($amountCents+$reserved>(int)$p['amount_cents'])throw new DomainException('La devolución supera el importe cobrado');
  if($p['method']==='paypal'&&(!$captureId||!preg_match('/^[A-Za-z0-9-]{8,80}$/D',$captureId)))
   throw new DomainException('PayPal requiere identificador CAPTURE verificado, no ORDER ID');
  $q=$db->prepare("INSERT INTO uco_refund_intents_615(payment_reference,provider,capture_id,amount_cents,idempotency_key,actor,reason) VALUES(?,?,?,?,?,?,?)");
  $q->execute([$reference,$p['method'],$captureId,$amountCents,$key,trim($actor),trim($reason)]);
  $id=(int)$db->lastInsertId();
  $db->commit();
  return ['id'=>$id,'status'=>'prepared','existing'=>false,'bank_request_sent'=>false];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
/**
 * Constructor de la petición PayPal. No realiza HTTP ni cambia estados.
 * POST /v2/payments/captures/{capture_id}/refund
 */
function ucoRefund615PaypalRequest(array $intent):array {
 if(($intent['provider']??'')!=='paypal'||($intent['status']??'')!=='prepared')
  throw new DomainException('Solicitud no preparada para PayPal');
 $capture=(string)($intent['capture_id']??'');
 if(!preg_match('/^[A-Za-z0-9-]{8,80}$/D',$capture))throw new DomainException('Capture ID inválido');
 $key=(string)($intent['idempotency_key']??'');
 if(!preg_match('/^[A-Za-z0-9_.:@-]{12,128}$/D',$key))throw new DomainException('Clave inválida');
 return [
  'path'=>'/v2/payments/captures/'.rawurlencode($capture).'/refund',
  'method'=>'POST',
  'idempotency_header'=>'PayPal-Request-Id',
  'idempotency_value'=>$key,
  'body'=>['amount'=>['value'=>number_format((int)$intent['amount_cents']/100,2,'.',''),'currency_code'=>'EUR']]
 ];
}
