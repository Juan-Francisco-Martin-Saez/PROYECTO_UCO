#!/usr/bin/env python3
"""Idempotent catalog activation. Does not delete orders, prices, or historical products."""
import sqlite3, pathlib, json, shutil, datetime
root=pathlib.Path(__file__).resolve().parents[1]
p=root/'private/database/catalogo.sqlite'
con=sqlite3.connect(p)
con.execute('PRAGMA foreign_keys=ON')
assert con.execute('PRAGMA integrity_check').fetchone()[0]=='ok'
assert con.execute('select count(*) from products where is_demo=0').fetchone()[0]==370
assert con.execute('select count(*) from products where is_demo=1').fetchone()[0]==16
assert con.execute('select count(*) from products p join categories c on c.id=p.category_id where p.is_demo=0 and c.active=0').fetchone()[0]==0
assert con.execute('select count(*) from products p left join base_prices b on b.product_id=p.id where p.is_demo=0 and b.product_id is null').fetchone()[0]==0
backup=p.with_name('catalogo_ANTES_ACTIVACION_20261103.sqlite')
if not backup.exists():
 with sqlite3.connect(backup) as dest:con.backup(dest)
with con:
 con.execute("UPDATE products SET active=0,updated_at=CURRENT_TIMESTAMP WHERE is_demo=1 AND active<>0")
 con.execute("UPDATE products SET active=1,stock=500,updated_at=CURRENT_TIMESTAMP WHERE is_demo=0 AND (active<>1 OR stock<>500)")
assert con.execute('select count(*) from products where is_demo=0 and active=1 and stock=500').fetchone()[0]==370
assert con.execute('select count(*) from products where is_demo=1 and active=1').fetchone()[0]==0
assert con.execute('PRAGMA integrity_check').fetchone()[0]=='ok'
print('OK: 370 reales activos con stock 500; 16 demostración inactivos; pedidos y tarifas intactos')
