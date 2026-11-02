<?php
declare(strict_types=1);
/** Read-only server diagnostic; run only from CLI, never expose over HTTP. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$path=dirname(__DIR__).'/private/database/catalogo.sqlite';
if(!is_file($path)){fwrite(STDERR,"Catálogo no encontrado\n");exit(2);}
try{
 $db=new PDO('sqlite:file:'.$path.'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA query_only=ON');
 $queries=[
  'Productos totales'=>'SELECT COUNT(*) FROM products',
  'Productos reales activos'=>'SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND p.is_demo=0 AND (c.id IS NULL OR c.active=1)',
  'Productos de demostración'=>'SELECT COUNT(*) FROM products WHERE is_demo=1',
  'Reales activos con precio general'=>'SELECT COUNT(*) FROM products p JOIN base_prices bp ON bp.product_id=p.id LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND p.is_demo=0 AND (c.id IS NULL OR c.active=1)',
  'Reales activos sin precio general'=>'SELECT COUNT(*) FROM products p LEFT JOIN base_prices bp ON bp.product_id=p.id LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND p.is_demo=0 AND bp.product_id IS NULL AND (c.id IS NULL OR c.active=1)',
  'Precios personalizados registrados'=>'SELECT COUNT(*) FROM customer_prices',
  'Precios de grupo registrados'=>'SELECT COUNT(*) FROM group_prices',
 ];
 foreach($queries as $name=>$sql)echo $name.': '.$db->query($sql)->fetchColumn().PHP_EOL;
 echo "Nota: un precio general ausente puede estar cubierto por una tarifa personal o de grupo.\n";
}catch(Throwable $e){fwrite(STDERR,'Error de lectura: '.$e->getMessage().PHP_EOL);exit(1);}
