<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');session_name('UCOADMIN');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>'/']);session_start();
function respond($v,int $code=200):never{http_response_code($code);echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function norm(string $s):string{$s=mb_strtolower($s,'UTF-8');if(class_exists('Normalizer'))$s=Normalizer::normalize($s,Normalizer::FORM_D);return strtr($s,['á'=>'a','à'=>'a','ä'=>'a','â'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u']);}
function db():PDO{$db=new PDO('sqlite:'.dirname(__DIR__,2).'/private/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=5000');return $db;}
function authorized():bool{return (int)($_SESSION['uco_user_id']??0)>0;}
function usersDb():PDO{$db=db();$db->exec("CREATE TABLE IF NOT EXISTS admin_users(id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL COLLATE NOCASE UNIQUE, display_name TEXT NOT NULL, email TEXT NOT NULL DEFAULT '', password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'manager' CHECK(role IN ('admin','manager')), active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");$columns=$db->query('PRAGMA table_info(admin_users)')->fetchAll();if(!in_array('avatar',array_column($columns,'name'),true))$db->exec("ALTER TABLE admin_users ADD COLUMN avatar TEXT NOT NULL DEFAULT ''");return $db;}
function adminOnly():void{if(($_SESSION['uco_role']??'')!=='admin')respond(['error'=>'Solo el administrador principal puede gestionar usuarios.'],403);}
function userInfo(array $u):array{return ['id'=>(int)$u['id'],'username'=>$u['username'],'display_name'=>$u['display_name'],'email'=>$u['email'],'avatar'=>$u['avatar']??'','role'=>$u['role'],'active'=>(int)$u['active'],'created_at'=>$u['created_at']];}
function validatePassword(string $p):void{if(strlen($p)<12||strlen($p)>256)respond(['error'=>'La contraseña debe tener entre 12 y 256 caracteres.'],422);}
function activeAdmins(PDO $db):int{return (int)$db->query("SELECT COUNT(*) FROM admin_users WHERE role='admin' AND active=1")->fetchColumn();}
function csrf():void{if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))respond(['error'=>'Sesión no válida. Actualiza la página.'],403);}
$action=(string)($_GET['action']??'session');$method=$_SERVER['REQUEST_METHOD'];
try{
if($action==='session'&&$method==='GET')respond(['authenticated'=>authorized(),'csrf'=>authorized()?($_SESSION['csrf']??''):null,'user'=>authorized()?($_SESSION['uco_user']??null):null]);
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
 session_regenerate_id(true);$_SESSION['uco_user_id']=(int)$u['id'];$_SESSION['uco_role']=$u['role'];$_SESSION['uco_user']=userInfo($u);$_SESSION['uco_auth_stamp']=hash('sha256',$u['password_hash']);$_SESSION['csrf']=bin2hex(random_bytes(32));respond(['authenticated'=>true,'csrf'=>$_SESSION['csrf'],'user'=>$_SESSION['uco_user']]);
}
if(!authorized())respond(['error'=>'Acceso no autorizado'],401);
if($action==='logout'&&$method==='POST'){csrf();$_SESSION=[];session_destroy();respond(['ok'=>true]);}
$db=usersDb();
// Si una cuenta se desactiva o cambia de rol, las sesiones anteriores dejan de autorizarse.
$check=$db->prepare('SELECT * FROM admin_users WHERE id=? AND active=1');$check->execute([$_SESSION['uco_user_id']]);$current=$check->fetch();
if(!$current||!hash_equals((string)($_SESSION['uco_auth_stamp']??''),hash('sha256',$current['password_hash']))){$_SESSION=[];session_destroy();respond(['error'=>'La sesión ya no es válida. Vuelve a entrar.'],401);}
$_SESSION['uco_role']=$current['role'];$_SESSION['uco_user']=userInfo($current);
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
 $stmt=$db->prepare('SELECT p.id,p.sku,p.name,p.image,p.active,p.stock,p.notes,p.created_at,p.updated_at,c.slug AS category,c.name AS family'.$sql.' ORDER BY p.created_at DESC,p.id DESC LIMIT 2000');$stmt->execute($args);
 $categories=$db->query('SELECT id,slug,name FROM categories WHERE active=1 ORDER BY sort_order,id')->fetchAll();
 $totals=$db->query('SELECT COUNT(*) AS total,COALESCE(SUM(active),0) AS active,COALESCE(SUM(CASE WHEN active=0 THEN 1 ELSE 0 END),0) AS inactive FROM products')->fetch();
 respond(['products'=>$stmt->fetchAll(),'categories'=>$categories,'totals'=>$totals]);
}
if($action==='save'&&$method==='POST'){
 csrf();$id=(int)($_POST['id']??0);$sku=trim((string)($_POST['sku']??''));$name=trim((string)($_POST['name']??''));$cat=(int)($_POST['category_id']??0);$active=(string)($_POST['active']??'1')==='1'?1:0;$stock=filter_var($_POST['stock']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100000000]]);$notes=trim((string)($_POST['notes']??''));
 if($sku===''||mb_strlen($sku)>60||$name===''||mb_strlen($name)>160||$stock===false||mb_strlen($notes)>350||substr_count($notes,"\n")>2)respond(['error'=>'Revisa código, nombre, stock y observaciones (máximo 3 líneas y 350 caracteres).'],422);
 if(!$db->prepare('SELECT 1 FROM categories WHERE id=? AND active=1')->execute([$cat]))respond(['error'=>'Familia inválida'],422);
 $check=$db->prepare('SELECT id FROM categories WHERE id=? AND active=1');$check->execute([$cat]);if(!$check->fetch())respond(['error'=>'Selecciona una familia válida'],422);
 $photo=$_FILES['photo']??null;$image=null;
 if($photo&&$photo['error']!==UPLOAD_ERR_NO_FILE){
  if($photo['error']!==UPLOAD_ERR_OK||$photo['size']>12*1024*1024)respond(['error'=>'Imagen inválida o superior a 12 MB'],422);
  if(!extension_loaded('gd'))respond(['error'=>'El servidor necesita la extensión GD para optimizar imágenes'],503);
  $info=@getimagesize($photo['tmp_name']);if(!$info||$info[0]>10000||$info[1]>10000||$info[0]*$info[1]>40000000||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true))respond(['error'=>'Usa JPG, PNG o WebP válidos'],422);
  $src=match($info[2]){IMAGETYPE_JPEG=>@imagecreatefromjpeg($photo['tmp_name']),IMAGETYPE_PNG=>@imagecreatefrompng($photo['tmp_name']),IMAGETYPE_WEBP=>@imagecreatefromwebp($photo['tmp_name'])};if(!$src)respond(['error'=>'No se pudo leer la imagen'],422);
  $dst=imagecreatetruecolor(960,720);$bg=imagecolorallocate($dst,248,249,246);imagefill($dst,0,0,$bg);$scale=min(960/$info[0],720/$info[1],1);$w=max(1,(int)round($info[0]*$scale));$h=max(1,(int)round($info[1]*$scale));imagecopyresampled($dst,$src,(int)((960-$w)/2),(int)((720-$h)/2),0,0,$w,$h,$info[0],$info[1]);imagedestroy($src);
  $dir=dirname(__DIR__).'/img/productos';if(!is_dir($dir)&&!mkdir($dir,0755,true))respond(['error'=>'No se pudo crear la carpeta de imágenes'],503);
  $filename=bin2hex(random_bytes(16)).'.webp';if(!imagewebp($dst,$dir.'/'.$filename,88)){imagedestroy($dst);respond(['error'=>'Error al guardar la fotografía'],503);}imagedestroy($dst);$image='./img/productos/'.$filename;
 }
 $search=norm($sku.' '.$name);
 try{
  if($id>0){$old=$db->prepare('SELECT image FROM products WHERE id=?');$old->execute([$id]);$prev=$old->fetch();if(!$prev)respond(['error'=>'Producto inexistente'],404);$stmt=$db->prepare('UPDATE products SET sku=?,name=?,category_id=?,active=?,stock=?,notes=?,search_text=?,image=COALESCE(?,image),updated_at=CURRENT_TIMESTAMP WHERE id=?');$stmt->execute([$sku,$name,$cat,$active,$stock,$notes,$search,$image,$id]);}
  else{$stmt=$db->prepare("INSERT INTO products(sku,name,category_id,active,stock,notes,search_text,image,is_demo) VALUES(?,?,?,?,?,?,?,?,0)");$stmt->execute([$sku,$name,$cat,$active,$stock,$notes,$search,$image]);$id=(int)$db->lastInsertId();}
 }catch(PDOException $e){if($image)@unlink(dirname(__DIR__).'/'.substr($image,2));if(str_contains($e->getMessage(),'UNIQUE'))respond(['error'=>'Ese código de producto ya existe'],409);throw $e;}
 respond(['ok'=>true,'id'=>$id]);
}
respond(['error'=>'Operación no permitida'],405);
}catch(Throwable $e){error_log('UCO admin: '.$e->getMessage());respond(['error'=>'No se pudo completar la operación'],500);}
