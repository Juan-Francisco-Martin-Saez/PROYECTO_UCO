const fs=require('fs'),path=require('path'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(path.join(__dirname,'../public/assistant/assistant.js'),'utf8');
const p=source.indexOf('function isSafeGeneralQuestion72e39(question){'),e=source.indexOf('// 7.2E.22: derivación automática',p);
const a=source.indexOf("form.addEventListener('submit',async e=>{"),b=source.indexOf("\ndocument.addEventListener('keydown'",a);
assert(p>=0&&e>p&&a>=0&&b>a);
let handler,question='',busy=false,output=[],calls=[],commercial=[],count=0,cleared=0,aborted=0;
let responseMode='503',order=null,budget=null,pending=null,cart=null;
const input={get value(){return question},set value(v){question=v}};
const button={disabled:false};
const context={normalizeUco:s=>s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''),
 AbortController:class{constructor(){this.signal={}}abort(){aborted++}},setTimeout:()=>1,clearTimeout:()=>{cleared++},
 fetch:async(url,opts)=>{calls.push({url,opts});if(responseMode==='network')throw Error('offline');if(responseMode==='timeout')throw Error('aborted');if(responseMode==='badjson')return {ok:true,json:async()=>{throw Error('json')}};if(responseMode==='success')return {ok:true,json:async()=>({ok:true,response:'Respuesta recuperada'})};return {ok:false,status:Number(responseMode)}},
 form:{addEventListener:(event,fn)=>{if(event==='submit')handler=fn},querySelector:()=>button},input,
 get busy(){return busy},set busy(v){busy=v},append:(role,msg)=>output.push([role,msg]),area:'client',
 isReset:v=>v==='reiniciar',clearCatalogContext:()=>{},
 shouldUseGeneralEngine72e22:v=>/^¿?qué es|^¿?quién fue/i.test(v)&&order===null&&budget===null&&pending===null&&cart===null,
 parseBudgetCommand:()=>null,cartContextCommand:()=>null,updateProvisionalBudget:async()=>null,
 isComparisonFollowup:()=>false,readCatalogContext:()=>null,isFollowup:()=>false,
 contextualRoute:v=>/pedido/i.test(v)?'order':'catalog',askMultiQuantityEstimate:async()=>null,askQuantityEstimate:async()=>null,
 askOwnDocuments:async()=>null,askOwnOrders:async q=>{commercial.push(['order',q]);return 'Pedido local'},
 askComparedPrices:async()=>null,guideFromQuestion:()=>null,askUcoLogic:async q=>{commercial.push(['catalog',q]);return 'Catálogo local'},
 get lastOwnOrderId(){return order},set lastOwnOrderId(v){order=v},get provisionalBudget(){return budget},set provisionalBudget(v){budget=v},
 get pendingCartTransfer(){return pending},set pendingCartTransfer(v){pending=v},get lastCartReference(){return cart},set lastCartReference(v){cart=v},lastComparedProducts:null};
vm.createContext(context);vm.runInContext(source.slice(p,e)+'\n'+source.slice(a,b),context);assert(handler);
async function send(q){question=q;const before=output.length;await handler({preventDefault(){}});assert.equal(output.length,before+2);assert.equal(output.at(-2)[0],'user');assert.equal(output.at(-1)[0],'bot');assert.equal(button.disabled,false);assert.equal(busy,false);assert.equal(question,'');count++;return output.at(-1)[1]}
(async()=>{
 for(let i=0;i<60;i++){
  responseMode=['503','429','500','network','timeout','badjson'][i%6];
  const before=calls.length;
  assert.equal(await send('¿Qué es la fotosíntesis?'),'Ahora mismo no puedo responder esa consulta general. Puedes intentarlo de nuevo más tarde.');
  assert.equal(calls.length,before+1);
  assert.equal(await send('tomates'),'Catálogo local');assert.equal(await send('mi pedido'),'Pedido local');
  assert.equal(calls.length,before+1,'Las consultas locales no invocan OpenAI');
 }
 responseMode='success';assert.equal(await send('¿Qué es la fotosíntesis?'),'Respuesta recuperada');
 assert.equal(await send('tomates'),'Catálogo local');
 for(const [name,q] of [['pedido','Mi pedido 123'],['email','correo test@example.com'],['token','sk-proj-abcdefghijklmnop'],['iban','ES9121000418450200051332']]){
  const before=calls.length;assert.equal(await send('IA: '+q),'No puedo consultar OpenAI ahora. El asistente local sigue disponible sin cambios.',name);assert.equal(calls.length,before,name+' no llega a red');
 }
 for(const state of ['order','budget','pending','cart']){
  if(state==='order')order=123;if(state==='budget')budget={};if(state==='pending')pending={};if(state==='cart')cart='X';
  const before=calls.length;assert.equal(await send('¿Qué es la fotosíntesis?'),'Catálogo local');assert.equal(calls.length,before,'Contexto '+state+' no se deriva');order=budget=pending=cart=null;
 }
 assert.equal(await send('reiniciar'),'He borrado el contexto de esta conversación. ¿Qué necesitas consultar?');
 assert.equal(await send('¿Qué es la fotosíntesis?'),'Respuesta recuperada');
 assert.equal(calls.length,62);assert.equal(cleared,62,'Cada solicitud a OpenAI limpia su temporizador');
 assert.equal(commercial.length,125,'Las consultas locales siguen operativas');
 console.log(JSON.stringify({passed:count,requestsOpenAI:calls.length,localOperations:commercial.length,consecutiveFailures:60,privacyBlocks:4,contextGuards:4,timerCleanups:cleared,productionChanged:false},null,2));
})().catch(e=>{console.error(e);process.exit(1)});
