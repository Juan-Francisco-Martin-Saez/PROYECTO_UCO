<?php
declare(strict_types=1);
/** Solo lectura: no infiere envases ni unidades de precio. */
function ucoPresentacion(PDO $db,int $id): ?array {
 static $available=null;
 if($available===null){$q=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='uco_presentaciones'");$available=(bool)$q->fetchColumn();}
 if(!$available)return null;
 $s=$db->prepare('SELECT formato_original,envases,peso_envase_kg,peso_total_kg,tipo_envase,unidad_venta,estado FROM uco_presentaciones WHERE product_id=?');
 $s->execute([$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)return null;
 $fmt=static fn($n)=>rtrim(rtrim(number_format((float)$n,3,',','.'),'0'),',');
 if($r['estado']==='desglosado' && (int)$r['envases']>0 && (float)$r['peso_envase_kg']>0){
  $r['detalle']=(int)$r['envases'].' envases × '.$fmt($r['peso_envase_kg']).' kg = '.$fmt($r['peso_total_kg']).' kg en total';
 }elseif((float)$r['peso_total_kg']>0){
  $r['detalle']='Peso indicado: '.$fmt($r['peso_total_kg']).' kg · Presentación por confirmar';
 }else{$r['detalle']='Formato original: '.$r['formato_original'].' · Presentación por confirmar';}
 return $r;
}
