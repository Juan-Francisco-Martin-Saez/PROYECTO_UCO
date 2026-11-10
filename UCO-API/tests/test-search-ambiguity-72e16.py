#!/usr/bin/env python3
"""Auditoría reproducible y de solo lectura. Aproximación SQL; NO ejecuta el endpoint PHP."""
import json, sqlite3, pathlib, sys
root=pathlib.Path(__file__).resolve().parents[1]
db=root/'private/database/catalogo.sqlite'
con=sqlite3.connect(f'file:{db}?mode=ro',uri=True)
CASES=[
 ('aros de cebolla',['aros','cebolla'],'exact_product'),
 ('cebolla congelada',['cebolla','congelad'],'ambiguous_qualifier'),
 ('verduras con cebolla',['verdura','cebolla'],'ingredient_or_product'),
 ('zanahoria',['zanahori'],'ingredient_or_product'),
 ('producto inexistente xyz',['inexistente','xyz'],'no_match'),
]
def search(words):
 terms=['(p.name LIKE ? OR p.description LIKE ? OR p.search_text LIKE ? OR c.name LIKE ?)' for _ in words]
 sql='SELECT p.sku,p.name,CASE WHEN '+ ' AND '.join('p.name LIKE ?' for _ in words) + ' THEN 0 ELSE 1 END AS tier FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND (c.id IS NULL OR c.active=1) AND '+' AND '.join(terms)+' ORDER BY tier,p.is_demo,p.name LIMIT 13'
 params=[f'%{w}%' for w in words for _ in range(4)]+[f'%{w}%' for w in words]
 return con.execute(sql,params).fetchall()
report=[]
for query,terms,category in CASES:
 rows=search(terms)
 report.append({'query':query,'category':category,'matches_sampled':len(rows),'has_more_possible':len(rows)==13,'top':[{'sku':r[0],'name':r[1],'name_terms_match':r[2]==0} for r in rows[:5]],'requires_semantic_review':category in ('ambiguous_qualifier','ingredient_or_product')})
assert any('AROS DE CEBOLLA' in x['name'] for x in report[0]['top'][:2])
assert report[-1]['matches_sampled']==0
assert len(report)==5
print(json.dumps({'version':'7.2E.16','method':'read-only SQL approximation, not PHP HTTP','cases':report},ensure_ascii=False,indent=2))
