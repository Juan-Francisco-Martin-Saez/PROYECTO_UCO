<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-late-review-613.php';
$apply=in_array('--queue',$argv,true);
try{
    $db=ucoPaymentDb();
    $report=ucoLateReview613($db,100,$apply);
    echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $e){
    fwrite(STDERR,"No se pudo generar la revisión: ".$e->getMessage().PHP_EOL);
    exit(3);
}
