const fs=require('fs'),vm=require('vm'),assert=require('assert');
const src=fs.readFileSync(require('path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
const a=src.indexOf('function shouldUseGeneralEngine72e22(value){'),b=src.indexOf("\nform.addEventListener('submit'",a);
assert(a>=0&&b>a,'Router function exists');
let catalog=null,order=null,budget=null,transfer=null,reference=null;
const normalizeUco=x=>x.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^\p{L}\p{N}\s¿?¡!:]/gu,' ').replace(/\s+/g,' ').trim();
const isFollowup=x=>/^(y|tambien|ademas|que tal|cuanto|cuantos|cuesta|valen|vale|precio|precios|cuales|tipos|variedades|las|los|esas|esos|estos|estas|tienen|hay|estan|disponibles|puedo pedir|quiero pedir)\b/.test(normalizeUco(x))&&normalizeUco(x).split(' ').length<=9;
const contextualRoute=x=>/\b(producto|productos|patata|patatas|croqueta|croquetas|tomate|tomates|catalogo|categoria|categorias|variedad|variedades)\b/.test(normalizeUco(x))?'catalog':/\b(pedido|pedidos|factura)\b/.test(normalizeUco(x))?'order':'general';
const ctx={normalizeUco,isFollowup,readCatalogContext:()=>catalog,contextualRoute,
get lastOwnOrderId(){return order},get provisionalBudget(){return budget},get pendingCartTransfer(){return transfer},get lastCartReference(){return reference}};
vm.createContext(ctx);vm.runInContext(src.slice(a,b),ctx);
const sessions=[
{name:'alternancia catálogo/general',steps:[
['¿Qué productos hay?',false,'patatas'],['¿Qué es una API?',true],['¿Y las otras?',false],
['Por cierto, ¿cómo funciona internet?',true],['¿Cuánto cuesta?',false],['Define biodiversidad',true],
['¿Qué productos hay?',false]]},
{name:'seguimiento y conector',steps:[
['¿Qué es una API?',true],['Y ¿qué es una API?',true],['Y las patatas?',false,'patatas'],
['¿Qué significa precio?',false],['Ahora explica biodiversidad',true],['¿Y las otras?',false]]},
{name:'pedido activo bloquea desvío',steps:[
['¿Qué es una API?',true],['¿Qué es una API?',false,undefined,'order'],
['¿Qué productos hay?',false],['¿Qué es una API?',true,undefined,'clearOrder']]},
{name:'presupuesto activo bloquea desvío',steps:[
['¿Qué es una API?',true],['¿Qué es una API?',false,undefined,'budget'],
['¿Cuánto cuesta?',false],['¿Qué es una API?',true,undefined,'clearBudget']]},
{name:'carrito activo bloquea desvío',steps:[
['¿Qué es una API?',true],['¿Qué es una API?',false,undefined,'cart'],
['¿Qué es una API?',true,undefined,'clearCart']]},
{name:'transferencia activa bloquea desvío',steps:[
['¿Qué es una API?',true],['¿Qué es una API?',false,undefined,'transfer'],
['¿Qué es una API?',true,undefined,'clearTransfer']]},
{name:'sin fuga de referencias',steps:[
['¿Qué es una API?',true],['¿Qué es mi pedido?',false],
['¿Qué significa precio?',false],['Define mi cuenta',false],
['¿Qué es una API?',true]]}
];
let count=0;
for(const session of sessions){
 catalog=null;order=null;budget=null;transfer=null;reference=null;
 for(const [question,expected,newCatalog,action] of session.steps){
 if(newCatalog!==undefined)catalog={term:newCatalog};
 if(action==='order')order=42;
 if(action==='clearOrder')order=null;
 if(action==='budget')budget={items:[{ref:'A'}]};
 if(action==='clearBudget')budget=null;
 if(action==='cart')reference='SKU123';
 if(action==='clearCart')reference=null;
 if(action==='transfer')transfer={items:[{ref:'A'}]};
 if(action==='clearTransfer')transfer=null;
 const before=JSON.stringify({catalog,order,budget,transfer,reference});
 const actual=ctx.shouldUseGeneralEngine72e22(question);
 assert.strictEqual(actual,expected,session.name+' :: '+question);
 assert.strictEqual(JSON.stringify({catalog,order,budget,transfer,reference}),before,'Router must not mutate state');
 count++;
 }
}
console.log(JSON.stringify({passed:count,total:count,sessions:sessions.length,status:'OK'}));
