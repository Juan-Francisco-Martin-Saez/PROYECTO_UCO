const fs=require('node:fs'),assert=require('node:assert/strict');
const js=fs.readFileSync(require('node:path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
assert.match(js,/choices\.every\(list=>list\.length===1 && !list\[0\]\.is_demo\)/);
assert.match(js,/await askOwnPrices\(\[choices\[0\]\[0\],choices\[1\]\[0\]\]\)/);
assert.match(js,/Number\(choices\[0\]\[0\]\.id\)!==Number\(choices\[1\]\[0\]\.id\)/);
assert.match(js,/credentials:'same-origin'/);
console.log('7.1Q: static checks OK');
