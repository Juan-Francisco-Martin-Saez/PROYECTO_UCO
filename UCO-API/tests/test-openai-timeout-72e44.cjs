const fs=require('fs'),path=require('path'),vm=require('vm'),assert=require('assert');
const root=path.join(__dirname,'..');
const php=fs.readFileSync(path.join(root,'public/api/assistant-openai.php'),'utf8');
const js=fs.readFileSync(path.join(root,'public/assistant/assistant.js'),'utf8');
let checks=0;function ok(value,label){assert(value,label);checks++;console.log('OK',label)}
const connect=php.match(/CURLOPT_CONNECTTIMEOUT\s*=>\s*(\d+)/);
const server=php.match(/CURLOPT_TIMEOUT\s*=>\s*(\d+)/);
const a=js.indexOf('async function askProtectedOpenAI(question){');const b=js.indexOf('// 7.2E.22: derivación automática',a);
const segment=js.slice(a,b);const client=segment.match(/setTimeout\(\(\)=>ctrl\.abort\(\),(\d+)\)/);
ok(!!connect&&!!server&&!!client,'Los tres límites están declarados');
ok(Number(connect[1])<=Number(server[1]),'Conexión <= tiempo servidor');
ok(Number(server[1])*1000<Number(client[1]),'Servidor finaliza antes que navegador');
ok(Number(client[1])-Number(server[1])*1000>=3000,'Margen de al menos 3 segundos');
ok(php.includes('CURLOPT_RETURNTRANSFER=>true'),'Respuesta PHP conservada');
ok(segment.includes('finally{clearTimeout(timer)}'),'Limpieza del temporizador conservada');
ok(segment.includes('if(!isSafeGeneralQuestion72e39(question))return null;'),'Privacidad previa a fetch');
(async()=>{let count=0;const context={normalizeUco:s=>s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''),AbortController:class{constructor(){this.signal={}}abort(){}},setTimeout:()=>1,clearTimeout:()=>{},fetch:async()=>{count++;throw Error('network')}};vm.createContext(context);vm.runInContext(js.slice(js.indexOf('function isSafeGeneralQuestion72e39(question){'),b),context);ok(await context.askProtectedOpenAI('¿Qué es una API?')===null,'Fallo externo devuelve null');ok(count===1,'Una sola petición fallida');ok(await context.askProtectedOpenAI('Mi pedido 123')===null,'Dato comercial bloqueado');ok(count===1,'Dato comercial no origina petición');console.log('TOTAL '+checks+'/'+checks)})().catch(e=>{console.error(e);process.exit(1)});
