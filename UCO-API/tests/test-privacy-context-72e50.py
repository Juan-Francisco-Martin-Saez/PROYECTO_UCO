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
cases=[
 ('email','¿Qué es persona@example.com?',False),('telefono','¿Qué es 612345678?',False),('dni','¿Qué es 12345678Z?',False),('nie','¿Qué es X1234567L?',False),('iban','¿Qué es ES9121000418450200051332?',False),('token','¿Qué es sk-proj-abcdefghijklmnop?',False),('github token','¿Qué es ghp_abcdefghijklmnop?',False),('factura','¿Qué es mi factura?',False),('precio','¿Qué precio tiene el producto?',False),('pedido','¿Qué es mi pedido?',False),('contrasena','¿Qué es mi contraseña?',False),('referencia','¿Qué significa SKU?',False),('api key','¿Qué es API key?',False),('cuenta','¿Qué es mi cuenta?',False),('domicilio','¿Qué es mi domicilio?',False),('catalogo','¿Qué es el catálogo?',False),('descuento','¿Qué es un descuento?',False),('iban separado','¿Qué es ES91 2100 0418 4502 0005 1332?',False),('dni separado','¿Qué es 1234 5678 Z?',False),('telefono separado','¿Qué es 612 345 678?',False),('unicode invisible','¿Qué es mi pe\u200bdido?',False),('palabra fragmentada','¿Qué es p e d i d o?',False),('general api','¿Qué es una API?',True),('general ciencia','Define biodiversidad',True),('general historia','¿Quién fue Cervantes?',True),('general internet','¿Cómo funciona internet?',True),('general clima','¿Por qué llueve?',True),
]
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
  # El 503 indica paso de privacidad, no disponibilidad real del servicio.
  php_allowed=status==503
  assert status in (422,503),(name,status)
  assert j['allowed']==expected,(name,'JS',j['allowed'],expected)
  assert php_allowed==expected,(name,'PHP',status,expected)
  results.append({'case':name,'js':j['allowed'],'php_http':status,'passed':True})
finally:
 proc.terminate()
 try:proc.wait(timeout=3)
 except subprocess.TimeoutExpired:proc.kill()
# Guardia efectiva: ninguna llamada fetch a OpenAI fuera de askProtectedOpenAI.
source=js.read_text();assert source.count("fetch('/api/assistant-openai.php'")==1
assert source.index('if(!isSafeGeneralQuestion72e39(question))return null;')<source.index("fetch('/api/assistant-openai.php'")
assert php.read_text().index('foreach($privatePatterns')<php.read_text().index('ucoOpenAIRateAllow72e27')
output={'passed':len(results)+3,'total':len(results)+3,'cases':results,'static_guards':3,'limitations':'No prueba ataques de codificación deliberada, configuración de IONOS ni llamadas reales a OpenAI.'}
(root/'tests/RESULTADOS_PRIVACIDAD_72E50.json').write_text(json.dumps(output,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'passed':output['passed'],'total':output['total']},ensure_ascii=False))
