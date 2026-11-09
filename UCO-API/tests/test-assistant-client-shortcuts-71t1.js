const fs=require('node:fs'),assert=require('node:assert/strict'),path=require('node:path');
const js=fs.readFileSync(path.join(__dirname,'../public/assistant/assistant.js'),'utf8');
const html=fs.readFileSync(path.join(__dirname,'../public/clientes/index.html'),'utf8');
assert(js.includes("[['Carrito','#cart-toggle'],['Historial de compras','#uco-purchase-history']]"));
assert(js.includes("if(area==='client'){"));
assert(js.includes('if(nav){nav.click()}'),'Shortcuts must trigger native client navigation');
assert(/assistant\.js\?v=[\w.%-]+/.test(html),'Client must load versioned assistant script');
console.log('7.1T.1 client shortcuts OK');
