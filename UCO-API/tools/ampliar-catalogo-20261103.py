#!/usr/bin/env python3
"""Migración idempotente: ejecutar sobre copia actualizada de SQLite, nunca sustituir producción."""
import sqlite3,re,unicodedata,sys,shutil
from pathlib import Path
from decimal import Decimal,ROUND_HALF_UP
DB=Path(sys.argv[1]) if len(sys.argv)>1 else Path(__file__).resolve().parents[1]/'private/database/catalogo.sqlite'
def norm(s):return ''.join(x for x in unicodedata.normalize('NFD',s.upper()) if unicodedata.category(x)!='Mn')
def category(name,old):
 n=norm(name)
 if 'COR DEL RAIGUER KM 0' in n:return 'uco-km0'
 if any(x in n for x in ('SAL FINA','SAL GRUESA','SAL MARINA','SAL YODADA')):return 'sales'
 if any(x in n for x in ('LENTEJA','GARBANZO','ALUBIA','ARROZ SECO','ARROZ BOMBA','ARROZ LARGO')):return 'legumbres-arroces'
 if old==3 or old==4:return 'congelados'
 if any(x in n for x in ('CREMA','CALDO','SALSA','BECHAMEL','COCIDA','COCIDO','ASADO','ASADA','ESCALIVADA','FRITO MALLORQUIN','PAELLA','GAZPACHO','SALMOREJO','LOLLIPOPS','PIRULETAS','BOMBON')):return 'v-gama'
 if any(x in n for x in ('PELAD','CORTAD','CUADRO','CUBO','DADO','RODAJA','JULIANA','RALLAD','TIRAS','BASTON','PICADA','ENSALADA','MEZCLUM','BROTES','GERMINADO','SOFRITO','LECHUGA ICEBERG 9','LECHUGA ICEBERG 20')):return 'iv-gama'
 return 'frutas-verduras'
def weight(desc):
 s=norm(desc).replace(',','.')
 # formato compuesto 4*2.5 KG, 6X1 KG
 m=re.search(r'(\d+)\s*[X*]\s*(\d+(?:\.\d+)?)\s*(KG|GR|G)\b',s)
 if m:return Decimal(m[1])*Decimal(m[2])/(Decimal(1000) if m[3]!='KG' else 1)
 m=re.search(r'(\d+(?:\.\d+)?)\s*(KG|GR|G)\b',s)
 if m:return Decimal(m[1])/(Decimal(1000) if m[2]!='KG' else 1)
 return None
shutil.copy2(DB,DB.with_suffix('.pre-familias-precios.sqlite'))
c=sqlite3.connect(DB);c.execute('PRAGMA foreign_keys=ON')
with c:
 cats=[('frutas-verduras','Frutas y verduras'),('iv-gama','IV Gama'),('v-gama','V Gama'),('congelados','Congelados'),('legumbres-arroces','Legumbres y arroces'),('sales','Sales'),('uco-km0','UCO KM0')]
 for i,(slug,label) in enumerate(cats,1):c.execute('INSERT INTO categories(slug,name,sort_order,active) VALUES(?,?,?,1) ON CONFLICT(slug) DO UPDATE SET name=excluded.name,sort_order=excluded.sort_order,active=1',(slug,label,i))
 ids=dict(c.execute('SELECT slug,id FROM categories'))
 c.execute('CREATE TABLE IF NOT EXISTS product_weight_prices(product_id INTEGER PRIMARY KEY REFERENCES products(id) ON DELETE CASCADE,weight_kg REAL NOT NULL CHECK(weight_kg>0),price_per_kg_cents INTEGER NOT NULL CHECK(price_per_kg_cents>=0),price_source TEXT NOT NULL DEFAULT "catalog_import",verified INTEGER NOT NULL DEFAULT 0 CHECK(verified IN(0,1)))')
 c.execute('CREATE TABLE IF NOT EXISTS product_km0(product_id INTEGER PRIMARY KEY REFERENCES products(id) ON DELETE CASCADE,origin_note TEXT NOT NULL)')
 products=c.execute('SELECT p.id,p.name,p.description,p.category_id,b.price_cents FROM products p LEFT JOIN base_prices b ON b.product_id=p.id WHERE p.is_demo=0').fetchall()
 for pid,name,desc,old,price in products:
  slug=category(name,old);c.execute('UPDATE products SET category_id=? WHERE id=?',(ids[slug],pid))
  if slug=='uco-km0':c.execute('INSERT OR IGNORE INTO product_km0 VALUES (?,?)',(pid,'Cor del Raiguer KM 0 (identificación en catálogo de origen)'))
  w=weight(desc)
  if w is not None and price is not None:
   c.execute('INSERT OR IGNORE INTO product_weight_prices(product_id,weight_kg,price_per_kg_cents,verified) VALUES(?,?,?,0)',(pid,float(w),price))
   # La tarifa importada se interpreta como precio/kg cuando hay formato en peso; revisión comercial obligatoria.
   total=int((Decimal(price)*w).quantize(Decimal('1'),rounding=ROUND_HALF_UP))
   c.execute('UPDATE base_prices SET price_cents=? WHERE product_id=?',(total,pid))
 # Solo categorías nuevas visibles; las antiguas permanecen para preservar referencias históricas.
 c.execute('UPDATE categories SET active=0 WHERE slug IN ("verdura","patata","congelado","empanado")')
 # El cambio del precio/kg actualiza el total de venta de forma transaccional.
 c.execute('''CREATE TRIGGER IF NOT EXISTS uco_weight_price_insert AFTER INSERT ON product_weight_prices BEGIN
 INSERT INTO base_prices(product_id,price_cents) VALUES(NEW.product_id,CAST(ROUND(NEW.price_per_kg_cents*NEW.weight_kg) AS INTEGER)) ON CONFLICT(product_id) DO UPDATE SET price_cents=excluded.price_cents; END''')
 c.execute('''CREATE TRIGGER IF NOT EXISTS uco_weight_price_update AFTER UPDATE OF price_per_kg_cents,weight_kg ON product_weight_prices BEGIN
 INSERT INTO base_prices(product_id,price_cents) VALUES(NEW.product_id,CAST(ROUND(NEW.price_per_kg_cents*NEW.weight_kg) AS INTEGER)) ON CONFLICT(product_id) DO UPDATE SET price_cents=excluded.price_cents; END''')
print('Integridad:',c.execute('PRAGMA integrity_check').fetchone()[0]);print('Familias:',c.execute('SELECT c.name,COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_demo=0 GROUP BY c.name').fetchall());print('Precios por peso:',c.execute('SELECT COUNT(*) FROM product_weight_prices').fetchone()[0])
