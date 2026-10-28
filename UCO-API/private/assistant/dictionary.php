<?php
declare(strict_types=1);
function uco_norm(string $s):string {
 $s=mb_strtolower($s,'UTF-8');
 $s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
 return trim(preg_replace('/\s+/u',' ',preg_replace('/[^\p{L}\p{N}]+/u',' ',$s))??'');
}
function uco_stem(string $s):string {
 if(mb_strlen($s,'UTF-8')>4 && str_ends_with($s,'es'))return mb_substr($s,0,-2,'UTF-8');
 if(mb_strlen($s,'UTF-8')>3 && str_ends_with($s,'s'))return mb_substr($s,0,-1,'UTF-8');
 return $s;
}
function uco_dictionary(PDO $db):void {
 $db->exec('CREATE TABLE IF NOT EXISTS concepts (
 id INTEGER PRIMARY KEY, term TEXT NOT NULL UNIQUE, kind TEXT NOT NULL DEFAULT \'general\',
 origin TEXT NOT NULL DEFAULT \'manual\', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
 )');
 $db->exec('CREATE TABLE IF NOT EXISTS synonyms (
 alias TEXT PRIMARY KEY, concept_id INTEGER NOT NULL REFERENCES concepts(id), status TEXT NOT NULL DEFAULT \'approved\'
 CHECK(status IN (\'approved\',\'disabled\')), origin TEXT NOT NULL DEFAULT \'manual\'
 )');
 $db->exec('CREATE TABLE IF NOT EXISTS relations (
 source_id INTEGER NOT NULL REFERENCES concepts(id), target_id INTEGER NOT NULL REFERENCES concepts(id),
 relation TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'approved\'
 CHECK(status IN (\'approved\',\'disabled\')), PRIMARY KEY(source_id,target_id,relation)
 )');
 $db->exec('CREATE TABLE IF NOT EXISTS intents (
 term TEXT PRIMARY KEY, intent TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'approved\'
 CHECK(status IN (\'approved\',\'disabled\'))
 )');
 $db->exec('CREATE TABLE IF NOT EXISTS unknown_terms (
 term TEXT PRIMARY KEY, occurrences INTEGER NOT NULL DEFAULT 1, last_seen TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 status TEXT NOT NULL DEFAULT \'pending\' CHECK(status IN (\'pending\',\'ignored\',\'approved\'))
 )');
 $db->exec('CREATE TABLE IF NOT EXISTS dictionary_meta (key TEXT PRIMARY KEY,value TEXT NOT NULL)');
 $seed=[
 'patata'=>['papa','papas'], 'croqueta'=>['croquetas','croqueton'],
 'tomate'=>['jitomate'], 'precio'=>['coste','importe','tarifa'],
 'factura'=>['recibo','comprobante','justificante'], 'pedido'=>['compra','encargo'],
 'envio'=>['entrega','transporte','transportista'], 'categoria'=>['familia','seccion'],
 'cliente'=>['comprador'], 'producto'=>['articulo'], 'ayuda'=>['asistencia']
 ];
 $put=$db->prepare('INSERT OR IGNORE INTO concepts(term,kind,origin) VALUES(?,\'general\',\'seed\')');
 $alias=$db->prepare('INSERT OR IGNORE INTO synonyms(alias,concept_id,origin) VALUES(?,?,\'seed\')');
 foreach($seed as $term=>$words){
  $put->execute([$term]);$id=(int)$db->query('SELECT id FROM concepts WHERE term='.$db->quote($term))->fetchColumn();
  foreach($words as $w)$alias->execute([uco_norm($w),$id]);
 }
 foreach(['price'=>['precio','cuanto','cuesta','vale'],'buy'=>['comprar','pedir','encargar','quiero'],'varieties'=>['tipos','clases','variedades'],'help'=>['como','donde','ayuda','explica']] as $intent=>$words){
  $stmt=$db->prepare('INSERT OR IGNORE INTO intents(term,intent) VALUES(?,?)');
  foreach($words as $w)$stmt->execute([$w,$intent]);
 }
}
function uco_expand(PDO $db,array $tokens):array {
 $aliases=[];$canonical=[];
 foreach($db->query("SELECT c.term,s.alias FROM synonyms s JOIN concepts c ON c.id=s.concept_id WHERE s.status='approved'") as $row){
  $aliases[uco_stem($row['alias'])]=$row['term'];
  $aliases[uco_stem($row['term'])]=$row['term'];
  $canonical[$row['term']][]=$row['alias'];
 }
 $expanded=[];
 foreach($tokens as $token){
  $canon=$aliases[uco_stem($token)]??$token;
  $expanded[$canon]=array_values(array_unique(array_merge([$canon],$canonical[$canon]??[])));
 }
 return $expanded;
}

/** 7.1F: interpretación de expresiones, sin ejecutar acciones. */
function uco_interpret(PDO $db,string $question):array {
 $normalized=uco_norm($question);
 $raw=array_values(array_filter(explode(' ',$normalized),static fn($v)=>$v!==''));
 $phrases=[];
 // Las expresiones más largas tienen preferencia sobre palabras aisladas.
 $phraseMap=[
  'cuanto cuesta'=>'price','cuanto vale'=>'price','que precio'=>'price',
  'que tipos'=>'varieties','que variedades'=>'varieties','que clases'=>'varieties',
  'como puedo'=>'help','donde puedo'=>'help','como hago'=>'help',
  'quiero comprar'=>'buy','quiero pedir'=>'buy','hacer un pedido'=>'buy',
  'hay disponibles'=>'availability','teneis'=>'availability','tienen'=>'availability',
  'no quiero'=>'negation','sin'=>'negation'
 ];
 foreach($phraseMap as $phrase=>$type){
  if(preg_match('/(?:^| )'.preg_quote($phrase,'/').'(?: |$)/u',$normalized))$phrases[$phrase]=$type;
 }
 $intent='availability';
 foreach(['price','buy','varieties','help'] as $priority){
  if(in_array($priority,$phrases,true)){$intent=$priority;break;}
 }
 if($intent==='availability'){
  $s=$db->prepare("SELECT intent FROM intents WHERE term=? AND status='approved'");
  foreach($raw as $w){
   $s->execute([$w]);$found=$s->fetchColumn();
   if($found!==false && in_array($found,['price','buy','varieties','help'],true)){$intent=(string)$found;break;}
  }
 }
 $stop=array_flip(explode(' ','hola buenas buenos dias tardes noches por favor gracias puedes podria podriais dime decir tienes teneis hay alguna algun unos unas una uno que cuales cual cuantos cuanto como donde son del los las para por con sobre este esta esto estos estas interes saber busco busca buscar mostrar muestra y o de el la lo en al a mi tu su se me es si no un quiero necesito puede tienen cuanto cuesta vale precios precio tipos clases variedades necesito comprar pedir encargar explicame hacer dime'));
 $tokens=[];
 foreach($raw as $w){
  if(mb_strlen($w,'UTF-8')<3||isset($stop[$w]))continue;
  $w=uco_stem($w);if(!in_array($w,$tokens,true))$tokens[]=$w;
 }
 $tokens=array_slice($tokens,0,12);
 $expanded=uco_expand($db,$tokens);
 $topics=[];
 $topicMap=[
  'catalogo'=>['catalogo','producto','articulo','familia','categoria'],
  'pedidos'=>['pedido','compra','encargo','envio','transportista','entrega'],
  'facturas'=>['factura','recibo','comprobante','justificante'],
  'clientes'=>['cliente','usuario','cuenta','historial'],
  'precios'=>['precio','tarifa','coste','importe','promocion'],
  'ayuda'=>['ayuda','funciona','instruccion','procedimiento']
 ];
 foreach($topicMap as $topic=>$words){
  foreach($expanded as $canon=>$variants){
   foreach(array_merge([$canon],$variants) as $v){
    if(in_array(uco_stem($v),array_map('uco_stem',$words),true)){$topics[]=$topic;break 2;}
   }
  }
 }
 return ['intent'=>$intent,'terms'=>$tokens,'expanded'=>$expanded,'topics'=>$topics,'phrases'=>array_keys($phrases),
  'negated'=>isset($phrases['no quiero'])||isset($phrases['sin'])];
}
function uco_contains_term(string $text,string $term):bool {
 $needle=uco_stem(uco_norm($term));
 if($needle==='')return false;
 foreach(explode(' ',uco_norm($text)) as $word){
  if(uco_stem($word)===$needle)return true;
 }
 return false;
}
