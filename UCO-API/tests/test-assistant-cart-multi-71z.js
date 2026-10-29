const fs=require('fs'),assert=require('assert');
const a=fs.readFileSync('public/assistant/assistant.js','utf8');
const c=fs.readFileSync('public/clientes/clientes.js','utf8');
for(const term of ["action:'cart-multi'", "command.action==='cart-multi'", 'requestNativeCartEdit(command.commands[i])', 'He detenido la operación'])assert(a.includes(term),term);
assert(c.includes("await cart.set(item.product_id,qty)"));
assert(c.includes('matches.length!==1'));
console.log('7.1Z: static integration checks OK');
