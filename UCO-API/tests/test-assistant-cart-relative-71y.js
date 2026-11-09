const fs=require('node:fs'),assert=require('node:assert/strict'),path=require('node:path');
const bot=fs.readFileSync(path.join(__dirname,'../public/assistant/assistant.js'),'utf8');
const client=fs.readFileSync(path.join(__dirname,'../public/clientes/clientes.js'),'utf8');
for(const action of ['cart-increase','cart-decrease']){assert(bot.includes(action));assert(client.includes(action));}
for(const s of ['current+detail.quantity','current-detail.quantity','qty<0||qty>99999','await cart.set(item.product_id,qty)','await refreshCart()'])assert(client.includes(s),`Missing cart guard/update ${s}`);
assert(/area==='client'\?\(parseBudgetCommand\(value\)\|\|cartContextCommand\(value\)\):null/.test(bot),'Client cart must use explicit and contextual commands');
assert(bot.includes('Never infer a product from conversation context'),'Explicit relative changes must remain guarded');
console.log('7.1Y relative cart integration OK');
