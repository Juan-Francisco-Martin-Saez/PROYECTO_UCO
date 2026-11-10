<?php
declare(strict_types=1);
/** UCO: conexion OpenAI independiente, protegida. Sin acceso a carrito, pagos o escrituras. */
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function outAI(array $x,int $code=200):never {http_response_code($code);echo json_encode($x,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')outAI(['ok'=>false,'error'=>'Método no permitido'],405);
// Rechazar tipos como text/application/json y cabeceras engañosas.
$contentType=strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''),2)[0]));
if($contentType!=='application/json')outAI(['ok'=>false,'error'=>'Formato incorrecto'],415);
if((int)($_SERVER['CONTENT_LENGTH']??0)>12000)outAI(['ok'=>false,'error'=>'Solicitud demasiado grande'],413);
// El tamaño declarado puede faltar o ser falso: limitar también el cuerpo leído.
$rawBody=file_get_contents('php://input',false,null,0,12001);
if(!is_string($rawBody)||strlen($rawBody)>12000)outAI(['ok'=>false,'error'=>'Solicitud demasiado grande'],413);
$body=json_decode($rawBody,true);
$message=is_array($body)?($body['message']??null):null;
$q=is_string($message)?trim($message):'';
if(!is_array($body)||!is_string($message)||mb_strlen($q,'UTF-8')<2||mb_strlen($q,'UTF-8')>300)outAI(['ok'=>false,'error'=>'Consulta inválida'],422);
// 7.2E.39: protección del lado servidor incluso para solicitudes directas y «IA:».
// Política conservadora: no enviar datos identificables ni asuntos comerciales.
// 7.2E.48: NFKC si intl está disponible; nunca depender de esa extensión.
$privacyInput=class_exists('Normalizer') ? (Normalizer::normalize($q, Normalizer::FORM_KC) ?: $q) : $q;
$privacyInput=preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u','',$privacyInput);
$privacyText=mb_strtolower($privacyInput,'UTF-8');
$privacyNormalized=strtr($privacyText,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
$privatePatterns=[
    '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
    '/(?:\+?34[\s.\-]*)?[6789](?:[\s.\-]*\d){8}\b/u',
    '/\b(?:pedido|pedidos|factura|facturas|cliente|clientes|usuario|usuarios|cuenta|direccion|telefono|correo|email|carrito|pago|pagar|comprar|presupuesto|precio|precios|tarifa|tarifas|referencia|sku|stock|disponibilidad|catalogo|formato|envase|kilos|kg|producto|productos|entrega|envio|devolucion|descuento|oferta|ofertas|uco|uc[oó]sapobla)\b/iu',
    '/\b(?:dni|nie|nif|cif|iban|bic|swift|tarjeta|contrase(?:n|ñ)a|password|token|api[_ -]?key|clave secreta|domicilio|direccion postal)\b/iu',
    '/\b(?:mi|mis|nuestro|nuestros)\s+(?:pedido|factura|cuenta|direccion|telefono|correo|tarjeta|compra|cliente|usuario|presupuesto)\b/iu',
    // 7.2E.40: identificadores estructurados aunque no se mencione su tipo.
    '/\b(?:ES[0-9]{22}|[XYZ][0-9]{7}[A-Z]|[0-9]{8}[A-Z])\b/iu',
    '/\b(?:sk-[A-Za-z0-9_-]{12,}|ghp_[A-Za-z0-9]{12,})\b/u',
];
// Identificadores fragmentados con espacios, puntos o guiones.
$privacyCompact=preg_replace('/[\s.\-\x{00A0}]+/u','',$privacyNormalized);
if(preg_match('/(?:ES[0-9]{22}|[XYZ][0-9]{7}[A-Z]|[0-9]{8}[A-Z]|(?:\+?34)?[6789][0-9]{8}|sk[A-Za-z0-9_-]{12,}|ghp_[A-Za-z0-9]{12,})/iu',$privacyCompact)){
    outAI(['ok'=>false,'error'=>'Consulta no apta para el asistente general'],422);
}
// 7.2E.52: consultas educativas exactas, solo después de validar identificadores estructurados.
$educational52=in_array(trim(preg_replace('/^[¿?¡!.,;:"“”«»\s]+|[¿?¡!.,;:"“”«»\s]+$/u','',$privacyNormalized)),[
    'que es el formato wav en audio',
    'que es el catalogo de messier en astronomia',
    'explica el formato wav en audio',
    'define el catalogo de messier',
    'que es el formato pdf',
    'que es el formato json',
    'que es el formato mp3',
    'que es un catalogo estelar',
    'explica el catalogo astronomico',
    'que es una referencia bibliografica',
    'que es el precio de equilibrio',
    'explica la ley de la oferta y la demanda',
    'que es el producto escalar',
    'que es la fotografia de stock',
    'que es una cuenta matematica',
    'que es el modelo cliente servidor',
    'que es un token en programacion',
    'que es una api key',
],true);
if($educational52){
    // No se exime ningún identificador real: comprobaciones anteriores obligatorias.
    goto privacy_verified_72e52;
}
// Términos comerciales deliberadamente separados letra a letra.
$privacySpaced=preg_replace_callback('/\b(?:[a-z]\s+){3,}[a-z]\b/u',static fn($m)=>preg_replace('/\s+/u','',$m[0]),$privacyNormalized);
if(preg_match('/\b(?:pedido|factura|precio|carrito|cliente|usuario|tarjeta|contrasena|password|token|iban)\b/iu',$privacySpaced)){
    outAI(['ok'=>false,'error'=>'Consulta no apta para el asistente general'],422);
}
// 7.2E.50: excepción exacta para dos preguntas educativas, no para variantes con datos.
$educational=in_array(trim(preg_replace('/^[¿?\s]+|[¿?\s]+$/u','',$privacyNormalized)),[
    'que es el formato wav en audio',
    'que es el catalogo de messier en astronomia',
],true);
$privacyTerms=$educational ? preg_replace('/\b(?:formato|catalogo)\b/u','concepto',$privacyNormalized) : $privacyNormalized;
foreach($privatePatterns as $privacyPattern){
    if(preg_match($privacyPattern,$privacyTerms))outAI(['ok'=>false,'error'=>'Consulta no apta para el asistente general'],422);
}
privacy_verified_72e52:
// Limitar antes de realizar cualquier llamada de pago a OpenAI.
require_once dirname(__DIR__,2).'/private/assistant/openai-rate-limit-72e27.php';
$clientIp=(string)($_SERVER['REMOTE_ADDR']??'');
$rateDir=dirname(__DIR__,2).'/private/logs/openai-rate-72e27';
if(!ucoOpenAIRateAllow72e27($rateDir,$clientIp,10,60)){
    header('Retry-After: 60');
    outAI(['ok'=>false,'error'=>'Demasiadas solicitudes; inténtalo más tarde'],429);
}
$conf=require dirname(__DIR__,2).'/private/config/openai-protected.php';
// Priorizar la clave privada local que ya se ha verificado con HTTP 200.
// Si no existe, conservar la variable de entorno para otros despliegues.
$secretFile=dirname(__DIR__,2).'/private/config/openai-local-secret.php';
$key='';
$keySource='archivo_privado';
if(is_file($secretFile)){
    $localKey=require $secretFile;
    if(is_string($localKey))$key=trim($localKey);
}
if($key===''){
    $key=trim((string)(getenv('OPENAI_API_KEY') ?: ''));
    $keySource='entorno';
}
if($key==='' || $key==='PEGA_AQUI_TU_API_KEY')$key='';
if(!$key||!function_exists('curl_init'))outAI(['ok'=>false,'unavailable'=>true,'error'=>'OpenAI no configurado'],503);
// No se envían historiales, cookies, identificadores personales ni tarifas privadas.
// El modelo interpreta lenguaje; NUNCA es fuente autorizada de precios o acciones comerciales.
$payload=['prompt'=>['id'=>$conf['prompt_id'],'version'=>$conf['prompt_version']],
 'input'=>$q,
 'tools'=>[['type'=>'file_search','vector_store_ids'=>['vs_6ae89338df50819182bf5c81768837ab']]],
 'store'=>false,'max_output_tokens'=>700];
$ch=curl_init('https://api.openai.com/v1/responses');
$responseHeaders = []; // La prueba directa HTTP 200 no usa callback de cabeceras.
curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>16]);
$raw = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
$curlNumber = curl_errno($ch);
$info = curl_getinfo($ch);
if ($raw === false || $status < 200 || $status >= 300) {
    $decodedError = is_string($raw) ? json_decode($raw, true) : null;
    $apiError = is_array($decodedError) && isset($decodedError['error']) && is_array($decodedError['error']) ? $decodedError['error'] : [];
    $type = is_string($apiError['type'] ?? null) ? $apiError['type'] : '';
    $code = is_scalar($apiError['code'] ?? null) ? (string)$apiError['code'] : '';
    $param = is_scalar($apiError['param'] ?? null) ? (string)$apiError['param'] : '';
    $message = is_string($apiError['message'] ?? null) ? $apiError['message'] : '';
    if ($message === '' && is_array($decodedError) && is_string($decodedError['message'] ?? null)) $message = $decodedError['message'];
    if ($message === '') $message = $err !== '' ? $err : 'Respuesta sin mensaje reconocible';
    $sanitize = static function (string $value, int $limit = 160): string {
        $value = preg_replace('/sk-(?:proj-)?[A-Za-z0-9_-]+/', '[CLAVE_OCULTA]', $value);
        $value = preg_replace('/[\r\n\t]+/', ' ', (string)$value);
        return mb_substr((string)$value, 0, $limit, 'UTF-8');
    };
    $parts = [
        '[UCO OpenAI] HTTP ' . $status,
        'curl_errno=' . $curlNumber,
        'origen_clave=' . $keySource,
        'prompt_v2=' . (($conf['prompt_version'] ?? '') === '2' ? 'si' : 'no'),
        'tipo=' . $sanitize($type),
        'codigo=' . $sanitize($code),
        'parametro=' . $sanitize($param),
        'mensaje=' . $sanitize($message, 500),
        'cuerpo_json=' . (is_array($decodedError) ? 'si' : 'no'),
        'longitud=' . (is_string($raw) ? strlen($raw) : 0),
        'bytes_enviados=' . (int)($info['size_upload'] ?? 0),
        'tiempo_total=' . round((float)($info['total_time'] ?? 0), 3),
        'ip_remota=' . $sanitize((string)($info['primary_ip'] ?? ''), 60),
    ];
    foreach ($responseHeaders as $name => $value) $parts[] = $name . '=' . $sanitize($value);
    error_log(implode(' | ', $parts));
    outAI(['ok'=>false,'unavailable'=>true,'error'=>'Asistente avanzado temporalmente no disponible'],503);
}
$result=json_decode($raw,true);
if(!is_array($result))outAI(['ok'=>false,'unavailable'=>true,'error'=>'Respuesta inválida'],502);
$text='';
foreach(($result['output']??[]) as $entry){
    if(!is_array($entry)||!is_array($entry['content']??null))continue;
    foreach($entry['content'] as $part){
        if(is_array($part)&&($part['type']??'')==='output_text'&&is_string($part['text']??null))$text.=$part['text'];
    }
}
$parsed=json_decode($text,true);
if(!is_array($parsed)||!isset($parsed['response'])||!is_string($parsed['response']))outAI(['ok'=>false,'unavailable'=>true,'error'=>'Formato del prompt incompatible; requiere revisión autorizada'],502);
// El texto de OpenAI no se muestra como presupuesto ni precio. Cualquier cifra monetaria
// requiere la API comercial autenticada existente. Rechazar respuestas con importes.
$reply=trim($parsed['response']);
if($reply===''||mb_strlen($reply,'UTF-8')>1800||preg_match('/(?:\d[\d.,]*\s*€|€\s*\d|\d[\d.,]*\s*euros?)/iu',$reply))outAI(['ok'=>false,'unavailable'=>true,'error'=>'Respuesta pendiente de validación comercial'],422);
outAI(['ok'=>true,'response'=>$reply,'status'=>$parsed['status']??'needs_clarification','source'=>'openai_prompt_v2','commercial_verified'=>false]);
