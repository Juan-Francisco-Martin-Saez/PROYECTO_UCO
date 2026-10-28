<?php
declare(strict_types=1);
/* UCO 7.1A: consulta pública de catálogo, SOLO LECTURA, sin precios personalizados. */
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function answer(array $data,int $code=200):never {
 http_response_code($code);
 echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')answer(['ok'=>false,'error'=>'Método no permitido'],405);
$q=trim((string)($_GET['q']??''));
if(function_exists('mb_strlen')?mb_strlen($q)>80:strlen($q)>160)answer(['ok'=>false,'error'=>'Consulta demasiado larga'],400);
if($q===''||(function_exists('mb_strlen')?mb_strlen($q)<2:strlen($q)<2))answer(['ok'=>true,'items'=>[]]);
try {
 $path=dirname(__DIR__,2).'/private/database/catalogo.sqlite';
 $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=2000');
 $sql="SELECT p.id,p.name,p.unit,c.name AS category
       FROM products p LEFT JOIN categories c ON c.id=p.category_id
       WHERE p.active=1 AND COALESCE(p.is_demo,0)=0 AND (c.id IS NULL OR c.active=1)
       AND (p.name LIKE :q ESCAPE '\\' OR p.description LIKE :q ESCAPE '\\' OR c.name LIKE :q ESCAPE '\\')
       ORDER BY CASE WHEN p.name LIKE :prefix ESCAPE '\\' THEN 0 ELSE 1 END,p.name LIMIT 8";
 $escaped=str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q);
 $stmt=$db->prepare($sql);
 $stmt->execute([':q'=>'%'.$escaped.'%',':prefix'=>$escaped.'%']);
 answer(['ok'=>true,'items'=>$stmt->fetchAll()]);
}catch(Throwable $e){
 error_log('UCO assistant 71a catalog: '.$e->getMessage());
 answer(['ok'=>false,'error'=>'Consulta temporalmente no disponible'],503);
}
