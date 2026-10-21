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
 f.state AS fulfillment_state,f.updated_at AS fulfillment_updated_at
 FROM uco_orders o JOIN customers c ON c.id=o.customer_id
 LEFT JOIN uco_simulated_payments p ON p.order_id=o.id
 LEFT JOIN uco_order_fulfillment f ON f.order_id=o.id
 ORDER BY o.id DESC LIMIT 300");
 $rows=$q->fetchAll();
 $events=$db->prepare("SELECT e.previous_state,e.new_state,e.note,e.created_at,COALESCE(a.display_name, 'Usuario no disponible') AS actor FROM uco_order_fulfillment_events e LEFT JOIN admin_users a ON a.id=e.actor_staff_id WHERE e.order_id=? ORDER BY e.id ASC");
 $resets=$db->prepare("SELECT r.previous_state,r.reason,r.verification_method,r.created_at,COALESCE(a.display_name, 'Usuario no disponible') AS actor FROM uco_order_fulfillment_resets r LEFT JOIN admin_users a ON a.id=r.actor_staff_id WHERE r.order_id=? ORDER BY r.id ASC");
 $lines=$db->prepare('SELECT sku,name,unit,quantity,unit_price_cents,line_cents FROM uco_order_lines WHERE order_id=? ORDER BY id');
 foreach($rows as &$row){
  $lines->execute([(int)$row['id']]);$row['lines']=$lines->fetchAll();
  $events->execute([(int)$row['id']]);$row['events']=$events->fetchAll();
  $resets->execute([(int)$row['id']]);$row['resets']=$resets->fetchAll();
  $row['payment_confirmed']=($row['payment_order_state']==='paid'&&$row['payment_status']==='simulated_approved'&&$row['simulation_state']==='approved');
 }unset($row);
 replyOrders(['orders'=>$rows,'read_only'=>$staff['role']!=='admin']);
}catch(Throwable $e){error_log('UCO Pedidos 2B: '.$e->getMessage());replyOrders(['error'=>'No se pudo consultar el listado de pedidos. Comprueba que has aplicado la Fase 2A.'],500);}
