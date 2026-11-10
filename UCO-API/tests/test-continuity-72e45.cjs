const fs=require('fs'),vm=require('vm'),assert=require('assert');
const src=fs.readFileSync(require('path').join(__dirname,'../public/assistant/assistant.js'),'utf8');
const a=src.indexOf("form.addEventListener('submit',async e=>{");
const b=src.indexOf("\ndocument.addEventListener('keydown'",a);
assert(a>=0&&b>a,'submit handler no encontrado');
let handler, output=[], aiCalls=[], commercialCalls=[], currentInput='', busy=false;
let order=null, budget=null, pending=null, cart=null, catalog={term:'tomates'};
const input={get value(){return currentInput},set value(v){currentInput=v}};
const button={disabled:false};
const form={addEventListener:(name,fn)=>{if(name==='submit')handler=fn},querySelector:()=>button};
const context={get busy(){return busy},set busy(v){busy=v},form,input,area:'client',append:(who,msg)=>output.push([who,msg]),
 askProtectedOpenAI:async q=>{aiCalls.push(q);return aiResult(q)},
 isReset:q=>q==='reiniciar',clearCatalogContext:()=>{catalog=null},
 shouldUseGeneralEngine72e22:q=>/^¿?qué es|^¿?quién fue/i.test(q)&&order===null&&budget===null&&pending===null&&cart===null,
 parseBudgetCommand:()=>null,cartContextCommand:()=>null,updateProvisionalBudget:async()=>null,
 isComparisonFollowup:()=>false,readCatalogContext:()=>catalog,isFollowup:()=>false,
 contextualRoute:q=>/pedido/i.test(q)?'order':'catalog',
 askMultiQuantityEstimate:async()=>null,askQuantityEstimate:async()=>null,
 askOwnDocuments:async()=>null,askOwnOrders:async q=>{commercialCalls.push(['order',q]);return 'Pedido atendido'},
 askComparedPrices:async()=>null,guideFromQuestion:()=>null,
 askUcoLogic:async q=>{commercialCalls.push(['catalog',q]);return 'Catálogo atendido'},
 get lastOwnOrderId(){return order},set lastOwnOrderId(v){order=v},
 get provisionalBudget(){return budget},set provisionalBudget(v){budget=v},
 get pendingCartTransfer(){return pending},set pendingCartTransfer(v){pending=v},
 get lastCartReference(){return cart},set lastCartReference(v){cart=v},
 lastComparedProducts:null};
let aiResult=async()=>null;
vm.createContext(context);vm.runInContext(src.slice(a,b),context);assert(handler);
async function send(q){currentInput=q;const old=output.length;await handler({preventDefault(){}});assert.strictEqual(currentInput,'');assert.strictEqual(button.disabled,false);assert.strictEqual(output.length,old+2);assert.strictEqual(output[old][0],'user');assert.strictEqual(output[old+1][0],'bot');return output.at(-1)[1]}
let checks=0;function eq(actual,expected,label){assert.strictEqual(actual,expected,label);checks++}
(async()=>{
 for(const [name,fn] of [['HTTP 503',async()=>null],['timeout',async()=>null],['error de red',async()=>null],['JSON inválido',async()=>null],['HTTP 429',async()=>null]]){
  aiResult=fn;let before=commercialCalls.length;eq(await send('¿Qué es una API?'),'Ahora mismo no puedo responder esa consulta general. Puedes intentarlo de nuevo más tarde.',name);eq(commercialCalls.length,before,name+' no inventa catálogo');
  eq(await send('tomates'),'Catálogo atendido',name+' recuperación comercial');
  eq(await send('mi pedido'),'Pedido atendido',name+' recuperación pedidos');
 }
 aiResult=async()=> 'Respuesta general';eq(await send('¿Qué es una API?'),'Respuesta general','OpenAI se recupera');
 eq(await send('tomates'),'Catálogo atendido','catálogo después de recuperación');
 for(const state of ['order','budget','pending','cart']){
  if(state==='order')order=10;if(state==='budget')budget={};if(state==='pending')pending={};if(state==='cart')cart='ABC';
  const before=aiCalls.length;eq(await send('¿Qué es una API?'),'Catálogo atendido','estado protegido '+state);eq(aiCalls.length,before,'no deriva '+state);
  order=budget=pending=cart=null;
 }
 aiResult=async()=>null;const before=aiCalls.length;
 eq(await send('IA: ¿Qué es una API?'),'No puedo consultar OpenAI ahora. El asistente local sigue disponible sin cambios.','prefijo IA falla');
 eq(aiCalls.length,before+1,'IA llamada simulada');eq(await send('tomates'),'Catálogo atendido','local después de IA');
 eq(await send('reiniciar'),'He borrado el contexto de esta conversación. ¿Qué necesitas consultar?','reset');
 eq(catalog,null,'contexto limpiado');
 console.log('TOTAL '+checks+'/'+checks+' comprobaciones; submit real, dependencias externas simuladas');
})().catch(e=>{console.error(e);process.exit(1)});
