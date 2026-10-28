<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-integration-audit-625.php';
try{
 $root=dirname(__DIR__);
 echo json_encode(ucoPayment625Audit($root),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Auditoría fallida: '.$e->getMessage().PHP_EOL);exit(3);}
