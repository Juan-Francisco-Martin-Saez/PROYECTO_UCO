from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
php=(root/'public/api/assistant-openai.php').read_text()
js=(root/'public/assistant/assistant.js').read_text()
conf=(root/'private/config/openai-protected.php').read_text()
checks={
'prompt fijado a versión 2': "'prompt_version'=>'2'" in conf,
'prompt externo sin alteración': "'prompt'=>['id'=>$conf['prompt_id'],'version'=>$conf['prompt_version']]" in php,
'entrada limitada a pregunta': "'input'=>$q" in php,
'sin almacenamiento en OpenAI': "'store'=>false" in php,
'búsqueda documental separada': "'file_search'" in php,
'contrato JSON response': "isset($parsed['response'])" in php,
'respuesta debe ser texto': "is_string($parsed['response'])" in php,
'error 502 ante formato incompatible': "Formato del prompt incompatible" in php,
'no aceptar importes de OpenAI': "Respuesta pendiente de validación comercial" in php,
'respuesta no verificada comercialmente': "'commercial_verified'=>false" in php,
'endpoint independiente': "'/api/assistant-openai.php'" in js,
'entrada explícita IA disponible': r'^ia\s*:' in js,
'enrutamiento automático con filtro': 'shouldUseGeneralEngine72e22' in js,
'fallo de OpenAI sin respuesta inventada': 'No puedo consultar OpenAI ahora' in js,
}
for name,ok in checks.items():print(('OK' if ok else 'FALLO')+' - '+name)
print(f'{sum(checks.values())}/{len(checks)} comprobaciones estáticas')
assert all(checks.values())
