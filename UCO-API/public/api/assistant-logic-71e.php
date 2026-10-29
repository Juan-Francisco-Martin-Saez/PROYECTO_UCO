<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function answer(array $data,int $code=200):never {
 http_response_code($code);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')answer(['ok'=>false,'error'=>'Método no permitido'],405);
$q=trim((string)($_GET['q']??''));
if(mb_strlen($q,'UTF-8')>240)answer(['ok'=>false,'error'=>'Consulta demasiado larga'],400);
if(mb_strlen($q,'UTF-8')<2)answer(['ok'=>true,'items'=>[],'topics'=>[],'intent'=>'unknown','terms'=>[]]);
$private=dirname(__DIR__,2).'/private';
require_once $private.'/assistant/dictionary.php';
require_once $private.'/assistant/semantic.php';
require_once $private.'/assistant/query-clarity.php';
require_once $private.'/assistant/catalog-filters.php';
require_once $private.'/assistant/catalog-comparison.php';
try{
 $catalog=$private.'/database/catalogo.sqlite';
 if(!is_file($catalog))throw new RuntimeException('Catálogo ausente');
 $db=new PDO('sqlite:'.$catalog,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA query_only=ON');
 $dictPath=$private.'/assistant/dictionary.sqlite';
 if(!is_dir(dirname($dictPath)))throw new RuntimeException('Carpeta de diccionario ausente');
 $dict=new PDO('sqlite:'.$dictPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $dict->exec('PRAGMA busy_timeout=2000');
 uco_dictionary($dict);
 $filter=uco_catalog_filter_request($q);
 $analysis=uco_interpret($dict,$filter['active']?$filter['positive']:$q);
 $clarity=uco_query_clarity($q);
 $tokens=$analysis['terms'];
 $expanded=$analysis['expanded'];
 $intent=$analysis['intent'];
 $topics=$analysis['topics'];
 $rows=$db->query("SELECT p.id,p.name,p.description,p.unit,p.search_text,p.is_demo,p.stock,c.name AS category
 FROM products p LEFT JOIN categories c ON c.id=p.category_id
 WHERE p.active=1 AND (c.id IS NULL OR c.active=1)")->fetchAll();
 $comparison=uco_comparison_request($q);
 if($comparison){
  $groups=[];
  foreach($comparison as $term)$groups[]=['query'=>$term,'items'=>uco_comparison_candidates($rows,$term,$dict)];
  answer(['ok'=>true,'intent'=>'comparison','comparison'=>$groups,'items'=>[],
   'topics'=>[],'terms'=>[],'clarity'=>$clarity,'negated'=>false,'count'=>0]);
 }
 $found=[];$recognized=[];
 foreach($rows as $p){
  if($filter['active']&&uco_catalog_excluded($p,$filter['exclude']))continue;
  $rank=uco_semantic_rank($p,$expanded);
  if(!$rank['score'])continue;
  foreach($rank['concepts'] as $concept)$recognized[$concept]=true;
  $found[]=['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],
   'category'=>$p['category'],'is_demo'=>(bool)$p['is_demo'],
   'score'=>$rank['score'],'matched'=>$rank['matched'],'coverage'=>$rank['coverage']];
 }
 $found=uco_semantic_filter($found,count($expanded));
 usort($found,static fn($a,$b)=>($b['coverage']<=>$a['coverage'])?:($b['score']<=>$a['score'])?:($a['is_demo']<=>$b['is_demo'])?:strcmp($a['name'],$b['name']));
 // Register unknown vocabulary as suggestions only; no raw conversation is stored.
 // Restrict writes to 3 tokens per query, <= 40 chars and maximum 2000 entries.
 $pending=$dict->prepare("INSERT INTO unknown_terms(term) VALUES(?) ON CONFLICT(term) DO UPDATE SET occurrences=MIN(occurrences+1,100000),last_seen=CURRENT_TIMESTAMP WHERE status='pending'");
 $known=$dict->prepare('SELECT 1 FROM concepts WHERE term=? UNION SELECT 1 FROM synonyms WHERE alias=? LIMIT 1');
 $unknownCount=(int)$dict->query('SELECT COUNT(*) FROM unknown_terms')->fetchColumn();
 $new=0;
 foreach($tokens as $token){
  if(isset($recognized[$token]) || (isset($expanded[$token]) && count($expanded[$token])>1))continue;
  if(mb_strlen($token,'UTF-8')>40||preg_match('/\d/u',$token))continue;
  if(in_array($token,['pedido','factura','precio','cliente','producto','categoria','ayuda','catalogo'],true))continue;
  $known->execute([$token,$token]);if($known->fetchColumn())continue;
  if($unknownCount>=2000||$new>=3)break;
  try{$pending->execute([$token]);$new++;}catch(Throwable $ignored){/* no impact on search */}
 }
 answer(['ok'=>true,'intent'=>$intent,'terms'=>$tokens,'topics'=>$topics,
 'items'=>array_slice($found,0,12),'truncated'=>count($found)>12,
 'source'=>'active_catalog+approved_dictionary_71m','negated'=>($analysis['negated']||($clarity['exclusion']&&!$filter['active'])),'filter_applied'=>$filter['active'],'filter_type'=>$filter['active']?'explicit_catalog_exclusion':null,'clarity'=>$clarity,'count'=>count($found)]);
}catch(Throwable $e){
 error_log('[UCO assistant 7.1E] '.$e->getMessage());
 answer(['ok'=>false,'error'=>'No se puede consultar el catálogo en este momento'],503);
}
