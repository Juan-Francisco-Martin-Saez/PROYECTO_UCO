<?php
declare(strict_types=1);
function commercialDb(PDO $db):void{
 $db->exec("CREATE TABLE IF NOT EXISTS customer_groups(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL COLLATE NOCASE UNIQUE,active INTEGER NOT NULL DEFAULT 1)");
 $db->exec("CREATE TABLE IF NOT EXISTS customers(id INTEGER PRIMARY KEY AUTOINCREMENT,company TEXT NOT NULL,tax_id TEXT NOT NULL DEFAULT '',contact TEXT NOT NULL DEFAULT '',email TEXT NOT NULL COLLATE NOCASE UNIQUE,password_hash TEXT NOT NULL,group_id INTEGER REFERENCES customer_groups(id) ON DELETE SET NULL,active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
 $db->exec("CREATE TABLE IF NOT EXISTS group_prices(group_id INTEGER NOT NULL REFERENCES customer_groups(id) ON DELETE CASCADE,product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,price_cents INTEGER NOT NULL CHECK(price_cents>=0),PRIMARY KEY(group_id,product_id))");
 $db->exec("CREATE TABLE IF NOT EXISTS base_prices(product_id INTEGER PRIMARY KEY REFERENCES products(id) ON DELETE CASCADE,price_cents INTEGER NOT NULL CHECK(price_cents>=0))");
 $db->exec("CREATE TABLE IF NOT EXISTS group_discounts(group_id INTEGER PRIMARY KEY REFERENCES customer_groups(id) ON DELETE CASCADE,percent_bp INTEGER NOT NULL DEFAULT 0 CHECK(percent_bp BETWEEN 0 AND 10000))");
 $db->exec("CREATE TABLE IF NOT EXISTS promotions(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,scope TEXT NOT NULL CHECK(scope IN ('all','group')),group_id INTEGER REFERENCES customer_groups(id) ON DELETE CASCADE,percent_bp INTEGER NOT NULL CHECK(percent_bp BETWEEN 1 AND 10000),starts_at TEXT NOT NULL,ends_at TEXT NOT NULL,active INTEGER NOT NULL DEFAULT 1, CHECK(ends_at>=starts_at))");
 $db->exec("CREATE TABLE IF NOT EXISTS customer_prices(customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,price_cents INTEGER NOT NULL CHECK(price_cents>=0),PRIMARY KEY(customer_id,product_id))");
 $cols=array_column($db->query('PRAGMA table_info(customers)')->fetchAll(PDO::FETCH_ASSOC),'name');
 if(!in_array('username',$cols,true))$db->exec("ALTER TABLE customers ADD COLUMN username TEXT COLLATE NOCASE");
 if(!in_array('email_verified',$cols,true))$db->exec("ALTER TABLE customers ADD COLUMN email_verified INTEGER NOT NULL DEFAULT 0");
 if(!in_array('verification_token_hash',$cols,true))$db->exec("ALTER TABLE customers ADD COLUMN verification_token_hash TEXT");
 if(!in_array('verification_expires',$cols,true))$db->exec("ALTER TABLE customers ADD COLUMN verification_expires TEXT");
 $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_customers_username ON customers(username) WHERE username IS NOT NULL');
 $db->exec("CREATE INDEX IF NOT EXISTS idx_customers_group ON customers(group_id)");
 // Producción: nunca crear cuentas de prueba durante una solicitud.


}
function customerPublic(array $c):array{return ['id'=>(int)$c['id'],'company'=>$c['company'],'tax_id'=>$c['tax_id'],'contact'=>$c['contact'],'email'=>$c['email'],'username'=>$c['username']??null,'email_verified'=>(int)($c['email_verified']??0),'group_id'=>$c['group_id']===null?null:(int)$c['group_id'],'active'=>(int)$c['active']];}
function priceCents(string $raw):?int{if(!preg_match('/^\d{1,8}(?:[.,]\d{1,2})?$/',$raw))return null;$v=explode('.',str_replace(',','.',$raw));return (int)$v[0]*100+(int)str_pad($v[1]??'',2,'0');}


/* Prioridad: precio personal > promoción (sin acumular descuentos) > precio manual de grupo > descuento del grupo > precio general. */
function commercialPrice(PDO $db,int $productId,int $customerId,?int $groupId):array{
 $st=$db->prepare('SELECT price_cents FROM customer_prices WHERE customer_id=? AND product_id=?');$st->execute([$customerId,$productId]);
 $v=$st->fetchColumn();if($v!==false)return ['price_cents'=>(int)$v,'price_source'=>'personal'];
 $base=$db->prepare('SELECT price_cents FROM base_prices WHERE product_id=?');$base->execute([$productId]);$v=$base->fetchColumn();$cents=$v===false?null:(int)$v;$source=$cents===null?null:'general';$undiscounted=$cents;
 if($groupId){
  $st=$db->prepare('SELECT gp.price_cents FROM group_prices gp JOIN customer_groups cg ON cg.id=gp.group_id AND cg.active=1 WHERE gp.group_id=? AND gp.product_id=?');$st->execute([$groupId,$productId]);$v=$st->fetchColumn();
  if($v!==false){$cents=(int)$v;$undiscounted=$cents;$source='grupo';}
  elseif($cents!==null){$st=$db->prepare('SELECT gd.percent_bp FROM group_discounts gd JOIN customer_groups cg ON cg.id=gd.group_id AND cg.active=1 WHERE gd.group_id=?');$st->execute([$groupId]);$v=$st->fetchColumn();if($v!==false&&$v>0){$cents=(int)round($cents*(10000-(int)$v)/10000);$source='descuento';}}
 }
 if($cents!==null){
  $st=$db->prepare("SELECT percent_bp FROM promotions WHERE active=1 AND starts_at<=date('now') AND ends_at>=date('now') AND (scope='all' OR (scope='group' AND group_id=? AND EXISTS(SELECT 1 FROM customer_groups cg WHERE cg.id=promotions.group_id AND cg.active=1))) ORDER BY percent_bp DESC,id DESC LIMIT 1");$st->execute([$groupId?:0]);$v=$st->fetchColumn();
  if($v!==false){$promo=(int)round($undiscounted*(10000-(int)$v)/10000);if($promo<$cents){$cents=$promo;$source='promocion';}}
 }
 return ['price_cents'=>$cents,'price_source'=>$source];
}
