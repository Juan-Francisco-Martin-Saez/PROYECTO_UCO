<?php
declare(strict_types=1);
require dirname(__DIR__).'/private/assistant/query-router-72e21.php';
$cases=[
 ['aros de cebolla 6 kg','commercial'],
 ['precio de patatas','commercial'],
 ['¿Dónde está mi pedido?','authenticated'],
 ['quiero pagar el carrito','commercial'],
 ['descargar mis facturas','authenticated'],
 ['¿Qué es la fotosíntesis?','general'],
 ['¿Quién escribió Don Quijote?','general'],
 ['','clarify'],
];
foreach($cases as [$q,$expected]){
 $actual=ucoQueryRoute72e21($q)['route'];
 if($actual!==$expected){fwrite(STDERR,"FAIL: $q expected $expected got $actual\n");exit(1);}
}
echo count($cases)."/".count($cases)." rutas correctas (aislado, sin HTTP)\n";
