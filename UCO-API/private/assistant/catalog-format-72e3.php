<?php
declare(strict_types=1);
/** Extract explicit packaging constraints; never infer missing packaging metadata. */
function ucoFormatQuery72e3(string $query): array {
 $s=mb_strtolower($query,'UTF-8');
 $s=str_replace(',','.',$s);
 $number='([0-9]+(?:\.[0-9]+)?)';
 $unit='(kg|kilos?|kilogramos?|g|gr|gramos?)';
 $result=['text'=>$s,'total_kg'=>null,'envases'=>null,'peso_envase_kg'=>null,'tipo_envase'=>null];
 $toKg=static fn($v,$u)=>(float)$v*(preg_match('/^(g|gr|gramos?)$/u',$u)?0.001:1);
 if(preg_match('/\b([0-9]+)\s*(?:x|×|por)\s*'.$number.'\s*'.$unit.'\b/u',$s,$m,PREG_OFFSET_CAPTURE)){
  $result['envases']=(int)$m[1][0];$result['peso_envase_kg']=$toKg($m[2][0],$m[3][0]);
  $result['text']=substr_replace($s,' ', $m[0][1],strlen($m[0][0]));
 }elseif(preg_match('/\b'.$number.'\s*'.$unit.'\b/u',$s,$m,PREG_OFFSET_CAPTURE)){
  $result['total_kg']=$toKg($m[1][0],$m[2][0]);
  $result['text']=substr_replace($s,' ', $m[0][1],strlen($m[0][0]));
 }
 // Packaging type is only a hard constraint when an explicit, recognized word is present.
 if(preg_match('/\b(bolsas?|cajas?|paquetes?)\b/u',$result['text'],$m)){
  $result['tipo_envase']=str_starts_with($m[1],'bolsa')?'bolsa':(str_starts_with($m[1],'caja')?'caja':'paquete');
  $result['text']=preg_replace('/\b(bolsas?|cajas?|paquetes?)\b/u',' ',$result['text']);
 }
 return $result;
}
