const fs=require('fs'),assert=require('assert');
const a=fs.readFileSync('public/assistant/assistant.js','utf8');
const c=fs.readFileSync('public/clientes/clientes.js','utf8');
assert(a.includes("action:'cart-checkout-open'"));
assert(a.includes("uco:assistant-checkout-open"));
assert(c.includes("document.addEventListener('uco:assistant-checkout-open'"));
assert(c.includes("checkout.click()"));
assert(c.includes("if(!customer||!csrf)"));
console.log('7.2C static checks OK');
