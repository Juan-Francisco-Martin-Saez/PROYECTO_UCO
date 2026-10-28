<?php
declare(strict_types=1);
/* Fase 7.1C: lectura pública, índice derivado del catálogo; sin acceso a datos privados. */
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function respond(array $v,int $status=200):never {
 http_response_code($status);
 echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
function norm(string $s):string {
 $s=mb_strtolower($s,'UTF-8');
 $s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u']);
 return trim(preg_replace('/\s+/u',' ',preg_replace('/[^\p{L}\p{N}]+/u',' ',$s))??'');
}
function stem(string $s):string {
 if(mb_strlen($s,'UTF-8')>4 && str_ends_with($s,'es'))return mb_substr($s,0,-2,'UTF-8');
 if(mb_strlen($s,'UTF-8')>3 && str_ends_with($s,'s'))return mb_substr($s,0,-1,'UTF-8');
 return $s;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')respond(['ok'=>false,'error'=>'Método no permitido'],405);
$q=trim((string)($_GET['q']??''));
if(mb_strlen($q,'UTF-8')>240)respond(['ok'=>false,'error'=>'Consulta demasiado larga'],400);
if(mb_strlen($q,'UTF-8')<2)respond(['ok'=>true,'items'=>[],'topics'=>[],'intent'=>'unknown','terms'=>[]]);
$normalized=norm($q);
$intent='availability';
if(preg_match('/\b(cuanto|precio|coste|costar|vale|tarifa|importe)\b/u',$normalized))$intent='price';
elseif(preg_match('/\b(comprar|pedir|encargar|pedido|llevar|necesito|quiero)\b/u',$normalized))$intent='buy';
elseif(preg_match('/\b(tipos|clases|variedades|cuales|cuantos|opciones)\b/u',$normalized))$intent='varieties';
elseif(preg_match('/\b(como|donde|ayuda|funciona|explica|hacer)\b/u',$normalized))$intent='help';
$stop=array_flip(explode(' ','hola buenas buenos dias tardes noches por favor gracias quiero necesito puedes podria podriais dime decir tienes teneis tenéis hay alguna algun unos unas una uno que cuales cual cuantos cuanto como donde son del los las para por con sobre este esta esto estos estas interes saber busco busca buscar mostrar muestra y o de el la lo en al a mi tu su se me es si no un'));
$aliasGroups=[
 ['patata','papa'],['croqueta','croqueton'],['tomate','jitomate'],
 ['precio','coste','importe','tarifa'],['factura','recibo','comprobante','justificante'],
 ['pedido','compra','encargo'],['envio','entrega','transporte','transportista'],
 ['categoria','familia','seccion'],['cliente','comprador']
];
$aliases=[];
foreach($aliasGroups as $group)foreach($group as $word)$aliases[$word]=$group;
$tokens=[];
foreach(explode(' ',$normalized) as $w){
 if(mb_strlen($w,'UTF-8')<3||isset($stop[$w]))continue;
 $w=stem($w);
 if(!in_array($w,$tokens,true))$tokens[]=$w;
}
$tokens=array_slice($tokens,0,12);
$topics=[];
$topicMap=[
 'catalogo'=>['catalogo','producto','articulo','familia','categoria'],
 'pedidos'=>['pedido','compra','encargo','envio','transportista','entrega'],
 'facturas'=>['factura','recibo','comprobante','justificante'],
 'clientes'=>['cliente','usuario','cuenta','historial'],
 'precios'=>['precio','tarifa','coste','importe','promocion'],
 'ayuda'=>['ayuda','funciona','instruccion','procedimiento']
];
foreach($topicMap as $topic=>$keywords){
 foreach($tokens as $w)foreach($keywords as $kw)if($w===stem($kw)){$topics[]=$topic;break 2;}
}
try{
 $dbPath=dirname(__DIR__,2).'/private/database/catalogo.sqlite';
 if(!is_file($dbPath))throw new RuntimeException('Falta catalogo.sqlite');
 $db=new PDO('sqlite:'.$dbPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA query_only=ON');
 $rows=$db->query("SELECT p.id,p.name,p.description,p.unit,p.search_text,p.is_demo,p.stock,c.name AS category
 FROM products p LEFT JOIN categories c ON c.id=p.category_id
 WHERE p.active=1 AND (c.id IS NULL OR c.active=1)")->fetchAll();
 $found=[];
 foreach($rows as $p){
  $name=norm((string)$p['name']);$cat=norm((string)($p['category']??''));
  $body=norm(implode(' ',[$p['description']??'',$p['search_text']??'']));
  $score=0;$matched=0;
  foreach($tokens as $token){
   $variants=$aliases[$token]??[$token];
   $best=0;
   foreach($variants as $variant){
    $needle=stem($variant);
    if($needle==='')continue;
    if(str_contains($name,$needle))$best=max($best,8);
    elseif(str_contains($cat,$needle))$best=max($best,5);
    elseif(str_contains($body,$needle))$best=max($best,2);
   }
   if($best){$score+=$best;$matched++;}
  }
  if(!$score)continue;
  // Avoid matches on just one irrelevant term in a multi-concept question.
  if(count($tokens)>2 && $matched===1)$score-=2;
  if($score<=0)continue;
  $found[]=['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],
    'category'=>$p['category'],'is_demo'=>(bool)$p['is_demo'],
    'score'=>$score,'matched'=>$matched];
 }
 usort($found,static fn($a,$b)=>($b['matched']<=>$a['matched'])?:($b['score']<=>$a['score'])?:($a['is_demo']<=>$b['is_demo'])?:strcmp($a['name'],$b['name']));
 respond(['ok'=>true,'intent'=>$intent,'terms'=>$tokens,'topics'=>array_values(array_unique($topics)),
  'items'=>array_slice($found,0,12),'truncated'=>count($found)>12,
  'source'=>'active_catalog','count'=>count($found)]);
}catch(Throwable $e){
 error_log('[UCO assistant 7.1C] '.$e->getMessage());
 respond(['ok'=>false,'error'=>'No se puede consultar el catálogo en este momento'],503);
}
