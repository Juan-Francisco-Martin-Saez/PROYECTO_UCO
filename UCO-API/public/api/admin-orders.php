<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');
session_name('UCOADMIN');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);
session_start();
function replyOrders(array $body,int $status=200):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true))replyOrders(['error'=>'Método no permitido'],405);
 $staffId=(int)($_SESSION['uco_user_id']??0);
 if($staffId<1)replyOrders(['error'=>'Inicia sesión en administración'],401);
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000');$db->exec('PRAGMA foreign_keys=ON');
 require_once dirname(__DIR__,2).'/private/identity.php';
 $q=$db->prepare('SELECT id,role,active,password_hash FROM admin_users WHERE id=?');
 $q->execute([$staffId]);$staff=$q->fetch();
 if(!$staff||(int)$staff['active']!==1||!in_array($staff['role'],['admin','manager'],true)||!hash_equals((string)($_SESSION['uco_auth_stamp']??''),hash('sha256',(string)$staff['password_hash']))||!ucoIdentitySessionValid($db,'staff',$staffId,$_SESSION['uco_identity_id']??null))replyOrders(['error'=>'Sesión no válida'],403);
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if($staff['role']!=='admin')replyOrders(['error'=>'Solo administración puede cambiar estados de preparación'],403);
  if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))replyOrders(['error'=>'Token de seguridad no válido'],403);
  $data=json_decode(file_get_contents('php://input'),true);
  if(!is_array($data))replyOrders(['error'=>'Solicitud no válida'],400);
  $orderId=filter_var($data['order_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  $action=(string)($data['action']??'');
  if($action==='verify_line'){
   $lineId=filter_var($data['line_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
   $quantity=$data['prepared_quantity']??null;
   $note=trim((string)($data['note']??''));
   if(!$orderId||!$lineId||!is_scalar($quantity)||!preg_match('/^(?:0|[1-9]\d{0,8})(?:\.\d{1,3})?$/',(string)$quantity)||mb_strlen($note)>500)replyOrders(['error'=>'Cantidad o datos no válidos'],400);
   $prepared=(float)$quantity;
   $db->exec('BEGIN IMMEDIATE');
   $q=$db->prepare("SELECT f.state,f.assigned_staff_id,o.status,o.payment_status,p.state AS payment_sim FROM uco_order_fulfillment f JOIN uco_orders o ON o.id=f.order_id LEFT JOIN uco_simulated_payments p ON p.order_id=o.id WHERE f.order_id=?");
   $q->execute([$orderId]);$current=$q->fetch();
   if(!$current||!in_array($current['state'],['pending_preparation','preparing','incident'],true)){$db->rollBack();replyOrders(['error'=>'Este pedido no admite verificaciones en su estado actual'],409);}
   if($current['status']!=='paid'||$current['payment_status']!=='simulated_approved'||$current['payment_sim']!=='approved'){$db->rollBack();replyOrders(['error'=>'Pago simulado no verificado'],409);}
   $q=$db->prepare('SELECT id,quantity FROM uco_order_lines WHERE id=? AND order_id=?');$q->execute([$lineId,$orderId]);$line=$q->fetch();
   if(!$line){$db->rollBack();replyOrders(['error'=>'Artículo no encontrado en este pedido'],404);}
   $requested=(float)$line['quantity'];
   if($prepared>$requested+0.00001){$db->rollBack();replyOrders(['error'=>'La cantidad preparada no puede superar la solicitada'],422);}
   if($prepared>0&&abs($prepared-$requested)>0.00001&&mb_strlen($note)<5){$db->rollBack();replyOrders(['error'=>'Indica el motivo de la diferencia (mínimo 5 caracteres)'],422);}
   if($prepared==0.0){
    $q=$db->prepare('DELETE FROM uco_order_line_checks WHERE order_id=? AND line_id=?');$q->execute([$orderId,$lineId]);
    $q=$db->prepare('INSERT INTO uco_order_line_check_events(order_id,line_id,prepared_quantity,note,actor_staff_id) VALUES(?,?,?,?,?)');$q->execute([$orderId,$lineId,0,'Verificación retirada (cantidad 0)',$staffId]);
    $db->commit();replyOrders(['ok'=>true,'order_id'=>$orderId,'line_id'=>$lineId,'unverified'=>true]);
   }
   $q=$db->prepare('INSERT INTO uco_order_line_checks(order_id,line_id,prepared_quantity,note,verified_by_staff_id,updated_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(line_id) DO UPDATE SET prepared_quantity=excluded.prepared_quantity,note=excluded.note,verified_by_staff_id=excluded.verified_by_staff_id,updated_at=CURRENT_TIMESTAMP');
   $q->execute([$orderId,$lineId,$prepared,$note,$staffId]);
   $q=$db->prepare('INSERT INTO uco_order_line_check_events(order_id,line_id,prepared_quantity,note,actor_staff_id) VALUES(?,?,?,?,?)');$q->execute([$orderId,$lineId,$prepared,$note,$staffId]);
   $db->commit();replyOrders(['ok'=>true,'order_id'=>$orderId,'line_id'=>$lineId]);
  }
  $assign=$action==='assign_staff';
  if($assign){
   $rawTarget=$data['staff_id']??null;
   $target=$rawTarget===null?null:filter_var($rawTarget,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
   if(!$orderId||($rawTarget!==null&&$target===false))replyOrders(['error'=>'Selecciona un empleado válido'],400);
   $db->exec('BEGIN IMMEDIATE');
   $q=$db->prepare("SELECT f.assigned_staff_id,f.state,o.status,o.payment_status,p.state AS payment_sim FROM uco_order_fulfillment f JOIN uco_orders o ON o.id=f.order_id LEFT JOIN uco_simulated_payments p ON p.order_id=o.id WHERE f.order_id=?");
   $q->execute([$orderId]);$current=$q->fetch();
   if(!$current||!in_array($current['state'],['pending_preparation','preparing','incident'],true)){$db->rollBack();replyOrders(['error'=>'Inicia primero la preparación; este estado no permite asignación'],409);}
   if($current['status']!=='paid'||$current['payment_status']!=='simulated_approved'||$current['payment_sim']!=='approved'){$db->rollBack();replyOrders(['error'=>'Pago simulado no verificado'],409);}
   if($target!==null){
    $q=$db->prepare("SELECT id,active,role FROM admin_users WHERE id=?");$q->execute([$target]);$person=$q->fetch();
    if(!$person||(int)$person['active']!==1||!in_array($person['role'],['admin','manager'],true)){$db->rollBack();replyOrders(['error'=>'Empleado no disponible'],422);}
   }
   $old=$current['assigned_staff_id']!==null?(int)$current['assigned_staff_id']:null;
   if($old===$target){$db->rollBack();replyOrders(['error'=>'La asignación ya tiene ese valor'],409);}
   $q=$db->prepare('UPDATE uco_order_fulfillment SET assigned_staff_id=?,updated_by_staff_id=?,updated_at=CURRENT_TIMESTAMP WHERE order_id=?');
   $q->execute([$target,$staffId,$orderId]);
   $q=$db->prepare('INSERT INTO uco_order_assignment_events(order_id,previous_staff_id,new_staff_id,actor_staff_id) VALUES(?,?,?,?)');
   $q->execute([$orderId,$old,$target,$staffId]);
   $db->commit();replyOrders(['ok'=>true,'order_id'=>$orderId,'assigned_staff_id'=>$target]);
  }
  $next=(string)($data['state']??'');
  $reset=($next==='__reset__');
  $note=trim((string)($data['note']??''));
  if(!$orderId||strlen($note)>500)replyOrders(['error'=>'Datos no válidos'],400);
  $allowed=[
   ''=>['pending_preparation'],
   'pending_preparation'=>['preparing','incident','cancelled'],
   'preparing'=>['prepared','incident'],
   'prepared'=>['dispatched','incident'],
   'dispatched'=>['delivered','incident'],
   'incident'=>['pending_preparation','cancelled'],
   'delivered'=>[],
   'cancelled'=>[]
  ];
  if(!$reset&&!in_array($next,['pending_preparation','preparing','prepared','dispatched','delivered','incident','cancelled'],true))replyOrders(['error'=>'Estado desconocido'],400);
  $db->exec('BEGIN IMMEDIATE');
  $q=$db->prepare("SELECT o.id,o.status,o.payment_status,p.state AS simulation_state,f.state AS fulfillment_state FROM uco_orders o LEFT JOIN uco_simulated_payments p ON p.order_id=o.id LEFT JOIN uco_order_fulfillment f ON f.order_id=o.id WHERE o.id=?");
  $q->execute([$orderId]);$order=$q->fetch();
  if(!$order){$db->rollBack();replyOrders(['error'=>'Pedido no encontrado'],404);}
  if($order['status']!=='paid'||$order['payment_status']!=='simulated_approved'||$order['simulation_state']!=='approved'){
   $db->rollBack();replyOrders(['error'=>'El pedido no tiene un pago simulado aprobado y verificado'],409);
  }
  if($reset){
   $password=(string)($data['admin_password']??'');
   if($password===''||strlen($password)>1024||!password_verify($password,(string)$staff['password_hash'])){
    $db->rollBack();usleep(300000);replyOrders(['error'=>'Contraseña administrativa incorrecta'],403);
   }
   if(mb_strlen($note)<12){$db->rollBack();replyOrders(['error'=>'Explica el motivo del reinicio (mínimo 12 caracteres)'],400);}
   $previous=(string)($order['fulfillment_state']??'');
   if($previous===''){$db->rollBack();replyOrders(['error'=>'El pedido ya está sin iniciar'],409);}
   // Un reinicio elimina el progreso vigente, pero conserva el historial auditado.
   $q=$db->prepare('DELETE FROM uco_order_line_checks WHERE order_id=?');$q->execute([$orderId]);
   $q=$db->prepare("DELETE FROM uco_order_fulfillment WHERE order_id=? AND state=?");
   $q->execute([$orderId,$previous]);
   if($q->rowCount()!==1)throw new RuntimeException('Conflicto de estado');
   $q=$db->prepare("INSERT INTO uco_order_fulfillment_resets(order_id,previous_state,actor_staff_id,reason,verification_method) VALUES(?,?,?,?,'admin_password')");
   $q->execute([$orderId,$previous,$staffId,$note]);
   $db->commit();
   replyOrders(['ok'=>true,'order_id'=>$orderId,'state'=>null,'reset'=>true]);
  }
  $previous=(string)($order['fulfillment_state']??'');
  if(!in_array($next,$allowed[$previous]??[],true)){$db->rollBack();replyOrders(['error'=>'Transición de estado no permitida'],409);}
  if($next==='prepared'){
   $q=$db->prepare('SELECT COUNT(*) FROM uco_order_lines WHERE order_id=?');$q->execute([$orderId]);$total=(int)$q->fetchColumn();
   $q=$db->prepare('SELECT COUNT(*) FROM uco_order_lines l JOIN uco_order_line_checks c ON c.line_id=l.id AND c.order_id=l.order_id WHERE l.order_id=? AND ABS(CAST(l.quantity AS REAL)-c.prepared_quantity)<0.00001');$q->execute([$orderId]);$complete=(int)$q->fetchColumn();
   if($total===0||$complete!==$total){$db->rollBack();replyOrders(['error'=>'Verifica todas las líneas con sus cantidades completas antes de marcar el pedido como Preparado'],409);}
  }
  if($previous===''){
   $q=$db->prepare("INSERT INTO uco_order_fulfillment(order_id,state,updated_by_staff_id) VALUES(?,?,?)");
   $q->execute([$orderId,$next,$staffId]);
  }else{
   $q=$db->prepare("UPDATE uco_order_fulfillment SET state=?,updated_by_staff_id=?,updated_at=CURRENT_TIMESTAMP WHERE order_id=? AND state=?");
   $q->execute([$next,$staffId,$orderId,$previous]);
   if($q->rowCount()!==1)throw new RuntimeException('Conflicto de actualización');
  }
  $q=$db->prepare('INSERT INTO uco_order_fulfillment_events(order_id,previous_state,new_state,actor_staff_id,note) VALUES(?,?,?,?,?)');
  $q->execute([$orderId,$previous?:null,$next,$staffId,$note]);
  $db->commit();
  replyOrders(['ok'=>true,'order_id'=>$orderId,'state'=>$next]);
 }

 $q=$db->query("SELECT o.id,o.customer_id,c.company AS customer,o.status AS payment_order_state,o.payment_status,o.subtotal_cents,o.created_at,
 p.state AS simulation_state,p.method AS payment_method,p.total_cents,
 f.state AS fulfillment_state,f.updated_at AS fulfillment_updated_at,f.assigned_staff_id,assigned.display_name AS assigned_staff_name
 FROM uco_orders o JOIN customers c ON c.id=o.customer_id
 LEFT JOIN uco_simulated_payments p ON p.order_id=o.id
 LEFT JOIN uco_order_fulfillment f ON f.order_id=o.id
 LEFT JOIN admin_users assigned ON assigned.id=f.assigned_staff_id
 ORDER BY o.id DESC LIMIT 300");
 $rows=$q->fetchAll();
 $staffList=$db->query("SELECT id,display_name FROM admin_users WHERE active=1 AND role IN ('admin','manager') ORDER BY display_name,id")->fetchAll();
 $events=$db->prepare("SELECT e.previous_state,e.new_state,e.note,e.created_at,COALESCE(a.display_name, 'Usuario no disponible') AS actor FROM uco_order_fulfillment_events e LEFT JOIN admin_users a ON a.id=e.actor_staff_id WHERE e.order_id=? ORDER BY e.id ASC");
 $resets=$db->prepare("SELECT r.previous_state,r.reason,r.verification_method,r.created_at,COALESCE(a.display_name, 'Usuario no disponible') AS actor FROM uco_order_fulfillment_resets r LEFT JOIN admin_users a ON a.id=r.actor_staff_id WHERE r.order_id=? ORDER BY r.id ASC");
 $lines=$db->prepare('SELECT l.id,l.sku,l.name,l.unit,l.quantity,l.unit_price_cents,l.line_cents,c.prepared_quantity,c.note AS check_note,c.updated_at AS checked_at,a.display_name AS checked_by FROM uco_order_lines l LEFT JOIN uco_order_line_checks c ON c.line_id=l.id LEFT JOIN admin_users a ON a.id=c.verified_by_staff_id WHERE l.order_id=? ORDER BY l.id');
 foreach($rows as &$row){
  $lines->execute([(int)$row['id']]);$row['lines']=$lines->fetchAll();
  $events->execute([(int)$row['id']]);$row['events']=$events->fetchAll();
  $resets->execute([(int)$row['id']]);$row['resets']=$resets->fetchAll();
  $row['payment_confirmed']=($row['payment_order_state']==='paid'&&$row['payment_status']==='simulated_approved'&&$row['simulation_state']==='approved');
 }unset($row);
 replyOrders(['orders'=>$rows,'staff'=>$staffList,'read_only'=>$staff['role']!=='admin']);
}catch(Throwable $e){error_log('UCO Pedidos 2B: '.$e->getMessage());replyOrders(['error'=>'No se pudo consultar el listado de pedidos. Comprueba que has aplicado la Fase 2A.'],500);}
