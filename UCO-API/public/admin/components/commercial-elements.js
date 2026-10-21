/* UCO: elementos comerciales reutilizables, sin Shadow DOM para respetar CSS heredado. */
export class UcoTariffView extends HTMLElement {
 renderFrame(tabs,active){
  this.innerHTML=`<div class="tariff-title"><div><span class="eyebrow">PRECIOS · ADMINISTRACIÓN</span><h3>Tarifas y promociones</h3><p>Configura los importes base y las promociones generales</p></div></div><div class="tariff-tabs" role="tablist" aria-label="Gestión de precios">${tabs.map(([id,name])=>`<button type="button" role="tab" aria-selected="${active===id}" data-tab="${id}">${name}</button>`).join('')}</div><div id="tariff-panel"></div><p class="tip tariff-help">Prioridad: precio individual del cliente → promoción o mejor precio de grupo → tarifa general. Los descuentos de grupo y promociones no se suman. <span class="uco-tariff-final-sentence">Sin tarifa general ni precio especial, se muestra «Consultar&nbsp;precio».</span></p>`;
 }
}
export class UcoTableScroll extends HTMLElement {
 connectedCallback(){this.style.display='block'}
}
if(!customElements.get('uco-tariff-view'))customElements.define('uco-tariff-view',UcoTariffView);
if(!customElements.get('uco-table-scroll'))customElements.define('uco-table-scroll',UcoTableScroll);

export class UcoCommercialContent extends HTMLElement {}
if(!customElements.get('uco-commercial-content'))customElements.define('uco-commercial-content',UcoCommercialContent);
