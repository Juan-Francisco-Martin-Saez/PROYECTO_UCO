<?php
declare(strict_types=1);
/**
 * Fase 6.11: diagnóstico de consistencia de stock y reservas.
 * Compatible con el esquema real uco_orders / uco_order_lines / products.
 * Sin escrituras ni dependencias de las pasarelas.
 */
function ucoPaymentConsistency611(PDO $db):array {
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    $existing=[];
    foreach($db->query("SELECT name FROM sqlite_master WHERE type='table'") as $row) $existing[$row['name']]=true;
    $required=['uco_orders','uco_order_lines','products'];
    $missing=array_values(array_filter($required,static fn(string $t):bool=>!isset($existing[$t])));
    if($missing)return ['safe_to_enable'=>false,'missing_tables'=>$missing,'checks'=>[],
        'warning'=>'No activar pagos: faltan tablas necesarias'];
    $queries=[
      'stock_negativo'=>[
       'sql'=>"SELECT COUNT(*) FROM products WHERE stock<0",'level'=>'blocker'],
      'reserva_excede_stock'=>[
       'sql'=>"SELECT COUNT(*) FROM (SELECT p.id FROM products p JOIN uco_order_lines l ON l.product_id=p.id JOIN uco_orders o ON o.id=l.order_id WHERE o.status='reserved' AND o.expires_at>CAST(strftime('%s','now') AS INTEGER) GROUP BY p.id,p.stock HAVING SUM(l.quantity)>p.stock)",'level'=>'blocker'],
      'reservas_caducadas_activas'=>[
       'sql'=>"SELECT COUNT(*) FROM uco_orders WHERE status='reserved' AND expires_at<=CAST(strftime('%s','now') AS INTEGER)",'level'=>'review'],
      'pedidos_sin_lineas'=>[
       'sql'=>"SELECT COUNT(*) FROM uco_orders o WHERE o.status IN ('reserved','pending_payment','paid','processing','shipped','completed') AND NOT EXISTS(SELECT 1 FROM uco_order_lines l WHERE l.order_id=o.id)",'level'=>'blocker'],
      'pedidos_pendientes_caducados'=>[
       'sql'=>"SELECT COUNT(*) FROM uco_orders WHERE status='pending_payment' AND expires_at<=CAST(strftime('%s','now') AS INTEGER)",'level'=>'review'],
      'cobrados_no_confirmados'=>[
       'sql'=>"SELECT COUNT(*) FROM uco_orders WHERE status IN ('paid','processing','shipped','completed') AND payment_status<>'confirmed'",'level'=>'blocker'],
      'confirmados_sin_estado_cobrado'=>[
       'sql'=>"SELECT COUNT(*) FROM uco_orders WHERE payment_status='confirmed' AND status NOT IN ('paid','processing','shipped','completed')",'level'=>'blocker'],
      'lineas_con_cantidad_invalida'=>[
       'sql'=>"SELECT COUNT(*) FROM uco_order_lines WHERE quantity<=0 OR line_cents<0",'level'=>'blocker'],
    ];
    $checks=[];$blockers=0;
    foreach($queries as $code=>$def){
       $count=(int)$db->query($def['sql'])->fetchColumn();
       $checks[]=['code'=>$code,'level'=>$def['level'],'count'=>$count];
       if($count>0 && $def['level']==='blocker')$blockers++;
    }
    // Los pagos reales pueden no estar instalados. No creamos tablas.
    $checks[]=['code'=>'tabla_pagos_reales_presente','level'=>'info','count'=>isset($existing['uco_real_payments'])?1:0];
    return [
      'safe_to_enable'=>false,
      'checks'=>$checks,
      'blocker_types'=>$blockers,
      'warning'=>'Este informe es de solo lectura. No valida las confirmaciones de Redsys/PayPal ni autoriza activar cobros. El motor actual necesita revisar especialmente reservas pending_payment y el descuento de stock.'
    ];
}
