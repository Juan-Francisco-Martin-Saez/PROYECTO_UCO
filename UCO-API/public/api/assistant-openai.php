<?php
declare(strict_types=1);
/** UCO: conexion OpenAI independiente, protegida. Sin acceso a carrito, pagos o escrituras. */
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function outAI(array $x,int $code=200):never {http_response_code($code);echo json_encode($x,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')outAI(['ok'=>false,'error'=>'Método no permitido'],405);
if(!str_contains((string)($_SERVER['CONTENT_TYPE']??''),'application/json'))outAI(['ok'=>false,'error'=>'Formato incorrecto'],415);
if((int)($_SERVER['CONTENT_LENGTH']??0)>12000)outAI(['ok'=>false,'error'=>'Solicitud demasiado grande'],413);
$body=json_decode(file_get_contents('php://input'),true);
$q=is_array($body) ? trim((string)($body['message']??'')) : '';
if(!is_array($body)||mb_strlen($q,'UTF-8')<2||mb_strlen($q,'UTF-8')>300)outAI(['ok'=>false,'error'=>'Consulta inválida'],422);
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
curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);
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
foreach(($result['output']??[]) as $entry)foreach(($entry['content']??[]) as $part)if(($part['type']??'')==='output_text')$text.=(string)($part['text']??'');
$parsed=json_decode($text,true);
if(!is_array($parsed)||!isset($parsed['response'])||!is_string($parsed['response']))outAI(['ok'=>false,'unavailable'=>true,'error'=>'Formato del prompt incompatible; requiere revisión autorizada'],502);
// El texto de OpenAI no se muestra como presupuesto ni precio. Cualquier cifra monetaria
// requiere la API comercial autenticada existente. Rechazar respuestas con importes.
$reply=trim($parsed['response']);
if($reply===''||mb_strlen($reply,'UTF-8')>1800||preg_match('/(?:\d[\d.,]*\s*€|€\s*\d|\d[\d.,]*\s*euros?)/iu',$reply))outAI(['ok'=>false,'unavailable'=>true,'error'=>'Respuesta pendiente de validación comercial'],422);
outAI(['ok'=>true,'response'=>$reply,'status'=>$parsed['status']??'needs_clarification','source'=>'openai_prompt_v2','commercial_verified'=>false]);
