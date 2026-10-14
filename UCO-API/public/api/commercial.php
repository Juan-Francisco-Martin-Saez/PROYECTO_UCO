<?php
declare(strict_types=1);
function commercialDb(PDO $db):void{
 $db->exec("CREATE TABLE IF NOT EXISTS customer_groups(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL COLLATE NOCASE UNIQUE,active INTEGER NOT NULL DEFAULT 1)");
 $db->exec("CREATE TABLE IF NOT EXISTS customers(id INTEGER PRIMARY KEY AUTOINCREMENT,company TEXT NOT NULL,tax_id TEXT NOT NULL DEFAULT '',contact TEXT NOT NULL DEFAULT '',email TEXT NOT NULL COLLATE NOCASE UNIQUE,password_hash TEXT NOT NULL,group_id INTEGER REFERENCES customer_groups(id) ON DELETE SET NULL,active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
 $db->exec("CREATE TABLE IF NOT EXISTS group_prices(group_id INTEGER NOT NULL REFERENCES customer_groups(id) ON DELETE CASCADE,product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,price_cents INTEGER NOT NULL CHECK(price_cents>=0),PRIMARY KEY(group_id,product_id))");
 $db->exec("CREATE TABLE IF NOT EXISTS customer_prices(customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,price_cents INTEGER NOT NULL CHECK(price_cents>=0),PRIMARY KEY(customer_id,product_id))");
 $cols=array_column($db->query('PRAGMA table_info(customers)')->fetchAll(PDO::FETCH_ASSOC),'name');
 if(!in_array('username',$cols,true))$db->exec("ALTER TABLE customers ADD COLUMN username TEXT COLLATE NOCASE");
 if(!in_array('email_verified',$cols,true))$db->exec("ALTER TABLE customers ADD COLUMN email_verified INTEGER NOT NULL DEFAULT 0");
 if(!in_array('verification_token_hash',$cols,true))$db->exec("ALTER TABLE customers ADD COLUMN verification_token_hash TEXT");
 if(!in_array('verification_expires',$cols,true))$db->exec("ALTER TABLE customers ADD COLUMN verification_expires TEXT");
 $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_customers_username ON customers(username) WHERE username IS NOT NULL');
 $db->exec("CREATE INDEX IF NOT EXISTS idx_customers_group ON customers(group_id)");
 // Cuenta inicial de prueba: se crea solo si no existe. Contraseña almacenada exclusivamente como hash bcrypt.
 $seed=$db->prepare('SELECT 1 FROM customers WHERE username=? COLLATE NOCASE LIMIT 1');$seed->execute(['Pepe']);
 if(!$seed->fetchColumn()){
  $seed=$db->prepare('SELECT 1 FROM customers WHERE email=? COLLATE NOCASE LIMIT 1');$seed->execute(['pepe@pendiente.invalid']);
  if(!$seed->fetchColumn())$db->prepare('INSERT INTO customers(company,contact,email,password_hash,username,email_verified,active) VALUES(?,?,?,?,?,1,1)')->execute(['Pepe','Pepe','pepe@pendiente.invalid','$2y$12$BBFsNke7nI6V5e65awES/OuwjcQdGpCXlLcb9B4IY/s3/1qg.A6L2','Pepe']);
 }

}
function customerPublic(array $c):array{return ['id'=>(int)$c['id'],'company'=>$c['company'],'tax_id'=>$c['tax_id'],'contact'=>$c['contact'],'email'=>$c['email'],'username'=>$c['username']??null,'email_verified'=>(int)($c['email_verified']??0),'group_id'=>$c['group_id']===null?null:(int)$c['group_id'],'active'=>(int)$c['active']];}
function priceCents(string $raw):?int{if(!preg_match('/^\d{1,8}(?:[.,]\d{1,2})?$/',$raw))return null;$v=explode('.',str_replace(',','.',$raw));return (int)$v[0]*100+(int)str_pad($v[1]??'',2,'0');}
