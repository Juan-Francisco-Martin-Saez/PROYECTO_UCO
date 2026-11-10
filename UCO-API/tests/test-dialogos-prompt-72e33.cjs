const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(require('path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
function extract(name){const start=source.indexOf('function '+name+'(');assert(start>=0,name);let i=source.indexOf('{',start),depth=0;for(let j=i;j<source.length;j++){if(source[j]==='{')depth++;if(source[j]==='}'&&!--depth)return source.slice(start,j+1)}throw Error(name)}
const ctx={lastOwnOrderId:null,provisionalBudget:null,pendingCartTransfer:null,lastCartReference:null,catalog:null,isOwnDocumentQuery:(q)=>/\b(mi factura|mi justificante)\b/i.test(q),orderQuestion:(q)=>/\b(mi pedido|mis pedidos)\b/i.test(q),readCatalogContext:()=>ctx.catalog};
vm.createContext(ctx);for(const n of ['normalizeUco','isFollowup','contextualRoute','shouldUseGeneralEngine72e22'])vm.runInContext(extract(n),ctx);
const cases=[
['¿Qué es una API?',true],['¿Qué significa IV gama?',true],['¿Cómo funciona la congelación rápida?',true],['¿Cuál es la diferencia entre congelado y ultracongelado?',true],['Explica la cadena de frío',true],['¿Quién fue Louis Pasteur?',true],['¿Dónde está París?',true],['hola',false],['añade 3 cajas de croquetas',false],['¿Cuánto cuesta la patata?',false],['Dime la referencia y el precio del producto X',false],['¿Dónde está mi pedido?',false],['¿Puedes confirmar que ya he pagado?',false],['No encuentro el producto Y',false],['¿Qué es el catálogo?',false],['¿Qué es mi cuenta?',false],['¿Qué es una factura?',false],['¿Qué es el stock?',false],['¿Qué es un pedido?',false],['¿Qué es una oferta?',false]
];let pass=0;for(const [q,expected] of cases){const actual=ctx.shouldUseGeneralEngine72e22(q);assert.strictEqual(actual,expected,q);pass++}
const turns=[
{label:'general -> comercial -> general',steps:[['¿Qué es una API?',true],['¿Cuánto cuesta la patata?',false],['¿Qué significa IV gama?',true]]},
{label:'catálogo activo',state:{catalog:{term:'patata',at:Date.now()}},steps:[['¿Qué es una API?',true],['¿Cuánto cuesta?',false]]},
{label:'pedido activo',state:{lastOwnOrderId:12},steps:[['¿Qué es una API?',false],['¿Dónde está mi pedido?',false]]},
{label:'presupuesto activo',state:{provisionalBudget:{}},steps:[['¿Qué es una API?',false]]},
{label:'carrito pendiente',state:{pendingCartTransfer:{}},steps:[['¿Qué es una API?',false]]},
{label:'referencia carrito',state:{lastCartReference:'ABC'},steps:[['¿Qué es una API?',false]]},
{label:'reinicio de contexto',state:{catalog:{term:'patata',at:Date.now()},lastOwnOrderId:2},steps:[['¿Qué es una API?',false]],reset:true},
];for(const t of turns){Object.assign(ctx,{catalog:null,lastOwnOrderId:null,provisionalBudget:null,pendingCartTransfer:null,lastCartReference:null},t.state||{});for(const [q,expected] of t.steps){assert.strictEqual(ctx.shouldUseGeneralEngine72e22(q),expected,t.label+': '+q);pass++}if(t.reset){Object.assign(ctx,{catalog:null,lastOwnOrderId:null,provisionalBudget:null,pendingCartTransfer:null,lastCartReference:null});assert.strictEqual(ctx.shouldUseGeneralEngine72e22('¿Qué es una API?'),true);pass++}}
console.log(`${pass}/${pass} casos de enrutamiento con funciones reales extraídas del JS. Sin llamadas a OpenAI.`);
