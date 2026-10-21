<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');session_name('UCOADMIN');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);session_start();
function respond($v,int $code=200):never{http_response_code($code);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function norm(string $s):string{$s=mb_strtolower($s,'UTF-8');if(class_exists('Normalizer'))$s=Normalizer::normalize($s,Normalizer::FORM_D);return strtr($s,['á'=>'a','à'=>'a','ä'=>'a','â'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u']);}
function db():PDO{$db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=5000');return $db;}
require_once __DIR__.'/commercial.php';
require_once dirname(__DIR__,2).'/private/identity.php';
function authorized():bool{return (int)($_SESSION['uco_user_id']??0)>0;}
function usersDb():PDO{$db=db();$db->exec("CREATE TABLE IF NOT EXISTS admin_users(id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL COLLATE NOCASE UNIQUE, display_name TEXT NOT NULL, email TEXT NOT NULL DEFAULT '', password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'manager' CHECK(role IN ('admin','manager')), active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");$columns=$db->query('PRAGMA table_info(admin_users)')->fetchAll();if(!in_array('avatar',array_column($columns,'name'),true))$db->exec("ALTER TABLE admin_users ADD COLUMN avatar TEXT NOT NULL DEFAULT ''");return $db;}
function adminOnly():void{if(($_SESSION['uco_role']??'')!=='admin')respond(['error'=>'Solo el administrador principal puede gestionar usuarios.'],403);}
function userInfo(array $u):array{return ['id'=>(int)$u['id'],'username'=>$u['username'],'display_name'=>$u['display_name'],'email'=>$u['email'],'avatar'=>$u['avatar']??'','role'=>$u['role'],'active'=>(int)$u['active'],'created_at'=>$u['created_at']];}
function validatePassword(string $p):void{if(strlen($p)<12||strlen($p)>256)respond(['error'=>'La contraseña debe tener entre 12 y 256 caracteres.'],422);}
function activeAdmins(PDO $db):int{return (int)$db->query("SELECT COUNT(*) FROM admin_users WHERE role='admin' AND active=1")->fetchColumn();}
function csrf():void{if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))respond(['error'=>'Sesión no válida. Actualiza la página.'],403);}
$action=(string)($_GET['action']??'session');$method=$_SERVER['REQUEST_METHOD'];
try{
if($action==='session'&&$method==='GET'){
 if(!authorized())respond(['authenticated'=>false,'csrf'=>null,'user'=>null]);
 $sessionDb=db();
 $sessionCheck=$sessionDb->prepare('SELECT * FROM admin_users WHERE id=? AND active=1');
 $sessionCheck->execute([(int)$_SESSION['uco_user_id']]);
 $sessionUser=$sessionCheck->fetch();
 if(!$sessionUser||!ucoIdentitySessionValid($sessionDb,'staff',(int)$_SESSION['uco_user_id'],$_SESSION['uco_identity_id']??null)||!hash_equals((string)($_SESSION['uco_auth_stamp']??''),hash('sha256',(string)$sessionUser['password_hash']))){
  $_SESSION=[];session_destroy();respond(['authenticated'=>false,'csrf'=>null,'user'=>null]);
 }
 $_SESSION['uco_role']=$sessionUser['role'];$_SESSION['uco_user']=userInfo($sessionUser);
 respond(['authenticated'=>true,'csrf'=>$_SESSION['csrf']??null,'user'=>$_SESSION['uco_user']]);
}
if($action==='login'&&$method==='POST'){
 $username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
 $db=usersDb();$existing=(int)$db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
 // Migración segura: el primer usuario conserva la contraseña ya configurada en el servidor.
 if($existing===0&&strcasecmp($username,'Administrador')===0){
  $legacy=(string)(getenv('UCO_ADMIN_PASSWORD_HASH')?:'');
  if($legacy!==''&&password_verify($password,$legacy)){
   $st=$db->prepare("INSERT OR IGNORE INTO admin_users(username,display_name,email,password_hash,role,active) VALUES('Administrador','Administrador','',?,'admin',1)");$st->execute([$legacy]);
  }
 }
 $st=$db->prepare('SELECT * FROM admin_users WHERE username=? COLLATE NOCASE LIMIT 1');$st->execute([$username]);$u=$st->fetch();
 if(!$u||!(int)$u['active']||!password_verify($password,$u['password_hash'])){usleep(450000);respond(['error'=>'Usuario o contraseña incorrectos.'],401);}
 if(password_needs_rehash($u['password_hash'],PASSWORD_DEFAULT)){$db->prepare('UPDATE admin_users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$u['id']]);}
 $identityId=ucoIdentityResolve($db,'staff',(int)$u['id'],true);if($identityId===null)respond(['error'=>'No se pudo vincular la identidad.'],503);
 session_regenerate_id(true);$_SESSION['uco_user_id']=(int)$u['id'];$_SESSION['uco_identity_id']=$identityId;$_SESSION['uco_role']=$u['role'];$_SESSION['uco_user']=userInfo($u);$_SESSION['uco_auth_stamp']=hash('sha256',$u['password_hash']);$_SESSION['csrf']=bin2hex(random_bytes(32));respond(['authenticated'=>true,'csrf'=>$_SESSION['csrf'],'user'=>$_SESSION['uco_user']]);
}
if(!authorized())respond(['error'=>'Acceso no autorizado'],401);
if($action==='logout'&&$method==='POST'){csrf();$_SESSION=[];session_destroy();respond(['ok'=>true]);}
$db=usersDb();
$cols=array_column($db->query('PRAGMA table_info(products)')->fetchAll(),'name');if(!in_array('description',$cols,true))$db->exec("ALTER TABLE products ADD COLUMN description TEXT NOT NULL DEFAULT ''");if(!in_array('unit',$cols,true))$db->exec("ALTER TABLE products ADD COLUMN unit TEXT NOT NULL DEFAULT 'unidad'");
// Si una cuenta se desactiva o cambia de rol, las sesiones anteriores dejan de autorizarse.
$check=$db->prepare('SELECT * FROM admin_users WHERE id=? AND active=1');$check->execute([$_SESSION['uco_user_id']]);$current=$check->fetch();
if(!$current||!ucoIdentitySessionValid($db,'staff',(int)$_SESSION['uco_user_id'],$_SESSION['uco_identity_id']??null)||!hash_equals((string)($_SESSION['uco_auth_stamp']??''),hash('sha256',$current['password_hash']))){$_SESSION=[];session_destroy();respond(['error'=>'La sesión ya no es válida. Vuelve a entrar.'],401);}
$_SESSION['uco_role']=$current['role'];$_SESSION['uco_user']=userInfo($current);
if($action==='account_history_index'&&$method==='GET'){
 adminOnly();
 $counts=[];
 $q=$db->query('SELECT customer_id,COUNT(*) AS n FROM uco_orders GROUP BY customer_id');
 foreach($q->fetchAll() as $r)$counts[(string)$r['customer_id']]=(int)$r['n'];
 respond(['counts'=>$counts]);
}
if($action==='account_receipt'&&$method==='GET'){
 adminOnly();
 $oid=filter_var($_GET['order_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$oid)respond(['error'=>'Pedido no válido'],422);
 $st=$db->prepare('SELECT o.id,o.customer_id,o.status,o.payment_status,o.created_at,p.method,p.state,p.reference,p.subtotal_cents,p.tax_cents,p.shipping_cents,p.total_cents,p.delivery_json,p.tax_json,p.updated_at AS operation_at FROM uco_orders o JOIN uco_simulated_payments p ON p.order_id=o.id WHERE o.id=?');
 $st->execute([$oid]);$order=$st->fetch();
 if(!$order)respond(['error'=>'Justificante no encontrado'],404);
 if($order['state']!=='approved'||$order['payment_status']!=='simulated_approved')respond(['error'=>'Solo disponible para pagos simulados aprobados'],409);
 $st=$db->prepare('SELECT l.product_id,l.sku,l.name,l.unit,l.quantity,l.unit_price_cents,l.line_cents,p.image FROM uco_order_lines l LEFT JOIN products p ON p.id=l.product_id WHERE l.order_id=? ORDER BY l.id');
 $st->execute([$oid]);$items=$st->fetchAll();if(!$items)respond(['error'=>'El pedido no tiene líneas'],409);
 foreach($items as &$item){$src=(string)($item['image']??'');$item['image']=preg_match('~^\./img/(?:productos/[a-f0-9]+|[0-9]{2})\.webp$~',$src)?'../'.substr($src,2):null;}unset($item);
 $delivery=json_decode((string)$order['delivery_json'],true);$tax=json_decode((string)$order['tax_json'],true);
 respond(['simulation'=>true,'order_id'=>(int)$order['id'],'status'=>$order['status'],'method'=>$order['method'],'state'=>$order['state'],'reference'=>$order['reference'],'created_at'=>$order['created_at'],'operation_at'=>$order['operation_at'],'delivery'=>is_array($delivery)?$delivery:[],'tax_lines'=>is_array($tax)?$tax:[],'items'=>$items,'subtotal_cents'=>(int)$order['subtotal_cents'],'tax_cents'=>(int)$order['tax_cents'],'shipping_cents'=>(int)$order['shipping_cents'],'total_cents'=>(int)$order['total_cents']]);
}
if($action==='account_history'&&$method==='GET'){
 adminOnly();
 $cid=filter_var($_GET['customer_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$cid)respond(['error'=>'Cliente inválido'],422);
 $check=$db->prepare('SELECT id FROM customers WHERE id=?');$check->execute([$cid]);
 if(!$check->fetchColumn())respond(['error'=>'Cliente inexistente'],404);
 $st=$db->prepare('SELECT o.id,o.created_at,o.status,o.payment_status,o.subtotal_cents,p.state AS payment_state,p.reference AS payment_reference FROM uco_orders o LEFT JOIN uco_simulated_payments p ON p.order_id=o.id WHERE o.customer_id=? ORDER BY o.id DESC LIMIT 200');
 $st->execute([$cid]);$orders=$st->fetchAll();
 $lines=$db->prepare('SELECT name,unit,quantity,line_cents FROM uco_order_lines WHERE order_id=? ORDER BY id');
 foreach($orders as &$order){$lines->execute([$order['id']]);$order['lines']=$lines->fetchAll();}unset($order);
 respond(['orders'=>$orders]);
}
// Fase 1C: vínculo explícito empleado-cliente, sin compartir sesiones ni contraseñas.
if($action==='identity_links'&&$method==='GET'){
 adminOnly();
 $rows=$db->query("SELECT a.legacy_id AS staff_id,l.customer_id FROM identity_customer_links l JOIN identity_accounts a ON a.id=l.identity_id WHERE a.kind='staff' ORDER BY a.legacy_id,l.customer_id")->fetchAll();
 respond(['links'=>$rows]);
}
if($action==='identity_link'&&$method==='POST'){
 csrf();adminOnly();
 $staffId=filter_var($_POST['staff_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $customerId=filter_var($_POST['customer_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $proof=(string)($_POST['customer_password']??'');
 if(!$staffId||!$customerId||$proof==='')respond(['error'=>'Selecciona ambas cuentas y acredita el acceso comercial.'],422);
 $st=$db->prepare('SELECT id,active FROM admin_users WHERE id=?');$st->execute([$staffId]);$staff=$st->fetch();
 $st=$db->prepare('SELECT id,active,password_hash FROM customers WHERE id=?');$st->execute([$customerId]);$customer=$st->fetch();
 if(!$staff||!$customer||(int)$staff['active']!==1||(int)$customer['active']!==1||!password_verify($proof,(string)$customer['password_hash'])){
  usleep(450000);respond(['error'=>'No se pudo verificar la vinculación. Comprueba que ambas cuentas estén activas y la contraseña comercial.'],422);
 }
 $identityId=ucoIdentityResolve($db,'staff',(int)$staffId,true);
 if($identityId===null)respond(['error'=>'Identidad interna no disponible.'],409);
 $existing=$db->prepare("SELECT customer_id FROM identity_customer_links WHERE identity_id=? AND customer_id<>?");$existing->execute([$identityId,$customerId]);
 if($existing->fetchColumn()!==false)respond(['error'=>'Esta identidad interna ya está vinculada a otro cliente. Desvincúlala primero.'],409);
 $existing=$db->prepare("SELECT a.legacy_id FROM identity_customer_links l JOIN identity_accounts a ON a.id=l.identity_id WHERE a.kind='staff' AND l.customer_id=? AND a.id<>?");$existing->execute([$customerId,$identityId]);
 if($existing->fetchColumn()!==false)respond(['error'=>'Este cliente ya está vinculado a otra cuenta interna.'],409);
 $db->prepare('INSERT OR IGNORE INTO identity_customer_links(identity_id,customer_id) VALUES(?,?)')->execute([$identityId,$customerId]);
 error_log('UCO identity link staff_id='.(int)$staffId.' customer_id='.(int)$customerId.' by_admin='.(int)$_SESSION['uco_user_id']);
 respond(['ok'=>true]);
}
if($action==='identity_unlink'&&$method==='POST'){
 csrf();adminOnly();
 $staffId=filter_var($_POST['staff_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $customerId=filter_var($_POST['customer_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$staffId||!$customerId)respond(['error'=>'Vínculo no válido.'],422);
 $st=$db->prepare("DELETE FROM identity_customer_links WHERE customer_id=? AND identity_id IN (SELECT id FROM identity_accounts WHERE kind='staff' AND legacy_id=?)");
 $st->execute([$customerId,$staffId]);
 error_log('UCO identity unlink staff_id='.(int)$staffId.' customer_id='.(int)$customerId.' by_admin='.(int)$_SESSION['uco_user_id']);
 respond(['ok'=>true]);
}
if($action==='users'&&$method==='GET'){adminOnly();respond(['users'=>array_map('userInfo',$db->query('SELECT * FROM admin_users ORDER BY id')->fetchAll())]);}
if($action==='user_save'&&$method==='POST'){
 csrf();adminOnly();$id=(int)($_POST['id']??0);$username=trim((string)($_POST['username']??''));$display=trim((string)($_POST['display_name']??''));$email=trim((string)($_POST['email']??''));$role=(string)($_POST['role']??'manager');$active=(string)($_POST['active']??'1')==='1'?1:0;$pass=(string)($_POST['password']??'');
 $avatar=null;$upload=$_FILES['avatar']??null;
 if($upload&&$upload['error']!==UPLOAD_ERR_NO_FILE){
  if($upload['error']!==UPLOAD_ERR_OK||$upload['size']>5*1024*1024)respond(['error'=>'Imagen de usuario inválida o superior a 5 MB.'],422);
  if(!extension_loaded('gd'))respond(['error'=>'Se necesita GD para procesar imágenes.'],503);
  $info=@getimagesize($upload['tmp_name']);if(!$info||$info[0]<1||$info[1]<1||$info[0]>8000||$info[1]>8000||$info[0]*$info[1]>25000000||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true))respond(['error'=>'Selecciona una fotografía JPG, PNG o WebP válida.'],422);
  $src=match($info[2]){IMAGETYPE_JPEG=>@imagecreatefromjpeg($upload['tmp_name']),IMAGETYPE_PNG=>@imagecreatefrompng($upload['tmp_name']),IMAGETYPE_WEBP=>@imagecreatefromwebp($upload['tmp_name'])};if(!$src)respond(['error'=>'No se pudo procesar la fotografía.'],422);
  $dst=imagecreatetruecolor(240,240);$bg=imagecolorallocate($dst,248,249,246);imagefill($dst,0,0,$bg);$scale=min(240/$info[0],240/$info[1]);$w=max(1,(int)round($info[0]*$scale));$h=max(1,(int)round($info[1]*$scale));imagecopyresampled($dst,$src,(int)((240-$w)/2),(int)((240-$h)/2),0,0,$w,$h,$info[0],$info[1]);imagedestroy($src);
  $dir=dirname(__DIR__).'/img/usuarios';if(!is_dir($dir)&&!mkdir($dir,0755,true))respond(['error'=>'No se pudo crear la carpeta de perfiles.'],503);
  $filename=bin2hex(random_bytes(16)).'.webp';if(!imagewebp($dst,$dir.'/'.$filename,90)){imagedestroy($dst);respond(['error'=>'No se pudo guardar la imagen.'],503);}imagedestroy($dst);$avatar='./img/usuarios/'.$filename;
 }
 if(!preg_match('/^[\p{L}\p{N}_.-]{3,40}$/u',$username)||mb_strlen($display)<2||mb_strlen($display)>100||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))||!in_array($role,['admin','manager'],true))respond(['error'=>'Revisa el usuario, nombre, correo y permisos.'],422);
 if($id){$st=$db->prepare('SELECT * FROM admin_users WHERE id=?');$st->execute([$id]);$old=$st->fetch();if(!$old)respond(['error'=>'Usuario inexistente.'],404);if($old['role']==='admin'&&(int)$old['active']===1&&($role!=='admin'||!$active)&&activeAdmins($db)<=1)respond(['error'=>'Debe quedar al menos un administrador principal activo.'],422);if($id===(int)$_SESSION['uco_user_id']&&(!$active||$role!=='admin'))respond(['error'=>'No puedes quitarte tus propios permisos desde esta sesión.'],422);if($pass!=='')validatePassword($pass);
  $sql='UPDATE admin_users SET username=?,display_name=?,email=?,role=?,active=?,updated_at=CURRENT_TIMESTAMP'.($pass!==''?',password_hash=?':'').($avatar!==null?',avatar=?':'').' WHERE id=?';$args=[$username,$display,$email,$role,$active];if($pass!=='')$args[]=password_hash($pass,PASSWORD_DEFAULT);if($avatar!==null)$args[]=$avatar;$args[]=$id;$db->prepare($sql)->execute($args);
 }else{validatePassword($pass);$db->prepare('INSERT INTO admin_users(username,display_name,email,role,active,password_hash,avatar) VALUES(?,?,?,?,?,?,?)')->execute([$username,$display,$email,$role,$active,password_hash($pass,PASSWORD_DEFAULT),$avatar??'']);}
 if($id===(int)$_SESSION['uco_user_id']){ $st=$db->prepare('SELECT * FROM admin_users WHERE id=?');$st->execute([$id]);$me=$st->fetch();if($me){$_SESSION['uco_user']=userInfo($me);}}respond(['ok'=>true,'user'=>$_SESSION['uco_user']]);
}
if($action==='user_delete'&&$method==='POST'){
 csrf();adminOnly();$id=(int)($_POST['id']??0);if($id===(int)$_SESSION['uco_user_id'])respond(['error'=>'No puedes eliminar tu propia cuenta.'],422);$st=$db->prepare('SELECT role,active FROM admin_users WHERE id=?');$st->execute([$id]);$u=$st->fetch();if(!$u)respond(['error'=>'Usuario inexistente.'],404);if($u['role']==='admin'&&(int)$u['active']===1&&activeAdmins($db)<=1)respond(['error'=>'Debe quedar un administrador principal activo.'],422);$db->prepare('DELETE FROM admin_users WHERE id=?')->execute([$id]);respond(['ok'=>true]);
}
if($action==='change_password'&&$method==='POST'){
 csrf();$old=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');if(!password_verify($old,$current['password_hash']))respond(['error'=>'La contraseña actual no es correcta.'],422);validatePassword($new);$newHash=password_hash($new,PASSWORD_DEFAULT);$db->prepare('UPDATE admin_users SET password_hash=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$newHash,$current['id']]);$_SESSION['uco_auth_stamp']=hash('sha256',$newHash);respond(['ok'=>true]);
}
// v1.9: administración comercial, separada de las cuentas de empleados.
if(in_array($action,['commercial_list','customer_save','customer_delete','group_save','group_delete','price_save','price_update','price_delete','base_save','discount_save','promotion_save','promotion_delete'],true)){
 adminOnly();commercialDb($db);
 if($action==='commercial_list'&&$method==='GET'){
  $customers=$db->query('SELECT id,company,tax_id,contact,email,group_id,active FROM customers ORDER BY company COLLATE NOCASE')->fetchAll();
  $groups=$db->query('SELECT id,name,active FROM customer_groups ORDER BY name COLLATE NOCASE')->fetchAll();
  $prices=$db->query("SELECT 'group' AS kind,group_id AS owner_id,product_id,price_cents FROM group_prices UNION ALL SELECT 'customer',customer_id,product_id,price_cents FROM customer_prices ORDER BY kind,owner_id,product_id")->fetchAll();
  $catalog=$db->query('SELECT id,sku,name,image FROM products ORDER BY name COLLATE NOCASE')->fetchAll();
  $base=$db->query('SELECT product_id,price_cents FROM base_prices ORDER BY product_id')->fetchAll();
  $discounts=$db->query('SELECT group_id,percent_bp FROM group_discounts')->fetchAll();
  $promotions=$db->query('SELECT id,name,scope,group_id,percent_bp,starts_at,ends_at,active FROM promotions ORDER BY active DESC,ends_at DESC,id DESC')->fetchAll();
  respond(['customers'=>array_map('customerPublic',$customers),'groups'=>$groups,'prices'=>$prices,'products'=>$catalog,'base'=>$base,'discounts'=>$discounts,'promotions'=>$promotions]);
 }
 if($method!=='POST')respond(['error'=>'Método no permitido'],405);csrf();
 if($action==='base_save'){
  $product=(int)($_POST['product_id']??0);$raw=trim((string)($_POST['price']??''));$st=$db->prepare('SELECT 1 FROM products WHERE id=?');$st->execute([$product]);if(!$st->fetchColumn())respond(['error'=>'Producto inexistente'],422);
  if(isset($_POST['expected_price_cents'])){$st=$db->prepare('SELECT price_cents FROM base_prices WHERE product_id=?');$st->execute([$product]);$actual=$st->fetchColumn();$expected=(string)$_POST['expected_price_cents'];if(($actual===false?'':(string)$actual)!==$expected)respond(['error'=>'Otro administrador ha modificado este precio. Actualiza la página antes de guardar.'],409);}
  if($raw===''){$db->prepare('DELETE FROM base_prices WHERE product_id=?')->execute([$product]);respond(['ok'=>true]);}
  $cents=priceCents($raw);if($cents===null)respond(['error'=>'Precio inválido'],422);
  $db->prepare('INSERT INTO base_prices(product_id,price_cents) VALUES(?,?) ON CONFLICT(product_id) DO UPDATE SET price_cents=excluded.price_cents')->execute([$product,$cents]);respond(['ok'=>true]);
 }
 if($action==='discount_save'){
  $group=(int)($_POST['group_id']??0);$raw=trim((string)($_POST['percent']??''));$st=$db->prepare('SELECT 1 FROM customer_groups WHERE id=?');$st->execute([$group]);if(!$st->fetchColumn())respond(['error'=>'Grupo inexistente'],422);
  if(!preg_match('/^(?:100(?:[.,]0{1,2})?|\d{1,2}(?:[.,]\d{1,2})?)$/',$raw))respond(['error'=>'El descuento debe estar entre 0 y 100 %'],422);
  if(isset($_POST['expected_percent_bp'])){$st=$db->prepare('SELECT percent_bp FROM group_discounts WHERE group_id=?');$st->execute([$group]);$actual=$st->fetchColumn();if(($actual===false?'':(string)$actual)!==(string)$_POST['expected_percent_bp'])respond(['error'=>'Otro administrador ha modificado este descuento. Actualiza la página antes de guardar.'],409);}
  $bp=priceCents($raw);$db->prepare('INSERT INTO group_discounts(group_id,percent_bp) VALUES(?,?) ON CONFLICT(group_id) DO UPDATE SET percent_bp=excluded.percent_bp')->execute([$group,$bp]);respond(['ok'=>true]);
 }
 if($action==='promotion_save'){
  $id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));$scope=(string)($_POST['scope']??'all');$group=$scope==='group'?(int)($_POST['group_id']??0):null;
  $start=(string)($_POST['starts_at']??'');$end=(string)($_POST['ends_at']??'');$raw=trim((string)($_POST['percent']??''));$active=($_POST['active']??'1')==='1'?1:0;
  if(mb_strlen($name)<3||mb_strlen($name)>100||!in_array($scope,['all','group'],true)||($scope==='group'&&$group<1)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end)||!checkdate((int)substr($start,5,2),(int)substr($start,8,2),(int)substr($start,0,4))||!checkdate((int)substr($end,5,2),(int)substr($end,8,2),(int)substr($end,0,4))||$end<$start||!preg_match('/^(?:100(?:[.,]0{1,2})?|\d{1,2}(?:[.,]\d{1,2})?)$/',$raw)||priceCents($raw)<1)respond(['error'=>'Revisa nombre, fechas y descuento de la promoción'],422);
  if($tax!==''){$dup=$db->prepare('SELECT company FROM customers WHERE LOWER(TRIM(tax_id))=LOWER(?) AND id<>? LIMIT 1');$dup->execute([$tax,$id]);$duplicate=$dup->fetchColumn();if($duplicate!==false)respond(['error'=>'Este CIF/NIF ya pertenece al cliente «'.$duplicate.'». Abre su ficha para editarlo.'],409);}
  if($group){$st=$db->prepare('SELECT 1 FROM customer_groups WHERE id=?');$st->execute([$group]);if(!$st->fetchColumn())respond(['error'=>'Grupo inexistente'],422);}
  $bp=priceCents($raw);
  if($id){$st=$db->prepare('UPDATE promotions SET name=?,scope=?,group_id=?,percent_bp=?,starts_at=?,ends_at=?,active=? WHERE id=?');$st->execute([$name,$scope,$group,$bp,$start,$end,$active,$id]);if(!$st->rowCount()){$st=$db->prepare('SELECT 1 FROM promotions WHERE id=?');$st->execute([$id]);if(!$st->fetchColumn())respond(['error'=>'Promoción inexistente'],404);}}
  else $db->prepare('INSERT INTO promotions(name,scope,group_id,percent_bp,starts_at,ends_at,active) VALUES(?,?,?,?,?,?,?)')->execute([$name,$scope,$group,$bp,$start,$end,$active]);respond(['ok'=>true]);
 }
 if($action==='promotion_delete'){$db->prepare('DELETE FROM promotions WHERE id=?')->execute([(int)($_POST['id']??0)]);respond(['ok'=>true]);}
 if($action==='group_save'){
  $id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));$active=($_POST['active']??'1')==='1'?1:0;
  if(mb_strlen($name)<2||mb_strlen($name)>90)respond(['error'=>'Nombre de grupo inválido.'],422);
  $dup=$db->prepare('SELECT name FROM customer_groups WHERE LOWER(TRIM(name))=LOWER(?) AND id<>? LIMIT 1');$dup->execute([$name,$id]);$existingGroup=$dup->fetchColumn();if($existingGroup!==false)respond(['error'=>'Ya existe el grupo «'.$existingGroup.'». Abre ese grupo para editarlo.'],409);
  if($id){$st=$db->prepare('UPDATE customer_groups SET name=?,active=? WHERE id=?');$st->execute([$name,$active,$id]);if(!$st->rowCount())respond(['error'=>'Grupo inexistente o sin cambios.'],404);}
  else{$db->prepare('INSERT INTO customer_groups(name,active) VALUES(?,?)')->execute([$name,$active]);}
  respond(['ok'=>true]);
 }
 if($action==='group_delete'){$st=$db->prepare('DELETE FROM customer_groups WHERE id=?');$st->execute([(int)($_POST['id']??0)]);respond(['ok'=>true]);}
 if($action==='customer_save'){
  $id=(int)($_POST['id']??0);$company=trim((string)($_POST['company']??''));$tax=trim((string)($_POST['tax_id']??''));$contact=trim((string)($_POST['contact']??''));$email=mb_strtolower(trim((string)($_POST['email']??'')),'UTF-8');$pass=(string)($_POST['password']??'');$active=($_POST['active']??'1')==='1'?1:0;$group=(int)($_POST['group_id']??0);$group=$group?:null;
  if(mb_strlen($company)<2||mb_strlen($company)>160||mb_strlen($tax)>40||mb_strlen($contact)>100||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>190)respond(['error'=>'Revisa los datos de empresa y correo.'],422);
  if($group){$st=$db->prepare('SELECT 1 FROM customer_groups WHERE id=?');$st->execute([$group]);if(!$st->fetchColumn())respond(['error'=>'Grupo no válido.'],422);}
  if($pass!==''||!$id)validatePassword($pass);
  if($id){$st=$db->prepare('SELECT id FROM customers WHERE id=?');$st->execute([$id]);if(!$st->fetch())respond(['error'=>'Cliente inexistente.'],404);$sql='UPDATE customers SET company=?,tax_id=?,contact=?,email=?,group_id=?,active=?,updated_at=CURRENT_TIMESTAMP'.($pass!==''?',password_hash=?':'').' WHERE id=?';$args=[$company,$tax,$contact,$email,$group,$active];if($pass!=='')$args[]=password_hash($pass,PASSWORD_DEFAULT);$args[]=$id;$db->prepare($sql)->execute($args);}
  else{$db->prepare('INSERT INTO customers(company,tax_id,contact,email,group_id,active,password_hash) VALUES(?,?,?,?,?,?,?)')->execute([$company,$tax,$contact,$email,$group,$active,password_hash($pass,PASSWORD_DEFAULT)]);}
  respond(['ok'=>true]);
 }
 if($action==='customer_delete'){
  $id=(int)($_POST['id']??0);
  $count=$db->prepare('SELECT COUNT(*) FROM uco_orders WHERE customer_id=?');$count->execute([$id]);
  if((int)$count->fetchColumn()>0)respond(['error'=>'Este cliente tiene pedidos históricos. Desactiva la cuenta en lugar de eliminarla.'],409);
  $db->prepare('DELETE FROM customers WHERE id=?')->execute([$id]);respond(['ok'=>true]);
 }
  if($action==='price_update'||$action==='price_save'){
   $kind=(string)($_POST['kind']??'');$owner=(int)($_POST['owner_id']??0);$product=(int)($_POST['product_id']??0);
   $cents=priceCents(trim((string)($_POST['price']??'')));
   if(!in_array($kind,['group','customer'],true)||$owner<1||$product<1||$cents===null)respond(['error'=>'Revisa destinatario, producto y precio.'],422);
   $updating=$action==='price_update';
   $oldKind=(string)($_POST['old_kind']??'');$oldOwner=(int)($_POST['old_owner_id']??0);$oldProduct=(int)($_POST['old_product_id']??0);
   if($updating&&(!in_array($oldKind,['group','customer'],true)||$oldOwner<1||$oldProduct<1))respond(['error'=>'La oferta original no es válida.'],422);
   $table=$kind==='group'?'group_prices':'customer_prices';$col=$kind==='group'?'group_id':'customer_id';
   $oldTable=$oldKind==='group'?'group_prices':'customer_prices';$oldCol=$oldKind==='group'?'group_id':'customer_id';
   try{
    $db->beginTransaction();
    $same=$updating&&$kind===$oldKind&&$owner===$oldOwner&&$product===$oldProduct;
    if($updating){
     $st=$db->prepare("SELECT price_cents FROM $oldTable WHERE $oldCol=? AND product_id=?");$st->execute([$oldOwner,$oldProduct]);$original=$st->fetchColumn();
     if($original===false){$db->rollBack();respond(['error'=>'La oferta original ya no existe. Actualiza la página.'],409);}
     if(isset($_POST['original_price_cents'])&&(int)$original!==(int)$_POST['original_price_cents']){$db->rollBack();respond(['error'=>'La oferta original ha cambiado desde que abriste el formulario. Actualiza la página antes de continuar.'],409);}
    }
    $st=$db->prepare("SELECT price_cents FROM $table WHERE $col=? AND product_id=?");$st->execute([$owner,$product]);$existing=$st->fetchColumn();
    if($existing!==false&&((int)$existing!==$cents||($updating&&!$same))){
     $confirmed=($_POST['confirm_overwrite']??'')==='1'&&isset($_POST['expected_price_cents'])&&(int)$_POST['expected_price_cents']===(int)$existing;
     if(!$confirmed){$db->rollBack();respond(['error'=>'Ya existe una tarifa para este producto y destinatario.','price_conflict'=>true,'old_price_cents'=>(int)$existing,'new_price_cents'=>$cents],409);}
    }
    if($existing===false){$db->prepare("INSERT INTO $table($col,product_id,price_cents) VALUES(?,?,?)")->execute([$owner,$product,$cents]);}
    else if((int)$existing!==$cents){$db->prepare("UPDATE $table SET price_cents=? WHERE $col=? AND product_id=?")->execute([$cents,$owner,$product]);}
    if($updating&&!$same)$db->prepare("DELETE FROM $oldTable WHERE $oldCol=? AND product_id=?")->execute([$oldOwner,$oldProduct]);
    $db->commit();respond(['ok'=>true]);
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  }
 if($action==='price_delete'){
  $kind=(string)($_POST['kind']??'');if(!in_array($kind,['group','customer'],true))respond(['error'=>'Tipo inválido'],422);$table=$kind==='group'?'group_prices':'customer_prices';$col=$kind==='group'?'group_id':'customer_id';$db->prepare("DELETE FROM $table WHERE $col=? AND product_id=?")->execute([(int)($_POST['owner_id']??0),(int)($_POST['product_id']??0)]);respond(['ok'=>true]);
 }
}
if($action==='list' &&$method==='GET'){
 $q=trim((string)($_GET['q']??''));$family=(string)($_GET['family']??'');$active=(string)($_GET['active']??'');$stock=(string)($_GET['stock']??'');
 if(mb_strlen($q)>100)respond(['error'=>'Búsqueda demasiado larga'],400);
 $where=[];$args=[];
 if($q!==''){$where[]="p.search_text LIKE :q ESCAPE '!'";$args[':q']='%'.str_replace(['!','%','_'],['!!','!%','!_'],norm($q)).'%';}
 if($family!==''){$where[]='c.slug=:family';$args[':family']=$family;}
 if(in_array($active,['0','1'],true)){$where[]='p.active=:active';$args[':active']=(int)$active;}
 if($stock==='yes')$where[]='p.stock>0';if($stock==='no')$where[]='p.stock=0';
 $filter=$where?' WHERE '.implode(' AND ',$where):'';
 $sql=' FROM products p JOIN categories c ON c.id=p.category_id'.$filter;
 $stmt=$db->prepare('SELECT p.id,p.sku,p.name,p.image,p.active,p.stock,p.notes,p.description,p.unit,p.created_at,p.updated_at,c.slug AS category,c.name AS family'.$sql.' ORDER BY p.created_at DESC,p.id DESC LIMIT 2000');$stmt->execute($args);
 $categories=$db->query('SELECT id,slug,name FROM categories WHERE active=1 ORDER BY sort_order,id')->fetchAll();
 $totals=$db->query('SELECT COUNT(*) AS total,COALESCE(SUM(active),0) AS active,COALESCE(SUM(CASE WHEN active=0 THEN 1 ELSE 0 END),0) AS inactive FROM products')->fetch();
 respond(['products'=>$stmt->fetchAll(),'categories'=>$categories,'totals'=>$totals]);
}
if($action==='save'&&$method==='POST'){
 csrf();$id=(int)($_POST['id']??0);$sku=trim((string)($_POST['sku']??''));$name=trim((string)($_POST['name']??''));$cat=(int)($_POST['category_id']??0);$active=(string)($_POST['active']??'1')==='1'?1:0;$stock=filter_var($_POST['stock']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100000000]]);$notes=trim((string)($_POST['notes']??''));$description=trim((string)($_POST['description']??''));$unit=(string)($_POST['unit']??'unidad');
 if(mb_strlen($description)>800||!in_array($unit,['unidad','kg','caja','saco','bandeja','litro','otro'],true))respond(['error'=>'Revisa el formato y la descripción comercial.'],422);
 if($sku===''||mb_strlen($sku)>60||$name===''||mb_strlen($name)>160||$stock===false||mb_strlen($notes)>350||substr_count($notes,"\n")>2)respond(['error'=>'Revisa código, nombre, stock y observaciones (máximo 3 líneas y 350 caracteres).'],422);
 if(!$db->prepare('SELECT 1 FROM categories WHERE id=? AND active=1')->execute([$cat]))respond(['error'=>'Familia inválida'],422);
 $check=$db->prepare('SELECT id FROM categories WHERE id=? AND active=1');$check->execute([$cat]);if(!$check->fetch())respond(['error'=>'Selecciona una familia válida'],422);
 $photo=$_FILES['photo']??null;$image=null;
 if($photo&&$photo['error']!==UPLOAD_ERR_NO_FILE){
  if($photo['error']!==UPLOAD_ERR_OK||$photo['size']>12*1024*1024)respond(['error'=>'Imagen inválida o superior a 12 MB'],422);
  if(!extension_loaded('gd'))respond(['error'=>'El servidor necesita la extensión GD para optimizar imágenes'],503);
  $info=@getimagesize($photo['tmp_name']);if(!$info||$info[0]>10000||$info[1]>10000||$info[0]*$info[1]>40000000||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true))respond(['error'=>'Usa JPG, PNG o WebP válidos'],422);
  $src=match($info[2]){IMAGETYPE_JPEG=>@imagecreatefromjpeg($photo['tmp_name']),IMAGETYPE_PNG=>@imagecreatefrompng($photo['tmp_name']),IMAGETYPE_WEBP=>@imagecreatefromwebp($photo['tmp_name'])};if(!$src)respond(['error'=>'No se pudo leer la imagen'],422);
  $dst=imagecreatetruecolor(960,720);$ratio=4/3;$sw=$info[0];$sh=$info[1];if($sw/$sh>$ratio){$sw=(int)round($sh*$ratio);$sx=(int)(($info[0]-$sw)/2);$sy=0;}else{$sh=(int)round($sw/$ratio);$sx=0;$sy=(int)(($info[1]-$sh)/2);}imagecopyresampled($dst,$src,0,0,$sx,$sy,960,720,$sw,$sh);imagedestroy($src);
  $dir=dirname(__DIR__).'/img/productos';if(!is_dir($dir)&&!mkdir($dir,0755,true))respond(['error'=>'No se pudo crear la carpeta de imágenes'],503);
  $filename=bin2hex(random_bytes(16)).'.webp';if(!imagewebp($dst,$dir.'/'.$filename,88)){imagedestroy($dst);respond(['error'=>'Error al guardar la fotografía'],503);}imagedestroy($dst);$image='./img/productos/'.$filename;
 }
 $search=norm($sku.' '.$name);
 try{
  if($id>0){if(isset($_POST['expected_updated_at'])){$st=$db->prepare('SELECT updated_at FROM products WHERE id=?');$st->execute([$id]);$stamp=$st->fetchColumn();if($stamp!==false&&(string)$stamp!==(string)$_POST['expected_updated_at'])respond(['error'=>'Otro administrador ha modificado este producto. Actualiza el catálogo antes de guardar.'],409);} $old=$db->prepare('SELECT image FROM products WHERE id=?');$old->execute([$id]);$prev=$old->fetch();if(!$prev)respond(['error'=>'Producto inexistente'],404);$stmt=$db->prepare('UPDATE products SET sku=?,name=?,category_id=?,active=?,stock=?,notes=?,description=?,unit=?,search_text=?,image=COALESCE(?,image),updated_at=CURRENT_TIMESTAMP WHERE id=?');$stmt->execute([$sku,$name,$cat,$active,$stock,$notes,$description,$unit,$search,$image,$id]);}
  else{$stmt=$db->prepare("INSERT INTO products(sku,name,category_id,active,stock,notes,description,unit,search_text,image,is_demo) VALUES(?,?,?,?,?,?,?,?,?,?,0)");$stmt->execute([$sku,$name,$cat,$active,$stock,$notes,$description,$unit,$search,$image]);$id=(int)$db->lastInsertId();}
 }catch(PDOException $e){if($image)@unlink(dirname(__DIR__).'/'.substr($image,2));if(str_contains($e->getMessage(),'UNIQUE'))respond(['error'=>'Ese código de producto ya existe'],409);throw $e;}
 respond(['ok'=>true,'id'=>$id]);
}
respond(['error'=>'Operación no permitida'],405);
}catch(Throwable $e){error_log('UCO admin: '.$e->getMessage());respond(['error'=>'No se pudo completar la operación'],500);}
