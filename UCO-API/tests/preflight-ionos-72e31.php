<?php
declare(strict_types=1);
/** Read-only deployment preflight. Run from project root with PHP CLI. Never prints secrets. */
$root=dirname(__DIR__);
$checks=[];
$checks['PHP >= 8.1']=PHP_VERSION_ID>=80100;
$checks['mbstring']=extension_loaded('mbstring');
$checks['curl']=extension_loaded('curl');
$checks['JSON']=extension_loaded('json');
$checks['OpenAI endpoint exists']=is_file($root.'/public/api/assistant-openai.php');
$checks['Rate limiter exists']=is_file($root.'/private/assistant/openai-rate-limit-72e27.php');
$checks['Private config exists']=is_file($root.'/private/config/openai-protected.php');
$checks['Public index exists']=is_file($root.'/public/index.php')||is_file($root.'/public/index.html');
$checks['Private directory outside public']=realpath($root.'/private')!==false && realpath($root.'/public')!==false && !str_starts_with(realpath($root.'/private'),realpath($root.'/public').DIRECTORY_SEPARATOR);
$rateDir=$root.'/private/logs/openai-rate-72e27';
$parent=dirname($rateDir);
$checks['Rate directory writable or creatable']=is_dir($rateDir)?is_writable($rateDir):(is_dir($parent)?is_writable($parent):is_writable(dirname($parent)));
foreach($checks as $label=>$ok) echo ($ok?'PASS':'FAIL').' | '.$label.PHP_EOL;
$fail=count(array_filter($checks,static fn($v)=>!$v));
echo 'Resultado: '.(count($checks)-$fail).'/'.count($checks).' comprobaciones'.PHP_EOL;
exit($fail?1:0);
