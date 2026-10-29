/* UCO 7.0C: interfaz local guiada; sin operaciones bancarias ni API de IA. */
(()=>{'use strict';
if(document.getElementById('uco-assistant-root'))return;
const area=document.currentScript?.dataset.area||'public', KEY='uco_assistant_v70', POS='uco_assistant_position_v70c', MAX=24;
const mobile=()=>matchMedia('(max-width:650px)').matches;
const read=(key,fallback)=>{try{return JSON.parse(localStorage.getItem(key))||fallback}catch{return fallback}};
let state={messages:[],closedAt:0,invitedAt:0};
try{const old=JSON.parse(sessionStorage.getItem(KEY)||'{}');if(old&&typeof old==='object')state={...state,...old}}catch{}
if(!Array.isArray(state.messages))state.messages=[];
const save=()=>{try{sessionStorage.setItem(KEY,JSON.stringify({...state,messages:state.messages.slice(-MAX)}))}catch{}};
const root=document.createElement('div');root.id='uco-assistant-root';root.dataset.area=area;
const launch=document.createElement('button');launch.type='button';launch.className='uco-ai-launch';launch.setAttribute('aria-label','Abrir asistente UCO');launch.setAttribute('aria-expanded','false');launch.setAttribute('aria-controls','uco-ai-panel');
const robotSVG=`<svg viewBox="0 0 220 260" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><g fill="#232f5d" stroke="#232f5d" stroke-linejoin="round"><path d="M110 28V13" fill="none" stroke-width="8" stroke-linecap="round"/><circle cx="110" cy="11" r="9" fill="#a9e13f" stroke="none"/><path d="M51 87H38V66M169 87h13V66" fill="none" stroke-width="7"/><circle cx="38" cy="65" r="6" fill="#a9e13f" stroke="none"/><circle cx="182" cy="65" r="6" fill="#a9e13f" stroke="none"/><rect x="43" y="35" width="134" height="108" rx="22"/><rect x="29" y="72" width="18" height="36" rx="9"/><rect x="173" y="72" width="18" height="36" rx="9"/><rect x="55" y="146" width="110" height="78" rx="7"/><path d="M55 157H37Q18 157 18 181v22M165 157h18q19 0 19 24v22" fill="none" stroke-width="19" stroke-linecap="round"/><path d="M18 201c-17 0-20 20-7 28v-15q7-8 14 0v15c13-8 10-28-7-28ZM202 201c-17 0-20 20-7 28v-15q7-8 14 0v15c13-8 10-28-7-28Z"/><path d="M76 224v25h-17v8h45v-8H92v-25M128 224v25h-12v8h45v-8h-17v-25"/></g><circle cx="82" cy="88" r="22" fill="#fff"/><circle cx="138" cy="88" r="22" fill="#fff"/><circle cx="82" cy="88" r="10" fill="#232f5d"/><circle cx="138" cy="88" r="10" fill="#232f5d"/><rect class="uco-ai-mouth" x="92" y="119" width="36" height="5" rx="2.5" fill="#a9e13f"/><path d="M55 146h110" stroke="#a9e13f" stroke-width="5"/><text x="110" y="192" fill="#fff" font-size="35" font-family="Arial Black,Arial,sans-serif" font-weight="900" text-anchor="middle" letter-spacing="-2">UCO</text><g fill="#a9e13f"><rect x="76" y="201" width="12" height="9"/><rect x="91" y="201" width="12" height="9"/><rect x="106" y="201" width="12" height="9"/><rect x="121" y="201" width="12" height="9"/></g></svg>`;
const smallRobot=document.createElement('span');smallRobot.className='uco-ai-launch-robot';smallRobot.innerHTML=robotSVG;
const launchText=document.createElement('span');launchText.className='uco-ai-launch-label';launchText.textContent='Asistente UCO';launch.append(smallRobot,launchText);
const panel=document.createElement('section');panel.id='uco-ai-panel';panel.className='uco-ai-panel';panel.hidden=true;panel.setAttribute('aria-label','Asistente UCO');
const head=document.createElement('div');head.className='uco-ai-head';head.title='Arrastrar para mover el asistente';
const title=document.createElement('strong');title.textContent=area==='admin'?'Asistente UCO · Administración':area==='client'?'Asistente UCO · Clientes':'Asistente UCO';
const mini=document.createElement('button');mini.type='button';mini.textContent='−';mini.className='uco-ai-minimize';mini.setAttribute('aria-label','Minimizar');
const close=document.createElement('button');close.type='button';close.textContent='×';close.className='uco-ai-close';close.setAttribute('aria-label','Cerrar');
head.append(title,mini,close);
const stage=document.createElement('div');stage.className='uco-ai-stage';const robot=document.createElement('div');robot.className='uco-ai-robot';robot.innerHTML=robotSVG;stage.append(robot);
const messages=document.createElement('div');messages.className='uco-ai-messages';messages.setAttribute('role','log');messages.setAttribute('aria-live','polite');
const actions=document.createElement('div');actions.className='uco-ai-actions';
const form=document.createElement('form');form.className='uco-ai-form';const input=document.createElement('input');input.type='text';input.maxLength=300;input.autocomplete='off';input.placeholder='Escribe tu consulta…';input.setAttribute('aria-label','Consulta');const send=document.createElement('button');send.type='submit';send.textContent='➤';send.setAttribute('aria-label','Enviar consulta');form.append(input,send);
const foot=document.createElement('div');foot.className='uco-ai-foot';foot.textContent='Consulta de catálogo · Sin pedidos ni pagos desde el chat';panel.append(head,stage,messages,actions,form,foot);
const hint=document.createElement('div');hint.className='uco-ai-hint';hint.hidden=true;const hintRobot=document.createElement('span');hintRobot.className='uco-ai-hint-robot';hintRobot.innerHTML=robotSVG;const hintText=document.createElement('span');hintText.className='uco-ai-hint-bubble';hintText.textContent='¿Te ayudo?';const hintClose=document.createElement('button');hintClose.type='button';hintClose.textContent='×';hintClose.setAttribute('aria-label','Ocultar sugerencia');hint.append(hintRobot,hintText,hintClose);
root.append(panel,launch,hint);document.body.append(root);
// 7.1T.2: Private assistants are unavailable until the corresponding
// authenticated panel is actually mounted. Login, registration and loading
// screens never expose the launcher, panel or invitation.
if(area==='client'||area==='admin'){
 const app=document.getElementById('app');
 const authenticatedPanel=area==='client'?'uco-client-panel':'uco-admin-panel';
 const updateAssistantAccess=()=>{
  const allowed=Boolean(app?.querySelector(authenticatedPanel));
  root.style.display=allowed?'':'none';
  if(!allowed){panel.hidden=true;hint.hidden=true;launch.setAttribute('aria-expanded','false');}
 };
 root.style.display='none';
 updateAssistantAccess();
 if(app)new MutationObserver(updateAssistantAccess).observe(app,{childList:true});
}

let speechTimer=0;
function speak(text){robot.classList.add('is-speaking');clearTimeout(speechTimer);speechTimer=setTimeout(()=>robot.classList.remove('is-speaking'),Math.min(6500,Math.max(1000,text.length*35)))}
function append(who,text,persist=true){const el=document.createElement('div');el.className='uco-ai-msg';el.dataset.who=who;el.textContent=text;messages.append(el);messages.scrollTop=messages.scrollHeight;if(who==='bot'&&persist)speak(text);if(persist){state.messages.push({who,text});state.messages=state.messages.slice(-MAX);save()}}
const greeting=area==='admin'?'Puedo orientarte por las secciones de administración. No realizo cambios por ti.':area==='client'?'Puedo ayudarte a localizar productos y explicarte cómo preparar tu pedido.':'Puedo ayudarte a encontrar productos y orientarte sobre cómo comprar en UCO.';
if(state.messages.length)state.messages.slice(-MAX).forEach(m=>{if(['bot','user'].includes(m.who)&&typeof m.text==='string')append(m.who,m.text,false)});else append('bot',greeting);
// Shortcuts reuse existing navigation, never privileged APIs.
const shortcuts=area==='admin'
 ?[['Catálogo','#catalog-nav'],['Clientes','#customers-nav'],['Tarifas','#tariffs-main-nav'],['Pedidos','#orders-nav'],['Pagos','#payments-nav']]
 :area==='public'
 ?[['Inicio','#inicio'],['Familias','#familias'],['Selección','#seleccion'],['Filosofía','#nosotros'],['Pedidos','#pedidos'],['Contacto','#contacto']]
 :[['Carrito','#cart-toggle'],['Historial de compras','#uco-purchase-history']];
function navigateShortcut(label,selector){
 if(area==='client'){
  // Reuse native client cart/history windows and their authenticated handlers.
  const target=document.querySelector(selector);
  if(!target||target.disabled||target.closest('[hidden]')){
   append('bot','Inicia sesión en el área de clientes para abrir '+label.toLowerCase()+'.');return;
  }
  target.click();return;
 }
 if(area==='admin'){
  const target=document.querySelector(selector);
  if(!target||target.hidden||target.disabled||target.closest('[hidden]')){
   append('bot','La sección '+label+' no está disponible para tu usuario.');return;
  }
  target.click();
  // Let the native administration controller switch sections.
  return;
 }
 if(area==='public'){
  // Use the same link when present; native handlers remain authoritative.
  const existing=[...document.querySelectorAll('uco-header a[href], uco-footer a[href]')]
   .find(a=>{try{return new URL(a.href,location.href).hash===selector}catch{return false}});
  if(existing){existing.click();return}
  const section=document.querySelector(selector);
  if(section){section.scrollIntoView({behavior:'smooth',block:'start'});history.replaceState(null,'',selector);return}
  append('bot','No encuentro ahora la sección '+label+'.');return;
 }
 const nav=[...document.querySelectorAll('main a, nav a, main button, nav button')]
  .find(el=>el.textContent?.trim().toLowerCase()===label.toLowerCase()&&el.offsetParent!==null);
 if(nav){nav.click()}else append('bot','La sección '+label+' todavía no está disponible en esta área.');
}
shortcuts.forEach(([label,selector])=>{
 const b=document.createElement('button');b.type='button';b.textContent=label;
 b.setAttribute('aria-label',area==='client'?'Abrir '+label:'Ir a '+label);
 b.addEventListener('click',()=>navigateShortcut(label,selector));actions.append(b);
});
actions.style.setProperty('--uco-ai-shortcut-count',String(shortcuts.length));
// Each draggable surface uses viewport-fixed coordinates; no transforms or parent-relative offsets.
const margin=8;const coords={launch:read(POS+'_launch',null),panel:read(POS+'_panel',null)};
function size(){return {w:document.documentElement.clientWidth,h:window.visualViewport?.height||innerHeight,offsetTop:window.visualViewport?.offsetTop||0}}
function clamp(el,x,y){const {w,h,offsetTop}=size(),r=el.getBoundingClientRect();return {x:Math.max(margin,Math.min(Math.max(margin,w-r.width-margin),x)),y:Math.max(offsetTop+margin,Math.min(Math.max(offsetTop+margin,offsetTop+h-r.height-margin),y))}}
function apply(el,xy){const p=clamp(el,xy.x,xy.y);el.style.setProperty('left',p.x+'px','important');el.style.setProperty('top',p.y+'px','important');el.style.setProperty('right','auto','important');el.style.setProperty('bottom','auto','important');return p}
function defaultPosition(el,kind){const {w,h,offsetTop}=size(),r=el.getBoundingClientRect();if(kind==='launch'){const gap=mobile()?16:24;const safe=mobile()?Math.max(76,parseInt(getComputedStyle(document.documentElement).getPropertyValue('--uco-ai-mobile-clearance'),10)||76):24;return {x:w-r.width-gap,y:offsetTop+h-r.height-safe}};return {x:w-r.width-16,y:offsetTop+h-r.height-(mobile()?10:96)}}
function place(el,kind){const saved=coords[kind];return apply(el,saved&&Number.isFinite(saved.x)&&Number.isFinite(saved.y)?saved:defaultPosition(el,kind))}
function savePosition(kind,xy){coords[kind]=xy;try{localStorage.setItem(POS+'_'+kind,JSON.stringify(xy))}catch{}}
function hideHint(){hint.hidden=true;root.classList.remove('uco-ai-inviting')}
function open(){panel.hidden=false;hideHint();launch.setAttribute('aria-expanded','true');root.classList.add('uco-ai-open');place(panel,'panel');input.focus({preventScroll:true})}
function minimize(){panel.hidden=true;root.classList.remove('uco-ai-open');launch.setAttribute('aria-expanded','false');showHint();hint.focus?.({preventScroll:true})}
function dismiss(){state.closedAt=Date.now();state.invitedAt=Date.now();save();panel.hidden=true;hideHint();root.classList.remove('uco-ai-open');launch.setAttribute('aria-expanded','false');launch.focus({preventScroll:true})}
// Dragging is independent from clicking. Pointer capture stays on the handle
// even if the pointer leaves the element. Never move an open panel on launcher drag.
let ignoreLaunchClick=false,hintDragged=false;
function draggable(handle,target,kind){
 let drag=null;
 handle.style.touchAction='none';
 handle.addEventListener('pointerdown',e=>{
  if(e.button!==0||((kind==='panel'||kind==='hint')&&e.target.closest('button,input')))return;
  const r=target.getBoundingClientRect();
  drag={id:e.pointerId,dx:e.clientX-r.left,dy:e.clientY-r.top,
    startX:e.clientX,startY:e.clientY,moved:false};
  try{handle.setPointerCapture(e.pointerId)}catch{}
  // Do not preventDefault: tap/click remains usable on touch and mouse.
 });
 handle.addEventListener('pointermove',e=>{
  if(!drag||e.pointerId!==drag.id)return;
  if(!drag.moved&&Math.hypot(e.clientX-drag.startX,e.clientY-drag.startY)>7)drag.moved=true;
  if(drag.moved){
   if(e.cancelable)e.preventDefault();
   apply(target,{x:e.clientX-drag.dx,y:e.clientY-drag.dy});
  }
 });
 const finish=e=>{
  if(!drag||e.pointerId!==drag.id)return;
  if(drag.moved){
   const r=target.getBoundingClientRect();
   savePosition(kind,{x:r.left,y:r.top});
   if(kind==='launch')ignoreLaunchClick=true;
   if(kind==='hint')hintDragged=true;
  }
  drag=null;
  if(handle.hasPointerCapture?.(e.pointerId))try{handle.releasePointerCapture(e.pointerId)}catch{}
 };
 handle.addEventListener('pointerup',finish);
 handle.addEventListener('pointercancel',finish);
 handle.addEventListener('lostpointercapture',()=>{drag=null});
}
draggable(launch,launch,'launch');
// The invitation is a separate movable robot. Preserve the launcher position when it is dismissed.
function placeHint(){const saved=coords.hint;if(saved&&Number.isFinite(saved.x)&&Number.isFinite(saved.y)){apply(hint,saved);return}const r=launch.getBoundingClientRect();const h=hint.getBoundingClientRect();apply(hint,{x:Math.max(8,r.right-h.width),y:Math.max(8,r.bottom-h.height)})}
function showHint(){hint.hidden=false;placeHint();root.classList.add('uco-ai-inviting')}
draggable(hint,hint,'hint');
draggable(head,panel,'panel');
launch.addEventListener('click',e=>{
 if(ignoreLaunchClick){ignoreLaunchClick=false;e.preventDefault();return}
 panel.hidden?open():minimize();
});
mini.addEventListener('click',minimize);
close.addEventListener('click',dismiss);
hintClose.addEventListener('click',e=>{e.stopPropagation();const r=hint.getBoundingClientRect();apply(launch,{x:r.right-launch.offsetWidth,y:r.bottom-launch.offsetHeight});savePosition('launch',{x:launch.getBoundingClientRect().left,y:launch.getBoundingClientRect().top});coords.hint=null;try{localStorage.removeItem(POS+'_hint')}catch{}hideHint();state.invitedAt=Date.now();save()});hint.addEventListener('click',e=>{if(e.target.closest('button'))return;if(hintDragged){hintDragged=false;return}open()});
function normalizeUco(text){return String(text||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^\p{L}\p{N}\s]/gu,' ').replace(/\s+/g,' ').trim()}
// 7.1G: memoria de contexto mínima, por pestaña y con caducidad.
// No conserva datos de clientes ni documentos; solo términos de búsqueda de catálogo.
const CTX_KEY='uco_assistant_catalog_context_71g',CTX_TTL=30*60*1000;
let busy=false;
function readCatalogContext(){
 try{const c=JSON.parse(sessionStorage.getItem(CTX_KEY)||'null');
  return c&&typeof c.term==='string'&&c.term.length<=120&&Number.isFinite(c.at)&&Date.now()-c.at<CTX_TTL?c:null;
 }catch{return null}
}
function saveCatalogContext(term){
 const clean=normalizeUco(term).slice(0,120);
 if(!clean)return;
 try{sessionStorage.setItem(CTX_KEY,JSON.stringify({term:clean,at:Date.now()}))}catch{}
}
function clearCatalogContext(){try{sessionStorage.removeItem(CTX_KEY)}catch{}}
function isFollowup(q){
 const n=normalizeUco(q);
 return /^(y|tambien|ademas|que tal|cuanto|cuantos|cuesta|valen|vale|precio|precios|cuales|tipos|variedades|las|los|esas|esos|estos|estas|tienen|hay|estan|disponibles|puedo pedir|quiero pedir)\b/.test(n)
  && n.split(' ').length<=9;
}
function isReset(q){return /^(otra consulta|nuevo tema|cambia de tema|olvida lo anterior|empezar de nuevo|borra el contexto)$/i.test(normalizeUco(q))}
function catalogSubject(question,data){
 // Solo memorizar una referencia explícita comprobada por el catálogo.
 if(!data||!Array.isArray(data.items)||!data.items.length)return '';
 const terms=Array.isArray(data.terms)?data.terms.filter(x=>typeof x==='string'&&x.length>2):[];
 const topicWords=new Set(['factura','pedido','cliente','usuario','permiso','tarifa','precio','ayuda','envio']);
 const subject=terms.filter(t=>!topicWords.has(t)).slice(0,5).join(' ');
 return subject||'';
}
const HELP_TOPICS={
 catalogo:'Puedo ayudarte a buscar productos y categorías del catálogo. Para modificarlos, utiliza la administración mientras habilitamos acciones autorizadas.',
 pedidos:'Puedo explicarte dónde consultar los pedidos. Aún no tengo acceso autenticado a pedidos ni puedo modificarlos.',
 facturas:'Las facturas y comprobantes deben recuperarse desde el área autorizada del cliente o administración. Todavía no puedo adjuntarlos aquí.',
 clientes:'La información de clientes requiere identificación y permisos. Todavía no está conectada a este asistente.',
 precios:'Puedo localizar productos, pero los precios personalizados requieren consultar la tarifa del cliente autenticado.',
 ayuda:'Puedes preguntarme por productos, categorías, pedidos, facturas o el funcionamiento de la web.'
};
// 7.1R: only non-sensitive, unambiguous product identifiers in tab memory.
// Never store account prices, sessions, or order data.
let lastComparedProducts=null;
function isComparisonFollowup(question){
 const n=normalizeUco(question);
 return /^(?:y |cual |cuales |cuanto |cuantos |que |dime |comparalos|comparalas|sus precios|los precios|el precio|precios|precio)/.test(n)
  && /\b(precio|precios|cuesta|cuestan|vale|valen|barato|barata|baratos|baratas|caro|cara|caros|caras|diferencia|comparacion|comparar|comparalos|comparalas)\b/.test(n);
}
async function askComparedPrices(){
 const pair=lastComparedProducts;
 if(!pair||pair.length!==2)return 'Para comparar precios, primero necesito identificar dos productos comerciales concretos.';
 const ctrl=new AbortController(),timer=setTimeout(()=>ctrl.abort(),8000);
 try{
  const response=await fetch('/api/assistant-prices-71i.php?'+new URLSearchParams({ids:pair.map(p=>p.id).join(',')}),{
   credentials:'same-origin',cache:'no-store',signal:ctrl.signal,headers:{Accept:'application/json'}
  });
  if(response.status===401)return 'Inicia sesión como cliente para comparar los precios de tu tarifa.';
  if(!response.ok)throw Error('HTTP '+response.status);
  const data=await response.json();
  if(!Array.isArray(data.items))throw Error('Respuesta de precios inválida');
  const rows=pair.map(p=>data.items.find(x=>Number(x.id)===p.id));
  if(rows.some(x=>!x||!Number.isSafeInteger(x.price_cents)||x.price_cents<0))
   return 'No puedo comparar los importes: falta un precio confirmado para alguna referencia.';
  const euro=v=>(v/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
  const [a,b]=rows;
  const sameUnit=String(a.unit||'').trim().toLowerCase()===String(b.unit||'').trim().toLowerCase();
  let result='Precios de tu tarifa actual:\n• '+a.name+': '+euro(a.price_cents)+(a.unit?' / '+a.unit:'')+
   '\n• '+b.name+': '+euro(b.price_cents)+(b.unit?' / '+b.unit:'');
  if(!sameUnit)return result+'\nLas unidades de venta son distintas; no puedo concluir cuál es más económico sin una base comparable.';
  if(a.price_cents===b.price_cents)return result+'\nAmbas referencias tienen el mismo precio para esa unidad.';
  const cheaper=a.price_cents<b.price_cents?a:b;
  return result+'\nLa referencia de menor precio para la misma unidad es '+cheaper.name+
   ', con una diferencia de '+euro(Math.abs(a.price_cents-b.price_cents))+'. Los importes pueden variar hasta confirmar el pedido.';
 }catch(error){console.warn('[UCO 7.1R] Comparación de precios:',error);return 'No puedo verificar ahora los precios de tu tarifa.';}
 finally{clearTimeout(timer)}
}
// 7.1S: estimates are read-only and require a single unambiguous real product.
// Units must be identical to the catalog's selling unit. Never assume kg = unit.
function parseQuantityRequest(question){
 const n=normalizeUco(question);
 const m=n.match(/(?:^|\s)(\d+(?:[.,]\d{1,3})?)\s*(kg|kilos?|kilogramos?|g|gramos?|unidades?|uds?|piezas?|cajas?|caja|bandejas?|bandeja|bolsas?|bolsa)(?=\s|$)/);
 if(!m)return null;
 const amount=Number(m[1].replace(',','.'));
 if(!Number.isFinite(amount)||amount<=0||amount>10000)return null;
 const unit=m[2];
 const groups={kg:['kg','kilo','kilos','kilogramo','kilogramos'],g:['g','gramo','gramos'],ud:['ud','uds','unidad','unidades','pieza','piezas'],caja:['caja','cajas'],bandeja:['bandeja','bandejas'],bolsa:['bolsa','bolsas']};
 const canonical=Object.keys(groups).find(k=>groups[k].includes(unit));
 if(!canonical)return null;
 const before=n.slice(0,m.index),after=n.slice(m.index+m[0].length);
 const subject=(before+' '+after).replace(/\b(?:cuanto|cuantos|cuesta|cuestan|vale|valen|precio|precios|importe|total|seria|serian|quiero|necesito|comprar|pedir|dame|de|del|el|la|los|las|un|una|unas|unos|por|favor|para|me|costaria|costarian|calcula|calcular|presupuesto|aproximado)\b/g,' ').replace(/\s+/g,' ').trim();
 return {amount,canonical,subject};
}
function sellingUnit(unit){
 const n=normalizeUco(String(unit||''));
 const groups={kg:['kg','kilo','kilos','kilogramo','kilogramos'],g:['g','gramo','gramos'],ud:['ud','uds','unidad','unidades','pieza','piezas'],caja:['caja','cajas'],bandeja:['bandeja','bandejas'],bolsa:['bolsa','bolsas']};
 return Object.keys(groups).find(k=>groups[k].includes(n))||null;
}
async function askQuantityEstimate(question){
 const request=parseQuantityRequest(question);
 if(!request)return null;
 if(!request.subject)return 'Indícame el producto concreto junto con la cantidad para calcular un importe orientativo.';
 const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),8500);
 try{
  const response=await fetch('/api/assistant-logic-71e.php?'+new URLSearchParams({q:request.subject}),{credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}});
  if(!response.ok)throw Error('Catálogo HTTP '+response.status);
  const data=await response.json();
  if(!data.ok||data.negated||data.clarity?.alternative||data.clarity?.multiple_requests||data.intent==='comparison')return 'La consulta incluye condiciones que no puedo resolver con seguridad. Indica una única referencia y su cantidad.';
  const real=Array.isArray(data.items)?data.items.filter(p=>!p.is_demo):[];
  if(real.length!==1||data.truncated)return 'Necesito una única referencia comercial inequívoca para calcular el importe. Concreta el nombre del producto; he encontrado '+real.length+' coincidencias comerciales.';
  const item=real[0],catalogUnit=sellingUnit(item.unit);
  if(!catalogUnit||catalogUnit!==request.canonical)return 'La cantidad indicada no coincide con la unidad de venta del catálogo ('+String(item.unit||'sin especificar')+'). No haré conversiones ni asumiré formatos de venta.';
  const priceResponse=await fetch('/api/assistant-prices-71i.php?'+new URLSearchParams({ids:String(item.id)}),{credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}});
  if(priceResponse.status===401)return 'Inicia sesión como cliente para calcular importes con tu tarifa.';
  if(!priceResponse.ok)throw Error('Tarifa HTTP '+priceResponse.status);
  const prices=await priceResponse.json();
  const row=Array.isArray(prices.items)?prices.items.find(p=>Number(p.id)===Number(item.id)):null;
  if(!row||!Number.isSafeInteger(row.price_cents)||row.price_cents<0)return 'No existe un precio verificable para esta referencia en tu tarifa.';
  const quantityMillis=Math.round(request.amount*1000);
  const totalCents=Math.round(row.price_cents*quantityMillis/1000);
  if(!Number.isSafeInteger(totalCents))return 'No puedo calcular con seguridad ese importe.';
  const euro=c=>(c/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
  return 'Estimación orientativa para '+String(row.name)+': '+request.amount.toLocaleString('es-ES')+' '+String(row.unit)+' × '+euro(row.price_cents)+' = '+euro(totalCents)+'.\nNo es un pedido ni un cobro. El importe definitivo, los impuestos y la disponibilidad se verifican al confirmar la compra.';
 }catch(error){console.warn('[UCO 7.1S] Estimación:',error);return 'No puedo verificar ahora el catálogo o la tarifa para calcular el importe.';}
 finally{clearTimeout(timer)}
}
// 7.1U: ephemeral draft. Never store prices, credentials or orders.
let provisionalBudget=null;
let pendingCartTransfer=null;
// 7.2A: short-lived, in-tab reference to the last successfully edited cart item.
// Never persist the reference or infer it from a budget, catalog search or order.
let lastCartReference=null;
const CART_REFERENCE_TTL=5*60*1000;
function cartContextCommand(value){
 const n=normalizeUco(value);
 if(!lastCartReference||Date.now()-lastCartReference.at>CART_REFERENCE_TTL){lastCartReference=null;return null;}
 let m=n.match(/^(?:aumenta|incrementa|suma|anade|agrega|reduce|disminuye|resta|quita) (?:esa cantidad|su cantidad|la cantidad|la|lo|ese producto|esa referencia) (?:en|por) (\d+) (?:unidades?|uds?|piezas?)?(?: (?:del|en|de mi) carrito)?$/);
 if(m){
  const verb=n.split(' ')[0];
  return {action:/^(reduce|disminuye|resta|quita)$/.test(verb)?'cart-decrease':'cart-increase',term:lastCartReference.term,quantity:Number(m[1])};
 }
 m=n.match(/^(?:pon|cambia|modifica) (?:su cantidad|la cantidad|la|lo|ese producto|esa referencia) (?:a|por) (\d+) (?:unidades?|uds?|piezas?)?(?: (?:del|en|de mi) carrito)?$/);
 if(m)return {action:'cart-set',term:lastCartReference.term,quantity:Number(m[1])};
 if(/^(?:quitalo|quitala|eliminalo|eliminala|quita ese producto|quita esa referencia)(?: (?:del|de mi) carrito)?$/.test(n))return {action:'cart-remove',term:lastCartReference.term};
 return null;
}

function parseBudgetCommand(value){
 const n=normalizeUco(value);
 // 7.1Z: multiple explicitly named cart changes in one message.
 // Keep the existing single-line parser as the source of truth for each clause.
 if(/(?:\s+y\s+|\s*;\s*)/.test(n)&&/carrito/.test(n)){
  const clauses=n.split(/\s+y\s+|\s*;\s*/).map(x=>x.trim()).filter(Boolean);
  if(clauses.length>=2&&clauses.length<=8){
   const commands=clauses.map(x=>parseBudgetCommand(x));
   if(commands.every(c=>c&&['cart-remove','cart-set','cart-increase','cart-decrease'].includes(c.action)))
    return {action:'cart-multi',commands};
  }
 }
 // 7.1Y: relative edits explicitly targeting the native cart, not the draft budget.
 // Never infer a product from conversation context: require a product name/SKU.
 let relative=n.match(/^(?:anade|agrega|suma|incrementa|aumenta|quita|retira|reduce|disminuye) (\d+) (?:unidades?|uds?|piezas?) (?:mas|menos)? (?:de |a |del )?(.+?) (?:en|del|de mi|al|a mi) carrito$/);
 if(relative){
  const verb=n.split(' ')[0];
  return {action:['quita','retira','reduce','disminuye'].includes(verb)?'cart-decrease':'cart-increase',term:relative[2],quantity:Number(relative[1])};
 }
 relative=n.match(/^(?:aumenta|incrementa|reduce|disminuye) (.+?) (?:del|de mi) carrito (?:en|por) (\d+) (?:unidades?|uds?|piezas?)?$/);
 if(relative)return {action:/^(reduce|disminuye)/.test(n)?'cart-decrease':'cart-increase',term:relative[1],quantity:Number(relative[2])};
 // 7.1X: native cart editing, never the provisional budget.
 let cm=n.match(/^(?:quita|quitar|elimina|eliminar|borra|borrar) (.+?) (?:del|de mi) carrito$/);
 if(cm)return {action:'cart-remove',term:cm[1]};
 cm=n.match(/^(?:cambia|cambiar|modifica|modificar|pon) (.+?) (?:del|de mi) carrito (?:a|por) (\d+) (?:unidades?|uds?|piezas?)?$/);
 if(cm)return {action:'cart-set',term:cm[1],quantity:Number(cm[2])};
 if(/^(?:revisar|comprobar|ayuda con|ayudame con|que falta en|estado de) (?:los |mis |el |mi )?(?:datos de entrega|direccion de entrega|formulario de entrega)$/.test(n))return {action:'delivery-guide'};
 if(/^(?:iniciar|abrir|continuar|empezar|preparar) (?:el |mi |la )?(?:proceso de )?(?:compra|pedido|checkout)$/.test(n)||/^(?:ir a|abrir) (?:la )?(?:pantalla de )?(?:finalizar compra|tramitar pedido)$/.test(n))return {action:'cart-checkout-open'};
 if(/^(?:revisar|revisa|resumen de|resumen del|comprobar|comprueba|revisar mi compra|preparar compra|antes de comprar)(?: mi| el| del)? (?:carrito|compra|pedido)$/.test(n)||/^(?:revisar mi compra|resumen de mi compra|antes de confirmar el pedido)$/.test(n))return {action:'cart-review'};
 if(/^(?:ver|mostrar|consultar)(?: mi| el)? carrito$/.test(n))return {action:'cart-show'};
 if(/^(?:pasar|pasalo|pasar el|enviar|anadir|agregar|llevar)(?: mi| el)? presupuesto (?:al|a mi) carrito$/.test(n)||/^(?:pasar|anadir|agregar) (?:al|a mi) carrito$/.test(n))return {action:'cart'};
 if(/^(?:confirmar|confirmo) (?:paso|traspaso|envio) (?:al )?carrito$/.test(n))return {action:'cart-confirm'};
 if(/^(?:ver|mostrar|muestra|recalcular|recalcula|resumen)(?: mi| el)? presupuesto$/.test(n))return {action:'show'};
 if(/^(?:borrar|vaciar|cancelar|limpiar)(?: mi| el)? presupuesto$/.test(n))return {action:'clear'};
 let m=n.match(/^(?:quita|quitar|elimina|eliminar|borra|borrar) (.+?)(?: del presupuesto)?$/);
 if(m)return {action:'remove',term:m[1]};
 m=n.match(/^(?:cambia|cambiar|modifica|modificar|pon) (.+?) (?:a|por) (\d+(?:[.,]\d{1,3})?)\s*(kg|kilos?|kilogramos?|g|gramos?|unidades?|uds?|piezas?|cajas?|caja|bandejas?|bandeja|bolsas?|bolsa)$/);
 if(m)return {action:'change',term:m[1],quantity:parseQuantityRequest(m[2]+' '+m[3]+' referencia')};
 m=n.match(/^(?:anade|agrega|agregar|incluye|incluir|suma) (.+)$/);
 if(m)return {action:'add',request:parseQuantityRequest(m[1])};
 return null;
}
function budgetMatch(term){
 const n=normalizeUco(term);
 const matches=(provisionalBudget||[]).filter(p=>normalizeUco(p.name)===n||normalizeUco(p.name).includes(n));
 return matches.length===1?matches[0]:null;
}
async function showProvisionalBudget(){
 if(!provisionalBudget?.length)return 'No tienes ningún presupuesto provisional en esta conversación.';
 const ctrl=new AbortController(),timer=setTimeout(()=>ctrl.abort(),9000);
 try{
  const response=await fetch('/api/assistant-prices-71i.php?'+new URLSearchParams({ids:[...new Set(provisionalBudget.map(p=>p.id))].join(',')}),{credentials:'same-origin',cache:'no-store',signal:ctrl.signal,headers:{Accept:'application/json'}});
  if(response.status===401)return 'Inicia sesión como cliente para consultar tu tarifa.';
  if(!response.ok)throw Error('Tarifa HTTP '+response.status);
  const data=await response.json();if(!Array.isArray(data.items))throw Error('Tarifa inválida');
  const byId=new Map(data.items.map(p=>[Number(p.id),p]));
  let total=0;const rows=[];
  const euro=c=>(c/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
  for(const line of provisionalBudget){
   const row=byId.get(line.id);
   if(!row||!Number.isSafeInteger(row.price_cents)||row.price_cents<0||sellingUnit(row.unit)!==line.canonical)return 'No puedo verificar todas las tarifas y unidades. No mostraré un presupuesto parcial.';
   const cents=Math.round(row.price_cents*Math.round(line.amount*1000)/1000);
   if(!Number.isSafeInteger(cents)||!Number.isSafeInteger(total+cents))return 'El importe supera el límite seguro.';
   total+=cents;rows.push('• '+row.name+': '+line.amount.toLocaleString('es-ES')+' '+row.unit+' × '+euro(row.price_cents)+' = '+euro(cents));
  }
  return 'Presupuesto provisional según tu tarifa actual:\n'+rows.join('\n')+'\nTotal estimado: '+euro(total)+'\nNo es un pedido ni un cobro. Impuestos y disponibilidad se verifican al confirmar la compra.';
 }catch(error){console.warn('[UCO 7.1U] Presupuesto provisional:',error);return 'No puedo consultar ahora tu tarifa para recalcular el presupuesto.';}
 finally{clearTimeout(timer)}
}
// 7.1W: Read-only cart consultation through the existing authenticated client controller.
async function requestNativeCartSummary(){
 return new Promise(resolve=>{
  const id='uco-cart-read-'+Date.now()+'-'+Math.random().toString(36).slice(2);
  const timer=setTimeout(()=>{document.removeEventListener('uco:assistant-cart-summary-result',listener);resolve('No he recibido respuesta del carrito. Abre «Carrito» para consultarlo.')},8000);
  const listener=e=>{if(e.detail?.id!==id)return;clearTimeout(timer);document.removeEventListener('uco:assistant-cart-summary-result',listener);resolve(String(e.detail.message||'No puedo consultar el carrito ahora.'))};
  document.addEventListener('uco:assistant-cart-summary-result',listener);
  document.dispatchEvent(new CustomEvent('uco:assistant-cart-summary',{detail:{id}}));
 });
}
async function requestNativeCartEdit(command){
 return new Promise(resolve=>{
  const id='uco-cart-edit-'+Date.now()+'-'+Math.random().toString(36).slice(2);
  const listener=e=>{if(e.detail?.id!==id)return;clearTimeout(timer);document.removeEventListener('uco:assistant-cart-edit-result',listener);resolve(String(e.detail.message||'No se ha podido modificar el carrito.'))};
  const timer=setTimeout(()=>{document.removeEventListener('uco:assistant-cart-edit-result',listener);resolve('El carrito no ha respondido. Comprueba su estado antes de repetir la operación.')},12000);
  document.addEventListener('uco:assistant-cart-edit-result',listener);
  document.dispatchEvent(new CustomEvent('uco:assistant-cart-edit',{detail:{id,action:command.action,term:command.term,quantity:command.quantity}}));
 });
}
function requestNativeCheckoutOpen(){
 return new Promise(resolve=>{
  const id='uco-checkout-open-'+Date.now()+'-'+Math.random().toString(36).slice(2);
  const listener=e=>{if(e.detail?.id!==id)return;clearTimeout(timer);document.removeEventListener('uco:assistant-checkout-open-result',listener);resolve(String(e.detail.message||'No se ha podido abrir la revisión.'))};
  const timer=setTimeout(()=>{document.removeEventListener('uco:assistant-checkout-open-result',listener);resolve('El área de clientes no ha respondido. Abre «Carrito» para continuar.')},10000);
  document.addEventListener('uco:assistant-checkout-open-result',listener);
  document.dispatchEvent(new CustomEvent('uco:assistant-checkout-open',{detail:{id}}));
 });
}
// 7.2D: request field-completeness only; never send delivery PII through chat.
function requestNativeDeliveryGuide(){
 return new Promise(resolve=>{
  const id='uco-delivery-guide-'+Date.now()+'-'+Math.random().toString(36).slice(2);
  const listener=e=>{if(e.detail?.id!==id)return;clearTimeout(timer);document.removeEventListener('uco:assistant-delivery-guide-result',listener);resolve(String(e.detail.message||'No puedo comprobar el formulario ahora.'))};
  const timer=setTimeout(()=>{document.removeEventListener('uco:assistant-delivery-guide-result',listener);resolve('No he recibido respuesta del formulario de entrega. Ábrelo desde el carrito.')},10000);
  document.addEventListener('uco:assistant-delivery-guide-result',listener);
  document.dispatchEvent(new CustomEvent('uco:assistant-delivery-guide',{detail:{id}}));
 });
}
function requestNativeCartReview(){
 return new Promise(resolve=>{
  const id='uco-cart-review-'+Date.now()+'-'+Math.random().toString(36).slice(2);
  const listener=e=>{if(e.detail?.id!==id)return;clearTimeout(timer);document.removeEventListener('uco:assistant-cart-review-result',listener);resolve(String(e.detail.message||'No puedo revisar el carrito ahora.'))};
  const timer=setTimeout(()=>{document.removeEventListener('uco:assistant-cart-review-result',listener);resolve('No he recibido respuesta del carrito. Abre «Carrito» para revisarlo.')},10000);
  document.addEventListener('uco:assistant-cart-review-result',listener);
  document.dispatchEvent(new CustomEvent('uco:assistant-cart-review',{detail:{id}}));
 });
}
async function updateProvisionalBudget(command){
 if(!command)return null;
 if(command.action==='cart-multi'){
  lastCartReference=null;
  // The native controller performs authorization, item matching and stock checks.
  // Sequential writes are not atomic: always disclose any partial completion.
  const results=[];
  for(let i=0;i<command.commands.length;i++){
   const result=await requestNativeCartEdit(command.commands[i]);
   results.push((i+1)+'. '+result);
   if(!/^He (?:quitado|actualizado)/.test(result)){
    return 'He detenido la operación en el cambio '+(i+1)+' de '+command.commands.length+'. Los cambios anteriores pueden haberse aplicado.\n'+results.join('\n')+'\nComprueba el carrito antes de repetir la instrucción.';
   }
  }
  return 'He procesado '+results.length+' cambios en tu carrito:\n'+results.join('\n')+'\nRevisa el carrito para confirmar cantidades y precios.';
 }
 if(['cart-remove','cart-set','cart-increase','cart-decrease'].includes(command.action)){
  const result=await requestNativeCartEdit(command);
  if(/^He (?:quitado|actualizado)/.test(result)){
   lastCartReference=command.action==='cart-remove'?null:{term:command.term,at:Date.now()};
  }else lastCartReference=null;
  return result;
 }
 if(command.action==='delivery-guide')return requestNativeDeliveryGuide();
 if(command.action==='cart-checkout-open')return requestNativeCheckoutOpen();
 if(command.action==='cart-review')return requestNativeCartReview();
 if(command.action==='cart-show'){
  const result=await requestNativeCartSummary();
  return result;
 }
 if(command.action==='clear'){pendingCartTransfer=null;provisionalBudget=null;return 'He descartado el presupuesto provisional. No se ha creado ningún pedido.';}
 if(command.action==='cart'){
  if(!provisionalBudget?.length)return 'Primero prepara un presupuesto con productos y cantidades.';
  const invalid=provisionalBudget.find(p=>!Number.isSafeInteger(p.id)||p.id<1||!Number.isSafeInteger(p.amount)||p.amount<1||p.amount>99999);
  if(invalid)return 'El carrito admite exclusivamente cantidades enteras entre 1 y 99999. Corrige las cantidades fraccionarias del presupuesto antes de transferirlo.';
  const detail=await showProvisionalBudget();
  if(!detail.startsWith('Presupuesto provisional según tu tarifa actual:'))return detail;
  pendingCartTransfer=provisionalBudget.map(p=>({id:p.id,quantity:p.amount}));
  return detail+'\n\nPara añadir estas cantidades a tu carrito actual, escribe «confirmar paso al carrito». Se sumarán a lo que ya tengas y no se creará ningún pedido ni cobro.';
 }
 if(command.action==='cart-confirm'){
  if(!pendingCartTransfer?.length)return 'Primero di «pasar presupuesto al carrito» para revisar y confirmar las cantidades.';
  const lines=pendingCartTransfer;pendingCartTransfer=null;
  const result=await new Promise(resolve=>{
   const id='uco-budget-'+Date.now()+'-'+Math.random().toString(36).slice(2);
   const timeout=setTimeout(()=>{document.removeEventListener('uco:budget-cart-result',listener);resolve({ok:false,message:'No he recibido respuesta del carrito de clientes.'})},20000);
   const listener=e=>{if(e.detail?.id!==id)return;clearTimeout(timeout);document.removeEventListener('uco:budget-cart-result',listener);resolve(e.detail)};
   document.addEventListener('uco:budget-cart-result',listener);
   document.dispatchEvent(new CustomEvent('uco:budget-cart-transfer',{detail:{id,lines}}));
  });
  if(result.ok){provisionalBudget=null;return 'He añadido '+result.count+' referencia(s) al carrito existente. Abre «Carrito» para revisar cantidades, existencias y precios antes de continuar. No se ha creado ningún pedido ni cobro.';}
  return 'No se ha podido completar el traspaso: '+String(result.message||'Error del carrito')+'. Revisa el carrito antes de reintentarlo; algunas líneas podrían haberse añadido.';
 }
 if(command.action==='show')return showProvisionalBudget();
 if(!provisionalBudget?.length)return 'Primero calcula un presupuesto con productos y cantidades.';
 pendingCartTransfer=null;
 if(command.action==='remove'||command.action==='change'){
  const target=budgetMatch(command.term);
  if(!target)return 'No identifico una única línea con ese nombre. Dime el nombre exacto del producto del presupuesto.';
  if(command.action==='remove'){
   provisionalBudget=provisionalBudget.filter(p=>p!==target);
   return provisionalBudget.length?await showProvisionalBudget():'El presupuesto ha quedado vacío. No se ha creado ningún pedido.';
  }
  const q=command.quantity;
  if(!q||q.canonical!==target.canonical)return 'La cantidad debe ser válida y utilizar la misma unidad de venta ('+target.canonical+').';
  target.amount=q.amount;return showProvisionalBudget();
 }
 if(command.action==='add'){
  const q=command.request;
  if(!q||!q.subject)return 'Indica una cantidad, unidad y producto concretos. Ejemplo: «añade 2 kg de zanahorias».';
  if(provisionalBudget.length>=8)return 'El presupuesto admite un máximo de ocho líneas.';
  const ctrl=new AbortController(),timer=setTimeout(()=>ctrl.abort(),8500);
  try{
   const response=await fetch('/api/assistant-logic-71e.php?'+new URLSearchParams({q:q.subject}),{credentials:'same-origin',cache:'no-store',signal:ctrl.signal,headers:{Accept:'application/json'}});
   if(!response.ok)throw Error('Catálogo HTTP '+response.status);
   const data=await response.json();
   const real=Array.isArray(data.items)?data.items.filter(p=>!p.is_demo):[];
   if(!data.ok||data.negated||data.clarity?.alternative||data.clarity?.multiple_requests||data.truncated||real.length!==1)return 'No identifico una única referencia comercial para añadir. Concreta el producto.';
   const item=real[0];if(sellingUnit(item.unit)!==q.canonical)return 'La unidad indicada no coincide con la unidad de venta del catálogo.';
   if(provisionalBudget.some(p=>p.id===Number(item.id)))return 'Ese producto ya está en el presupuesto. Utiliza «cambia '+item.name+' a ...» para modificar su cantidad.';
   // Do not mutate the draft unless its current tariff is verifiable.
   const price=await fetch('/api/assistant-prices-71i.php?'+new URLSearchParams({ids:String(item.id)}),{credentials:'same-origin',cache:'no-store',signal:ctrl.signal,headers:{Accept:'application/json'}});
   if(price.status===401)return 'Inicia sesión como cliente para consultar tu tarifa.';
   if(!price.ok)throw Error('Tarifa HTTP '+price.status);
   const priced=await price.json();const row=Array.isArray(priced.items)?priced.items.find(p=>Number(p.id)===Number(item.id)):null;
   if(!row||!Number.isSafeInteger(row.price_cents)||row.price_cents<0||sellingUnit(row.unit)!==q.canonical)return 'No puedo verificar la tarifa o la unidad de ese producto.';
   provisionalBudget.push({id:Number(item.id),name:String(item.name),amount:q.amount,canonical:q.canonical});
   return showProvisionalBudget();
  }catch(error){console.warn('[UCO 7.1U] Añadir:',error);return 'No puedo verificar el producto o su tarifa ahora.';}
  finally{clearTimeout(timer)}
 }
 return null;
}
// 7.1T: independent line items; one authenticated tariff lookup, no order creation.
function parseMultiQuantityRequest(question){
 const normalized=normalizeUco(question);
 // Require an explicit monetary/estimate question, not a purchase instruction.
 if(!/\b(cuanto|cuesta|cuestan|costaria|costarian|presupuesto|importe|calcula|calcular|total)\b/.test(normalized))return null;
 const quantityPattern=/(?:^|\s)(\d+(?:[.,]\d{1,3})?)\s*(kg|kilos?|kilogramos?|g|gramos?|unidades?|uds?|piezas?|cajas?|caja|bandejas?|bandeja|bolsas?|bolsa)(?=\s|$)/g;
 const matches=[...normalized.matchAll(quantityPattern)];
 if(matches.length<2)return null;
 if(matches.length>8)return {error:'Puedo calcular como máximo ocho líneas en una consulta. Divide el presupuesto en varias preguntas.'};
 const lines=[];
 for(let i=0;i<matches.length;i++){
  const match=matches[i];
  const end=match.index+match[0].length;
  const next=i+1<matches.length?matches[i+1].index:normalized.length;
  const raw=normalized.slice(end,next).replace(/\b(?:y|ademas|tambien|mas|de|del|el|la|los|las|un|una|unos|unas|por|favor|total|importe|cuanto|cuesta|cuestan|costaria|costarian|presupuesto)\b/g,' ').trim();
  // Every quantity must have a distinct, explicit product; no guessing.
  const request=parseQuantityRequest(match[1]+' '+match[2]+' '+raw);
  if(!request||!request.subject)return {error:'Indica un producto concreto después de cada cantidad, por ejemplo: «2 kg de patatas y 3 kg de tomates».'};
  lines.push(request);
 }
 return {lines};
}
async function askMultiQuantityEstimate(question){
 const request=parseMultiQuantityRequest(question);
 if(!request)return null;
 if(request.error)return request.error;
 const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),12000);
 try{
  const products=[];
  for(const line of request.lines){
   const response=await fetch('/api/assistant-logic-71e.php?'+new URLSearchParams({q:line.subject}),{credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}});
   if(!response.ok)throw Error('Catálogo HTTP '+response.status);
   const data=await response.json();
   if(!data.ok||data.negated||data.clarity?.alternative||data.clarity?.multiple_requests||data.intent==='comparison')return 'Hay condiciones ambiguas en el presupuesto. Indica cada producto con su cantidad y unidad.';
   const real=Array.isArray(data.items)?data.items.filter(p=>!p.is_demo):[];
   if(real.length!==1||data.truncated)return 'No puedo identificar una única referencia comercial para «'+line.subject+'». Concreta el producto antes de calcular el presupuesto.';
   const product=real[0];
   if(sellingUnit(product.unit)!==line.canonical)return 'La unidad solicitada para «'+product.name+'» no coincide con su unidad de venta ('+String(product.unit||'sin especificar')+'). No haré conversiones.';
   products.push({...product,amount:line.amount,canonical:line.canonical});
  }
  const ids=[...new Set(products.map(p=>Number(p.id)))];
  const response=await fetch('/api/assistant-prices-71i.php?'+new URLSearchParams({ids:ids.join(',')}),{credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}});
  if(response.status===401)return 'Inicia sesión como cliente para calcular un presupuesto con tu tarifa.';
  if(!response.ok)throw Error('Tarifa HTTP '+response.status);
  const data=await response.json();
  if(!Array.isArray(data.items))throw Error('Tarifa inválida');
  const byId=new Map(data.items.map(p=>[Number(p.id),p]));
  let total=0;const rows=[];
  const euro=c=>(c/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
  for(const product of products){
   const price=byId.get(Number(product.id));
   if(!price||!Number.isSafeInteger(price.price_cents)||price.price_cents<0)return 'No existe un precio verificable para «'+product.name+'» en tu tarifa. No calcularé un total incompleto.';
   if(sellingUnit(price.unit)!==product.canonical)return 'La unidad de tarifa de «'+product.name+'» no coincide con la cantidad solicitada.';
   const cents=Math.round(price.price_cents*Math.round(product.amount*1000)/1000);
   if(!Number.isSafeInteger(cents))return 'El importe calculado supera el límite seguro.';
   total+=cents;
   if(!Number.isSafeInteger(total))return 'El total supera el límite seguro.';
   rows.push('• '+String(price.name||product.name)+': '+product.amount.toLocaleString('es-ES')+' '+String(price.unit)+' × '+euro(price.price_cents)+' = '+euro(cents));
  }
  provisionalBudget=products.map(p=>({id:Number(p.id),name:String(p.name),amount:p.amount,canonical:p.canonical}));
  return 'Presupuesto orientativo según tu tarifa:\n'+rows.join('\n')+'\nTotal estimado: '+euro(total)+'\nPuedes decir «cambia patatas a 3 kg», «quita tomates», «añade 2 kg de zanahorias» o «ver presupuesto». No es un pedido ni un cobro. Impuestos, disponibilidad e importe definitivo se verifican antes de confirmar la compra.';
 }catch(error){console.warn('[UCO 7.1T] Presupuesto:',error);return 'No puedo verificar ahora el catálogo o tu tarifa para calcular el presupuesto.';}
 finally{clearTimeout(timer)}
}
async function askUcoLogic(question){
 const ctrl=new AbortController(),timer=setTimeout(()=>ctrl.abort(),8500);
 try{
  const response=await fetch('/api/assistant-logic-71e.php?'+new URLSearchParams({q:question}),{
   credentials:'same-origin',cache:'no-store',signal:ctrl.signal,headers:{Accept:'application/json'}
  });
  if(!response.ok)throw Error('HTTP '+response.status);
  const data=await response.json();if(!data.ok)throw Error(data.error||'API no disponible');
  if(data.intent!=='comparison')lastComparedProducts=null;
  if(data.intent==='comparison'&&Array.isArray(data.comparison)){
   const groups=data.comparison;
   const describeSide=group=>{
    const options=Array.isArray(group.items)?group.items:[];
    if(!options.length)return '• '+group.query+': no encuentro una referencia verificable.';
    return '• '+group.query+': '+options.map(p=>p.name+(p.unit?' · '+p.unit:'')+(p.category?' ('+p.category+')':'')+(p.is_demo?' [demostración]':'')).join('; ');
   };
   let answer='He buscado ambas opciones por separado en el catálogo:\n'+groups.map(describeSide).join('\n');
   // 7.1Q: price only two unique, real references. Never infer which is intended.
   const choices=groups.map(group=>Array.isArray(group.items)?group.items:[]);
   lastComparedProducts=null;
   if(choices.length===2 && choices.every(list=>list.length===1 && !list[0].is_demo)
      && Number(choices[0][0].id)!==Number(choices[1][0].id)){
    lastComparedProducts=choices.map(list=>({id:Number(list[0].id),name:String(list[0].name||'')}));
    answer+='\n'+await askOwnPrices([choices[0][0],choices[1][0]]);
    answer+='\nLos precios corresponden a tu cuenta, no son una comparación de calidad ni de disponibilidad.';
   }else{
    answer+='\nPara comparar precios necesito dos referencias comerciales inequívocas. Si aparecen varias opciones o referencias de demostración, dime los nombres exactos.';
   }
   return answer;
  }
  const items=Array.isArray(data.items)?data.items:[];
  const subject=catalogSubject(question,data);if(subject)saveCatalogContext(subject);
  // 7.1N: no present potentially mixed results as a single definite answer.
  if(data.negated)return 'Entiendo que quieres excluir algo, pero todavía no puedo aplicar esa exclusión con seguridad. ¿Qué producto buscas y cuál debo dejar fuera?';
  if(data.clarity?.alternative && data.clarity?.comparison)return '¿Quieres que compare las dos alternativas o que busque una de ellas? Dime cuáles son las dos opciones.';
  if(data.clarity?.alternative)return 'Has mencionado alternativas. ¿Quieres resultados de ambas o prefieres que busque solo una?';
  if(data.clarity?.multiple_requests)return 'Puedo ayudarte con ambas cuestiones, pero para no mezclar resultados, ¿cuál quieres resolver primero?';

  const real=items.filter(p=>!p.is_demo),demo=items.filter(p=>p.is_demo);
  if(data.intent==='price'&&!real.length&&demo.length)return 'He encontrado referencias de demostración, pero no productos comerciales activos para esta consulta. Los precios de venta solo pueden consultarse para referencias reales publicadas en el catálogo de clientes.';
  const topic=(data.topics||[]).find(t=>HELP_TOPICS[t]);
  if(!items.length){
   if(data.filter_applied)return 'No encuentro coincidencias tras excluir las referencias indicadas en el catálogo. Esto no certifica características o ausencia de ingredientes.';
   if(topic)return HELP_TOPICS[topic];
   return 'No encuentro una coincidencia verificable en el catálogo actual. Puedes darme otra palabra, una categoría o más detalles. No afirmaré que no existe sin verificarlo.';
  }
  const describe=arr=>arr.slice(0,9).map(p=>'• '+p.name+(p.unit?' · '+p.unit:'')+(p.category?' ('+p.category+')':'')).join('\n');
  let answer=real.length
   ?'He encontrado '+real.length+(data.truncated?' o más':'')+' referencia'+(real.length===1?'':'s')+' comercial'+(real.length===1?'':'es')+' relacionada'+(real.length===1?'':'s')+':\n'+describe(real)
   :'He encontrado referencias relacionadas, pero ninguna confirmada como producto comercial activo.';
  if(data.filter_applied)answer+='\nHe excluido las referencias que mencionan el término indicado en los datos del catálogo. Esto no acredita ausencia de ingredientes ni alérgenos.';
  if(demo.length)answer+='\nReferencias de demostración (no confirman disponibilidad):\n'+describe(demo);
  if(data.intent==='price')answer+='\n'+await askOwnPrices(real);
  else if(data.intent==='buy')answer+='\nSi quieres comprar, dime las cantidades. Todavía no puedo crear ni cobrar pedidos desde el chat.';
  else if(data.intent==='varieties')answer+='\nEstas son las presentaciones o variedades relacionadas que he localizado.';
  else answer+='\nQue aparezca en catálogo no confirma existencias en tiempo real.';
  if(data.truncated)answer+='\nHay más coincidencias; concreta un tipo para afinar la búsqueda.';
  return answer;
 }catch(error){
  console.warn('[UCO Assistant] Fallo motor lógico:',error);
  return 'No he podido conectar con el catálogo en este momento. No voy a inventar productos ni disponibilidad. Comprueba que esté instalado assistant-logic-71e.php.';
 }finally{clearTimeout(timer)}
}

// Fase 7.1D: procedimientos verificados contra las vistas de UCO.
// La interfaz se inspecciona SOLO en el DOM visible del usuario actual;
// nunca se accede a datos de otros usuarios ni se inventan permisos.
const UCO_GUIDES=[
 {terms:['producto','articulo','catalogo','categoría','categoria','familia'],title:'Catálogo y productos',
  admin:'En Administración, abre Catálogo. Allí puedes consultar productos y categorías y, si tienes permiso, crear o modificar sus fichas. Antes de sustituir datos, revisa el producto existente y confirma los cambios.',
  public:'Abre la sección Catálogo de la web y utiliza el buscador o el filtro de familias para localizar productos.',
  client:'Consulta el catálogo de tu área de cliente. Las condiciones de compra dependen de tu cuenta.'},
 {terms:['precio','tarifa','promocion','descuento','importe'],title:'Precios y tarifas',
  admin:'Abre Tarifas en Administración. Comprueba qué clientes o productos afecta el cambio. Revisa el precio vigente y las promociones antes de guardar; no modifiques importes masivamente sin comprobar su alcance.',
  public:'Puedes consultar productos en el catálogo. Los precios aplicables a tu compra requieren tu tarifa de cliente.',
  client:'Los precios aplicables se consultan en el catálogo de tu área. Para cambios de tarifa, contacta con administración.'},
 {terms:['pedido','compra','carrito','transportista','envio','entrega'],title:'Pedidos',
  admin:'Abre Pedidos en Administración. Localiza el pedido y revisa su estado antes de actuar. La asignación de transporte o cualquier cambio de estado debe hacerse mediante los controles autorizados.',
  public:'Para preparar un pedido debes acceder a tu área de cliente. Este asistente aún no crea ni cobra pedidos.',
  client:'Consulta Pedidos o tu historial en el área de cliente. Revisa cantidades e importes antes de confirmar un pedido.'},
 {terms:['factura','recibo','comprobante','justificante','pdf'],title:'Facturas y comprobantes',
  admin:'Localiza primero el pedido desde Pedidos o la ficha autorizada del cliente. Descarga el comprobante disponible desde su control correspondiente; no confundas comprobante con factura fiscal.',
  public:'Para obtener documentos de compra, entra en tu área de cliente. No puedo entregar documentos privados desde este chat.',
  client:'Busca el pedido en tu historial y utiliza las opciones de comprobante disponibles. Si necesitas una factura fiscal no emitida, solicítala al departamento correspondiente.'},
 {terms:['usuario','cliente','permiso','rol','seguridad','contraseña','contrasena'],title:'Usuarios y permisos',
  admin:'En Administración, abre Usuarios / Clientes. Las operaciones disponibles dependen de tu rol. Para cambiar permisos o credenciales, utiliza los controles de seguridad autorizados y verifica el alcance antes de confirmar.',
  public:'Los datos y permisos de usuarios son privados. Accede a tu área autorizada para gestionarlos.',
  client:'Puedes consultar las opciones disponibles en tu área de cliente. Los permisos administrativos no se gestionan desde aquí.'}
];
// 7.1L: índice de navegación limitado a controles visibles en el área actual.
// No lee campos, tablas de clientes, valores privados ni contenido oculto.
const UCO_NAV_LEXICON={
 admin:[['Catálogo','#catalog-nav',['producto','articulo','catalogo','categoria','familia']],
 ['Clientes','#customers-nav',['cliente','usuario','cuenta']],
 ['Tarifas','#tariffs-main-nav',['precio','tarifa','promocion','descuento']],
 ['Pedidos','#orders-nav',['pedido','compra','envio','entrega','transportista']],
 ['Pagos','#payments-nav',['pago','cobro','devolucion','reembolso']]],
 client:[['Catálogo','#catalogo',['producto','catalogo','categoria']],
 ['Pedidos','#pedidos',['pedido','compra','carrito']],
 ['Historial','#historial',['historial','factura','recibo','comprobante']],
 ['Cuenta','#cuenta',['cuenta','perfil','contrasena']]],
 public:[['Familias','#familias',['familia','categoria','producto']],
 ['Selección','#seleccion',['seleccion','producto','catalogo']],
 ['Pedidos','#pedidos',['pedido','comprar','compra']],
 ['Contacto','#contacto',['contacto','telefono','correo','email']],
 ['Filosofía','#nosotros',['filosofia','empresa','nosotros']]]
};
function ucoWords(s){return normalizeUco(s).split(' ').filter(Boolean)}
function ucoMatchTerm(haystack,term){
 const h=' '+normalizeUco(haystack)+' ',t=normalizeUco(term);
 if(!t)return false;
 if(h.includes(' '+t+' '))return true;
 // Singular/plural variants without matching arbitrary substrings.
 return ucoWords(haystack).some(w=>w.length>3&&t.length>3&&
  (w===t+'s'||w===t+'es'||t===w+'s'||t===w+'es'));
}
function ucoIsVisible(el){
 if(!el||el.hidden||el.disabled||el.closest('[hidden],[aria-hidden="true"], [inert]'))return false;
 const st=getComputedStyle(el);
 return st.display!=='none'&&st.visibility!=='hidden'&&el.getClientRects().length>0;
}
function ucoSectionMatches(query){
 const entries=UCO_NAV_LEXICON[area]||UCO_NAV_LEXICON.public;
 return entries.map(([name,selector,terms])=>({name,selector,score:terms.reduce((n,t)=>n+(ucoMatchTerm(query,t)?1:0),0)}))
  .filter(e=>e.score>0).sort((a,b)=>b.score-a.score);
}
function guideFromQuestion(value){
 const n=normalizeUco(value);
 const asksHow=/\b(como|donde|explica|instruccion|ayuda|procedimiento|pasos|gestionar|modificar|cambiar|crear|anadir|agregar|eliminar|borrar|descargar|obtener|asignar|editar|acceder|entrar|encontrar|localizar|abrir|navegar)\b/.test(n);
 if(!asksHow)return null;
 const ranked=UCO_GUIDES.map(g=>({g,score:g.terms.reduce((a,t)=>a+(ucoMatchTerm(n,t)?1:0),0)})).sort((a,b)=>b.score-a.score);
 const sections=ucoSectionMatches(n);
 if(!ranked[0]?.score&&!sections.length)return 'Puedo orientarte por las secciones disponibles de UCO. Indícame qué quieres hacer: consultar productos, pedidos, precios, facturas o gestionar clientes.';
 const guide=ranked[0]?.score?ranked[0].g:null;
 const section=sections[0];
 let answer=guide?guide.title+':\n'+(guide[area]||guide.public):'Te puedo orientar por la sección '+section.name+'.';
 if(section){
  const target=document.querySelector(section.selector);
  answer+=ucoIsVisible(target)?'\nLa sección «'+section.name+'» está disponible en esta pantalla. Puedes abrirla desde los accesos del asistente.':
   '\nNo veo el acceso «'+section.name+'» disponible en esta pantalla. Puede depender de la página actual o de tus permisos.';
 }
 if(sections.length>1&&sections[0].score===sections[1].score&&sections[0].name!==sections[1].name){
  answer+='\nTambién podría estar relacionado con «'+sections[1].name+'». Si no era esto, concreta la sección.';
 }
 return answer;
}

// 7.1I: los precios se calculan EN SERVIDOR para el cliente de la sesión.
// No se aceptan IDs de cliente ni tarifas desde el navegador.
async function askOwnPrices(products){
 if(!products.length)return 'No hay productos comerciales confirmados para consultar precios.';
 const ids=products.slice(0,12).map(p=>Number(p.id)).filter(n=>Number.isSafeInteger(n)&&n>0);
 if(!ids.length)return 'No he encontrado productos válidos para consultar precios.';
 const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),8000);
 try{
  const response=await fetch('/api/assistant-prices-71i.php?'+new URLSearchParams({ids:ids.join(',')}),{
   method:'GET',credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}
  });
  if(response.status===401)return 'Inicia sesión como cliente para consultar los precios correspondientes a tu tarifa.';
  if(!response.ok)throw Error('HTTP '+response.status);
  const data=await response.json();if(!Array.isArray(data.items))throw Error('Respuesta inválida');
  if(!data.items.length){
   const reasons=Array.isArray(data.unavailable)?data.unavailable.map(x=>x.reason):[];
   if(reasons.includes('demo'))return 'Las referencias encontradas son de demostración, no productos comerciales activos. Administración debe publicar productos reales para consultar tarifas.';
   if(reasons.includes('inactive')||reasons.includes('category_inactive'))return 'La referencia está inactiva o su categoría está desactivada. No puedo mostrarla como producto comercial.';
   return 'No encuentro productos comerciales activos con precio consultable para esta búsqueda.';
  }
  const rows=data.items.slice(0,9).map(p=>{
   const amount=p.price_cents===null?'Sin precio asignado':(Number(p.price_cents)/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
   return '• '+String(p.name||'Producto')+': '+amount+(p.unit?' / '+p.unit:'');
  });
  const missing=(Array.isArray(data.unavailable)?data.unavailable:[]).filter(x=>x.reason==='price_missing').length;
  return 'Precios según tu cuenta de cliente:\n'+rows.join('\n')+(missing?'\nHay '+missing+' referencia(s) sin precio asignado.':'')+'\nLos importes pueden variar hasta confirmar el pedido.';
 }catch(error){console.warn('[UCO 7.1I] Precios:',error);return 'No puedo verificar tus precios ahora mismo. Consulta tu área de cliente.';}
 finally{clearTimeout(timer)}
}
function isReceiptQuery(value){
 const n=normalizeUco(value);
 return /\b(justificante|comprobante|recibo|factura|pdf)\b/.test(n)
   && (/\b(mi|mis|pedido|compra|descargar|ver|consultar|obtener|mostrar)\b/.test(n)||/\d/.test(n))
   && !/\b(como|donde|crear|emitir|modificar)\b/.test(n);
}
async function askOwnReceipt(value){
 const n=normalizeUco(value);
 const match=n.match(/\b(?:pedido|numero|n)\s*(?:n[ºo.]*)?\s*#?\s*(\d{1,10})\b/);
 if(!match)return 'Indícame el número de pedido para consultar si existe un justificante asociado a tu cuenta. Esta función no emite facturas fiscales.';
 const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),8000);
 try{
  const response=await fetch('/api/order-receipt.php?'+new URLSearchParams({order_id:match[1]}),{
   credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}
  });
  if(response.status===401)return 'Inicia sesión en tu cuenta para consultar documentos de tus pedidos.';
  const data=await response.json();
  if(!response.ok)return response.status===404?'No encuentro un justificante autorizado para ese pedido.':
    response.status===409?'Ese pedido todavía no dispone de justificante de operación aprobada.':
    'No puedo verificar el justificante de ese pedido ahora mismo.';
  if(!data.simulation)return 'El documento requiere revisión en el área de cliente.';
  return 'El pedido #'+data.order_id+' tiene un justificante de operación simulada aprobada. Referencia: '+String(data.reference||'no disponible')+
    '. Importe total: '+(Number(data.total_cents)/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'})+
    '. Puedes abrir el pedido desde tu historial para acceder a sus opciones de documento. Esto NO es una factura fiscal ni acredita un cobro real.';
 }catch(error){console.warn('[UCO 7.1I] Justificante:',error);return 'No he podido consultar ese justificante en este momento.';}
 finally{clearTimeout(timer)}
}
// 7.1J: contexto de pedidos aislado del contexto de catálogo.
// Solo guardamos el ID, no datos personales, importes ni documentos.
let lastOwnOrderId=null;
function parseOrderId(value){
 const n=normalizeUco(value);
 const m=n.match(/\b(?:pedido|orden|numero|num|n)\s*(?:numero\s*)?#?\s*(\d{1,10})\b/);
 return m?Number(m[1]):null;
}
function orderQuestion(value){
 const n=normalizeUco(value);
 const order=/\b(pedido|pedidos|compra|compras|encargo|encargos|historial)\b/.test(n);
 const own=/\b(mi|mis|mio|mios|historial|ultimo|ultima|ultimos|anteriores|estado|seguimiento|numero|reciente|detalles|detalle|productos|articulos|total|importe|fecha)\b/.test(n);
 const instruction=/\b(como|donde|crear|hacer|modificar|cancelar|anadir|asignar|editar|borrar)\b/.test(n);
 if(instruction)return false;
 if(order&&(own||parseOrderId(value)!==null))return true;
 return lastOwnOrderId!==null&&/^(y |y el |y la |que |cual |cuanto |cuantos |cuando |estado|importe|total|fecha|productos|articulos|detalles)/.test(n)
   && /\b(estado|importe|total|fecha|productos|articulos|detalles|detalle|lleva|coste|cuesta|tiene|contiene)\b/.test(n);
}
function safeOrderDate(v){return typeof v==='string'?v.slice(0,10):''}
function cents(v){return Number.isFinite(Number(v))?(Number(v)/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'}):'Importe no disponible'}
function summarizeOwnOrder(o,details){
 const lines=Array.isArray(o.items)?o.items:[];
 let output='• Pedido #'+Number(o.id)+' · '+safeOrderDate(o.created_at)+' · '+String(o.status||'Sin estado')+' · '+cents(o.subtotal_cents);
 if(o.fulfillment_state)output+=' · Entrega: '+String(o.fulfillment_state);
 if(details&&lines.length)output+='\n'+lines.slice(0,8).map(x=>'  '+String(x.name||'Producto')+' × '+Number(x.quantity||0)).join('\n');
 else if(lines.length)output+='\n  '+lines.slice(0,3).map(x=>String(x.name||'Producto')).join(', ');
 if(details&&lines.length>8)output+='\n  …y '+(lines.length-8)+' líneas más';
 return output;
}
async function fetchOwnOrderHistory(){
 const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),8000);
 try{
  const response=await fetch('/api/orders.php?action=history',{method:'GET',credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}});
  if(response.status===401)return {error:'Inicia sesión en tu cuenta de cliente para consultar tus pedidos.'};
  if(!response.ok)throw Error('HTTP '+response.status);
  const result=await response.json();if(!Array.isArray(result.orders))throw Error('Respuesta no válida');
  return {orders:result.orders};
 }catch(error){console.warn('[UCO 7.1J] Historial:',error);return {error:'No puedo consultar tus pedidos en este momento.'}}
 finally{clearTimeout(timer)}
}
async function askOwnOrders(value){
 const data=await fetchOwnOrderHistory();if(data.error)return data.error;
 const n=normalizeUco(value),explicit=parseOrderId(value);
 const follow=lastOwnOrderId!==null&&/^(y |y el |y la |que |cual |cuanto |cuantos |cuando |estado|importe|total|fecha|productos|articulos|detalles)/.test(n);
 const detail=/\b(detalle|detalles|productos|articulos|contiene|tiene)\b/.test(n);
 let orders=data.orders;
 if(explicit!==null)orders=orders.filter(o=>Number(o.id)===explicit);
 else if(follow)orders=orders.filter(o=>Number(o.id)===lastOwnOrderId);
 else if(/\b(ultimo|ultima|reciente)\b/.test(n))orders=orders.slice(0,1);
 else orders=orders.slice(0,5);
 if(!orders.length){if(explicit!==null)return 'Ese pedido no aparece entre los pedidos de tu cuenta.';return 'No encuentro pedidos que coincidan en tu historial.'}
 if(orders.length===1)lastOwnOrderId=Number(orders[0].id);
 else lastOwnOrderId=null; // Evita resolver «ese pedido» de forma arbitraria.
 return 'Pedidos de tu cuenta:\n'+orders.map(o=>summarizeOwnOrder(o,detail)).join('\n')+'\nNo he realizado cambios.';
}
async function askOwnDocuments(value){
 const n=normalizeUco(value);
 const explicit=parseOrderId(value);
 if(explicit!==null){lastOwnOrderId=explicit;return askOwnReceipt('justificante pedido '+explicit)}
 if(lastOwnOrderId!==null&&/\b(ese|este|anterior|ultimo|del|su|lo)\b/.test(n))return askOwnReceipt('justificante pedido '+lastOwnOrderId);
 const data=await fetchOwnOrderHistory();if(data.error)return data.error;
 if(!data.orders.length)return 'No aparecen pedidos en tu historial. No puedo listar justificantes sin pedidos asociados.';
 const list=data.orders.slice(0,5).map(o=>'• Pedido #'+Number(o.id)+' · '+safeOrderDate(o.created_at)+' · '+String(o.status||'Sin estado')).join('\n');
 return 'Estos son tus pedidos recientes:\n'+list+'\nIndica el número de pedido para comprobar si existe un justificante de operación simulada aprobada. No puedo emitir facturas fiscales.';
}
// 7.1K: enrutamiento contextual por dominios sin mezclar datos privados.
// Los pedidos solo se obtienen por la API autenticada; el catálogo no recibe IDs de cliente.
function contextualRoute(value){
 const n=normalizeUco(value);
 if(isOwnDocumentQuery(value))return 'document';
 if(orderQuestion(value))return 'order';
 const catalog=readCatalogContext();
 const product=/\b(producto|productos|patata|patatas|croqueta|croquetas|tomate|tomates|catalogo|categoria|categorias|variedad|variedades)\b/.test(n);
 const order=/\b(pedido|pedidos|compra|compras|envio|entrega|transportista|historial)\b/.test(n);
 const price=/\b(precio|precios|cuanto|cuantos|cuesta|cuestan|vale|valen|tarifa|importe|coste)\b/.test(n);
 // Un seguimiento de importe del pedido tiene prioridad si se nombra el pedido.
 if(order && lastOwnOrderId!==null && /\b(importe|total|coste|cuanto|cuesta|estado|fecha|productos|articulos)\b/.test(n)
    && !/\b(como|donde|modificar|cambiar|crear)\b/.test(n))return 'order';
 // Una pregunta explícita de producto rompe el contexto de pedido.
 if(product)return 'catalog';
 // Preguntas sobre precios de producto mantienen el contexto de catálogo,
 // salvo cuando se está siguiendo explícitamente un pedido.
 if(price && catalog && !order)return 'catalog';
 return 'general';
}
function isOwnDocumentQuery(value){
 const n=normalizeUco(value);
 return /\b(justificante|comprobante|recibo|factura|facturas|pdf)\b/.test(n)
  && !/\b(como|donde|crear|emitir|modificar|hacer)\b/.test(n)
  && (/\b(mi|mis|pedido|compra|descargar|ver|consultar|obtener|mostrar|ultimo|ese|este)\b/.test(n)||parseOrderId(value)!==null);
}
form.addEventListener('submit',async e=>{
 e.preventDefault();const value=input.value.trim();if(!value||busy)return;
 append('user',value);input.value='';busy=true;
 const submit=form.querySelector('button[type="submit"]');if(submit)submit.disabled=true;
 try{
  if(isReset(value)){
   clearCatalogContext();lastOwnOrderId=null;lastComparedProducts=null;provisionalBudget=null;pendingCartTransfer=null;lastCartReference=null;append('bot','He borrado el contexto de esta conversación. ¿Qué necesitas consultar?');
  }else{
   const budgetCommand=area==='client'?(parseBudgetCommand(value)||cartContextCommand(value)):null;
   if(!budgetCommand)lastCartReference=null;
   const budgetReply=budgetCommand?await updateProvisionalBudget(budgetCommand):null;
   if(budgetReply!==null){append('bot',budgetReply);return;}
   const compareFollowup=lastComparedProducts&&isComparisonFollowup(value);
   const prior=readCatalogContext();
   const follow=isFollowup(value);
   const routeHint=contextualRoute(value);
   const query=follow&&prior&&routeHint!=='order'&&routeHint!=='document'?value+' '+prior.term:value;
   const route=contextualRoute(value);
   const multiEstimate=route!=='order'&&route!=='document'?await askMultiQuantityEstimate(value):null;
   const estimate=multiEstimate!==null?multiEstimate:(route!=='order'&&route!=='document'?await askQuantityEstimate(value):null);
   const reply=estimate!==null?estimate:(route==='document'?await askOwnDocuments(value):(route==='order'?await askOwnOrders(value):(compareFollowup?await askComparedPrices():(guideFromQuestion(value)||await askUcoLogic(query)))));
   append('bot',reply);
  }
 }finally{busy=false;if(submit)submit.disabled=false}
});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden)minimize()});
let scheduled=false;function reflow(){if(scheduled)return;scheduled=true;requestAnimationFrame(()=>{scheduled=false;place(launch,'launch');if(!panel.hidden)place(panel,'panel');if(!hint.hidden)placeHint()})}
window.addEventListener('resize',reflow);window.visualViewport?.addEventListener('resize',reflow);
window.visualViewport?.addEventListener('scroll',reflow);
place(launch,'launch');
function maybeInvite(){const t=Date.now();if(panel.hidden&&document.visibilityState==='visible'&&state.closedAt&&t-state.closedAt>=12*60*1000&&t-(state.invitedAt||0)>=12*60*1000){showHint();state.invitedAt=t;save()}}
maybeInvite();setInterval(maybeInvite,60000);
})();
