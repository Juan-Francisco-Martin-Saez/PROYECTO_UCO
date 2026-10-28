<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/private/assistant/dictionary.php';
require_once dirname(__DIR__).'/private/assistant/semantic.php';
function check71m(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
$terms=['patata'=>['papa'],'frita'=>['freir']];
$a=uco_semantic_rank(['name'=>'Patatas para freír','category'=>'Verduras'],$terms);
$b=uco_semantic_rank(['name'=>'Patatas','category'=>'Verduras'],$terms);
$c=uco_semantic_rank(['name'=>'Croquetas','category'=>'Congelados'],$terms);
check71m($a['score']>$b['score'],'No prioriza combinación de conceptos');
check71m($b['score']>$c['score'],'No prioriza coincidencia real');
check71m($c['score']===0,'Coincidencia inexistente');
check71m(uco_contains_term('Patatas frescas','patata'),'Plural no reconocido');
check71m(!uco_contains_term('Patatal','patata'),'Coincidencia parcial incorrecta');
echo "7.1M: OK\n";
