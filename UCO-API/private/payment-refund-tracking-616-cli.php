<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-refund-tracking-616.php';
$command=$argv[1]??'report';
if(!in_array($command,['report','schema'],true)){
    fwrite(STDERR,"Uso: php payment-refund-tracking-616-cli.php [report|schema]\n");exit(2);
}
try {
    $path=__DIR__.'/database/catalogo.sqlite';
    if(!is_file($path))throw new RuntimeException('No existe la base de datos');
    $uri=$command==='report'?'sqlite:file:'.$path.'?mode=ro':'sqlite:'.$path;
    $db=new PDO($uri,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
    if($command==='schema'){
        $db->exec('PRAGMA foreign_keys=ON');
        ucoRefund616Schema($db);
        echo "Esquema de seguimiento creado; no se ha realizado ninguna devolución.\n";
    }else{
        echo json_encode(ucoRefund616Report($db),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
    }
}catch(Throwable $e){fwrite(STDERR,'Error: '.$e->getMessage().PHP_EOL);exit(3);}
