const fs=require('fs'),vm=require('vm'),path=require('path');
const src=fs.readFileSync(path.join(__dirname,'../public/assistant/assistant.js'),'utf8');
function extract(name){let s=src.indexOf('function '+name+'(');if(s<0)throw Error(name);let b=src.indexOf('{',s),d=0,q=null,esc=false;for(let i=b;i<src.length;i++){let c=src[i];if(q){if(esc){esc=false;continue}if(c==='\\'){esc=true;continue}if(c===q)q=null;continue}if('"\'`'.includes(c)){q=c;continue}if(c==='{')d++;if(c==='}'&&!--d)return src.slice(s,i+1)}throw Error('unclosed '+name)}
const store=new Map();let order=null;
const ctx={sessionStorage:{getItem:k=>store.get(k)||null},CTX_KEY:'uco_assistant_catalog_context_71g',CTX_TTL:1800000,Date,Number,JSON,console,get lastOwnOrderId(){return order}};
vm.createContext(ctx);vm.runInContext(['normalizeUco','readCatalogContext','parseOrderId','orderQuestion','isOwnDocumentQuery','contextualRoute'].map(extract).join('\n'),ctx);
const cases=[
['pedido explícito','¿Cuál es el estado de mi pedido?', 'order',44],
['seguimiento estado','¿Y el estado?', 'order',44],
['seguimiento importe','¿Y el importe?', 'order',44],
['producto explícito','¿Qué patatas tenéis?', 'catalog',44],
['catálogo explícito','Muéstrame el catálogo', 'catalog',44],
['precio producto explícito','¿Cuánto cuestan las croquetas?', 'catalog',44],
['producto y pedido explícitos','¿Qué productos tiene mi pedido?', 'order',44],
['pedido con productos','¿Qué productos contiene el pedido 44?', 'order',44],
['precio de pedido','¿Cuánto cuesta mi pedido?', 'order',44],
['consulta catálogo sin pedido','¿Qué tomates hay?', 'catalog',null],
['consulta general','¿Qué es una API?', 'general',44],
['consulta PDF educativa','¿Qué es un PDF?', 'general',44],
['solicitar justificante','Ver justificante de mi pedido', 'document',44],
['pedido sin contexto','¿Cuál es el estado de mi pedido?', 'order',null],
];
let passed=0;const failures=[];
for(const [label,q,expected,id] of cases){order=id;store.clear();const actual=ctx.contextualRoute(q);if(actual===expected)passed++;else failures.push({label,q,expected,actual});}
console.log(JSON.stringify({passed,total:cases.length,failures},null,2));if(failures.length)process.exitCode=1;
