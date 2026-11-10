<?php
require __DIR__.'/../private/assistant/openai-rate-limit-72e27.php';
$dir=sys_get_temp_dir().'/uco-rate-test-'.bin2hex(random_bytes(5));
$pass=0;
for($i=0;$i<10;$i++){if(ucoOpenAIRateAllow72e27($dir,'192.0.2.1'))$pass++;}
if($pass!==10)exit(1);
if(ucoOpenAIRateAllow72e27($dir,'192.0.2.1'))exit(2);
if(!ucoOpenAIRateAllow72e27($dir,'192.0.2.2'))exit(3);
if(ucoOpenAIRateAllow72e27($dir,''))exit(4);
foreach(glob($dir.'/*') as $file)unlink($file);
rmdir($dir);
echo "13/13 controles PHP: 10 admitidas, exceso rechazado, IP distinta permitida, IP vacía rechazada\n";
