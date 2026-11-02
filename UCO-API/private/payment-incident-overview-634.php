<?php
declare(strict_types=1);
/** Devuelve la última anotación de cada expediente, no una confirmación bancaria. */
function uco634Overview(PDO $db,string $status='all',string $type='all',string $query='',int $limit=25,int $offset=0):array {
 if(!in_array($status,['all','open','investigating','awaiting_provider','reviewed'],true))throw new InvalidArgumentException('Estado inválido');
 if(!in_array($type,['all','payment','refund'],true))throw new InvalidArgumentException('Tipo inválido');
 if(strlen($query)>160)throw new InvalidArgumentException('Búsqueda demasiado larga');
 $limit=max(1,min(100,$limit));$offset=max(0,$offset);
 $where=[];$params=[];
 if($status!=='all'){$where[]='f.status=:status';$params[':status']=$status;}
 if($type!=='all'){$where[]='f.incident_type=:type';$params[':type']=$type;}
 if($query!==''){$where[]='instr(f.incident_reference,:query)>0';$params[':query']=$query;}
 $filter=$where?' WHERE '.implode(' AND ',$where):'';
 $base=' FROM uco_incident_followup_633 f JOIN (SELECT incident_type,incident_reference,MAX(id) latest_id FROM uco_incident_followup_633 GROUP BY incident_type,incident_reference) latest ON latest.latest_id=f.id ';
 $count=$db->prepare('SELECT COUNT(*)'.$base.$filter);
 foreach($params as $key=>$value)$count->bindValue($key,$value,PDO::PARAM_STR);
 $count->execute();$total=(int)$count->fetchColumn();
 $stmt=$db->prepare('SELECT f.id,f.incident_type,f.incident_reference,f.status,f.owner,f.note,f.actor_id,f.created_at'.$base.$filter.' ORDER BY f.id DESC LIMIT :limit OFFSET :offset');
 foreach($params as $key=>$value)$stmt->bindValue($key,$value,PDO::PARAM_STR);
 $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();
 return ['ok'=>true,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'limit'=>$limit,'offset'=>$offset,'read_only'=>true,'bank_verified'=>false];
}
