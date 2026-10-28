(()=>{'use strict';
const $=id=>document.getElementById(id);let csrf='',admin=false,customer=null;
const status=s=>$('status').textContent=s;
function add(container,title,detail){
 const item=document.createElement('div');item.className='item';
 const strong=document.createElement('strong');strong.textContent=title;
 const small=document.createElement('small');small.textContent=detail;
 item.append(strong,small);container.append(item);
}
async function call(method='GET',payload=null,customerId=''){
 const url='../api/chatbot-admin-641.php'+(customerId?'?customer_id='+encodeURIComponent(customerId):'');
 const r=await fetch(url,{method,credentials:'same-origin',cache:'no-store',
 headers:{Accept:'application/json',...(method==='POST'?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{})},
 ...(payload?{body:JSON.stringify(payload)}:{})});
 let data;try{data=await r.json()}catch{throw new Error('Respuesta no válida del servidor')}
 if(!r.ok||!data.ok)throw new Error(data.error||'Error de consulta');return data;
}
function renderList(id,rows,format){
 const target=$(id);target.replaceChildren();
 if(!rows.length){target.textContent='Sin registros todavía.';return}
 rows.forEach(x=>{const [title,detail]=format(x);add(target,title,detail)});
}
async function load(cid=''){
 status('Consultando…');
 try{
  const d=await call('GET',null,cid);csrf=d.csrf;admin=d.is_admin;customer=d.customer;
  const metrics=[['Permitidos',d.summary.allowed],['Bloqueados',d.summary.blocked],['Con consentimiento',d.summary.consented],['Errores',d.summary.errors],['Pedidos chatbot',d.summary.chatbot_orders],['Pedidos web',d.summary.web_orders]];
  $('stats').replaceChildren();metrics.forEach(([label,n])=>{
   const card=document.createElement('div');card.className='stat';
   const strong=document.createElement('strong');strong.textContent=n;
   const span=document.createElement('span');span.textContent=label;card.append(strong,span);$('stats').append(card);
  });
  $('customer').textContent=cid?(customer?'Cliente #'+customer.id+' · '+(customer.display_name||'Sin nombre')+' · '+(customer.allowed_by_admin?'Permitido':'Bloqueado')+' · Consentimiento: '+(customer.consent_at?'registrado':'pendiente'):'No existe un cliente con ese ID.'):'Introduce un ID y consulta su estado.';
  $('allow').disabled=!admin||!customer;$('block').disabled=!admin||!customer;
  renderList('errors',d.errors,x=>[x.category+' · '+(x.reference||'Sin referencia'),x.created_at+' · '+x.detail]);
  renderList('origins',d.origins,x=>['Pedido #'+x.order_id+' · '+x.origin,x.created_at]);
  renderList('audit',d.audit,x=>[x.action+' · Cliente #'+x.subject_id,x.created_at+' · Administrador #'+x.actor_id]);
  status('Datos actualizados. Chatbot y pagos reales no activados por esta fase.');
 }catch(e){status(e.message)}
}
$('lookup').addEventListener('click',()=>{const id=$('customer-id').value;if(!/^[1-9]\d*$/.test(id)){status('Introduce un ID válido');return}load(id)});
async function setAllowed(allowed){
 if(!customer||!admin)return;
 if(!confirm((allowed?'Permitir':'Bloquear')+' uso futuro del chatbot para cliente #'+customer.id+'?'))return;
 status('Guardando…');
 try{await call('POST',{action:'set_access',customer_id:Number(customer.id),allowed});await load(String(customer.id))}
 catch(e){status(e.message)}
}
$('allow').addEventListener('click',()=>setAllowed(true));
$('block').addEventListener('click',()=>setAllowed(false));
load();
})();