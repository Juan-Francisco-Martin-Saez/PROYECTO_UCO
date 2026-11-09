<?php
declare(strict_types=1);
// Regression test for direct catalog API query normalization (static contract).
$source=file_get_contents(dirname(__DIR__).'/public/api/assistant-catalog-71a.php');
if($source===false)throw new RuntimeException('API missing');
foreach(['$stop=array_fill_keys','!isset($stop[$w])','array_unique','implode(\' AND \',$clauses)'] as $required){
 if(!str_contains($source,$required))throw new RuntimeException('Missing query contract: '.$required);
}
if(!str_contains($source,"$".'words=array_slice($words,0,7)'))throw new RuntimeException('Query limit missing');
echo "7.2E catalog query: OK\n";
