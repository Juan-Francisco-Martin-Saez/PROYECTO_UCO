#!/usr/bin/env python3
"""7.2E.11: evaluación reproducible de precisión SQL, solo lectura.
No emula ni certifica la normalización PHP, el enrutamiento, ni respuestas HTTP.
"""
import json, sqlite3, pathlib, re, unicodedata, collections, sys
root=pathlib.Path(__file__).resolve().parents[1]
db=sqlite3.connect(f"file:{root/'private/database/catalogo.sqlite'}?mode=ro",uri=True)
db.row_factory=sqlite3.Row
rows=db.execute('''SELECT p.id,p.sku,p.name,p.description,p.search_text,c.name category,
 f.peso_total_kg,f.envases,f.peso_envase_kg,f.tipo_envase,f.estado
 FROM products p LEFT JOIN categories c ON c.id=p.category_id
 LEFT JOIN uco_presentaciones f ON f.product_id=p.id
 WHERE p.active=1 AND (c.id IS NULL OR c.active=1)''').fetchall()
def norm(v):
 s=unicodedata.normalize('NFKD',str(v or '').lower())
 return ''.join(c for c in s if not unicodedata.combining(c))
def tokens(s):return [x for x in re.findall(r'[a-z0-9]+',norm(s)) if len(x)>=3]
def matches(query):
 q=tokens(query)
 return [r for r in rows if all(any(w in norm(r[k]) for k in ('name','description','search_text','category')) for w in q)]
def sample(r):return {'sku':r['sku'],'name':r['name'],'id':r['id']}
cases=[]
def check(name,query,expected,mode):
 found=matches(query)
 if mode=='contains':ok=any(norm(expected) in norm(r['name']) for r in found)
 elif mode=='exclude':ok=all(norm(expected) not in norm(r['name']) for r in found)
 elif mode=='none':ok=not found
 else:raise ValueError(mode)
 cases.append({'name':name,'query':query,'expectation':mode+':'+expected,'passed':ok,'count':len(found),'examples':[sample(r) for r in found[:5]]})
# These checks assess the catalog's lexical discriminability, NOT the PHP endpoint.
check('Aros de cebolla: sin sustitución por cebolla simple','aros cebolla','aros','contains')
check('Consulta inexistente','zzzinexistentezz','', 'none')
check('Zanahoria: coincidencias','zanahoria','zanahoria','contains')
check('Patatas: coincidencias','patatas','patat','contains')
# Verify actual format dimensions by direct SQL, without guessing weights.
format_checks=[]
def fmt(name,where,params=()):
 found=db.execute('''SELECT p.id,p.sku,p.name,f.formato_original FROM products p JOIN uco_presentaciones f ON f.product_id=p.id WHERE p.active=1 AND ('''+where+''') LIMIT 5''',params).fetchall()
 format_checks.append({'name':name,'matches':len(found),'examples':[dict(x) for x in found]})
fmt('Peso total 1 kg','f.peso_total_kg BETWEEN ? AND ?',(.99999,1.00001))
fmt('Presentaciones múltiples desglosadas',"f.estado='desglosado' AND f.envases>1 AND f.peso_envase_kg>0")
fmt('Peso total 6 kg','f.peso_total_kg BETWEEN ? AND ?',(5.99999,6.00001))
report={'phase':'7.2E.11','scope':'catalog SQL and lexical diagnostic only','http_tested':False,'php_executed':False,'catalog_active_count':len(rows),'cases':cases,'passed':sum(c['passed'] for c in cases),'total':len(cases),'format_checks':format_checks,'limitations':['Las coincidencias léxicas son una aproximación Python, no la lógica PHP exacta','Los ejemplos positivos no demuestran que el primer resultado sea correcto','No se ha ejecutado el endpoint HTTP ni se ha alterado la base de datos']}
print(json.dumps(report,ensure_ascii=False,indent=2))
# A failed precision expectation is a finding, not a runner error.
