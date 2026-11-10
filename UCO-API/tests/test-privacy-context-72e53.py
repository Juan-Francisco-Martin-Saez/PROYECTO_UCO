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
cases=[('exacta wav', '¿Qué es el formato WAV en audio?', True), ('exacta api', '¿Qué es una API key?', True), ('exacta token', '¿Qué es un token en programación?', True), ('exacta precio', '¿Qué es el precio de equilibrio?', True), ('exacta cuenta', '¿Qué es una cuenta matemática?', True), ('exacta catalogo', '¿Qué es el catálogo de Messier en astronomía?', True), ('extra pedido', '¿Qué es una API key? Mi pedido 42', False), ('extra precio', '¿Qué es el formato PDF? precio de UCO', False), ('extra tarjeta', '¿Qué es el producto escalar? Mi tarjeta', False), ('extra email', '¿Qué es una API key? test@example.com', False), ('extra movil', '¿Qué es el formato WAV en audio? 612345678', False), ('extra dni', '¿Qué es un token en programación? 12345678Z', False), ('extra iban', '¿Qué es el precio de equilibrio? ES9121000418450200051332', False), ('extra sk', '¿Qué es una API key? sk-abcdefghijklmnop12345', False), ('extra github', '¿Qué es una API key? ghp_abcdefghijklmnop', False), ('extra letra', '¿Qué es una API key? p e d i d o', False), ('extra zero width', '¿Qué es el formato PDF? p\u200bedido', False), ('extra salto', '¿Qué es el formato PDF?\nmi factura', False), ('extra unicode', '¿Qué es el formato PDF? ｐｒｅｃｉｏ', False), ('variante puntuacion', '¿Qué es una API key?!', True), ('variante coma', '¿Qué es una API key,?', True), ('variante comillas', '"¿Qué es una API key?"', True), ('variante salto', '¿Qué es una API key?\n', True), ('variante unicode invisible', '¿Qué es una API\u200b key?', True), ('variante acento', '¿Qué es un tóken en programación?', True), ('variacion con comercial', '¿Qué es una API key? pedido', False), ('variacion pregunta uco', '¿Qué es el precio de equilibrio de UCO?', False), ('variacion producto', '¿Qué es el producto escalar del pedido?', False)]
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
(root/'tests/RESULTADOS_PRIVACIDAD_72E53.json').write_text(json.dumps(output,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'passed':output['passed'],'total':output['total'],'mismatches':[x['case'] for x in results if not x['passed']]},ensure_ascii=False))
