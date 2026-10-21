<?php
declare(strict_types=1);
/**
 * UCO Pedidos Fase 2F: auditoría estática y pruebas HTTP sin credenciales.
 * Nunca inicia sesión ni envía solicitudes capaces de cambiar pedidos autenticados.
 */
if(PHP_SAPI!=='cli'){http_response_code(403);exit("Solo CLI\n");}
$root=dirname(__DIR__,2);$failed=0;$warnings=0;
function result(string $label,bool $ok,string $detail=''):void{
 global $failed;echo ($ok?'[OK] ':'[ERROR] ').$label.($detail!==''?' — '.$detail:'')."\n";
 if(!$ok)$failed++;
}
function source(string $relative):string{
 global $root;
 $file=$root.'/'.$relative;
 if(!is_file($file))throw new RuntimeException('Falta '.$relative);
 $data=file_get_contents($file);
 if($data===false)throw new RuntimeException('No se puede leer '.$relative);
 return $data;
}
function patterns(string $label,string $text,array $needles):void{
 $missing=[];foreach($needles as $needle){if(!str_contains($text,$needle))$missing[]=$needle;}
 result($label,!$missing,$missing?'Falta: '.implode(', ',$missing):'');
}
function httpProbe(string $base,string $path,string $method='GET',string $body=''):void{
 global $warnings;
 $url=rtrim($base,'/').$path;
 $headers=['Content-Type: application/json','Accept: application/json'];
 $ctx=stream_context_create(['http'=>[
  'method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body,
  'timeout'=>5,'ignore_errors'=>true,'follow_location'=>0
 ]]);
 $data=@file_get_contents($url,false,$ctx);
 $headers=$http_response_header??[];
 $code=0;if(isset($headers[0])&&preg_match('/\s(\d{3})\s/',$headers[0],$m))$code=(int)$m[1];
 $safe=in_array($code,[401,403],true);
 if($code===0){$warnings++;echo "[NO VERIFICADO] $method $path — no se pudo conectar al servidor\n";return;}
 result("Sin sesión: $method $path",$safe,'HTTP '.$code);
 if($safe&&$data!==false&&str_contains(strtolower((string)$data),'fatal error'))result('Sin filtración de errores PHP',false);
}
try{
 echo "UCO Pedidos 2F — pruebas de seguridad sin cambios de datos\n";
 $admin=source('public/api/admin-orders.php');
 $orders=source('public/api/orders.php');
 $receipt=source('public/api/order-receipt.php');
 $payment=source('public/api/payment-simulator.php');
 $client=source('public/api/client.php');
 patterns('Pedidos admin: sesión e identidad',$admin,["session_name('UCOADMIN')","uco_user_id","uco_auth_stamp","ucoIdentitySessionValid"]);
 patterns('Pedidos admin: escritura solo admin y CSRF',$admin,["REQUEST_METHOD","$"."staff['role']!=='admin'","HTTP_X_CSRF_TOKEN","hash_equals"]);
 patterns('Pedidos admin: transiciones controladas',$admin,["$"."allowed=","in_array($"."next,$"."allowed","BEGIN IMMEDIATE","uco_order_fulfillment_events"]);
 patterns('Pedidos admin: pago simulado validado',$admin,["simulated_approved","simulation_state","approved"]);
 patterns('Reinicio: contraseña y motivo',$admin,["password_verify","admin_password","mb_strlen($"."note)<12","uco_order_fulfillment_resets"]);
 patterns('Cliente: sesión y caducidad de credenciales',$orders,["session_name('UCOCLIENT')","customer_stamp","customer_id"]);
 patterns('Cliente: historial limitado al propietario',$orders,["if($"."action==='history')","WHERE o.customer_id=?","$"."q->execute([$"."id])"]);
 patterns('Cliente: operaciones protegidas por CSRF',$orders,["customer_csrf","HTTP_X_CSRF_TOKEN","hash_equals"]);
 patterns('Justificantes: control de sesión',$receipt,["session_name('UCOCLIENT')","customer_id"]);
 patterns('Simulador de pagos: sesión de cliente',$payment,["session_name('UCOCLIENT')","customer_id"]);
 patterns('API de cliente: sesión identificada',$client,["session_name('UCOCLIENT')"]);
 echo "\nLas verificaciones anteriores son inspección de código: NO demuestran por sí solas que los controles resistan todas las peticiones.\n";
 $base=null;
 foreach($argv as $arg)if(str_starts_with($arg,'--url='))$base=substr($arg,6);
 if($base!==null){
  $parts=parse_url($base);
  if(!$parts||!in_array($parts['scheme']??'',['http','https'],true)||!in_array(strtolower($parts['host']??''),['127.0.0.1','localhost','[::1]','::1'],true))
   throw new RuntimeException('Por seguridad, --url solo admite localhost/127.0.0.1');
  echo "\nPruebas HTTP sin cookies, contra servidor local:\n";
  httpProbe($base,'/api/admin-orders.php');
  httpProbe($base,'/api/admin-orders.php','POST','{"order_id":1,"state":"preparing"}');
  httpProbe($base,'/api/orders.php?action=history');
  httpProbe($base,'/api/order-receipt.php?order_id=1');
  httpProbe($base,'/api/payment-simulator.php','POST','{}');
 }else{
  echo "\n[NO VERIFICADO] Pruebas HTTP: ejecuta --url=http://127.0.0.1:8000 si el servidor local está activo y sirve public/.\n";
  $warnings++;
 }
 echo "\nResultado: ".($failed?'ERRORES ENCONTRADOS':($warnings?'COMPROBACIONES ESTÁTICAS SUPERADAS; PRUEBAS PENDIENTES':'COMPROBACIONES EJECUTADAS SUPERADAS'))."\n";
 echo "No se ha modificado ningún dato.\n";
 exit($failed?2:0);
}catch(Throwable $e){fwrite(STDERR,'ERROR: '.$e->getMessage()."\n");exit(1);}
