<?php
declare(strict_types=1);
/** Esquema auxiliar, sin alterar tablas de pedidos, clientes ni pagos. */
function uco641Schema(PDO $db):void {
 $db->exec("CREATE TABLE IF NOT EXISTS uco_bot_customer_access_641(
 customer_id INTEGER PRIMARY KEY, allowed_by_admin INTEGER NOT NULL DEFAULT 0 CHECK(allowed_by_admin IN (0,1)),
 consent_at TEXT DEFAULT NULL, updated_by INTEGER NOT NULL, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
 $db->exec("CREATE TABLE IF NOT EXISTS uco_bot_admin_audit_641(
 id INTEGER PRIMARY KEY AUTOINCREMENT, actor_id INTEGER NOT NULL, action TEXT NOT NULL,
 subject_id INTEGER, detail TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
 $db->exec("CREATE TABLE IF NOT EXISTS uco_bot_order_origin_641(
 order_id INTEGER PRIMARY KEY, origin TEXT NOT NULL CHECK(origin IN ('web','chatbot')),
 actor_customer_id INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
 $db->exec("CREATE TABLE IF NOT EXISTS uco_bot_errors_641(
 id INTEGER PRIMARY KEY AUTOINCREMENT, category TEXT NOT NULL, reference TEXT NOT NULL DEFAULT '',
 detail TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
 $db->exec("CREATE INDEX IF NOT EXISTS idx_bot_errors_641_date ON uco_bot_errors_641(id)");
}
function uco641CustomerExists(PDO $db,int $customerId):bool {
 $q=$db->prepare("SELECT 1 FROM customers WHERE id=? LIMIT 1");$q->execute([$customerId]);
 return (bool)$q->fetchColumn();
}
function uco641SetAllowed(PDO $db,int $customerId,bool $allowed,int $staffId):void {
 if($customerId<1||$staffId<1||!uco641CustomerExists($db,$customerId))throw new InvalidArgumentException('Cliente no válido');
 $db->beginTransaction();
 try {
  $q=$db->prepare("INSERT INTO uco_bot_customer_access_641(customer_id,allowed_by_admin,consent_at,updated_by)
   VALUES(?,?,NULL,?) ON CONFLICT(customer_id) DO UPDATE SET allowed_by_admin=excluded.allowed_by_admin,
   updated_by=excluded.updated_by,updated_at=CURRENT_TIMESTAMP");
  $q->execute([$customerId,$allowed?1:0,$staffId]);
  $a=$db->prepare("INSERT INTO uco_bot_admin_audit_641(actor_id,action,subject_id,detail) VALUES(?,?,?,?)");
  $a->execute([$staffId,$allowed?'allow_customer':'block_customer',$customerId,'Permiso administrativo; no implica consentimiento']);
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
/** Solo acceso potencial: falta consentimiento explícito del cliente y autenticación real del bot. */
function uco641Eligible(PDO $db,int $customerId):bool {
 $q=$db->prepare("SELECT 1 FROM uco_bot_customer_access_641 WHERE customer_id=?
 AND allowed_by_admin=1 AND consent_at IS NOT NULL");
 $q->execute([$customerId]);return (bool)$q->fetchColumn();
}
/** Para uso futuro exclusivo del motor autenticado de pedidos: no expuesto en HTTP en 6.41. */
function uco641RecordOrderOrigin(PDO $db,int $orderId,string $origin,?int $customerId=null):void {
 if($orderId<1||!in_array($origin,['web','chatbot'],true))throw new InvalidArgumentException('Origen no válido');
 $q=$db->prepare("SELECT customer_id FROM uco_orders WHERE id=?");$q->execute([$orderId]);
 $owner=$q->fetchColumn();if($owner===false)throw new InvalidArgumentException('Pedido inexistente');
 if($customerId!==null&&(int)$owner!==$customerId)throw new InvalidArgumentException('Pedido de otro cliente');
 $q=$db->prepare("INSERT INTO uco_bot_order_origin_641(order_id,origin,actor_customer_id) VALUES(?,?,?)");
 $q->execute([$orderId,$origin,$customerId]); // no permite reescribir el origen
}
function uco641RecordError(PDO $db,string $category,string $reference,string $detail):void {
 if(!in_array($category,['interpretation','validation','order_creation','payment_handoff','other'],true)
 ||strlen($reference)>120||strlen($detail)>600||$detail==='')throw new InvalidArgumentException('Error inválido');
 $q=$db->prepare("INSERT INTO uco_bot_errors_641(category,reference,detail) VALUES(?,?,?)");
 $q->execute([$category,$reference,$detail]); // nunca almacenar texto completo de chat, claves o datos bancarios
}
function uco641Summary(PDO $db):array {
 $counts=['allowed'=>0,'blocked'=>0,'consented'=>0,'errors'=>0,'chatbot_orders'=>0,'web_orders'=>0];
 $q=$db->query("SELECT SUM(CASE WHEN allowed_by_admin=1 THEN 1 ELSE 0 END) allowed,
 SUM(CASE WHEN allowed_by_admin=0 THEN 1 ELSE 0 END) blocked,
 SUM(CASE WHEN allowed_by_admin=1 AND consent_at IS NOT NULL THEN 1 ELSE 0 END) consented
 FROM uco_bot_customer_access_641");
 $row=$q->fetch(PDO::FETCH_ASSOC)?:[];
 foreach(['allowed','blocked','consented'] as $k)$counts[$k]=(int)($row[$k]??0);
 $counts['errors']=(int)$db->query('SELECT COUNT(*) FROM uco_bot_errors_641')->fetchColumn();
 $q=$db->query("SELECT origin,COUNT(*) n FROM uco_bot_order_origin_641 GROUP BY origin");
 foreach($q as $r)$counts[$r['origin'].'_orders']=(int)$r['n'];
 return $counts;
}
