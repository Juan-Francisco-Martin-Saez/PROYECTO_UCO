<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/private/assistant/dictionary.php';
require_once dirname(__DIR__).'/private/assistant/query-clarity.php';
function check(bool $v,string $msg):void {if(!$v)throw new RuntimeException($msg);}
check(uco_query_clarity('¿Patatas o tomates?')['alternative'],'alternativas');
check(uco_query_clarity('Quiero patatas sin sal')['exclusion'],'exclusion');
check(uco_query_clarity('Compara patatas versus tomates')['comparison'],'comparacion');
check(uco_query_clarity('Patatas para freír')['condition'],'condicion');
check(!uco_query_clarity('Patatas frescas')['alternative'],'falso positivo');
echo "7.1N: OK\n";
