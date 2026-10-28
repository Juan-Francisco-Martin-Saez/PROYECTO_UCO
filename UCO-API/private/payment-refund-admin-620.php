<?php
declare(strict_types=1);
/** Informe administrativo de solo lectura; no expone datos bancarios ni credenciales. */
function ucoRefund620Report(PDO $db, string $status='all', int $limit=100, int $offset=0): array {
    $allowed=['all','prepared','submitted','succeeded','failed','requires_review'];
    if(!in_array($status,$allowed,true)) throw new InvalidArgumentException('Estado inválido');
    $limit=max(1,min(200,$limit));$offset=max(0,$offset);
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $where=$status==='all'?'':' WHERE r.status=:status';
    $sql='SELECT r.id,r.payment_reference,r.provider,r.amount_cents,r.currency,r.status,
        r.provider_refund_id,r.actor,r.reason,r.created_at,r.updated_at,
        p.state AS payment_state,o.status AS order_status,
        (SELECT COUNT(*) FROM uco_refund_verifications_619 v WHERE v.intent_id=r.id) AS verification_count,
        (SELECT MAX(v.verified_at) FROM uco_refund_verifications_619 v WHERE v.intent_id=r.id) AS last_verified_at
        FROM uco_refund_intents_615 r
        LEFT JOIN uco_real_payments p ON p.reference=r.payment_reference
        LEFT JOIN uco_orders o ON o.id=p.order_id'.$where.' ORDER BY r.id DESC LIMIT :limit OFFSET :offset';
    $q=$db->prepare($sql);
    if($status!=='all')$q->bindValue(':status',$status,PDO::PARAM_STR);
    $q->bindValue(':limit',$limit,PDO::PARAM_INT);
    $q->bindValue(':offset',$offset,PDO::PARAM_INT);
    $q->execute();$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$r){
        $r['id']=(int)$r['id'];$r['amount_cents']=(int)$r['amount_cents'];
        $r['verification_count']=(int)$r['verification_count'];
        $r['needs_attention']=in_array($r['status'],['failed','requires_review','submitted'],true)
            ||($r['status']==='succeeded'&&$r['verification_count']===0);
        $r['bank_verified']=false; // El conteo de eventos no acredita el resultado: consultar la última verificación autenticada.
        // Nunca atribuir confirmación bancaria por el simple estado local o por contar eventos.
    }unset($r);
    $count=$db->prepare('SELECT COUNT(*) FROM uco_refund_intents_615 r'.$where);
    if($status!=='all')$count->bindValue(':status',$status,PDO::PARAM_STR);
    $count->execute();
    return ['read_only'=>true,'status_filter'=>$status,'total'=>(int)$count->fetchColumn(),
        'limit'=>$limit,'offset'=>$offset,'items'=>$rows,
        'notice'=>'El informe es interno. No ejecuta reembolsos ni confirma Redsys/Bizum.'];
}
