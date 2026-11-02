const fs=require('fs'),assert=require('assert');
const s=fs.readFileSync(require('path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
assert(s.includes("'/api/orders.php?action=history'"));
assert(s.includes("credentials:'same-origin'"));
assert(s.includes('isOwnOrderQuery(value)?await askOwnOrders(value)'));
assert(s.includes('response.status===401'));
console.log('7.1H: integración de pedidos autenticados comprobada (estática)');
