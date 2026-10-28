<?php
/* Prueba del diccionario 7.1F: php tests/test-dictionary-71f.php */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
if(!extension_loaded('pdo_sqlite')||!extension_loaded('mbstring')){fwrite(STDERR,"Faltan extensiones pdo_sqlite o mbstring\n");exit(2);}
require_once dirname(__DIR__).'/private/assistant/dictionary.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);uco_dictionary($db);
$cases=[
 ['¿Cuánto cuestan las papas?','price','patata'],
 ['¿Qué tipos de croquetas tenéis?','varieties','croqueta'],
 ['Quiero comprar tomates','buy','tomate'],
 ['¿Cómo puedo pedir productos?','help','producto']
];
foreach($cases as [$q,$intent,$concept]){
 $a=uco_interpret($db,$q);
 if($a['intent']!==$intent)throw new RuntimeException("Intención errónea: $q => {$a['intent']}");
 if(!isset($a['expanded'][$concept]))throw new RuntimeException("Concepto no reconocido: $q");
}
if(!uco_contains_term('Patatas para freír','patata'))throw new RuntimeException('No reconoce plural');
if(uco_contains_term('Patatas para freír','pata'))throw new RuntimeException('Falso positivo por subcadena');
$a=uco_interpret($db,'No quiero papas');if(!$a['negated'])throw new RuntimeException('No detecta negación');
echo "OK: diccionario 7.1F\n";
