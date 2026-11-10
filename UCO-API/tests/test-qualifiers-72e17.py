#!/usr/bin/env python3
"""Read-only checks: qualifier evidence in the real catalog, not a PHP HTTP test."""
from pathlib import Path
import sqlite3
p=Path(__file__).resolve().parents[1]/'private/database/catalogo.sqlite'
c=sqlite3.connect('file:'+str(p)+'?mode=ro',uri=True)
assert c.execute("SELECT COUNT(*) FROM products WHERE active=1").fetchone()[0]>0
for term in ('cebolla','zanahoria','patata'):
 assert c.execute("SELECT COUNT(*) FROM products WHERE active=1 AND LOWER(name) LIKE ?",('%'+term+'%',)).fetchone()[0]>0,term
print('4/4 pruebas de integridad y disponibilidad de términos superadas; no equivalen a pruebas HTTP.')
c.close()
