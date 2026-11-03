<?php
// Migración de familias: ejecutar UNA VEZ con copia de seguridad previa.
declare(strict_types=1);
$dbPath=dirname(__DIR__).'/private/database/catalogo.sqlite';
$db=new PDO('sqlite:'.$dbPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA busy_timeout=10000');
$db->beginTransaction();
try {
 $categories=[1=>['frutas-y-verduras','Frutas y verduras'],2=>['iv-gama','IV Gama'],3=>['v-gama','V Gama'],4=>['congelados','Congelados'],5=>['legumbres-y-arroces','Legumbres y arroces'],6=>['uco-km0','UCO KM0']];
 foreach($categories as $id=>$cat){
  $exists=$db->prepare('SELECT COUNT(*) FROM categories WHERE id=?');$exists->execute([$id]);
  if($exists->fetchColumn()){$stmt=$db->prepare('UPDATE categories SET slug=?,name=?,sort_order=?,active=1 WHERE id=?');$stmt->execute([$cat[0],$cat[1],$id,$id]);}
  else {$stmt=$db->prepare('INSERT INTO categories(id,slug,name,sort_order,active) VALUES(?,?,?,?,1)');$stmt->execute([$id,$cat[0],$cat[1],$id]);}
 }
 $stmt=$db->prepare('UPDATE products SET category_id=? WHERE sku=?');
 $mapping=json_decode(file_get_contents(__DIR__.'/familias-productos-20261103.json'),true,512,JSON_THROW_ON_ERROR);
 foreach($mapping as $sku=>$category){$stmt->execute([$category,$sku]);}
 $db->commit();echo "Familias actualizadas correctamente\n";
}catch(Throwable $e){$db->rollBack();throw $e;}
