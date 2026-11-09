"""Contract check for additive ambiguity metadata (no PHP runtime required)."""
from pathlib import Path
p=Path(__file__).resolve().parents[1]/'public/api/assistant-catalog-71a.php'
s=p.read_text()
assert s.count("'match_status'")==2
assert s.count("'has_more'")==2
assert "LIMIT 13" in s
assert "count($rows)>12" in s
assert "array_slice($rows,0,12)" in s
assert "p.sku=:sku" in s
print('OK: metadata de ambigüedad, límite y referencia exacta')
