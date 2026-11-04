/* UCO: borrador conversacional, sin pedidos ni pagos automáticos. */
(()=>{'use strict';
const root=document.querySelector('#uco-assistant-root');if(!root||root.dataset.area==='admin')return;
const area=root.dataset.area, form=root.querySelector('.uco-ai-form'), log=root.querySelector('.uco-ai-messages');
const CHECKOUT='uco_client_checkout_float_v2';
const KEY='uco_prepedido_v1', SEARCH='uco_catalog_search_v1', PRIORITY='uco_catalog_priority_v1';
function publishPriority(terms){const cleanTerms=[...new Set(terms.map(t=>String(t||'').trim()).filter(Boolean))].slice(0,100);try{sessionStorage.setItem(PRIORITY,JSON.stringify(cleanTerms));sessionStorage.removeItem(SEARCH)}catch{};document.dispatchEvent(new CustomEvent('uco:assistant-catalog-priority',{detail:{terms:cleanTerms}}))}
let lastSearch=null; // Solo se limpia la búsqueda; el prepedido permanece intacto.
let searchEpoch=0; // Invalida respuestas tardías al abrir un chat nuevo.
let resubmittingLegacy=false;
const clean=s=>String(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money=n=>(n/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
let draft=[];try{const old=JSON.parse(sessionStorage.getItem(KEY)||'[]');if(Array.isArray(old))draft=old.filter(x=>Number.isSafeInteger(x.id)&&x.id>0&&Number.isSafeInteger(x.qty)&&x.qty>0).slice(0,100)}catch{}
const save=()=>{sessionStorage.setItem(KEY,JSON.stringify(draft));if(area==='client'&&draft.length){try{sessionStorage.setItem(CHECKOUT,JSON.stringify({stage:'draft'}))}catch{}}queueMicrotask(()=>{if(typeof updateDraftButton==='function')updateDraftButton()});if(area==='client'&&document.querySelector('#app uco-client-panel')){if(draft.length){void show();document.dispatchEvent(new CustomEvent('uco:assistant-checkout-mode',{detail:{active:true,stage:'draft',message:'Tienes un prepedido pendiente. Pulsa «Enviar al carrito» para revisarlo.'}}));}else{let saved;try{saved=JSON.parse(sessionStorage.getItem(CHECKOUT)||'null')}catch{}if(saved?.stage!=='cart')document.dispatchEvent(new CustomEvent('uco:assistant-checkout-mode',{detail:{active:false}}))}}};
function note(s){const d=document.createElement('div');d.className='uco-ai-msg';d.dataset.who='bot';d.textContent=s;log.append(d);log.scrollTop=log.scrollHeight}
// Los controles comerciales viven fuera de los mensajes conversacionales.
const workspace=document.createElement('aside');workspace.className='uco-ai-workspace';workspace.hidden=true;workspace.setAttribute('aria-label','Productos y prepedido');
workspace.innerHTML='<div class="uco-ai-workspace-head"><strong>Productos y prepedido</strong><button type="button" aria-label="Cerrar panel comercial">×</button></div><div class="uco-ai-workspace-body"></div>';
root.append(workspace);
const workspaceBody=workspace.querySelector('.uco-ai-workspace-body');
if(area==='client')document.addEventListener('uco:assistant-checkout-mode',e=>{if(e.detail?.active!==false&&e.detail?.stage==='draft'&&draft.length){workspace.hidden=false;requestAnimationFrame(placeWorkspace)}else if(e.detail?.stage==='cart'||e.detail?.active===false){workspace.hidden=true}});
workspace.querySelector('button').onclick=()=>{workspace.hidden=true};
const workspacePositionKey='uco_ai_workspace_position_v2';
let workspaceManual=null;
try{const saved=JSON.parse(localStorage.getItem(workspacePositionKey)||'null');if(saved&&Number.isFinite(saved.x)&&Number.isFinite(saved.y))workspaceManual=saved}catch{}
function workspaceAt(x,y){
 const r=workspace.getBoundingClientRect(),margin=8;
 const px=Math.max(margin,Math.min(Math.max(margin,innerWidth-r.width-margin),x));
 const py=Math.max(margin,Math.min(Math.max(margin,innerHeight-r.height-margin),y));
 workspace.style.setProperty('left',px+'px','important');workspace.style.setProperty('top',py+'px','important');
 workspace.style.setProperty('bottom','auto','important');workspace.style.setProperty('right','auto','important');
}
function placeWorkspace(){
 if(workspace.hidden)return;
 if(area==='client'&&root.classList.contains('uco-ai-checkout-mode')){
  const mobile=innerWidth<=650;
  const w=mobile?Math.min(innerWidth-16,540):Math.min(540,Math.max(300,innerWidth-360));
  workspace.style.setProperty('width',w+'px','important');
  if(workspaceManual){workspaceAt(workspaceManual.x,workspaceManual.y);return}
  const hint=root.querySelector('.uco-ai-hint');
  if(mobile){const h=workspace.getBoundingClientRect().height;workspaceAt((innerWidth-w)/2,Math.max(8,(innerHeight-h-155)/2))}
  else{const robotW=hint?.getBoundingClientRect().width||340;const gap=24,total=w+robotW+gap;workspaceAt(Math.max(8,(innerWidth-total)/2),Math.max(8,(innerHeight-workspace.getBoundingClientRect().height)/2))}
  return;
 }
 const panel=root.querySelector('.uco-ai-panel');if(!panel||panel.hidden)return;
 const r=panel.getBoundingClientRect(),width=Math.min(592,Math.max(310,innerWidth-r.width-30));
 const left=r.right+width+12<=innerWidth-8?r.right+12:r.left-width-12>=8?r.left-width-12:Math.max(8,(innerWidth-width)/2);
 workspace.style.width=width+'px';workspaceAt(left,Math.max(8,Math.min(r.top,innerHeight-workspace.offsetHeight-8)));
}
(function enableWorkspaceDrag(){
 const head=workspace.querySelector('.uco-ai-workspace-head');let drag=null;
 head.style.touchAction='none';head.style.cursor='grab';
 head.addEventListener('pointerdown',e=>{if(e.button!==0||e.target.closest('button'))return;const r=workspace.getBoundingClientRect();drag={id:e.pointerId,dx:e.clientX-r.left,dy:e.clientY-r.top,moved:false,sx:e.clientX,sy:e.clientY};try{head.setPointerCapture(e.pointerId)}catch{}});
 head.addEventListener('pointermove',e=>{if(!drag||drag.id!==e.pointerId)return;if(Math.hypot(e.clientX-drag.sx,e.clientY-drag.sy)>5)drag.moved=true;if(drag.moved){e.preventDefault();workspaceAt(e.clientX-drag.dx,e.clientY-drag.dy)}});
 const finish=e=>{if(!drag||drag.id!==e.pointerId)return;if(drag.moved){const r=workspace.getBoundingClientRect();workspaceManual={x:r.left,y:r.top};try{localStorage.setItem(workspacePositionKey,JSON.stringify(workspaceManual))}catch{}}drag=null;try{head.releasePointerCapture(e.pointerId)}catch{}};
 head.addEventListener('pointerup',finish);head.addEventListener('pointercancel',finish);
})();

function reveal(){workspace.hidden=false;requestAnimationFrame(placeWorkspace)}
function card(kind='draft'){workspaceBody.querySelectorAll('[data-uco-card="'+kind+'"]').forEach(x=>x.remove());const d=document.createElement('div');d.className='uco-prepedido';d.dataset.ucoCard=kind;workspaceBody.append(d);reveal();return d}
window.addEventListener('resize',()=>{if(!workspace.hidden)placeWorkspace()});document.addEventListener('uco:workspace-reflow',()=>{if(!workspace.hidden)placeWorkspace()});
root.querySelector('.uco-ai-head')?.addEventListener('pointerup',()=>{if(!workspace.hidden)requestAnimationFrame(placeWorkspace)});
root.querySelector('.uco-ai-close')?.addEventListener('click',()=>{workspace.hidden=true});
root.querySelector('.uco-ai-minimize')?.addEventListener('click',()=>{workspace.hidden=true});
// Recover the commercial stage only after the authenticated client panel is mounted.
// Never trigger transfer, reservation, or payment during restoration.
if(area==='client'){
 let mounted=false;
 const activate=()=>{
  if(mounted||!document.querySelector('#app uco-client-panel'))return;
  mounted=true;
  let saved=null;try{saved=JSON.parse(sessionStorage.getItem(CHECKOUT)||'null')}catch{}
  const stage=draft.length?'draft':(saved?.stage==='cart'?'cart':null);
  if(stage){
   if(stage==='draft'&&draft.length){void show().then(()=>{requestAnimationFrame(placeWorkspace)}).catch(err=>note('No se pudo mostrar el prepedido: '+err.message))}
   const message=stage==='draft'?'Tienes un prepedido pendiente. Pulsa «Enviar al carrito» para revisarlo.':'Continúa la compra desde el carrito. Comprueba las cantidades y los siguientes pasos.';
   document.dispatchEvent(new CustomEvent('uco:assistant-checkout-mode',{detail:{active:true,stage,message}}));
   try{sessionStorage.setItem(CHECKOUT,JSON.stringify({stage}))}catch{}
  }else if(saved){try{sessionStorage.removeItem(CHECKOUT)}catch{}}
 };
 activate();const app=document.getElementById('app');
 if(app){const observer=new MutationObserver(()=>{activate();if(mounted)observer.disconnect()});observer.observe(app,{childList:true,subtree:true})}
 document.addEventListener('uco:assistant-request-transfer',()=>{if(draft.length)void transferCart()});
}
const search=async q=>{const r=await fetch('/api/assistant-logic-71e.php?'+new URLSearchParams({q}),{credentials:'same-origin',cache:'no-store'});if(!r.ok)throw Error('No se pudo consultar el catálogo');const data=await r.json();return (data.items||[]).filter(x=>!x.is_demo).slice(0,8)};
async function prices(ids){const result=new Map();if(area!=='client')return result;for(let i=0;i<ids.length;i+=12){const r=await fetch('/api/assistant-prices-71i.php?'+new URLSearchParams({ids:ids.slice(i,i+12).join(',')}),{credentials:'same-origin',cache:'no-store'});if(!r.ok)throw Error(r.status===401?'Inicia sesión para consultar tu tarifa':'No se pudo verificar la tarifa');const d=await r.json();for(const p of d.items||[])result.set(Number(p.id),p)}return result}
function segments(q){let s=q.replace(/^[\s]*(?:quiero|necesito|dame|buscame|busca|anade|añade|tambien quiero|y tambien|me gustaria|pedido de|comprar|pedir)\s+/i,'');return s.split(/\s*(?:,|;|\s+y\s+(?=(?:\d+\s+)?[a-záéíóúñ]))\s*/i).map(x=>x.replace(/^(?:tambien|ademas|unos?|unas?|de|del|el|la|los|las)\s+/i,'').trim()).filter(Boolean).slice(0,100)}
function qtyTerm(t){const m=t.match(/^(\d+)\s+(?:(?:cajas?|bolsas?|bandejas?|unidades?|uds?)\s+(?:de\s+)?)?(.+)$/i);return m?{qty:Math.max(1,Math.min(99999,Number(m[1]))),term:m[2]}:{qty:1,term:t}}
function presentation(p){const v=p.presentacion;if(!v)return String(p.unit||'Unidad sin especificar');if(typeof v==='string')return v;return [v.descripcion,v.etiqueta,v.formato,v.peso_total_kg?`${v.peso_total_kg} kg`:null].filter(Boolean).join(' · ')||String(p.unit||'Unidad sin especificar')}
async function show(){const d=card();d.innerHTML='<strong>Prepedido · comprobando tarifas…</strong>';if(!draft.length){d.textContent='Tu prepedido está vacío.';return}let p=new Map();try{p=await prices([...new Set(draft.map(x=>x.id))])}catch(e){note(e.message)}let total=0,complete=area==='client';d.replaceChildren();const h=document.createElement('strong');h.textContent='Tu prepedido · '+draft.length+' referencias';d.append(h);
const linesContainer=document.createElement('div');linesContainer.className='uco-prepedido-lines';d.append(linesContainer);
for(const x of draft){const row=p.get(x.id);const line=document.createElement('div');line.className='uco-prepedido-line';const price=row?.price_cents;if(!Number.isSafeInteger(price))complete=false;else total+=price*x.qty;line.innerHTML=`<span>${esc(x.name)}<small>${esc(x.ref||'')} · ${esc(presentation(row||x))}</small></span><span>${Number.isSafeInteger(price)?money(price)+' / '+esc(row.unit||'ud.'):'Tarifa al iniciar sesión'}</span>`;const qty=document.createElement('input');qty.type='number';qty.min='1';qty.max='99999';qty.value=x.qty;qty.setAttribute('aria-label','Cantidad de '+x.name);qty.onchange=()=>{const n=Number(qty.value);if(!Number.isSafeInteger(n)||n<1||n>99999){qty.value=x.qty;return}x.qty=n;save();show()};const remove=document.createElement('button');remove.type='button';remove.textContent='Quitar';remove.onclick=()=>{draft=draft.filter(z=>z.id!==x.id);save();show()};line.append(qty,remove);linesContainer.append(line)}
const footer=document.createElement('p');footer.textContent=complete?'Total provisional: '+money(total)+' · sujeto a validación de stock y precios.':'Los precios y el total se mostrarán al identificarte como cliente.';d.append(footer);
const transfer=document.createElement('button');transfer.type='button';transfer.textContent=area==='client'?'Pasar al carrito':'Ir al área de clientes con el prepedido';transfer.onclick=()=>area==='client'?transferCart():location.assign('/clientes/');d.append(transfer);
requestAnimationFrame(()=>{const rows=[...linesContainer.querySelectorAll('.uco-prepedido-line')];if(rows.length>3){const styles=getComputedStyle(linesContainer);const gap=parseFloat(styles.rowGap)||0;linesContainer.style.maxHeight=(rows.slice(0,3).reduce((sum,row)=>sum+row.getBoundingClientRect().height,0)+2*gap+2)+'px'}else{linesContainer.style.maxHeight='none'}});
const clear=document.createElement('button');clear.type='button';clear.textContent='Vaciar prepedido';clear.onclick=()=>{if(!confirm('¿Vaciar el prepedido?'))return;draft=[];save();show()};d.append(clear);workspaceBody.scrollTop=workspaceBody.scrollHeight}
async function transferCart(){if(!draft.length)return;const id='uco-pre-'+Date.now();const lines=draft.map(x=>({id:x.id,quantity:x.qty}));note('Enviando las referencias al carrito para su validación…');const response=await new Promise(resolve=>{const listener=e=>{if(e.detail?.id!==id)return;clearTimeout(timeout);document.removeEventListener('uco:budget-cart-result',listener);resolve(e.detail)};const timeout=setTimeout(()=>{document.removeEventListener('uco:budget-cart-result',listener);resolve({ok:false,message:'El carrito no ha respondido'})},25000);document.addEventListener('uco:budget-cart-result',listener);document.dispatchEvent(new CustomEvent('uco:budget-cart-transfer',{detail:{id,lines}}))});if(response.ok){
  draft=[];save();workspace.hidden=true;workspaceBody.replaceChildren();
  try{sessionStorage.setItem(CHECKOUT,JSON.stringify({stage:'cart'}))}catch{}
  note('El prepedido se ha trasladado al carrito. Te lo abro ahora para revisar las referencias, cantidades y el importe actualizado. Puedes seguir preguntándome durante la compra.');
  document.dispatchEvent(new CustomEvent('uco:assistant-checkout-mode',{detail:{active:true,stage:'cart',message:'Prepedido transferido. Comprueba ahora los artículos y el total del carrito.'}}));
  document.dispatchEvent(new CustomEvent('uco:assistant-open-transferred-cart'));
 }else{
  note(String(response.message||'No se pudo completar el traspaso')+'. Comprueba el carrito antes de reintentar. No he borrado el prepedido.');
 }}
async function find(q){const epoch=searchEpoch;const terms=segments(q);if(!terms.length)return;publishPriority(terms.map(t=>qtyTerm(t).term));workspaceBody.querySelectorAll('[data-uco-card=search]').forEach(x=>x.remove());lastSearch=q;const d=card('search');d.innerHTML='<strong>Buscando productos…</strong>';for(const termRaw of terms){const {qty,term}=qtyTerm(termRaw);const box=document.createElement('div');box.className='uco-prepedido-choice';d.append(box);try{const results=await search(term);if(epoch!==searchEpoch)return;box.replaceChildren();const label=document.createElement('strong');label.textContent=term;box.append(label);if(!results.length){box.append(document.createTextNode(' · Sin coincidencias precisas. Prueba otro nombre.'));continue}const sel=document.createElement('select');sel.setAttribute('aria-label','Presentación de '+term);for(const p of results){const o=document.createElement('option');o.value=p.id;o.textContent=p.name+' · '+(p.reference||'')+' · '+(p.unit||'');sel.append(o)}const count=document.createElement('input');count.type='number';count.min='1';count.max='99999';count.value=qty;count.setAttribute('aria-label','Cantidad de '+term);const add=document.createElement('button');add.type='button';add.textContent='Añadir';const pricesMap=await prices(results.map(p=>Number(p.id)));if(epoch!==searchEpoch)return;const detail=document.createElement('small');const refresh=()=>{const p=results.find(x=>String(x.id)===sel.value),r=pricesMap.get(Number(p.id));detail.textContent=r&&Number.isSafeInteger(r.price_cents)?money(r.price_cents)+' / '+(r.unit||'ud.')+' · '+presentation(r):'Precio según tarifa del cliente · '+(p.unit||'unidad')};sel.onchange=refresh;refresh();add.onclick=()=>{const n=Number(count.value),p=results.find(x=>String(x.id)===sel.value);if(!Number.isSafeInteger(n)||n<1||n>99999){note('Introduce una cantidad entera válida.');return}const old=draft.find(x=>x.id===Number(p.id));if(old){if(old.qty+n>99999){note('Cantidad máxima superada.');return}old.qty+=n}else{if(draft.length>=100){note('Máximo de 100 referencias por prepedido.');return}draft.push({id:Number(p.id),qty:n,name:p.name,ref:p.reference,unit:p.unit})}save();note('Añadido: '+p.name+'. Puedes seguir pidiendo productos o escribir «ver prepedido».');show()};box.append(sel,count,detail,add);
// La búsqueda del bot ordena el catálogo; nunca lo reduce a una sola coincidencia.
}catch(e){if(epoch!==searchEpoch)return;box.textContent='No se pudo consultar «'+term+'»: '+e.message}}if(epoch!==searchEpoch)return;d.firstChild?.remove();workspaceBody.scrollTop=workspaceBody.scrollHeight;note('Te muestro las opciones en el panel comercial. Elige presentación y cantidad; puedes añadir más productos cuando quieras.')}
// El historial se obtiene del servidor autenticado, nunca de almacenamiento compartido.
async function history(){
 if(area!=='client'){note('Para consultar tus pedidos anteriores, inicia sesión en el área de clientes.');const d=card('history');const b=document.createElement('button');b.type='button';b.textContent='Ir al área de clientes';b.onclick=()=>location.assign('/clientes/');d.append(b);return}
 const d=card('history');d.textContent='Consultando tus últimos pedidos…';
 try{
  const r=await fetch('/api/orders.php?action=history',{credentials:'same-origin',cache:'no-store'});
  if(!r.ok)throw Error(r.status===401?'Debes iniciar sesión':'No se pudo consultar el historial');
  const data=await r.json();d.replaceChildren();const orders=(data.orders||[]).slice(0,5);
  const title=document.createElement('strong');title.textContent='Mis últimos pedidos';d.append(title);
  if(!orders.length){d.append(document.createTextNode('Todavía no tienes pedidos registrados.'));return}
  for(const order of orders){
   const line=document.createElement('div');line.className='uco-prepedido-history';
   const heading=document.createElement('strong');heading.textContent='PED-'+String(order.id).padStart(2,'0')+' · '+String(order.created_at||'').slice(0,16);line.append(heading);
   const info=document.createElement('small');info.textContent=(order.items||[]).length+' referencias · '+money(Number(order.subtotal_cents)||0)+' (importe histórico)';line.append(info);
   const details=document.createElement('details');const summary=document.createElement('summary');summary.textContent='Ver productos';details.append(summary);
   for(const item of order.items||[]){const itemLine=document.createElement('small');itemLine.textContent=item.name+' · '+item.quantity+' ud. · '+(item.sku||'');details.append(itemLine)}line.append(details);
   const repeat=document.createElement('button');repeat.type='button';repeat.textContent='Preparar de nuevo';repeat.onclick=()=>repeatOrder(order,repeat);line.append(repeat);d.append(line);
  }
  const more=document.createElement('button');more.type='button';more.textContent='Ver historial completo';more.onclick=()=>{const target=document.querySelector('#uco-purchase-history');if(target&&!target.closest('[hidden]'))target.click();else note('Abre el historial desde el menú Carrito de tu área de clientes.')};d.append(more);
 }catch(e){d.textContent=e.message}
}
async function repeatOrder(order,button){
 if(!confirm('¿Preparar de nuevo las referencias del pedido PED-'+order.id+'? Se añadirán al prepedido actual, con precios y stock vigentes.'))return;
 button.disabled=true;let added=0;const pending=[];
 try{
  for(const item of order.items||[]){
   const sku=String(item.sku||'').trim(),n=Number(item.quantity);
   if(!sku||!Number.isSafeInteger(n)||n<1||n>99999){pending.push(item.name);continue}
   const found=await search(sku);
   const match=found.find(x=>String(x.reference||'').trim().toLowerCase()===sku.toLowerCase());
   if(!match){pending.push(item.name+' ('+sku+')');continue}
   const id=Number(match.id),existing=draft.find(x=>x.id===id);
   if(existing){if(existing.qty+n>99999){pending.push(item.name);continue}existing.qty+=n}
   else{if(draft.length>=100){pending.push(item.name);continue}draft.push({id,qty:n,name:match.name,ref:match.reference,unit:match.unit})}
   added++;
  }
  save();note(added+' referencias recuperadas del pedido PED-'+order.id+'. '+(pending.length?'Sin coincidencia exacta o sin capacidad: '+pending.join(', ')+'. ':'')+'Revisa las cantidades y los precios actuales antes de pasar al carrito.');await show();
 }catch(e){save();note('No se pudo terminar de recuperar el pedido: '+e.message+'. Comprueba el prepedido antes de reintentar.');await show()}
 finally{button.disabled=false}
}
function newSearch(){
 // Nuevo chat: elimina mensajes y resultados, pero conserva el prepedido comercial.
 searchEpoch++;
 publishPriority([]);
 lastSearch=null;
 try{sessionStorage.removeItem(SEARCH)}catch{}
 log.replaceChildren();
 workspaceBody.replaceChildren();workspace.hidden=true;
 document.dispatchEvent(new CustomEvent('uco:assistant-new-chat'));
 // La conversación se reinicia, pero las referencias elegidas siguen disponibles.
 // No se restaura la búsqueda antigua ni se convierte el prepedido en texto del chat.
 if(draft.length){
   note('Chat nuevo. Tu prepedido sigue guardado. Puedes revisarlo en el panel comercial o buscar más productos.');
   void show();
 }
 const input=form.querySelector('input');if(input){input.value='';input.focus({preventScroll:true})}
}

if(area==='client')document.addEventListener('uco:assistant-purchase-summary',e=>{if(typeof e.detail?.message==='string'){note(e.detail.message);document.dispatchEvent(new CustomEvent('uco:assistant-checkout-guidance',{detail:{message:e.detail.message}}))}});
// El asistente sigue activo mientras el cliente utiliza el carrito nativo.
if(area==='client')document.addEventListener('uco:client-purchase-stage',event=>{
 const detail=event.detail||{};
 if(!draft.length&&detail.stage!=='receipt'){try{sessionStorage.setItem(CHECKOUT,JSON.stringify({stage:'cart'}))}catch{}}
 if(detail.stage==='receipt'){try{sessionStorage.removeItem(CHECKOUT)}catch{};document.dispatchEvent(new CustomEvent('uco:assistant-checkout-mode',{detail:{active:false}}));}
 document.dispatchEvent(new CustomEvent('uco:assistant-checkout-guidance',{detail:{message:({review:'Revisa artículos, cantidades y total del carrito.',checkout:'Comprueba los datos de entrega y las existencias.',reservation:'Consulta el estado de la reserva antes de pagar.',payment:'Elige el método de pago disponible.',confirmation:'Revisa el importe y confirma la operación segura.',receipt:'Consulta el justificante y el PDF del pedido.'})[detail.stage]||'Te acompaño durante la compra.'}}));
 if(detail.stage==='review')note('Ya tienes abierto el carrito. Comprueba los productos y las cantidades. El total definitivo dependerá de impuestos, transporte y condiciones aplicables. Puedes preguntarme por los siguientes pasos.');
 else if(detail.stage==='checkout')note('Estamos en la revisión de compra. Comprueba disponibilidad y datos de entrega antes de reservar. La reserva y el pago solo se ejecutan cuando tú los confirmas.');
 else if(detail.stage==='reservation')note('La reserva se ha solicitado. Comprueba su estado y el tiempo restante en el carrito; después podrás revisar las formas de pago disponibles.');
 else if(detail.stage==='payment')note('Ahora puedes consultar los métodos de pago disponibles. Selecciona el que prefieras y revisa el resumen final antes de confirmar.');
 else if(detail.stage==='confirmation')note('Ya puedes revisar el resumen final de compra. Comprueba importe, entrega y método elegido. Si aparece «entorno de pruebas», el pago será simulado; si hay pasarela real, continuarás con el proveedor seguro.');
 else if(detail.stage==='receipt')note('Cuando el pedido esté confirmado, utiliza las opciones de justificante o PDF disponibles en el área de clientes. Si aún no aparece, revisa el estado del pedido.');
});
function intent(q){const n=clean(q);if(/^(?:nuevo chat|nueva busqueda|buscar otro producto|empezar otra busqueda|limpiar busqueda)$/.test(n))return 'new';if(/^(?:mis pedidos|pedidos anteriores|historial|historial de pedidos|ultimo pedido|ver pedidos|ver mis pedidos|ver historial)$/.test(n))return 'history';if(/^(?:ver|mostrar|revisar|resumen)(?: el| mi)? prepedido$/.test(n))return 'show';if(/^(?:vaciar|borrar|cancelar)(?: el| mi)? prepedido$/.test(n))return 'clear';if(/^(?:pasar|enviar|llevar)(?: el| mi)? prepedido al carrito$/.test(n))return 'transfer';if(/^(?:quiero|necesito|dame|busca|buscame|anade|añade|tambien|ademas|y tambien|me gustaria|comprar|pedir)\b/.test(n))return 'find';return null}
form.addEventListener('submit',async e=>{
 if(resubmittingLegacy){resubmittingLegacy=false;return}
 const input=form.querySelector('input'),q=input?.value.trim();if(!q)return;
 const action=intent(q);
 // Los mensajes generales siguen usando el asistente original. Las búsquedas de
 // catálogo, incluso sin «quiero» (p. ej. «aros de cebolla»), van al panel lateral.
 const normalized=clean(q);
 const general=/^(?:ia\s*:|como |por que |cuando |donde |explica|ayuda|hola|buenas|gracias|que es |que significa |contacto|horario|factura|estado de |mi cuenta|mis datos|cambiar contrasena|administracion|politica|privacidad|envio|devolucion)/.test(normalized);
 const candidate=!action&&!general&&q.length<=110&&q.split(/\s+/).length<=12;
 if(!action&&!candidate)return;
 e.preventDefault();e.stopImmediatePropagation();
 if(candidate){
   // No interpretar cualquier frase como compra: confirmar coincidencias reales.
   const epoch=searchEpoch;
   try{
     const lookup=q.replace(/^(?:tienes|teneis|hay|busco|buscame|muestrame|ensename|quiero ver|quiero buscar)\s+/i,'').trim();
     const results=await search(lookup);
     if(epoch!==searchEpoch)return;
     if(!results.length){
       // Reentregar la consulta al asistente de texto cuando no hay producto.
       resubmittingLegacy=true;form.requestSubmit();return;
     }
   }catch{
     if(epoch!==searchEpoch)return;
     resubmittingLegacy=true;form.requestSubmit();return;
   }
 }
 input.value='';
 const msg=document.createElement('div');msg.className='uco-ai-msg';msg.dataset.who='user';msg.textContent=q;log.append(msg);
 try{
  if(action==='new')newSearch();
  else if(action==='history')await history();
  else if(action==='show')await show();
  else if(action==='clear'){draft=[];save();await show()}
  else if(action==='transfer')await (area==='client'?transferCart():Promise.resolve(location.assign('/clientes/')));
  else await find(candidate?q.replace(/^(?:tienes|teneis|hay|busco|buscame|muestrame|ensename|quiero ver|quiero buscar)\s+/i,'').trim():q);
 }catch(err){note('No se pudo completar la consulta: '+err.message)}
},true);
{const actions=root.querySelector('.uco-ai-actions');if(actions){const fresh=document.createElement('button');fresh.type='button';fresh.textContent='Nuevo chat';fresh.onclick=newSearch;actions.append(fresh);const past=document.createElement('button');past.type='button';past.textContent='Mis pedidos';past.onclick=history;actions.append(past);if(area==='public'){fresh.classList.add('uco-ai-new-chat');past.classList.add('uco-ai-my-orders')}}}
const draftButton=document.createElement('button');draftButton.type='button';draftButton.dataset.ucoDraftButton='';draftButton.onclick=show;(area==='public'?root.querySelector('.uco-ai-shortcut-menu'):root.querySelector('.uco-ai-actions'))?.append(draftButton);
function updateDraftButton(){draftButton.hidden=!draft.length;draftButton.textContent='Prepedido ('+draft.length+')';root.querySelector('.uco-ai-actions')?.style.setProperty('--uco-ai-shortcut-count',String(root.querySelectorAll('.uco-ai-actions button:not([hidden])').length))}
const originalSave=save; // El estado comercial se mantiene aunque cambie el chat.
// Actualizar los accesos cuando cambia el número de referencias.
const actionsObserver=new MutationObserver(updateDraftButton);actionsObserver.observe(root.querySelector('.uco-ai-actions'),{childList:true});
updateDraftButton();
root.addEventListener('click',()=>queueMicrotask(updateDraftButton));
})();
