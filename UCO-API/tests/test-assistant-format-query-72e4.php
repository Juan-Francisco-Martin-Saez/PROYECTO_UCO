<?php
declare(strict_types=1);
// Standalone parser regression test. Requires PHP mbstring.
require_once dirname(__DIR__).'/private/assistant/catalog-format-72e3.php';
$cases=[
 ['patatas 6 kg','total_kg',6.0],
 ['patatas 6000 gramos','total_kg',6.0],
 ['patatas 6x1 kg','envases',6],
 ['patatas 6×1 kg','peso_envase_kg',1.0],
 ['bolsas de 500 g','tipo_envase','bolsa'],
 ['caja de 6x1 kg','tipo_envase','caja'],
];
foreach($cases as [$query,$key,$expected]){
 $actual=ucoFormatQuery72e3($query)[$key];
 if($actual!==$expected){fwrite(STDERR,"FAIL $query: $key ".var_export($actual,true)." expected ".var_export($expected,true)."\n");exit(1);}
}
echo "OK: ".count($cases)." parser checks\n";
