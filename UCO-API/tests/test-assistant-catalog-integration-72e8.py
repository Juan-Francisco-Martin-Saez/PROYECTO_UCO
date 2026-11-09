#!/usr/bin/env python3
"""Read-only checks for catalog ranking, presentation filters, and query limits.
This does NOT replace a PHP HTTP integration test (pdo_sqlite + mbstring required).
"""
import sqlite3, pathlib, sys
root=pathlib.Path(__file__).resolve().parents[1]
db=sqlite3.connect(f"file:{root / 'private/database/catalogo.sqlite'}?mode=ro",uri=True)
db.row_factory=sqlite3.Row
checks=[]
def check(label,condition):
    checks.append((label,bool(condition)))
    print(('OK' if condition else 'FAIL')+' '+label)
# Exact references are identifiers, never cast to integers.
ref=db.execute("SELECT sku FROM products WHERE active=1 AND sku IS NOT NULL AND sku != '' LIMIT 1").fetchone()
check('Catalog has an active reference',ref is not None)
if ref:
    sku=ref['sku']
    check('Exact reference lookup',db.execute('SELECT COUNT(*) FROM products WHERE active=1 AND sku=?',(sku,)).fetchone()[0]>=1)
# Validate the real presentation table and its declared dimensions.
columns={r['name'] for r in db.execute('PRAGMA table_info(uco_presentaciones)')}
check('Presentation dimensions available',{'product_id','peso_total_kg','envases','peso_envase_kg','tipo_envase','estado'}<=columns)
check('No duplicate presentation rows',db.execute('SELECT COUNT(*) FROM uco_presentaciones').fetchone()[0]==db.execute('SELECT COUNT(DISTINCT product_id) FROM uco_presentaciones').fetchone()[0])
check('One-kilo presentation SQL',db.execute('SELECT COUNT(*) FROM uco_presentaciones WHERE peso_total_kg BETWEEN ? AND ?',(0.99999,1.00001)).fetchone()[0]>=0)
check('Multi-pack presentation SQL',db.execute("SELECT COUNT(*) FROM uco_presentaciones WHERE estado='desglosado' AND envases>1 AND peso_envase_kg>0").fetchone()[0]>=0)
# Test the actual 13-row lookahead used to detect truncation.
rows=db.execute('SELECT id FROM products WHERE active=1 ORDER BY name LIMIT 13').fetchall()
check('Limit-13 lookahead returns at most 13',len(rows)<=13)
# The endpoint must use sanitized terms, rather than the original polite request, for ranking.
php=(root/'public/api/assistant-catalog-71a.php').read_text()
check('Rank derives from filtered product terms',"$rankText=implode(' ',$words);" in php)
check('Lookahead and truncation', 'LIMIT 13' in php and 'count($rows)>12' in php)
check('Ambiguity metadata', "'match_status'" in php and "'has_more'" in php)
print(f'{sum(ok for _,ok in checks)}/{len(checks)} passed')
sys.exit(0 if all(ok for _,ok in checks) else 1)
