from pathlib import Path
import re,sqlite3
root=Path(__file__).resolve().parents[1]
code=(root/'public/api/assistant-catalog-71a.php').read_text()
assert "p.name LIKE :rank_phrase" in code
assert "THEN 5 WHEN" in code and "THEN 6 WHEN c.name" in code
assert "implode('%',$phraseWords)" in code
assert "LIMIT 13" in code
assert "p.sku=:sku" in code
con=sqlite3.connect('file:'+str(root/'private/database/catalogo.sqlite')+'?mode=ro',uri=True)
rows=con.execute("SELECT name FROM products WHERE active=1 AND UPPER(name) LIKE '%AROS%CEBOLLA%' LIMIT 5").fetchall()
assert rows, 'Catalog should contain compound product names'
print('OK: structural ranking checks and read-only SQLite fixture')
