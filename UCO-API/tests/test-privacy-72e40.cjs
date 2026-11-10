const fs=require('fs'),vm=require('vm'),assert=require('assert');
const js=fs.readFileSync(require('path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
const a=js.indexOf('function isSafeGeneralQuestion72e39(question){'),b=js.indexOf('\nasync function askProtectedOpenAI(',a);
assert(a>=0&&b>a);const ctx={normalizeUco:s=>s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^\p{L}\p{N}\s]/gu,' ').replace(/\s+/g,' ').trim()};vm.createContext(ctx);vm.runInContext(js.slice(a,b),ctx);
for(const q of ['12345678Z','X1234567L','ES9121000418450200051332','sk-abcdefghijklmnopqrstuvwxyz','ghp_abcdefghijklmnopqrstuvwxyz'])assert.strictEqual(ctx.isSafeGeneralQuestion72e39(q),false,q);
for(const q of ['¿Qué es una API?','Explica la fotosíntesis'])assert.strictEqual(ctx.isSafeGeneralQuestion72e39(q),true,q);
console.log('7/7 comprobaciones nuevas OK');
