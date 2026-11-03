#!/usr/bin/env python3
"""Vincula solo imágenes locales cuyo motivo se identificó en el diseño original."""
import sqlite3
from pathlib import Path
root=Path(__file__).resolve().parents[1];db=sqlite3.connect(root/'private/database/catalogo.sqlite')
# Fotografías de portada identificadas en el código original; fotos ilustrativas, no fichas del fabricante.
known=[('AROS DE CEBOLLA','15.webp'),('FILETE DE','16.webp'),('PATATA EXTRA','11.webp'),('PATATÓ','11.webp')]
with db:
 for pid,name in db.execute('SELECT id,name FROM products WHERE is_demo=0 AND (image IS NULL OR image="")').fetchall():
  for prefix,img in known:
   if name.upper().startswith(prefix) and (root/'public/img'/img).is_file():
    db.execute('UPDATE products SET image=? WHERE id=?',('./img/'+img,pid));break
print('Fotos vinculadas:',db.execute('SELECT COUNT(*) FROM products WHERE is_demo=0 AND image IS NOT NULL AND image!=""').fetchone()[0])
