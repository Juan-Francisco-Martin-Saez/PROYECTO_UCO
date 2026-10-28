<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-late-incidents-628.php';
try {
 $path=__DIR__.'/database/catalogo.sqlite';
 if(!is_file($path))throw new RuntimeException('Base de datos no encontrada');
 $db=new PDO('sqlite:file:'.$path.'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 echo json_encode(ucoLate628Report($db),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Error de informe: '.$e->getMessage().PHP_EOL);exit(3);}
