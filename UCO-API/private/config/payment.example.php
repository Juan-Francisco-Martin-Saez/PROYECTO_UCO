<?php
// Copiar como payment.php en esta misma carpeta y completar únicamente en el servidor.
// Por seguridad los pagos permanecen desactivados hasta validación final.
return [
 'enabled'=>false,
 'base_url'=>'https://uco.reijohan.com',
 'redsys'=>['enabled'=>false,'sandbox'=>true,'merchant_code'=>'','terminal'=>'1','secret_key'=>''],
 'paypal'=>['enabled'=>false,'sandbox'=>true,'client_id'=>'','client_secret'=>'','webhook_id'=>''],
 'transfer'=>['enabled'=>false,'iban'=>'','beneficiary'=>'','concept_prefix'=>'UCO PEDIDO'],
];
