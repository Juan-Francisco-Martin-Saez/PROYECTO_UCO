<?php
/* Test autónomo de diccionario. Ejecutar: php tests/test-dictionary-71e.php */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (!extension_loaded('pdo_sqlite') || !extension_loaded('mbstring')) { fwrite(STDERR,"Faltan extensiones pdo_sqlite o mbstring\n"); exit(2); }
require_once dirname(__DIR__).'/private/assistant/dictionary.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
uco_dictionary($db);
$terms=uco_expand($db,['papa','croqueta','precio']);
if (!isset($terms['patata']) || !in_array('papas',$terms['patata'],true)) throw new RuntimeException('Falla relación papa/patata');
if (!isset($terms['croqueta'])) throw new RuntimeException('Falla croqueta');
if (uco_norm('¡PÁPAS!') !== 'papas') throw new RuntimeException('Falla normalización');
$terms=uco_expand($db,['papas']);
if (!isset($terms['patata'])) throw new RuntimeException('Falla plural de sinónimo');
echo "OK: diccionario 7.1E\n";
