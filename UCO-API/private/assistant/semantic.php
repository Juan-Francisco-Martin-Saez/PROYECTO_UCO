<?php
declare(strict_types=1);
/** Pure ranking helpers: no DB access, no side effects. */
function uco_semantic_rank(array $product,array $expanded):array {
 $fields=[
  'name'=>uco_norm((string)($product['name']??'')),
  'category'=>uco_norm((string)($product['category']??'')),
  'description'=>uco_norm((string)($product['description']??'')),
  'search_text'=>uco_norm((string)($product['search_text']??''))
 ];
 $matched=[];$score=0;
 foreach($expanded as $concept=>$variants){
  $best=0;
  foreach(array_unique(array_merge([$concept],$variants)) as $variant){
   if(uco_contains_term($fields['name'],$variant))$best=max($best,12);
   elseif(uco_contains_term($fields['category'],$variant))$best=max($best,7);
   elseif(uco_contains_term($fields['search_text'],$variant))$best=max($best,4);
   elseif(uco_contains_term($fields['description'],$variant))$best=max($best,2);
  }
  if($best){$score+=$best;$matched[]=$concept;}
 }
 $total=count($expanded);
 $coverage=$total?count($matched)/$total:0;
 // Reward combinations actually found in the same product; no guessed links.
 if(count($matched)>=2)$score+=6*(count($matched)-1);
 return ['score'=>$score,'matched'=>count($matched),'coverage'=>$coverage,'concepts'=>$matched];
}
function uco_semantic_filter(array $ranked,int $terms):array {
 if(!$ranked)return [];
 $max=max(array_column($ranked,'score'));
 $out=[];
 foreach($ranked as $p){
  // For multi-term searches, prefer products satisfying several concepts.
  // Keep partial matches if none satisfy all concepts; avoid false "no results".
  if($terms>=2 && $p['coverage']<0.5 && $max>=18)continue;
  $out[]=$p;
 }
 return $out?:$ranked;
}
