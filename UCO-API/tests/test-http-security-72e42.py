#!/usr/bin/env python3
"""Local-only HTTP tests; no API credentials or remote calls."""
import contextlib,json,os,pathlib,socket,subprocess,time,urllib.request,urllib.error
root=pathlib.Path(__file__).resolve().parents[1]
with socket.socket() as s:
 s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
cmd=['php','-d',f'auto_prepend_file={root}/tests/php-mbstring-shim-72e41.php','-S',f'127.0.0.1:{port}','-t',str(root/'public')]
env=os.environ.copy();env.pop('OPENAI_API_KEY',None)
proc=subprocess.Popen(cmd,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,env=env)
results=[]
def check(name,method='POST',body=b'{}',ctype='application/json',expect=422):
 headers={'Content-Type':ctype}
 req=urllib.request.Request(f'http://127.0.0.1:{port}/api/assistant-openai.php',data=body if method!='GET' else None,headers=headers,method=method)
 try:
  with urllib.request.urlopen(req,timeout=3) as resp:status=resp.status;raw=resp.read()
 except urllib.error.HTTPError as ex:status=ex.code;raw=ex.read()
 parsed=json.loads(raw)
 assert status==expect,(name,status,expect,parsed)
 assert isinstance(parsed,dict) and parsed.get('ok') is False,(name,parsed)
 results.append({'name':name,'status':status})
try:
 for i in range(40):
  try:
   with urllib.request.urlopen(f'http://127.0.0.1:{port}/',timeout=.2):break
  except Exception:time.sleep(.05)
 check('GET rechazado',method='GET',expect=405)
 check('tipo text/plain',body=b'{}',ctype='text/plain',expect=415)
 check('tipo application/json falso',body=b'{}',ctype='text/application/json',expect=415)
 check('JSON malformado',body=b'{broken',expect=422)
 check('array JSON',body=b'[]',expect=422)
 check('mensaje objeto',body=b'{"message":{}}',expect=422)
 check('mensaje corto',body=b'{"message":"a"}',expect=422)
 check('mensaje largo',body=json.dumps({'message':'x'*301}).encode(),expect=422)
 check('cuerpo excesivo',body=b' '*12001,expect=413)
 check('correo privado',body=json.dumps({'message':'Mi correo es persona@example.com'}).encode(),expect=422)
 check('consulta comercial',body=json.dumps({'message':'¿Qué precio tiene mi pedido?'}).encode(),expect=422)
 check('identificador DNI',body=json.dumps({'message':'El DNI es 12345678Z'}).encode(),expect=422)
 check('identificador IBAN',body=json.dumps({'message':'ES9121000418450200051332'}).encode(),expect=422)
 # These requests reach the limiter; missing key/curl means HTTP 503 for first ten, 429 afterwards.
 for i in range(10):check(f'general admitida {i+1}',body=b'{"message":"Define biodiversidad"}',expect=503)
 check('limite 11a peticion',body=b'{"message":"Define biodiversidad"}',expect=429)
 check('privacidad antes del limite',body=b'{"message":"Mi factura 123"}',expect=422)
finally:
 proc.terminate()
 try:proc.wait(timeout=3)
 except subprocess.TimeoutExpired:proc.kill()
print(json.dumps({'passed':len(results),'total':len(results),'cases':results},ensure_ascii=False,indent=2))
