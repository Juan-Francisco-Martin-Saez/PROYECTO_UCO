<?php
declare(strict_types=1);
require_once __DIR__.'/payment-operations-65.php';

/**
 * Clasifica operaciones para revisión humana. No confirma ni revierte cobros.
 * La inserción es idempotente: una sola tarea abierta por referencia y tipo.
 */
function ucoLateReview613(PDO $db,int $limit=100,bool $apply=false):array {
    $limit=max(1,min(500,$limit));
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    // Asegurar esquema solo al aplicar: diagnóstico no debe alterar la BD.
    if($apply)ucoPayment65Schema($db);
    $sql="SELECT p.reference,p.method,p.state,p.amount_cents,o.status AS order_status,
                 o.payment_status,o.expires_at
          FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id
          WHERE (p.state='review')
             OR (p.state='pending' AND o.expires_at<=CAST(strftime('%s','now') AS INTEGER))
             OR (p.state='paid' AND (o.payment_status<>'confirmed'
                 OR o.status NOT IN ('paid','processing','shipped','completed')))
          ORDER BY p.id ASC LIMIT ".$limit;
    $rows=$db->query($sql)->fetchAll();
    $result=[];
    foreach($rows as $row){
        $reason=match(true){
            $row['state']==='paid'=>'paid_order_mismatch',
            $row['state']==='review'=>'payment_requires_reconciliation',
            default=>'expired_unconfirmed_payment'
        };
        $kind=($reason==='expired_unconfirmed_payment')?'expiry_review':'late_payment';
        $entry=['reference'=>$row['reference'],'method'=>$row['method'],
          'payment_state'=>$row['state'],'order_status'=>$row['order_status'],
          'amount_cents'=>(int)$row['amount_cents'],'reason'=>$reason,
          'action'=>'manual_provider_or_bank_verification','queued'=>false];
        if($apply){
            // No almacenar datos bancarios ni justificar un cobro con un simple evento.
            $task=ucoPayment65Queue($db,(string)$row['reference'],$kind,
                'system:phase-6.13','Revisión automática: '.$reason);
            $entry['queued']=true;
            $entry['task_id']=$task['task_id'];
        }
        $result[]=$entry;
    }
    return ['dry_run'=>!$apply,'count'=>count($result),'items'=>$result,
      'notice'=>'No se han confirmado cobros, reembolsos ni cancelaciones. Las tareas son solo avisos.'];
}
