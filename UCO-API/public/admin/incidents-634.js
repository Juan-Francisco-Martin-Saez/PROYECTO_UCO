(()=>{'use strict';
const $=id=>document.getElementById(id);let offset=0,total=0;const limit=25;
const message=s=>$('overview-message').textContent=s;
async function load(){
 message('Consultando expedientes…');$('overview-results').replaceChildren();
 const params=new URLSearchParams({status:$('overview-status').value,type:$('overview-type').value,q:$('overview-query').value.trim(),limit:String(limit),offset:String(offset)});
 try{const response=await fetch('../api/payment-incident-overview-634.php?'+params,{credentials:'same-origin',cache:'no-store'});
 const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'No se pudo consultar');
 total=data.total||0;
 for(const item of data.items){const article=document.createElement('article');article.className='uco-overview-result';
 const label=document.createElement('strong');label.textContent=(item.incident_type==='payment'?'Pago':'Devolución')+' · '+item.incident_reference;
 const state=document.createElement('small');state.textContent=item.status+' · '+(item.owner||'Sin responsable');
 const note=document.createElement('p');note.textContent=item.note||'Sin observación';
 const date=document.createElement('small');date.textContent=item.created_at;
 article.append(label,state,note,date);$('overview-results').append(article);}
 if(!data.items.length)$('overview-results').textContent='No hay expedientes con estos filtros.';
 $('overview-page').textContent=total?`${offset+1}–${Math.min(offset+limit,total)} de ${total}`:'0 resultados';
 $('overview-prev').disabled=offset===0;$('overview-next').disabled=offset+limit>=total;
 message(data.table_missing?'El registro aún no está inicializado.':'Consulta administrativa de solo lectura.');
 }catch(error){message(error.message);$('overview-prev').disabled=true;$('overview-next').disabled=true;}
}
$('overview-refresh').addEventListener('click',()=>{offset=0;load()});
$('overview-prev').addEventListener('click',()=>{offset=Math.max(0,offset-limit);load()});
$('overview-next').addEventListener('click',()=>{if(offset+limit<total){offset+=limit;load()}});
$('overview-results').addEventListener('click',()=>{});
load();
})();