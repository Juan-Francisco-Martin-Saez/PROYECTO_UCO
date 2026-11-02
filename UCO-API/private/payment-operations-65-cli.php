<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-operations-65.php';
try {
 $db=ucoPaymentDb();$action=$argv[1]??'help';
 if($action==='list')$out=ucoPayment65List($db,(int)($argv[2]??100));
 elseif($action==='expired')$out=ucoPayment65ExpiredCandidates($db,(int)($argv[2]??100));
 elseif($action==='queue'){
  if(count($argv)<5)throw new InvalidArgumentException('Uso: queue REFERENCIA TIPO ACTOR [NOTA] [EVIDENCIA]');
  $out=ucoPayment65Queue($db,$argv[2],$argv[3],$argv[4],$argv[5]??'', $argv[6]??null);
 }else $out=['usage'=>['php payment-operations-65-cli.php list [limite]','php payment-operations-65-cli.php expired [limite]','php payment-operations-65-cli.php queue REFERENCIA TIPO ACTOR [NOTA] [EVIDENCIA]'],'types'=>['transfer_evidence','refund_request','cancel_request','late_payment','expiry_review']];
 echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'ERROR: '.$e->getMessage().PHP_EOL);exit(1);}
