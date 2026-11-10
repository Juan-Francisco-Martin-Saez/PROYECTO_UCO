<?php
declare(strict_types=1);
/** Propuesta aislada: no se invoca desde la web; ninguna operación de red o escritura. */
function ucoQueryRoute72e21(string $question): array {
    $text=trim(function_exists('mb_strtolower') ? mb_strtolower($question,'UTF-8') : strtolower($question));
    $text=strtr($text,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u']);
    if ($text==='') return ['route'=>'clarify','reason'=>'empty'];
    // Los documentos propios y pedidos nunca se remiten al motor general.
    if (preg_match('/\b(mi|mis|nuestro|nuestros)\b/u',$text) && preg_match('/\b(pedido|pedidos|factura|facturas|recibo|justificante)\b/u',$text)) return ['route'=>'authenticated','reason'=>'private_resource'];
    if (preg_match('/\b(pedido|pedidos|carrito|comprar|pagar|pago|checkout|devolucion|entrega|envio)\b/u',$text)) return ['route'=>'commercial','reason'=>'transaction'];
    if (preg_match('/\b(precio|precios|tarifa|tarifas|referencia|referencias|sku|stock|disponible|catalogo|formato|envase|kg|kilo|kilos|patata|patatas|cebolla|zanahoria|aros|congelado|congelada|producto|productos)\b/u',$text)) return ['route'=>'commercial','reason'=>'catalog'];
    return ['route'=>'general','reason'=>'no_commercial_signal'];
}
