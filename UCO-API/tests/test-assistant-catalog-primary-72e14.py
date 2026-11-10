from pathlib import Path
s=(Path(__file__).resolve().parents[1]/"public/api/assistant-catalog-71a.php").read_text()
assert "rank_stem_prefix" in s and "rank_stem_contains" in s
assert "WHEN p.name LIKE :rank_stem_contains" in s
assert "WHEN c.name LIKE :rank_stem_contains" in s
assert s.index("WHEN p.name LIKE :rank_stem_contains") < s.index("WHEN c.name LIKE :rank_stem_contains")
assert "p.sku=:sku" in s and "LIMIT 13" in s
print("OK: prioridad nombre sobre categoría/descripción; referencia exacta intacta")
