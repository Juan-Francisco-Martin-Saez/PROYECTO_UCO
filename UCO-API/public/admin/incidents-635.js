(()=>{'use strict';
const grid=document.getElementById('summary-635-grid'),message=document.getElementById('summary-635-message');
if(!grid||!message)return;
const labels={open:'Abiertos',investigating:'En investigación',awaiting_provider:'Esperando al proveedor',reviewed:'Revisados'};
async function load(){
 try{const r=await fetch('../api/payment-incident-summary-635.php',{credentials:'same-origin',cache:'no-store'});const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Error de consulta');
 grid.replaceChildren();for(const [key,label] of Object.entries(labels)){
 const card=document.createElement('div');card.className='uco-summary-item';
 const number=document.createElement('strong');number.textContent=String(d.counts?.[key]??0);
 const caption=document.createElement('span');caption.textContent=label;card.append(number,caption);grid.append(card);
 }
 message.textContent=d.table_missing?'El registro todavía no está inicializado.':`${d.total} expedientes · estados administrativos, no bancarios.`;
 }catch(e){grid.textContent='No se pudo cargar el resumen.';message.textContent=e.message;}
}
load();document.getElementById('overview-refresh')?.addEventListener('click',load);
})();
