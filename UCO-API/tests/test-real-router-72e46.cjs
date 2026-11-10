const fs=require('fs'),vm=require('vm'),assert=require('assert'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../public/assistant/assistant.js'),'utf8');
function extract(name){const start=source.indexOf('function '+name+'(');assert(start>=0,name);let open=source.indexOf('{',start),depth=0,quote=null,escape=false;for(let i=open;i<source.length;i++){const c=source[i];if(quote){if(escape){escape=false;continue}if(c==='\\'){escape=true;continue}if(c===quote)quote=null;continue}if(c==='"'||c==="'"||c==='`'){quote=c;continue}if(c==='{')depth++;if(c==='}'&&!--depth)return source.slice(start,i+1);if(c==='}'&&depth<0)throw Error('braces')}throw Error(name+' not closed')}
const names=['normalizeUco','readCatalogContext','isFollowup','parseOrderId','orderQuestion','isOwnDocumentQuery','contextualRoute','isSafeGeneralQuestion72e39','shouldUseGeneralEngine72e22'];
const store=new Map();let order=null,budget=null,transfer=null,ref=null;
const context={sessionStorage:{getItem:k=>store.get(k)||null,setItem:(k,v)=>store.set(k,v),removeItem:k=>store.delete(k)},CTX_KEY:'uco_assistant_catalog_context_71g',CTX_TTL:1800000,Date,Number,JSON,console,
 get lastOwnOrderId(){return order},get provisionalBudget(){return budget},get pendingCartTransfer(){return transfer},get lastCartReference(){return ref}};
vm.createContext(context);vm.runInContext(names.map(extract).join('\n'),context);
let count=0;
function check(label,q,expected,opts={}){order=opts.order??null;budget=opts.budget??null;transfer=opts.transfer??null;ref=opts.ref??null;store.clear();if(opts.catalog)store.set(context.CTX_KEY,JSON.stringify({term:opts.catalog,at:Date.now()}));
 const route=context.contextualRoute(q),actual=context.shouldUseGeneralEngine72e22(q),safe=context.isSafeGeneralQuestion72e39(q);
 assert.strictEqual(actual,expected,label+' route='+route);if(opts.route)assert.strictEqual(route,opts.route,label+' contextualRoute');if(opts.safe!==undefined)assert.strictEqual(safe,opts.safe,label+' privacy');count++;}
const cases=[
['API','¿Qué es una API?',true,{route:'general'}],['API con catálogo','¿Qué es una API?',true,{catalog:'patatas'}],
['fotosíntesis','¿Qué es la fotosíntesis?',true],['define','Define biodiversidad',true],['internet','¿Cómo funciona internet?',true],
['conector y','Y ¿qué es una API?',true,{catalog:'tomates'}],['conector ahora','Ahora explica la gravedad',true,{catalog:'tomates'}],
['conector por cierto','Por cierto, ¿qué es una API?',true,{catalog:'patatas'}],['conector cambio','Cambiando de tema, ¿qué es una API?',true,{catalog:'patatas'}],
['catalogo','¿Qué productos hay?',false,{route:'catalog'}],['patatas','¿Qué patatas hay?',false,{route:'catalog'}],
['croquetas','¿Qué croquetas tenéis?',false,{route:'catalog'}],['precio seguimiento','¿Cuánto cuesta?',false,{catalog:'patatas',route:'catalog'}],
['precio solo','¿Cuánto cuesta?',false],['seguimiento y','¿Y las otras?',false,{catalog:'patatas'}],
['seguimiento eliptico','¿Y los tomates?',false,{catalog:'patatas',route:'catalog'}],
['seguimiento disponibilidad','¿Hay disponibles?',false,{catalog:'patatas'}],
['pedido propio','¿Cuál es el estado de mi pedido?',false,{route:'order'}],
['historial','Muéstrame mis pedidos',false,{route:'order'}],
['pedido activo','¿Qué es una API?',false,{order:44}],['presupuesto activo','¿Qué es una API?',false,{budget:{items:[]}}],
['transferencia activa','¿Qué es una API?',false,{transfer:{items:[]}}],['carrito ref','¿Qué es una API?',false,{ref:'REF1'}],
['documento','Ver justificante de mi pedido',false,{route:'document'}],
['factura','¿Qué es mi factura?',false,{safe:false}],['email','¿Qué es correo juan@example.com?',false,{safe:false}],
['dni','¿Qué es 12345678Z?',true,{safe:false}],['iban','¿Qué es ES9121000418450200051332?',true,{safe:false}],
['telefono','¿Qué es 612345678?',true,{safe:false}],['token','¿Qué es sk-proj-abcdefghijklmnop?',true,{safe:false}],
['saludo','Hola',false],['gracias','Gracias',false],['comando','Busca algo',false],
['pedido en pregunta','¿Qué es un pedido?',false],['sku','¿Qué significa SKU?',false,{safe:false}],
['referencia','¿Qué significa referencia?',false,{safe:false}],
['cliente','¿Qué es un cliente?',false,{safe:false}],['tarjeta','¿Qué es una tarjeta?',true,{safe:false}],
['nuevo tema con pedido','Por cierto, ¿qué es una API?',false,{order:10}],
['catalogo con conector','Y ¿qué productos hay?',false,{catalog:'tomates',route:'catalog'}],
['documento PDF','Ver PDF de mi pedido',false,{route:'document'}],
['catalogo caducado','¿Qué es una API?',true,{catalog:'tomates'}]
];
for(const x of cases)check(...x);
// Distinción deliberada: enrutamiento y protección de privacidad son filtros separados.
console.log('REAL ROUTER',count+'/'+cases.length,'con funciones reales; servicios externos sin conectar');
