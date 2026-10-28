<?php
declare(strict_types=1);
/** Antigüedad desde la última anotación (no desde el incidente bancario). */
function uco636Aging(PDO $db,int $days=7):array {
 if(!in_array($days,[3,7,14,30],true))throw new InvalidArgumentException('Umbral no permitido');
 $exists=(bool)$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='uco_incident_followup_633'")->fetchColumn();
 if(!$exists)return ['ok'=>true,'days'=>$days,'total'=>0,'items'=>[],'table_missing'=>true,'read_only'=>true];
 $sql="SELECT f.incident_type,f.incident_reference,f.status,f.owner,f.created_at,
 CAST(julianday('now')-julianday(f.created_at) AS INTEGER) AS age_days
 FROM uco_incident_followup_633 f
 JOIN (SELECT MAX(id) AS id FROM uco_incident_followup_633 GROUP BY incident_type,incident_reference) latest ON latest.id=f.id
 WHERE f.status IN ('open','investigating','awaiting_provider')
 AND datetime(f.created_at) <= datetime('now', :threshold)
 ORDER BY f.created_at ASC, f.id ASC";
 $stmt=$db->prepare($sql);
 $stmt->execute([':threshold'=>'-'.$days.' days']);
 $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
 $total=count($rows);
 return ['ok'=>true,'days'=>$days,'total'=>$total,'items'=>array_slice($rows,0,50),'limited'=>$total>50,
 'table_missing'=>false,'read_only'=>true,'bank_verified'=>false];
}
