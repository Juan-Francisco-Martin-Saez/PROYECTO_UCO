#!/usr/bin/env python3
"""7.2E.9: HTTP smoke test against a local PHP server; never writes to catalog.
Requires PHP CLI with pdo_sqlite and mbstring; executes against an extracted copy.
"""
import json, pathlib, sqlite3, subprocess, urllib.request, urllib.parse, time, socket, sys
root=pathlib.Path(__file__).resolve().parents[1]
modules=subprocess.run(['php','-m'],capture_output=True,text=True)
if modules.returncode or not {'pdo_sqlite','mbstring'} <= {s.lower() for s in modules.stdout.splitlines()}:
 print('SKIP: PHP CLI requires pdo_sqlite and mbstring; no HTTP test executed')
 sys.exit(2)
db=sqlite3.connect(f"file:{root/'private/database/catalogo.sqlite'}?mode=ro",uri=True)
ref=db.execute("SELECT sku FROM products WHERE active=1 AND sku GLOB '[0-9]*' AND length(sku) BETWEEN 6 AND 14 LIMIT 1").fetchone()
s=socket.socket();s.bind(('127.0.0.1',0));port=s.getsockname()[1];s.close()
proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(root/'public')],stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
try:
 def call(query):
  url=f'http://127.0.0.1:{port}/api/assistant-catalog-71a.php?'+urllib.parse.urlencode({'q':query})
  with urllib.request.urlopen(url,timeout=6) as response:return response.status,json.load(response)
 for _ in range(30):
  try: status,data=call('producto inexistente xyznotfoundzz');break
  except Exception:time.sleep(.1)
 else:raise RuntimeError('PHP server did not become ready')
 assert status==200 and data.get('ok') is True and data.get('items')==[],'No-results query failed'
 assert data.get('match_status')=='none' and data.get('has_more') is False
 print('PASS: HTTP no-results and metadata')
 status,data=call('bolsas de 1 kg')
 assert status==200 and data.get('ok') is True and isinstance(data.get('items'),list),'Packaging-only query failed'
 print('PASS: HTTP packaging-only query')
 if ref:
  status,data=call(ref[0]);assert status==200 and data.get('ok') is True
  assert any(x.get('id') for x in data['items']),'Reference lookup returned no rows'
  print('PASS: HTTP exact reference')
 else:print('SKIP: no numeric reference available')
 status,data=call('quiero patatas por favor')
 assert status==200 and data.get('ok') is True and isinstance(data.get('items'),list)
 print('PASS: HTTP natural-language query')
 print('HTTP smoke tests passed')
finally:
 proc.terminate()
 try:proc.wait(timeout=3)
 except subprocess.TimeoutExpired:proc.kill()
