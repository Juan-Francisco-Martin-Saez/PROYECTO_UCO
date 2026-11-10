const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(require('path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
const match=source.match(/function shouldUseGeneralEngine72e22\(value\)\{[\s\S]*?\n\}/);
assert(match,'Missing auto-router');
const state={catalog:null,order:null,budget:null,transfer:null,cart:null,follow:false};
const sandbox={
 normalizeUco:v=>v.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''),
 isFollowup:()=>state.follow,readCatalogContext:()=>state.catalog,
 get lastOwnOrderId(){return state.order},get provisionalBudget(){return state.budget},
 get pendingCartTransfer(){return state.transfer},get lastCartReference(){return state.cart},
 contextualRoute:v=>/\b(cebolla|patata|producto|precio|pedido|comprar|catalogo)\b/i.test(v)?'catalog':'general'
};
vm.createContext(sandbox);vm.runInContext(match[0],sandbox);
const route=q=>sandbox.shouldUseGeneralEngine72e22(q)?'openai':'local';
const steps=[
 ['¿Qué es la fotosíntesis?','openai'],
 ['¿Cuánto cuesta la cebolla?','local'],
 ['¿Cómo funciona una API REST?','openai'],
 ['¿Qué es el catálogo?','local'],
 ['Quiero comprar patatas','local'],
 ['IA: explícito','local'], // El prefijo explícito lo atiende un bloque anterior
];
for(const [q,expected] of steps)assert.strictEqual(route(q),expected,q);
state.catalog={term:'cebolla'};
assert.strictEqual(route('¿Qué es la fotosíntesis?'),'openai','pregunta general explícita con contexto catálogo');
state.catalog=null;state.order=123;
assert.strictEqual(route('¿Cómo funciona una API REST?'),'local','contexto pedido');
state.order=null;state.budget={};
assert.strictEqual(route('¿Qué es la gravedad?'),'local','presupuesto');
state.budget=null;state.transfer={};
assert.strictEqual(route('¿Qué es la gravedad?'),'local','transferencia');
state.transfer=null;state.cart='SKU-1';
assert.strictEqual(route('¿Qué es la gravedad?'),'local','carrito');
state.cart=null;state.follow=true;
assert.strictEqual(route('¿Qué es la gravedad?'),'local','seguimiento marcado por clasificador');
state.follow=false;
assert.strictEqual(route('¿Qué es la gravedad?'),'openai','retorno a general');
assert(source.indexOf('if(/^ia\\s*:/i.test(value))')<source.indexOf('if(shouldUseGeneralEngine72e22(value))'),'prefijo IA preservado');
assert(source.includes("if(answer!==null){append('bot',answer);return;}"),'salida temprana');
assert(source.includes("}finally{busy=false;if(submit)submit.disabled=false}"),'restablecimiento tras respuesta');
console.log('16/16 escenarios y guardas estáticas correctos; sin HTTP ni simulación DOM completa');
