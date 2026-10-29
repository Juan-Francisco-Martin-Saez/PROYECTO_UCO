<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/private/assistant/dictionary.php';
require_once dirname(__DIR__).'/private/assistant/catalog-comparison.php';
function check71p(bool $v,string $message):void {if(!$v)throw new RuntimeException($message);}
check71p(uco_comparison_request('Compara patatas y tomates')===['patatas','tomates'],'comparison');
check71p(uco_comparison_request('Tomates vs patatas')===['tomates','patatas'],'vs');
check71p(uco_comparison_request('Patatas y tomates')===[],'ordinary conjunction');
check71p(uco_comparison_request('Compara patatas y patatas')===[],'identical terms');
echo "7.1P OK\n";
