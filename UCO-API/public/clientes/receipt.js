/* UCO 5D — justificante exclusivamente de operaciones simuladas aprobadas. */
const money=n=>(Number(n)/100).toLocaleString('es-ES',{style:'currency',currency:'EUR'});
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
// Mostrar siempre la unidad real guardada en la línea del pedido.
const receiptQuantity=item=>String(item.quantity)+' ('+String(item.unit??'').trim().replace(/[()]/g,'') .trim().replace(/^$/,'ud.')+')';
/* Rellena los datos fiscales verificados en receipt-company.js, sin inventar valores. */
import {ucoReceiptCompany} from './receipt-company.js';
const companyLines=()=>[ucoReceiptCompany.fiscalName,ucoReceiptCompany.taxId?('NIF: '+ucoReceiptCompany.taxId):'',ucoReceiptCompany.address,[ucoReceiptCompany.postalCode,ucoReceiptCompany.city,ucoReceiptCompany.province].filter(Boolean).join(' · '),[ucoReceiptCompany.phone,ucoReceiptCompany.email].filter(Boolean).join(' · ')].filter(Boolean);
let logoPromise;
async function logoData(){
 if(!logoPromise)logoPromise=(async()=>{try{
  const r=await fetch('../img/uco-logo-blanco-calado.svg',{credentials:'same-origin'});if(!r.ok)return null;
  const svg=await r.text();const blob=new Blob([svg],{type:'image/svg+xml'});const src=URL.createObjectURL(blob);
  try{const img=new Image();await new Promise((resolve,reject)=>{img.onload=resolve;img.onerror=reject;img.src=src});const canvas=document.createElement('canvas');canvas.width=600;canvas.height=240;const ctx=canvas.getContext('2d');ctx.drawImage(img,0,0,600,240);return canvas.toDataURL('image/png')}finally{URL.revokeObjectURL(src)}
 }catch{return null}})();return logoPromise;
}
const methods={redsys:'Tarjeta bancaria (simulada)',paypal:'PayPal (simulado)',bizum:'Bizum (simulado)',transfer:'Transferencia (simulada)'};
let loader;
async function loadPdf(){
 if(window.jspdf?.jsPDF)return window.jspdf.jsPDF;
 if(!loader)loader=new Promise((resolve,reject)=>{
  const script=document.createElement('script');script.src='https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.2/jspdf.umd.min.js';
  script.crossOrigin='anonymous';script.onload=()=>resolve(window.jspdf?.jsPDF);script.onerror=()=>reject(Error('No se pudo cargar el generador PDF. Comprueba la conexión.'));document.head.append(script);
 });
 const result=await loader;if(!result)throw Error('Generador PDF no disponible');return result;
}
async function imageData(src){
 if(!src)return null;
 try{
  const response=await fetch(src,{credentials:'same-origin'});if(!response.ok)return null;
  const blob=await response.blob();const bmp=await createImageBitmap(blob);
  const canvas=document.createElement('canvas');canvas.width=150;canvas.height=150;
  const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,150,150);
  const ratio=Math.min(140/bmp.width,140/bmp.height);ctx.drawImage(bmp,(150-bmp.width*ratio)/2,(150-bmp.height*ratio)/2,bmp.width*ratio,bmp.height*ratio);
  bmp.close();return canvas.toDataURL('image/jpeg',.8);
 }catch{return null;}
}
async function buildPdf(d){
 const PDF=await loadPdf();const pdf=new PDF({orientation:'portrait',unit:'mm',format:'a4',compress:true});
 const W=210,H=297,L=15,R=195;let y=0,page=0;const logo=await logoData();
 const header=()=>{
  page++;pdf.setFillColor(35,47,93);pdf.rect(0,0,W,43,'F');
  if(logo){try{pdf.addImage(logo,'PNG',L,7,49,24,undefined,'FAST')}catch{pdf.setTextColor(255);pdf.setFont('helvetica','bold');pdf.setFontSize(22);pdf.text('UCO',L,23)}}
  else{pdf.setTextColor(255);pdf.setFont('helvetica','bold');pdf.setFontSize(22);pdf.text('UCO',L,23)}
  pdf.setTextColor(255);pdf.setFont('helvetica','bold');pdf.setFontSize(9);
  const lines=companyLines();const right=lines.length?lines:['UCO SA POBLA','Datos fiscales pendientes de configurar'];
  let yy=12;for(const t of right.slice(0,5)){const parts=pdf.splitTextToSize(String(t),102);for(const part of parts.slice(0,2)){pdf.text(part,R,yy,{align:'right'});yy+=5}}
  pdf.setFontSize(7);pdf.setFont('helvetica','normal');pdf.text('JUSTIFICANTE DE PEDIDO · SIMULACIÓN SIN COBRO REAL',R,39,{align:'right'});
  pdf.setTextColor(35,47,93);y=51;
 };
 const footer=()=>{
  pdf.setDrawColor(218,224,233);pdf.line(L,283,R,283);pdf.setTextColor(100,112,130);pdf.setFontSize(8);
  pdf.text('Documento de prueba - No es factura ni justificante bancario',L,289);
  pdf.text(String(page),R,289,{align:'right'});
 };
 const next=(needed)=>{if(y+needed>275){footer();pdf.addPage();header();}};
 const heading=t=>{next(15);pdf.setTextColor(35,47,93);pdf.setFont('helvetica','bold');pdf.setFontSize(11);pdf.text(t,L,y+5);y+=12;};
 const line=(label,value)=>{next(9);pdf.setFont('helvetica','normal');pdf.setFontSize(9);pdf.setTextColor(50,60,78);pdf.text(String(label),L,y);pdf.setFont('helvetica','bold');pdf.text(String(value),R,y,{align:'right'});y+=8;};
 header();heading('Datos del pedido');line('Numero de pedido','PED-'+d.order_id);line('Referencia',d.reference);line('Fecha',d.created_at);
 heading('Datos de entrega');
 const address=d.delivery||{};
 for(const s of [address.contact,address.phone,address.address,[address.postal_code,address.city].filter(Boolean).join(' '),address.province,address.notes].filter(Boolean)){
  const wrapped=pdf.splitTextToSize(String(s),175);next(wrapped.length*5+2);pdf.setFont('helvetica','normal');pdf.setFontSize(9);pdf.text(wrapped,L,y);y+=wrapped.length*5+2;
 }
 heading('Productos');
 for(const item of d.items){
  const name=pdf.splitTextToSize(String(item.name),107);const h=Math.max(19,name.length*5+8);next(h);
  pdf.setDrawColor(228,233,239);pdf.roundedRect(L,y-3,180,h-2,2,2,'S');
  const img=await imageData(item.image);if(img)pdf.addImage(img,'JPEG',L+2,y-1,16,16);
  pdf.setFont('helvetica','bold');pdf.setFontSize(9);pdf.setTextColor(35,47,93);pdf.text(name,L+22,y+3);
  pdf.setFont('helvetica','normal');pdf.setFontSize(8);pdf.text(String(item.sku||'')+'  |  '+receiptQuantity(item)+' x '+money(item.unit_price_cents),L+22,y+name.length*5+4);
  pdf.setFont('helvetica','bold');pdf.text(money(item.line_cents),R-3,y+6,{align:'right'});y+=h+2;
 }
 heading('Desglose economico');line('Subtotal',money(d.subtotal_cents));line('IVA',money(d.tax_cents));line('Transporte',money(d.shipping_cents));
 next(15);pdf.setFillColor(239,246,229);pdf.roundedRect(L,y-4,180,13,2,2,'F');pdf.setTextColor(35,47,93);pdf.setFontSize(12);pdf.setFont('helvetica','bold');pdf.text('TOTAL FINAL',L+3,y+4);pdf.text(money(d.total_cents),R-3,y+4,{align:'right'});y+=19;
 heading('Datos de la operacion simulada');line('Metodo',methods[d.method]||d.method);line('Resultado','APROBACION SIMULADA');line('Referencia',d.reference);line('Fecha de operacion',d.operation_at);
 next(15);pdf.setFontSize(9);pdf.setTextColor(130,65,30);pdf.text('OPERACION FICTICIA. NO ACREDITA NINGUN PAGO REAL.',L,y+5);footer();
 return pdf;
}
export async function openUcoReceipt(orderId,source='customer'){
 const endpoint=source==='admin'?'../api/admin.php?action=account_receipt&order_id=':'../api/order-receipt.php?order_id=';
 const r=await fetch(endpoint+encodeURIComponent(orderId),{credentials:'same-origin',cache:'no-store'});
 const raw=await r.text();let d;try{d=JSON.parse(raw)}catch{throw Error('El servidor no ha devuelto JSON válido (HTTP '+r.status+'). Comprueba el registro de errores PHP de order-receipt.php.')}if(!r.ok)throw Error((d.error||'No se pudo recuperar el justificante')+' (HTTP '+r.status+')');
 let url=null;let fallback=false;
 try{const PDF=await buildPdf(d);const blob=PDF.output('blob');url=URL.createObjectURL(blob)}
 catch(e){if(!/generador PDF|cargar el generador PDF|conexión/i.test(String(e.message)))throw e;fallback=true;}
 const dialog=document.createElement('dialog');dialog.className='uco-receipt-dialog';
 const head=document.createElement('header');head.className='uco-receipt-head';
 const title=document.createElement('strong');title.textContent='UCO · Justificante PED-'+d.order_id;
 const buttons=document.createElement('div');
 const download=document.createElement('a');download.href=url||'#';download.download='UCO_Pedido_'+d.order_id+'_SIMULADO.pdf';download.textContent=fallback?'Guardar como PDF (imprimir)':'Descargar PDF';
 const print=document.createElement('button');print.type='button';print.textContent='Imprimir';
 const close=document.createElement('button');close.type='button';close.textContent='Cerrar';
 buttons.append(download,print,close);head.append(title,buttons);
 const iframe=document.createElement('iframe');iframe.title='Vista previa del justificante';
 if(fallback){
  const rows=d.items.map(i=>'<tr><td>'+esc(i.sku)+'</td><td>'+esc(i.name)+'</td><td>'+esc(receiptQuantity(i))+'</td><td>'+esc(money(i.line_cents))+'</td></tr>').join('');
  const a=d.delivery||{};const address=[a.contact,a.phone,a.address,[a.postal_code,a.city].filter(Boolean).join(' '),a.province].filter(Boolean).map(esc).join('<br>');
  iframe.srcdoc='<!doctype html><html lang="es"><head><meta charset="utf-8"><style>body{font:14px Arial;color:#232f5d;margin:28px;line-height:1.4}.uco-doc-header{background:#232f5d;color:white;padding:17px 22px;display:flex;align-items:center;justify-content:space-between;gap:24px;min-height:116px;box-sizing:border-box}.uco-doc-header img{display:block;width:132px;height:78px;max-width:34%;object-fit:contain;object-position:left center;flex-shrink:0}.uco-doc-header .uco-company{text-align:right;font-size:10.5px;line-height:1.45;max-width:64%;overflow-wrap:anywhere;flex:1}h1{margin:0}h2{margin-top:25px}table{border-collapse:collapse;width:100%}td,th{border-bottom:1px solid #ddd;padding:9px;text-align:left}td:nth-child(3),th:nth-child(3){text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}td:last-child,th:last-child{text-align:right}footer{margin-top:28px;color:#98552b;font-weight:bold}thead{display:table-header-group}tr{break-inside:avoid;page-break-inside:avoid}@media print{@page{size:A4;margin:14mm 14mm 16mm}body{margin:0}.uco-doc-header{position:static;top:auto;left:auto;right:auto;height:auto;min-height:30mm;margin:0 0 9mm;padding:5mm 6mm;box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact;break-inside:avoid;page-break-inside:avoid}thead{display:table-header-group}}</style></head><body><header class="uco-doc-header"><img src="/img/uco-logo-blanco-calado.svg" alt="UCO"><div class="uco-company">'+companyLines().map(esc).join('<br>')+'<div>JUSTIFICANTE · SIMULACIÓN SIN COBRO REAL</div></div></header><h2>Pedido PED-'+esc(d.order_id)+'</h2><p>Referencia: '+esc(d.reference)+'<br>Fecha: '+esc(d.created_at)+'</p><h2>Datos de entrega</h2><p>'+address+'</p><h2>Productos</h2><table><thead><tr><th>Referencia</th><th>Producto</th><th>Cantidad</th><th>Importe</th></tr></thead><tbody>'+rows+'</tbody></table><h2>Resumen</h2><p>Subtotal: '+esc(money(d.subtotal_cents))+'<br>IVA: '+esc(money(d.tax_cents))+'<br>Transporte: '+esc(money(d.shipping_cents))+'</p><h2>TOTAL: '+esc(money(d.total_cents))+'</h2><p>Operación: '+esc(methods[d.method]||d.method)+' · APROBACIÓN SIMULADA</p><footer>DOCUMENTO DE PRUEBA · NO ES FACTURA NI JUSTIFICANTE BANCARIO</footer></body></html>';
  const warning=document.createElement('p');warning.textContent='Vista alternativa: el generador PDF externo no está disponible. Pulsa «Guardar como PDF (imprimir)» y selecciona «Guardar como PDF» en el navegador.';warning.className='uco-receipt-fallback-note';head.after(warning);
 }else iframe.src=url;
 dialog.append(head,iframe);document.body.append(dialog);
 const cleanup=()=>{dialog.close();dialog.remove();if(url)setTimeout(()=>URL.revokeObjectURL(url),30000)};
 close.onclick=cleanup;dialog.addEventListener('cancel',e=>{e.preventDefault();cleanup()});
 print.onclick=()=>{try{iframe.contentWindow?.focus();iframe.contentWindow?.print()}catch{if(url)window.open(url,'_blank','noopener')}};
 if(fallback){download.removeAttribute('download');download.onclick=e=>{e.preventDefault();print.click()};}
 dialog.showModal();
}
