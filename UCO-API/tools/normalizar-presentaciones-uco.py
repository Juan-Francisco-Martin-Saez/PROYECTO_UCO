#!/usr/bin/env python3
"""Normaliza presentaciones sin alterar tarifas ni referencias; ejecutar sobre copia respaldada."""
import sqlite3,re,sys
from pathlib import Path
p=Path(sys.argv[1]) if len(sys.argv)>1 else Path(__file__).resolve().parents[1]/'private/database/catalogo.sqlite'
c=sqlite3.connect(p)
c.execute('''CREATE TABLE IF NOT EXISTS uco_presentaciones (product_id INTEGER PRIMARY KEY, formato_original TEXT NOT NULL, envases INTEGER, peso_envase_kg REAL, peso_total_kg REAL, tipo_envase TEXT, unidad_venta TEXT, estado TEXT NOT NULL, FOREIGN KEY(product_id) REFERENCES products(id))''')
rows=c.execute('SELECT p.id, COALESCE(f.label,\'\'), p.name FROM products p LEFT JOIN product_formats f ON f.product_id=p.id AND f.active=1 GROUP BY p.id').fetchall()
for pid,fmt,name in rows:
 s=fmt.upper().replace(',','.')
 m=re.search(r'(\d+)\s*[X×*]\s*(\d+(?:\.\d+)?)\s*(KG|KGS|GR|G)\b',s)
 env=unit=total=None; status='por confirmar'
 if m:
  env=int(m[1]);unit=float(m[2])/(1000 if m[3] in ('GR','G') else 1);total=round(env*unit,4);status='desglosado'
 else:
  m=re.search(r'(?<!\d)(\d+(?:\.\d+)?)\s*(KG|KGS|GR|G)\b',s)
  if m:
   total=float(m[1])/(1000 if m[2] in ('GR','G') else 1);status='peso conocido; envases por confirmar'
 kind=next((word.lower() for word in ('BOLSA','CAJA','CUBO','BANDEJA','SACO','BOTE','TARRINA') if word in s),None)
 c.execute('INSERT OR REPLACE INTO uco_presentaciones VALUES (?,?,?,?,?,?,?,?)',(pid,fmt,env,unit,total,kind,None,status))
c.commit()
print('Presentaciones analizadas:',len(rows))
for status,n in c.execute('SELECT estado,count(*) FROM uco_presentaciones GROUP BY estado'):print(status,n)
