<?php
declare(strict_types=1);
require_once __DIR__.'/payment-refund-verify-618.php';
/**
 * Reconsulta PayPal con OAuth y registra la verificación en una transacción.
 * No acepta como prueba un JSON recibido del navegador o webhook sin verificar.
 * No emite solicitudes de devolución ni cambia pedidos o existencias.
 */
function ucoRefund619ReconcilePaypal(PDO $db, array $paypalConfig, int $intentId): array {
    if ($intentId < 1) throw new InvalidArgumentException('ID inválido');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $q=$db->prepare('SELECT * FROM uco_refund_intents_615 WHERE id=?');
    $q->execute([$intentId]); $intent=$q->fetch(PDO::FETCH_ASSOC);
    if (!$intent || $intent['provider']!=='paypal') throw new DomainException('Intención PayPal inexistente');
    if (!in_array($intent['status'],['submitted','requires_review','succeeded'],true))
        throw new DomainException('La intención aún no está enviada o está cerrada');
    $refundId=(string)($intent['provider_refund_id']??'');
    if ($refundId==='') throw new DomainException('Falta ID de devolución del proveedor');
    // La consulta autenticada se realiza ANTES de bloquear SQLite.
    $verified=ucoRefund618PaypalFetch($paypalConfig,$refundId);
    $match=ucoRefund618MatchIntent($verified,$intent);
    if (!$match['matches']) throw new DomainException('La devolución no coincide con la intención');
    $target=match($verified['provider_status']) {
        'COMPLETED'=>'succeeded', 'PENDING'=>'submitted',
        'CANCELLED','FAILED'=>'requires_review',
        default=>throw new DomainException('Estado PayPal desconocido')
    };
    ucoRefund619Schema($db);
    $db->exec('BEGIN IMMEDIATE');
    try {
        $q=$db->prepare('SELECT * FROM uco_refund_intents_615 WHERE id=?');
        $q->execute([$intentId]);$current=$q->fetch(PDO::FETCH_ASSOC);
        if (!$current || $current['provider']!=='paypal' || $current['provider_refund_id']!==$refundId
            || (int)$current['amount_cents']!==(int)$verified['amount_cents']
            || $current['currency']!==$verified['currency'])
            throw new DomainException('La intención cambió durante la verificación');
        $previous=$current['status'];
        if ($previous==='succeeded' && $target!=='succeeded')
            throw new DomainException('Cambio regresivo: requiere revisión independiente');
        if (!in_array($previous,['submitted','requires_review','succeeded'],true))
            throw new DomainException('Transición de estado no permitida');
        // Una verificación idéntica puede repetirse sin duplicar el evento.
        $eventKey=hash('sha256', 'paypal|'.$refundId.'|'.$verified['provider_status']);
        $q=$db->prepare('INSERT OR IGNORE INTO uco_refund_verifications_619 (intent_id,provider_refund_id,provider_status,event_key,amount_cents,verified_at) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)');
        $q->execute([$intentId,$refundId,$verified['provider_status'],$eventKey,(int)$verified['amount_cents']]);
        $newEvent=$q->rowCount()===1;
        if ($previous!==$target) {
            $q=$db->prepare('UPDATE uco_refund_intents_615 SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status=?');
            $q->execute([$target,$intentId,$previous]);
            if ($q->rowCount()!==1) throw new RuntimeException('Actualización concurrente');
        }
        $db->commit();
        return ['intent_id'=>$intentId,'previous_status'=>$previous,'status'=>$target,
            'new_verification_event'=>$newEvent,'provider_refund_id'=>$refundId,
            'verified_via_authenticated_api'=>true,'order_modified'=>false];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
function ucoRefund619Schema(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS uco_refund_verifications_619 (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        intent_id INTEGER NOT NULL,
        provider_refund_id TEXT NOT NULL,
        provider_status TEXT NOT NULL,
        event_key TEXT NOT NULL UNIQUE,
        amount_cents INTEGER NOT NULL,
        verified_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(intent_id) REFERENCES uco_refund_intents_615(id)
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_uco_refund_verifications_619_intent ON uco_refund_verifications_619(intent_id)');
}
