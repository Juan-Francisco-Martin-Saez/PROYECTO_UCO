"""Crea agrupaciones comerciales reversibles; nunca altera SKU, precios ni pedidos."""
import sqlite3,re,unicodedata,json,csv,pathlib
root=pathlib.Path(__file__).resolve().parents[1]
db=sqlite3.connect(root/'private/database/catalogo.sqlite')
db.row_factory=sqlite3.Row
rows=db.execute('SELECT p.id,p.sku,p.name,p.category_id,p.active,f.label formato FROM products p LEFT JOIN product_formats f ON f.product_id=p.id AND f.active=1 WHERE p.is_demo=0 ORDER BY p.id').fetchall()
brands=['FINDUS','MAHESO','ABUELA CANDIDA','COR DEL RAIGUER','CONNOLLY','FALGAS']
def normalize(s):
 s=unicodedata.normalize('NFKD',s.upper());return ''.join(c for c in s if not unicodedata.combining(c))
def key(r):
 s=normalize(r['name']);s=re.sub(r'\b(?:KM\s*0|KM0)\s*\*?','',s)
 s=re.sub(r'\b(?:COR DEL RAIGUER|FINDUS|MAHESO|ABUELA CANDIDA|CONNOLLY.S|FALGAS)\b','',s)
 s=re.sub(r'\b(?:CAJA|BOLSA|PIEZA|BANDEJA|SACO)\s*\d*(?:[.,]\d+)?\s*(?:X|\*)?\s*\d*(?:[.,]\d+)?\s*(?:KG|GR|G|UDS?|UND)?\b',' ',s)
 s=re.sub(r'\b\d+(?:[.,]\d+)?\s*(?:X|\*)\s*\d+(?:[.,]\d+)?\s*(?:KG|GR|G|UDS?|UND)\b',' ',s)
 s=re.sub(r'\b\d+(?:[.,]\d+)?\s*(?:KG|GR|G|UDS?|UND)\.?\b',' ',s)
 s=re.sub(r'\s+',' ',re.sub(r'[^A-Z0-9ÑÁÉÍÓÚ ]',' ',s)).strip()
 return s
# Únicamente unir nombres normalizados idénticos y de la misma familia.
groups={}
for r in rows:
 k=(r['category_id'],key(r))
 groups.setdefault(k,[]).append(r)
db.execute('CREATE TABLE IF NOT EXISTS uco_commercial_groups(id INTEGER PRIMARY KEY,display_name TEXT NOT NULL,category_id INTEGER NOT NULL,review_status TEXT NOT NULL DEFAULT "automatic")')
db.execute('CREATE TABLE IF NOT EXISTS uco_commercial_group_members(product_id INTEGER PRIMARY KEY REFERENCES products(id),group_id INTEGER NOT NULL REFERENCES uco_commercial_groups(id),original_name TEXT NOT NULL)')
db.execute('DELETE FROM uco_commercial_group_members');db.execute('DELETE FROM uco_commercial_groups')
for (cat,k),members in groups.items():
 first=members[0];gid=min(r['id'] for r in members)
 display=k.title() if k else first['name'].title()
 db.execute('INSERT INTO uco_commercial_groups(id,display_name,category_id) VALUES(?,?,?)',(gid,display,cat))
 for r in members:db.execute('INSERT INTO uco_commercial_group_members VALUES(?,?,?)',(r['id'],gid,r['name']))
db.commit()
with open(root/'INFORME_AGRUPACIONES_20261103.csv','w',newline='',encoding='utf-8-sig') as f:
 w=csv.writer(f,delimiter=';');w.writerow(['grupo','nombre_comercial','referencia','nombre_original','formato','estado'])
 for (cat,k),members in groups.items():
  for r in members:w.writerow([min(x['id'] for x in members),k.title(),r['sku'],r['name'],r['formato'] or '', 'REVISAR' if len(members)>1 else 'INDIVIDUAL'])
print('Referencias:',len(rows),'Fichas:',len(groups),'Grupos múltiples:',sum(len(v)>1 for v in groups.values()))
