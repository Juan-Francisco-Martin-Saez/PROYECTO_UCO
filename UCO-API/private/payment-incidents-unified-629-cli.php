<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-incidents-unified-629.php';
try{
 $path=__DIR__.'/database/catalogo.sqlite';
 if(!is_file($path))throw new RuntimeException('Base de datos no encontrada');
 $limit=isset($argv[1])?(int)$argv[1]:100;
 $offset=isset($argv[2])?(int)$argv[2]:0;
 $db=new PDO('sqlite:file:'.$path.'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 echo json_encode(ucoIncidents629Report($db,$limit,$offset),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Error de informe: '.$e->getMessage().PHP_EOL);exit(3);}
