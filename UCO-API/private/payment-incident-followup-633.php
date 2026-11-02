<?php
declare(strict_types=1);
function uco633Schema(PDO $db):void {
 $db->exec("CREATE TABLE IF NOT EXISTS uco_incident_followup_633(
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 incident_type TEXT NOT NULL CHECK(incident_type IN ('payment','refund')),
 incident_reference TEXT NOT NULL,
 status TEXT NOT NULL CHECK(status IN ('open','investigating','awaiting_provider','reviewed')),
 owner TEXT NOT NULL DEFAULT '',
 note TEXT NOT NULL DEFAULT '',
 actor_id INTEGER NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
 )");
 $db->exec("CREATE INDEX IF NOT EXISTS uco_incident_followup_633_ref
 ON uco_incident_followup_633(incident_type,incident_reference,id)");
}
function uco633Latest(PDO $db,string $type,string $reference,int $limit=30):array {
 $q=$db->prepare("SELECT id,incident_type,incident_reference,status,owner,note,actor_id,created_at
 FROM uco_incident_followup_633 WHERE incident_type=? AND incident_reference=?
 ORDER BY id DESC LIMIT ?");
 $q->bindValue(1,$type);$q->bindValue(2,$reference);$q->bindValue(3,max(1,min(100,$limit)),PDO::PARAM_INT);
 $q->execute();return $q->fetchAll(PDO::FETCH_ASSOC);
}
function uco633Add(PDO $db,string $type,string $reference,string $status,string $owner,string $note,int $actor):array {
 if(!in_array($type,['payment','refund'],true)||!in_array($status,['open','investigating','awaiting_provider','reviewed'],true)
 ||$reference===''||strlen($reference)>160||strlen($owner)>120||strlen($note)>2000||$actor<1)
 throw new InvalidArgumentException('Datos de seguimiento inválidos');
 $db->beginTransaction();
 try{
  $q=$db->prepare("INSERT INTO uco_incident_followup_633
  (incident_type,incident_reference,status,owner,note,actor_id) VALUES(?,?,?,?,?,?)");
  $q->execute([$type,$reference,$status,$owner,$note,$actor]);
  $id=(int)$db->lastInsertId();$db->commit();
  return ['id'=>$id,'status'=>$status,'bank_verified'=>false,'order_modified'=>false];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
