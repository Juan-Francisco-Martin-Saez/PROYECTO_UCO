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
$words=preg_split('/\s+/u',mb_strtolower($q,'UTF-8'))?:[];
$words=array_values(array_filter($words,static fn($w)=>mb_strlen($w,'UTF-8')>=3));
if(!$words)out(['ok'=>true,'items'=>[]]);
$words=array_slice($words,0,7);
try{
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=2000');
 // LIKE does not fold singular/plural or Spanish accents; query using word stems and alternate roots.
 $clauses=[];$params=[];
 foreach($words as $i=>$word){
  $root=preg_replace('/(es|s)$/u','',$word);
  if(mb_strlen($root,'UTF-8')<3)$root=$word;
  $key=':w'.$i;
  $clauses[]="(p.name LIKE $key ESCAPE '\\' COLLATE NOCASE OR p.description LIKE $key ESCAPE '\\' COLLATE NOCASE OR c.name LIKE $key ESCAPE '\\' COLLATE NOCASE OR p.search_text LIKE $key ESCAPE '\\' COLLATE NOCASE)";
  $params[$key]='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$root).'%';
 }
 $sql='SELECT p.id,p.name,p.unit,c.name AS category,p.is_demo FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND (c.id IS NULL OR c.active=1) AND '.implode(' AND ',$clauses).' ORDER BY p.is_demo ASC,p.name ASC LIMIT 12';
 $stmt=$db->prepare($sql);$stmt->execute($params);
 out(['ok'=>true,'items'=>$stmt->fetchAll()]);
}catch(Throwable $e){error_log('[UCO assistant catalog] '.$e->getMessage());out(['ok'=>false,'error'=>'Catálogo temporalmente no disponible'],503);}
