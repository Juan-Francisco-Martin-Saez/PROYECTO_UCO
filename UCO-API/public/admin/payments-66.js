(() => {
'use strict';
const el=id=>document.getElementById(id);
let csrf='',canManage=false;
const labels={pending:'Pendiente',paid:'Cobrado',review:'Revisión',failed:'Fallido',cancelled:'Cancelado'};
function cell(tr,value){const td=document.createElement('td');td.textContent=String(value??'—');tr.appendChild(td);}
function empty(tbody,colspan){const tr=document.createElement('tr');const td=document.createElement('td');td.colSpan=colspan;td.textContent='Sin registros';tr.appendChild(td);tbody.appendChild(tr);}
function money(cents,currency){return new Intl.NumberFormat('es-ES',{style:'currency',currency:currency||'EUR'}).format(Number(cents)/100);}
async function refresh(){el('message').textContent='Consultando…';const status=el('status').value;el('refresh').disabled=true;
try{const res=await fetch('../api/payment-admin-66.php?status='+encodeURIComponent(status),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'Error de consulta');
csrf=data.csrf||'';canManage=!!data.can_manage;
const stats=el('stats');stats.replaceChildren();for(const [key,n] of Object.entries(data.counts||{})){const box=document.createElement('div');const title=document.createElement('span');title.textContent=labels[key]||key;const value=document.createElement('strong');value.textContent=String(n);box.append(title,value);stats.appendChild(box);}
const pay=el('payments');pay.replaceChildren();for(const p of data.payments||[]){const tr=document.createElement('tr');[p.reference,p.method,labels[p.state]||p.state,money(p.amount_cents,p.currency),`#${p.order_id} · ${p.order_status||'—'}`,p.created_at].forEach(x=>cell(tr,x));pay.appendChild(tr);}if(!pay.children.length)empty(pay,6);
const tasks=el('tasks');tasks.replaceChildren();for(const t of data.tasks||[]){const tr=document.createElement('tr');[t.reference,t.kind,labels[t.payment_state]||t.payment_state,t.note,t.created_at].forEach(x=>cell(tr,x));const td=document.createElement('td');if(canManage){for(const [decision,title] of [['resolved','Resolver revisión'],['dismissed','Descartar revisión']]){const b=document.createElement('button');b.type='button';b.textContent=title;b.addEventListener('click',()=>decide(t,decision));td.appendChild(b);}}else td.textContent='Solo lectura';tr.appendChild(td);tasks.appendChild(tr);}if(!tasks.children.length)empty(tasks,6);
el('message').textContent=data.configured?'Consulta actualizada.':'Todavía no hay operaciones reales registradas.';
}catch(err){el('message').textContent=err.message||'No se pudieron consultar los pagos';}finally{el('refresh').disabled=false;}}
async function decide(task,decision){const note=prompt('Motivo obligatorio (mínimo 8 caracteres). Esta acción solo cierra la tarea de revisión, NO confirma cobros ni ejecuta reembolsos:');if(note===null)return;if(note.trim().length<8){alert('Introduce al menos 8 caracteres.');return;}if(!confirm('¿Confirmas el cierre administrativo de la tarea '+task.id+'? No cambia el estado del pago.'))return;try{const res=await fetch('../api/payment-admin-66.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf,'Accept':'application/json'},body:JSON.stringify({task_id:Number(task.id),decision,note:note.trim()})});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'No se pudo resolver');await refresh();}catch(e){alert(e.message||'Error de actualización');}}
el('refresh').addEventListener('click',refresh);el('status').addEventListener('change',refresh);refresh();
})();
