<?php
declare(strict_types=1);
/** UCO 3E.3A: autorización granular de pedidos. Nunca confiar en permisos enviados por el cliente. */
function ucoOrderCapabilities(PDO $db,int $staffId,string $legacyRole):array {
 $all=['orders.view','orders.prepare.view','orders.prepare.manage','orders.assign','orders.ship.view','orders.ship.manage','orders.history.view','orders.manage'];
 if($legacyRole==='admin')return array_fill_keys($all,true); // compatibilidad de administradores existentes
 $table=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('uco_access_staff_roles','uco_access_role_permissions')")->fetchAll(PDO::FETCH_COLUMN);
 if(count($table)!==2)return array_fill_keys(['orders.view'],true); // esquema anterior: manager consulta solamente
 $q=$db->prepare('SELECT COUNT(*) FROM uco_access_staff_roles WHERE staff_id=?');$q->execute([$staffId]);$assigned=(int)$q->fetchColumn();$q->closeCursor();
 if(!$assigned)return array_fill_keys(['orders.view'],true); // transición: sin asignación conserva lectura anterior
 $q=$db->prepare('SELECT DISTINCT rp.permission_code FROM uco_access_staff_roles sr JOIN uco_access_role_permissions rp ON rp.role_id=sr.role_id WHERE sr.staff_id=?');$q->execute([$staffId]);
 $perms=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);$q->closeCursor();
 // La lectura de pedidos está implícita para las operaciones de almacén.
 if(isset($perms['orders.prepare.view'])||isset($perms['orders.ship.view'])||isset($perms['orders.history.view']))$perms['orders.view']=true;
 return $perms;
}
function ucoOrderCan(array $caps,string $permission):bool{return isset($caps[$permission]);}
