<?php
// Static regression checks; live SQLite integration must also be tested on IONOS.
$source=file_get_contents(__DIR__.'/../public/api/assistant-catalog-71a.php');
foreach(["p.sku=:sku", "\$reference===null", "\$normalized=uco_norm"] as $needle){
 $needle=str_replace('\\$','$',$needle);
 if(strpos($source,$needle)===false){fwrite(STDERR,"Missing: $needle\n");exit(1);}
}
echo "Reference search regression: OK\n";
