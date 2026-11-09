<?php
$src=file_get_contents(__DIR__.'/../public/api/assistant-catalog-71a.php');
$needles=["s.status='approved'", '$alternatives[$word]??[$word]', "implode(' OR ',".'$parts)', "implode(' AND ',".'$clauses)', 'p.sku=:sku'];
foreach($needles as $needle){
 if(strpos($src,$needle)===false){fwrite(STDERR,"Missing: $needle\n");exit(1);}
}
echo "Approved synonym guardrails: OK\n";
