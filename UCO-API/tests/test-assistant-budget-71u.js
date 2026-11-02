const fs=require('node:fs');const assert=require('node:assert/strict');
const s=fs.readFileSync(require('node:path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
for(const key of ['parseBudgetCommand','updateProvisionalBudget','showProvisionalBudget','provisionalBudget=null','credentials:\'same-origin\'','price_cents','sellingUnit(row.unit)','area===\'client\''])assert(s.includes(key),`Missing ${key}`);
assert(s.includes('provisionalBudget=products.map'),'Initial multi-product estimate must establish draft');
assert(s.includes('provisionalBudget=null;append'),'Reset must discard draft');
assert(!s.includes('sessionStorage.setItem(\'uco_budget'),'Budget must remain ephemeral');
console.log('7.1U static integration: OK');
