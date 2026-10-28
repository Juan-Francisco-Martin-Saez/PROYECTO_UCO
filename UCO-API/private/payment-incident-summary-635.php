<?php
declare(strict_types=1);
/** Cuenta expedientes por su ÚLTIMA anotación; no cuenta eventos históricos. */
function uco635Summary(PDO $db):array {
 $exists=(bool)$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='uco_incident_followup_633'")->fetchColumn();
 $counts=['open'=>0,'investigating'=>0,'awaiting_provider'=>0,'reviewed'=>0];
 if(!$exists)return ['ok'=>true,'counts'=>$counts,'total'=>0,'table_missing'=>true,'read_only'=>true];
 $sql="SELECT f.status,COUNT(*) qty FROM uco_incident_followup_633 f
 JOIN (SELECT MAX(id) id FROM uco_incident_followup_633 GROUP BY incident_type,incident_reference) latest ON latest.id=f.id
 GROUP BY f.status";
 foreach($db->query($sql,PDO::FETCH_ASSOC) as $r){if(array_key_exists($r['status'],$counts))$counts[$r['status']]=(int)$r['qty'];}
 return ['ok'=>true,'counts'=>$counts,'total'=>array_sum($counts),'table_missing'=>false,'read_only'=>true,'bank_verified'=>false];
}
