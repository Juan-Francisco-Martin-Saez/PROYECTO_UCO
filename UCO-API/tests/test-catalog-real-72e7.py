#!/usr/bin/env python3
"""Pruebas de regresión de lectura sobre el catálogo real. No modifica datos."""
import sqlite3
from pathlib import Path
p=Path(__file__).resolve().parents[1]/'private/database/catalogo.sqlite'
db=sqlite3.connect(f'file:{p}?mode=ro',uri=True)
assert db.execute('SELECT count(*) FROM products').fetchone()[0]>0
assert db.execute("SELECT count(*) FROM products WHERE sku='02040007'").fetchone()[0]==1
assert db.execute("SELECT count(*) FROM products WHERE name LIKE '%ZZZPRODUCTOINEXISTENTE%'").fetchone()[0]==0
assert db.execute('SELECT count(*) FROM uco_presentaciones').fetchone()[0]>0
assert db.execute('SELECT count(*) FROM uco_presentaciones WHERE peso_total_kg IS NOT NULL').fetchone()[0]>0
assert db.execute("SELECT count(*) FROM products WHERE name LIKE '%PATATA%'").fetchone()[0]>1
print('6 comprobaciones SQLite correctas')
