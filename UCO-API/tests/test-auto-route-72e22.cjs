const fs=require('fs'),vm=require('vm'),assert=require('assert');
const src=fs.readFileSync(require('path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
const match=src.match(/function shouldUseGeneralEngine72e22\(value\)\{[\s\S]*?\n\}/);
assert(match,'Falta función de enrutamiento');
let context=false,order=false;
const sandbox={
 normalizeUco:v=>v.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''),
 isFollowup:()=>false,readCatalogContext:()=>context?{term:'cebolla'}:null,
 lastOwnOrderId:null,provisionalBudget:null,pendingCartTransfer:null,lastCartReference:null,
 contextualRoute:v=>/\b(patata|cebolla|precio|producto|pedido|comprar)\b/i.test(v)?'catalog':'general'
};
vm.createContext(sandbox);vm.runInContext(match[0],sandbox);
const cases=[
 ['Qué es la fotosíntesis',true],['Cómo funciona una API REST',true],
 ['Explica la gravedad',true],['Hola',false],['¿Cuánto cuesta la cebolla?',false],
 ['Qué es mi pedido',false],['Qué es el precio del producto',false],
 ['Qué es UCO',false],['Quiero comprar',false],['Mi dirección de entrega',false]
];
for(const [q,expected] of cases)assert.strictEqual(sandbox.shouldUseGeneralEngine72e22(q),expected,q);
context=true;assert.strictEqual(sandbox.shouldUseGeneralEngine72e22('Qué es la fotosíntesis'),true,'contexto comercial: consulta general independiente');
assert(src.includes("const answer=await askProtectedOpenAI(value);"),'No se usa API protegida');
assert(src.includes("if(answer!==null){append('bot',answer);return;}"),'No hay retorno protegido');
console.log('13/13 validaciones de enrutamiento (11 escenarios + 2 verificaciones de integración) OK');
