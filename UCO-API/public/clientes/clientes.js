import {openUcoReceipt} from './receipt.js';
import {createOrderService} from '../services/order-service.js';
import {createCartService} from '../services/cart-service.js';
import './components/cart-elements.js';
import {createClientServices} from '../services/client-services.js';
import './components/views.js';
import './components/catalog-elements.js';
const app=document.querySelector('#app');let csrf='',customer=null,page=1,query='';const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const services=createClientServices(()=>csrf),request=services.request,cart=createCartService(()=>csrf),orders=createOrderService(()=>csrf);
let reservationEnds=0,reservationId=null,reservationInterval=null;
let cartSizeObserver=null,cartMutationObserver=null,cartHeightFrame=0;
function fitCartWindow(){
 const host=document.querySelector('#cart-view');
 if(!host||host.hidden)return;
 const panel=host.querySelector('.uco-cart-panel');if(!panel)return;
 const max=Math.max(240,window.innerHeight-24);
 const header=panel.querySelector('.uco-cart-header');
 const content=panel.querySelector('.uco-cart-content');
 const footer=panel.querySelector('.uco-cart-footer');
 const layout=panel.querySelector('.uco-cart-layout');
 // Height is the sum of visible blocks, not the old flex height.
 const parts=layout?[header,layout]:[header,content,footer];
 let height=parts.reduce((n,el)=>{
  if(!el||getComputedStyle(el).display==='none')return n;
  const rect=el.getBoundingClientRect(),style=getComputedStyle(el);
  const actual=Math.max(rect.height,el.scrollHeight);
  return n+actual+parseFloat(style.marginTop||0)+parseFloat(style.marginBottom||0);
 },0);
 const style=getComputedStyle(panel);
 height+=parseFloat(style.borderTopWidth||0)+parseFloat(style.borderBottomWidth||0);
 panel.classList.toggle('uco-cart-height-limited',height>max);
 panel.style.setProperty('--uco-cart-natural-height',Math.min(max,Math.ceil(height)+2)+'px');
}
function scheduleCartFit(){cancelAnimationFrame(cartHeightFrame);cartHeightFrame=requestAnimationFrame(fitCartWindow)}
function watchCartSize(){
 cartSizeObserver?.disconnect();
 const host=document.querySelector('#cart-view');if(!host)return;
 cartSizeObserver=new ResizeObserver(()=>scheduleCartFit());
 cartMutationObserver?.disconnect();
 cartMutationObserver=new MutationObserver(()=>{scheduleCartFit();});
 cartMutationObserver.observe(host,{childList:true,subtree:true,characterData:true});
 for(const selector of ['.uco-cart-header','.uco-cart-content','.uco-cart-footer','.uco-cart-layout','.uco-cart-left','.uco-cart-right','.uco-cart-lines','.uco-checkout-view','.uco-order-feedback','.uco-reservation-status','.uco-economy-quote']){
  const node=host.querySelector(selector);if(node)cartSizeObserver.observe(node);
 }
 scheduleCartFit();
}
window.addEventListener('resize',scheduleCartFit);

function reservationClock(){
 const el=document.querySelector('#cart-view .uco-reservation-status');if(!el)return;
 if(!reservationEnds){if(el.childElementCount)el.replaceChildren();return;}
 const seconds=Math.max(0,Math.ceil(reservationEnds-Date.now()/1000));
 if(!seconds){reservationEnds=0;reservationId=null;updatePaymentEntry();el.innerHTML='<div class="uco-reservation-expired">La reserva ha caducado. Comprueba nuevamente la disponibilidad antes de continuar.</div>';return;}
 const mm=String(Math.floor(seconds/60)).padStart(2,'0'),ss=String(seconds%60).padStart(2,'0');
 el.innerHTML=`<div class="uco-reservation-banner"><div class="uco-reservation-description"><span class="uco-reservation-label">RESERVA ACTIVA</span><strong>Productos reservados</strong><small>Tiempo restante para completar el pedido</small></div><time class="uco-reservation-timer" aria-label="${mm} minutos y ${ss} segundos">${mm}:${ss}</time></div>`;
}
async function syncReservation(){
 if(!customer)return;
 try{const d=await orders.status();const active=(d.orders||[]).find(o=>o.status==='reserved'&&Number(o.expires_at)>Date.now()/1000);
 const count=Number(document.querySelector('#cart-count')?.textContent||0);
 reservationEnds=active&&count>0?Number(active.expires_at):0;reservationId=active&&count>0?active.id:null;reservationClock();
 updatePaymentEntry();scheduleCartFit();
 }catch(e){console.warn('No se pudo consultar la reserva',e);}
}
function updatePaymentEntry(){
 const host=document.querySelector('#cart-view');if(!host||host.hidden)return;
 const footer=host.querySelector('.uco-cart-footer');if(!footer)return;
 let button=footer.querySelector('[data-payment-open]');
 const show=!!reservationId&&reservationEnds>Date.now()/1000&&Number(document.querySelector('#cart-count')?.textContent||0)>0;
 if(!show){button?.remove();scheduleCartFit();return;}
 if(!button){button=document.createElement('button');button.type='button';button.className='uco-payment-open';button.dataset.paymentOpen='';button.textContent='Elegir forma de pago';footer.append(button);}
 scheduleCartFit();
}
function startReservationClock(){if(reservationInterval)clearInterval(reservationInterval);reservationInterval=setInterval(reservationClock,1000);}
async function refreshCart(){
 const d=await cart.list(),empty=!d.items.length;
 document.querySelector('#cart-count').textContent=d.items.reduce((a,i)=>a+i.quantity,0);
 const view=document.querySelector('#cart-view');view.renderCart(d);
 view.querySelector('.uco-cart-panel')?.classList.toggle('uco-cart-empty-state',empty);
 if(empty){reservationEnds=0;reservationId=null;if(reservationInterval){clearInterval(reservationInterval);reservationInterval=null;}}
 else if(!reservationInterval)startReservationClock();
 reservationClock();await updateEconomyQuote();if(!empty)await syncReservation();else updatePaymentEntry();watchCartSize();return d;
}
async function updateEconomyQuote(){const footer=document.querySelector('#cart-view .uco-cart-footer');if(!footer)return;let box=footer.querySelector('.uco-economy-quote');if(!box){box=document.createElement('div');box.className='uco-economy-quote';footer.querySelector('.uco-cart-total')?.after(box);}try{const r=await fetch('../api/economy-quote.php',{credentials:'same-origin',cache:'no-store'}),q=await r.json();if(!r.ok)throw Error(q.error||'Error de cálculo');const money=c=>(c/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});box.innerHTML=q.ready?`<div><span>IVA</span><strong>${money(q.tax_cents)}</strong></div><div><span>Transporte</span><strong>${money(q.shipping_cents)}</strong></div><div class="uco-economy-grand"><span>TOTAL DEL PEDIDO</span><strong>${money(q.total_cents)}</strong></div>`:`<p class="uco-economy-pending">Total pendiente de configurar: ${q.issues.map(x=>esc(x)).join(' · ')}</p>`;}catch(e){box.textContent='No se pudo calcular el importe definitivo.'}}

async function cartAction(fn){try{await fn();await refreshCart()}catch(e){alert(e.details?.available!==undefined?`${e.message}. Disponibles: ${e.details.available}`:e.message);await refreshCart().catch(()=>{})}}
function login(){app.innerHTML=`<uco-client-login></uco-client-login>`;document.querySelector('#register').onclick=register;document.querySelector('#login-form').onsubmit=async e=>{e.preventDefault();try{const d=await request('login',{method:'POST',body:new FormData(e.currentTarget)});csrf=d.csrf;customer=d.customer;panel()}catch(err){document.querySelector('#error').textContent=err.message}}}
function register(){app.innerHTML=`<uco-client-register></uco-client-register>`;document.querySelector('#back-login').onclick=login;document.querySelector('#register-form').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget,button=form.querySelector('[type=submit]'),error=document.querySelector('#error');error.textContent='';button.disabled=true;try{const data=await request('register',{method:'POST',body:new FormData(form)});login();const note=document.querySelector('#error');note.style.color='hsl(109 52% 25%)';note.textContent=data.message}catch(err){error.textContent=err.message}finally{button.disabled=false}}}
let cartReturnFocus=null;
function closeCart(){cartSizeObserver?.disconnect();cartMutationObserver?.disconnect();const el=document.querySelector('#cart-view');if(el)el.hidden=true;document.body.classList.remove('uco-cart-open');if(cartReturnFocus?.isConnected)cartReturnFocus.focus({preventScroll:true});cartReturnFocus=null;}
function openCart(){const el=document.querySelector('#cart-view');if(!el)return;cartReturnFocus=document.activeElement;el.hidden=false;document.body.classList.add('uco-cart-open');refreshCart().then(syncReservation).catch(e=>{const m=el.querySelector('.uco-cart-message');if(m)m.textContent=e.message});el.querySelector('.uco-cart-close')?.focus({preventScroll:true});}
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!document.querySelector('#cart-view')?.hidden)closeCart();});
async function openPurchaseHistory(){
 let dialog=document.querySelector('#uco-client-history');
 if(!dialog){dialog=document.createElement('dialog');dialog.id='uco-client-history';dialog.className='uco-client-history-dialog';dialog.innerHTML='<div class="uco-client-history-head"><h2>Historial de compras</h2><button type="button" data-history-close aria-label="Cerrar">×</button></div><div class="uco-client-history-body" role="status"></div>';document.body.append(dialog);dialog.querySelector('[data-history-close]').onclick=()=>dialog.close();dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close()});}
 const body=dialog.querySelector('.uco-client-history-body');body.textContent='Cargando pedidos…';dialog.showModal();
 try{
  const r=await fetch('../api/orders.php?action=history',{credentials:'same-origin',cache:'no-store'});const d=await r.json();if(!r.ok)throw Error(d.error||'No se pudo consultar el historial');
  body.replaceChildren();if(!d.orders?.length){body.textContent='Todavía no tienes pedidos registrados.';return;}
  const money=v=>(Number(v||0)/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
  for(const o of d.orders){
   const card=document.createElement('details');card.className='uco-client-history-order';if(o.fulfillment_state==='delivered')card.classList.add('is-delivered');
   const summary=document.createElement('summary');const name=document.createElement('strong');name.textContent='PED-'+String(o.id).padStart(2,'0');const dateGroup=document.createElement('span');dateGroup.className='uco-history-date-group';const date=document.createElement('time');date.textContent=String(o.created_at||'').slice(0,16);dateGroup.append(date);const amount=document.createElement('b');amount.textContent=money(o.subtotal_cents);summary.append(name,dateGroup,amount);card.append(summary);
   const info=document.createElement('p');info.className='uco-client-order-status';
   const paid=o.status==='paid'&&o.payment_status==='simulated_approved';
   const stateNames={pending_preparation:'Pendiente de preparación',preparing:'En preparación',prepared:'Preparado',dispatched:'Enviado',delivered:'Entregado',incident:'Incidencia',cancelled:'Cancelado'};
   const payment=document.createElement('span');payment.textContent='Pago: '+(paid?'Aprobado (simulación)':String(o.payment_status||'Pendiente'));
   const progress=document.createElement('span');progress.className='uco-client-order-progress';if(o.fulfillment_state==='delivered'){progress.classList.add('is-delivered');progress.textContent='ENTREGADO';}else{progress.textContent='Preparación: '+(stateNames[o.fulfillment_state]||'Sin iniciar');}
   const badge=progress.cloneNode(true);badge.classList.add('uco-history-summary-state');badge.textContent=o.fulfillment_state==='delivered'?'ENTREGADO':(stateNames[o.fulfillment_state]||'Sin iniciar');dateGroup.append(badge);info.append(payment,progress);card.append(info);
   const ul=document.createElement('ul');for(const line of o.items||[]){const li=document.createElement('li');li.textContent=line.name+' · '+line.quantity+' '+line.unit+' · '+money(line.line_cents);ul.append(li)}card.append(ul);
   if(o.payment_status==='simulated_approved'){const pdf=document.createElement('button');pdf.type='button';pdf.className='uco-history-receipt';pdf.textContent='Ver justificante PDF';pdf.onclick=async()=>{try{await openUcoReceipt(o.id)}catch(e){alert('No se pudo abrir el justificante: '+e.message)}};card.append(pdf)}
   body.append(card);
  }
 }catch(e){body.textContent='No se pudo cargar el historial: '+e.message;}
}
function panel(){startReservationClock();app.innerHTML=`<uco-client-panel></uco-client-panel>`;document.querySelector('#customer-name').textContent=customer.username||customer.company||'cliente';document.querySelector('#logout').onclick=async()=>{try{await request('logout',{method:'POST'})}finally{csrf='';customer=null;reservationEnds=0;reservationId=null;closeCart();login()}};document.querySelector('#password').onclick=changePassword;
const purchaseToggle=document.querySelector('#uco-purchases-toggle');
const purchases=document.querySelector('.uco-purchases');
const optionsToggle=document.querySelector('#uco-options-toggle');
const optionsMenu=document.querySelector('.uco-options');
const setHeaderMenu=(node,button,open)=>{node.classList.toggle('is-open',open);button.setAttribute('aria-expanded',String(open));};
purchaseToggle.onclick=()=>{const open=!purchases.classList.contains('is-open');setHeaderMenu(optionsMenu,optionsToggle,false);setHeaderMenu(purchases,purchaseToggle,open);};
optionsToggle.onclick=()=>{const open=!optionsMenu.classList.contains('is-open');setHeaderMenu(purchases,purchaseToggle,false);setHeaderMenu(optionsMenu,optionsToggle,open);};
document.addEventListener('pointerdown',event=>{if(!purchases.contains(event.target))setHeaderMenu(purchases,purchaseToggle,false);if(!optionsMenu.contains(event.target))setHeaderMenu(optionsMenu,optionsToggle,false);});
document.addEventListener('keydown',event=>{if(event.key==='Escape'){setHeaderMenu(purchases,purchaseToggle,false);setHeaderMenu(optionsMenu,optionsToggle,false);}});
const cartCount=document.querySelector('#cart-count');const headerCartCount=document.querySelector('#uco-head-cart-count');
const syncHeaderCartCount=()=>{headerCartCount.textContent=cartCount.textContent;};syncHeaderCartCount();
new MutationObserver(syncHeaderCartCount).observe(cartCount,{childList:true,characterData:true,subtree:true});
document.querySelector('#uco-purchase-history').onclick=()=>{setHeaderMenu(purchases,purchaseToggle,false);openPurchaseHistory();};
document.querySelector('#cart-toggle').onclick=()=>{setHeaderMenu(purchases,purchaseToggle,false);const el=document.querySelector('#cart-view');if(el.hidden)openCart();else closeCart();};function showCheckoutNotice(message){
 const panel=document.querySelector('#cart-view .uco-cart-panel');
 if(!panel)return;
 panel.classList.remove('uco-cart-checkout-active');scheduleCartFit();
 const checkout=panel.querySelector('.uco-checkout-view');
 if(checkout)checkout.replaceChildren();
 const footer=panel.querySelector('.uco-cart-footer');
 if(!footer)return;
 let notice=footer.querySelector('.uco-checkout-notice');
 if(!notice){notice=document.createElement('p');notice.className='uco-checkout-notice';notice.setAttribute('role','status');footer.append(notice);}
 notice.textContent=message;
}

const ucoMoney=c=>(Number(c||0)/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
let ucoOrderDialog=null;
async function showOrderConfirmation(orderId,chosenMethod){
 const r=await fetch('../api/order-review.php?order_id='+encodeURIComponent(orderId),{credentials:'same-origin',cache:'no-store'});
 const d=await r.json();if(!r.ok)throw Error(d.error||'No se pudo revisar el pedido');
 // No sale del carrito si la revisión no puede mostrar importes fiables.
 if(!d.ready||!d.economy?.ready||d.economy.total_cents===null)
  throw Error('No se puede confirmar todavía: '+(d.issues||d.economy?.issues||[]).join(' · '));
 const pm={redsys:'Tarjeta bancaria',transfer:'Transferencia bancaria',paypal:'PayPal',bizum:'Bizum'};
 const dialog=document.createElement('dialog');dialog.className='uco-order-dialog';
 dialog.setAttribute('aria-labelledby','uco-order-dialog-title');
 const head=document.createElement('header');head.className='uco-order-dialog-head';
 head.innerHTML='<img src="../img/uco-logo-cabecera.svg" alt="UCO"><div><small>CONFIRMACIÓN DEL PEDIDO</small><h2 id="uco-order-dialog-title">Revisa tu pedido</h2></div>';
 const close=document.createElement('button');close.type='button';close.className='uco-order-dialog-close';close.textContent='×';close.setAttribute('aria-label','Cerrar confirmación');
 head.append(close);
 const scroll=document.createElement('div');scroll.className='uco-order-dialog-scroll';
 const section=(title)=>{const el=document.createElement('section');el.className='uco-order-review-section';const h=document.createElement('h3');h.textContent=title;el.append(h);scroll.append(el);return el};
 const meta=section('Datos del pedido');
 const info=document.createElement('p');info.textContent='Reserva n.º '+d.order_id+' · '+(pm[chosenMethod]||'Método de pago');
 meta.append(info);
 const delivery=section('Entrega');
 const addr=d.delivery||{};
 for(const text of [addr.contact,addr.phone,addr.address,[addr.postal_code,addr.city].filter(Boolean).join(' '),addr.province,addr.notes].filter(Boolean)){
  const p=document.createElement('p');p.textContent=text;delivery.append(p);
 }
 const items=section('Productos');
 const list=document.createElement('div');list.className='uco-order-review-items';
 for(const item of d.items){
  const line=document.createElement('article');line.className='uco-order-review-item';
  const img=document.createElement('img');img.alt='';img.loading='lazy';
  const src=String(item.image||'');
  img.src=/^\.\/img\/(?:productos\/[a-zA-Z0-9_-]+\.webp|[0-9]{2}\.webp)$/.test(src)?'../'+src.slice(2):'../img/00.webp';
  const content=document.createElement('div');
  const name=document.createElement('strong');name.textContent=item.name;
  const detail=document.createElement('small');detail.textContent=item.sku+' · '+item.quantity+' × '+ucoMoney(item.unit_price_cents)+' · '+(item.unit||'');
  content.append(name,detail);
  const price=document.createElement('b');price.textContent=ucoMoney(item.line_cents);
  line.append(img,content,price);list.append(line);
 }
 items.append(list);
 const costs=section('Desglose económico');
 const amounts=[['Subtotal',d.economy.subtotal_cents],['IVA',d.economy.tax_cents],['Transporte',d.economy.shipping_cents],['TOTAL DEFINITIVO',d.economy.total_cents]];
 for(const [label,cents] of amounts){
  const line=document.createElement('div');line.className='uco-order-review-amount'+(label==='TOTAL DEFINITIVO'?' total':'');
  const l=document.createElement('span');l.textContent=label;
  const v=document.createElement('strong');v.textContent=ucoMoney(cents);line.append(l,v);costs.append(line);
 }
 const foot=document.createElement('footer');foot.className='uco-order-dialog-foot';
 const note=document.createElement('p');note.textContent='ENTORNO DE PRUEBAS · Aún no se ha confirmado el pedido ni realizado ningún cobro.';
 const actions=document.createElement('div');actions.className='uco-order-dialog-actions';
 const back=document.createElement('button');back.type='button';back.className='uco-order-review-back';back.textContent='Volver a forma de pago';
 const confirm=document.createElement('button');confirm.type='button';confirm.className='uco-order-review-confirm';confirm.textContent='Continuar al pago simulado';confirm.disabled=false;
 const explanation=document.createElement('small');explanation.textContent='ENTORNO DE PRUEBAS. Ningún método realiza cobros reales.';
 actions.append(back,confirm);foot.append(note,actions,explanation);
 const simulation=document.createElement('div');simulation.className='uco-simulation-controls';simulation.hidden=true;
 const simText=document.createElement('p');simText.textContent='SIMULADOR DE PAGO · No se realizará ningún cobro real.';
 const approved=document.createElement('button');approved.type='button';approved.textContent='Simular aprobación';
 const rejected=document.createElement('button');rejected.type='button';rejected.textContent='Simular rechazo';
 const cancelled=document.createElement('button');cancelled.type='button';cancelled.textContent='Cancelar operación';
 simulation.append(simText,approved,rejected,cancelled);foot.append(simulation);
 const simPost=async(action)=>{
  const formData=new FormData();formData.set('action',action);formData.set('order_id',String(orderId));formData.set('method',chosenMethod);
  const response=await fetch('../api/payment-simulator.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrf},body:formData});
  const result=await response.json();if(!response.ok)throw Error(result.error||'Error al procesar la simulación');
  return result;
 };
 confirm.addEventListener('click',async()=>{
  confirm.disabled=true;
  try{
   const result=await simPost('start');
   if(result.state!=='pending')throw Error('Esta operación ya está '+result.state+'. Consulta el historial de pedidos.');
   simulation.hidden=false;close.disabled=true;
   simText.textContent='PASARELA SIMULADA · '+result.reference+' · '+ucoMoney(result.total_cents)+' · Ningún cobro real.';
   confirm.hidden=true;back.disabled=true;
  }catch(e){confirm.disabled=false;alert(e.message);}
 });
 for(const [button,action] of [[approved,'approve'],[rejected,'reject'],[cancelled,'cancel']]){
  button.addEventListener('click',async()=>{
   approved.disabled=rejected.disabled=cancelled.disabled=true;
   try{
    const result=await simPost(action);
    simulation.replaceChildren();
    const status=document.createElement('p');status.textContent=(result.state==='approved'?'PAGO SIMULADO APROBADO':result.state==='rejected'?'PAGO SIMULADO RECHAZADO':'OPERACIÓN SIMULADA CANCELADA')+' · '+result.reference;
    simulation.append(status);
    note.textContent='OPERACIÓN DE PRUEBA. No se ha realizado ningún cobro real.';
    explanation.textContent=result.state==='approved'?'Puedes consultar, imprimir o descargar el justificante de prueba.':'La operación no ha sido aprobada; no se emitirá justificante.';
    if(result.state==='approved'){
     const receipt=document.createElement('button');receipt.type='button';receipt.textContent='Ver justificante del pedido';receipt.className='uco-order-review-confirm';
     receipt.onclick=()=>openUcoReceipt(orderId).catch(e=>alert('Justificante: '+e.message));
     simulation.append(receipt);
     openUcoReceipt(orderId).catch(e=>alert(e.message));
    }
    back.disabled=false;close.disabled=false;back.textContent='Cerrar y volver al carrito';
   }catch(e){alert(e.message);approved.disabled=rejected.disabled=cancelled.disabled=false;}
  });
 }

 dialog.append(head,scroll,foot);
 document.body.append(dialog);
 const cartWasOpen=!document.querySelector('#cart-view')?.hidden;
 if(cartWasOpen)closeCart();
 ucoOrderDialog=dialog;
 const exit=()=>{dialog.close();dialog.remove();ucoOrderDialog=null;if(cartWasOpen)openCart()};
 back.onclick=exit;close.onclick=exit;
 dialog.addEventListener('cancel',e=>{e.preventDefault();exit()});
 dialog.showModal();
}

function ensureCheckoutLayout(){
 const panel=document.querySelector('#cart-view .uco-cart-panel');
 if(!panel)return null;
 let layout=panel.querySelector('.uco-cart-layout');
 if(!layout){
  layout=document.createElement('div');layout.className='uco-cart-layout';
  const left=document.createElement('div');left.className='uco-cart-left';
  const right=document.createElement('div');right.className='uco-cart-right';
  const content=panel.querySelector('.uco-cart-content'),footer=panel.querySelector('.uco-cart-footer');
  panel.append(layout);layout.append(left,right);left.append(content,footer);
  const checkout=footer?.querySelector('.uco-checkout-view');
  if(checkout)right.append(checkout);
 }
 return {panel,layout,out:panel.querySelector('.uco-checkout-view')};
}
document.querySelector('#cart-view').onclick=async e=>{if(e.target.closest('[data-order-checkout]')){const feedback=document.querySelector('#cart-view .uco-order-feedback');if(feedback)feedback.textContent='';document.querySelector('#cart-view .uco-checkout-notice')?.remove();
 const {panel}=ensureCheckoutLayout();
 panel.classList.add('uco-cart-checkout-active');scheduleCartFit();
 const out=panel.querySelector('.uco-checkout-view');out.textContent='Comprobando existencias y condiciones del pedido…';try{const preview=await orders.preview();if(!preview.valid){showCheckoutNotice(preview.issues.length?preview.issues.map(i=>`${i.name}: solicitadas ${i.requested}, disponibles ${i.available}`).join(' · '):'El carrito está vacío.');return;}const methods=await orders.methods();const format=c=>(c/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});out.innerHTML=`<section class="uco-checkout-box"><div class="uco-checkout-title"><span class="uco-checkout-kicker">REVISIÓN DE COMPRA</span><h3>Resumen del pedido</h3><p>Comprueba los detalles antes de reservar tus productos.</p></div><div class="uco-checkout-metrics"><div><span>Referencias</span><strong>${preview.items.length}</strong></div><div><span>Subtotal provisional</span><strong>${format(preview.subtotal_cents)}</strong></div></div><div class="uco-checkout-info"><strong>Importe pendiente de confirmar</strong><p>Los impuestos y la entrega se calcularán en el cierre definitivo.<br>En esta fase no se realizará ningún cobro.</p></div><div class="uco-checkout-methods"><h4>Formas de pago previstas</h4><p>${methods.methods.map(m=>m.name).join(' · ')}</p></div><form class="uco-delivery-form" id="uco-delivery-form"><h4>Datos de entrega</h4><p>Indica dónde quieres recibir el pedido. Podrás modificarlos antes de la confirmación definitiva.</p><div class="uco-delivery-grid"><label>Persona de contacto<input name="contact" maxlength="100" autocomplete="name" required></label><label>Teléfono<input name="phone" maxlength="35" autocomplete="tel" required></label><label class="wide">Dirección de entrega<input name="address" maxlength="180" autocomplete="street-address" required></label><label>Código postal<input name="postal_code" maxlength="12" autocomplete="postal-code" required></label><label>Localidad<input name="city" maxlength="90" autocomplete="address-level2" required></label><label class="wide">Provincia<input name="province" maxlength="90" autocomplete="address-level1" required></label><label class="wide">Observaciones<textarea name="notes" maxlength="500" rows="2"></textarea></label></div><p class="uco-delivery-feedback" role="status" aria-live="polite"></p></form><p class="uco-checkout-hint">Al continuar, reservaremos tus existencias durante <strong>10 minutos</strong>.</p><button type="button" class="primary" data-order-reserve>Reservar existencias</button><button type="button" class="secondary" data-order-back>Volver al carrito</button></section>`;try{const saved=await fetch('../api/checkout.php',{credentials:'same-origin',cache:'no-store'});if(saved.ok){const data=await saved.json();if(data.details){const form=out.querySelector('#uco-delivery-form');for(const [key,value] of Object.entries(data.details)){const field=form.elements.namedItem(key);if(field)field.value=value??'';}}}}catch(error){console.warn('No se pudieron recuperar los datos de entrega',error)}}catch(err){showCheckoutNotice(err.message);}return;}if(e.target.closest('[data-order-back]')){document.querySelector('#cart-view .uco-checkout-view')?.replaceChildren();document.querySelector('#cart-view .uco-cart-panel')?.classList.remove('uco-cart-checkout-active');await syncReservation();updatePaymentEntry();scheduleCartFit();return;}if(e.target.closest('[data-order-reserve]')){const out=document.querySelector('.uco-checkout-view');try{const delivery=out.querySelector('#uco-delivery-form');if(delivery){if(!delivery.reportValidity())return;const response=await fetch('../api/checkout.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'X-CSRF-Token':csrf},body:new FormData(delivery)});const data=await response.json();if(!response.ok)throw Error(data.error||'No se pudieron guardar los datos de entrega');}await syncReservation();
 if(reservationId&&reservationEnds>Date.now()/1000){
  showCheckoutNotice('Ya tienes una reserva activa. Puedes continuar con la forma de pago.');
  updatePaymentEntry();return;
 }
 const r=await orders.reserve();reservationEnds=Number(r.expires_at);reservationId=r.order_id;reservationClock();showCheckoutNotice('Existencias reservadas correctamente. Todavía no se ha confirmado ni cobrado el pedido.');
 updatePaymentEntry();
}catch(err){showCheckoutNotice(err.details?.issues?.length?err.details.issues.map(i=>`${i.name}: disponibles ${i.available}`).join(' · '):err.message);}return;}if(e.target.closest('[data-payment-open]')){const quoteResponse=await fetch('../api/economy-quote.php',{credentials:'same-origin',cache:'no-store'});const quote=await quoteResponse.json();if(!quote.ready){showCheckoutNotice('Antes de elegir el pago hay que completar los impuestos, el transporte y la dirección de entrega.');return;}
 const structure=ensureCheckoutLayout();
 if(!structure||!structure.out){showCheckoutNotice('No se pudo abrir el formulario de pago. Vuelve a intentarlo.');return;}
 const {panel,out}=structure;
 panel.classList.add('uco-cart-checkout-active');scheduleCartFit();
 out.innerHTML='<section class="uco-payment-step"><h3 class="uco-payment-main-title">Forma de pago</h3><p>Selecciona cómo deseas pagar. Ningún cobro se realizará todavía.</p><div class="uco-payment-loading">Comprobando la reserva y los datos de entrega…</div></section>';
 try{
  const r=await fetch('../api/payment-selection.php',{credentials:'same-origin',cache:'no-store'});
  const data=await r.json();if(!r.ok)throw Error(data.error||'No se pudieron consultar los pagos');
  const step=out.querySelector('.uco-payment-step');
  step.querySelector('.uco-payment-loading').remove();
  const labels={redsys:'Tarjeta bancaria · TPV multibanco',transfer:'Transferencia bancaria',paypal:'PayPal',bizum:'Bizum'};
  const descriptions={redsys:'Pago seguro con el TPV bancario contratado por UCO',transfer:'Instrucciones bancarias al confirmar el pedido',paypal:'Pago mediante una cuenta PayPal',bizum:'Pago móvil con Bizum a través del TPV habilitado'};
  const form=document.createElement('form');form.className='uco-payment-form';
  for(const key of ['redsys','transfer','paypal','bizum']){
   const label=document.createElement('label');label.className='uco-payment-option';
   const input=document.createElement('input');input.type='radio';input.name='payment_method';input.value=key;input.required=true;
   if(key===data.selected_method)input.checked=true;
   const text=document.createElement('span'),strong=document.createElement('strong'),small=document.createElement('small');
   strong.textContent=labels[key];small.textContent=descriptions[key];text.append(strong,small);label.append(input,text);form.append(label);
  }
  const msg=document.createElement('p');msg.className='uco-payment-feedback';msg.setAttribute('role','status');
  const button=document.createElement('button');button.type='submit';button.className='uco-payment-save';button.textContent='Guardar forma de pago';
  form.append(button,msg);step.append(form);scheduleCartFit();
  const back=document.createElement('button');back.type='button';back.className='uco-payment-back';back.dataset.orderBack='';back.textContent='Volver al carrito';step.append(back);
  const brands=document.createElement('div');brands.className='uco-payment-brands';
  const caption=document.createElement('p');caption.textContent='MÉTODOS DE PAGO';brands.append(caption);
  const row=document.createElement('div');row.className='uco-payment-brands-row';
  const logoChoices=[['visa','Visa','redsys'],['mastercard','Mastercard','redsys'],['paypal','PayPal','paypal'],['bizum','Bizum','bizum']];
  const logoButtons=[];
  const syncPaymentLogos=()=>{
   const selected=form.querySelector('input[name="payment_method"]:checked')?.value||'';
   for(const b of logoButtons){
    const active=b.dataset.method===selected;
    b.classList.toggle('is-selected',active);
    b.setAttribute('aria-pressed',String(active));
   }
   scheduleCartFit();
  };
  for(const [key,name,method] of logoChoices){
   const tile=document.createElement('button');tile.type='button';tile.className='uco-payment-brand';
   tile.dataset.method=method;tile.setAttribute('aria-label','Seleccionar pago con '+name);
   tile.setAttribute('aria-pressed','false');tile.title='Seleccionar '+name;
   const logo=document.createElement('img');logo.alt=name;logo.loading='lazy';
   logo.src='https://cdn.jsdelivr.net/npm/simple-icons@v15/icons/'+key+'.svg';
   logo.onerror=()=>{logo.remove();tile.textContent=name};
   tile.append(logo);
   tile.addEventListener('click',()=>{
    const radio=Array.from(form.querySelectorAll('input[name="payment_method"]')).find(i=>i.value===method);
    if(!radio)return;
    radio.checked=true;radio.dispatchEvent(new Event('change',{bubbles:true}));
    syncPaymentLogos();
   });
   logoButtons.push(tile);row.append(tile);
  }
  brands.append(row);
  // Los logos se muestran ANTES del listado; el formulario y su envio siguen intactos.
  step.insertBefore(brands,form);
  form.addEventListener('change',e=>{if(e.target.matches('input[name="payment_method"]'))syncPaymentLogos()});
  syncPaymentLogos();
  scheduleCartFit();

  form.onsubmit=async ev=>{
   ev.preventDefault();button.disabled=true;msg.textContent='Guardando selección…';
   try{
    const fd=new FormData(form);
    const resp=await fetch('../api/payment-selection.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'X-CSRF-Token':csrf},body:fd});
    const result=await resp.json();if(!resp.ok)throw Error(result.error||'No se pudo guardar el método');
    await showOrderConfirmation(result.order_id,fd.get('payment_method'));
   }catch(err){msg.textContent=err.message}finally{button.disabled=false}
  };
 }catch(err){out.querySelector('.uco-payment-step').innerHTML='<h3>Forma de pago</h3><p class="uco-payment-feedback"></p>';out.querySelector('.uco-payment-feedback').textContent=err.message;scheduleCartFit();}
 return;
}
if(e.target.closest('[data-order-preview]')){const out=document.querySelector('.uco-order-feedback');out.textContent='Comprobando existencias…';orders.preview().then(d=>{out.textContent=d.valid?'Todos los productos están disponibles en este momento. Se verificarán de nuevo antes del cobro.':d.issues.length?d.issues.map(i=>`${i.name}: solicitadas ${i.requested}, disponibles ${i.available}${i.reason==='sin_precio'?' (sin precio)':''}`).join(' · '):'El carrito está vacío.'}).catch(err=>{out.textContent=err.message});return;}if(e.target.closest('[data-cart-close]')){closeCart();return;}const b=e.target.closest('[data-cart-remove]');if(b)cartAction(()=>cart.set(b.dataset.cartRemove,0))};document.querySelector('#cart-view').onchange=e=>{const input=e.target.closest('[data-cart-quantity]');if(input)cartAction(()=>cart.set(input.dataset.cartQuantity,input.value))};document.querySelector('uco-client-product-list').onclick=e=>{const b=e.target.closest('[data-cart-add]');if(!b)return;const field=b.closest('.client-card')?.querySelector('[data-product-units]');const raw=field?.value.trim()||'1';const amount=Number(raw);if(!/^\d+$/.test(raw)||!Number.isSafeInteger(amount)||amount<1||amount>99999){if(field){field.setCustomValidity('Introduce una cantidad entera entre 1 y 99999.');field.reportValidity();field.addEventListener('input',()=>field.setCustomValidity(''),{once:true})}return;}cartAction(async()=>{const d=await cart.list();const existing=d.items.find(i=>i.product_id===Number(b.dataset.cartAdd));await cart.set(b.dataset.cartAdd,(existing?.quantity||0)+amount);if(field)field.value='1'});};refreshCart().catch(()=>{});let t;document.querySelector('#q').oninput=e=>{clearTimeout(t);t=setTimeout(()=>{query=e.target.value.trim();page=1;load()},250)};document.querySelector('#prev').onclick=()=>{page--;load()};document.querySelector('#next').onclick=()=>{page++;load()};load()}
async function load(){const msg=document.querySelector('#msg');msg.textContent='Cargando productos…';try{const d=await services.catalog.list({page,q:query});msg.textContent='';document.querySelector('#count').textContent=`${d.total} productos`;document.querySelector('uco-client-product-list').renderProducts(d.products,esc);document.querySelector('#paging').textContent=`Página ${d.page} de ${Math.max(1,Math.ceil(d.total/d.limit))}`;document.querySelector('#prev').disabled=d.page<=1;document.querySelector('#next').disabled=d.page*d.limit>=d.total}catch(err){msg.textContent=err.message}}
function changePassword(){const d=document.createElement('dialog');d.className='password-dialog';d.append(document.createElement('uco-client-password-content'));document.body.append(d);d.querySelector('#close').onclick=d.querySelector('#cancel').onclick=()=>d.close();d.addEventListener('close',()=>d.remove());d.querySelector('form').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.currentTarget);if(f.get('new_password')!==f.get('confirm')){d.querySelector('#err').textContent='Las contraseñas no coinciden';return}try{await request('change_password',{method:'POST',body:f});d.close();alert('Contraseña actualizada.')}catch(err){d.querySelector('#err').textContent=err.message}};d.showModal()}
(async()=>{try{const d=await request('session');if(d.authenticated){csrf=d.csrf;customer=d.customer;panel()}else login()}catch(err){login()}})();
