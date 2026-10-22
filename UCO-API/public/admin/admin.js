import {openAdminOrders} from './orders-panel.js';
import {confirmChange,confirmStandalone,warnUnsaved} from './components/confirm-elements.js';
import {mountPager} from './components/list-pager.js';
import {UcoAdminViews as adminViews} from './components/area-elements.js';
import {createAdminServices} from '../services/admin-services.js';
import './components/views.js';
import './components/commercial-elements.js';
import './components/management-elements.js';
import {initTariffs} from './tarifas.js';
const app=document.querySelector('#app'),api='../api/admin.php';let token='',categories=[],products=[],timer,currentUser=null,users=[];const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const services=createAdminServices(()=>token),req=services.request;
function login(){app.innerHTML=`<uco-admin-login></uco-admin-login>`;document.querySelector('#login').onsubmit=async e=>{e.preventDefault();const f=new FormData();f.set('username',document.querySelector('#username').value.trim());f.set('password',document.querySelector('#pass').value);try{const d=await req('login',{method:'POST',body:f});token=d.csrf;currentUser=d.user;panel();await load()}catch(err){document.querySelector('#error').textContent=err.message}}}
function closeAdminMenu(){document.querySelector('#admin-menu')?.classList.remove('is-open');document.querySelector('#menu-toggle')?.setAttribute('aria-expanded','false')}
function panel(){app.innerHTML=`<uco-admin-panel></uco-admin-panel>`;
const productTable=document.querySelector('#rows')?.closest('table');if(productTable&&!productTable.closest('uco-product-table')){const host=document.createElement('uco-product-table');productTable.before(host);host.append(productTable)}
document.querySelector('#logged-user').textContent=currentUser?.display_name||currentUser?.username||'';setAvatar(document.querySelector('#logged-avatar'),currentUser?.avatar);document.querySelector('#users-nav').hidden=currentUser?.role!=='admin';document.querySelector('#security-desktop-admin').hidden=currentUser?.role!=='admin';document.querySelector('#security-desktop-self').hidden=currentUser?.role==='admin';document.querySelector('#customers-nav').hidden=currentUser?.role!=='admin';document.querySelector('#tariffs-main-nav').hidden=currentUser?.role!=='admin';const switchSection=section=>{closeAdminMenu();document.querySelector('#catalog-area').hidden=section!=='catalog';document.querySelector('#users-area')?.remove();document.querySelector('#commercial-area')?.remove();document.querySelector('#orders-area')?.remove();document.querySelectorAll('.admin-section-nav button').forEach(b=>b.classList.toggle('active',b.id===({catalog:'catalog-nav',customers:'customers-nav',tariffs:'tariffs-main-nav',orders:'orders-nav'}[section])));if(section==='orders')openAdminOrders();else if(section!=='catalog')showCommercial(section==='tariffs')};document.querySelector('#catalog-nav').onclick=()=>switchSection('catalog');document.querySelector('#customers-nav').onclick=()=>switchSection('customers');document.querySelector('#tariffs-main-nav').onclick=()=>switchSection('tariffs');document.querySelector('#orders-nav').onclick=()=>switchSection('orders');document.querySelector('#users-nav').onclick=()=>{closeAdminMenu();switchSection('customers')};document.querySelector('#my-security').onclick=()=>{closeAdminMenu();passwordDialog()};document.querySelector('#security-desktop-self').onclick=()=>passwordDialog();document.querySelector('#security-desktop-admin').onclick=()=>{const box=document.querySelector('#security-dropdown');const expanded=box.hidden;box.hidden=!expanded;document.querySelector('#security-desktop-admin').setAttribute('aria-expanded',String(expanded))};document.querySelector('#security-desktop-users').onclick=()=>{document.querySelector('#security-dropdown').hidden=true;switchSection('customers')};document.querySelector('#security-desktop-own').onclick=()=>{document.querySelector('#security-dropdown').hidden=true;passwordDialog()};const toggle=document.querySelector('#menu-toggle');toggle.onclick=()=>{const open=document.querySelector('#admin-menu').classList.toggle('is-open');toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Cerrar menú de usuario':'Abrir menú de usuario')};document.querySelector('#logout').onclick=async()=>{await req('logout',{method:'POST'});token='';currentUser=null;login()};document.querySelector('#new').onclick=()=>openEditor();['family','status','stockfilter'].forEach(id=>document.getElementById(id).onchange=load);document.querySelector('#q').oninput=()=>{clearTimeout(timer);timer=setTimeout(load,220)};document.querySelectorAll('[data-close]').forEach(b=>b.onclick=()=>closeProductEditor());document.querySelector('[data-close-notes]').onclick=()=>document.querySelector('#notes-dialog').close();document.querySelector('#photo').onchange=e=>{const file=e.target.files[0];if(file){const img=document.querySelector('#preview');if(img.dataset.url)URL.revokeObjectURL(img.dataset.url);img.dataset.url=URL.createObjectURL(file);img.src=img.dataset.url}};document.querySelector('#notes').oninput=e=>{let v=e.target.value.split('\n');if(v.length>3)e.target.value=v.slice(0,3).join('\n')};document.querySelector('#product-form').onsubmit=save;document.querySelector('#rows').onclick=e=>{const b=e.target.closest('button[data-id]');if(!b)return;const p=products.find(x=>String(x.id)===b.dataset.id);if(!p)return;if(b.dataset.type==='edit')openEditor(p);else{document.querySelector('#note-content').textContent=p.notes;document.querySelector('#notes-dialog').showModal()}}}
async function load(){try{const d=await req('list',{params:{q:document.querySelector('#q').value,family:document.querySelector('#family').value,active:document.querySelector('#status').value,stock:document.querySelector('#stockfilter').value}});products=d.products;categories=d.categories;const f=document.querySelector('#family'),value=f.value;f.innerHTML='<option value="">Todas las familias</option>'+categories.map(c=>`<option value="${esc(c.slug)}">${esc(c.name)}</option>`).join('');f.value=value;document.querySelector('#total').textContent=d.totals.total;document.querySelector('#active').textContent=d.totals.active;document.querySelector('#inactive').textContent=d.totals.inactive;document.querySelector('#count').textContent=`${products.length} resultados`;document.querySelector('uco-product-table').renderProducts(products.slice(0,2000),esc,validImg);mountPager(document.querySelector('uco-product-table'),{search:false})}catch(e){document.querySelector('#count').textContent=e.message}}
async function closeProductEditor(){const d=document.querySelector('#editor'),f=document.querySelector('#product-form');if(d.dataset.dirty==='1'&&!await confirmChange(d,{title:'Cambios sin guardar',message:'Si cierras el editor perderás los cambios realizados.',confirm:'Descartar cambios',danger:true}))return;d.close()}
function validImg(v){return /^\.\/img\/(?:productos\/[a-f0-9]{32}\.webp|[\w-]+\.(?:webp|png|svg))$/.test(v||'')?'../'+v.slice(2):'../img/00.webp'}
function openEditor(p){const f=document.querySelector('#product-form');f.reset();document.querySelector('#modal-title').textContent=p?'Editar producto':'Nuevo producto';document.querySelector('#pid').value=p?.id||'';document.querySelector('#sku').value=p?.sku||'';document.querySelector('#name').value=p?.name||'';document.querySelector('#availability').value=String(p?.active??1);document.querySelector('#stock').value=p?.stock??0;document.querySelector('#notes').value=p?.notes||'';document.querySelector('#unit').value=p?.unit||'unidad';document.querySelector('#description').value=p?.description||'';document.querySelector('#category_id').innerHTML='<option value="">Seleccionar familia</option>'+categories.map(c=>`<option value="${c.id}">${esc(c.name)}</option>`).join('');if(p){const cat=categories.find(c=>c.slug===p.category);document.querySelector('#category_id').value=cat?.id||''}const img=document.querySelector('#preview');if(img.dataset.url){URL.revokeObjectURL(img.dataset.url);delete img.dataset.url}img.src=p?validImg(p.image):'../img/00.webp';document.querySelector('#save-error').textContent='';const editor=document.querySelector('#editor');editor.dataset.dirty='0';editor.showModal();const changed=()=>editor.dataset.dirty='1';f.oninput=changed;f.onchange=changed;editor.oncancel=e=>{if(editor.dataset.dirty==='1'){e.preventDefault();closeProductEditor()}}}
async function save(e){e.preventDefault();const f=e.currentTarget;const data=new FormData(f);const btn=document.querySelector('#save');btn.disabled=true;try{const other=products.find(x=>x.id!==+(data.get('id')||0)&&x.sku.toLocaleLowerCase('es')===String(data.get('sku')).trim().toLocaleLowerCase('es'));if(other)throw Error('Ya existe la referencia «'+other.sku+'» para «'+other.name+'». Edita el producto existente.');const old=products.find(x=>+x.id===+(data.get('id')||0));if(old)data.set('expected_updated_at',old.updated_at||'');if(old&&String(old.active)!==String(data.get('active')))await requireChange(document.querySelector('#editor'),{title:'Cambiar disponibilidad del producto',message:'El cambio afectará a la visibilidad comercial de «'+old.name+'».',previous:+old.active?'Activo':'Inactivo',next:data.get('active')==='1'?'Activo':'Inactivo',confirm:'Cambiar disponibilidad'});await services.products.save(data);document.querySelector('#editor').dataset.dirty='0';document.querySelector('#editor').close();await load()}catch(err){document.querySelector('#save-error').textContent=err.message}finally{btn.disabled=false}}
function validAvatar(v){return typeof v==='string'&&/^\.\/img\/usuarios\/[a-f0-9]{32}\.webp$/.test(v)?'../'+v.slice(2):''}
function setAvatar(img,path){const url=validAvatar(path)||(currentUser?.role==='admin'?'../img/uco-logo-avatar.svg':'');img.hidden=!url;if(url)img.src=url;else img.removeAttribute('src');img.classList.toggle('avatar-logo',!validAvatar(path)&&currentUser?.role==='admin')}
function showUsers(){const old=document.querySelector('#users-area');if(old){old.remove();return}const el=document.createElement('section');el.id='users-area';el.className='wrap';el.innerHTML='<uco-admin-users-area></uco-admin-users-area>'; document.querySelector('.admin-section-nav').before(el);document.querySelector('#close-users').onclick=()=>el.remove();document.querySelector('#own-password').onclick=()=>passwordDialog();document.querySelector('#new-user').onclick=()=>userDialog();document.querySelector('#users-body').onclick=e=>{const b=e.target.closest('button[data-user]');if(!b)return;const u=users.find(x=>x.id===+b.dataset.user);if(b.dataset.act==='edit')userDialog(u);if(b.dataset.act==='delete'&&u)confirmStandalone({title:'Eliminar usuario',message:'Se eliminará definitivamente la cuenta de '+u.username+'. Esta acción no se puede deshacer.',confirm:'Eliminar usuario',danger:true}).then(ok=>{if(ok)req('user_delete',{method:'POST',body:formOf({id:u.id})}).then(refreshUsers).catch(x=>alert(x.message))})};if(currentUser?.role==='admin')refreshUsers();else{document.querySelector('#new-user').remove();document.querySelector('#users-body').innerHTML='<p>Tu cuenta puede cambiar su contraseña. La gestión de usuarios requiere permisos de administrador principal.</p>'}}
function formOf(obj){const f=new FormData();Object.entries(obj).forEach(([k,v])=>f.set(k,String(v)));return f}
async function refreshUsers(){try{users=(await services.users.list()).users;if(!document.querySelector('#users-body')){await refreshUnifiedAccounts();return}document.querySelector('#users-body').replaceChildren(document.createElement('uco-admin-user-table'));document.querySelector('uco-admin-user-table').renderUsers(users,currentUser,esc,validAvatar);mountPager(document.querySelector('uco-admin-user-table'))}catch(e){if(document.querySelector('#users-body'))document.querySelector('#users-body').textContent=e.message;else alert(e.message)}}
function makeDialog(title,content,onSubmit){const d=document.createElement('dialog');const shell=document.createElement('uco-admin-dialog-content');d.append(shell);shell.renderDialog(title,content,esc);document.body.append(d);d.querySelectorAll('[data-cancel]').forEach(b=>b.onclick=()=>d.close());d.addEventListener('close',()=>d.remove());d.querySelector('form').onsubmit=async e=>{e.preventDefault();const btn=d.querySelector('[type=submit]');btn.disabled=true;try{await onSubmit(new FormData(e.currentTarget));d.dispatchEvent(new Event('uco-saved'));d.close()}catch(err){if(!err.cancelled)if(!err.cancelled)d.querySelector('#security-error').textContent=err.message}finally{btn.disabled=false}};d.showModal();warnUnsaved(d);return d}

const cancelled=()=>Object.assign(new Error(''),{cancelled:true});
const euro=v=>(+v/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
async function requireChange(d,config){if(!await confirmChange(d,config))throw cancelled()}
function userDialog(u){const d=makeDialog(u?'Editar usuario':'Nuevo usuario',`<input type="hidden" name="id" value="${u?.id||''}"><div class="avatar-upload"><div class="avatar-frame"><img id="user-avatar-preview" src="${validAvatar(u?.avatar)||"../img/uco-logo-avatar.svg"}" alt="Vista previa del perfil"></div><div class="field"><label for="user-avatar-file">Imagen de perfil (opcional)</label><input id="user-avatar-file" type="file" name="avatar" accept="image/jpeg,image/png,image/webp"><small class="tip">JPG, PNG o WebP · máximo 5 MB. Se adapta sin deformarse.</small></div></div><div class="formgrid"><div class="field"><label>Nombre de usuario</label><input name="username" value="${esc(u?.username||'')}" minlength="3" maxlength="40" required autocomplete="off"></div><div class="field"><label>Nombre completo</label><input name="display_name" value="${esc(u?.display_name||'')}" required maxlength="100"></div><div class="field wide"><label>Correo electrónico (opcional)</label><input type="email" name="email" value="${esc(u?.email||'')}"></div><div class="field"><label>Permisos</label><select name="role"><option value="admin" ${u?.role==='admin'?'selected':''}>Administrador principal</option><option value="manager" ${u?.role==='manager'||!u?'selected':''}>Gestor de catálogo</option></select></div><div class="field"><label>Estado</label><select name="active"><option value="1" ${u?.active!==0?'selected':''}>Activo</option><option value="0" ${u?.active===0?'selected':''}>Inactivo</option></select></div><div class="field wide"><label>${u?'Nueva contraseña (vacío = conservar)':'Contraseña'}</label><input type="password" name="password" autocomplete="new-password" minlength="12" ${u?'':'required'}><small class="tip">Mínimo 12 caracteres.</small></div></div>`,async data=>{if(u&&(String(u.role)!==String(data.get('role'))||String(u.active)!==String(data.get('active'))))await requireChange(d,{title:'Modificar permisos de usuario',message:'Esta acción puede cambiar el acceso de '+u.username+'.',previous:(u.role==='admin'?'Administrador':'Gestor')+' · '+(+u.active?'Activo':'Inactivo'),next:(data.get('role')==='admin'?'Administrador':'Gestor')+' · '+(data.get('active')==='1'?'Activo':'Inactivo'),confirm:'Aplicar cambios',danger:true});const result=await req('user_save',{method:'POST',body:data});if(result.user){currentUser=result.user;document.querySelector('#logged-user').textContent=currentUser.display_name||currentUser.username;setAvatar(document.querySelector('#logged-avatar'),currentUser.avatar)}await refreshUsers()});const file=d.querySelector('#user-avatar-file');file.onchange=()=>{const f=file.files[0];if(!f)return;const img=d.querySelector('#user-avatar-preview');if(img.dataset.preview)URL.revokeObjectURL(img.dataset.preview);img.dataset.preview=URL.createObjectURL(f);img.src=img.dataset.preview};d.addEventListener('close',()=>{const img=d.querySelector('#user-avatar-preview');if(img?.dataset.preview)URL.revokeObjectURL(img.dataset.preview)});d.querySelector('[name=username]').focus()}
function showPasswordUpdated(){
 const d=document.createElement('dialog');d.className='uco-password-success';
 const title=document.createElement('h2');title.textContent='Contraseña actualizada';
 const heading=document.createElement('div');heading.className='uco-password-success-head';
 const icon=document.createElement('span');icon.className='uco-password-success-icon';icon.textContent='✓';icon.setAttribute('aria-hidden','true');
 heading.append(icon,title);
 const body=document.createElement('div');body.className='uco-password-success-body';
 const message=document.createElement('p');message.textContent='Tu contraseña se ha cambiado correctamente.';
 const actions=document.createElement('div');actions.className='uco-password-success-actions';
 const close=document.createElement('button');close.type='button';close.textContent='Aceptar';
 close.addEventListener('click',()=>d.close());actions.append(close);body.append(message,actions);d.append(heading,body);
 d.addEventListener('close',()=>d.remove(),{once:true});document.body.append(d);d.showModal();close.focus();
}
function passwordDialog(){const dialog=makeDialog('Cambiar mi contraseña',`<div class="field"><label>Contraseña actual</label><input type="password" name="current_password" required autocomplete="current-password"></div><div class="field"><label>Nueva contraseña</label><input type="password" name="new_password" minlength="12" required autocomplete="new-password"></div><div class="field"><label>Confirmar nueva contraseña</label><input type="password" name="confirm_password" minlength="12" required autocomplete="new-password"></div>`,async data=>{if(data.get('new_password')!==data.get('confirm_password'))throw Error('Las contraseñas nuevas no coinciden.');await req('change_password',{method:'POST',body:data});queueMicrotask(showPasswordUpdated)});dialog.classList.add('password-dialog')}
(async()=>{try{const d=await req('session');if(d.authenticated){token=d.csrf;currentUser=d.user;panel();await load()}else login()}catch(e){app.textContent='No se pudo conectar con PHP. Comprueba el servidor.'}})();

// v1.9 · Gestión comercial
let commercial={customers:[],groups:[],products:[],prices:[],base:[],discounts:[],promotions:[]};let tariffsUI=null;
async function refreshCommercial(){commercial=await services.commercial.list();renderCommercial();tariffsUI?.render()}
function commercialName(kind,id){return kind==='group'?commercial.groups.find(x=>+x.id===+id)?.name:commercial.customers.find(x=>+x.id===+id)?.company}
let commercialTab='commercial-groups';
function selectCommercialTab(){const el=document.querySelector('#commercial-area');if(!el)return;const unified=el.querySelector('#uco-unified');if(unified)unified.hidden=commercialTab!=='commercial-customers';const nav=el.querySelector('.commercial-subnav');if(!nav)return;nav.querySelectorAll('[data-jump]').forEach(b=>b.setAttribute('aria-selected',String(b.dataset.jump===commercialTab)));const target=el.querySelector('#commercial-content');if(!target)return;const ids=['commercial-customers','commercial-groups','commercial-prices'];ids.forEach((id,i)=>{const heading=target.querySelector('#'+id);if(!heading)return;let item=heading;const end=i<ids.length-1?target.querySelector('#'+ids[i+1]):null;while(item&&item!==end){item.hidden=id!==commercialTab;item=item.nextElementSibling}})}
function showCommercial(openTariffs=false){document.querySelector('#users-area')?.remove();document.querySelector('#commercial-area')?.remove();const el=document.createElement('section');el.className='wrap';el.id='commercial-area';el.innerHTML='<uco-admin-commercial-area></uco-admin-commercial-area>'; document.querySelector('.admin-section-nav').after(el);commercialTab='commercial-customers';el.querySelector('.commercial-subnav').onclick=e=>{const b=e.target.closest('[data-jump]');if(!b)return;commercialTab=b.dataset.jump;selectCommercialTab()};tariffsUI=initTariffs({req,makeDialog,esc,refreshCommercial,getCommercial:()=>commercial});if(!openTariffs){const heading=el.querySelector('.commercial-heading');if(heading){heading.querySelector('h2')?.replaceChildren(document.createTextNode('Usuarios / Clientes'));}}if(openTariffs){el.querySelector('.commercial-heading').hidden=true;el.querySelector('.commercial-subnav').hidden=true;tariffsUI.show();}el.querySelector('#commercial-content').onclick=async e=>{const b=e.target.closest('[data-commercial]');if(!b)return;const id=+b.dataset.id,act=b.dataset.commercial;if(act==='customer-edit')editCustomer(commercial.customers.find(x=>+x.id===id));if(act==='group-edit')editGroup(commercial.groups.find(x=>+x.id===id));if(act==='price-edit')editPrice(commercial.prices[id]);if(act.endsWith('-delete')){if(!await confirmStandalone({title:'Eliminar registro comercial',message:'Se eliminará el registro seleccionado. La operación puede afectar a tarifas o asignaciones relacionadas.',confirm:'Eliminar registro',danger:true}))return;const obj=act==='customer-delete'?{id}:act==='group-delete'?{id}:{kind:commercial.prices[id].kind,owner_id:commercial.prices[id].owner_id,product_id:commercial.prices[id].product_id};try{await req(act.replace('-','_'),{method:'POST',body:formOf(obj)});await refreshCommercial()}catch(err){alert(err.message)}}};refreshCommercial().catch(err=>el.querySelector('#commercial-content').textContent=err.message)}
function renderCommercial(){const target=document.querySelector('#commercial-content');if(!target)return;target.innerHTML=adminViews.commercialTables(commercial,esc,commercialName);if(document.querySelector('#commercial-area .commercial-heading')&&!document.querySelector('#commercial-area .commercial-heading').hidden)mountUnifiedAccounts(target);target.querySelectorAll(':scope > .tablewrap').forEach(w=>mountPager(w));selectCommercialTab();target.querySelector('#add-customer').onclick=()=>editCustomer();target.querySelector('#add-group').onclick=()=>editGroup();target.querySelector('#add-price').onclick=()=>editPrice();}
function editCustomer(c){const d=makeDialog(c?'Editar cliente':'Nuevo cliente',`<input type="hidden" name="id" value="${c?.id||0}"><div class="formgrid"><div class="field wide"><label>Empresa / razón social</label><input name="company" required maxlength="160" value="${esc(c?.company||'')}"></div><div class="field"><label>CIF / NIF</label><input name="tax_id" maxlength="40" value="${esc(c?.tax_id||'')}"></div><div class="field"><label>Contacto</label><input name="contact" maxlength="100" value="${esc(c?.contact||'')}"></div><div class="field wide"><label>Correo de acceso</label><input type="email" name="email" required maxlength="190" value="${esc(c?.email||'')}"></div><div class="field"><label>Grupo de tarifa</label><select name="group_id"><option value="">Sin grupo</option>${commercial.groups.map(g=>`<option value="${g.id}" ${+c?.group_id===+g.id?'selected':''}>${esc(g.name)}</option>`).join('')}</select></div><div class="field"><label>Estado</label><select name="active"><option value="1" ${c?.active!==0?'selected':''}>Activo</option><option value="0" ${c?.active===0?'selected':''}>Inactivo</option></select></div><div class="field wide"><label>${c?'Nueva contraseña (vacío = conservar)':'Contraseña inicial'}</label><input name="password" type="password" autocomplete="new-password" minlength="12" ${c?'':'required'}><small class="tip">Mínimo 12 caracteres. Comunica las credenciales al cliente por un canal seguro.</small></div></div>`,async body=>{const tax=String(body.get('tax_id')||'').trim().toLocaleLowerCase('es');const duplicate=commercial.customers.find(x=>x.id!==c?.id&&tax&&String(x.tax_id||'').trim().toLocaleLowerCase('es')===tax);if(duplicate)throw Error('Ya existe un cliente con este CIF/NIF: '+duplicate.company+'. Abre su ficha para editarlo.');if(c&&String(c.active)!==String(body.get('active')))await requireChange(d,{title:'Cambiar estado del cliente',message:'Confirma el cambio de acceso de '+c.company+'.',previous:+c.active?'Activo':'Inactivo',next:body.get('active')==='1'?'Activo':'Inactivo',confirm:'Cambiar estado'});await req('customer_save',{method:'POST',body});await refreshCommercial()})}
function editGroup(g){const d=makeDialog(g?'Editar grupo':'Nuevo grupo',`<input type="hidden" name="id" value="${g?.id||0}"><div class="field"><label>Nombre de la tarifa / grupo</label><input name="name" required maxlength="90" value="${esc(g?.name||'')}"></div><div class="field"><label>Estado</label><select name="active"><option value="1" ${g?.active!==0?'selected':''}>Activo</option><option value="0" ${g?.active===0?'selected':''}>Inactivo</option></select></div>`,async body=>{const duplicate=commercial.groups.find(x=>x.id!==g?.id&&x.name.trim().toLocaleLowerCase('es')===String(body.get('name')).trim().toLocaleLowerCase('es'));if(duplicate)throw Error('Ya existe el grupo «'+duplicate.name+'». Edita ese grupo en lugar de duplicarlo.');if(g&&String(g.active)!==String(body.get('active')))await requireChange(d,{title:'Cambiar estado del grupo',message:'Confirma el cambio para '+g.name+'.',previous:+g.active?'Activo':'Inactivo',next:body.get('active')==='1'?'Activo':'Inactivo',confirm:'Cambiar estado'});await req('group_save',{method:'POST',body});await refreshCommercial()})}
async function confirmPriceOverwrite(dialog,details,body){
 const kind=body.get('kind'),owner=body.get('owner_id'),product=body.get('product_id');
 const recipient=(kind==='group'?commercial.groups:commercial.customers).find(x=>String(x.id)===String(owner));
 const productInfo=commercial.products.find(x=>String(x.id)===String(product));
 return confirmChange(dialog,{title:'Ya existe una tarifa para este producto',message:'Vas a modificar una tarifa de precios que ya existe para este '+(kind==='group'?'grupo profesional':'cliente')+': '+(recipient?.[kind==='group'?'name':'company']||'Destinatario')+' · '+(productInfo?.name||'Producto')+'.',previous:euro(details.old_price_cents),next:euro(details.new_price_cents),confirm:'Sobrescribir tarifa'});
}
function editPrice(r){
 const kind=r?.kind||'group',owner=r?.owner_id||'',product=r?.product_id||'';
 const choices=(k,selected)=>'<option value="">Selecciona…</option>'+(k==='group'?commercial.groups:commercial.customers).map(x=>`<option value="${x.id}" ${String(x.id)===String(selected)?'selected':''}>${esc(k==='group'?x.name:x.company)}</option>`).join('');
 const d=makeDialog(r?'Editar oferta personalizada':'Asignar precio',`<div class="field"><label>Tipo de tarifa</label><select name="kind"><option value="group" ${kind==='group'?'selected':''}>Grupo de clientes</option><option value="customer" ${kind==='customer'?'selected':''}>Cliente específico</option></select></div><div class="field"><label>Destinatario</label><select name="owner_id" required></select></div><div class="field"><label>Producto</label><select name="product_id" required>${commercial.products.map(x=>`<option value="${x.id}" ${+x.id===+product?'selected':''}>${esc(x.sku+' · '+x.name)}</option>`).join('')}</select></div><div class="field"><label>Precio en euros</label><input name="price" inputmode="decimal" required pattern="[0-9]+([.,][0-9]{1,2})?" value="${r?(r.price_cents/100).toFixed(2):''}" placeholder="0,00"></div>`,async body=>{
  if(r){body.set('old_kind',kind);body.set('old_owner_id',owner);body.set('old_product_id',product);body.set('original_price_cents',r.price_cents)}
  const action=r?'price_update':'price_save';
  for(let attempt=0;attempt<3;attempt++){
   try{await req(action,{method:'POST',body});await refreshCommercial();return}
   catch(err){
    if(err.status!==409||!err.details?.price_conflict)throw err;
    const confirmed=await confirmPriceOverwrite(d,err.details,body);
    if(!confirmed){const cancelled=new Error('');cancelled.cancelled=true;throw cancelled}
    body.set('confirm_overwrite','1');body.set('expected_price_cents',err.details.old_price_cents);
   }
  }
  throw Error('El precio ha cambiado durante la operación. Vuelve a guardar para revisarlo.');
 });
 const k=d.querySelector('[name=kind]'),o=d.querySelector('[name=owner_id]');
 const update=()=>{const selected=k.value===kind?owner:'';o.innerHTML=choices(k.value,selected)};
 k.onchange=update;update();
}


/* UCO · Usuarios / Clientes: vista consolidada. Roles y credenciales permanecen aislados. */
let unifiedAccounts=[],unifiedOrders={},unifiedLinks=[];
const safeImage=x=>/^\.\.\/img\/[a-zA-Z0-9_/-]+\.(?:webp|png|jpg|svg)$/.test(x||'')?x:'../img/00.webp';
function mountUnifiedAccounts(target){
 const section=document.createElement('section');section.className='uco-unified panel';section.id='uco-unified';
 section.innerHTML=`<div class="uco-unified-head"><div><span class="eyebrow">CUENTAS · ADMINISTRACIÓN</span><h2>Usuarios / Clientes</h2><p>Accesos internos, clientes y consulta protegida de pedidos.</p></div><div class="uco-unified-actions"><button class="primary" type="button" data-unified-new="user">+ Usuario</button><button class="primary" type="button" data-unified-new="customer">+ Cliente</button></div></div><div class="uco-unified-filters"><input type="search" aria-label="Buscar cuentas" placeholder="Buscar nombre, empresa o correo…" id="uco-account-search"><select aria-label="Filtrar tipo" id="uco-account-type"><option value="">Todos los tipos</option><option value="admin">Administradores</option><option value="staff">Empleados</option><option value="customer">Clientes</option></select><select aria-label="Filtrar estado" id="uco-account-status"><option value="">Todos los estados</option><option value="1">Activos</option><option value="0">Inactivos</option></select></div><div class="uco-unified-list" id="uco-unified-list" aria-live="polite"><p class="tip">Cargando cuentas…</p></div><p class="tip">El historial es de solo lectura. La edición de productos o stock no permite modificar pedidos ni cobros.</p>`;
 target.prepend(section);
 section.querySelectorAll('input,select').forEach(el=>el.addEventListener('input',renderUnifiedAccounts));
 section.addEventListener('click',async e=>{
  const newButton=e.target.closest('[data-unified-new]');if(newButton){newButton.dataset.unifiedNew==='user'?userDialog():editCustomer();return}
  const b=e.target.closest('button[data-unified-id]');if(!b)return;
  const kind=b.dataset.unifiedKind,id=Number(b.dataset.unifiedId);
  if(b.dataset.unifiedAction==='edit'){if(kind==='customer')editCustomer(commercial.customers.find(x=>+x.id===id));else userDialog(users.find(x=>+x.id===id));return}
  if(b.dataset.unifiedAction==='orders'&&kind==='customer'){await openUnifiedHistory(id);return}
  if(b.dataset.unifiedAction==='profile'){openUnifiedProfile(kind,id);return}
  if(b.dataset.unifiedAction==='link'&&kind==='user'){await openIdentityLinkDialog(id);return}
 });
 refreshUnifiedAccounts();
}
async function refreshUnifiedAccounts(){
 const el=document.querySelector('#uco-unified');if(!el)return;
 try{
  const [u,h,l]=await Promise.all([services.users.list(),req('account_history_index'),req('identity_links')]);
  users=u.users;unifiedOrders=h.counts||{};unifiedLinks=l.links||[];
  unifiedAccounts=[
   ...users.map(x=>({kind:x.role==='admin'?'admin':'staff',source:'user',id:x.id,name:x.display_name||x.username,email:x.email||'',active:+x.active,group:'',avatar:validAvatar(x.avatar)?x.avatar:'',role:x.role==='admin'?'Administrador':'Empleado'})),
   ...commercial.customers.map(x=>({kind:'customer',source:'customer',id:x.id,name:x.company,email:x.email||'',active:+x.active,group:commercial.groups.find(g=>+g.id===+x.group_id)?.name||'Sin grupo',avatar:'',role:'Cliente'}))
  ];
  renderUnifiedAccounts();
 }catch(e){const list=el.querySelector('#uco-unified-list');if(list)list.textContent='No se pudieron cargar las cuentas: '+e.message}
}
function renderUnifiedAccounts(){
 const el=document.querySelector('#uco-unified');if(!el)return;
 const search=el.querySelector('#uco-account-search').value.trim().toLocaleLowerCase('es');
 const type=el.querySelector('#uco-account-type').value,status=el.querySelector('#uco-account-status').value;
 const list=el.querySelector('#uco-unified-list');list.replaceChildren();
 const data=unifiedAccounts.filter(x=>(!type||x.kind===type)&&(!status||String(x.active)===status)&&(!search||(x.name+' '+x.email+' '+x.role).toLocaleLowerCase('es').includes(search)));
 if(!data.length){list.textContent='No hay cuentas con esos filtros.';return}
 for(const x of data){
  const row=document.createElement('article');row.className='uco-account-row';
  const photo=document.createElement('img');photo.className='uco-account-photo';photo.alt='';photo.loading='lazy';photo.src=x.avatar&&validAvatar(x.avatar)?validAvatar(x.avatar):'../img/00.webp';photo.onerror=()=>{photo.onerror=null;photo.src='../img/00.webp'};
  const details=document.createElement('div');details.className='uco-account-details';
  const name=document.createElement('strong');name.textContent=x.name;
  const email=document.createElement('small');email.textContent=x.email;
  details.append(name,email);
  const linkedCustomer=x.source==='user'?unifiedLinks.find(l=>+l.staff_id===+x.id):null;
  const linkedStaff=x.source==='customer'?unifiedLinks.find(l=>+l.customer_id===+x.id):null;
  if(linkedCustomer||linkedStaff){const label=document.createElement('small');label.className='uco-profile-link-label';label.textContent=linkedCustomer?'Perfil comercial vinculado':'Cuenta interna vinculada';details.append(label)}
  const role=document.createElement('span');role.className='uco-account-meta';role.textContent=x.role;
  const group=document.createElement('span');group.className='uco-account-meta';group.textContent=x.group||'—';
  const active=document.createElement('span');active.className='uco-account-status '+(x.active?'is-active':'');active.textContent=x.active?'Activo':'Inactivo';
  const count=document.createElement('span');count.className='uco-account-count';count.textContent=x.source==='customer'?(unifiedOrders[x.id]??0)+' pedidos':'—';
  const actions=document.createElement('div');actions.className='uco-account-actions';
  const edit=document.createElement('button');edit.type='button';edit.textContent='Editar';edit.dataset.unifiedAction='edit';
  actions.append(edit);
  const profile=document.createElement('button');profile.type='button';profile.textContent='Perfil';profile.dataset.unifiedAction='profile';actions.append(profile);
  if(x.source==='user'){const link=document.createElement('button');link.type='button';const linked=unifiedLinks.find(l=>+l.staff_id===+x.id);link.textContent=linked?'Vínculo':'Vincular';link.dataset.unifiedAction='link';if(linked)link.classList.add('uco-link-active');actions.append(link)}
  if(x.source==='customer'){const orders=document.createElement('button');orders.type='button';orders.textContent='Historial';orders.dataset.unifiedAction='orders';actions.append(orders)}
  for(const b of actions.querySelectorAll('button')){b.dataset.unifiedKind=x.source;b.dataset.unifiedId=x.id}
  row.append(photo,details,role,group,active,count,actions);list.append(row);
 }
}
function openUnifiedProfile(kind,id){
 const account=unifiedAccounts.find(x=>x.source===kind&&+x.id===+id);if(!account)return;
 const staff=kind==='user'?users.find(x=>+x.id===+id):null;
 const customer=kind==='customer'?commercial.customers.find(x=>+x.id===+id):null;
 const link=kind==='user'?unifiedLinks.find(x=>+x.staff_id===+id):unifiedLinks.find(x=>+x.customer_id===+id);
 const related=link?(kind==='user'?commercial.customers.find(x=>+x.id===+link.customer_id):users.find(x=>+x.id===+link.staff_id)):null;
 const dialog=document.createElement('dialog');dialog.className='uco-history-dialog uco-profile-dialog';
 dialog.innerHTML='<div class="modal"><header><h2>Perfil de cuenta</h2><button type="button" class="close" aria-label="Cerrar">×</button></header><div class="uco-history-content"></div></div>';
 const body=dialog.querySelector('.uco-history-content');
 const hero=document.createElement('div');hero.className='uco-profile-hero';
 const photo=document.createElement('img');photo.className='uco-profile-avatar';photo.alt='Imagen de perfil';photo.src=account.avatar&&validAvatar(account.avatar)?validAvatar(account.avatar):'../img/00.webp';photo.onerror=()=>{photo.onerror=null;photo.src='../img/00.webp'};
 const identity=document.createElement('div');identity.className='uco-profile-identity';
 const eyebrow=document.createElement('span');eyebrow.className='uco-profile-eyebrow';eyebrow.textContent=kind==='customer'?'PERFIL COMERCIAL':'CUENTA INTERNA';
 const heading=document.createElement('h3');heading.textContent=account.name;
 const summary=document.createElement('p');summary.textContent=account.email||'Correo no informado';
 const tags=document.createElement('div');tags.className='uco-profile-tags';
 const roleTag=document.createElement('span');roleTag.textContent=account.role;
 const statusTag=document.createElement('span');statusTag.className=account.active?'uco-profile-tag-active':'uco-profile-tag-inactive';statusTag.textContent=account.active?'Activo':'Inactivo';
 tags.append(roleTag,statusTag);identity.append(eyebrow,heading,summary,tags);hero.append(photo,identity);body.append(hero);
 const rows=[['Tipo',account.role],['Estado',account.active?'Activo':'Inactivo'],['Correo',account.email||'No informado']];
 if(staff){rows.push(['Usuario de acceso',staff.username||'—'],['Permiso interno',staff.role==='admin'?'Administrador principal':'Gestor de catálogo']);}
 if(customer){rows.push(['Grupo comercial',account.group],['Pedidos registrados',String(unifiedOrders[id]??0)]);}
 rows.push(['Vinculación',related?(kind==='user'?(related.company||'Cliente vinculado'):(related.display_name||related.username||'Usuario vinculado')):'Sin vinculación adicional']);
 const dl=document.createElement('dl');dl.className='uco-profile-details';
 for(const [label,value] of rows){const dt=document.createElement('dt');dt.textContent=label;const dd=document.createElement('dd');dd.textContent=value;dl.append(dt,dd)}body.append(dl);
 const note=document.createElement('p');note.className='tip';note.textContent='Consulta de solo lectura. Los accesos y permisos internos y comerciales permanecen independientes.';body.append(note);
 const actions=document.createElement('div');actions.className='uco-profile-actions';
 const edit=document.createElement('button');edit.type='button';edit.textContent='Editar datos';edit.addEventListener('click',()=>{dialog.close();kind==='customer'?editCustomer(customer):userDialog(staff)});actions.append(edit);
 if(kind==='user'){const manage=document.createElement('button');manage.type='button';manage.textContent='Gestionar vínculo';manage.addEventListener('click',()=>{dialog.close();openIdentityLinkDialog(id)});actions.append(manage)}
 if(kind==='customer'){const history=document.createElement('button');history.type='button';history.textContent='Ver historial';history.addEventListener('click',()=>{dialog.close();openUnifiedHistory(id)});actions.append(history)}
 body.append(actions);document.body.append(dialog);
 dialog.querySelector('.close').onclick=()=>dialog.close();dialog.addEventListener('close',()=>dialog.remove(),{once:true});dialog.showModal();
}
async function openIdentityLinkDialog(staffId){
 const staff=users.find(x=>+x.id===+staffId);if(!staff)return;
 const linked=unifiedLinks.find(x=>+x.staff_id===+staffId);
 const d=document.createElement('dialog');d.className='uco-history-dialog uco-link-dialog';
 d.innerHTML='<div class="modal"><header><h2>Vinculación comercial</h2><button type="button" class="close" aria-label="Cerrar">×</button></header><div class="uco-history-content"></div></div>';
 const body=d.querySelector('.uco-history-content');
 const p=document.createElement('p');p.textContent='Usuario interno: '+(staff.display_name||staff.username)+'. Los accesos y permisos continuarán siendo independientes.';p.className='uco-link-intro';body.append(p);
 const form=document.createElement('form');form.method='dialog';form.className='uco-link-form';
 if(linked){
  const c=commercial.customers.find(x=>+x.id===+linked.customer_id);
  const info=document.createElement('p');info.textContent='Cliente vinculado: '+(c?.company||('#'+linked.customer_id));info.className='uco-link-current';form.append(info);
  const unlink=document.createElement('button');unlink.type='button';unlink.className='uco-link-unlink';unlink.textContent='Desvincular cliente';
  unlink.addEventListener('click',async()=>{if(!await confirmStandalone({title:'Desvincular cuenta',message:'Se quitará únicamente el vínculo. Las dos cuentas, sus contraseñas y pedidos permanecerán intactos.',confirm:'Desvincular',danger:true}))return;try{await req('identity_unlink',{method:'POST',body:formOf({staff_id:staffId,customer_id:linked.customer_id})});d.close();await refreshUnifiedAccounts()}catch(e){alert(e.message)}});form.append(unlink);
 }else{
  const label=document.createElement('label');label.className='uco-link-field';label.textContent='Cliente comercial';
  const select=document.createElement('select');select.required=true;select.name='customer_id';
  const first=document.createElement('option');first.value='';first.textContent='Selecciona un cliente';select.append(first);
  for(const c of commercial.customers){const opt=document.createElement('option');opt.value=c.id;opt.textContent=c.company+' · '+(c.email||'sin correo');select.append(opt)}label.append(select);form.append(label);
  const passLabel=document.createElement('label');passLabel.className='uco-link-field';passLabel.textContent='Contraseña actual de la cuenta comercial (verificación obligatoria)';
  const pass=document.createElement('input');pass.type='password';pass.name='customer_password';pass.autocomplete='off';pass.required=true;passLabel.append(pass);form.append(passLabel);
  const save=document.createElement('button');save.type='submit';save.className='primary uco-link-submit';save.textContent='Verificar y vincular';form.append(save);
  form.addEventListener('submit',async e=>{e.preventDefault();save.disabled=true;try{await req('identity_link',{method:'POST',body:formOf({staff_id:staffId,customer_id:select.value,customer_password:pass.value})});pass.value='';d.close();await refreshUnifiedAccounts()}catch(err){pass.value='';alert(err.message)}finally{save.disabled=false}});
 }
 body.append(form);document.body.append(d);d.querySelector('.close').onclick=()=>d.close();d.addEventListener('close',()=>d.remove(),{once:true});d.showModal();
}
async function openUnifiedHistory(id){
 const c=commercial.customers.find(x=>+x.id===id);if(!c)return;
 const dialog=document.createElement('dialog');dialog.className='uco-history-dialog';
 dialog.innerHTML='<div class="modal"><header><h2>Historial de pedidos</h2><button type="button" class="close" aria-label="Cerrar">×</button></header><div class="uco-history-content"><p>Cargando historial…</p></div></div>';
 document.body.append(dialog);dialog.querySelector('.close').onclick=()=>dialog.close();dialog.addEventListener('close',()=>dialog.remove());dialog.showModal();
 const content=dialog.querySelector('.uco-history-content');
 try{
  const d=await req('account_history',{params:{customer_id:id}});content.replaceChildren();
  const intro=document.createElement('p');intro.textContent=c.company+' · '+d.orders.length+' pedidos registrados';content.append(intro);
  if(!d.orders.length){const msg=document.createElement('p');msg.textContent='Este cliente todavía no tiene pedidos.';content.append(msg);return}
  for(const order of d.orders){
   const item=document.createElement('details');item.className='uco-history-order';
   const summary=document.createElement('summary');summary.textContent='#'+order.id+' · '+order.created_at+' · '+order.status+' · '+(order.subtotal_cents/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
   item.append(summary);
   const payment=document.createElement('p');payment.textContent='Pago: '+(order.payment_state||order.payment_status||'No iniciado')+(order.payment_reference?' · Ref. '+order.payment_reference:'');item.append(payment);
   const lines=document.createElement('ul');
   for(const l of order.lines){const li=document.createElement('li');li.textContent=l.name+' · '+l.quantity+' ('+l.unit+') · '+(l.line_cents/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});lines.append(li)}
   item.append(lines);
   if(order.payment_state==='approved'&&order.payment_status==='simulated_approved'){
    const pdfButton=document.createElement('button');pdfButton.type='button';pdfButton.className='uco-history-pdf-button';
    pdfButton.textContent='Ver en PDF';pdfButton.setAttribute('aria-label','Ver justificante PDF del pedido '+order.id);
    pdfButton.addEventListener('click',async()=>{
     if(pdfButton.disabled)return;pdfButton.disabled=true;
     try{const {openUcoReceipt}=await import('../clientes/receipt.js');await openUcoReceipt(order.id,'admin')}
     catch(error){alert('No se pudo abrir el justificante: '+error.message)}
     finally{pdfButton.disabled=false}
    });
    item.append(pdfButton);
   }
   content.append(item);
  }
 }catch(e){content.textContent='No se pudo consultar el historial: '+e.message}
}
