<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-reconciliation.php';
try {
 $config=ucoPaymentConfig();$db=ucoPaymentDb();
 $limit=isset($argv[1])?(int)$argv[1]:100;
 echo json_encode(['generated_at'=>gmdate('c'),'mode'=>'read_only','payments'=>ucoReconcileSnapshot($db,$config,$limit)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'No se pudo completar la conciliación: '.$e->getMessage().PHP_EOL);exit(1);}
