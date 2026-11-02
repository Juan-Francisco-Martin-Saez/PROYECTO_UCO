<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/payment-refund-settlement-619.php';
if ($argc!==2 || $argv[1]!=='schema') {
    fwrite(STDERR,"Uso: php payment-refund-619-cli.php schema\n"); exit(2);
}
try {
    $path=__DIR__.'/database/catalogo.sqlite';
    if (!is_file($path)) throw new RuntimeException('Base de datos inexistente');
    $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    ucoRefund619Schema($db);
    echo "Esquema de verificaciones creado. No se ha contactado con bancos.\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(3); }
