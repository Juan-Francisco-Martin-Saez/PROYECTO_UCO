from pathlib import Path
s=Path(__file__).resolve().parents[1].joinpath('public/api/assistant-catalog-71a.php').read_text()
assert 'rank_name_word_' in s
assert 'nameAllSql' in s
assert 'THEN 5 WHEN ".$nameAllSql." THEN 6 WHEN c.name' in s, "La prioridad de todos los términos del nombre debe preceder a categoría"
assert 'LIMIT 13' in s
print('4/4 comprobaciones estructurales correctas (no HTTP)')
