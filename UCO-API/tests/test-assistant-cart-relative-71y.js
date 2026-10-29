const fs=require('fs'),assert=require('assert');
const bot=fs.readFileSync('public/assistant/assistant.js','utf8');
const client=fs.readFileSync('public/clientes/clientes.js','utf8');
for(const action of ['cart-increase','cart-decrease']){assert(bot.includes(action));assert(client.includes(action));}
assert(client.includes('current+detail.quantity'));
assert(client.includes('current-detail.quantity'));
assert(client.includes('qty<0||qty>99999'));
assert(client.includes('await cart.set(item.product_id,qty)'));
assert(client.includes('await refreshCart()'));
assert(bot.includes("area==='client'?parseBudgetCommand(value):null"));
console.log('7.1Y static cart integration checks OK');
