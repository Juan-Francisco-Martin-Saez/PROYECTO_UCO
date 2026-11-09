const fs=require('node:fs'),assert=require('node:assert/strict'),path=require('node:path');
const s=fs.readFileSync(path.join(__dirname,'../public/assistant/assistant.js'),'utf8');
assert(s.includes("'/api/orders.php?action=history'"));
assert(s.includes("credentials:'same-origin'"));
assert(s.includes('response.status===401'),'Unauthorized history must be denied');
assert(s.includes("route==='document'?await askOwnDocuments(value)"),'Document route must be distinct');
assert(s.includes("route==='order'?await askOwnOrders(value)"),'Order route must use authenticated history');
assert(s.includes('async function askOwnOrders(value)'));
console.log('7.1H authenticated order/document routing OK');
