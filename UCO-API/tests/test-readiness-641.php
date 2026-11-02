<?php
declare(strict_types=1);
require dirname(__DIR__).'/private/chatbot/admin-readiness-641.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE customers(id INTEGER PRIMARY KEY, name TEXT)');
$db->exec('CREATE TABLE uco_orders(id INTEGER PRIMARY KEY,customer_id INTEGER)');
$db->exec("INSERT INTO customers(id,name) VALUES(1,'Cliente de prueba')");
$db->exec('INSERT INTO uco_orders(id,customer_id) VALUES(2,1)');
uco641Schema($db);uco641SetAllowed($db,1,true,5);
assert(uco641Eligible($db,1)===false);
uco641RecordOrderOrigin($db,2,'chatbot',1);
uco641RecordError($db,'interpretation','prueba','Producto no identificado');
$s=uco641Summary($db);
if($s['allowed']!==1||$s['chatbot_orders']!==1||$s['errors']!==1)throw new RuntimeException('Contadores incorrectos');
try{uco641RecordOrderOrigin($db,2,'web',1);throw new RuntimeException('Origen sobrescrito');}
catch(PDOException $e){}
echo "OK: esquema, permisos, ausencia de consentimiento, origen inmutable, errores y recuentos\n";
