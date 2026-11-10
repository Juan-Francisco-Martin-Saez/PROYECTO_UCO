const fs=require('fs'),vm=require('vm'),assert=require('assert');
const src=fs.readFileSync(require('path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
const a=src.indexOf('function shouldUseGeneralEngine72e22(value){'),b=src.indexOf("\nform.addEventListener('submit'",a);
assert(a>=0&&b>a);
let catalog=null,order=null,budget=null,transfer=null,reference=null;
const norm=x=>x.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^\p{L}\p{N}\s¿?¡!:]/gu,' ').replace(/\s+/g,' ').trim();
const follow=x=>/^(y|tambien|ademas|que tal|cuanto|cuantos|cuesta|valen|vale|precio|precios|cuales|tipos|variedades|las|los|esas|esos|estos|estas|tienen|hay|estan|disponibles|puedo pedir|quiero pedir)\b/.test(norm(x))&&norm(x).split(' ').length<=9;
const route=x=>/\b(producto|productos|patata|patatas|croqueta|croquetas|tomate|tomates|catalogo|categoria|categorias|variedad|variedades)\b/.test(norm(x))?'catalog':/\b(pedido|pedidos|factura)\b/.test(norm(x))?'order':'general';
const ctx={normalizeUco:norm,isFollowup:follow,readCatalogContext:()=>catalog,contextualRoute:route,
get lastOwnOrderId(){return order},get provisionalBudget(){return budget},get pendingCartTransfer(){return transfer},get lastCartReference(){return reference}};
vm.createContext(ctx);vm.runInContext(src.slice(a,b),ctx);
const cases=[
['general sin contexto','¿Qué es una API?',null,null,null,null,null,true],
['general con catálogo','¿Qué es una API?',{term:'patatas'},null,null,null,null,true],
['productos','¿Qué productos hay?',{term:'patatas'},null,null,null,null,false],
['seguimiento precio','¿Cuánto cuesta?',{term:'patatas'},null,null,null,null,false],
['pedido personal','¿Qué es mi pedido?',{term:'patatas'},null,null,null,null,false],
['pedido activo','¿Qué es una API?',null,42,null,null,null,false],
['presupuesto activo','¿Qué es una API?',null,null,{items:[]},null,null,false],
['transferencia activa','¿Qué es una API?',null,null,null,{items:[]},null,false],
['referencia carrito','¿Qué es una API?',null,null,null,null,'REF1',false],
['general tras limpiar','¿Cómo funciona internet?',null,null,null,null,null,true],
['comercial tras general','¿Qué es el catálogo?',null,null,null,null,null,false],
['seguimiento','¿Y las otras?',{term:'patatas'},null,null,null,null,false],
['precio','¿Qué significa precio?',{term:'patatas'},null,null,null,null,false],
['saludo','Hola',null,null,null,null,null,false],
['definición','Define biodiversidad',{term:'tomate'},null,null,null,null,true],
['cuenta','Define mi cuenta',{term:'tomate'},null,null,null,null,false],
['teléfono','¿Qué es un teléfono?',null,null,null,null,null,false],
['prepedido vacío','¿Por qué llueve?',null,null,{items:[]},null,null,false],
['cambio de tema','Y ¿qué es una API?',{term:'patatas'},null,null,null,null,true],
['por cierto','Por cierto, ¿cómo funciona internet?',{term:'patatas'},null,null,null,null,true],
['ahora general','Ahora explica biodiversidad',{term:'patatas'},null,null,null,null,true],
['seguimiento elíptico','Y las patatas?',{term:'patatas'},null,null,null,null,false],
['pregunta comercial con conector','Y ¿qué productos hay?',{term:'patatas'},null,null,null,null,false],
['pedido con conector','Por cierto, ¿qué es mi pedido?',{term:'patatas'},null,null,null,null,false],
['general con carrito activo','Y ¿qué es una API?',null,null,null,null,'REF1',false]
];
for(const [name,q,c,o,b,t,r,expected] of cases){
catalog=c;order=o;budget=b;transfer=t;reference=r;
assert.strictEqual(ctx.shouldUseGeneralEngine72e22(q),expected,name)}
console.log(`${cases.length}/${cases.length} pruebas de cambio de contexto superadas`);
