<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-audit-610.php';
try {
    $path=__DIR__.'/database/catalogo.sqlite';
    if(!is_file($path))throw new RuntimeException('No existe la base de datos');
    // Apertura estrictamente de solo lectura. No crea ni altera tablas.
    $db=new PDO('sqlite:file:'.$path.'?mode=ro',null,null,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT=>5
    ]);
    $report=ucoAudit610($db);
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
    exit($report['summary']['blockers']>0?2:0);
}catch(Throwable $e){
    fwrite(STDERR,"Auditoría no disponible: ".$e->getMessage().PHP_EOL);
    exit(3);
}
