<?php
declare(strict_types=1);
/** UCO 1D: auditoría SOLO LECTURA de identidades y relaciones. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI'); }
$path=dirname(__DIR__).'/database/catalogo.sqlite';
if (!is_file($path)) { fwrite(STDERR,"ERROR: No existe catalogo.sqlite\n");exit(2); }
try {
 $db=new PDO('sqlite:file:'.str_replace('?', '%3F',str_replace('#','%23',str_replace('%','%25',str_replace('\\','/',$path)))).'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $issues=[];$warn=[];
 $tables=['admin_users','customers','identity_accounts','identity_customer_links'];
 foreach($tables as $t){$s=$db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=?");$s->execute([$t]);if(!$s->fetchColumn())$issues[]="Falta tabla: $t";}
 if($issues)throw new RuntimeException(implode('; ',$issues));
 $count=fn(string $sql):int=>(int)$db->query($sql)->fetchColumn();
 $stats=['Empleados'=>$count('SELECT COUNT(*) FROM admin_users'),'Clientes'=>$count('SELECT COUNT(*) FROM customers'),'Identidades'=>$count('SELECT COUNT(*) FROM identity_accounts'),'Vínculos'=>$count('SELECT COUNT(*) FROM identity_customer_links')];
 foreach($stats as $k=>$v)echo "$k: $v\n";
 $checks=[
 'Identidades internas huérfanas'=>"SELECT COUNT(*) FROM identity_accounts a LEFT JOIN admin_users u ON a.legacy_id=u.id WHERE a.kind='staff' AND u.id IS NULL",
 'Identidades comerciales huérfanas'=>"SELECT COUNT(*) FROM identity_accounts a LEFT JOIN customers c ON a.legacy_id=c.id WHERE a.kind='customer' AND c.id IS NULL",
 'Empleados sin identidad'=>"SELECT COUNT(*) FROM admin_users u LEFT JOIN identity_accounts a ON a.kind='staff' AND a.legacy_id=u.id WHERE a.id IS NULL",
 'Clientes sin identidad'=>"SELECT COUNT(*) FROM customers c LEFT JOIN identity_accounts a ON a.kind='customer' AND a.legacy_id=c.id WHERE a.id IS NULL",
 'Clientes sin vínculo propio'=>"SELECT COUNT(*) FROM customers c WHERE NOT EXISTS (SELECT 1 FROM identity_accounts a JOIN identity_customer_links l ON l.identity_id=a.id WHERE a.kind='customer' AND a.legacy_id=c.id AND l.customer_id=c.id)",
 'Vínculos a clientes inexistentes'=>"SELECT COUNT(*) FROM identity_customer_links l LEFT JOIN customers c ON c.id=l.customer_id WHERE c.id IS NULL",
 'Vínculos a identidades inexistentes'=>"SELECT COUNT(*) FROM identity_customer_links l LEFT JOIN identity_accounts a ON a.id=l.identity_id WHERE a.id IS NULL",
 'Vínculos comerciales cruzados'=>"SELECT COUNT(*) FROM identity_customer_links l JOIN identity_accounts a ON a.id=l.identity_id WHERE a.kind='customer' AND a.legacy_id<>l.customer_id",
 'Empleados vinculados a más de un cliente'=>"SELECT COUNT(*) FROM (SELECT a.id FROM identity_accounts a JOIN identity_customer_links l ON l.identity_id=a.id WHERE a.kind='staff' GROUP BY a.id HAVING COUNT(*)>1)",
 'Clientes vinculados a varios empleados'=>"SELECT COUNT(*) FROM (SELECT l.customer_id FROM identity_customer_links l JOIN identity_accounts a ON a.id=l.identity_id WHERE a.kind='staff' GROUP BY l.customer_id HAVING COUNT(*)>1)",
 'Administradores activos'=>"SELECT COUNT(*) FROM admin_users WHERE role='admin' AND active=1"
 ];
 foreach($checks as $label=>$sql){$n=$count($sql);echo "$label: $n\n";if($label==='Administradores activos'){if($n<1)$issues[]='No hay administrador activo';}elseif($n>0)$issues[]="$label: $n";}
 $fk=$db->query('PRAGMA foreign_key_check')->fetchAll();if($fk)$issues[]='Relaciones SQLite inválidas: '.count($fk);
 $integrity=$db->query('PRAGMA integrity_check')->fetchColumn();if($integrity!=='ok')$issues[]='Integridad SQLite: '.$integrity;
 echo 'PRAGMA integrity_check: '.($integrity==='ok'?'OK':'ERROR')."\n";
 echo 'PRAGMA foreign_key_check: '.(!$fk?'OK':'ERROR')."\n";
 if($issues){foreach($issues as $v)fwrite(STDERR,"ERROR: $v\n");exit(1);}
 echo "OK: auditoría de identidades superada. No se ha modificado ningún dato.\n";
} catch(Throwable $e){fwrite(STDERR,'ERROR: '.$e->getMessage()."\n");exit(2);}
