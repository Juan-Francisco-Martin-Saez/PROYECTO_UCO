from pathlib import Path
s=Path("public/api/assistant-catalog-71a.php").read_text()
assert "LOWER(p.name)=LOWER(:rank_exact)" in s
assert ":rank_prefix" in s and ":rank_contains" in s
assert "p.is_demo ASC,p.name ASC LIMIT 13" in s
assert "p.sku=:sku" in s
print("Ranking structure OK")
