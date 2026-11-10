const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(require('path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
const a=source.indexOf('function shouldUseGeneralEngine72e22(value){');
const b=source.indexOf("\nform.addEventListener('submit'",a);
assert(a>=0&&b>a);
let catalog=null,order=null,budget=null,transfer=null,reference=null;
const normalizeUco=x=>x.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^\p{L}\p{N}\s¿?¡!:]/gu,' ').replace(/\s+/g,' ').trim();
const isFollowup=x=>/^(y|tambien|ademas|que tal|cuanto|cuantos|cuesta|valen|vale|precio|precios|cuales|tipos|variedades|las|los|esas|esos|estos|estas|tienen|hay|estan|disponibles|puedo pedir|quiero pedir)\b/.test(normalizeUco(x))&&normalizeUco(x).split(' ').length<=9;
const contextualRoute=x=>/\b(producto|productos|patata|patatas|croqueta|croquetas|tomate|tomates|catalogo|categoria|categorias|variedad|variedades)\b/.test(normalizeUco(x))?'catalog':/\b(pedido|pedidos|factura)\b/.test(normalizeUco(x))?'order':'general';
const ctx={normalizeUco,isFollowup,readCatalogContext:()=>catalog,contextualRoute,get lastOwnOrderId(){return order},get provisionalBudget(){return budget},get pendingCartTransfer(){return transfer},get lastCartReference(){return reference}};
vm.createContext(ctx);vm.runInContext(source.slice(a,b),ctx);
const general=['¿Qué es una API?','Por cierto, ¿cómo funciona internet?','Define biodiversidad','¿Por qué llueve?','Y ¿qué es una API?','¿Cómo funciona internet?'];
const local=['¿Qué productos hay?','¿Cuánto cuesta?','¿Y las otras?','¿Qué es mi pedido?','Define mi cuenta','¿Qué significa precio?','¿Qué es el catálogo?','Hola'];
let count=0,blocked=0,generalAllowed=0,localRetained=0;
for(let session=0;session<40;session++){
 catalog={term:'patatas'};order=null;budget=null;transfer=null;reference=null;
 for(let step=0;step<100;step++){
  const mode=(session*7+step)%20;
  order=mode===0?42:null;
  budget=mode===1?{items:[{ref:'A'}]}:null;
  transfer=mode===2?{items:[{ref:'A'}]}:null;
  reference=mode===3?'SKU123':null;
  const blockedNow=Boolean(order!==null||budget||transfer||reference);
  const useGeneral=(step+session)%2===0;
  const q=useGeneral?general[(session+step)%general.length]:local[(session+step)%local.length];
  const expected=useGeneral&&!blockedNow;
  const before=JSON.stringify({catalog,order,budget,transfer,reference});
  const actual=ctx.shouldUseGeneralEngine72e22(q);
  assert.strictEqual(actual,expected,`session ${session} step ${step}: ${q}`);
  assert.strictEqual(JSON.stringify({catalog,order,budget,transfer,reference}),before,'classification mutated state');
  count++;if(blockedNow&&useGeneral)blocked++;if(actual)generalAllowed++;if(!useGeneral)localRetained++;
 }
}
console.log(JSON.stringify({sessions:40,checks:count,blockedGeneral:blocked,allowedGeneral:generalAllowed,retainedLocal:localRetained,status:'PASS'}));
