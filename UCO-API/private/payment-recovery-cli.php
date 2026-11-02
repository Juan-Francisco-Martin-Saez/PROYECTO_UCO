<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-recovery.php';
try {
 $args=array_slice($argv,1);$apply=in_array('--apply',$args,true);$limit=50;
 foreach($args as $arg)if(str_starts_with($arg,'--limit=')){$n=substr($arg,8);if(!ctype_digit($n))throw new InvalidArgumentException('Límite inválido');$limit=max(1,min(200,(int)$n));}
 $config=ucoPaymentConfig();$db=ucoPaymentDb();
 echo json_encode(['generated_at'=>gmdate('c'),'mode'=>$apply?'verified_settlement':'dry_run','paypal'=>ucoPaymentRecoverPaypal($db,$config,$limit,$apply),'expired'=>ucoPaymentExpiredAudit($db,$limit)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Recuperación fallida: '.$e->getMessage().PHP_EOL);exit(1);}
