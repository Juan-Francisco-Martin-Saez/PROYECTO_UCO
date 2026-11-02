<?php
declare(strict_types=1);
/**
 * Auditoría estática de integración de Fase 6.
 * Solo lectura; no carga módulos de pago ni ejecuta consultas externas.
 */
function ucoPayment625Audit(string $projectRoot):array {
 $root=rtrim($projectRoot,'/');
 $modules=[
 'core_gateway'=>'private/payment-gateways.php',
 'core_ledger'=>'private/payment-ledger.php',
 'real_payment_api'=>'public/api/payment-real.php',
 'order_api'=>'public/api/orders.php',
 'stock_migration'=>'private/migrations/20261028_payment_stock_guards_612.php',
 'refund_intents'=>'private/payment-refund-ledger-615.php',
 'refund_tracking'=>'private/payment-refund-tracking-616.php',
 'refund_reconciliation'=>'private/payment-refund-reconcile-617.php',
 'refund_verification'=>'private/payment-refund-verify-618.php',
 'refund_settlement'=>'private/payment-refund-settlement-619.php',
 'refund_admin_report'=>'private/payment-refund-admin-620.php',
 'refund_admin_api'=>'public/api/payment-refund-admin-621.php',
 'refund_admin_page'=>'public/admin/refunds.html',
 'refund_admin_script'=>'public/admin/refunds-621.js',
 'redsys_audit'=>'private/payment-redsys-reconcile-622.php',
 'redsys_events'=>'private/payment-redsys-events-623.php',
 'redsys_linkage'=>'private/payment-redsys-linkage-624.php',
 ];
 $files=[];$missing=[];
 foreach($modules as $key=>$relative){
  $path=$root.'/'.$relative;$present=is_file($path);
  $files[$key]=['path'=>$relative,'present'=>$present,
    'sha256'=>$present?hash_file('sha256',$path):null];
  if(!$present)$missing[]=$relative;
 }
 $find=function(string $relative,string $needle)use($root):bool{
  $path=$root.'/'.$relative;
  return is_file($path)&&str_contains((string)file_get_contents($path),$needle);
 };
 $issues=[];
 if($find('private/payment-refund-verify-618.php','ucoPaypalBase(') &&
    !$find('private/payment-gateways.php','function ucoPaypalBase('))
  $issues[]=['severity'=>'blocking','code'=>'paypal_helper_missing',
   'message'=>'La fase 6.18 requiere un helper PayPal no encontrado.'];
 if($find('private/payment-refund-verify-618.php','ucoRedsysVerify(') &&
    !$find('private/payment-gateways.php','function ucoRedsysVerify('))
  $issues[]=['severity'=>'blocking','code'=>'redsys_helper_missing',
   'message'=>'La fase 6.18 requiere un verificador Redsys no encontrado.'];
 if($find('private/payment-refund-admin-620.php','uco_refund_verifications_619') &&
    !$find('private/payment-refund-settlement-619.php','uco_refund_verifications_619'))
  $issues[]=['severity'=>'blocking','code'=>'verification_table_not_defined',
   'message'=>'El informe 6.20 consulta una tabla no identificada en el módulo 6.19.'];
 if($find('private/payment-redsys-reconcile-622.php',"['merchant_code']") ||
    $find('private/payment-redsys-reconcile-622.php',"['terminal']"))
  $issues[]=['severity'=>'review','code'=>'redsys_config_key_review',
   'message'=>'Comprobar que los nombres de claves de comercio/terminal coinciden con la configuración real.'];
 if($find('private/payment-refund-admin-620.php',"'bank_verified'"))
  $issues[]=['severity'=>'review','code'=>'bank_verified_label_review',
   'message'=>'Revisar la semántica de bank_verified: un evento local no siempre acredita liquidación.'];
 if(!$find('public/api/orders.php',"'pending_payment'"))
  $issues[]=['severity'=>'blocking','code'=>'pending_stock_not_accounted',
   'message'=>'No se detecta pending_payment en la lógica de pedidos; revisar reservas.'];
 if($missing)$issues[]=['severity'=>'blocking','code'=>'missing_modules',
  'message'=>'Faltan módulos de fases anteriores; ver lista missing_files.'];
 return [
  'phase'=>'6.25','read_only'=>true,'generated_at_utc'=>gmdate('c'),
  'modules'=>$files,'missing_files'=>$missing,'issues'=>$issues,
  'ready_for_real_payments'=>false,'ready_for_real_refunds'=>false,
  'tests_executed'=>false,
  'note'=>'Revisión estática orientativa: no valida rutas de ejecución, permisos, bases de datos, pasarelas ni concurrencia.'
 ];
}
