<?php
declare(strict_types=1);
/**
 * Fase 6.29: agregador de incidencias existentes, solo lectura.
 * No hace llamadas a bancos, no modifica pedidos ni registra reembolsos.
 */
require_once __DIR__.'/payment-late-incidents-628.php';
require_once __DIR__.'/payment-redsys-linkage-624.php';

function ucoIncidents629HasTable(PDO $db,string $name):bool {
 $q=$db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
 $q->execute([$name]);return (bool)$q->fetchColumn();
}

function ucoIncidents629Report(PDO $db,int $limit=100,int $offset=0,?int $now=null):array {
 $limit=max(1,min(200,$limit));$offset=max(0,$offset);
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $requiredPayments=['uco_real_payments','uco_orders','uco_payment_events'];
 $requiredRefunds=['uco_refund_intents_615','uco_real_payments','uco_orders'];
 $missingPayments=array_values(array_filter($requiredPayments,fn($t)=>!ucoIncidents629HasTable($db,$t)));
 $missingRefunds=array_values(array_filter($requiredRefunds,fn($t)=>!ucoIncidents629HasTable($db,$t)));
 $sections=[];$warnings=[];
 if($missingPayments){
  $sections['late_payments']=['available'=>false,'missing_tables'=>$missingPayments,'items'=>[]];
  $warnings[]='No se puede consultar pagos tardíos: faltan tablas';
 }else{
  try{
   $data=ucoLate628Report($db,$limit,$offset,$now);
   $sections['late_payments']=['available'=>true,'items'=>$data['items'],
    'returned_count'=>count($data['items']),'count_scope'=>'page_only'];
  }catch(Throwable $e){
   $sections['late_payments']=['available'=>false,'items'=>[],'error'=>'query_failed'];
   $warnings[]='Consulta de pagos tardíos fallida: revisar esquema y logs internos';
  }
 }
 if($missingRefunds){
  $sections['redsys_bizum_refunds']=['available'=>false,'missing_tables'=>$missingRefunds,'items'=>[]];
  $warnings[]='No se puede consultar devoluciones: faltan tablas';
 }else{
  try{
   $data=ucoRedsys624Linkage($db,$limit,$offset);
   $sections['redsys_bizum_refunds']=['available'=>true,'items'=>$data['items'],
    'returned_count'=>count($data['items']),'count_scope'=>'page_only',
    'signed_event_table_present'=>$data['event_table_present']];
  }catch(Throwable $e){
   $sections['redsys_bizum_refunds']=['available'=>false,'items'=>[],'error'=>'query_failed'];
   $warnings[]='Consulta de devoluciones fallida: revisar esquema y logs internos';
  }
 }
 return ['phase'=>'6.29','read_only'=>true,'limit'=>$limit,'offset'=>$offset,
  'sections'=>$sections,'warnings'=>$warnings,
  'bank_verified'=>false,'automatic_resolution'=>false,
  'notice'=>'Resumen local de incidencias; no constituye conciliación bancaria. Los recuentos son por página, no totales globales.'];
}
