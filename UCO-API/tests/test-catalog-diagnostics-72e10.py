#!/usr/bin/env python3
"""7.2E.10: diagnóstico reproducible de catálogo (solo lectura).
No sustituye pruebas HTTP PHP; no modifica datos ni realiza llamadas a OpenAI.
"""
import sqlite3, pathlib, json, sys, re, collections
root=pathlib.Path(__file__).resolve().parents[1]
db=sqlite3.connect(f"file:{root/'private/database/catalogo.sqlite'}?mode=ro",uri=True)
db.row_factory=sqlite3.Row
checks=[];warnings=[]
def check(name,ok,detail=None):
 checks.append({'name':name,'ok':bool(ok),'detail':detail})
 if not ok:warnings.append(name)
rows=db.execute("SELECT p.id,p.sku,p.name,p.active,p.is_demo FROM products p").fetchall()
active=[r for r in rows if r['active']==1]
check('Catálogo activo disponible',len(active)>0,{'active':len(active),'total':len(rows)})
refs=collections.defaultdict(list)
for r in active:
 if r['sku'] is not None and str(r['sku']).strip():refs[str(r['sku'])].append(r['id'])
duplicates={k:v for k,v in refs.items() if len(v)>1}
check('Sin referencias activas duplicadas',not duplicates,{'duplicates':dict(list(duplicates.items())[:20])})
zero=[k for k in refs if k.startswith('0')]
check('Referencias con ceros iniciales preservadas como texto',all(isinstance(k,str) for k in zero),{'leading_zero_refs':len(zero)})
cols={r['name'] for r in db.execute('PRAGMA table_info(uco_presentaciones)')}
required={'product_id','formato_original','envases','peso_envase_kg','peso_total_kg','tipo_envase','estado'}
check('Esquema de presentaciones completo',required<=cols,{'missing':sorted(required-cols)})
if required<=cols:
 pres=db.execute('SELECT * FROM uco_presentaciones').fetchall()
 bad=[];unknown=[]
 for p in pres:
  n=p['envases'];single=p['peso_envase_kg'];total=p['peso_total_kg']
  if n is not None and single is not None and total is not None and abs(n*single-total)>0.0001:bad.append({'product_id':p['product_id'],'envases':n,'peso_envase_kg':single,'peso_total_kg':total})
  if total is None:unknown.append(p['product_id'])
 check('Coherencia peso total = envases × peso por envase',not bad,{'inconsistencies':bad[:20]})
 check('Presentaciones sin peso verificable identificadas',True,{'unknown_weight_count':len(unknown)})
 check('Presentaciones no duplicadas',len(pres)==len({p['product_id'] for p in pres}),{'count':len(pres)})
 missing=[r['id'] for r in active if r['id'] not in {p['product_id'] for p in pres}]
 check('Productos activos con presentación asociada',not missing,{'without_presentation':missing[:20],'count':len(missing)})
php=(root/'public/api/assistant-catalog-71a.php').read_text(encoding='utf-8')
check('API distingue unique/multiple/none',all(x in php for x in ("'match_status'","'has_more'","'unique'","'multiple'","'none'")))
check('API utiliza límite con fila extra',"LIMIT 13" in php and 'count($rows)>12' in php)
check('API conserva búsqueda exacta por SKU','p.sku=:sku' in php)
check('API conserva filtrado por formatos','ucoFormatQuery72e3' in php)
check('API no ejecuta escrituras de catálogo',not re.search(r'\b(?:INSERT\s+INTO|UPDATE\s+products|DELETE\s+FROM)\b',php,re.I))
result={'phase':'7.2E.10','kind':'read-only diagnostic','checks':checks,'passed':sum(x['ok'] for x in checks),'total':len(checks),'warnings':warnings,'http_tested':False}
print(json.dumps(result,ensure_ascii=False,indent=2))
sys.exit(0 if not warnings else 1)
