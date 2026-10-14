<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);echo json_encode(['error'=>'Método no permitido']);exit;}
if(($_GET['action']??'catalog')!=='catalog'){http_response_code(404);echo json_encode(['error'=>'Ruta no disponible']);exit;}
function normalizar(string $s):string { $s=mb_strtolower($s,'UTF-8');if(class_exists('Normalizer'))$s=Normalizer::normalize($s,Normalizer::FORM_D);return strtr($s,['á'=>'a','à'=>'a','ä'=>'a','â'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u']); }
try{
$db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->exec('PRAGMA query_only=ON');$cols=array_column($db->query('PRAGMA table_info(products)')->fetchAll(),'name');
$categories=$db->query('SELECT slug,name FROM categories WHERE active=1 ORDER BY sort_order,id')->fetchAll();
$category=trim((string)($_GET['category']??''));$q=trim((string)($_GET['q']??''));
if(strlen($q)>150||strlen($category)>60){http_response_code(400);echo json_encode(['error'=>'Filtro demasiado largo']);exit;}
$page=max(1,min(100000,(int)($_GET['page']??1)));$limit=max(1,min(24,(int)($_GET['limit']??12)));
$where=' WHERE p.active=1 AND c.active=1';$params=[];
if($category!==''){$where.=' AND c.slug=:category';$params[':category']=$category;}
if($q!==''){$where.=" AND p.search_text LIKE :search ESCAPE '!'";$params[':search']='%'.str_replace(['!','%','_'],['!!','!%','!_'],normalizar($q)).'%';}
$from=' FROM products p JOIN categories c ON c.id=p.category_id';$count=$db->prepare('SELECT COUNT(*)'.$from.$where);$count->execute($params);$total=(int)$count->fetchColumn();
$stmt=$db->prepare('SELECT p.id,p.sku,p.name,'.(in_array('description',$cols,true)?'p.description':"'' AS description").',p.image,'.(in_array('unit',$cols,true)?'p.unit':"'unidad' AS unit").',p.is_demo,p.stock,c.slug AS category'.$from.$where.' ORDER BY p.name COLLATE NOCASE,p.id LIMIT :limit OFFSET :offset');foreach($params as $k=>$v)$stmt->bindValue($k,$v,PDO::PARAM_STR);$stmt->bindValue(':limit',$limit,PDO::PARAM_INT);$stmt->bindValue(':offset',($page-1)*$limit,PDO::PARAM_INT);$stmt->execute();
echo json_encode(['demo'=>false,'categories'=>$categories,'products'=>$stmt->fetchAll(),'page'=>$page,'limit'=>$limit,'total'=>$total],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'Catálogo temporalmente no disponible']);}
