#!/usr/bin/env python3
"""Auditoría integral 7.2E.19: ejecuta pruebas existentes y documenta limitaciones.
No modifica bases de datos; HTTP se ejecuta solo si PHP tiene dependencias.
"""
from pathlib import Path
import subprocess,hashlib,json,sys,datetime,sqlite3
root=Path(__file__).resolve().parents[1]
dbs=[root/'private/database/catalogo.sqlite',root/'private/assistant/dictionary.sqlite']
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest()
before={str(p.relative_to(root)):digest(p) for p in dbs}
cases=['test-catalog-real-72e7.py','test-catalog-diagnostics-72e10.py','test-catalog-precision-72e11.py','test-relevancia-72e13.py','test-assistant-catalog-primary-72e14.py','test-assistant-compound-72e15.py','test-search-ambiguity-72e16.py','test-qualifiers-72e17.py','test-ranking-72e18.py','test-assistant-http-72e9.py']
results=[]
for name in cases:
 p=root/'tests'/name
 if not p.exists():results.append({'test':name,'status':'MISSING'});continue
 try:
  r=subprocess.run([sys.executable,str(p)],cwd=root,capture_output=True,text=True,timeout=35)
  status='PASS' if r.returncode==0 else 'SKIP' if r.returncode==2 and 'SKIP' in r.stdout else 'FAIL'
  results.append({'test':name,'status':status,'exit_code':r.returncode,'stdout':r.stdout[-1800:],'stderr':r.stderr[-1400:]})
 except Exception as e:results.append({'test':name,'status':'ERROR','detail':str(e)})
php=subprocess.run(['php','-l',str(root/'public/api/assistant-catalog-71a.php')],capture_output=True,text=True) if __import__('shutil').which('php') else None
syntax={'status':'PASS' if php and php.returncode==0 else 'FAIL' if php else 'SKIP','output':(php.stdout+php.stderr).strip() if php else 'php unavailable'}
after={str(p.relative_to(root)):digest(p) for p in dbs}
report={'phase':'7.2E.19','scope':'static and SQLite regression; HTTP only if dependencies available','tests':results,'php_syntax':syntax,'databases_unchanged':before==after,'sha256_databases':after,'summary':{x:sum(r['status']==x for r in results) for x in ['PASS','FAIL','SKIP','ERROR','MISSING']}}
(root/'INFORME_VALIDACION_72E19.json').write_text(json.dumps(report,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
print(json.dumps({'summary':report['summary'],'php_syntax':syntax['status'],'databases_unchanged':before==after},ensure_ascii=False))
if report['summary']['FAIL'] or report['summary']['ERROR'] or report['summary']['MISSING'] or syntax['status']=='FAIL' or before!=after:sys.exit(1)
