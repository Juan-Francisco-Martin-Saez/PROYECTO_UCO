(()=>{'use strict';
const $=id=>document.getElementById(id),labels={open:'Abierta',investigating:'En investigación',awaiting_provider:'Esperando al proveedor'};
async function load(){
 $('aging-message').textContent='Consultando expedientes…';$('aging-count').textContent='—';$('aging-results').replaceChildren();
 try{
  const r=await fetch('../api/payment-incident-aging-636.php?days='+encodeURIComponent($('aging-days').value),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
  const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||'Error de consulta');
  $('aging-count').textContent=String(d.total);
  for(const item of d.items){
   const article=document.createElement('article');article.className='uco-aging-item';
   const name=document.createElement('div');const strong=document.createElement('strong');strong.textContent=item.incident_reference;
   const type=document.createElement('small');type.textContent=item.incident_type==='refund'?'Devolución':'Pago';name.append(strong,type);
   const detail=document.createElement('div');const state=document.createElement('strong');state.textContent=labels[item.status]||item.status;
   const owner=document.createElement('small');owner.textContent='Responsable: '+(item.owner||'Sin asignar');detail.append(state,owner);
   const days=document.createElement('span');days.className='uco-aging-days';days.textContent=String(Math.max(0,Number(item.age_days)||0))+' días';
   const actions=document.createElement('div');actions.className='uco-aging-actions';
   const open=document.createElement('button');open.type='button';open.className='uco-aging-open';open.textContent='Ver en expedientes';
   const history=document.createElement('button');history.type='button';history.className='uco-aging-history';history.textContent='Consultar historial';
   actions.append(open,history);article.append(name,detail,days,actions);
   open.addEventListener('click',()=>{
    $('overview-status').value=item.status;
    $('overview-type').value=item.incident_type;
    $('overview-query').value=item.incident_reference;
    $('overview-refresh').click();
    $('overview-title').scrollIntoView({behavior:'smooth',block:'start'});
   });
   history.addEventListener('click',()=>{
    $('followup-type').value=item.incident_type;
    $('followup-reference').value=item.incident_reference;
    $('followup-load').click();
    $('followup-title').scrollIntoView({behavior:'smooth',block:'start'});
   });
   $('aging-results').append(article);
  }
  if(!d.items.length)$('aging-results').textContent='No hay expedientes que superen el umbral seleccionado.';
  $('aging-message').textContent=d.limited?'Mostrando los 50 más antiguos de '+d.total+' expedientes.':'Consulta administrativa, sin cambios en operaciones bancarias.';
 }catch(e){$('aging-message').textContent=e.message}
}
$('aging-days').addEventListener('change',load);$('aging-refresh').addEventListener('click',load);
load();
})();