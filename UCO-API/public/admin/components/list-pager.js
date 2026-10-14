/* Paginación reutilizable: búsqueda y 10 registros por página, sin alterar el origen de datos. */
export function mountPager(host,{search=true,placeholder='Buscar por nombre o referencia',pageSize=10}={}){
 if(!host)return;const table=host.querySelector('table');if(!table||!table.tBodies.length)return;
 const rows=[...table.tBodies[0].rows];const previous=host.querySelector(':scope > .uco-pager-tools');if(previous)previous.remove();
 const footer=host.querySelector(':scope > .uco-pager-controls');if(footer)footer.remove();
 const tools=document.createElement('div');tools.className='uco-pager-tools';const controls=document.createElement('div');controls.className='uco-pager-controls';
 if(search){const input=document.createElement('input');input.type='search';input.placeholder=placeholder;input.setAttribute('aria-label',placeholder);tools.append(input);input.addEventListener('input',()=>{page=1;draw()})}
 const input=tools.querySelector('input');let page=1;
 function draw(){const q=(input?.value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();const matches=rows.filter(r=>!q||r.textContent.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().includes(q));const total=Math.max(1,Math.ceil(matches.length/pageSize));page=Math.min(page,total);const shown=new Set(matches.slice((page-1)*pageSize,page*pageSize));rows.forEach(r=>r.hidden=!shown.has(r));controls.replaceChildren();const summary=document.createElement('span');summary.textContent=`${matches.length} registros · Página ${page} de ${total}`;const prev=document.createElement('button');prev.type='button';prev.textContent='Anterior';prev.disabled=page===1;prev.onclick=()=>{page--;draw()};const next=document.createElement('button');next.type='button';next.textContent='Siguiente';next.disabled=page===total;next.onclick=()=>{page++;draw()};controls.append(summary,prev,next)}
 if(search)host.prepend(tools);host.append(controls);draw();
}
