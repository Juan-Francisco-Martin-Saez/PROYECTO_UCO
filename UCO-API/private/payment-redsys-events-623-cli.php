<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-redsys-events-623.php';
$action=$argv[1]??'';
if(!in_array($action,['schema','report'],true)){
 fwrite(STDERR,"Uso: php payment-redsys-events-623-cli.php schema|report\n");exit(2);
}
try{
 $path=__DIR__.'/database/catalogo.sqlite';
 if(!is_file($path))throw new RuntimeException('Base de datos no encontrada');
 $dsn='sqlite:'.($action==='report'?'file:'.$path.'?mode=ro':$path);
 $db=new PDO($dsn,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 if($action==='schema'){ucoRedsys623Schema($db);echo "Tabla de auditoría creada. Sin operaciones bancarias.\n";}
 else echo json_encode(ucoRedsys623Audit($db),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Error: '.$e->getMessage().PHP_EOL);exit(3);}
