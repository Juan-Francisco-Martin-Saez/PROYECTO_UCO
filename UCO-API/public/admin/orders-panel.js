const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money=v=>(Number(v||0)/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
const states={pending_preparation:'Pendiente de preparación',preparing:'En preparación',prepared:'Preparado',dispatched:'Enviado',delivered:'Entregado',incident:'Incidencia',cancelled:'Cancelado'};

function orderDialog({title,body,reset=false,success=false}){
 return new Promise(resolve=>{
  const dialog=document.createElement('dialog');dialog.className='uco-order-dialog';
  dialog.innerHTML=`<div class="uco-order-dialog-head"><h2>${esc(title)}</h2><button type="button" class="uco-order-x" aria-label="Cerrar">×</button></div><div class="uco-order-dialog-body"><p>${esc(body)}</p>${reset?`<label>Motivo del reinicio <textarea rows="3" minlength="12" maxlength="500" placeholder="Explica por qué se reinicia este pedido" required></textarea></label><label>Contraseña administrativa <input type="password" autocomplete="current-password" required></label><small>El reinicio quedará registrado con fecha, administrador, estado anterior y motivo. No modifica el pago ni la factura.</small>`:''}<div class="uco-order-dialog-actions">${success?'':`<button type="button" class="uco-order-cancel">Cancelar</button>`}<button type="button" class="uco-order-confirm">${success?'Cerrar':reset?'Verificar y reiniciar':'Confirmar cambio'}</button></div></div>`;
  document.body.append(dialog);
  const finish=v=>{if(dialog.open)dialog.close();dialog.remove();resolve(v)};
  dialog.querySelector('.uco-order-x').onclick=()=>finish(null);
  dialog.querySelector('.uco-order-cancel')?.addEventListener('click',()=>finish(null));
  dialog.addEventListener('cancel',e=>{e.preventDefault();finish(null)});
  dialog.querySelector('.uco-order-confirm').onclick=()=>{
   if(reset){const reason=dialog.querySelector('textarea').value.trim(),password=dialog.querySelector('input').value;
    if(reason.length<12){dialog.querySelector('textarea').setCustomValidity('Escribe al menos 12 caracteres');dialog.querySelector('textarea').reportValidity();return}
    if(!password){dialog.querySelector('input').reportValidity();return}
    finish({note:reason,admin_password:password});
   }else finish({});
  };
  dialog.querySelector('textarea')?.addEventListener('input',e=>e.target.setCustomValidity(''));
  dialog.showModal();
 });
}

function orderHistory(o){
 const changes=(o.events||[]).map((e,i)=>({type:'change',date:e.created_at,sequence:i,actor:e.actor,from:e.previous_state,to:e.new_state,note:e.note}));
 const resets=(o.resets||[]).map((r,i)=>({type:'reset',date:r.created_at,sequence:i,actor:r.actor,from:r.previous_state,note:r.reason,verification:r.verification_method}));
 const history=[...changes,...resets].sort((a,b)=>String(a.date).localeCompare(String(b.date)));
 return `<section class="uco-order-history"><h3>Historial de preparación</h3>${history.length?`<ol>${history.map(h=>`<li class="${h.type==='reset'?'uco-history-reset':''}"><div class="uco-history-top"><strong>${h.type==='reset'?'Reinicio a Sin iniciar':esc(states[h.to]||h.to)}</strong><time>${esc(h.date||'Fecha no disponible')}</time></div><p>${esc(states[h.from]||'Sin iniciar')} → ${h.type==='reset'?'Sin iniciar':esc(states[h.to]||h.to)}</p><small>Responsable: ${esc(h.actor||'No disponible')}${h.type==='reset'?' · Contraseña administrativa verificada':''}</small>${h.note?`<p class="uco-history-note">Motivo / nota: ${esc(h.note)}</p>`:''}</li>`).join('')}</ol>`:'<p class="uco-order-notice">Todavía no hay movimientos de preparación registrados.</p>'}</section>`;
}
export async function openAdminOrders(){
 const el=document.createElement('section');el.id='orders-area';el.className='wrap uco-orders';
 el.innerHTML=`<div class="uco-orders-intro"><div><span class="eyebrow">PEDIDOS · ADMINISTRACIÓN</span><h1>Gestión de <em>pedidos.</em></h1><p>Consulta de pagos, productos y preparación. Los pedidos históricos no entran automáticamente en almacén.</p></div></div><section class="panel"><div class="uco-orders-filters"><label>Buscar pedido o cliente<input type="search" id="uco-orders-search" placeholder="PED-08, Pepe..."></label><label>Pago<select id="uco-orders-payment"><option value="">Todos</option><option value="yes">Aprobado</option><option value="no">Sin confirmar</option></select></label><label>Preparación<select id="uco-orders-state"><option value="">Todos</option><option value="none">Sin iniciar</option>${Object.entries(states).map(([k,v])=>`<option value="${k}">${v}</option>`).join('')}</select></label></div><div class="uco-orders-count" aria-live="polite">Cargando pedidos…</div><div class="uco-orders-list"></div></section>`;
 document.querySelector('.admin-section-nav').after(el);
 const list=el.querySelector('.uco-orders-list'),count=el.querySelector('.uco-orders-count');
 try{
  const response=await fetch('../api/admin-orders.php',{credentials:'same-origin',cache:'no-store'});
  const data=await response.json();
  if(!response.ok)throw Error(data.error||'Error de consulta');
  const orders=data.orders||[];const canManage=!data.read_only;
  const allowed={none:['pending_preparation'],pending_preparation:['preparing','incident','cancelled'],preparing:['prepared','incident'],prepared:['dispatched','incident'],dispatched:['delivered','incident'],incident:['pending_preparation','cancelled']};
  const render=()=>{
   const search=el.querySelector('#uco-orders-search').value.trim().toLocaleLowerCase('es');
   const payment=el.querySelector('#uco-orders-payment').value,state=el.querySelector('#uco-orders-state').value;
   const filtered=orders.filter(o=>(!search||('ped-'+String(o.id).padStart(2,'0')+' '+o.customer+' '+o.id).toLocaleLowerCase('es').includes(search))&&(!payment||((payment==='yes')===!!o.payment_confirmed))&&(!state||(state==='none'?!o.fulfillment_state:o.fulfillment_state===state)));
   count.textContent=`${filtered.length} pedidos · ${canManage?'Gestión autorizada':'Consulta protegida'}`;
   list.innerHTML=filtered.length?filtered.map(o=>`<details class="uco-order-card"><summary><strong>PED-${String(o.id).padStart(2,'0')}</strong><span>${esc(o.customer)}</span><span>${esc(o.created_at)}</span><strong>${money(o.total_cents??o.subtotal_cents)}</strong><span class="uco-order-status">${o.payment_confirmed?'Pago aprobado':'Pago sin confirmar'}</span><span class="uco-order-state">${esc(states[o.fulfillment_state]||'Sin iniciar')}</span></summary><div class="uco-order-detail"><p><strong>Cliente:</strong> ${esc(o.customer)} · <strong>Forma de pago:</strong> ${esc(({redsys:'Tarjeta bancaria (Visa / Mastercard)',paypal:'PayPal',bizum:'Bizum',transfer:'Transferencia bancaria'})[o.payment_method]||o.payment_method||'—')}</p><div class="uco-order-lines">${o.lines.map(l=>`<div><span>${esc(l.name)} <small>${esc(l.sku)}</small></span><span>${Number(l.quantity)} ${esc(l.unit)}</span><strong>${money(l.line_cents)}</strong></div>`).join('')}</div>${orderHistory(o)}${canManage&&o.payment_confirmed?`<div class="uco-order-actions"><label>Estado siguiente<select data-next="${o.id}">${(allowed[o.fulfillment_state||'none']||[]).map(s=>`<option value="${s}">${esc(states[s])}</option>`).join('')}</select></label><button type="button" data-save="${o.id}" ${(allowed[o.fulfillment_state||'none']||[]).length?'':'disabled'}>Actualizar estado</button>${o.fulfillment_state?`<button type="button" class="uco-order-reset" data-reset="${o.id}">Volver a sin iniciar</button>`:''}</div>`:'<p class="uco-order-notice">Sin cambios disponibles para esta cuenta o pedido.</p>'}</div></details>`).join(''):'<p class="uco-order-empty">No hay pedidos con estos filtros.</p>';
  };
  el.querySelectorAll('.uco-orders-filters input,.uco-orders-filters select').forEach(x=>x.addEventListener('input',render));
  list.addEventListener('click',async ev=>{
   const button=ev.target.closest('button[data-save],button[data-reset]');if(!button||button.disabled)return;
   const reset=button.hasAttribute('data-reset');
   const id=Number(reset?button.dataset.reset:button.dataset.save),select=list.querySelector(`select[data-next="${id}"]`),state=reset?'__reset__':select?.value;
   if(!state)return;
   const order=orders.find(o=>Number(o.id)===id);
   const values=await orderDialog({title:reset?'Reiniciar preparación':'Confirmar cambio de estado',body:reset?`PED-${String(id).padStart(2,'0')} · ${states[order.fulfillment_state]||'Sin iniciar'} → Sin iniciar. Requiere contraseña administrativa y motivo escrito.`:`PED-${String(id).padStart(2,'0')} · ${states[order.fulfillment_state]||'Sin iniciar'} → ${states[state]}`,reset});
   if(!values)return;
   button.disabled=true;
   try{
    const sessionResponse=await fetch('../api/admin.php?action=session',{credentials:'same-origin',cache:'no-store'});
    const session=await sessionResponse.json();
    if(!session.authenticated||!session.csrf)throw Error('Sesión caducada');
    const response=await fetch('../api/admin-orders.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':session.csrf},body:JSON.stringify({order_id:id,state,...values})});
    const result=await response.json();
    if(!response.ok)throw Error(result.error||'No se pudo actualizar');
    const refreshed=await fetch('../api/admin-orders.php',{credentials:'same-origin',cache:'no-store'});
    if(refreshed.ok){const latest=await refreshed.json();orders.splice(0,orders.length,...(latest.orders||[]));}
    else{order.fulfillment_state=reset?null:state;}
    render();await orderDialog({title:reset?'Preparación reiniciada':'Estado actualizado',body:reset?'El pedido vuelve a Sin iniciar. Se ha registrado el motivo y la verificación administrativa.':'El nuevo estado se ha guardado correctamente.',success:true});
   }catch(error){await orderDialog({title:'No se pudo completar',body:error.message,success:true});button.disabled=false;}
  });
  render();
 }catch(e){count.textContent='No se pudo cargar el listado';list.textContent=e.message}
}
