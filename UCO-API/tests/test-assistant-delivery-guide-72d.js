const fs=require('fs'),assert=require('assert');
const a=fs.readFileSync('public/assistant/assistant.js','utf8');
const c=fs.readFileSync('public/clientes/clientes.js','utf8');
assert(a.includes("action:'delivery-guide'"));
assert(a.includes("uco:assistant-delivery-guide"));
assert(c.includes("document.addEventListener('uco:assistant-delivery-guide'"));
assert(c.includes("form.elements.namedItem(name)"));
assert(c.includes('No he guardado ni enviado datos'));
console.log('7.2D: comprobaciones estáticas correctas');
