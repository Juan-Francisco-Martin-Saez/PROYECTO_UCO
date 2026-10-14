import './components/uco-catalog.js';
import './components/uco-header.js';
import './components/uco-home.js';
import './components/uco-footer.js';

// Navegación con encuadre exacto: el inicio de cada sección queda bajo la cabecera fija.
const ids=['inicio','familias','seleccion','nosotros','pedidos','contacto'];
const header=document.querySelector('uco-header');
const navLinks=()=>[...document.querySelectorAll('.main-nav a')];
const headerHeight=()=>header?.getBoundingClientRect().height||0;
const go=(id,smooth=true)=>{
 const target=document.getElementById(id);if(!target)return;
 const top=Math.max(0,window.scrollY+target.getBoundingClientRect().top-headerHeight());
 window.scrollTo({top,behavior:smooth&&!matchMedia('(prefers-reduced-motion: reduce)').matches?'smooth':'instant'});
};
const updateActive=()=>{
 const line=headerHeight()+Math.min(100,innerHeight*.14);
 let current=ids[0];for(const id of ids){const el=document.getElementById(id);if(el&&el.getBoundingClientRect().top<=line)current=id;}
 navLinks().forEach(a=>{const active=a.hash==='#'+current;a.classList.toggle('active',active);if(active)a.setAttribute('aria-current','location');else a.removeAttribute('aria-current')});
};
document.addEventListener('click',e=>{
 const link=e.target.closest('a[href^="#"]');if(!link)return;
 const id=decodeURIComponent(link.hash.slice(1));if(!ids.includes(id))return;
 e.preventDefault();history.pushState(null,'','#'+id);go(id);
});
let raf=0;addEventListener('scroll',()=>{if(raf)return;raf=requestAnimationFrame(()=>{raf=0;updateActive()})},{passive:true});
addEventListener('resize',()=>{document.documentElement.style.setProperty('--header-height',headerHeight()+'px');updateActive()},{passive:true});
addEventListener('popstate',()=>{if(ids.includes(location.hash.slice(1)))go(location.hash.slice(1),false);updateActive()});
requestAnimationFrame(()=>{document.documentElement.style.setProperty('--header-height',headerHeight()+'px');if(ids.includes(location.hash.slice(1)))go(location.hash.slice(1),false);updateActive()});
