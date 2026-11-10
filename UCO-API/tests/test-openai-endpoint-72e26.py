from pathlib import Path
import re
s=Path(__file__).resolve().parents[1].joinpath('public/api/assistant-openai.php').read_text()
checks={
'POST only': "'POST')outAI" in s,
'strict media type': "$contentType!=='application/json'" in s,
'actual payload limit': "strlen($rawBody)>12000" in s,
'string-only message': '!is_string($message)' in s,
'private config': "private/config/openai-protected.php" in s,
'no-store': 'Cache-Control: no-store, private' in s,
'no order API': "orders.php" not in s,
'no DB write': not re.search(r'\\b(?:INSERT|UPDATE|DELETE)\\s+(?:INTO|products|orders)',s,re.I),
'output structure guard': "is_array($entry['content']??null)" in s,
'prompt version preserved': "'version'=>$conf['prompt_version']" in s,
'store disabled': "'store'=>false" in s,
}
for k,v in checks.items():print(('PASS' if v else 'FAIL'),k)
assert all(checks.values())
print(f'{len(checks)}/{len(checks)} verificaciones estáticas superadas; NO HTTP')
