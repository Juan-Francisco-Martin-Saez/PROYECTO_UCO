function ucoDisplayProductName(value){
  const original=String(value||'').trim();
  let s=original.normalize('NFC')
    .replace(/[‐‑‒–—]/g,'-').replace(/\s*\/\s*/g,' / ')
    .replace(/\bCONGE\.(?=\s|$)|\bCONGE\b/gi,'Congelado')
    .replace(/\bCONG\.(?=\s|$)/gi,'Congelado')
    .replace(/\bULTRACONG\.(?=\s|$)/gi,'Ultracongelado')
    .replace(/\bREF\.(?=\s|$)/gi,'Referencia')
    .replace(/\s+-\s+/g,' ').replace(/\s*\.\s*(?=\S)/g,' ')
    .replace(/\s+([,;:.])/g,'$1').replace(/([,;:.]){2,}/g,'$1')
    .replace(/\s+/g,' ').replace(/[\s.,;:]+$/g,'').trim();
  // El peso, el formato y la marca permanecen en los datos originales.
  s=s.replace(/\s*\([^)]*\b(?:kg|gr|g|ml|litros?|uds?|unidades?)\b[^)]*\)/gi,' ')
    .replace(/\s+\d+(?:[.,]\d+)?\s*(?:kg|kgs|gr|g|ml|litros?|uds?)\s*$/gi,'')
    .replace(/\s+/g,' ').trim();
  if(!s)return original;
  s=s.toLocaleLowerCase('es-ES').replace(/(^|[\s(/-])([a-záéíóúüñ])/g,(m,p,c)=>p+c.toLocaleUpperCase('es-ES'))
    .replace(/\b(De|Del|La|Las|El|Los|En|Con|Sin|Para|Por|Y|O|Al|A)\b/g,(word,offset)=>offset===0?word:word.toLocaleLowerCase('es-ES'))
    .replace(/\b(findus|uco|kg|ml|iv)\b/gi,(word)=>({findus:'Findus',uco:'UCO',kg:'kg',ml:'ml',iv:'IV'})[word.toLowerCase()]);
  return s;
}

class UcoCatalog extends HTMLElement {
  connectedCallback(){this.category='';this.query='';this.priorityTerms=[];try{const t=JSON.parse(sessionStorage.getItem('uco_catalog_priority_v1')||'[]');if(Array.isArray(t))this.priorityTerms=t.slice(0,100)}catch{};this._priorityUpdate=e=>{this.priorityTerms=Array.isArray(e.detail?.terms)?e.detail.terms.slice(0,100):[];this.query='';this.category='';this.page=1;const input=this.querySelector('.catalog-search input');if(input)input.value='';this.load()};document.addEventListener('uco:assistant-catalog-priority',this._priorityUpdate);this.page=1;this.limit=8;this.data=null;this.renderShell();this.load();this._familyClick=e=>{const a=e.target.closest('.family-card[data-category]');if(!a)return;this.category=a.dataset.category;this.query='';this.page=1;const input=this.querySelector('.catalog-search input');if(input)input.value='';this.load()};document.addEventListener('click',this._familyClick)}
  disconnectedCallback(){document.removeEventListener('click',this._familyClick);document.removeEventListener('uco:assistant-catalog-priority',this._priorityUpdate);this.controller?.abort()}
  renderShell(){this.innerHTML=`<div class="catalog-tools"><label class="catalog-search"><span>Buscar productos</span><input type="search" maxlength="100" placeholder="Buscar por nombre…" aria-label="Buscar productos"></label><label class="catalog-filter"><span>Familia</span><select aria-label="Filtrar por familia"><option value="">Todas las familias</option></select></label></div><p class="catalog-status" role="status" aria-live="polite">Cargando catálogo…</p><div class="product-grid" data-products></div><dialog class="product-detail" aria-label="Ficha comercial del producto"><div class="product-detail-body"><button type="button" class="product-detail-close" aria-label="Cerrar ficha">×</button><div data-detail></div></div></dialog><div class="catalog-pagination"><button type="button" data-prev disabled>Anterior</button><span data-page></span><button type="button" data-next disabled>Siguiente</button></div>`;
    const dialog=this.querySelector('.product-detail');this.querySelector('.product-detail-close').onclick=()=>dialog.close();dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close()});const search=this.querySelector('input'),filter=this.querySelector('select');let timer;search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{this.query=search.value.trim();this.page=1;this.load()},250)});filter.addEventListener('change',()=>{this.category=filter.value;this.page=1;this.load()});this.querySelector('[data-prev]').addEventListener('click',()=>{if(this.page>1){this.page--;this.load()}});this.querySelector('[data-next]').addEventListener('click',()=>{this.page++;this.load()})}
  showDetail(p,categories){
    const dialog=this.querySelector('.product-detail'),host=this.querySelector('[data-detail]');
    host.replaceChildren();
    const img=document.createElement('img');img.className='detail-image';
    img.src=/^\.\/img\/(?:productos\/[a-f0-9]{32}\.webp|[\w-]+\.(webp|svg|png))$/.test(p.image||'')?p.image:'./img/00.webp';img.alt=p.name;
    const info=document.createElement('div');info.className='detail-info';
    const category=document.createElement('span');category.className='detail-category';
    category.textContent=(categories.find(c=>c.slug===p.category)||{}).name||p.category||'';
    const h=document.createElement('h2');h.textContent=ucoDisplayProductName(p.name);h.title=p.name;
    const desc=document.createElement('p');desc.className='detail-description';
    desc.textContent=p.description||'Consulta con nuestro equipo comercial para más información sobre este producto.';
    const sku=document.createElement('p');sku.className='detail-reference';sku.textContent='Referencia: '+(p.sku||'—');
    const unit=document.createElement('p');unit.className='detail-format';
    const formatLabel={unidad:'Unidad',kg:'Kilogramo',caja:'Caja',saco:'Saco',bandeja:'Bandeja',litro:'Litro',otro:'Consultar'};
    unit.textContent='Formato: '+(p.variants?.[0]?.presentacion?.detalle||p.format||formatLabel[p.unit]||'Consultar');
    const availability=document.createElement('p');availability.className='detail-availability';
    availability.textContent=Number(p.stock)>0?'Disponible':'Consultar disponibilidad';
    info.append(category,h,desc,sku,unit,availability);
    if(Array.isArray(p.variants)&&p.variants.length){
      const wrapper=document.createElement('div');wrapper.className='detail-variant-group';
      const label=document.createElement('label');label.className='detail-variant-label';
      const id='uco-detail-variant';label.htmlFor=id;label.textContent='Marca y formato';
      const selector=document.createElement('select');selector.id=id;selector.className='detail-variant-select';
      p.variants.forEach((v,i)=>{
        const o=new Option([v.brand||v.name,v.format||v.unit,v.sku].filter(Boolean).join(' · '),String(i));selector.add(o);
      });
      const apply=()=>{const v=p.variants[Number(selector.value)];if(!v)return;
        sku.textContent='Referencia: '+(v.sku||p.sku||'—');
        availability.textContent=Number(v.stock)>0?'Disponible':'Consultar disponibilidad';
        unit.textContent='Formato: '+(v.presentacion?.detalle||v.format||formatLabel[v.unit]||'Consultar');
        availability.classList.toggle('is-unavailable',!(Number(v.stock)>0));
      };
      selector.addEventListener('change',apply);
      wrapper.append(label,selector);info.append(wrapper);apply();
    }
    host.append(img,info);dialog.showModal();
  }
  async load(){this.controller?.abort();this.controller=new AbortController();const signal=this.controller.signal;const status=this.querySelector('.catalog-status');status.textContent='Cargando productos…';try{
    let data,local=false;try{const u=new URL('./api/catalog-groups.php',location.href);u.searchParams.set('action','catalog');u.searchParams.set('page',this.page);u.searchParams.set('limit',this.limit);if(this.category)u.searchParams.set('category',this.category);if(this.query)u.searchParams.set('q',this.query);else if(this.priorityTerms.length)u.searchParams.set('priority',this.priorityTerms.join('|'));const r=await fetch(u,{signal,headers:{Accept:'application/json'}});if(!r.ok)throw Error('API no disponible');data=await r.json();if(!Array.isArray(data.products))throw Error('Respuesta inválida')}catch(err){if(signal.aborted)return;const r=await fetch('./data/catalog-demo.json',{signal});if(!r.ok)throw err;const raw=await r.json();let all=raw.products.filter(p=>(!this.category||p.category===this.category)&&(!this.query||[p.name,p.sku||''].join(' ').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es').includes(this.query.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es'))));data={categories:raw.categories,products:all.slice((this.page-1)*this.limit,this.page*this.limit),total:all.length,page:this.page,limit:this.limit,demo:true};local=true}
    if(signal.aborted)return;this.data=data;const filter=this.querySelector('select');filter.replaceChildren(new Option('Todas las familias',''),...data.categories.map(c=>new Option(c.name,c.slug)));filter.value=this.category;const grid=this.querySelector('[data-products]');grid.replaceChildren();for(const p of data.products){const card=document.createElement('article');card.className='product-card';const photo=document.createElement('div');photo.className='product-photo';const img=document.createElement('img');img.src=/^\.\/img\/(?:productos\/[a-f0-9]{32}\.webp|[\w-]+\.(webp|svg|png))$/.test(p.image||'')?p.image:'./img/00.webp';img.alt=p.name;img.loading='lazy';photo.append(img);const info=document.createElement('div');info.className='product-info';const tag=document.createElement('span');tag.textContent=(data.categories.find(c=>c.slug===p.category)||{}).name||p.category;const title=document.createElement('h3');
    const fullName=String(p.name||'').trim();
    // Abreviación solo visual: no modifica el nombre, SKU ni los datos originales.
    const shortName=ucoDisplayProductName(fullName);
    title.textContent=shortName;title.title=fullName;title.setAttribute('aria-label',fullName);
    title.classList.add('product-name-single');
    info.append(tag,title);const view=document.createElement('button');view.type='button';view.className='product-view';view.textContent='Ver ficha';view.setAttribute('aria-label','Ver ficha de '+p.name);view.onclick=()=>this.showDetail(p,data.categories);info.append(view);card.append(photo,info);grid.append(card)}
    status.textContent=`${data.total} productos${local?' · Modo demostración sin PHP':''}. Consulta los detalles en cada ficha. Sin precios públicos.`;if(!data.products.length)grid.textContent='No hay productos que coincidan con la búsqueda.';const pages=Math.max(1,Math.ceil(data.total/this.limit));this.querySelector('[data-page]').textContent=`Página ${this.page} de ${pages}`;this.querySelector('[data-prev]').disabled=this.page<=1;this.querySelector('[data-next]').disabled=this.page>=pages;
  }catch(e){if(!signal.aborted)status.textContent='No se pudo cargar el catálogo. Comprueba el servidor.'}}
}
customElements.define('uco-catalog',UcoCatalog);
