<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-consistency-611.php';
try{
 $path=__DIR__.'/database/catalogo.sqlite';
 if(!is_file($path))throw new RuntimeException('No existe catalogo.sqlite');
 $db=new PDO('sqlite:file:'.$path.'?mode=ro',null,null,[
   PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
   PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
   PDO::ATTR_TIMEOUT=>5
 ]);
 $result=ucoPaymentConsistency611($db);
 echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
 exit(($result['blocker_types']??0)>0 || !empty($result['missing_tables'])?2:0);
}catch(Throwable $e){
 fwrite(STDERR,'Error en auditoría 6.11: '.$e->getMessage().PHP_EOL);
 exit(3);
}
