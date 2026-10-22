<?php
declare(strict_types=1);
/** UCO 3E.3A: autorización granular de pedidos. Nunca confiar en permisos enviados por el cliente. */
function ucoOrderCapabilities(PDO $db,int $staffId,string $legacyRole):array {
 $all=['orders.view','orders.prepare.view','orders.prepare.manage','orders.assign','orders.ship.view','orders.ship.manage','orders.history.view','orders.manage'];
 if($legacyRole==='admin')return array_fill_keys($all,true); // compatibilidad de administradores existentes
 $table=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('uco_access_staff_roles','uco_access_role_permissions')")->fetchAll(PDO::FETCH_COLUMN);
 if(count($table)!==2)return []; // sin esquema RBAC: acceso denegado para no administradores
 $q=$db->prepare('SELECT COUNT(*) FROM uco_access_staff_roles WHERE staff_id=?');$q->execute([$staffId]);$assigned=(int)$q->fetchColumn();$q->closeCursor();
 if(!$assigned)return []; // sin perfiles no hay acceso a pedidos
 $q=$db->prepare('SELECT DISTINCT rp.permission_code FROM uco_access_staff_roles sr JOIN uco_access_role_permissions rp ON rp.role_id=sr.role_id JOIN uco_access_roles ar ON ar.id=sr.role_id WHERE sr.staff_id=? AND ar.code<>"administrator"');$q->execute([$staffId]);
 $perms=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);$q->closeCursor();
 // La lectura de pedidos está implícita para las operaciones de almacén.
 if(isset($perms['orders.prepare.view'])||isset($perms['orders.ship.view'])||isset($perms['orders.history.view']))$perms['orders.view']=true;
 return $perms;
}
function ucoOrderCan(array $caps,string $permission):bool{return isset($caps[$permission]);}

/** 3E.3B: permisos generales; nunca se aceptan permisos proporcionados por el cliente. */
function ucoAdminCapabilities(PDO $db,int $staffId,string $legacyRole):array {
 if($legacyRole==='admin'){
  $codes=['catalog.view','catalog.manage','customers.view','customers.manage','pricing.view','pricing.manage','staff.manage'];
  return array_fill_keys($codes,true);
 }
 $tables=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('uco_access_staff_roles','uco_access_role_permissions')")->fetchAll(PDO::FETCH_COLUMN);
 if(count($tables)!==2)return [];
 $q=$db->prepare('SELECT DISTINCT rp.permission_code FROM uco_access_staff_roles sr JOIN uco_access_role_permissions rp ON rp.role_id=sr.role_id JOIN uco_access_roles ar ON ar.id=sr.role_id WHERE sr.staff_id=? AND ar.code<>"administrator"');$q->execute([$staffId]);
 $caps=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
 foreach(['catalog','customers','pricing'] as $area)if(isset($caps[$area.'.manage']))$caps[$area.'.view']=true;
 return $caps;
}
