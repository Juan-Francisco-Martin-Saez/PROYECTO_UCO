<?php
declare(strict_types=1);
require_once __DIR__.'/payment-redsys-reconcile-622.php';

/** Crea un registro de auditoría independiente, sin tocar el estado de devoluciones. */
function ucoRedsys623Schema(PDO $db):void {
 $db->exec("CREATE TABLE IF NOT EXISTS uco_redsys_refund_events_623 (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 intent_id INTEGER NOT NULL,
 event_key TEXT NOT NULL UNIQUE,
 provider TEXT NOT NULL CHECK(provider IN ('redsys','bizum')),
 reported_order TEXT NOT NULL,
 response_code TEXT NOT NULL,
 amount_cents INTEGER NOT NULL CHECK(amount_cents>0),
 flags_json TEXT NOT NULL,
 signature_verified INTEGER NOT NULL CHECK(signature_verified=1),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(intent_id) REFERENCES uco_refund_intents_615(id)
 )");
 $db->exec("CREATE INDEX IF NOT EXISTS idx_uco_redsys_refund_events_623_intent
 ON uco_redsys_refund_events_623(intent_id,created_at)");
}
/**
 * Solo se debe llamar desde un proceso interno autorizado.
 * Verifica firma y compara datos con la intención ANTES de registrar evento.
 * No devuelve una confirmación de reembolso bancario.
 */
function ucoRedsys623Record(PDO $db,array $config,string $parameters,string $signature,int $intentId):array {
 if($intentId<1)throw new InvalidArgumentException('Intención inválida');
 if(strlen($parameters)>16384||strlen($signature)>512)
  throw new InvalidArgumentException('Notificación demasiado grande');
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $comparison=ucoRedsys622CompareNotification($db,$config,$parameters,$signature,$intentId);
 // Rechazar operaciones que no sean devoluciones, firmas inválidas y cambios
 // de comercio/terminal. El resto se registra como incidencia, no como éxito.
 $fatal=['wrong_provider','not_refund_operation','merchant_mismatch','terminal_mismatch','currency_code_mismatch'];
 if(array_intersect($fatal,$comparison['flags']))
  throw new DomainException('Notificación no válida para esta intención');
 $eventKey=hash('sha256','redsys-refund-623|'.$intentId.'|'.$parameters.'|'.$signature);
 ucoRedsys623Schema($db);
 $db->exec('BEGIN IMMEDIATE');
 try {
  // Bloqueo transaccional y nueva comprobación de vinculación crítica.
  $q=$db->prepare("SELECT r.provider,r.amount_cents,r.payment_reference
   FROM uco_refund_intents_615 r WHERE r.id=?");
  $q->execute([$intentId]);$current=$q->fetch(PDO::FETCH_ASSOC);
  if(!$current||$current['provider']!==$comparison['provider']
   ||(int)$current['amount_cents']!==(int)$comparison['amount_cents'])
   throw new DomainException('Intención o importe cambiaron: requiere revisión');
  $q=$db->prepare("INSERT OR IGNORE INTO uco_redsys_refund_events_623
   (intent_id,event_key,provider,reported_order,response_code,amount_cents,flags_json,signature_verified)
   VALUES(?,?,?,?,?,?,?,1)");
  $q->execute([$intentId,$eventKey,$comparison['provider'],
   $comparison['reported_order'],$comparison['response_code'],
   $comparison['amount_cents'],json_encode($comparison['flags'],JSON_THROW_ON_ERROR)]);
  $inserted=$q->rowCount()===1;
  $db->commit();
  return ['intent_id'=>$intentId,'new_event'=>$inserted,'event_key'=>$eventKey,
   'flags'=>$comparison['flags'],'refund_confirmed'=>false,
   'payment_or_order_modified'=>false,'requires_acquirer_reconciliation'=>true];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function ucoRedsys623Audit(PDO $db,int $limit=100):array {
 $limit=max(1,min(200,$limit));
 $q=$db->query("SELECT id,intent_id,provider,reported_order,response_code,
 amount_cents,flags_json,created_at FROM uco_redsys_refund_events_623
 ORDER BY id DESC LIMIT ".$limit);
 return ['read_only'=>true,'events'=>$q->fetchAll(PDO::FETCH_ASSOC),
 'notice'=>'Los eventos firmados no acreditan el abono final al comprador.'];
}
