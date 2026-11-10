const fs=require('fs'),vm=require('vm'),assert=require('assert'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../public/assistant/assistant.js'),'utf8');
function extract(name){const start=source.indexOf('function '+name+'(');assert(start>=0,name);let open=source.indexOf('{',start),depth=0,quote=null,escape=false;for(let i=open;i<source.length;i++){const c=source[i];if(quote){if(escape){escape=false;continue}if(c==='\\'){escape=true;continue}if(c===quote)quote=null;continue}if(c==='"'||c==="'"||c==='`'){quote=c;continue}if(c==='{')depth++;if(c==='}'&&!--depth)return source.slice(start,i+1)}throw Error('unclosed '+name)}
const names=['normalizeUco','readCatalogContext','isFollowup','parseOrderId','orderQuestion','isOwnDocumentQuery','contextualRoute','isSafeGeneralQuestion72e39','shouldUseGeneralEngine72e22'];
const store=new Map();let order=null,budget=null,transfer=null,ref=null;const ctx={sessionStorage:{getItem:k=>store.get(k)||null,setItem:(k,v)=>store.set(k,v),removeItem:k=>store.delete(k)},CTX_KEY:'uco_assistant_catalog_context_71g',CTX_TTL:1800000,Date,Number,JSON,console,get lastOwnOrderId(){return order},get provisionalBudget(){return budget},get pendingCartTransfer(){return transfer},get lastCartReference(){return ref}};
vm.createContext(ctx);vm.runInContext(names.map(extract).join('\n'),ctx);
const general=['¿Qué es una API?','Por cierto, ¿cómo funciona internet?','Define biodiversidad','¿Por qué llueve?','Y ¿qué es una API?'];
const local=[['¿Qué productos hay?','catalog'],['¿Qué patatas hay?','catalog'],['¿Cuánto cuesta?',null],['¿Y las otras?',null],['¿Cuál es el estado de mi pedido?','order'],['Muéstrame mis pedidos','order'],['Ver justificante de mi pedido','document']];
const secrets=['IA: Mi pedido 123','IA: test@example.com','IA: ES9121000418450200051332','IA: sk-proj-abcdefghijklmnop'];
let checks=0, generalAllowed=0, generalRetained=0, localRetained=0, privacyBlocked=0, contextTransitions=0;
function check(q,expected,expectedRoute){const before=JSON.stringify({order,budget,transfer,ref,store:[...store]});const route=ctx.contextualRoute(q),auto=ctx.shouldUseGeneralEngine72e22(q);assert.strictEqual(auto,expected,JSON.stringify({q,route,order,budget,transfer,ref}));if(expectedRoute && order===null && !budget && !transfer && !ref)assert.strictEqual(route,expectedRoute,q);assert.strictEqual(JSON.stringify({order,budget,transfer,ref,store:[...store]}),before,'routing mutated context');checks++;if(expected)generalAllowed++;else if(general.includes(q))generalRetained++;else localRetained++;}
for(let session=0;session<20;session++){
 store.clear();order=budget=transfer=ref=null;
 for(let step=0;step<40;step++){
  const phase=step%10;
  if(phase===0){store.set(ctx.CTX_KEY,JSON.stringify({term:'patatas',at:Date.now()}));order=budget=transfer=ref=null;contextTransitions++}
  if(phase===2){order=100+session;contextTransitions++}
  if(phase===4){order=null;budget={items:[]};contextTransitions++}
  if(phase===6){budget=null;transfer={items:[]};contextTransitions++}
  if(phase===8){transfer=null;ref='SKU-TEST';contextTransitions++}
  const active=Boolean(order!==null||budget||transfer||ref);
  if(step%2===0){const q=general[(session+step)%general.length];check(q,!active,'general')}
  else {const [q,route]=local[(session+step)%local.length];check(q,false,route)}
 }
 // Se cierra el contexto comercial: la pregunta general vuelve a estar disponible.
 order=budget=transfer=ref=null;check('¿Qué es una API?',true,'general');
 // Los datos privados jamás superan el filtro para el motor externo.
 for(const q of secrets){assert.strictEqual(ctx.isSafeGeneralQuestion72e39(q.slice(4)),false,q);privacyBlocked++;checks++}
}
// Caso de catálogo caducado y contexto inválido: no se propagan datos de cliente.
store.set(ctx.CTX_KEY,JSON.stringify({term:'patatas',at:Date.now()-1900000}));assert.strictEqual(ctx.readCatalogContext(),null);checks++;
store.set(ctx.CTX_KEY,'{broken');assert.strictEqual(ctx.readCatalogContext(),null);checks++;
console.log(JSON.stringify({passed:checks,sessions:20,turnsPerSession:40,autoGeneral:generalAllowed,commercialOrContextRetained:localRetained+generalRetained,privacyBlocked,contextTransitions,productionChanged:false},null,2));