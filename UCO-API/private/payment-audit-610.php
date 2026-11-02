<?php
declare(strict_types=1);
/**
 * Fase 6.10: auditoría NO MUTANTE del circuito de pagos.
 * No carga payment-ledger.php porque su apertura crea tablas.
 */
function ucoAudit610(PDO $db):array {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tables=[];
    foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table'") as $r) $tables[$r['name']]=true;
    $required=['uco_orders','uco_order_lines','products','uco_real_payments','uco_payment_events'];
    $missing=array_values(array_filter($required,static fn($t)=>!isset($tables[$t])));
    $issues=[];
    if($missing) {
        $issues[]=['severity'=>'blocker','code'=>'missing_tables','count'=>count($missing),'details'=>$missing];
        return ['ready'=>false,'issues'=>$issues,'summary'=>['checked'=>0,'blockers'=>1]];
    }
    $checks=[
        'paid_payment_unpaid_order'=>["SELECT COUNT(*) FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id WHERE p.state='paid' AND (o.payment_status<>'confirmed' OR o.status NOT IN ('paid','processing','shipped','completed'))",'blocker'],
        'confirmed_order_unpaid_payment'=>["SELECT COUNT(*) FROM uco_orders o JOIN uco_real_payments p ON p.order_id=o.id WHERE o.payment_status='confirmed' AND p.state<>'paid'",'blocker'],
        'pending_payment_wrong_order_state'=>["SELECT COUNT(*) FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id WHERE p.state='pending' AND o.status NOT IN ('pending_payment','paid','processing','shipped','completed')",'review'],
        'expired_pending_payments'=>["SELECT COUNT(*) FROM uco_real_payments p JOIN uco_orders o ON o.id=p.order_id WHERE p.state='pending' AND o.expires_at < CAST(strftime('%s','now') AS INTEGER)",'review'],
        'paid_without_event'=>["SELECT COUNT(*) FROM uco_real_payments p WHERE p.state='paid' AND p.method IN ('redsys','bizum','paypal') AND NOT EXISTS (SELECT 1 FROM uco_payment_events e WHERE e.reference=p.reference)",'blocker'],
        'payment_without_lines'=>["SELECT COUNT(*) FROM uco_real_payments p WHERE NOT EXISTS (SELECT 1 FROM uco_order_lines l WHERE l.order_id=p.order_id)",'blocker'],
        'payment_bad_amount'=>["SELECT COUNT(*) FROM uco_real_payments WHERE amount_cents<=0 OR currency<>'EUR'",'blocker'],
        'negative_stock'=>["SELECT COUNT(*) FROM products WHERE stock<0",'blocker'],
        'duplicate_provider_id'=>["SELECT COUNT(*) FROM (SELECT method,provider_id FROM uco_real_payments WHERE provider_id IS NOT NULL GROUP BY method,provider_id HAVING COUNT(*)>1)",'blocker'],
        'duplicate_provider_event'=>["SELECT COUNT(*) FROM (SELECT provider,event_id FROM uco_payment_events GROUP BY provider,event_id HAVING COUNT(*)>1)",'blocker']
    ];
    foreach($checks as $code=>[$sql,$severity]){
        $count=(int)$db->query($sql)->fetchColumn();
        if($count>0)$issues[]=['severity'=>$severity,'code'=>$code,'count'=>$count];
    }
    $blockers=count(array_filter($issues,static fn($x)=>$x['severity']==='blocker'));
    return ['ready'=>$blockers===0,'issues'=>$issues,'summary'=>['checked'=>count($checks),'blockers'=>$blockers],
      'warning'=>'Una auditoría sin incidencias NO valida pasarelas, webhooks, reembolsos ni reservas de stock. No autoriza activar cobros.'];
}
