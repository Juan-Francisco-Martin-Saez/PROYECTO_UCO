<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/payment-refund-verify-618.php';
if($argc!==2||$argv[1]!=='check'){
 fwrite(STDERR,"Uso: php payment-refund-verify-618-cli.php check\n");exit(2);
}
$ok=extension_loaded('curl')&&extension_loaded('openssl')
 &&function_exists('hash_equals');
echo json_encode(['php_version'=>PHP_VERSION,'curl'=>extension_loaded('curl'),
 'openssl'=>extension_loaded('openssl'),'hash_equals'=>function_exists('hash_equals'),
 'ready_for_sandbox_tests'=>$ok,'real_refunds_enabled'=>false],
 JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),PHP_EOL;
if(!$ok)exit(3);
