(()=>{'use strict';
const $=id=>document.getElementById(id),size=25;let offset=0,loading=false,last=null;
const money=n=>typeof n==='number'?new Intl.NumberFormat('es-ES',{style:'currency',currency:'EUR'}).format(n/100):'—';
const labels={critical:'Urgente',high:'Alta',medium:'Media',low:'Informativa'};
const critical=new Set(['amount_mismatch','refund_exceeds_original_payment','paid_order_mismatch','paid_status_mismatch','event_amount_mismatch','original_payment_missing','currency_mismatch','unexpected_currency','provider_mismatch','original_payment_not_paid','payment_not_settled','payment_review']);
const high=new Set(['reservation_expired','pending_after_expiry','order_closed','refund_order_not_linked_to_original','original_payment_not_confirmed','local_success_not_bank_proof','payment_state_mismatch','event:amount_mismatch','event:merchant_mismatch','event:terminal_mismatch']);
const medium=new Set(['provider_reference_missing','original_provider_reference_missing','refund_provider_reference_missing','no_signed_refund_notification_registered','pending_bank_reconciliation','event:order_reference_requires_acquirer_review']);
function priority(item){const flags=Array.isArray(item.flags)?item.flags:[];if(flags.some(x=>critical.has(x)))return 'critical';if(flags.some(x=>high.has(x)))return 'high';if(flags.some(x=>medium.has(x)||String(x).startsWith('event:')))return 'medium';return 'low'}
function cell(tr,value){const td=document.createElement('td');td.textContent=value==null?'—':String(value);tr.appendChild(td)}
function renderRows(id,items,refund){const body=$(id);body.replaceChildren();for(const item of items){const tr=document.createElement('tr');cell(tr,refund?item.payment_reference:item.reference);cell(tr,item.order_id);cell(tr,item.provider);cell(tr,refund?item.refund_status:item.payment_state);cell(tr,money(refund?item.refund_amount_cents:item.amount_cents));const td=document.createElement('td'),pill=document.createElement('span');pill.className='uco-priority-pill';pill.dataset.level=priority(item);pill.textContent=labels[pill.dataset.level];td.appendChild(pill);tr.appendChild(td);cell(tr,Array.isArray(item.flags)?item.flags.join(' · '):'—');body.appendChild(tr)}if(!items.length){const tr=document.createElement('tr'),td=document.createElement('td');td.colSpan=7;td.textContent='No hay incidencias para este filtro en la página';tr.appendChild(td);body.appendChild(tr)}}
function renderSection(key,id,data,refund){const sec=data?.sections?.[key];if(!sec?.available){$(id+'-status').textContent='Sección no disponible'+(sec?.missing_tables?.length?' (faltan tablas: '+sec.missing_tables.join(', ')+')':'');$(id).replaceChildren();return []}const all=Array.isArray(sec.items)?sec.items:[];const filtered=all.filter(x=>$('priority').value==='all'||priority(x)===$('priority').value);$(id+'-status').textContent=filtered.length+' de '+all.length+' incidencias de esta página (no es el total global)';renderRows(id,filtered,refund);return all}
function render(){if(!last)return;const a=renderSection('late_payments','payments',last,false),b=renderSection('redsys_bizum_refunds','refunds',last,true);const all=[...a,...b],summary=$('priority-summary');summary.replaceChildren();for(const level of ['critical','high','medium','low']){const span=document.createElement('span');span.className='uco-priority-count';span.textContent=labels[level]+': '+all.filter(x=>priority(x)===level).length;summary.appendChild(span)}$('export-csv').disabled=!(a.length||b.length);$('page').textContent='Página '+(Math.floor(offset/size)+1);$('prev').disabled=offset===0;$('next').disabled=a.length<size&&b.length<size}
async function load(){if(loading)return;loading=true;$('refresh').disabled=true;$('prev').disabled=true;$('next').disabled=true;$('message').textContent='Consultando incidencias…';try{const r=await fetch('../api/payment-incidents-admin-630.php?limit='+size+'&offset='+offset,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});const data=await r.json();if(!r.ok||!data.ok)throw new Error(data.error||'No se pudo consultar el informe');last=data;render();$('message').textContent=(data.warnings||[]).join(' · ')||'Clasificación orientativa sobre esta página. Sin confirmación bancaria.'}catch(e){last=null;$('export-csv').disabled=true;$('message').textContent=e.message;$('payments').replaceChildren();$('refunds').replaceChildren();$('priority-summary').replaceChildren();$('next').disabled=true;$('prev').disabled=offset===0}finally{loading=false;$('refresh').disabled=false}}

// Exportación de una instantánea local. No cambia estados ni crea expedientes.
function csvSafe(value){
 const str=String(value??'');
 // Evitar ejecución de fórmulas al abrir el CSV en Excel/Calc.
 const safe=/^[\s\x00-\x1f]*[=+@\-]/.test(str)?"'"+str:str;
 return '"'+safe.replace(/"/g,'""')+'"';
}
function exportCsv(){
 if(!last)return;
 const filter=$('priority').value;
 const rows=[['Tipo','ID de incidencia','Referencia','Pedido','Proveedor','Estado','Importe (céntimos)','Prioridad','Códigos de incidencia','Fecha de exportación (UTC)']];
 const generated=new Date().toISOString();
 for(const [key,type] of [['late_payments','Pago'],['redsys_bizum_refunds','Devolución']]){
  const section=last.sections?.[key];if(!section?.available)continue;
  for(const item of Array.isArray(section.items)?section.items:[]){
   const level=priority(item);if(filter!=='all'&&filter!==level)continue;
   const refund=type==='Devolución';
   rows.push([type,refund?item.intent_id??'':item.payment_id??'',refund?item.payment_reference:item.reference,
    item.order_id??'',item.provider??'',refund?item.refund_status:item.payment_state,
    refund?item.refund_amount_cents:item.amount_cents,labels[level],
    Array.isArray(item.flags)?item.flags.join(' | '):'',generated]);
  }
 }
 if(rows.length===1){$('message').textContent='No hay incidencias para exportar con el filtro actual.';return;}
 const csv='\uFEFF'+rows.map(row=>row.map(csvSafe).join(';')).join('\r\n');
 const url=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));
 const a=document.createElement('a');a.href=url;
 a.download='uco-incidencias-pagina-'+(Math.floor(offset/size)+1)+'-'+generated.slice(0,10)+'.csv';
 document.body.append(a);a.click();a.remove();
 setTimeout(()=>URL.revokeObjectURL(url),1000);
 $('message').textContent='CSV generado con '+(rows.length-1)+' incidencias de la página actual. No incluye otras páginas.';
}
$('export-csv').addEventListener('click',exportCsv);

$('priority').addEventListener('change',render);$('refresh').addEventListener('click',load);$('prev').addEventListener('click',()=>{if(offset>=size){offset-=size;load()}});$('next').addEventListener('click',()=>{offset+=size;load()});load();
})();
