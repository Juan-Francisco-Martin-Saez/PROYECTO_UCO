const fs=require('fs'),vm=require('vm'),path=require('path');
const src=fs.readFileSync(path.join(__dirname,'../public/assistant/assistant.js'),'utf8');
function extract(name){let s=src.indexOf('function '+name+'(');if(s<0)throw Error(name);let b=src.indexOf('{',s),d=0,q=null,esc=false;for(let i=b;i<src.length;i++){let c=src[i];if(q){if(esc){esc=false;continue}if(c==='\\'){esc=true;continue}if(c===q)q=null;continue}if('"\'`'.includes(c)){q=c;continue}if(c==='{')d++;if(c==='}'&&!--d)return src.slice(s,i+1)}throw Error('unclosed '+name)}
const store=new Map();let order=null;const ctx={sessionStorage:{getItem:k=>store.get(k)||null},CTX_KEY:'uco_assistant_catalog_context_71g',CTX_TTL:1800000,Date,Number,JSON,console,get lastOwnOrderId(){return order}};
vm.createContext(ctx);vm.runInContext(['normalizeUco','readCatalogContext','parseOrderId','orderQuestion','isOwnDocumentQuery','contextualRoute'].map(extract).join('\n'),ctx);
const cases=[
['estado de mi pedido','¿Cuál es el estado de mi pedido?','order',44],
['seguimiento el estado','¿Y el estado?','order',44],
['seguimiento el importe','¿Y el importe?','order',44],
['seguimiento la fecha','¿Y la fecha?','order',44],
['seguimiento el total','¿Y el total?','order',44],
['seguimiento cuánto cuesta','¿Cuánto cuesta?','order',44],
['seguimiento cuánto lleva','¿Cuánto lleva?','order',44],
['seguimiento qué contiene','¿Qué contiene?','order',44],
['seguimiento qué productos tiene','¿Qué productos tiene?','order',44],
['seguimiento de ese pedido','¿Y ese pedido?','order',44],
['seguimiento entrega','¿Cuándo llega mi pedido?','order',44],
['seguimiento número','¿Qué número tiene mi pedido?','order',44],
['consulta pedido directo','¿Qué contiene mi pedido?','order',44],
['consulta pedido número','¿Qué contiene el pedido 44?','order',44],
['pedido con precio','¿Qué precio tiene mi pedido?','order',44],
['consulta catálogo explícita','Muéstrame el catálogo','catalog',44],
['patatas explícitas','¿Qué patatas tenéis?','catalog',44],
['croquetas explícitas','¿Cuánto cuestan las croquetas?','catalog',44],
['precio producto','¿Cuál es el precio de las patatas?','catalog',44],
['productos de pedido','¿Qué productos tiene mi pedido?','order',44],
['artículos de pedido','¿Qué artículos contiene mi pedido?','order',44],
['precio pedido','¿Cuánto cuesta mi pedido?','order',44],
['justificante','Quiero el justificante de mi pedido','document',44],
['factura de pedido','Ver factura de mi pedido','document',44],
['pregunta general','¿Qué es una API?','general',44],
['pdf educativo','¿Qué es un PDF?','general',44],
['precio de equilibrio','¿Qué es el precio de equilibrio?','general',44],
['sin pedido precio genérico','¿Cuánto cuesta?','general',null],
['sin pedido productos','¿Qué tomates hay?','catalog',null],
['sin pedido estado explícito','¿Cuál es el estado de mi pedido?','order',null],
['sin pedido fecha elíptica','¿Y la fecha?','general',null],
['sin pedido importe elíptico','¿Y el importe?','general',null],
];
let passed=0;let failures=[];for(const [label,q,expected,id] of cases){order=id;store.clear();const actual=ctx.contextualRoute(q);if(actual===expected)passed++;else failures.push({label,q,expected,actual});}
console.log(JSON.stringify({passed,total:cases.length,failures},null,2));if(failures.length)process.exitCode=1;
