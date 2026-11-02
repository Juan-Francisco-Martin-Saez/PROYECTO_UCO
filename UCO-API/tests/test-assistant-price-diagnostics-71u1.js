const fs=require('fs'),assert=require('assert');
const api=fs.readFileSync('public/api/assistant-prices-71i.php','utf8');
const js=fs.readFileSync('public/assistant/assistant.js','utf8');
for(const reason of ['not_found','demo','inactive','category_inactive','price_missing'])assert(api.includes("'"+reason+"'"),reason);
assert(api.includes('if(!$cid)'),'authentication');
assert(api.includes('p.is_demo=0'),'demo guard');
assert(js.includes('data.unavailable'),'client diagnostic');
console.log('7.1U.1 static checks OK');
