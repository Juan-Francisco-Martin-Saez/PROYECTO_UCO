/* Presentación del catálogo y seguridad del cliente. Sin llamadas HTTP. */
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
export class UcoClientProductList extends HTMLElement {
 renderProducts(products,esc){this.innerHTML=products.map(p=>{const src=String(p.image||'');const image=/^\.\/img\/(?:productos\/[a-zA-Z0-9_-]+\.webp|[0-9]{2}\.webp)$/.test(src)?'../'+src.slice(2):'../img/00.webp';return `<article class="client-card"><div class="client-photo"><img src="${esc(image)}" alt="${esc(p.name)}" loading="lazy"></div><div class="client-card-content"><small>${esc(p.family)}</small><h3 class="uco-client-short-name" title="${esc(p.name)}" aria-label="${esc(p.name)}">${esc(ucoDisplayProductName(p.name))}</h3><p class="uco-client-product-meta" title="${esc(p.name)}">${esc(p.unit||'unidad')} · Ref. ${esc(p.sku)}</p>${p.presentacion?`<p class="uco-client-presentation">${esc(p.presentacion.detalle)}</p>`:''}<small class="uco-client-availability">${+p.stock>0?'Disponible':'Consultar disponibilidad'}</small><div class="uco-client-price-row">${p.price_cents!==null&&+p.stock>0?`<label class="uco-client-units">Unidades<input type="number" inputmode="numeric" min="1" max="99999" step="1" value="1" data-product-units="${Number(p.id)}" aria-label="Unidades de ${esc(p.name)}"></label>`:'<span></span>'}<strong class="client-price ${p.price_cents===null?'missing':''}">${p.price_cents===null?'Consultar precio':(p.price_cents/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'})}</strong></div>${p.price_cents!==null&&+p.stock>0?`<button class="primary uco-add-cart" type="button" data-cart-add="${Number(p.id)}">Añadir al carrito</button>`:""}</div></article>`}).join('')||'<p>No se encontraron productos.</p>';}
}
export class UcoClientPasswordContent extends HTMLElement {
 connectedCallback(){if(!this.hasChildNodes())this.innerHTML=`<div class="modal"><header><h2>Mi seguridad</h2><button type="button" class="close" id="close">×</button></header><form class="security-form"><div class="field"><label>Contraseña actual</label><input type="password" name="old_password" required autocomplete="current-password"></div><div class="field"><label>Nueva contraseña</label><input type="password" name="new_password" required minlength="12" autocomplete="new-password"></div><div class="field"><label>Confirmar contraseña</label><input type="password" name="confirm" required minlength="12" autocomplete="new-password"></div><p class="alert" id="err"></p><div class="buttons"><button type="button" class="secondary" id="cancel">Cancelar</button><button type="submit" class="primary">Guardar contraseña</button></div></form></div>`;}
}
for(const [tag,cls] of [['uco-client-product-list',UcoClientProductList],['uco-client-password-content',UcoClientPasswordContent]])if(!customElements.get(tag))customElements.define(tag,cls);
