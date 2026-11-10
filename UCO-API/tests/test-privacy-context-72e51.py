#!/usr/bin/env python3
"""Auditoría cruzada local: JS real + endpoint PHP real; sin llamadas externas."""
import pathlib,subprocess,json,socket,os,time,urllib.request,urllib.error,contextlib
root=pathlib.Path(__file__).resolve().parents[1]
js=root/'public/assistant/assistant.js';php=root/'public/api/assistant-openai.php'
assert js.is_file() and php.is_file()
# Ejecutar la guardia JS real mediante VM, sin navegador ni red.
node=r"""
const fs=require('fs'),vm=require('vm');
const source=fs.readFileSync(process.argv[1],'utf8');
function extract(name){let s=source.indexOf('function '+name+'(');if(s<0)throw Error(name);let o=source.indexOf('{',s),d=0,q=null,esc=false;for(let i=o;i<source.length;i++){let c=source[i];if(q){if(esc){esc=false;continue}if(c==='\\'){esc=true;continue}if(c===q)q=null;continue}if(c==='"'||c==="'"||c==='`'){q=c;continue}if(c==='{')d++;if(c==='}'&&!--d)return source.slice(s,i+1)}throw Error('unclosed')}
const c={normalizeUco:s=>s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^\p{L}\p{N}\s]/gu,' ').replace(/\s+/g,' ').trim()};vm.createContext(c);vm.runInContext(extract('isSafeGeneralQuestion72e39'),c);
const cases=JSON.parse(fs.readFileSync(0,'utf8'));process.stdout.write(JSON.stringify(cases.map(x=>({name:x.name,allowed:c.isSafeGeneralQuestion72e39(x.message)}))));
"""
cases=[('wav exacto', '¿Qué es el formato WAV en audio?', True), ('messier exacto', '¿Qué es el catálogo de Messier en astronomía?', True), ('wav variación', 'Explica el formato WAV en audio', True), ('messier variación', 'Define el catálogo de Messier', True), ('formato pdf', '¿Qué es el formato PDF?', True), ('formato json', '¿Qué es el formato JSON?', True), ('formato mp3', '¿Qué es el formato MP3?', True), ('catalogo estelar', '¿Qué es un catálogo estelar?', True), ('catalogo astronomia', 'Explica el catálogo astronómico', True), ('referencia bibliografica', '¿Qué es una referencia bibliográfica?', True), ('precio economico', '¿Qué es el precio de equilibrio?', True), ('oferta demanda', 'Explica la ley de la oferta y la demanda', True), ('producto matematico', '¿Qué es el producto escalar?', True), ('stock fotografia', '¿Qué es la fotografía de stock?', True), ('cuenta matematica', '¿Qué es una cuenta matemática?', True), ('cliente servidor', '¿Qué es el modelo cliente servidor?', True), ('token programacion', '¿Qué es un token en programación?', True), ('api key tecnica', '¿Qué es una API key?', True), ('formato comercial', '¿Qué formato tiene el producto UCO?', False), ('catalogo comercial', 'Muéstrame el catálogo de UCO', False), ('pedido comercial', '¿Dónde está mi pedido?', False), ('precio comercial', '¿Qué precio tiene el arroz?', False), ('factura comercial', '¿Dónde está mi factura?', False), ('tarjeta comercial', '¿Cuál es mi tarjeta?', False), ('telefono real', 'Mi teléfono es 612345678', False), ('email real', 'Mi email es test@example.com', False), ('dni real', 'Mi DNI es 12345678Z', False), ('iban real', 'Mi IBAN es ES9121000418450200051332', False), ('token real', 'Mi token es ghp_abcdefghijklmnop', False), ('wav con pedido', '¿Qué es el formato WAV en audio? Mi pedido 42', False), ('messier con cuenta', '¿Qué es el catálogo de Messier en astronomía? Mi cuenta', False), ('api general', '¿Qué es una API?', True), ('historia general', '¿Quién fue Cervantes?', True), ('astronomia general', '¿Qué es una nebulosa?', True), ('programacion general', '¿Qué es una variable?', True)]
payload=[{'name':n,'message':q} for n,q,_ in cases]
jsresult=json.loads(subprocess.check_output(['node','-e',node,str(js)],input=json.dumps(payload).encode(),timeout=8))
with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
env=os.environ.copy();env.pop('OPENAI_API_KEY',None)
proc=subprocess.Popen(['php','-d',f'auto_prepend_file={root}/tests/php-mbstring-shim-72e41.php','-S',f'127.0.0.1:{port}','-t',str(root/'public')],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
results=[]
try:
 for _ in range(80):
  try:urllib.request.urlopen(f'http://127.0.0.1:{port}/',timeout=.3);break
  except Exception:time.sleep(.05)
 for (name,q,expected),j in zip(cases,jsresult):
  req=urllib.request.Request(f'http://127.0.0.1:{port}/api/assistant-openai.php',data=json.dumps({'message':q}).encode(),headers={'Content-Type':'application/json'},method='POST')
  try:
   with urllib.request.urlopen(req,timeout=3) as r:status=r.status
  except urllib.error.HTTPError as e:status=e.code
  # Limpiar solo el limitador del entorno de prueba local, sin tocar producción.
  for f in (root/'private/logs/openai-rate-72e27').glob('*'): f.unlink()
  # El 503 indica paso de privacidad, no disponibilidad real del servicio.
  php_allowed=status==503
  assert status in (422,503),(name,status)
  results.append({'case':name,'expected':expected,'js':j['allowed'],'php_http':status,'passed':j['allowed']==expected and php_allowed==expected,'js_php_agree':j['allowed']==php_allowed})
finally:
 proc.terminate()
 try:proc.wait(timeout=3)
 except subprocess.TimeoutExpired:proc.kill()
# Guardia efectiva: ninguna llamada fetch a OpenAI fuera de askProtectedOpenAI.
source=js.read_text();assert source.count("fetch('/api/assistant-openai.php'")==1
assert source.index('if(!isSafeGeneralQuestion72e39(question))return null;')<source.index("fetch('/api/assistant-openai.php'")
assert php.read_text().index('foreach($privatePatterns')<php.read_text().index('ucoOpenAIRateAllow72e27')
output={'passed':sum(x['passed'] for x in results)+3,'total':len(results)+3,'cases':results,'static_guards':3,'limitations':'Las expectativas educativas son criterios de auditoría, no garantías del enrutamiento; sin IONOS ni OpenAI.'}
(root/'tests/RESULTADOS_PRIVACIDAD_72E51.json').write_text(json.dumps(output,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'passed':output['passed'],'total':output['total'],'mismatches':[x['case'] for x in results if not x['passed']]},ensure_ascii=False))
