(()=>{'use strict';
const $=id=>document.getElementById(id);let csrf='';
const msg=s=>$('followup-message').textContent=s;
async function request(method,payload){
 const type=$('followup-type').value,reference=$('followup-reference').value.trim();
 if(!reference)throw new Error('Introduce la referencia exacta');
 const url='../api/payment-incident-followup-633.php';
 const r=await fetch(method==='GET'?url+'?'+new URLSearchParams({type,reference}):url,{
 method,credentials:'same-origin',cache:'no-store',
 headers:{Accept:'application/json',...(method==='POST'?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{})},
 ...(method==='POST'?{body:JSON.stringify({type,reference,...payload})}:{})});
 const data=await r.json();if(!r.ok||!data.ok)throw new Error(data.error||'Error de seguimiento');return data;
}
async function load(){
 msg('Consultando historial…');$('followup-history').replaceChildren();
 try{const d=await request('GET');csrf=d.csrf;const rows=d.events||[];
 for(const e of rows){const a=document.createElement('article');const h=document.createElement('strong');h.textContent=e.created_at+' · '+e.status+' · '+(e.owner||'Sin responsable');
 const p=document.createElement('p');p.textContent=e.note;a.append(h,p);$('followup-history').append(a)}
 if(!rows.length)$('followup-history').textContent='Sin observaciones registradas.';
 msg(rows.length+' anotaciones consultadas. El seguimiento no acredita operaciones bancarias.');
 }catch(e){msg(e.message)}
}
$('followup-load').addEventListener('click',load);
$('followup-form').addEventListener('submit',async e=>{
 e.preventDefault();try{
 if(!csrf)await load();
 if(!csrf)throw new Error('No se pudo validar la sesión');
 await request('POST',{status:$('followup-status').value,owner:$('followup-owner').value.trim(),note:$('followup-note').value.trim()});
 $('followup-note').value='';await load();msg('Observación registrada. No se han modificado pagos ni pedidos.');
 }catch(err){msg(err.message)}
});
})();