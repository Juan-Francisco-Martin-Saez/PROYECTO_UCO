<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try {
$db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA query_only=ON');
require_once dirname(__DIR__,2).'/private/presentaciones.php';
$category=substr(trim((string)($_GET['category']??'')),0,60);$q=substr(trim((string)($_GET['q']??'')),0,100);
$page=max(1,(int)($_GET['page']??1));$limit=max(1,min(24,(int)($_GET['limit']??8)));
$categories=$db->query('SELECT slug,name FROM categories WHERE active=1 ORDER BY sort_order,id')->fetchAll();
$sql='SELECT g.id group_id,g.display_name,p.id,p.sku,p.name,p.description,p.image,p.unit,p.stock,c.slug category,f.label format_label FROM uco_commercial_groups g JOIN uco_commercial_group_members m ON m.group_id=g.id JOIN products p ON p.id=m.product_id JOIN categories c ON c.id=p.category_id LEFT JOIN product_formats f ON f.product_id=p.id AND f.active=1 WHERE p.active=1 AND c.active=1';
$args=[];if($category!==''){$sql.=' AND c.slug=:category';$args[':category']=$category;}
$sql.=' ORDER BY g.display_name COLLATE NOCASE,p.id';$st=$db->prepare($sql);$st->execute($args);
$all=[];
foreach($st as $r){$id=(int)$r['group_id'];if(!isset($all[$id]))$all[$id]=['id'=>$id,'name'=>$r['display_name'],'description'=>$r['description'],'image'=>$r['image'],'category'=>$r['category'],'variants'=>[]];$all[$id]['variants'][]=['id'=>(int)$r['id'],'sku'=>$r['sku'],'name'=>$r['name'],'format'=>$r['format_label'],'stock'=>(int)$r['stock'],'unit'=>$r['unit'],'presentacion'=>ucoPresentacion($db,(int)$r['id'])];}
$norm=static function($s){$s=mb_strtolower((string)$s,'UTF-8');return strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u']);};
$items=array_values(array_filter($all,static function($p)use($q,$norm){if($q==='')return true;$hay=$p['name'].' '.implode(' ',array_map(static fn($v)=>$v['sku'].' '.$v['name'].' '.$v['format'],$p['variants']));return str_contains($norm($hay),$norm($q));}));
$priorityTerms=array_slice(array_values(array_unique(array_filter(array_map('trim',explode('|',substr((string)($_GET['priority']??''),0,2500)))))),0,30);
if($q==='' && $priorityTerms){
 $score=static function($p)use($priorityTerms,$norm){
  $hay=$norm($p['name'].' '.implode(' ',array_map(static fn($v)=>$v['name'].' '.$v['sku'].' '.$v['format'],$p['variants'])));
  $score=0;foreach($priorityTerms as $term){$term=$norm($term);if(mb_strlen($term)<2)continue;if(str_contains($hay,$term))$score+=10;}
  return $score;
 };
 $ranked=[];$categories=[];
 foreach($items as $i=>$item){$ranked[$i]=$score($item);if($ranked[$i]>0)$categories[$item['category']]=true;}
 usort($items,static function($a,$b)use($score,$categories){
  $sa=$score($a);$sb=$score($b);if($sa!==$sb)return $sb<=>$sa;
  $ca=isset($categories[$a['category']])?1:0;$cb=isset($categories[$b['category']])?1:0;
  return $cb<=>$ca; // Empates conservan el orden previo (PHP 8+).
 });
}
$total=count($items);$items=array_slice($items,($page-1)*$limit,$limit);
foreach($items as &$p){$p['sku']=$p['variants'][0]['sku'];$p['stock']=array_sum(array_column($p['variants'],'stock'));$p['unit']=$p['variants'][0]['unit'];}unset($p);
echo json_encode(['categories'=>$categories,'products'=>$items,'total'=>$total,'page'=>$page,'limit'=>$limit,'grouped'=>true],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'Catálogo no disponible']);}
