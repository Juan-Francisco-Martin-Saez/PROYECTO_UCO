/* Confirmaciones reutilizables: siempre dentro del formulario y sin perder sus datos. */
const escapeText=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
class UcoConfirmChange extends HTMLElement{
 show({title='Confirmar cambios',message='',previous='',next='',confirm='Confirmar cambio',danger=false}={}){
  this.innerHTML=`<section class="uco-confirm-change" role="alertdialog" aria-label="${escapeText(title)}"><div class="uco-confirm-heading"><span aria-hidden="true">!</span><strong>${escapeText(title)}</strong></div><p>${escapeText(message)}</p>${previous!==''||next!==''?`<div class="uco-confirm-values"><div><small>Valor anterior</small><strong>${escapeText(previous)}</strong></div><div><small>Valor nuevo</small><strong>${escapeText(next)}</strong></div></div>`:''}<div class="uco-confirm-actions"><button type="button" class="primary" data-yes>${escapeText(confirm)}</button><button type="button" class="secondary" data-no>Cancelar y seguir editando</button></div></section>`;
  if(danger)this.querySelector('[data-yes]').classList.add('danger-confirm');
  return new Promise(resolve=>{this.querySelector('[data-yes]').onclick=()=>resolve(true);this.querySelector('[data-no]').onclick=()=>resolve(false);this.querySelector('[data-no]').focus()});
 }
}
customElements.define('uco-confirm-change',UcoConfirmChange);
export async function confirmChange(dialog,config){
 const form=dialog?.querySelector('form');if(!form)return false;
 const actions=form.querySelector(':scope > .buttons');const box=document.createElement('uco-confirm-change');
 actions?.before(box);if(actions)actions.hidden=true;
 let onClose;const closed=new Promise(resolve=>{onClose=()=>resolve(false);dialog.addEventListener('close',onClose,{once:true})});
 try{return await Promise.race([box.show(config),closed])}finally{dialog.removeEventListener('close',onClose);box.remove();if(actions)actions.hidden=false}
}
export function warnUnsaved(dialog){
 const form=dialog.querySelector('form');if(!form)return;
 let initial='';const snapshot=()=>JSON.stringify([...new FormData(form).entries()].filter(([,v])=>typeof v==='string'));
 queueMicrotask(()=>initial=snapshot());let saving=false;
 dialog.addEventListener('uco-saved',()=>{saving=true});
 dialog.addEventListener('cancel',async e=>{if(saving||snapshot()===initial)return;e.preventDefault();if(await confirmChange(dialog,{title:'Cambios sin guardar',message:'Has modificado datos de este formulario. ¿Quieres descartarlos?',confirm:'Descartar cambios',danger:true}))dialog.close()});
 dialog.querySelectorAll('[data-cancel]').forEach(b=>{b.onclick=async()=>{if(saving||snapshot()===initial||await confirmChange(dialog,{title:'Cambios sin guardar',message:'Se perderán los cambios introducidos.',confirm:'Descartar cambios',danger:true}))dialog.close()}});
}
export async function confirmStandalone(config){
 const d=document.createElement('dialog');d.className='uco-standalone-confirm';d.innerHTML='<form method="dialog"><uco-confirm-change></uco-confirm-change></form>';document.body.append(d);d.showModal();
 try{return await Promise.race([d.querySelector('uco-confirm-change').show(config),new Promise(resolve=>d.addEventListener('close',()=>resolve(false),{once:true}))])}finally{d.close();d.remove()}
}
