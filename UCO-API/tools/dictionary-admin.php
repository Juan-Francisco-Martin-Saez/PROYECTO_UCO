<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once dirname(__DIR__).'/private/assistant/dictionary.php';
$path=dirname(__DIR__).'/private/assistant/dictionary.sqlite';
$db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
uco_dictionary($db);
$command=$argv[1]??'help';
if($command==='pending'){
 foreach($db->query("SELECT term,occurrences FROM unknown_terms WHERE status='pending' ORDER BY occurrences DESC,term LIMIT 100") as $r)
 echo $r['term']."\t".$r['occurrences']."\n";
}elseif($command==='approve' && isset($argv[2],$argv[3])){
 $alias=uco_norm($argv[2]);$concept=uco_norm($argv[3]);
 if(!$alias||!$concept||mb_strlen($alias)>40||mb_strlen($concept)>40)exit("Términos inválidos\n");
 $db->beginTransaction();
 try{
  $s=$db->prepare("INSERT OR IGNORE INTO concepts(term,origin) VALUES(?,'admin')");$s->execute([$concept]);
  $id=$db->prepare('SELECT id FROM concepts WHERE term=?');$id->execute([$concept]);$conceptId=(int)$id->fetchColumn();
  $s=$db->prepare("INSERT INTO synonyms(alias,concept_id,status,origin) VALUES(?,?,'approved','admin') ON CONFLICT(alias) DO UPDATE SET concept_id=excluded.concept_id,status='approved',origin='admin'");
  $s->execute([$alias,$conceptId]);
  $s=$db->prepare("UPDATE unknown_terms SET status='approved' WHERE term=?");$s->execute([$alias]);
  $db->commit();echo "Aprobado: $alias => $concept\n";
 }catch(Throwable $e){$db->rollBack();throw $e;}
}elseif($command==='ignore'&&isset($argv[2])){
 $s=$db->prepare("UPDATE unknown_terms SET status='ignored' WHERE term=?");$s->execute([uco_norm($argv[2])]);echo "Ignorado\n";
}else echo "Uso:\n php tools/dictionary-admin.php pending\n php tools/dictionary-admin.php approve papas patata\n php tools/dictionary-admin.php ignore palabra\n";
