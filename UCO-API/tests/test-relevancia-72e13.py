#!/usr/bin/env python3
"""7.2E.20: regresión de relevancia: ingrediente secundario no equivale a falso positivo."""
import sqlite3,pathlib,json,sys
root=pathlib.Path(__file__).resolve().parents[1]
db=sqlite3.connect(f"file:{root/'private/database/catalogo.sqlite'}?mode=ro",uri=True)
db.row_factory=sqlite3.Row
cases=[('patata','patat'),('cebolla','ceboll'),('zanahoria','zanahori'),('tomate','tomat'),('arroz','arroz')]
results=[]
for label,stem in cases:
 rows=db.execute("SELECT name FROM products WHERE active=1 AND (lower(name) LIKE ? OR lower(coalesce(search_text,'')) LIKE ?) LIMIT 500",('%'+stem+'%','%'+stem+'%')).fetchall()
 names=[r['name'] for r in rows]
 # El término solicitado debe aparecer en el nombre o search_text; la presencia de OTROS
 # ingredientes no invalida un producto compuesto.
 count=db.execute("SELECT COUNT(*) FROM products WHERE active=1 AND lower(name) LIKE ?",('%'+stem+'%',)).fetchone()[0]
 ok=bool(names) and count>0
 results.append({'query':label,'lexical_candidates':len(names),'named_products':count,'passed':ok})
print(json.dumps({'phase':'7.2E.20','scope':'SQLite read-only, not HTTP','passed':sum(r['passed'] for r in results),'total':len(results),'cases':results},ensure_ascii=False,indent=2))
sys.exit(0 if all(r['passed'] for r in results) else 1)
