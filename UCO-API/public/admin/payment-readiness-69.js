"use strict";
const statusNode=document.getElementById("status");
const listNode=document.getElementById("checks");
async function loadReadiness(){
 statusNode.textContent="Consultando configuración…";listNode.replaceChildren();
 try{
  const response=await fetch("../api/payment-readiness-69.php",{credentials:"same-origin",cache:"no-store"});
  const data=await response.json();
  if(!response.ok||!data.ok)throw new Error(data.error||"No disponible");
  for(const item of data.checks){
   const li=document.createElement("li");
   const strong=document.createElement("strong");
   strong.textContent=(item.ok?"✓ ":"✕ ")+item.title;
   const small=document.createElement("small");
   small.textContent=item.detail;
   li.append(strong,small);listNode.append(li);
  }
  const count=data.checks.filter(item=>item.ok).length;
  statusNode.textContent=count+" de "+data.checks.length+" comprobaciones estáticas correctas. Cobros reales: "+(data.gateways_enabled?"configuración general activada; revisar antes de usar":"desactivados")+".";
 }catch(error){statusNode.textContent="No se pudo consultar el diagnóstico: "+error.message;}
}
document.getElementById("refresh").addEventListener("click",loadReadiness);
loadReadiness();
