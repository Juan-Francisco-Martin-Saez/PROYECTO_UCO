import './components/economy-elements.js';
import {confirmChange,confirmStandalone} from './components/confirm-elements.js';
import {mountPager} from './components/list-pager.js';
/* v1.9.6 fase 2: controlador de tarifas separado de la interfaz Web Component. */
import './components/commercial-elements.js';
export function initTariffs({req,makeDialog,esc,refreshCommercial,getCommercial}){
 const root=document.querySelector('#commercial-area');if(!root)return;
 const money=v=>(v/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
 const pct=v=>(v/100).toLocaleString('es-ES',{maximumFractionDigits:2})+' %';
 const options=(items,value,caption)=>'<option value="">'+caption+'</option>'+items.map(x=>`<option value="${x.id}" ${String(x.id)===String(value)?'selected':''}>${esc(x.name||x.sku+' · '+x.name)}</option>`).join('');
 let tab='base',query='';
 function show(){const area=root.querySelector('#commercial-content');if(!area)return;area.hidden=true;let view=root.querySelector('#tariff-view');if(!view){view=document.createElement('uco-tariff-view');view.id='tariff-view';area.after(view);}view.hidden=false;render();}
 async function save(action,data){await req(action,{method:'POST',body:new URLSearchParams(data)});await refreshCommercial();render();}
 function render(){const c=getCommercial(),view=root.querySelector('#tariff-view');if(!view||view.hidden)return;
 const tabs=[['base','Tarifa general'],['groups','Descuentos por grupo'],['promos','Promociones'],['economy','Impuestos y transporte']];
 view.renderFrame(tabs,tab);
 view.querySelectorAll('[data-tab]').forEach(b=>b.onclick=()=>{tab=b.dataset.tab;render()});const panel=view.querySelector('#tariff-panel');
 if(tab==='economy'){
  panel.innerHTML='<uco-economy-settings></uco-economy-settings>';
  const widget=panel.querySelector('uco-economy-settings'),msg=widget.querySelector('.uco-economy-message');
  const request=async(body=null)=>{const res=await fetch('../api/economy-admin.php',{method:body?'POST':'GET',credentials:'same-origin',headers:body?{'X-CSRF-Token':(await req('session')).csrf}:undefined,body});const d=await res.json();if(!res.ok)throw Error(d.error||'Error de configuración');return d};
  const cents=s=>Math.round(Number(s)*100);
  Promise.all([request(),req('session')]).then(([d,session])=>{
   const sessionId=String(session.user?.id||session.user_id||'admin');
   const ship=widget.querySelector('#uco-shipping');ship.shipping.value=d.settings.shipping_cents!==undefined?(+d.settings.shipping_cents/100).toFixed(2):'';
   ship.free.value=d.settings.free_shipping_from_cents!==undefined?(+d.settings.free_shipping_from_cents/100).toFixed(2):'';
   ship.prefixes.value=d.settings.allowed_postal_prefixes||'';
   const tax=widget.querySelector('#uco-tax'),multi=tax.querySelector('.uco-tax-multi');
   const trigger=multi.querySelector('.uco-tax-trigger'),dropdown=multi.querySelector('.uco-tax-dropdown');
   const search=multi.querySelector('.uco-tax-search'),options=multi.querySelector('.uco-tax-options');
   const status=multi.querySelector('.uco-tax-status'),confirmation=tax.querySelector('.uco-tax-confirm');
   const products=d.products||[],valid=new Set(products.map(p=>String(p.id)));
   const rates=new Map(d.taxes.map(x=>[+x.product_id,+x.rate_bp]));
   const userKey='uco-iva-pendiente-'+String(sessionId);
   let saved={};
   try{saved=JSON.parse(localStorage.getItem(userKey)||'{}')}catch{}
   const selected=new Set((Array.isArray(saved.ids)?saved.ids:[]).map(String).filter(id=>valid.has(id)));
   tax.rate.value=typeof saved.rate==='string'?saved.rate:'';
   const persist=()=>{try{localStorage.setItem(userKey,JSON.stringify({ids:[...selected],rate:tax.rate.value}))}catch{}};
   const refreshStatus=()=>{
    const count=selected.size,rate=tax.rate.value.trim();
    trigger.textContent=count?count+' productos seleccionados ▾':'Seleccionar productos ▾';
    status.classList.toggle('pending',count>0);
    status.textContent=count?`Actualización de IVA pendiente · ${count} producto${count===1?'':'s'} seleccionado${count===1?'':'s'}${rate?' · Nuevo IVA: '+rate.replace('.',',')+' %':''}`:'Ninguna operación pendiente';
   };
   const renderOptions=()=>{
    const term=search.value.toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g,'');
    const visible=products.filter(p=>(p.sku+' '+p.name).toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g,'').includes(term))
      .sort((a,b)=>Number(selected.has(String(b.id)))-Number(selected.has(String(a.id)))||a.name.localeCompare(b.name,'es'));
    options.replaceChildren();
    for(const p of visible){
     const label=document.createElement('label');label.className='uco-tax-option';
     const input=document.createElement('input');input.type='checkbox';input.value=p.id;input.checked=selected.has(String(p.id));
     const span=document.createElement('span');span.textContent=p.sku+' · '+p.name;
     input.addEventListener('change',()=>{input.checked?selected.add(String(p.id)):selected.delete(String(p.id));persist();refreshStatus();renderOptions()});
     label.append(input,span);options.append(label);
    }
    if(!visible.length){const p=document.createElement('p');p.textContent='No se encontraron referencias';options.append(p)}
   };
   trigger.onclick=()=>{dropdown.hidden=!dropdown.hidden;trigger.setAttribute('aria-expanded',String(!dropdown.hidden));if(!dropdown.hidden){renderOptions();search.focus()}};
   search.oninput=renderOptions;
   tax.rate.oninput=()=>{persist();refreshStatus()};
   refreshStatus();renderOptions();
   ship.onsubmit=async e=>{e.preventDefault();const f=new FormData();f.set('type','shipping');f.set('shipping_cents',cents(ship.shipping.value));f.set('free_shipping_from_cents',cents(ship.free.value));f.set('allowed_postal_prefixes',ship.prefixes.value);try{await request(f);msg.textContent='Transporte guardado correctamente'}catch(err){msg.textContent=err.message}};
   tax.onsubmit=e=>{
    e.preventDefault();
    const value=tax.rate.value.trim(),rate=Number(value.replace(',','.'));
    if(!selected.size){msg.textContent='Selecciona al menos una referencia';return}
    if(!value||!Number.isFinite(rate)||rate<0||rate>100){msg.textContent='Introduce un IVA válido entre 0 y 100 %';return}
    confirmation.hidden=false;confirmation.replaceChildren();
    const heading=document.createElement('h4');heading.textContent='Confirmar actualización de IVA';
    const summary=document.createElement('p');summary.textContent=`Se aplicará un IVA del ${rate.toLocaleString('es-ES')} % a ${selected.size} referencias:`;
    const list=document.createElement('ul');list.className='uco-tax-confirm-list';
    for(const p of products.filter(p=>selected.has(String(p.id)))){const li=document.createElement('li');li.textContent=p.sku+' · '+p.name;list.append(li)}
    const actions=document.createElement('div');actions.className='uco-tax-confirm-actions';
    const cancel=document.createElement('button');cancel.type='button';cancel.textContent='Cancelar';
    cancel.onclick=()=>{confirmation.hidden=true;persist()};
    const accept=document.createElement('button');accept.type='button';accept.textContent='Confirmar actualización';
    accept.onclick=async()=>{
     accept.disabled=true;cancel.disabled=true;
     const f=new FormData();f.set('type','tax_batch');f.set('product_ids',JSON.stringify([...selected].map(Number)));f.set('rate_bp',String(Math.round(rate*100)));
     try{
      await request(f);
      for(const id of selected)rates.set(+id,Math.round(rate*100));
      selected.clear();tax.rate.value='';persist();refreshStatus();renderOptions();
      confirmation.hidden=true;dropdown.hidden=true;trigger.setAttribute('aria-expanded','false');
      msg.textContent='IVA actualizado correctamente en todas las referencias seleccionadas';
     }catch(err){msg.textContent=err.message;accept.disabled=false;cancel.disabled=false}
    };
    actions.append(cancel,accept);confirmation.append(heading,summary,list,actions);
    confirmation.scrollIntoView({block:'nearest',behavior:'smooth'});
   };

  }).catch(e=>{msg.textContent=e.message});
  return;
 }
 if(tab==='base'){
  const list=c.products.filter(p=>(p.sku+' '+p.name).toLocaleLowerCase('es').includes(query.toLocaleLowerCase('es')));const prices=new Map((c.base||[]).map(x=>[+x.product_id,+x.price_cents]));
  panel.innerHTML=`<div class="tariff-toolbar"><label class="field"><span>Buscar producto o referencia</span><input id="tariff-search" type="search" placeholder="Buscar producto…" value="${esc(query)}"></label><span class="muted">${list.length} productos · ${prices.size} con precio general</span></div><uco-table-scroll class="tablewrap"><table class="tariff-table"><thead><tr><th>Referencia</th><th>Producto</th><th>Precio general</th><th>Acción</th></tr></thead><tbody>${list.map(p=>`<tr><td>${esc(p.sku)}</td><td><div class="prod"><img src="${esc(/^\.\/img\/(?:productos\/[a-f0-9]{32}\.webp|[\w-]+\.(?:webp|png|svg))$/.test(p.image||'')?'../'+p.image.slice(2):'../img/00.webp')}" alt=""><strong>${esc(p.name)}</strong></div></td><td>${prices.has(+p.id)?money(prices.get(+p.id)):'<span class="muted">Sin precio</span>'}</td><td><button type="button" class="edit" data-base="${p.id}">Editar precio</button></td></tr>`).join('')||'<tr><td colspan="4">No hay productos con ese filtro.</td></tr>'}</tbody></table></uco-table-scroll>`;
  mountPager(panel.querySelector('uco-table-scroll'),{search:false});panel.querySelector('#tariff-search').oninput=e=>{query=e.target.value;const pos=e.target.selectionStart;render();const input=root.querySelector('#tariff-search');input.focus();input.setSelectionRange(pos,pos)};
  panel.querySelectorAll('[data-base]').forEach(b=>b.onclick=()=>{const id=+b.dataset.base,p=c.products.find(x=>+x.id===id),v=prices.get(id);const d=makeDialog('Precio general',`<p><strong>${esc(p.sku)} · ${esc(p.name)}</strong></p><div class="field"><label>Precio general en euros</label><input name="price" inputmode="decimal" pattern="[0-9]+([.,][0-9]{1,2})?" value="${v===undefined?'':(v/100).toFixed(2)}" placeholder="Ej. 12,50"><small class="tip">Dejar vacío elimina el precio general de este producto.</small></div><input type="hidden" name="product_id" value="${id}">`,async body=>{const next=String(body.get('price')||'').trim();if(v!==undefined&&next!==''&&Math.round(Number(next.replace(',','.'))*100)!==v){if(!await confirmChange(d,{title:'Sobrescribir tarifa general',message:'Vas a modificar el precio general de '+p.name+'.',previous:money(v),next:next+' €',confirm:'Sobrescribir precio'}))throw Object.assign(new Error(''),{cancelled:true})}else if(v!==undefined&&next===''){if(!await confirmChange(d,{title:'Eliminar tarifa general',message:'El producto dejará de tener un precio general configurado.',previous:money(v),next:'Sin precio',confirm:'Eliminar precio',danger:true}))throw Object.assign(new Error(''),{cancelled:true})}body.set('expected_price_cents',v===undefined?'':String(v));await save('base_save',body)});});
 }else if(tab==='groups'){
  const disc=new Map((c.discounts||[]).map(x=>[+x.group_id,+x.percent_bp]));panel.innerHTML=`<p class="tip">El descuento se aplica a la tarifa general. Los precios manuales del grupo siguen disponibles en «Asignar precio».</p><uco-table-scroll class="tablewrap"><table class="tariff-table"><thead><tr><th>Grupo comercial</th><th>Descuento general</th><th>Estado</th><th>Acción</th></tr></thead><tbody>${c.groups.map(g=>`<tr><td><strong>${esc(g.name)}</strong></td><td>${pct(disc.get(+g.id)||0)}</td><td>${+g.active?'Activo':'Inactivo'}</td><td><button class="edit" data-discount="${g.id}">Configurar</button></td></tr>`).join('')||'<tr><td colspan="4">Crea primero un grupo de tarifas.</td></tr>'}</tbody></table></uco-table-scroll>`;
  mountPager(panel.querySelector('uco-table-scroll'));panel.querySelectorAll('[data-discount]').forEach(b=>b.onclick=()=>{const id=+b.dataset.discount,g=c.groups.find(x=>+x.id===id);const d=makeDialog('Descuento del grupo',`<p><strong>${esc(g.name)}</strong></p><input type="hidden" name="group_id" value="${id}"><div class="field"><label>Descuento sobre la tarifa general (%)</label><input type="text" name="percent" required inputmode="decimal" pattern="(100([.,]0{1,2})?|[0-9]{1,2}([.,][0-9]{1,2})?)" value="${((disc.get(id)||0)/100).toFixed(2)}"><small class="tip">Entre 0 y 100. Un 0 % deja el precio general sin cambios.</small></div>`,async body=>{const next=String(body.get('percent')).replace(',','.');if(Math.round(Number(next)*100)!==(disc.get(id)||0)){if(!await confirmChange(d,{title:'Modificar descuento de grupo',message:'Se actualizará el descuento general de '+g.name+'.',previous:pct(disc.get(id)||0),next:next+' %',confirm:'Sobrescribir descuento'}))throw Object.assign(new Error(''),{cancelled:true})}body.set('expected_percent_bp',disc.has(id)?String(disc.get(id)):'');await save('discount_save',body)});});
 }else{
  const today=new Date().toISOString().slice(0,10);
  panel.innerHTML=`<div class="tariff-toolbar"><p class="tip">Promociones con fechas de vigencia. Se utiliza la mejor promoción aplicable, sin acumular descuentos.</p><button class="primary" id="new-promo">+ Nueva promoción</button></div><uco-table-scroll class="tablewrap"><table class="tariff-table"><thead><tr><th>Promoción</th><th>Destinatarios</th><th>Descuento</th><th>Vigencia</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>${(c.promotions||[]).map(p=>`<tr><td><strong>${esc(p.name)}</strong></td><td>${p.scope==='all'?'Todos los clientes':esc(c.groups.find(g=>+g.id===+p.group_id)?.name||'Grupo eliminado')}</td><td>${pct(+p.percent_bp)}</td><td>${esc(p.starts_at)} a ${esc(p.ends_at)}</td><td>${!+p.active?'Inactiva':p.starts_at>today?'Programada':p.ends_at<today?'Finalizada':'Vigente'}</td><td class="commercial-row-actions"><button class="edit" data-promo-edit="${p.id}">Editar</button><button class="note" data-promo-delete="${p.id}">Eliminar</button></td></tr>`).join('')||'<tr><td colspan="6">No hay promociones configuradas.</td></tr>'}</tbody></table></uco-table-scroll>`;
  mountPager(panel.querySelector('uco-table-scroll'));const edit=p=>{const d=makeDialog(p?'Editar promoción':'Nueva promoción',`<input type="hidden" name="id" value="${p?.id||0}"><div class="formgrid"><div class="field wide"><label>Nombre de la promoción</label><input name="name" maxlength="100" required value="${esc(p?.name||'')}"></div><div class="field"><label>Aplicación</label><select name="scope"><option value="all" ${p?.scope!=='group'?'selected':''}>Todos los clientes</option><option value="group" ${p?.scope==='group'?'selected':''}>Grupo comercial</option></select></div><div class="field" id="promo-group"><label>Grupo</label><select name="group_id">${options(c.groups,p?.group_id,'Seleccionar grupo…')}</select></div><div class="field"><label>Descuento (%)</label><input name="percent" inputmode="decimal" required pattern="(100([.,]0{1,2})?|[0-9]{1,2}([.,][0-9]{1,2})?)" value="${p?(p.percent_bp/100).toFixed(2):''}" placeholder="Ej. 10"></div><div class="field"><label>Estado</label><select name="active"><option value="1" ${p?.active!==0?'selected':''}>Activa</option><option value="0" ${p?.active===0?'selected':''}>Inactiva</option></select></div><div class="field"><label>Desde</label><input type="date" name="starts_at" required value="${p?.starts_at||today}"></div><div class="field"><label>Hasta</label><input type="date" name="ends_at" required value="${p?.ends_at||today}"></div></div>`,async body=>{const conflicts=c.promotions.filter(x=>+x.id!==+body.get('id')&&+x.active&&body.get('active')==='1'&&x.scope===body.get('scope')&&(x.scope==='all'||String(x.group_id)===String(body.get('group_id')))&&x.starts_at<=body.get('ends_at')&&x.ends_at>=body.get('starts_at'));if(conflicts.length){if(!await confirmChange(d,{title:'Promociones con fechas coincidentes',message:'Hay '+conflicts.length+' promoción(es) activas para el mismo destinatario y fechas, como «'+conflicts[0].name+'». Se aplicará la mejor promoción; no se eliminará ninguna.',confirm:'Guardar igualmente'}))throw Object.assign(new Error(''),{cancelled:true})}if(p&&(String(p.percent_bp)!==String(Math.round(Number(String(body.get('percent')).replace(',','.'))*100))||p.starts_at!==body.get('starts_at')||p.ends_at!==body.get('ends_at')||p.scope!==body.get('scope')||String(p.active)!==String(body.get('active')))){if(!await confirmChange(d,{title:'Modificar promoción',message:'Vas a cambiar las condiciones de «'+p.name+'».',previous:pct(p.percent_bp)+' · '+p.starts_at+' a '+p.ends_at,next:body.get('percent')+' % · '+body.get('starts_at')+' a '+body.get('ends_at'),confirm:'Guardar cambios'}))throw Object.assign(new Error(''),{cancelled:true})}await save('promotion_save',body)});const sel=d.querySelector('[name=scope]'),grp=d.querySelector('#promo-group');const update=()=>grp.hidden=sel.value!=='group';sel.onchange=update;update();};
  panel.querySelector('#new-promo').onclick=()=>edit(null);panel.querySelectorAll('[data-promo-edit]').forEach(b=>b.onclick=()=>edit(c.promotions.find(p=>+p.id===+b.dataset.promoEdit)));
  panel.querySelectorAll('[data-promo-delete]').forEach(b=>b.onclick=async()=>{if(!await confirmStandalone({title:'Eliminar promoción',message:'Esta promoción dejará de estar disponible para los clientes.',confirm:'Eliminar promoción',danger:true}))return;try{await save('promotion_delete',{id:b.dataset.promoDelete})}catch(e){alert(e.message)}});
 }
 }
 return {render,show};
}
