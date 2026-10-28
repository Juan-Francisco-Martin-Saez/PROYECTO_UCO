<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
ini_set('session.use_trans_sid','0');
session_name('UCOADMIN');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);
session_start();
require_once dirname(__DIR__,2).'/private/session-security.php';
function out66(array $v,int $status=200):never {http_response_code($status);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(!in_array(($_SERVER['REQUEST_METHOD']??''),['GET','POST'],true))out66(['error'=>'Método no permitido'],405);
if(ucoSecurityIdleExpired())out66(['error'=>'Sesión caducada'],401);
$id=(int)($_SESSION['uco_user_id']??0);
if($id<1)out66(['error'=>'No autenticado'],401);
try {
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000');
 require_once dirname(__DIR__,2).'/private/identity.php';
 require_once dirname(__DIR__,2).'/private/access-permissions.php';
 $q=$db->prepare('SELECT id,role,active,password_hash FROM admin_users WHERE id=?');$q->execute([$id]);$u=$q->fetch();
 if(!$u||(int)$u['active']!==1||!in_array($u['role'],['admin','manager'],true)||!hash_equals((string)($_SESSION['uco_auth_stamp']??''),hash('sha256',(string)$u['password_hash']))||!ucoIdentitySessionValid($db,'staff',$id,$_SESSION['uco_identity_id']??null))out66(['error'=>'Sesión no válida'],403);
 $caps=ucoOrderCapabilities($db,$id,(string)$u['role']);
 if(!ucoOrderCan($caps,'orders.view'))out66(['error'=>'No autorizado para consultar pedidos'],403);
 if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  if(!ucoOrderCan($caps,'orders.manage'))out66(['error'=>'Se requiere permiso de gestión de pedidos'],403);
  if(!ucoSecurityCsrfValid('uco_payments_csrf'))out66(['error'=>'CSRF inválido'],403);
  if(!str_starts_with((string)($_SERVER['CONTENT_TYPE']??''),'application/json'))out66(['error'=>'Formato inválido'],415);
  $raw=file_get_contents('php://input',false,null,0,4097);
  if($raw===false||strlen($raw)>4096)out66(['error'=>'Solicitud demasiado grande'],413);
  $body=json_decode($raw,true);
  if(!is_array($body)||array_diff(array_keys($body),['task_id','decision','note'])||!isset($body['task_id'],$body['decision'],$body['note']))out66(['error'=>'Solicitud inválida'],422);
  $taskId=filter_var($body['task_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  $decision=$body['decision'];$note=$body['note'];
  if(!$taskId||!in_array($decision,['resolved','dismissed'],true)||!is_string($note)||strlen(trim($note))<8||strlen($note)>1000)out66(['error'=>'Decisión o motivo inválidos (mínimo 8 caracteres)'],422);
  require_once dirname(__DIR__,2).'/private/payment-operations-65.php';
  ucoPayment65Schema($db);
  $db->exec("CREATE TABLE IF NOT EXISTS uco_payment_review_audit (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER NOT NULL, reference TEXT NOT NULL, decision TEXT NOT NULL, note TEXT NOT NULL, actor_id INTEGER NOT NULL, previous_status TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
  $db->exec('BEGIN IMMEDIATE');
  try{
   $q=$db->prepare('SELECT id,reference,status FROM uco_payment_review_tasks WHERE id=?');$q->execute([$taskId]);$task=$q->fetch();
   if(!$task||$task['status']!=='open'){$db->rollBack();out66(['error'=>'La tarea ya no está abierta'],409);}
   $q=$db->prepare("UPDATE uco_payment_review_tasks SET status=?,resolved_at=CURRENT_TIMESTAMP WHERE id=? AND status='open'");$q->execute([$decision,$taskId]);
   if($q->rowCount()!==1)throw new RuntimeException('Conflicto de actualización');
   $db->prepare('INSERT INTO uco_payment_review_audit(task_id,reference,decision,note,actor_id,previous_status) VALUES(?,?,?,?,?,?)')->execute([$taskId,$task['reference'],$decision,trim($note),$id,$task['status']]);
   $db->commit();out66(['ok'=>true,'task_id'=>$taskId,'status'=>$decision]);
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 if(empty($_SESSION['uco_payments_csrf']))$_SESSION['uco_payments_csrf']=bin2hex(random_bytes(32));
 $canManage=ucoOrderCan($caps,'orders.manage');
 $table=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='uco_real_payments'")->fetchColumn();
 if(!$table)out66(['ok'=>true,'configured'=>false,'payments'=>[],'tasks'=>[],'counts'=>['pending'=>0,'paid'=>0,'review'=>0,'failed'=>0,'cancelled'=>0],'can_manage'=>$canManage,'csrf'=>$_SESSION['uco_payments_csrf']]);
 $limit=filter_input(INPUT_GET,'limit',FILTER_VALIDATE_INT,['options'=>['default'=>100,'min_range'=>1,'max_range'=>200]]);if(!$limit)$limit=100;
 $status=(string)($_GET['status']??'all');
 if(!in_array($status,['all','pending','paid','review','failed','cancelled'],true))out66(['error'=>'Filtro inválido'],422);
 $where=$status==='all'?'':' WHERE p.state=:state';
 $sql='SELECT p.reference,p.method,p.state,p.amount_cents,p.currency,p.created_at,p.updated_at,p.order_id,o.status AS order_status,o.payment_status FROM uco_real_payments p LEFT JOIN uco_orders o ON o.id=p.order_id'.$where.' ORDER BY p.id DESC LIMIT '.(int)$limit;
 $stmt=$db->prepare($sql);if($status!=='all')$stmt->bindValue(':state',$status);$stmt->execute();$payments=$stmt->fetchAll();
 $counts=['pending'=>0,'paid'=>0,'review'=>0,'failed'=>0,'cancelled'=>0];foreach($db->query('SELECT state,COUNT(*) AS n FROM uco_real_payments GROUP BY state') as $r){if(array_key_exists($r['state'],$counts))$counts[$r['state']]=(int)$r['n'];}
 $tasks=[];
 if($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='uco_payment_review_tasks'")->fetchColumn()){
  $tasks=$db->query("SELECT t.id,t.reference,t.kind,t.status,t.note,t.evidence_ref,t.actor,t.created_at,p.method,p.state AS payment_state FROM uco_payment_review_tasks t LEFT JOIN uco_real_payments p ON p.reference=t.reference WHERE t.status='open' ORDER BY t.id DESC LIMIT 100")->fetchAll();
 }
 out66(['ok'=>true,'configured'=>true,'payments'=>$payments,'tasks'=>$tasks,'counts'=>$counts,'can_manage'=>$canManage,'csrf'=>$_SESSION['uco_payments_csrf']]);
} catch(Throwable $e){error_log('UCO payment-admin-66: '.$e->getMessage());out66(['error'=>'No se pudo consultar el registro de pagos'],500);}
