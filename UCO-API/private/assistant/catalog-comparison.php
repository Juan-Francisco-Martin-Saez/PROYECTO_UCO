<?php
declare(strict_types=1);
/** Compare only explicitly requested, distinct catalog concepts. */
function uco_comparison_request(string $question):array {
 $original=uco_norm($question);
 $explicit=(bool)preg_match('/^(?:compara|comparar|comparame|diferencia entre|diferencias entre) /u',$original);
 $q=preg_replace('/^(?:compara|comparar|comparame|diferencia entre|diferencias entre) /u','',$original);
 $parts=preg_split('/(?:^| )(?:versus|vs|frente a)(?: |$)|(?:^| )y(?: |$)/u',$q,2);
 if(count($parts)!==2)return [];
 $clean=static function(string $v):string {
  return trim(preg_replace('/^(?:el|la|los|las|un|una|unos|unas|producto|productos) /u','',$v));
 };
 $a=$clean($parts[0]);$b=$clean($parts[1]);
 if($a===''||$b===''||$a===$b)return [];
 if(!$explicit&&!preg_match('/(?:^| )(?:versus|vs|frente a)(?: |$)/u',$original))return [];
 return [$a,$b];
}
function uco_comparison_candidates(array $rows,string $query,PDO $dict):array {
 $analysis=uco_interpret($dict,$query);
 if(!$analysis['expanded'])return [];
 $found=[];
 foreach($rows as $p){
  $rank=uco_semantic_rank($p,$analysis['expanded']);
  if($rank['score']<=0)continue;
  $found[]=['id'=>(int)$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],
   'category'=>$p['category'],'is_demo'=>(bool)$p['is_demo'],
   'score'=>$rank['score'],'coverage'=>$rank['coverage']];
 }
 usort($found,static fn($a,$b)=>($b['coverage']<=>$a['coverage'])?:($b['score']<=>$a['score'])?:($a['is_demo']<=>$b['is_demo']));
 return array_slice($found,0,3);
}
