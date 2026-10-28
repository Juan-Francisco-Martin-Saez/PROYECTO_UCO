<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-refund-guard-614.php';
if($argc!==3||!in_array($argv[1],['refund','cancel'],true)){
 fwrite(STDERR,"Uso: php payment-refund-guard-614-cli.php refund|cancel REFERENCIA\n");
 exit(2);
}
try{
 $path=__DIR__.'/database/catalogo.sqlite';
 if(!is_file($path))throw new RuntimeException('Base de datos inexistente');
 $db=new PDO('sqlite:file:'.$path.'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 echo json_encode(ucoRefundGuard614($db,$argv[2],$argv[1]),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $e){
 fwrite(STDERR,'Error: '.$e->getMessage().PHP_EOL);
 exit(3);
}
