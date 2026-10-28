<?php
declare(strict_types=1);
require_once __DIR__.'/payment-ledger.php';
/** Fase 6.5: registro auditable de decisiones; ninguna llamada bancaria ni reembolso automático. */
function ucoPayment65Schema(PDO $db):void {
 ucoPaymentSchema($db);
 $db->exec("CREATE TABLE IF NOT EXISTS uco_payment_review_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, reference TEXT NOT NULL, kind TEXT NOT NULL CHECK(kind IN ('transfer_evidence','refund_request','cancel_request','late_payment','expiry_review')), status TEXT NOT NULL DEFAULT 'open' CHECK(status IN ('open','resolved','dismissed')), note TEXT NOT NULL DEFAULT '', evidence_ref TEXT, actor TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, resolved_at TEXT, UNIQUE(reference,kind,status))");
 $db->exec("CREATE INDEX IF NOT EXISTS idx_uco_payment_review_tasks_status ON uco_payment_review_tasks(status,kind)");
}
function ucoPayment65Queue(PDO $db,string $reference,string $kind,string $actor,string $note='',?string $evidenceRef=null):array {
 if(!in_array($kind,['transfer_evidence','refund_request','cancel_request','late_payment','expiry_review'],true))throw new InvalidArgumentException('Tipo no admitido');
 if(!preg_match('/^[A-Za-z0-9_.:@-]{3,128}$/D',$reference))throw new InvalidArgumentException('Referencia inválida');
 if(trim($actor)===''||strlen($actor)>120||strlen($note)>1500||($evidenceRef!==null&&strlen($evidenceRef)>255))throw new InvalidArgumentException('Campos inválidos');
 ucoPayment65Schema($db);
 $db->exec('BEGIN IMMEDIATE');
 try {
  $q=$db->prepare('SELECT p.method,p.state,o.status AS order_status,p.amount_cents FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id WHERE p.reference=?');$q->execute([$reference]);$p=$q->fetch();
  if(!$p)throw new RuntimeException('Operación inexistente');
  if($kind==='transfer_evidence'&&$p['method']!=='transfer')throw new RuntimeException('No es transferencia');
  if($kind==='refund_request'&&$p['state']!=='paid')throw new RuntimeException('No consta pago liquidado');
  if($kind==='cancel_request'&&$p['state']==='paid')throw new RuntimeException('Pago liquidado: revisar devolución en lugar de cancelar');
  $q=$db->prepare("SELECT id FROM uco_payment_review_tasks WHERE reference=? AND kind=? AND status='open'");$q->execute([$reference,$kind]);$id=$q->fetchColumn();
  if(!$id){$q=$db->prepare("INSERT INTO uco_payment_review_tasks(reference,kind,note,evidence_ref,actor) VALUES(?,?,?,?,?)");$q->execute([$reference,$kind,$note,$evidenceRef,$actor]);$id=(int)$db->lastInsertId();}
  $db->commit();return ['task_id'=>(int)$id,'reference'=>$reference,'kind'=>$kind,'payment_state'=>$p['state'],'order_status'=>$p['order_status'],'amount_cents'=>(int)$p['amount_cents'],'status'=>'open'];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function ucoPayment65List(PDO $db,int $limit=100):array {
 ucoPayment65Schema($db);$limit=max(1,min(500,$limit));
 return $db->query("SELECT t.*,p.method,p.state AS payment_state,o.status AS order_status,p.amount_cents FROM uco_payment_review_tasks t JOIN uco_real_payments p ON p.reference=t.reference JOIN uco_orders o ON o.id=p.order_id WHERE t.status='open' ORDER BY t.id ASC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
}
function ucoPayment65ExpiredCandidates(PDO $db,int $limit=100):array {
 ucoPayment65Schema($db);$limit=max(1,min(500,$limit));
 return $db->query("SELECT p.reference,p.method,p.state,o.status AS order_status,o.expires_at FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id WHERE p.state IN ('pending','review') AND o.expires_at < CAST(strftime('%s','now') AS INTEGER) ORDER BY o.expires_at ASC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
}
