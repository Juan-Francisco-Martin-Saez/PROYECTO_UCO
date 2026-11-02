<?php
declare(strict_types=1);
require_once __DIR__.'/payment-gateways.php';
function ucoPaymentDb():PDO {
 $db=new PDO('sqlite:'.__DIR__.'/database/catalogo.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA foreign_keys=ON');$db->exec('PRAGMA busy_timeout=10000');ucoPaymentSchema($db);return $db;
}
/** Never confirm a payment from a browser redirect. Caller must verify with provider. */
function ucoPaymentSettle(PDO $db,string $reference,string $providerId,string $eventId):string {
 $db->exec('BEGIN IMMEDIATE');
 try{
  $q=$db->prepare('SELECT * FROM uco_real_payments WHERE reference=?');$q->execute([$reference]);$p=$q->fetch();
  if(!$p){$db->rollBack();return 'unknown';}
  if($p['provider_id']!==null && !hash_equals((string)$p['provider_id'],$providerId)){$db->rollBack();return 'mismatch';}
  if($p['state']==='paid'){$db->commit();return 'already_paid';}
  if($p['state']!=='pending'){$db->rollBack();return 'review';}
  $q=$db->prepare('SELECT status,expires_at FROM uco_orders WHERE id=?');$q->execute([(int)$p['order_id']]);$order=$q->fetch();
  $status=$order['status']??null;
  // Un pago verificado tras la expiración necesita conciliación, no preparación automática.
  if($status==='pending_payment' && (int)($order['expires_at']??0)<=time()){
   $db->prepare("UPDATE uco_real_payments SET state='review',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int)$p['id']]);
   $db->commit();return 'expired_review';
  }
  if($status!=='pending_payment'){$db->prepare("UPDATE uco_real_payments SET state='review',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int)$p['id']]);$db->commit();return 'review';}
  $q=$db->prepare('SELECT l.product_id,l.quantity,p.stock FROM uco_order_lines l JOIN products p ON p.id=l.product_id WHERE l.order_id=?');$q->execute([(int)$p['order_id']]);$lines=$q->fetchAll();
  if(!$lines){$db->rollBack();return 'review';}
  // El stock físico incluye reservas aún no cobradas. Al liquidar este pedido,
  // conservar unidades suficientes para TODAS las demás reservas vigentes.
  $reservedOthers=$db->prepare("SELECT COALESCE(SUM(l.quantity),0)
    FROM uco_order_lines l JOIN uco_orders o ON o.id=l.order_id
    WHERE l.product_id=? AND o.id<>? AND o.status IN ('reserved','pending_payment')
    AND o.expires_at>?");
  foreach($lines as $line){
   $reservedOthers->execute([(int)$line['product_id'],(int)$p['order_id'],time()]);
   $otherQty=(int)$reservedOthers->fetchColumn();
   if((int)$line['stock']-(int)$line['quantity']<$otherQty){
    $db->prepare("UPDATE uco_real_payments SET state='review',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int)$p['id']]);
    $db->commit();return 'insufficient_unreserved_stock_review';
   }
  }
  $q=$db->prepare("UPDATE uco_orders SET status='paid',payment_status='confirmed',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending_payment'");$q->execute([(int)$p['order_id']]);if($q->rowCount()!==1)throw new RuntimeException('Pedido no actualizable');
  $q=$db->prepare('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?');foreach($lines as $line){$q->execute([(int)$line['quantity'],(int)$line['product_id'],(int)$line['quantity']]);if($q->rowCount()!==1)throw new RuntimeException('Stock concurrente');}
  $db->prepare("UPDATE uco_real_payments SET state='paid',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int)$p['id']]);
  $db->prepare('DELETE FROM shopping_cart_items WHERE customer_id=?')->execute([(int)$p['customer_id']]);
  $db->prepare('INSERT OR IGNORE INTO uco_payment_events(provider,event_id,reference) VALUES(?,?,?)')->execute([$p['method'],$eventId,$reference]);
  $db->commit();return 'paid';
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
