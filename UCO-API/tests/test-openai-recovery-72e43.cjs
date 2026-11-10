const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(require('path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
const start=source.indexOf('function isSafeGeneralQuestion72e39(question){');
const end=source.indexOf('// 7.2E.22: derivación automática',start);
assert(start>=0&&end>start,'No se encontró el código real de protección y consulta');
const script=source.slice(start,end);
let passes=0;
async function check(label,fetchMock,expected,opts={}){
 let calls=0,clears=0,aborts=0,lastRequest;
 class AbortControllerMock{constructor(){this.signal={};}abort(){aborts++}}
 const context={normalizeUco:s=>s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''),AbortController:AbortControllerMock,
 setTimeout:()=>123,clearTimeout:()=>{clears++},fetch:async(url,request)=>{calls++;lastRequest={url,request};return fetchMock(url,request)}};
 vm.createContext(context);vm.runInContext(script,context);
 const result=await context.askProtectedOpenAI(opts.question||'¿Qué es la fotosíntesis?');
 assert.strictEqual(result,expected,label);assert.strictEqual(calls,opts.calls??1,label+' llamadas');
 assert.strictEqual(clears,opts.clears??1,label+' limpieza');
 if(calls){assert.strictEqual(lastRequest.url,'/api/assistant-openai.php');assert.strictEqual(lastRequest.request.credentials,'same-origin');assert.strictEqual(lastRequest.request.method,'POST');assert.strictEqual(JSON.parse(lastRequest.request.body).message,opts.question||'¿Qué es la fotosíntesis?')}
 if(opts.aborts!==undefined)assert.strictEqual(aborts,opts.aborts,label+' abortos');
 passes++;console.log('OK',label);
}
(async()=>{
 await check('200 respuesta válida',async()=>({ok:true,json:async()=>({ok:true,response:'Una explicación.'})}),'Una explicación.');
 for(const status of [400,401,403,404,408,422,429,500,502,503,504])await check('HTTP '+status,async()=>({ok:false,status}),null);
 await check('200 ok=false',async()=>({ok:true,json:async()=>({ok:false,response:'No'})}),null);
 await check('200 respuesta ausente',async()=>({ok:true,json:async()=>({ok:true})}),null);
 await check('200 respuesta numérica',async()=>({ok:true,json:async()=>({ok:true,response:123})}),null);
 await check('200 JSON inválido',async()=>({ok:true,json:async()=>{throw Error('JSON')} }),null);
 await check('Fallo de red',async()=>{throw Error('network')},null);
 await check('Aborto/timeout',async()=>{const e=Error('aborted');e.name='AbortError';throw e},null);
 for(const question of ['Mi pedido 123','IA: mi factura','ES9121000418450200051332','cliente@example.com','sk-proj-abcdefghijklmnop'])await check('Dato sensible '+question,async()=>{throw Error('No debe llamarse')},null,{question,calls:0,clears:0});
 console.log('TOTAL',passes+'/'+passes,'(motor real de consulta, red simulada)');
})().catch(e=>{console.error(e);process.exit(1)});
