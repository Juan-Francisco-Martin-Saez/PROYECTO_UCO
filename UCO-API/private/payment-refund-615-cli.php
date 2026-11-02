<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-refund-ledger-615.php';
if($argc<2||$argv[1]!=='schema'){
 fwrite(STDERR,"Uso: php payment-refund-615-cli.php schema\nNo ejecuta devoluciones.\n");exit(2);
}
try{
 $path=__DIR__.'/database/catalogo.sqlite';
 if(!is_file($path))throw new RuntimeException('No existe base de datos');
 $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 ucoRefund615Schema($db);
 echo "Esquema de intenciones de devolución preparado. Sin solicitudes bancarias.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(3);}
