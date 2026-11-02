<?php
declare(strict_types=1);
/** Safe exclusion of explicitly named catalog references; never infer allergens. */
function uco_catalog_filter_request(string $question):array {
 $q=uco_norm($question);
 $parts=preg_split('/(?:^| )(?:excepto|excluyendo)(?: |$)/u',$q,2);
 if(count($parts)!==2)return ['active'=>false,'reason'=>'','exclude'=>[],'positive'=>''];
 $positive=trim($parts[0]);$negative=trim($parts[1]);
 if($positive===''||$negative==='')return ['active'=>false,'reason'=>'incomplete','exclude'=>[],'positive'=>''];
 $stop=['el','la','los','las','un','una','unos','unas','de','del','en','con','y','o','por','favor','que','quiero','busco','teneis','tienen','productos','producto','dame','mostrar','muestra','todos','todas','todo'];
 $tokens=array_values(array_filter(explode(' ',$negative),static fn($t)=>mb_strlen($t,'UTF-8')>=3&&!in_array($t,$stop,true)));
 if(!$tokens)return ['active'=>false,'reason'=>'incomplete','exclude'=>[],'positive'=>$positive];
 return ['active'=>true,'reason'=>'explicit_named_exclusion','exclude'=>array_slice($tokens,0,5),'positive'=>$positive];
}
function uco_catalog_excluded(array $product,array $terms):bool {
 // Only positive matches are excluded; missing metadata never proves an absence.
 $fields=implode(' ',array_map(static fn($v)=>(string)$v,[
 $product['name']??'', $product['category']??'', $product['description']??'', $product['search_text']??''
 ]));
 foreach($terms as $term)if(uco_contains_term($fields,$term))return true;
 return false;
}
