/* UCO: borrador conversacional, sin pedidos ni pagos automáticos. */
(()=>{'use strict';
const root=document.querySelector('#uco-assistant-root');if(!root||root.dataset.area==='admin')return;
const area=root.dataset.area, form=root.querySelector('.uco-ai-form'), log=root.querySelector('.uco-ai-messages');
const KEY='uco_prepedido_v1', SEARCH='uco_catalog_search_v1', PRIORITY='uco_catalog_priority_v1';
function publishPriority(terms){const cleanTerms=[...new Set(terms.map(t=>String(t||'').trim()).filter(Boolean))].slice(0,100);try{sessionStorage.setItem(PRIORITY,JSON.stringify(cleanTerms));sessionStorage.removeItem(SEARCH)}catch{};document.dispatchEvent(new CustomEvent('uco:assistant-catalog-priority',{detail:{terms:cleanTerms}}))}
let lastSearch=null; // Solo se limpia la búsqueda; el prepedido permanece intacto.
let searchEpoch=0; // Invalida respuestas tardías al abrir un chat nuevo.
let resubmittingLegacy=false;
const clean=s=>String(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money=n=>(n/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
let draft=[];try{const old=JSON.parse(sessionStorage.getItem(KEY)||'[]');if(Array.isArray(old))draft=old.filter(x=>Number.isSafeInteger(x.id)&&x.id>0&&Number.isSafeInteger(x.qty)&&x.qty>0).slice(0,100)}catch{}
const save=()=>{sessionStorage.setItem(KEY,JSON.stringify(draft));queueMicrotask(()=>{if(typeof updateDraftButton==='function')updateDraftButton()})};
function note(s){const d=document.createElement('div');d.className='uco-ai-msg';d.dataset.who='bot';d.textContent=s;log.append(d);log.scrollTop=log.scrollHeight}
// Los controles comerciales viven fuera de los mensajes conversacionales.
const workspace=document.createElement('aside');workspace.className='uco-ai-workspace';workspace.hidden=true;workspace.setAttribute('aria-label','Productos y prepedido');
workspace.innerHTML='<div class="uco-ai-workspace-head"><strong>Productos y prepedido</strong><button type="button" aria-label="Cerrar panel comercial">×</button></div><div class="uco-ai-workspace-body"></div>';
root.append(workspace);
const workspaceBody=workspace.querySelector('.uco-ai-workspace-body');
workspace.querySelector('button').onclick=()=>{workspace.hidden=true};
function placeWorkspace(){const panel=root.querySelector('.uco-ai-panel');if(!panel||panel.hidden)return;const r=panel.getBoundingClientRect();const width=Math.min(592,Math.max(310,innerWidth-r.width-30));const left=r.right+width+12<=innerWidth-8?r.right+12:r.left-width-12>=8?r.left-width-12:Math.max(8,(innerWidth-width)/2);workspace.style.width=width+'px';workspace.style.left=left+'px';workspace.style.top=Math.max(8,Math.min(r.top,innerHeight-workspace.offsetHeight-8))+'px'}
function reveal(){workspace.hidden=false;requestAnimationFrame(placeWorkspace)}
function card(kind='draft'){workspaceBody.querySelectorAll('[data-uco-card="'+kind+'"]').forEach(x=>x.remove());const d=document.createElement('div');d.className='uco-prepedido';d.dataset.ucoCard=kind;workspaceBody.append(d);reveal();return d}
window.addEventListener('resize',()=>{if(!workspace.hidden)placeWorkspace()});
root.querySelector('.uco-ai-head')?.addEventListener('pointerup',()=>{if(!workspace.hidden)requestAnimationFrame(placeWorkspace)});
root.querySelector('.uco-ai-close')?.addEventListener('click',()=>{workspace.hidden=true});
root.querySelector('.uco-ai-minimize')?.addEventListener('click',()=>{workspace.hidden=true});
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
async function transferCart(){if(!draft.length)return;const id='uco-pre-'+Date.now();const lines=draft.map(x=>({id:x.id,quantity:x.qty}));note('Enviando las referencias al carrito para su validación…');const response=await new Promise(resolve=>{const listener=e=>{if(e.detail?.id!==id)return;clearTimeout(timeout);document.removeEventListener('uco:budget-cart-result',listener);resolve(e.detail)};const timeout=setTimeout(()=>{document.removeEventListener('uco:budget-cart-result',listener);resolve({ok:false,message:'El carrito no ha respondido'})},25000);document.addEventListener('uco:budget-cart-result',listener);document.dispatchEvent(new CustomEvent('uco:budget-cart-transfer',{detail:{id,lines}}))});note(response.ok?'Productos transferidos al carrito. Revísalos y confirma la compra desde el área de clientes.':String(response.message||'No se pudo completar el traspaso')+'. Comprueba el carrito antes de reintentar.');if(response.ok){draft=[];save()}}
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
