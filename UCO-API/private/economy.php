<?php
declare(strict_types=1);
function ucoEconomySchema(PDO $db):void{
 $db->exec("CREATE TABLE IF NOT EXISTS uco_economy_settings(key TEXT PRIMARY KEY,value TEXT NOT NULL)");
 $db->exec("CREATE TABLE IF NOT EXISTS uco_product_taxes(product_id INTEGER PRIMARY KEY REFERENCES products(id) ON DELETE CASCADE,rate_bp INTEGER NOT NULL CHECK(rate_bp BETWEEN 0 AND 10000))");
}
function ucoEconomyQuote(PDO $db,array $items,?array $delivery=null):array{
 ucoEconomySchema($db);
 $settings=$db->query('SELECT key,value FROM uco_economy_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
 $taxStmt=$db->prepare('SELECT rate_bp FROM uco_product_taxes WHERE product_id=?');
 $subtotal=0;$tax=0;$issues=[];$details=[];
 foreach($items as $item){
  $pid=(int)($item['product_id']??0);$quantity=(int)($item['quantity']??0);
  $line=isset($item['line_cents'])?(int)$item['line_cents']:null;
  if($pid<1||$quantity<1||$line===null||$line<0){$issues[]='Hay productos sin precio válido';continue;}
  $taxStmt->execute([$pid]);$bp=$taxStmt->fetchColumn();
  if($bp===false){$issues[]='Falta configurar el IVA de '.(string)($item['name']??('producto '.$pid));continue;}
  $subtotal+=$line;
  // Half-up per line; tax on discounted selling price, not catalogue base price.
  $lineTax=intdiv($line*(int)$bp+5000,10000);$tax+=$lineTax;
  $details[]=['product_id'=>$pid,'quantity'=>$quantity,'tax_rate_bp'=>(int)$bp,'tax_cents'=>$lineTax,'line_cents'=>$line];
 }
 $shipping=null;$free=null;
 if(!isset($settings['shipping_cents'])||!preg_match('/^\d+$/',$settings['shipping_cents']))$issues[]='Falta configurar el transporte';
 else $shipping=(int)$settings['shipping_cents'];
 if(isset($settings['free_shipping_from_cents'])&&preg_match('/^\d+$/',$settings['free_shipping_from_cents']))$free=(int)$settings['free_shipping_from_cents'];
 elseif(!isset($settings['free_shipping_from_cents']))$issues[]='Falta configurar el umbral de envío gratuito';
 if($delivery===null||trim((string)($delivery['postal_code']??''))===''||trim((string)($delivery['province']??''))==='')$issues[]='Falta indicar la dirección de entrega';
 // Fixed rate is not valid for regions where carrier costs may differ. Explicitly configure permitted destinations.
 $allowed=trim((string)($settings['allowed_postal_prefixes']??''));
 if($allowed==='')$issues[]='Falta configurar las zonas de entrega';
 elseif($delivery!==null){$pc=preg_replace('/\s+/','',(string)($delivery['postal_code']??''));$ok=false;foreach(explode(',',$allowed) as $prefix){$prefix=trim($prefix);if($prefix!==''&&str_starts_with($pc,$prefix))$ok=true;}if(!$ok)$issues[]='No hay tarifa de transporte para este código postal';}
 if($shipping!==null&&$free!==null&&$free>0&&$subtotal>=$free)$shipping=0;
 $ready=!$issues;
 return ['subtotal_cents'=>$subtotal,'tax_cents'=>$ready?$tax:null,'shipping_cents'=>$ready?$shipping:null,'total_cents'=>$ready?$subtotal+$tax+$shipping:null,'tax_lines'=>$details,'ready'=>$ready,'issues'=>array_values(array_unique($issues)),'notice'=>$ready?'Total calculado con las tarifas configuradas. Se volverá a verificar antes del cobro.':'Configura los datos indicados antes de obtener el importe definitivo.'];
}
