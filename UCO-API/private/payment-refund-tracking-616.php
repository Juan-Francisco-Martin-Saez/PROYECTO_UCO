<?php
declare(strict_types=1);
/**
 * Fase 6.16: seguimiento auditable de devoluciones.
 * Los eventos no mueven dinero ni modifican el estado bancario del reembolso.
 * Solo se permiten evidencias manuales pendientes de verificación.
 */
function ucoRefund616Schema(PDO $db):void {
    $db->exec("CREATE TABLE IF NOT EXISTS uco_refund_events_616 (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        intent_id INTEGER NOT NULL,
        event_type TEXT NOT NULL CHECK(event_type IN ('note','provider_reference','review_required','verification_requested')),
        evidence_ref TEXT,
        detail TEXT NOT NULL,
        actor TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(intent_id) REFERENCES uco_refund_intents_615(id)
    )");
    $db->exec('CREATE INDEX IF NOT EXISTS idx_uco_refund_events_616_intent ON uco_refund_events_616(intent_id,id)');
}
function ucoRefund616Record(PDO $db,int $intentId,string $type,string $detail,string $actor,?string $evidenceRef=null):array {
    if($intentId<1)throw new InvalidArgumentException('ID inválido');
    if(!in_array($type,['note','provider_reference','review_required','verification_requested'],true))
        throw new InvalidArgumentException('Evento no permitido');
    $detail=trim($detail);$actor=trim($actor);
    if($detail===''||mb_strlen($detail)>1500||$actor===''||mb_strlen($actor)>120)
        throw new InvalidArgumentException('Detalle o actor inválido');
    if($evidenceRef!==null && (strlen($evidenceRef)>200 || preg_match('/[\x00-\x1F]/',$evidenceRef)))
        throw new InvalidArgumentException('Referencia de evidencia inválida');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('BEGIN IMMEDIATE');
    try {
        // No se crea el esquema automáticamente dentro de la transacción.
        $q=$db->prepare('SELECT id,status FROM uco_refund_intents_615 WHERE id=?');
        $q->execute([$intentId]);$intent=$q->fetch(PDO::FETCH_ASSOC);
        if(!$intent)throw new DomainException('Solicitud no encontrada');
        $q=$db->prepare('INSERT INTO uco_refund_events_616(intent_id,event_type,evidence_ref,detail,actor) VALUES(?,?,?,?,?)');
        $q->execute([$intentId,$type,$evidenceRef,$detail,$actor]);
        $eventId=(int)$db->lastInsertId();
        $db->commit();
        return ['event_id'=>$eventId,'intent_id'=>$intentId,'refund_status'=>$intent['status'],
            'bank_action_performed'=>false,'verified'=>false];
    } catch(Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
}
function ucoRefund616Report(PDO $db,int $limit=100):array {
    $limit=max(1,min(500,$limit));
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    $rows=$db->query('SELECT id,payment_reference,provider,amount_cents,currency,status,provider_refund_id,created_at,updated_at FROM uco_refund_intents_615 ORDER BY id DESC LIMIT '.$limit)->fetchAll();
    $q=$db->prepare('SELECT event_type,evidence_ref,detail,actor,created_at FROM uco_refund_events_616 WHERE intent_id=? ORDER BY id DESC LIMIT 20');
    foreach($rows as &$row) {
        $q->execute([(int)$row['id']]);$row['events']=$q->fetchAll();
        $row['requires_provider_verification']=in_array($row['status'],['prepared','submitted','requires_review'],true);
    }
    unset($row);
    return ['count'=>count($rows),'items'=>$rows,'read_only'=>true,
      'warning'=>'Los eventos son anotaciones, no confirmaciones de reintegro. No activar devoluciones reales sin conciliación con proveedor.'];
}
