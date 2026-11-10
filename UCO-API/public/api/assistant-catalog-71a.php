<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function out(array $v,int $status=200):never{http_response_code($status);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')out(['ok'=>false,'error'=>'Método no permitido'],405);
$q=trim((string)($_GET['q']??''));
if(strlen($q)>200)out(['ok'=>false,'error'=>'Consulta demasiado larga'],400);
require_once dirname(__DIR__,2).'/private/assistant/dictionary.php';
require_once dirname(__DIR__,2).'/private/assistant/catalog-format-72e3.php';
$format=ucoFormatQuery72e3($q);
$normalized=uco_norm($format['text']);
$referenceSource=uco_norm($q);
$words=preg_split('/\s+/u',$normalized)?:[];
// A product reference is an exact identifier, not a fuzzy word.
// Preserve its leading zeros and bypass conversational filler only for this lookup.
$reference=null;
if(preg_match('/(?<![0-9])[0-9]{6,14}(?![0-9])/u',$referenceSource,$match))$reference=$match[0];
// Exclude conversational filler before applying the strict AND product filter.
// Product qualifiers (e.g. 'aros', 'cebolla', 'congelado') are deliberately retained.
$stop=array_fill_keys(explode(' ','por favor hola buenas buenos dias tardes noches por favor gracias puedes podria podriais dime decir mostrar muestra muestra me quiero necesito busco buscar buscas teneis tienen tienes hay alguna algun unos unas una uno cuales cual cuantos cuanto como donde para sobre este esta esto estos estas interes saber producto productos articulo articulos catalogo precio precios cuesta vale comprar pedir encargar necesito los las del con que una unos unas entre desde hasta tambien'),true);
$words=array_values(array_unique(array_filter($words,static fn($w)=>mb_strlen($w,'UTF-8')>=3&&!isset($stop[$w]))));
if(!$words&&$reference===null&&$format['total_kg']===null&&$format['envases']===null&&$format['tipo_envase']===null)out(['ok'=>true,'items'=>[]]);
$words=array_slice($words,0,7);
try{
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=2000');
 if($reference!==null){
  require_once dirname(__DIR__,2).'/private/presentaciones.php';
  $stmt=$db->prepare('SELECT p.id,p.name,p.unit,c.name AS category,p.is_demo FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND (c.id IS NULL OR c.active=1) AND p.sku=:sku ORDER BY p.is_demo ASC LIMIT 12');
  $stmt->execute([':sku'=>$reference]);
  $rows=$stmt->fetchAll();
  foreach($rows as &$row)$row['presentacion']=ucoPresentacion($db,(int)$row['id']);unset($row);
  out(['ok'=>true,'items'=>$rows,'match_status'=>count($rows)===0?'none':(count($rows)===1?'unique':'multiple'),'has_more'=>false]);
 }
 // Expand only APPROVED dictionary synonyms. Each query term remains mandatory:
 // alternatives apply within a term, never across unrelated product qualifiers.
 // Do not write to the production dictionary or invent product relationships.
 // Resolve approved aliases to their canonical catalog term, including plural forms.
 // Do not search all aliases as substrings: "papas" must not match "papaya".
 $canonicalByStem=[];
 try{
  $dict=new PDO('sqlite:'.dirname(__DIR__,2).'/private/assistant/dictionary.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $entries=$dict->query("SELECT c.term,s.alias FROM synonyms s JOIN concepts c ON c.id=s.concept_id WHERE s.status='approved'")->fetchAll();
  foreach($entries as $entry){
   $canonical=uco_norm((string)$entry['term']);$alias=uco_norm((string)$entry['alias']);
   if($canonical===''||$alias===''||str_contains($canonical,' ')||str_contains($alias,' '))continue;
   foreach([$canonical,$alias] as $member){
    $stem=uco_stem($member);
    // Ambiguous stems must not be arbitrarily mapped to one concept.
    if(!array_key_exists($stem,$canonicalByStem))$canonicalByStem[$stem]=$canonical;
    elseif($canonicalByStem[$stem]!==$canonical)$canonicalByStem[$stem]=null;
   }
  }
 }catch(Throwable $dictionaryError){
  error_log('[UCO assistant synonyms] '.$dictionaryError->getMessage());
 }
 $clauses=[];$params=[];
 foreach($words as $i=>$word){
  $stem=uco_stem($word);
  $canonical=$canonicalByStem[$stem]??null;
  $variant=$canonical!==null?$canonical:$word;
  $root=uco_stem($variant);
  if(mb_strlen($root,'UTF-8')<3)$root=$variant;
  $key=':w'.$i;
  $clauses[]="(p.name LIKE $key ESCAPE '\\' COLLATE NOCASE OR p.description LIKE $key ESCAPE '\\' COLLATE NOCASE OR c.name LIKE $key ESCAPE '\\' COLLATE NOCASE OR p.search_text LIKE $key ESCAPE '\\' COLLATE NOCASE)";
  $params[$key]='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$root).'%';
 }
 // Match weights against verified presentation data only. Unknown packaging is never guessed.
 $formatWhere=[];
 $formatJoin='';
 if($format['total_kg']!==null || $format['envases']!==null || $format['tipo_envase']!==null){
  $formatJoin=' JOIN uco_presentaciones f ON f.product_id=p.id ';
  if($format['total_kg']!==null){$formatWhere[]='f.peso_total_kg BETWEEN :weight_min AND :weight_max';$params[':weight_min']=$format['total_kg']-0.00001;$params[':weight_max']=$format['total_kg']+0.00001;}
  if($format['envases']!==null){
   $formatWhere[]="f.estado='desglosado' AND f.envases=:pack_count AND f.peso_envase_kg BETWEEN :pack_min AND :pack_max";
   $params[':pack_count']=$format['envases'];$params[':pack_min']=$format['peso_envase_kg']-0.00001;$params[':pack_max']=$format['peso_envase_kg']+0.00001;
  }
  if($format['tipo_envase']!==null){$formatWhere[]='LOWER(f.tipo_envase)=:pack_type';$params[':pack_type']=$format['tipo_envase'];}
 }
 $filters=array_merge(['p.active=1','(c.id IS NULL OR c.active=1)'],$clauses,$formatWhere);
 // Rank explicit product names before broader description/category matches.
 // Exact SKU remains handled above and therefore always has absolute priority.
 // Rank using the same normalized, filler-free product terms used for filtering.
 // This prevents polite wording ('quiero patatas') from downgrading an exact match.
 // Preserve the original text only for packaging-only queries with no product terms.
 $rankText=implode(' ',$words);
 if($rankText==='')$rankText=trim((string)$format['text']);
 $params[':rank_exact']=$rankText;
 $params[':rank_prefix']=str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$rankText).'%';
 $params[':rank_contains']='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$rankText).'%';
 // A product mentioned in its official name is more relevant than an ingredient
 // mentioned only in its description or search metadata. Use the canonical
 // lexical stem for singular/plural queries, without loosening the AND filters.
 $rankStemParts=[];
 foreach($words as $word){
  $stem=uco_stem($canonicalByStem[uco_stem($word)]??$word);
  if($stem!=='')$rankStemParts[]=$stem;
 }
 $rankStem=implode(' ',$rankStemParts);
 $escapedStem=str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$rankStem);
 $params[':rank_stem_prefix']=$escapedStem.'%';
 $params[':rank_stem_contains']='%'.$escapedStem.'%';
 // All mandatory qualifiers in the official product name outrank matches
 // that rely on descriptions or broad search metadata. Do not relax AND.
 $nameAll=[];
 foreach($rankStemParts as $i=>$stem){
  $key=':rank_name_word_'.$i;
  $nameAll[]="p.name LIKE $key ESCAPE '\\' COLLATE NOCASE";
  $params[$key]='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$stem).'%';
 }
 $nameAllSql=$nameAll?'('.implode(' AND ',$nameAll).')':'0';
 // A complete product phrase (e.g. "aros de cebolla") must outrank a
 // product whose name merely contains the same words separately.
 // Keep this as a ranking-only signal: it never changes eligibility.
 $phraseWords=[];
 foreach($rankStemParts as $stem){
  if($stem!=='')$phraseWords[]=str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$stem);
 }
 $params[':rank_phrase']='%'.implode('%',$phraseWords).'%';
 $sql='SELECT p.id,p.name,p.unit,c.name AS category,p.is_demo FROM products p LEFT JOIN categories c ON c.id=p.category_id '.$formatJoin.' WHERE '.implode(' AND ',$filters)." ORDER BY CASE WHEN LOWER(p.name)=LOWER(:rank_exact) THEN 0 WHEN p.name LIKE :rank_prefix ESCAPE '\\' COLLATE NOCASE THEN 1 WHEN p.name LIKE :rank_contains ESCAPE '\\' COLLATE NOCASE THEN 2 WHEN p.name LIKE :rank_stem_prefix ESCAPE '\\' COLLATE NOCASE THEN 3 WHEN p.name LIKE :rank_stem_contains ESCAPE '\\' COLLATE NOCASE THEN 4 WHEN p.name LIKE :rank_phrase ESCAPE '\\' COLLATE NOCASE THEN 5 WHEN ".$nameAllSql." THEN 6 WHEN c.name LIKE :rank_stem_contains ESCAPE '\\' COLLATE NOCASE THEN 7 ELSE 8 END ASC, p.is_demo ASC,p.name ASC LIMIT 13";
 require_once dirname(__DIR__,2).'/private/presentaciones.php';
 $stmt=$db->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();$hasMore=count($rows)>12;if($hasMore)$rows=array_slice($rows,0,12);foreach($rows as &$row)$row['presentacion']=ucoPresentacion($db,(int)$row['id']);unset($row);
 out(['ok'=>true,'items'=>$rows,'match_status'=>count($rows)===0?'none':(count($rows)===1?'unique':'multiple'),'has_more'=>$hasMore]);
}catch(Throwable $e){error_log('[UCO assistant catalog] '.$e->getMessage());out(['ok'=>false,'error'=>'Catálogo temporalmente no disponible'],503);}
