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
async function consultCatalog(term){
 const ctrl=new AbortController(),timer=setTimeout(()=>ctrl.abort(),6500);
 try{
  const response=await fetch('/api/assistant-catalog-71a.php?q='+encodeURIComponent(term),{
   credentials:'same-origin',cache:'no-store',signal:ctrl.signal,headers:{'Accept':'application/json'}
  });
  if(!response.ok)throw Error('Consulta no disponible');
  const data=await response.json();
  if(!data.ok)throw Error('Respuesta no disponible');
  if(!data.items.length)return 'No he encontrado productos activos que coincidan con «'+term+'». Puedes consultar el catálogo completo.';
  return 'Productos encontrados en el catálogo UCO:\\n'+data.items.map(p=>
   '• '+p.name+' ('+p.unit+')'+(p.category?' · '+p.category:'')).join('\\n')+
   '\\nLos precios dependen de la tarifa del cliente. Aún no puedo calcular un presupuesto ni crear pedidos.';
 }catch{return 'No he podido consultar ahora el catálogo. Prueba de nuevo o utiliza la sección Catálogo.'}
 finally{clearTimeout(timer)}
}
form.addEventListener('submit',async e=>{e.preventDefault();const value=input.value.trim();if(!value)return;append('user',value);input.value='';const v=value.toLowerCase();let reply;
const search=v.match(/(?:busca|buscar|tienes|tenéis|teneis|producto|precio|catálogo|catalogo)\\s+(?:de\\s+|el\\s+|la\\s+|los\\s+|las\\s+)?(.{2,80})/i);
if(search){append('bot',await consultCatalog(search[1].replace(/[?.!]+$/,'')));return;}

if(/(pedido|carrito|comprar)/.test(v))reply=area==='admin'?'Gestiona los pedidos desde su panel. Todavía no puedo crearlos ni modificarlos.':'Puedes preparar tu compra desde el catálogo y el carrito; aún no puedo cursarla desde el chat.';
else if(/(pago|factura|justificante|devoluci)/.test(v))reply=area==='admin'?'Consulta Control de pagos. No puedo ejecutar operaciones bancarias.':'Consulta el estado de tus pedidos en el área de clientes. No compartas datos de tarjeta aquí.';
else if(/(producto|precio|catálogo|catalogo|buscar)/.test(v))reply='Consulta el catálogo para ver productos y precios actualizados. Todavía no tengo conexión a su contenido.';
else reply='Esta versión ofrece ayuda guiada; todavía no tiene interpretación inteligente ni acceso a datos de tu cuenta.';
append('bot',reply)});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden)minimize()});
let scheduled=false;function reflow(){if(scheduled)return;scheduled=true;requestAnimationFrame(()=>{scheduled=false;place(launch,'launch');if(!panel.hidden)place(panel,'panel');if(!hint.hidden)placeHint()})}
window.addEventListener('resize',reflow);window.visualViewport?.addEventListener('resize',reflow);
window.visualViewport?.addEventListener('scroll',reflow);
place(launch,'launch');
function maybeInvite(){const t=Date.now();if(panel.hidden&&document.visibilityState==='visible'&&state.closedAt&&t-state.closedAt>=12*60*1000&&t-(state.invitedAt||0)>=12*60*1000){showHint();state.invitedAt=t;save()}}
maybeInvite();setInterval(maybeInvite,60000);
})();
