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
 :[['Inicio','#inicio'],['Catálogo','#catalogo'],['Pedidos','#pedidos'],['Historial','#historial'],['Cuenta','#cuenta']];
function navigateShortcut(label,selector){
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
 b.setAttribute('aria-label','Ir a '+label);
 b.addEventListener('click',()=>navigateShortcut(label,selector));actions.append(b);
});
actions.style.setProperty('--uco-ai-shortcut-count',String(shortcuts.length));
// Each draggable surface uses viewport-fixed coordinates; no transforms or parent-relative offsets.
const margin=8;const coords={launch:read(POS+'_launch',null),panel:read(POS+'_panel',null)};
function size(){return {w:document.documentElement.clientWidth,h:window.visualViewport?.height||innerHeight,offsetTop:window.visualViewport?.offsetTop||0}}
function clamp(el,x,y){const {w,h,offsetTop}=size(),r=el.getBoundingClientRect();return {x:Math.max(margin,Math.min(Math.max(margin,w-r.width-margin),x)),y:Math.max(offsetTop+margin,Math.min(Math.max(offsetTop+margin,offsetTop+h-r.height-margin),y))}}
function apply(el,xy){const p=clamp(el,xy.x,xy.y);el.style.setProperty('left',p.x+'px','important');el.style.setProperty('top',p.y+'px','important');el.style.setProperty('right','auto','important');el.style.setProperty('bottom','auto','important');return p}
function defaultPosition(el,kind){const {w,h,offsetTop}=size(),r=el.getBoundingClientRect();if(kind==='launch')return {x:w-r.width-16,y:offsetTop+h-r.height-(mobile()?Math.max(75,parseInt(getComputedStyle(document.documentElement).getPropertyValue('--uco-ai-mobile-clearance'))||75):110)};return {x:w-r.width-16,y:offsetTop+h-r.height-(mobile()?10:96)}}
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
async function askUcoLogic(question){
 const ctrl=new AbortController(),timer=setTimeout(()=>ctrl.abort(),8500);
 try{
  const response=await fetch('/api/assistant-logic-71e.php?'+new URLSearchParams({q:question}),{
   credentials:'same-origin',cache:'no-store',signal:ctrl.signal,headers:{Accept:'application/json'}
  });
  if(!response.ok)throw Error('HTTP '+response.status);
  const data=await response.json();if(!data.ok)throw Error(data.error||'API no disponible');
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
   if(choices.length===2 && choices.every(list=>list.length===1 && !list[0].is_demo)
      && Number(choices[0][0].id)!==Number(choices[1][0].id)){
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
  if(!data.items.length)return 'No hay precios comerciales disponibles para esas referencias.';
  const rows=data.items.slice(0,9).map(p=>{
   const amount=p.price_cents===null?'Sin precio asignado':(Number(p.price_cents)/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
   return '• '+String(p.name||'Producto')+': '+amount+(p.unit?' / '+p.unit:'');
  });
  return 'Precios según tu cuenta de cliente:\n'+rows.join('\n')+'\nLos importes pueden variar hasta confirmar el pedido.';
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
   clearCatalogContext();lastOwnOrderId=null;append('bot','He borrado el contexto de esta conversación. ¿Qué necesitas consultar?');
  }else{
   const prior=readCatalogContext();
   const follow=isFollowup(value);
   const routeHint=contextualRoute(value);
   const query=follow&&prior&&routeHint!=='order'&&routeHint!=='document'?value+' '+prior.term:value;
   const route=contextualRoute(value);
   const reply=route==='document'?await askOwnDocuments(value):(route==='order'?await askOwnOrders(value):(guideFromQuestion(value)||await askUcoLogic(query)));
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
